<?php

namespace App\Console\Commands;

use App\Models\ListrikPln;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPaymentPlnMasterDataset extends Command
{
    protected $signature = 'dataset:import-payment-pln-master';

    protected $description = 'Import historical Flagging Payment PLN from the Centralized master workbook';

    private const MONTHS = [
        'januari' => 1, 'january' => 1, 'jan' => 1,
        'februari' => 2, 'february' => 2, 'feb' => 2,
        'maret' => 3, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'mei' => 5, 'may' => 5,
        'juni' => 6, 'june' => 6, 'jun' => 6,
        'juli' => 7, 'july' => 7, 'jul' => 7,
        'agustus' => 8, 'agst' => 8, 'aug' => 8,
        'september' => 9, 'sept' => 9, 'sep' => 9,
        'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11, 'desember' => 12, 'december' => 12, 'des' => 12, 'dec' => 12,
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        $file = base_path('DATASET/03 Electricity/Centralized/Listrik All/Data Master PLN Centralized_Flagging September 2026_Eastern Jabotabek.xlsx');

        if (!is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");
            return self::FAILURE;
        }

        $statusBySite = ListrikPln::query()->pluck('status_aktif_site', 'site_id')->mapWithKeys(
            fn ($status, $site) => [strtoupper(trim((string) $site)) => $status]
        );
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);
        $headers = array_map(fn ($header) => trim((string) $header), $rows[2] ?? []);
        $periodColumns = [];

        foreach ($headers as $index => $header) {
            if (!preg_match('/^flagging\s+(.+?)\s+(\d{4})$/i', $header, $match)) {
                continue;
            }

            $month = self::MONTHS[strtolower(trim($match[1]))] ?? null;
            if ($month !== null) {
                $periodColumns[] = [$index, $month, (int) $match[2]];
            }
        }

        if ($periodColumns === []) {
            $this->error('Kolom Flagging historis tidak ditemukan.');
            return self::FAILURE;
        }

        $inserted = DB::transaction(function () use ($rows, $periodColumns, $statusBySite): int {
            DB::table('payment_pln_master_monthly')->delete();
            $batch = [];
            $inserted = 0;

            foreach (array_slice($rows, 3) as $row) {
                $siteId = trim((string) ($row[2] ?? ''));
                if ($siteId === '') {
                    continue;
                }

                foreach ($periodColumns as [$index, $month, $year]) {
                    $raw = $row[$index] ?? null;
                    $amount = $this->parseAmount($raw);
                    $batch[] = [
                        'id_pelanggan' => $this->clean($row[1] ?? null),
                        'site_id' => $siteId,
                        'site_name' => $this->clean($row[3] ?? null),
                        'bulan' => $month,
                        'tahun' => $year,
                        'amount' => $amount,
                        'is_paid' => $amount !== null && $amount > 0,
                        'status_aktif_site' => $statusBySite[strtoupper($siteId)] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= 1000) {
                        DB::table('payment_pln_master_monthly')->insert($batch);
                        $inserted += count($batch);
                        $batch = [];
                    }
                }
            }

            if ($batch !== []) {
                DB::table('payment_pln_master_monthly')->insert($batch);
                $inserted += count($batch);
            }

            return $inserted;
        });

        $this->info("✓ Imported {$inserted} monthly payment records.");
        return self::SUCCESS;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' || $value === '-' ? null : $value;
    }

    private function parseAmount(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^0-9,.\-]/', '', (string) $value);
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $fraction = substr($value, strrpos($value, $decimalSeparator) + 1);
            if (strlen($fraction) <= 2) {
                $value = str_replace($decimalSeparator === ',' ? '.' : ',', '', $value);
                $value = str_replace($decimalSeparator, '.', $value);
            } else {
                $value = str_replace([',', '.'], '', $value);
            }
        } elseif ($lastComma !== false) {
            $fraction = substr($value, $lastComma + 1);
            $value = strlen($fraction) === 3
                ? str_replace(',', '', $value)
                : str_replace(',', '.', $value);
        } elseif ($lastDot !== false) {
            $fraction = substr($value, $lastDot + 1);
            if (strlen($fraction) === 3) {
                $value = str_replace('.', '', $value);
            }
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
