<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class CheckExcelHeadings extends Command
{
    protected $signature = 'simawar:check-headings {file : Path relatif ke storage/app/}';
    protected $description = 'Debug: tampilkan heading keys hasil konversi Maatwebsite dari file Excel Simawar';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!file_exists(storage_path("app/{$filePath}"))) {
            $this->error("File tidak ditemukan: storage/app/{$filePath}");
            return self::FAILURE;
        }

        $this->info("Reading headings from: storage/app/{$filePath}");
        $this->newLine();

        // Anonymous import class — hanya baca 1 baris untuk cek heading keys
        $rows = collect();

        Excel::import(new class($rows) implements ToCollection, WithHeadingRow {
            private Collection $container;

            public function __construct(Collection &$container)
            {
                $this->container = &$container;
            }

            public function collection(Collection $rows): void
            {
                // Ambil baris pertama saja
                if ($rows->isNotEmpty()) {
                    $this->container->push($rows->first());
                }
            }

            public function headingRow(): int
            {
                return 2; // Baris 1 = judul "Simawar", baris 2 = header
            }
        }, storage_path("app/{$filePath}"));

        if ($rows->isEmpty()) {
            $this->error("Tidak ada data ditemukan di file.");
            return self::FAILURE;
        }

        $firstRow = $rows->first();
        $keys = $firstRow->keys()->toArray();

        $this->info("Total kolom: " . count($keys));
        $this->newLine();

        // Tampilkan semua keys dengan index
        $this->info("=== ALL HEADING KEYS ===");
        foreach ($keys as $index => $key) {
            $value = $firstRow[$key];
            $displayValue = is_null($value) ? '(null)' : (string) $value;
            if (strlen($displayValue) > 50) {
                $displayValue = substr($displayValue, 0, 50) . '...';
            }
            $this->line(sprintf("  [%02d] %-30s = %s", $index, $key, $displayValue));
        }

        $this->newLine();

        // Cari pattern kolom revenue/cost/pnl
        $this->info("=== DETECTED MONTH COLUMNS ===");
        $revKeys = array_filter($keys, fn($k) => str_starts_with((string) $k, 'rev'));
        $costKeys = array_filter($keys, fn($k) => str_starts_with((string) $k, 'cost'));
        $pnlKeys = array_filter($keys, fn($k) => str_starts_with((string) $k, 'pnl'));

        $this->line("  Revenue columns (" . count($revKeys) . "): " . implode(', ', $revKeys));
        $this->line("  Cost columns (" . count($costKeys) . "): " . implode(', ', $costKeys));
        $this->line("  PnL columns (" . count($pnlKeys) . "): " . implode(', ', $pnlKeys));

        $this->newLine();
        $this->info("Gunakan output di atas untuk memverifikasi mapping di SimawarPnLImport.");

        return self::SUCCESS;
    }
}
