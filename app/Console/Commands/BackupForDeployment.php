<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupForDeployment extends Command
{
    protected $signature = 'simaster:backup';

    protected $description = 'Cadangkan MySQL dan dokumen ke ZIP privat untuk pemindahan atau pemulihan';

    public function handle(): int
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            $this->error('Backup ini membutuhkan koneksi MySQL.');
            return self::FAILURE;
        }

        $directory = storage_path('app/backups/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)));
        if (! mkdir($directory, 0700, true)) {
            $this->error('Tidak dapat membuat direktori backup.');
            return self::FAILURE;
        }

        $sql = $directory.'/database.sql';
        $zipPath = $directory.'/simaster-data.zip';
        try {
            $config = $connection->getConfig();
            $arguments = [
                'mysqldump', '--single-transaction', '--quick', '--no-tablespaces',
                '--skip-add-locks', '--hex-blob', '--default-character-set=utf8mb4',
                '--host='.$config['host'], '--port='.($config['port'] ?? 3306),
                '--user='.$config['username'], '--result-file='.$sql,
            ];
            if (! empty($config['unix_socket'])) {
                $arguments[] = '--socket='.$config['unix_socket'];
            }
            $arguments[] = $connection->getDatabaseName();
            // The password is passed in the subprocess environment, never in
            // command arguments, console output, or the resulting archive.
            $process = new Process($arguments, base_path(), ['MYSQL_PWD' => (string) $config['password']]);
            $process->setTimeout(600);
            $process->mustRun();
            chmod($sql, 0600);

            // cPanel uses a different database user. Remove source-account
            // DEFINER clauses without changing table data or view queries.
            $portable = $directory.'/portable.sql';
            $input = fopen($sql, 'rb');
            $output = fopen($portable, 'wb');
            if ($input === false || $output === false) {
                throw new \RuntimeException('Tidak dapat memproses SQL backup.');
            }
            while (($line = fgets($input)) !== false) {
                if (preg_match('/^(?:\/\*![0-9]+\s+(?:CREATE|DEFINER)|CREATE\s+)/i', $line)) {
                    $line = preg_replace('/\bDEFINER=`[^`]*`@`[^`]*`\s*/', '', $line);
                }
                if (fwrite($output, $line) === false) {
                    throw new \RuntimeException('Gagal menulis SQL backup.');
                }
            }
            fclose($input);
            fclose($output);
            if (! rename($portable, $sql)) {
                throw new \RuntimeException('Gagal menyelesaikan SQL backup.');
            }
            chmod($sql, 0600);

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new \RuntimeException('Tidak dapat membuat ZIP backup.');
            }
            $zip->addFile($sql, 'database.sql');
            $files = 0;
            foreach (['private', 'public'] as $disk) {
                $root = storage_path('app/'.$disk);
                if (! is_dir($root)) {
                    continue;
                }
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (! $file->isFile() || $file->isLink() || $file->getFilename() === '.gitignore') {
                        continue;
                    }
                    if (! $zip->addFile($file->getPathname(), 'storage/app/'.$disk.'/'.substr($file->getPathname(), strlen($root) + 1))) {
                        throw new \RuntimeException('Gagal menambahkan dokumen ke backup.');
                    }
                    $files++;
                }
            }
            $manifest = [
                'created_at' => now()->toIso8601String(),
                'database' => $connection->getDatabaseName(),
                'sql_sha256' => hash_file('sha256', $sql),
                'document_files' => $files,
                'tables' => [],
            ];
            foreach ($connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $table) {
                $name = array_values((array) $table)[0];
                $manifest['tables'][$name] = $connection->table($name)->count();
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (! $zip->close()) {
                throw new \RuntimeException('ZIP backup gagal diselesaikan.');
            }
            chmod($zipPath, 0600);
            $verify = new ZipArchive;
            if ($verify->open($zipPath, ZipArchive::CHECKCONS) !== true) {
                throw new \RuntimeException('Verifikasi ZIP backup gagal.');
            }
            $verify->close();
            file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->info('Backup terverifikasi: '.$zipPath);
            $this->line('SHA-256: '.hash_file('sha256', $zipPath));
            $this->line('Dokumen: '.$files.'. Simpan arsip ini di luar document root. APP_KEY harus dipindahkan terpisah.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Backup gagal: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
