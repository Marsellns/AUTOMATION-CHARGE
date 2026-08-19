<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom is_anomaly dan tandai baris dengan revenue = 2147483647
     * (INT32 max — anomali dari sumber data; 29 baris, terverifikasi manual).
     *
     * Data TIDAK dihapus/ditimpa: hanya diberi penanda agar agregat finansial
     * dashboard bisa mengecualikannya, sementara barisnya tetap ada untuk
     * cross-check ke sumber asli.
     */
    public function up(): void
    {
        Schema::table('site_monthly_metrics', function (Blueprint $table) {
            $table->boolean('is_anomaly')
                ->default(false)
                ->after('profit_loss')
                ->comment('Ditandai true untuk baris dengan nilai mencurigakan (misal revenue = INT32 max)');
        });

        $flagged = DB::table('site_monthly_metrics')
            ->where('revenue', '=', 2147483647)
            ->update(['is_anomaly' => true]);

        // Sanity check: jumlah yang ditandai harus persis 29 (hasil audit).
        // Jika sumber data berubah di masa depan, angka ini bisa disesuaikan.
        if ($flagged !== 29) {
            throw new RuntimeException(
                "Anomali yang ditandai {$flagged} baris, diharapkan 29. Periksa ulang data sumber."
            );
        }
    }

    public function down(): void
    {
        DB::table('site_monthly_metrics')->update(['is_anomaly' => false]);

        Schema::table('site_monthly_metrics', function (Blueprint $table) {
            $table->dropColumn('is_anomaly');
        });
    }
};
