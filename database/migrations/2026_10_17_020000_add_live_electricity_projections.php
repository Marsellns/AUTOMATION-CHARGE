<?php

use App\Support\ElectricityReadViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('status_pembayaran', 'is_operational')) {
            Schema::table('status_pembayaran', fn (Blueprint $table) => $table->boolean('is_operational')->default(false)->index());
        }
        ElectricityReadViews::create();
    }

    public function down(): void
    {
        ElectricityReadViews::drop();
        Schema::table('status_pembayaran', fn (Blueprint $table) => $table->dropColumn('is_operational'));
    }
};
