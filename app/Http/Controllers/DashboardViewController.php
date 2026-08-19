<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Controller view untuk Dashboard utama (read-only, tanpa mutasi).
 * Data diambil penuh dari endpoint /api/dashboard/* via AJAX —
 * kelas ini hanya merender kerangka halamannya.
 */
class DashboardViewController extends Controller
{
    public function index(): View
    {
        return view('dashboard.index');
    }
}
