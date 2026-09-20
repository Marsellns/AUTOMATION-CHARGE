<?php

namespace App\Console\Commands;

use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use XMLReader;
use ZipArchive;

class ImportRecurringTagihanIpasDataset extends Command
{
    protected $signature = 'dataset:import-recurring-tagihan-ipas';

    protected $description = 'Import snapshot Recurring Tagihan IPAS secara streaming untuk file besar';

    public function handle(): int
    {
        $file = base_path('DATASET/02 Infrastruktur management/03 Recurring ( Tagihan Ipas)/ExportTagihan-09-09-2026 gg.xlsx');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        try {
            $this->info('Membaca workbook Recurring Tagihan IPAS secara streaming...');
            $sharedStrings = $this->sharedStrings($file);

            $stored = DB::transaction(function () use ($file, $sharedStrings): int {
                DB::table('recurring_tagihan_ipas')->delete();

                $headers = [];
                $batch = [];
                $inserted = 0;

                foreach ($this->worksheetRows($file, $sharedStrings) as $rowNumber => $cells) {
                    if ($rowNumber === 1) {
                        foreach ($cells as $column => $value) {
                            $headers[$column] = Str::slug(trim((string) $value), '_');
                        }

                        continue;
                    }

                    $source = [];
                    foreach ($cells as $column => $value) {
                        $header = $headers[$column] ?? null;
                        if ($header !== null && $header !== '') {
                            $source[$header] = $value;
                        }
                    }

                    $record = $this->record($source);
                    if ($record === null) {
                        continue;
                    }

                    $batch[] = $record;

                    if (count($batch) < 500) {
                        continue;
                    }

                    DB::table('recurring_tagihan_ipas')->insert($batch);
                    $inserted += count($batch);
                    $batch = [];

                    if ($inserted % 10_000 === 0) {
                        $this->line('  tersimpan: '.number_format($inserted).' baris');
                    }
                }

                if ($batch !== []) {
                    DB::table('recurring_tagihan_ipas')->insert($batch);
                    $inserted += count($batch);
                }

                return $inserted;
            });
        } catch (Throwable $exception) {
            $this->error('Import dibatalkan dan data lama dipertahankan: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($stored === 0) {
            $this->error('Import dibatalkan: sumber tidak memiliki baris dengan Site ID.');

            return self::FAILURE;
        }

        $databaseCount = DB::table('recurring_tagihan_ipas')->count();

        if ($databaseCount !== $stored) {
            throw new RuntimeException("Verifikasi gagal: importer {$stored} baris, database {$databaseCount} baris.");
        }

        $this->info('✓ Recurring Tagihan IPAS tersinkron: '.number_format($databaseCount).' baris.');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function sharedStrings(string $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException('Workbook tidak dapat dibuka.');
        }

        $hasSharedStrings = $zip->locateName('xl/sharedStrings.xml') !== false;
        $zip->close();

        if (! $hasSharedStrings) {
            return [];
        }

        $reader = new XMLReader();
        if (! $reader->open($this->zipUri($file, 'xl/sharedStrings.xml'), null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Shared strings workbook tidak dapat dibaca.');
        }

        $strings = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }

                $node = new SimpleXMLElement($reader->readOuterXml());
                $parts = $node->xpath('.//*[local-name()="t"]') ?: [];
                $strings[] = implode('', array_map(static fn (SimpleXMLElement $part): string => (string) $part, $parts));
            }
        } finally {
            $reader->close();
        }

        return $strings;
    }

    /** @return \Generator<int, array<string, mixed>> */
    private function worksheetRows(string $file, array $sharedStrings): \Generator
    {
        $reader = new XMLReader();
        if (! $reader->open($this->zipUri($file, 'xl/worksheets/sheet1.xml'), null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Worksheet pertama tidak dapat dibaca.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $node = new SimpleXMLElement($reader->readOuterXml());
                $rowNumber = (int) $node['r'];
                $cells = [];

                foreach ($node->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    $reference = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $reference, $matches);
                    $column = $matches[0] ?? '';
                    if ($column === '') {
                        continue;
                    }

                    $type = (string) $cell['t'];
                    $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
                    $raw = isset($valueNodes[0]) ? (string) $valueNodes[0] : null;

                    if ($type === 's' && $raw !== null) {
                        $value = $sharedStrings[(int) $raw] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];
                        $value = implode('', array_map(static fn (SimpleXMLElement $part): string => (string) $part, $parts));
                    } else {
                        $value = $raw;
                    }

                    $cells[$column] = $value;
                }

                yield $rowNumber => $cells;
            }
        } finally {
            $reader->close();
        }
    }

    private function zipUri(string $file, string $entry): string
    {
        return 'zip://'.str_replace('\\', '/', $file).'#'.$entry;
    }

    /** @param array<string, mixed> $source
     *  @return array<string, mixed>|null
     */
    private function record(array $source): ?array
    {
        $siteCode = $this->text($source['siteid'] ?? $source['site_id'] ?? $source['site_code'] ?? null);

        if ($siteCode === null) {
            return null;
        }

        $sourceDetails = [];
        foreach ($source as $key => $value) {
            if ($value !== null && trim((string) $value) !== '') {
                $sourceDetails[$key] = $value;
            }
        }

        return [
            'source_details' => json_encode($sourceDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'site_code' => $siteCode,
            'site_name' => $this->text($source['site_name'] ?? null),
            'tp' => $this->text($source['tp'] ?? $source['tower_provider'] ?? null),
            'contract_type' => $this->text($source['contract_type'] ?? null),
            'termin' => $this->text($source['termin'] ?? null),
            'periode_ke' => $this->text($source['periode_ke'] ?? $source['periode'] ?? null),
            'termin_start' => $this->date($source['termin_start'] ?? $source['start_date'] ?? null),
            'termin_end' => $this->date($source['termin_end'] ?? $source['end_date'] ?? null),
            'amount' => $this->amount($source['termin_amount'] ?? $source['amount'] ?? $source['nilai'] ?? $source['nominal'] ?? null),
            'batch_name' => $this->text($source['batch_name'] ?? $source['batch'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' || $value === '-' ? null : $value;
    }

    private function amount(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '' || trim((string) $value) === '-') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^0-9\-]/', '', (string) $value);

        return $value === '' || $value === '-' ? null : (float) $value;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $value = trim((string) $value);

        foreach (['Y-m-d H:i:s', 'd-m-Y H:i:s', 'Y-m-d', 'd-m-Y', 'd/m/Y', 'j M Y', 'd M Y', 'j-M-Y'] as $format) {
            $date = DateTime::createFromFormat($format, $value);

            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
