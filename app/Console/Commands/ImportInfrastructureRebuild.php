<?php

namespace App\Console\Commands;

use App\Imports\Datasets\InfrastructureRebuildImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportInfrastructureRebuild extends Command
{
    protected $signature = 'dataset:import-infrastructure-rebuild
        {--file= : Lokasi workbook relatif project atau path absolut}
        {--only= : sewa-lahan atau combat; default keduanya}
        {--dry-run : Baca dan cocokkan sumber tanpa mengubah MySQL}';

    protected $description = 'Sinkronkan transaksi aktif Rebuild Sewa Lahan dan Combat ke MySQL';

    public function handle(InfrastructureRebuildImport $importer): int
    {
        $file = (string) ($this->option('file') ?: InfrastructureRebuildImport::DEFAULT_FILE);
        $path = is_file($file) ? $file : base_path($file);
        try {
            $datasets = $importer->read($path, $this->option('only'));
            $summary = [];
            foreach ($datasets as $key => $records) {
                $summary[] = [$key, InfrastructureRebuildImport::SHEETS[$key]['sheet'], count($records), count(array_unique(array_column($records, 'site_code')))];
            }
            $this->table(['Dataset', 'Sheet aktif', 'Transaksi', 'Site ID unik'], $summary);
            if ($this->option('dry-run')) {
                $this->info('Pemeriksaan sumber selesai; database belum diubah.');
                return self::SUCCESS;
            }
            if (DB::connection()->getDriverName() !== 'mysql') {
                throw new RuntimeException('Sinkronisasi ini harus dijalankan pada database MySQL aplikasi.');
            }
            $backup = storage_path('app/backups/infrastructure-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json');
            if (! is_dir(dirname($backup))) mkdir(dirname($backup), 0700, true);
            DB::transaction(function () use ($datasets, $backup): void {
                $old = ['created_at' => now()->toIso8601String(), 'database' => DB::connection()->getDatabaseName(), 'tables' => []];
                foreach ($datasets as $key => $records) {
                    $table = InfrastructureRebuildImport::SHEETS[$key]['table'];
                    $old['tables'][$table] = DB::table($table)->lockForUpdate()->get()->all();
                }
                if (file_put_contents($backup, json_encode($old, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
                    throw new RuntimeException('Backup snapshot sebelumnya gagal dibuat.');
                }
                chmod($backup, 0600);
                foreach ($datasets as $key => $records) {
                    $table = InfrastructureRebuildImport::SHEETS[$key]['table'];
                    DB::table($table)->delete();
                    $timestamp = now();
                    foreach ($records as &$record) {
                        $record['created_at'] = $timestamp;
                        $record['updated_at'] = $timestamp;
                    }
                    unset($record);
                    foreach (array_chunk($records, 250) as $chunk) DB::table($table)->insert($chunk);
                    if (DB::table($table)->count() !== count($records)) {
                        throw new RuntimeException('Jumlah sumber dan MySQL tidak cocok: '.$table);
                    }
                }
            });
            $this->info('Transaksi Rebuild tersimpan pada MySQL '.DB::connection()->getDatabaseName().'.');
            $this->line('Backup snapshot sebelumnya: '.$backup);
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Sinkronisasi dibatalkan: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
