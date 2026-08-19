<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PoHqController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/po-hq');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Sumber data DataTables — WAJIB dideklarasikan sebelum resource agar
    // tidak tertangkap oleh route /po-hq/{po_hq}.
    Route::get('/po-hq/data', [PoHqController::class, 'data'])->name('po-hq.data');

    // Satu resource utuh (jangan dipecah dua: route show /po-hq/{po_hq}
    // akan membayangi /po-hq/create jika resource mutasi dideklarasikan
    // sesudahnya). Proteksi mutasi diterapkan per-method via middlewareFor().
    Route::resource('po-hq', PoHqController::class)
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'role:admin');
});
