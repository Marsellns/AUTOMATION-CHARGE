<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProductionCheck extends Command
{
    protected $signature = 'simaster:production-check';

    protected $description = 'Periksa konfigurasi, database, aset, dan dokumen sebelum membuka produksi';

    private bool $failed = false;

    public function handle(): int
    {
        $this->failed = false;
        $this->check(version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP minimal 8.2');
        foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'iconv', 'intl', 'mbstring', 'openssl', 'pcre', 'pdo_mysql', 'session', 'simplexml', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip'] as $extension) {
            $this->check(extension_loaded($extension), 'Ekstensi PHP '.$extension);
        }
        $this->check(app()->environment('production'), 'APP_ENV=production');
        $this->check(config('app.debug') === false, 'APP_DEBUG=false');
        $key = (string) config('app.key');
        $decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $this->check(is_string($decodedKey) && strlen($decodedKey) === 32, 'APP_KEY AES-256 tersedia');
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $this->check(parse_url($url, PHP_URL_SCHEME) === 'https' && $host !== ''
            && ! in_array($host, ['localhost', '127.0.0.1', 'example.com', 'simaster.example.com'], true)
            && ! str_contains($host, 'ngrok'), 'APP_URL memakai domain produksi HTTPS');
        $this->check(config('session.secure') === true && config('session.http_only') === true, 'Cookie session Secure dan HttpOnly');
        $this->check(trim((string) config('app.trusted_proxies')) !== '*', 'Trusted proxy tidak memakai wildcard');
        $this->check((string) config('provisioning.admin_password', '') === '', 'Password provisioning admin sudah dihapus dari konfigurasi');

        foreach ([storage_path(), storage_path('app/private'), storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')] as $directory) {
            $this->check(is_dir($directory) && is_writable($directory), 'Direktori dapat ditulis: '.str_replace(base_path().'/', '', $directory));
        }
        $this->check(! is_file(public_path('hot')), 'Vite development hot file tidak ada');
        $manifestPath = public_path('build/manifest.json');
        $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
        $assetsValid = is_array($manifest) && count($manifest) > 0;
        foreach (is_array($manifest) ? $manifest : [] as $asset) {
            foreach (array_merge([$asset['file'] ?? ''], $asset['css'] ?? []) as $file) {
                $assetsValid = $assetsValid && $file !== '' && ! str_contains($file, '..') && is_file(public_path('build/'.$file));
            }
        }
        $this->check($assetsValid, 'Manifest dan aset build Vite tersedia');
        $this->check(isset($manifest['resources/css/report.css'], $manifest['resources/js/report.js']), 'Build aset halaman laporan Filament tersedia');
        foreach (['css/filament/filament/app.css', 'js/filament/filament/app.js', 'js/filament/tables/tables.js',
            'vendor/simaster/jquery-3.7.1.min.js', 'vendor/simaster/bootstrap-5.3.3.bundle.min.js', 'vendor/simaster/datatables-2.1.8.min.js'] as $file) {
            $this->check(is_file(public_path($file)), 'Aset panel tersedia: '.$file);
        }
        $this->check(\Filament\Facades\Filament::getPanel('report')->getPath() === 'report'
            && is_subclass_of(User::class, \Filament\Models\Contracts\FilamentUser::class), 'Panel report dan otorisasi produksi Filament terdaftar');
        $publicPhpDependencies = is_dir(public_path('vendor'))
            && collect(\Illuminate\Support\Facades\File::allFiles(public_path('vendor')))
                ->contains(fn ($file): bool => in_array(strtolower($file->getExtension()), ['php', 'phtml', 'phar'], true));
        $this->check(! is_file(public_path('.env')) && ! $publicPhpDependencies && ! is_file(public_path('artisan')), 'Document root hanya berisi berkas publik');
        $privateRoot = realpath((string) config('filesystems.disks.private.root'));
        $publicRoot = realpath(public_path());
        $this->check($privateRoot !== false && $publicRoot !== false && ! str_starts_with($privateRoot.'/', $publicRoot.'/'), 'Dokumen privat berada di luar document root');

        $mailer = (string) config('mail.default');
        $this->check($mailer === 'smtp' && (string) config('mail.mailers.smtp.host') !== ''
            && (string) config('mail.from.address') !== '' && ! str_ends_with((string) config('mail.from.address'), '@example.com'), 'Pengiriman email SMTP produksi dikonfigurasi');
        $this->warn('Koneksi SMTP, HTTPS publik, dan cron harus diuji di hosting; command ini tidak mengirim email.');

        try {
            $connection = DB::connection();
            $this->check($connection->getDriverName() === 'mysql', 'Driver database MySQL');
            $connection->getPdo();
            $this->check(true, 'Koneksi database berhasil');
            $migrator = app('migrator');
            $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan());
            $this->check($pending === [], 'Semua migrasi telah diterapkan');
            foreach (['sites', 'site_monthly_metrics', 'site_owners', 'sewa_lahan_renewals', 'combat_sites', 'recurring_ipas', 'recurring_tagihan_ipas', 'jaknet_contracts', 'data_site_unlocks', 'bapss', 'listrik_pln', 'payment_pln_master_monthly', 'equipment_relocation_inventory'] as $table) {
                $count = $connection->table($table)->count();
                $this->check($count > 0, 'Data '.$table.': '.number_format($count).' baris');
            }
            $this->check(User::query()->where('account_status', 'approved')->role('admin')->exists(), 'Administrator aktif tersedia');
            $unsafeAccount = User::query()->whereIn('email', ['admin@example.com', 'viewer@example.com'])->where('account_status', 'approved')->get()
                ->contains(fn (User $user): bool => Hash::check('password', $user->password));
            $this->check(! $unsafeAccount, 'Akun demo dengan password lama dinonaktifkan');
            $missing = 0;
            $exposed = 0;
            foreach (['document_circulations' => ['file_path'], 'bapss' => ['pdf_bapss', 'pdf_ba_dismantle'], 'upload_files' => ['file_path']] as $table => $columns) {
                foreach ($columns as $column) {
                    foreach ($connection->table($table)->whereNotNull($column)->pluck($column) as $path) {
                        if ($path === '') {
                            continue;
                        }
                        if (str_contains($path, '..') || ! preg_match('#^(document-circulation|bapss|upload-files)/#', $path)) {
                            $missing++;
                            continue;
                        }
                        $missing += Storage::disk('private')->exists($path) ? 0 : 1;
                        $exposed += Storage::disk('public')->exists($path) ? 1 : 0;
                    }
                }
            }
            $this->check($missing === 0 && $exposed === 0, "Dokumen privat yang dirujuk tersedia; hilang={$missing}, salinan publik={$exposed}");
            $sourceUnavailable = DB::table('bapss')->get(['pdf_bapss', 'pdf_ba_dismantle', 'source_documents'])->sum(function ($row): int {
                $source = json_decode($row->source_documents ?? '{}', true) ?? [];
                $count = 0;
                foreach (['pdf_bapss', 'pdf_ba_dismantle'] as $column) {
                    if (strcasecmp((string) ($source[$column] ?? ''), 'Download') === 0 && ! $row->{$column}) {
                        $count++;
                    }
                }
                return $count;
            });
            if ($sourceUnavailable > 0) {
                $this->warn("{$sourceUnavailable} PDF sumber BAPSS belum tersedia; UI menampilkan status tersebut tanpa tautan palsu.");
            }
            if ($connection->table('failed_jobs')->exists()) {
                $this->warn('Terdapat failed_jobs; periksa sebelum produksi.');
            }
        } catch (\Throwable $exception) {
            $this->check(false, 'Pemeriksaan database gagal: '.$exception->getMessage());
        }

        if ($this->failed) {
            $this->error('Pemeriksaan belum lulus. Perbaiki baris FAIL sebelum membuka aplikasi.');
            return self::FAILURE;
        }
        $this->info('Pemeriksaan lokal konfigurasi produksi lulus. Lanjutkan smoke test HTTPS dan cron pada hosting.');
        return self::SUCCESS;
    }

    private function check(bool $passed, string $message): void
    {
        $this->failed = $this->failed || ! $passed;
        $this->line(($passed ? 'PASS' : 'FAIL').' '.$message);
    }
}
