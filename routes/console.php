<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Keep-alive dashboard (dev): tiap menit ping beberapa endpoint ringan.
| Tujuannya menjaga page-cache file bind-mount & bytecode OPcache worker
| `php artisan serve` tetap hangat, sehingga klik drilldown pengguna tidak
| membayar "cold start" 2-4 detik. Dijalankan oleh program scheduler di
| supervisord (php artisan schedule:work).
*/
Schedule::call(function () {
    for ($i = 0; $i < 5; $i++) {
        try {
            Http::timeout(15)->get('http://localhost/api/dashboard/periods');
        } catch (\Throwable) {
            // Keep-alive bersifat best-effort; abaikan kegagalan.
        }
    }
})->everyMinute()->name('dashboard-keepalive')->withoutOverlapping();
