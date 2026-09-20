<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_monthly_metrics', function (Blueprint $table) {
            $table->decimal('opex_freq', 15, 2)->nullable()->after('cost');
            $table->decimal('opex_isr', 15, 2)->nullable()->after('opex_freq');
            $table->decimal('opex_trans', 15, 2)->nullable()->after('opex_isr');
            $table->decimal('opex_power', 15, 2)->nullable()->after('opex_trans');
            $table->decimal('opex_rm', 15, 2)->nullable()->after('opex_power');
            $table->decimal('total_direct_dep', 15, 2)->nullable()->after('opex_rm');
            $table->decimal('rev_voice', 15, 2)->nullable()->after('total_direct_dep');
            $table->decimal('rev_sms', 15, 2)->nullable()->after('rev_voice');
            $table->decimal('rev_broath', 15, 2)->nullable()->after('rev_sms');
            $table->decimal('rev_digi', 15, 2)->nullable()->after('rev_broath');
            $table->decimal('rev_tapout', 15, 2)->nullable()->after('rev_digi');
        });
    }

    public function down(): void
    {
        Schema::table('site_monthly_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'opex_freq', 'opex_isr', 'opex_trans', 'opex_power', 'opex_rm',
                'total_direct_dep', 'rev_voice', 'rev_sms', 'rev_broath',
                'rev_digi', 'rev_tapout',
            ]);
        });
    }
};