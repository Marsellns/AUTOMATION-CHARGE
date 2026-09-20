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
        // ============================================================
        // CENTRALIZED
        // ============================================================

        // --- Listrik PLN (tabel utama) ---
        Schema::create('listrik_pln', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelanggan', 50)->index();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->text('alamat')->nullable();
            $table->string('status_aktif_site', 50)->nullable()->default('Aktif');
            $table->string('nama_pelanggan')->nullable();
            $table->unsignedInteger('daya_va')->nullable(); // VA
            $table->string('phasa', 50)->nullable();
            $table->string('status_amr', 50)->nullable();
            $table->string('gol_tarif', 50)->nullable();
            $table->string('unit_layanan_pln', 100)->nullable();
            $table->string('jenis_bayar', 50)->nullable();
            $table->string('tp_owner', 100)->nullable();
            $table->string('nop', 100)->nullable(); // Nomor Objek Pajak
            $table->string('update_by', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->timestamps();
        });

        // --- Status Pembayaran (sub-tabel dari Listrik PLN, relasi ke ID Pelanggan) ---
        Schema::create('status_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listrik_pln_id')->constrained('listrik_pln')->cascadeOnDelete();
            $table->string('id_pelanggan', 50)->index(); // denormalisasi untuk query cepat
            $table->unsignedTinyInteger('bulan'); // 1-12
            $table->unsignedInteger('tahun'); // 2024, 2025, ...
            $table->decimal('harga', 18, 2)->default(0);
            $table->text('remark')->nullable();
            $table->string('update_by', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->timestamps();

            $table->index(['listrik_pln_id', 'bulan', 'tahun'], 'idx_status_pembayaran_periode');
        });

        // --- Bongkar Rampung — dari Listrik PLN (form "Tambah Boram") ---
        Schema::create('bongkar_rampung_pln', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listrik_pln_id')->constrained('listrik_pln')->cascadeOnDelete();
            $table->string('id_pelanggan', 50)->index(); // denormalisasi
            $table->string('no_surat_boram', 100)->nullable();
            $table->date('tanggal_surat_boram')->nullable();
            $table->string('mitra_boram')->nullable();
            $table->timestamps();
        });

        // --- Payment PLN (gabungan Payment Done + Payment Pending) ---
        Schema::create('payment_pln', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelanggan', 50)->index();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->unsignedInteger('daya')->nullable();
            $table->string('phasa', 50)->nullable();
            $table->string('gol_tarif', 50)->nullable();
            $table->string('unit_pln', 100)->nullable();
            $table->decimal('harga', 18, 2)->default(0);
            $table->string('update_by', 100)->nullable();
            $table->date('tanggal_status')->nullable();
            $table->string('status', 50)->default('Pending');
            $table->unsignedTinyInteger('bulan')->nullable(); // 1-12
            $table->unsignedInteger('tahun')->nullable();
            $table->timestamps();

            $table->index(['id_pelanggan', 'bulan', 'tahun', 'status'], 'idx_payment_pln_filter');
        });

        // --- Anomali Tagihan PLN ---
        Schema::create('anomali_tagihan_pln', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelanggan', 50)->index();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->unsignedInteger('tahun')->nullable();
            $table->decimal('tagihan_sebelumnya', 18, 2)->default(0);
            $table->decimal('tagihan_saat_ini', 18, 2)->default(0);
            $table->decimal('selisih', 18, 2)->default(0);
            $table->decimal('kenaikan_persen', 10, 2)->default(0);
            $table->timestamps();
        });

        // --- Bongkar Rampung — tabel terpisah/mandiri ---
        Schema::create('bongkar_rampung_mandiri', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelanggan', 50)->index();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->timestamps();
        });

        // --- Listrik All (summary 12 bulan per tahun) ---
        Schema::create('listrik_all', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelanggan', 50)->index();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->string('gol_tarif', 50)->nullable();
            $table->string('unit_pln', 100)->nullable();
            $table->unsignedInteger('tahun')->nullable();

            // 12 kolom bulan Jan-Des
            $table->decimal('jan', 18, 2)->default(0);
            $table->decimal('feb', 18, 2)->default(0);
            $table->decimal('mar', 18, 2)->default(0);
            $table->decimal('apr', 18, 2)->default(0);
            $table->decimal('mei', 18, 2)->default(0);
            $table->decimal('jun', 18, 2)->default(0);
            $table->decimal('jul', 18, 2)->default(0);
            $table->decimal('ags', 18, 2)->default(0);
            $table->decimal('sep', 18, 2)->default(0);
            $table->decimal('okt', 18, 2)->default(0);
            $table->decimal('nov', 18, 2)->default(0);
            $table->decimal('des', 18, 2)->default(0);

            $table->timestamps();
        });

        // ============================================================
        // INBUILDING
        // ============================================================

        // --- Listrik Inbuilding ---
        Schema::create('listrik_inbuilding', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->string('status', 50)->nullable()->default('Aktif');
            $table->string('nama_bm')->nullable();
            $table->string('no_npwp', 100)->nullable();
            $table->text('alamat')->nullable();
            $table->string('telkomsel_tp', 50)->nullable(); // Telkomsel / TP
            $table->unsignedInteger('daya')->nullable();
            $table->decimal('harga_per_kwh', 18, 2)->nullable();
            $table->string('update_by', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->timestamps();
        });

        // --- Input/Upload Data Tagihan IBC ---
        Schema::create('input_upload_tagihan_ibc', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('site_id', 50)->index();
            $table->string('no_faktur_pajak', 100)->nullable();
            $table->date('tgl_faktur_pajak')->nullable();
            $table->string('no_bast', 100)->nullable();
            $table->date('tgl_bast')->nullable();
            $table->decimal('meter_awal', 18, 2)->nullable();
            $table->decimal('meter_akhir', 18, 2)->nullable();
            $table->string('periode', 50)->nullable(); // format: 2024-01
            $table->string('no_invoice_bm', 100)->nullable();
            $table->date('tgl_bayar_to_bm')->nullable();
            $table->string('no_invoice_rpj', 100)->nullable();
            $table->decimal('dpp_ppn_b', 18, 2)->default(0); // DPP PPN (B)
            $table->decimal('ppj_pju_c', 18, 2)->default(0); // PPJ/PJU (C)
            $table->decimal('materai_d', 18, 2)->default(0); // Materai (D)
            $table->decimal('others_e', 18, 2)->default(0); // Others (E)
            $table->decimal('total_tagihan_f', 18, 2)->default(0); // Total Tagihan (F)
            $table->decimal('gross_up_g', 18, 2)->default(0); // Gross Up (G)
            $table->decimal('ppn_h', 18, 2)->default(0); // PPN (H)
            $table->decimal('dpp_wht_i', 18, 2)->default(0); // DPP WHT (I)
            $table->decimal('wht_j', 18, 2)->default(0); // WHT (J)
            $table->decimal('total_payment_k', 18, 2)->default(0); // Total Payment (K)
            $table->timestamps();

            $table->index(['site_id', 'periode'], 'idx_tagihan_ibc_filter');
        });

        // --- Payment IBC (gabungan Done + Pending) ---
        Schema::create('payment_ibc', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->string('nama_bm')->nullable();
            $table->unsignedInteger('daya')->nullable();
            $table->string('status', 50)->default('Pending');
            $table->decimal('jumlah_tagihan', 18, 2)->default(0);
            $table->string('invoice', 100)->nullable();
            $table->string('update_by', 100)->nullable();
            $table->date('tanggal_update_status')->nullable();
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->unsignedInteger('tahun')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'bulan', 'tahun', 'status'], 'idx_payment_ibc_filter');
        });

        // --- Anomali Tagihan Inbuilding ---
        Schema::create('anomali_tagihan_inbuilding', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 50)->index();
            $table->string('periode_sebelumnya', 50)->nullable(); // format: 2024-01
            $table->decimal('tagihan_sebelumnya', 18, 2)->default(0);
            $table->string('periode_saat_ini', 50)->nullable(); // format: 2024-02
            $table->decimal('tagihan_saat_ini', 18, 2)->default(0);
            $table->decimal('selisih', 18, 2)->default(0);
            $table->decimal('kenaikan_persen', 10, 2)->default(0);
            $table->timestamps();
        });

        // --- Inbuilding All (summary 12 bulan per tahun) ---
        Schema::create('inbuilding_all', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 50)->index();
            $table->string('site_name')->nullable();
            $table->string('status', 50)->nullable()->default('Aktif');
            $table->string('nama_bm')->nullable();
            $table->string('tp_nontp', 50)->nullable(); // TP/NONTP
            $table->unsignedInteger('tahun')->nullable();

            // 12 kolom bulan Jan-Des
            $table->decimal('jan', 18, 2)->default(0);
            $table->decimal('feb', 18, 2)->default(0);
            $table->decimal('mar', 18, 2)->default(0);
            $table->decimal('apr', 18, 2)->default(0);
            $table->decimal('mei', 18, 2)->default(0);
            $table->decimal('jun', 18, 2)->default(0);
            $table->decimal('jul', 18, 2)->default(0);
            $table->decimal('ags', 18, 2)->default(0);
            $table->decimal('sep', 18, 2)->default(0);
            $table->decimal('okt', 18, 2)->default(0);
            $table->decimal('nov', 18, 2)->default(0);
            $table->decimal('des', 18, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbuilding_all');
        Schema::dropIfExists('anomali_tagihan_inbuilding');
        Schema::dropIfExists('payment_ibc');
        Schema::dropIfExists('input_upload_tagihan_ibc');
        Schema::dropIfExists('listrik_inbuilding');
        Schema::dropIfExists('listrik_all');
        Schema::dropIfExists('bongkar_rampung_mandiri');
        Schema::dropIfExists('anomali_tagihan_pln');
        Schema::dropIfExists('payment_pln');
        Schema::dropIfExists('bongkar_rampung_pln');
        Schema::dropIfExists('status_pembayaran');
        Schema::dropIfExists('listrik_pln');
    }
};