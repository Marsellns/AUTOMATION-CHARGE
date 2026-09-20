<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_varcost', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->enum('expense_type', ['Capex', 'Opex']);
            $table->text('description');
            $table->string('gr_status')->nullable();
            $table->unsignedSmallInteger('po_year');
            $table->date('delivery_date')->nullable();
            $table->string('update_by')->nullable();
            $table->timestamp('update_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_varcost');
    }
};
