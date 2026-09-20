<?php

namespace App\Console\Commands;

use App\Models\ListrikPln;
use App\Models\StatusPembayaran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportPaymentStatusDataset extends Command
{
    protected $signature = 'dataset:import-payment-status';
    protected $description = 'Import monthly payment data from DATASET/03 Electricity/Centralized/Payment/Payment Done into status_pembayaran table';

    private const BULAN_MAP = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        $baseDir = base_path('DATASET/03 Electricity/Centralized/Payment/Payment Done');

        if (!is_dir($baseDir)) {
            $this->warn("Folder $baseDir tidak ditemukan.");
            return self::FAILURE;
        }

        $plnMap = ListrikPln::query()->pluck('id', 'id_pelanggan')->toArray();
        $this->info("Memuat " . count($plnMap) . " ID Pelanggan PLN untuk mapping...");

        $totalInserted = DB::transaction(function () use ($baseDir, $plnMap): int {
            DB::table('status_pembayaran')->delete();
            $totalInserted = 0;

            foreach (['2025', '2026'] as $year) {
                $yearDir = $baseDir . '/' . $year;
                if (!is_dir($yearDir)) continue;

                $files = glob($yearDir . '/*.xlsx');
                foreach ($files as $file) {
                    $filename = basename($file, '.xlsx');
                    // Format: Tagihan_Januari_2026
                    preg_match('/Tagihan_([A-Za-z]+)_(\d{4})/i', $filename, $matches);
                    if (!$matches) continue;

                    $bulanStr = strtolower($matches[1]);
                    $tahun = (int) $matches[2];
                    $bulan = self::BULAN_MAP[$bulanStr] ?? null;
                    if (!$bulan) continue;

                    $this->info("Mengimpor: $filename (Bulan $bulan / $tahun)...");

                    $spreadsheet = IOFactory::load($file);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray(null, true, false, false);

                    // Row 0 = Title, Row 1 = Heading, Data starts at Row 2
                    $batch = [];
                    for ($i = 2; $i < count($rows); $i++) {
                        $r = $rows[$i];
                        $idPelanggan = trim((string)($r[1] ?? ''));
                        $hargaRaw = $r[8] ?? 0;
                        $updateBy = trim((string)($r[9] ?? 'Dataset Import'));
                        $tanggalRaw = $r[10] ?? null;

                        if ($idPelanggan === '' || !isset($plnMap[$idPelanggan])) {
                            continue;
                        }

                        $batch[] = [
                            'listrik_pln_id' => $plnMap[$idPelanggan],
                            'id_pelanggan'   => $idPelanggan,
                            'bulan'          => $bulan,
                            'tahun'          => $tahun,
                            'harga'          => $this->parseHarga($hargaRaw),
                            'remark'         => 'Lunas (Payment Done)',
                            'update_by'      => $updateBy ?: 'System Import',
                            'tanggal'        => $this->parseTanggal($tanggalRaw, $bulan, $tahun),
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ];
                    }

                    foreach (array_chunk($batch, 1000) as $chunk) {
                        DB::table('status_pembayaran')->insert($chunk);
                        $totalInserted += count($chunk);
                    }
                }
            }

            return $totalInserted;
        });

        $this->info("✓ Selesai! Berhasil mengimpor {$totalInserted} data status pembayaran.");
        return self::SUCCESS;
    }

    private function parseHarga($raw): float
    {
        if ($raw === null || $raw === '') return 0.0;

        if (is_numeric($raw)) {
            $num = (float) $raw;
            // Jika angka < 10000 dan memiliki pecahan desimal (akibat penulisan titik ribuan di Excel misal 467.216 -> Rp 467.216)
            if ($num > 0 && $num < 10000 && floor($num) != $num) {
                $num = round($num * 1000);
            }
            return $num;
        }

        $str = trim((string) $raw);
        $str = str_replace(['Rp', 'RP', 'rp', ' ', "\xc2\xa0"], '', $str);

        if (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (str_contains($str, '.')) {
            $str = str_replace('.', '', $str);
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return is_numeric($str) ? (float) $str : 0.0;
    }

    private function parseTanggal(mixed $raw, int $bulan, int $tahun): string
    {
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        if (is_numeric($raw) && (float) $raw >= 20000 && (float) $raw <= 80000) {
            return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
        }

        if ($raw !== null && trim((string) $raw) !== '') {
            $timestamp = strtotime((string) $raw);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return sprintf('%04d-%02d-15', $tahun, $bulan);
    }
}
