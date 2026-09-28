<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Ganti APP_URL di .env untuk publikasi sementara lewat tunnel (ngrok).
 *
 * Pakai:  php artisan app:use-tunnel https://xxxx.ngrok-free.app
 * Balik:  php artisan app:use-tunnel http://localhost
 *
 * APP_URL harus menunjuk URL publik supaya Laravel menghasilkan link,
 * redirect, dan cookie sesi dengan host & skema yang benar saat diakses
 * lewat proxy. Di Laravel 12 TrustProxies sudah mempercayai semua proxy
 * secara default, jadi X-Forwarded-* dari ngrok langsung dihormati.
 */
class SetTunnelUrl extends Command
{
    protected $signature = 'app:use-tunnel {url : URL tunnel (https://...), atau URL lokal untuk mengembalikan}';
    protected $description = 'Set APP_URL di .env ke URL tunnel (ngrok) atau kembali ke lokal';

    public function handle(): int
    {
        $url = rtrim($this->argument('url'), '/');
        $path = base_path('.env');

        if (! is_file($path)) {
            $this->error('File .env tidak ditemukan.');

            return self::FAILURE;
        }

        $contents = file_get_contents($path);
        $updated = preg_replace('/^APP_URL=.*$/m', 'APP_URL=' . $url, $contents, 1, $count);

        if ($count === 0) {
            $updated = $contents . "\nAPP_URL=" . $url . "\n";
        }

        file_put_contents($path, $updated);

        // Saat URL tunnel menggunakan HTTPS, cookie dan seluruh URL yang
        // dibuat Laravel juga harus HTTPS. Ini mencegah mixed content ketika
        // ngrok meneruskan trafik ke server lokal melalui HTTP.
        $secure = str_starts_with($url, 'https://') ? 'true' : 'false';
        $this->setEnvironmentValue($path, 'SESSION_SECURE_COOKIE', $secure);
        $this->setEnvironmentValue($path, 'APP_FORCE_HTTPS', $secure);

        $this->info("APP_URL diset ke {$url} (SESSION_SECURE_COOKIE={$secure}, APP_FORCE_HTTPS={$secure}).");
        $this->line('Restart aplikasi bila server atau konfigurasi berjalan dalam proses jangka panjang.');

        return self::SUCCESS;
    }

    private function setEnvironmentValue(string $path, string $key, string $value): void
    {
        $contents = file_get_contents($path);
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $contents)) {
            file_put_contents($path, preg_replace($pattern, $key . '=' . $value, $contents));

            return;
        }

        file_put_contents($path, $contents . "\n{$key}={$value}\n");
    }
}
