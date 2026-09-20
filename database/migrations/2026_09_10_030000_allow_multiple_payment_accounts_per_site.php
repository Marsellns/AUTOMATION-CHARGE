<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_pln_master_monthly', function (Blueprint $table) {
            $table->dropUnique('payment_master_site_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_pln_master_monthly', function (Blueprint $table) {
            $table->unique(['site_id', 'bulan', 'tahun'], 'payment_master_site_period_unique');
        });
    }
};
