<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Throwable;

class ImportEquipmentRelocationInventory extends Command
{
    private const COLUMNS = [
        'uniq_key', 'site_id', 'nop', 'region', 'to_name', 'ne_name',
        'equipment_group', 'equipment_type', 'category', 'board_name',
        'board_type', 'serial_number', 'utilization_status', 'safe_to_reloc',
    ];

    protected $signature = 'equipment:import-inventory
        {file? : Path to the compact Equipment Relocation inventory JSON}
        {--replace : Replace an inventory already stored in MySQL}';

    protected $description = 'Import the Equipment Relocation inventory snapshot into MySQL';

    public function handle(): int
    {
        if (!Schema::hasTable('equipment_relocation_inventory')) {
            $this->error('Tabel equipment_relocation_inventory belum ada. Jalankan migration terlebih dahulu.');

            return self::FAILURE;
        }

        $input = $this->argument('file');
        $path = $input === null
            ? storage_path('app/imports/equipment_relocation_inventory.json')
            : (is_file($input) ? $input : base_path($input));

        if (!is_file($path) || !is_readable($path)) {
            $this->error("File inventaris tidak dapat dibaca: {$path}");

            return self::FAILURE;
        }

        try {
            $snapshot = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('JSON inventaris tidak valid: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (!is_array($snapshot)
            || ($snapshot['schema'] ?? null) !== 1
            || ($snapshot['columns'] ?? null) !== self::COLUMNS
            || !is_array($snapshot['rows'] ?? null)
            || $snapshot['rows'] === []) {
            $this->error('Format atau urutan kolom snapshot inventaris tidak sesuai.');

            return self::FAILURE;
        }

        $rows = $snapshot['rows'];
        $seen = [];

        foreach ($rows as $index => $values) {
            if (!is_array($values) || count($values) !== count(self::COLUMNS)) {
                $this->error('Jumlah kolom tidak sesuai pada baris '.($index + 1).'.');

                return self::FAILURE;
            }

            foreach ($values as $columnIndex => $value) {
                if (!is_string($value) && !is_int($value) && !is_float($value) && $value !== null) {
                    $this->error('Nilai kolom '.self::COLUMNS[$columnIndex].' tidak valid pada baris '.($index + 1).'.');

                    return self::FAILURE;
                }

                $limit = $columnIndex === 0 ? 191 : 255;
                if (mb_strlen((string) $value) > $limit) {
                    $this->error('Nilai kolom '.self::COLUMNS[$columnIndex].' terlalu panjang pada baris '.($index + 1).'.');

                    return self::FAILURE;
                }
            }

            $key = trim((string) $values[0]);
            if ($key === '' || $key !== (string) $values[0] || isset($seen[$key])) {
                $this->error('uniq_key kosong, memiliki spasi tepi, atau duplikat pada baris '.($index + 1).'.');

                return self::FAILURE;
            }
            $seen[$key] = true;
        }

        $existing = DB::table('equipment_relocation_inventory')->count();
        if ($existing > 0 && !$this->option('replace')) {
            $this->error("Inventaris MySQL sudah berisi {$existing} unit. Gunakan --replace untuk menggantinya.");

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($rows): void {
                DB::table('equipment_relocation_inventory')->delete();
                $now = now();

                foreach (array_chunk($rows, 300) as $chunk) {
                    $records = [];
                    foreach ($chunk as $values) {
                        $record = [];
                        foreach (self::COLUMNS as $index => $column) {
                            $record[$column] = (string) ($values[$index] ?? '');
                        }
                        $record['created_at'] = $now;
                        $record['updated_at'] = $now;
                        $records[] = $record;
                    }
                    DB::table('equipment_relocation_inventory')->insert($records);
                }

                $stored = DB::table('equipment_relocation_inventory')->count();
                if ($stored !== count($rows)) {
                    throw new \RuntimeException("Jumlah data berbeda: sumber ".count($rows).", MySQL {$stored}.");
                }
            });
        } catch (Throwable $exception) {
            $this->error('Impor inventaris dibatalkan: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Inventaris Equipment Relocation tersimpan di MySQL: '.count($rows).' unit.');

        return self::SUCCESS;
    }
}
