<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel-tabel modul dataset Simawar (impor dari folder DATASET/).
 *
 * Semua tabel bersifat "snapshot" — setiap import menghapus isi lama lalu
 * mengisi ulang dari Excel, sehingga aman dijalankan berulang (idempotent).
 * Kolom site disimpan sebagai string `site_code` (tanpa FK ke sites) agar
 * import tidak mengubah data/dashboard PnL yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Data Potensi — Site Owner (Dapot & ANT)
        Schema::create('site_owners', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 30)->index()->comment('Site ID dari Excel');
            $table->string('site_name')->nullable();
            $table->string('site_class', 50)->nullable();
            $table->text('alamat')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('nop', 100)->nullable();
            $table->string('coverage_type', 100)->nullable();
            $table->string('status_mla', 50)->nullable()->comment('Kolom "Status (MLA/Non MLA)"');
            $table->string('pln_connection', 50)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('id_pel', 30)->nullable()->comment('ID pelanggan PLN, disimpan string karena panjang');
            $table->decimal('tower_height', 8, 2)->nullable();
            $table->string('site_owner', 100)->nullable();
            $table->date('tgl_update')->nullable();
            $table->timestamps();
        });

        // 2. Infrastruktur management — 01 Sewa Lahan (Renewal Eastern Jabotabek)
        Schema::create('sewa_lahan_renewals', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 30)->index();
            $table->string('site_name')->nullable();
            $table->smallInteger('tahun_renewal')->nullable();
            $table->string('status_dokumen', 100)->nullable();
            $table->string('status_perpanjangan', 100)->nullable();
            $table->string('no_pks_baru', 150)->nullable();
            $table->date('start_date_baru')->nullable();
            $table->date('end_date_baru')->nullable();
            $table->decimal('harga_baru', 18, 2)->nullable();
            $table->decimal('total_harga_baru', 18, 2)->nullable();
            $table->string('no_bak_baru', 150)->nullable();
            $table->date('tgl_bak_baru')->nullable();
            $table->string('no_sip', 150)->nullable();
            $table->date('tgl_terima_sip')->nullable();
            $table->decimal('penawaran_1', 18, 2)->nullable();
            $table->decimal('nego_1', 18, 2)->nullable();
            $table->decimal('penawaran_2', 18, 2)->nullable();
            $table->decimal('nego_2', 18, 2)->nullable();
            $table->decimal('penawaran_3', 18, 2)->nullable();
            $table->decimal('nego_3', 18, 2)->nullable();
            $table->string('no_pks_lama', 150)->nullable();
            $table->date('start_date_lama')->nullable();
            $table->date('end_date_lama')->nullable();
            $table->decimal('harga_lama', 18, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('update_by', 100)->nullable();
            $table->date('tgl_update')->nullable();
            $table->timestamps();
        });

        // 3. Infrastruktur management — 02 Combat
        //    Blok bulanan (Revenue/Cost/PnL Jan–Jun 2026) disimpan flat di tabel ini,
        //    TIDAK digabung ke site_monthly_metrics agar tidak menimpa data PnL utama.
        Schema::create('combat_sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 30)->index();
            $table->string('site_name')->nullable();
            $table->smallInteger('tahun_justi_dirnet')->nullable();
            $table->string('status_dokumen', 150)->nullable();
            $table->string('status_perpanjangan', 100)->nullable();
            $table->string('no_pks_baru', 150)->nullable();
            $table->date('start_date_baru')->nullable();
            $table->date('end_date_baru')->nullable();
            $table->decimal('harga_baru', 18, 2)->nullable();
            $table->decimal('total_harga_baru', 18, 2)->nullable();
            $table->decimal('penawaran_1', 18, 2)->nullable();
            $table->decimal('nego_1', 18, 2)->nullable();
            $table->decimal('penawaran_2', 18, 2)->nullable();
            $table->decimal('nego_2', 18, 2)->nullable();
            $table->decimal('penawaran_3', 18, 2)->nullable();
            $table->decimal('nego_3', 18, 2)->nullable();
            $table->string('no_pks_lama', 150)->nullable();
            $table->date('start_date_lama')->nullable();
            $table->date('end_date_lama')->nullable();
            $table->decimal('harga_lama', 18, 2)->nullable();
            $table->string('nomor_surat', 150)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('update_by', 100)->nullable();
            $table->date('tanggal')->nullable()->comment('Kolom "Tanggal" (tgl update versi Excel)');

            foreach (['jan', 'feb', 'mar', 'apr', 'mei', 'jun'] as $m) {
                $table->decimal("revenue_{$m}_2026", 18, 2)->nullable();
                $table->decimal("cost_{$m}_2026", 18, 2)->nullable();
                $table->decimal("pnl_{$m}_2026", 18, 2)->nullable();
            }
            $table->timestamps();
        });

        // 4. Infrastruktur management — 04 Recurring (ANT & Ipas)
        Schema::create('recurring_ipas', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 30)->index();
            $table->string('site_name')->nullable();
            $table->text('alamat')->nullable();
            $table->string('site_owner', 100)->nullable();
            $table->string('rtp', 100)->nullable();
            $table->date('tgl_update')->nullable();
            $table->text('contract_description')->nullable();
            $table->string('source_id', 100)->nullable();
            $table->text('sow_detail')->nullable();
            $table->string('sow_id', 100)->nullable();
            $table->decimal('year_amount', 18, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        // 5. Infrastruktur management — 05 Sewa Lahan (Jaknet & Dapot)
        Schema::create('jaknet_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 30)->index();
            $table->string('site_name')->nullable();
            $table->string('no_pks', 255)->nullable()->comment('Isi bercampur deskripsi perjanjian di sumber');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_berakhir')->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->text('contact_address')->nullable();
            $table->string('telp', 100)->nullable();
            $table->decimal('nilai', 18, 2)->nullable();
            $table->decimal('nilai_per_tahun', 18, 2)->nullable()->comment('Kolom "NILAI/THN"');
            $table->smallInteger('tahun_berakhir')->nullable();
            $table->date('tgl_update')->nullable();
            $table->timestamps();
        });

        // 6. Infrastruktur management — 07 BAPSS
        Schema::create('bapss', function (Blueprint $table) {
            $table->id();
            $table->string('site_code', 30)->index();
            $table->string('site_name')->nullable();
            $table->date('tgl_bapss')->nullable();
            $table->date('tgl_dismantle')->nullable();
            $table->text('remark')->nullable();
            $table->string('pdf_bapss', 50)->nullable()->comment('Bernilai "Download"/"-" dari sumber');
            $table->string('pdf_ba_dismantle', 50)->nullable();
            $table->string('update_by', 100)->nullable();
            $table->dateTime('tgl_update')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bapss');
        Schema::dropIfExists('jaknet_contracts');
        Schema::dropIfExists('recurring_ipas');
        Schema::dropIfExists('combat_sites');
        Schema::dropIfExists('sewa_lahan_renewals');
        Schema::dropIfExists('site_owners');
    }
};
