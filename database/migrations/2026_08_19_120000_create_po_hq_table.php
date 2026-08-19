<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel PO HQ — modul UI pertama (contoh pola tabel + modal drilldown).
     *
     * Skema mengikuti referensi: PO Number, Agreement Number, Vendor Name,
     * Description, Capex/Opex, Status, Location. PO bersifat level HQ
     * (tidak terikat satu site), sehingga tidak ada relasi ke sites.
     */
    public function up(): void
    {
        Schema::create('po_hq', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 100)->unique();
            $table->string('agreement_number', 100)->nullable();
            $table->string('vendor_name');
            $table->text('description')->nullable();
            $table->enum('expense_type', ['Capex', 'Opex']);
            $table->enum('status', ['Draft', 'On Process', 'Approved', 'Rejected', 'Closed'])
                ->default('Draft');
            $table->string('location')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_hq');
    }
};
