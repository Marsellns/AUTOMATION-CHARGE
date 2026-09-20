<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRoutes extends Command
{
    protected $signature = 'routes:check';
    protected $description = 'Check all infrastruktur routes and data endpoints for 200 response';

    public function handle(): int
    {
        $user = User::first();
        if (!$user) {
            $this->error('No user found');
            return 1;
        }

        $routes = [
            '/dashboard',
            '/dashboard/chart-data',
            '/pnl',
            '/infrastruktur/sewa-lahan',
            '/infrastruktur/sewa-lahan/data',
            '/infrastruktur/combat',
            '/infrastruktur/combat/data',
            '/infrastruktur/recurring-ipas',
            '/infrastruktur/recurring-ipas/data',
            '/infrastruktur/recurring-tagihan-ipas',
            '/infrastruktur/recurring-tagihan-ipas/data',
            '/infrastruktur/jaknet',
            '/infrastruktur/jaknet/data',
            '/infrastruktur/site-unlock',
            '/infrastruktur/site-unlock/data',
            '/infrastruktur/bapss',
            '/infrastruktur/bapss/data',
            '/infrastruktur/upload-file',
            '/infrastruktur/upload-file/data',
            '/electricity/centralized/listrik-pln',
            '/electricity/centralized/listrik-pln/data',
            '/electricity/centralized/listrik-pln/1/status-pembayaran',
            '/electricity/centralized/listrik-pln/1/status-pembayaran/data',
            '/electricity/centralized/listrik-pln/1/grafik',
            '/electricity/centralized/listrik-pln/1/grafik-data',
            '/electricity/centralized/payment',
            '/electricity/centralized/payment/data',
            '/electricity/centralized/payment/export-excel?bulan=5&tahun=2026',
            '/electricity/centralized/anomali',
            '/electricity/centralized/anomali/data',
            '/electricity/centralized/anomali/export-excel?bulan=1&tahun=2026',
            '/electricity/centralized/bongkar-rampung-mandiri',
            '/electricity/centralized/bongkar-rampung-mandiri/data',
            '/electricity/centralized/bongkar-rampung-mandiri/export-excel',
            '/electricity/centralized/listrik-all',
            '/electricity/centralized/listrik-all/data',
            '/electricity/centralized/listrik-all/export-excel?tahun=2026',
        ];

        Auth::setUser($user);

        foreach ($routes as $uri) {
            $request = Request::create($uri, 'GET');
            $request->setUserResolver(fn() => $user);
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');

            $response = app()->handle($request);
            $status = $response->getStatusCode();

            if ($status === 200) {
                $this->info("{$uri} -> {$status} OK");
            } else {
                $this->error("{$uri} -> {$status}");
            }
        }

        return 0;
    }
}
