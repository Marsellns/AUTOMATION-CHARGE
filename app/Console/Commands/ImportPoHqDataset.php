<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class ImportPoHqDataset extends Command
{
    protected $signature = 'dataset:import-po-hq';

    protected $description = 'Import snapshot Data PO HQ ke tabel po_hq';

    public function handle(): int
    {
        $file = base_path('DATASET/04 PO Monitoring/PO Varcost/Data PO HQ.xlsx');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        try {
            $rows = $this->readRows($file);
        } catch (\Throwable $exception) {
            $this->error('Import dibatalkan: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->error('Import dibatalkan: tidak ada baris PO valid pada sumber.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($rows): void {
            // The source is authoritative and replaces the prior demo snapshot.
            DB::table('po_hq')->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('po_hq')->insert($chunk);
            }
        });

        $stored = DB::table('po_hq')->count();

        if ($stored !== count($rows)) {
            throw new RuntimeException("Verifikasi gagal: sumber ".count($rows)." baris, database {$stored} baris.");
        }

        $this->info("✓ PO HQ tersinkron: {$stored} baris dari Data PO HQ.xlsx.");

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function readRows(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($file)->getActiveSheet();
        $records = [];
        $poNumbers = [];

        // Row 1 is the title and row 2 is the source header.
        foreach ($sheet->getRowIterator(3) as $row) {
            $values = $sheet->rangeToArray(
                'A'.$row->getRowIndex().':L'.$row->getRowIndex(),
                null,
                true,
                false,
                false,
            )[0];

            $poNumber = $this->text($values[2] ?? null);

            if ($poNumber === null) {
                continue;
            }

            if (isset($poNumbers[$poNumber])) {
                throw new RuntimeException("PO Number duplikat pada sumber: {$poNumber}.");
            }

            $expenseType = $this->expenseType($values[6] ?? null, $poNumber);
            $sourceUpdatedAt = $this->dateTime($values[11] ?? null, $poNumber);

            $records[] = [
                'po_number' => $poNumber,
                'agreement_number' => $this->text($values[3] ?? null),
                'vendor_name' => $this->text($values[4] ?? null) ?? '-',
                'description' => $this->text($values[5] ?? null),
                'expense_type' => $expenseType,
                'status' => $this->text($values[7] ?? null) ?? 'Draft',
                'location' => $this->text($values[8] ?? null),
                'remark' => $this->text($values[9] ?? null),
                'update_by' => $this->text($values[10] ?? null),
                'source_updated_at' => $sourceUpdatedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $poNumbers[$poNumber] = true;
        }

        return $records;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' || $value === '-' ? null : $value;
    }

    private function expenseType(mixed $value, string $poNumber): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            'capex' => 'Capex',
            'opex' => 'Opex',
            default => throw new RuntimeException("Capex/Opex tidak valid untuk PO {$poNumber}: {$value}."),
        };
    }

    private function dateTime(mixed $value, string $poNumber): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d H:i:s');
        }

        foreach (['d-m-Y H:i', 'd-m-Y H:i:s', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = Carbon::createFromFormat($format, trim((string) $value));

            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new RuntimeException("Tanggal pembaruan tidak valid untuk PO {$poNumber}: {$value}.");
    }
}
