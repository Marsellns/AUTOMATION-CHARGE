<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_relocation_inventory', function (Blueprint $table) {
            $table->id();
            $table->string('uniq_key', 191)->unique();
            $table->string('site_id')->default('')->index();
            $table->string('nop')->default('')->index();
            $table->string('region')->default('');
            $table->string('to_name')->default('');
            $table->string('ne_name')->default('');
            $table->string('equipment_group')->default('')->index();
            $table->string('equipment_type')->default('');
            $table->string('category')->default('');
            $table->string('board_name')->default('');
            $table->string('board_type')->default('');
            $table->string('serial_number')->default('');
            $table->string('utilization_status')->default('');
            $table->string('safe_to_reloc')->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_relocation_inventory');
    }
};
