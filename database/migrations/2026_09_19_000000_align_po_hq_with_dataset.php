<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_hq', function (Blueprint $table) {
            // Dataset PO HQ has empty Capex/Opex values on some rows and
            // operational statuses such as "Overdue" and "Need Check".
            $table->string('expense_type', 20)->nullable()->change();
            $table->string('status', 100)->default('Draft')->change();
            $table->text('remark')->nullable()->after('location');
            $table->string('update_by')->nullable()->after('remark');
            $table->timestamp('source_updated_at')->nullable()->after('update_by');
        });
    }

    public function down(): void
    {
        DB::table('po_hq')
            ->whereNotIn('status', ['Draft', 'On Process', 'Approved', 'Rejected', 'Closed'])
            ->update(['status' => 'Draft']);

        DB::table('po_hq')
            ->whereNull('expense_type')
            ->update(['expense_type' => 'Opex']);

        Schema::table('po_hq', function (Blueprint $table) {
            $table->dropColumn(['remark', 'update_by', 'source_updated_at']);
            $table->enum('expense_type', ['Capex', 'Opex'])->change();
            $table->enum('status', ['Draft', 'On Process', 'Approved', 'Rejected', 'Closed'])
                ->default('Draft')
                ->change();
        });
    }
};
