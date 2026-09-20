<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infrastruktur Management v2 — perubahan skema.
 *
 * ALTER: Tambah softDeletes ke sewa_lahan_renewals, combat_sites,
 *        jaknet_contracts (+ is_site_unlock), bapss (+ perlebar pdf path).
 * CREATE: recurring_tagihan_ipas, data_site_unlocks, upload_files.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────
        // ALTER existing tables
        // ──────────────────────────────────────────────

        // 1. sewa_lahan_renewals — tambah soft delete
        Schema::table('sewa_lahan_renewals', function (Blueprint $table) {
            $table->softDeletes();
        });

        // 2. combat_sites — tambah soft delete
        Schema::table('combat_sites', function (Blueprint $table) {
            $table->softDeletes();
        });

        // 3. jaknet_contracts — tambah is_site_unlock flag + soft delete
        Schema::table('jaknet_contracts', function (Blueprint $table) {
            $table->boolean('is_site_unlock')->default(false)
                  ->after('tgl_update')
                  ->comment('Flag untuk menandai baris Site Unlock (toggle UI)');
            $table->softDeletes();
        });

        // 4. bapss — perlebar kolom pdf path + soft delete
        Schema::table('bapss', function (Blueprint $table) {
            // Ubah dari string(50) ke string(500) agar cukup untuk path file
            $table->string('pdf_bapss', 500)->nullable()->comment('Path file PDF BAPSS')->change();
            $table->string('pdf_ba_dismantle', 500)->nullable()->comment('Path file PDF BA Dismantle')->change();
            $table->softDeletes();
        });

        // ──────────────────────────────────────────────
        // CREATE new tables
        // ──────────────────────────────────────────────

        // 5. Modul 4 — Recurring (Tagihan Ipas)
        Schema::create('recurring_tagihan_ipas', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 30)->index()->comment('Site ID');
            $table->string('site_name')->nullable();
            $table->string('tp', 100)->nullable()->comment('Tower Provider');
            $table->string('contract_type', 100)->nullable();
            $table->string('termin', 100)->nullable();
            $table->string('periode_ke', 50)->nullable()->comment('Periode Ke-N');
            $table->date('termin_start')->nullable();
            $table->date('termin_end')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('batch_name', 150)->nullable();
            $table->timestamps();
        });

        // 6. Modul 6 — Data Site Unlock
        Schema::create('data_site_unlocks', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 30)->index()->comment('Site ID');
            $table->string('site_name')->nullable();
            $table->string('site_class', 50)->nullable()->comment('Class site');
            $table->string('city', 100)->nullable();
            $table->string('batch', 100)->nullable();
            $table->string('status', 100)->nullable();
            $table->string('final_status', 100)->nullable();
            $table->string('update_by', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 7. Modul 8 — Upload File PDF
        Schema::create('upload_files', function (Blueprint $table) {
            $table->id();
            $table->text('keterangan')->nullable();
            $table->string('file_path', 500)->nullable()->comment('Path file PDF yang diupload');
            $table->string('update_by', 100)->nullable();
            $table->dateTime('update_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop new tables
        Schema::dropIfExists('upload_files');
        Schema::dropIfExists('data_site_unlocks');
        Schema::dropIfExists('recurring_tagihan_ipas');

        // Revert alterations
        Schema::table('bapss', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->string('pdf_bapss', 50)->nullable()->comment('Bernilai "Download"/"-" dari sumber')->change();
            $table->string('pdf_ba_dismantle', 50)->nullable()->change();
        });

        Schema::table('jaknet_contracts', function (Blueprint $table) {
            $table->dropColumn('is_site_unlock');
            $table->dropSoftDeletes();
        });

        Schema::table('combat_sites', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('sewa_lahan_renewals', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
