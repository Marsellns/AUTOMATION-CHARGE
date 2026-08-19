<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Dashboard Simawar-style
|--------------------------------------------------------------------------
| Semua route di sini otomatis mendapat prefix /api (bootstrap/app.php).
| Format response JSON; tidak ada Blade view.
*/

Route::prefix('dashboard')->group(function () {
    // Agregat satu periode (default: periode terbaru di data)
    Route::get('/pnl-summary', [DashboardController::class, 'pnlSummary']);

    // Daftar site per status: Profit | Loss | TidakAktif (paginated 25/halaman)
    Route::get('/pnl-summary/{status}', [DashboardController::class, 'pnlSummaryByStatus']);

    // Detail lengkap satu site (histori semua bulan + bulan tanpa data)
    Route::get('/site/{siteId}/detail', [DashboardController::class, 'siteDetail']);
});
