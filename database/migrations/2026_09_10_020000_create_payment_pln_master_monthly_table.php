<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_pln_master_monthly', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelanggan', 50)->nullable()->index();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('amount', 18, 2)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->string('status_aktif_site', 50)->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'bulan', 'tahun'], 'payment_master_site_period_unique');
            $table->index(['tahun', 'bulan', 'is_paid'], 'payment_master_period_paid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_pln_master_monthly');
    }
};
