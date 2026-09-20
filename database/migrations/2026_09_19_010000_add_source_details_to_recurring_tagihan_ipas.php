<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_tagihan_ipas', function (Blueprint $table): void {
            $table->json('source_details')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_tagihan_ipas', function (Blueprint $table): void {
            $table->dropColumn('source_details');
        });
    }
};
