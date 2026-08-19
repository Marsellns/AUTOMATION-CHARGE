<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_monthly_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->tinyInteger('bulan')->comment('1-12');
            $table->smallInteger('tahun')->comment('Misal 2025, 2026');
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            $table->decimal('profit_loss', 15, 2)->default(0)->comment('Dihitung: revenue - cost saat import');
            $table->timestamps();

            // Unique: satu record per site per bulan per tahun
            $table->unique(['site_id', 'bulan', 'tahun'], 'smm_site_period_unique');

            // Index untuk query agregat per periode (dashboard chart)
            $table->index(['bulan', 'tahun'], 'smm_period_index');

            // Index untuk histori per site (drilldown detail)
            $table->index(['site_id', 'tahun'], 'smm_site_year_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_monthly_metrics');
    }
};
