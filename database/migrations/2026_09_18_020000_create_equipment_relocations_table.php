<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('equipment_relocations', function (Blueprint $table) {
            $table->id();
            $table->string('donor_uniq_key', 191)->unique();
            $table->string('donor_acceptor')->nullable();
            $table->string('site_target_source')->nullable();
            $table->string('pic')->nullable();
            $table->string('progress')->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_relocations');
    }
};
