<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bapss', fn (Blueprint $table) => $table->json('source_documents')->nullable());
        DB::table('bapss')->orderBy('id')->get(['id', 'pdf_bapss', 'pdf_ba_dismantle'])->each(function ($row): void {
            $update = [];
            $source = [];
            foreach (['pdf_bapss', 'pdf_ba_dismantle'] as $column) {
                $path = $row->{$column};
                if ($path !== null && (! preg_match('#^bapss/[A-Za-z0-9][A-Za-z0-9._/-]*\.pdf$#i', $path) || str_contains($path, '..'))) {
                    $source[$column] = $path;
                    $update[$column] = null;
                }
            }
            if ($update !== []) {
                $update['source_documents'] = json_encode($source, JSON_THROW_ON_ERROR);
                DB::table('bapss')->where('id', $row->id)->update($update);
            }
        });
    }

    public function down(): void
    {
        // Source labels must never become download paths again.
        Schema::table('bapss', fn (Blueprint $table) => $table->dropColumn('source_documents'));
    }
};
