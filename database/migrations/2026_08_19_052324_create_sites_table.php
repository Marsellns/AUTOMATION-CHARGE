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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 20)->unique()->comment('ID unik dari Excel, misal BDB001, TNG735');
            $table->string('site_name')->nullable()->comment('Nama site, bisa kosong atau "-"');
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->timestamps();

            $table->index('region_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
