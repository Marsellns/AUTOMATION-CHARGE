<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use Throwable;

/**
 * Cek heading & jumlah baris file Excel secara GENERIC (read-only).
 *
 * Dipakai untuk inspeksi dataset sebelum menentukan mapping import.
 * Tidak mengubah data apa pun; hanya membaca baris-baris awal tiap sheet
 * (read filter) + dimensi sheet, sehingga aman untuk file besar.
 *
 * Cara pakai:
 *   php artisan dataset:inspect-excel DATASET/nama-file.xlsx   (satu file)
 *   php artisan dataset:inspect-excel DATASET                  (semua file di folder, rekursif)
 */
class InspectDatasetExcel extends Command
{
    protected $signature = 'dataset:inspect-excel {path : Path file Excel atau folder (relatif ke base project)}';
    protected $description = 'Inspeksi read-only: heading asli tiap sheet + jumlah baris file Excel di DATASET';

    private const EXTENSIONS = ['xlsx', 'xls', 'csv'];

    public function handle(): int
    {
        // File besar (PnL 9,6 MB ~ ratusan ribu baris) butuh memori ekstra
        // hanya jika fallback full-load .xls terpakai.
        ini_set('memory_limit', '-1');

        $path = base_path($this->argument('path'));

        if (! file_exists($path)) {
            $this->error("Path tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $files = is_dir($path)
            ? $this->collectExcelFiles($path)
            : [$path];

        if ($files === []) {
            $this->warn('Tidak ada file Excel (xlsx/xls/csv) yang ditemukan.');

            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $this->inspectFile($file);
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function collectExcelFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && in_array(strtolower($item->getExtension()), self::EXTENSIONS, true)) {
                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function inspectFile(string $file): void
    {
        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
        $sizeMb = round(filesize($file) / 1048576, 2);

        $this->newLine();
        $this->info("FILE: {$relative}  ({$sizeMb} MB)");
        $this->line(str_repeat('-', 100));

        try {
            $reader = IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true);
            // Hanya baris 1-3 yang benar-benar dibaca ke memori;
            // jumlah baris diambil dari dimensi sheet (murah).
            $reader->setReadFilter(new class implements IReadFilter {
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= 3;
                }
            });

            $spreadsheet = $reader->load($file);
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            foreach ($spreadsheet->getSheetNames() as $i => $name) {
                $sheet = $spreadsheet->getSheet($i);
                $highestColumn = $sheet->getHighestColumn();
                // getHighestRow() dari load ber-filter hanya menghitung baris
                // yang dibaca (1-3), jadi jumlah baris asli dihitung terpisah.
                $highestRow = $this->countRows($file, $ext, $i, $sheet);

                $this->line("SHEET [{$i}] \"{$name}\" — baris: {$highestRow}, kolom terakhir: {$highestColumn}");

                foreach ([1, 2] as $rowNo) {
                    $values = $this->readRow($sheet, $highestColumn, $rowNo);
                    $nonEmpty = array_filter($values, fn ($v) => $v !== null && trim((string) $v) !== '');

                    if ($nonEmpty === []) {
                        $this->line("  baris {$rowNo}: (kosong)");
                        continue;
                    }

                    $this->line("  baris {$rowNo} (asli):");
                    foreach ($values as $idx => $value) {
                        if ($value === null || trim((string) $value) === '') {
                            continue;
                        }
                        $text = $this->truncate((string) $value);
                        $this->line(sprintf('    [%02d] %s  ->  laravel-excel key: "%s"', $idx, $text, $this->formatHeading((string) $value)));
                    }
                }

                // Estimasi baris data jika header ada di baris 1.
                $dataRows = max(0, $highestRow - 1);
                $this->line("  => jika header baris 1: {$dataRows} baris data");
            }
        } catch (Throwable $e) {
            $this->error('  GAGAL DIBACA: ' . $e->getMessage());
        }
    }

    /** @return array<int, mixed> nilai baris per indeks kolom (0-based) */
    private function readRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $highestColumn, int $rowNo): array
    {
        $columnCount = Coordinate::columnIndexFromString($highestColumn);
        $values = [];

        for ($col = 1; $col <= $columnCount; $col++) {
            $values[] = $sheet->getCell([$col, $rowNo])->getValue();
        }

        return $values;
    }

    /**
     * Hitung jumlah baris asli tanpa memuat seluruh file ke memori:
     * - xlsx: streaming XML <row> di xl/worksheets/sheet{n}.xml
     *   (fallback: scan sharedStrings bila <dimension> tidak dapat dipercaya)
     * - xls/csv: load penuh (file-file ini kecil di DATASET).
     */
    private function countRows(string $file, string $ext, int $sheetIndex, \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): int
    {
        if ($ext === 'csv') {
            $lines = 0;
            $handle = fopen($file, 'r');
            while (fgetcsv($handle) !== false) {
                $lines++;
            }
            fclose($handle);

            return $lines;
        }

        if ($ext === 'xls') {
            $full = IOFactory::load($file);

            return $full->getSheet($sheetIndex)->getHighestRow();
        }

        // .xlsx — streaming lewat XMLReader, hanya menghitung elemen <row>.
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return $sheet->getHighestRow();
        }

        $sheetXml = 'xl/worksheets/sheet' . ($sheetIndex + 1) . '.xml';
        if ($zip->locateName($sheetXml) === false) {
            $zip->close();

            return $sheet->getHighestRow();
        }

        $count = 0;
        $xml = new \XMLReader();
        $xml->open('zip://' . $file . '#' . $sheetXml);
        while ($xml->read()) {
            if ($xml->nodeType === \XMLReader::ELEMENT && $xml->name === 'row') {
                $count++;
            }
        }
        $xml->close();
        $zip->close();

        return $count;
    }

    /**
     * Reproduksi formatter default Laravel Excel ('slug', karena tidak ada
     * config/excel.php): Str::slug dengan separator underscore — lowercase,
     * separator berturut-turut di-collapse jadi satu underscore. Identik
     * dengan key yang akan dihasilkan Import class nanti.
     */
    private function formatHeading(string $heading): string
    {
        return Str::slug($heading, '_');
    }

    private function truncate(string $text, int $max = 42): string
    {
        $text = str_replace(["\r", "\n"], ' ', trim($text));

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }
}
