<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SewaLahanRenewal extends Model
{
    use HasFactory, SoftDeletes;

    /** 15 tahap Status Dokumen (dropdown form Edit). */
    public const STATUS_DOKUMEN = [
        'A. Negosiasi by NOS', 'B. Pembuatan SIP by NOS', 'C. Pengiriman SIP by NOS',
        'D. Tanda Tangan BAK by Owner', 'E. Tanda Tangan BAK by NOS', 'F. Pending Legal by Owner',
        'G. Finalisasi PKS by Legal', 'H. Tandatangan PKS by Owner', 'I. Tandatangan PKS by Telkomsel',
        'J. Submit Pembayaran by Finance', 'K. Pembayaran Done', 'L. Site Dismantle by ND Dismantle',
        'M. Site Migrasi / Owner TP', 'N. Site Unlock Project', 'O. Others',
    ];

    protected $fillable = [
        'source_details',
        'site_code', 'site_name', 'tahun_renewal', 'status_dokumen', 'status_perpanjangan',
        'no_pks_baru', 'start_date_baru', 'end_date_baru', 'harga_baru', 'total_harga_baru',
        'no_bak_baru', 'tgl_bak_baru', 'no_sip', 'tgl_terima_sip',
        'penawaran_1', 'nego_1', 'penawaran_2', 'nego_2', 'penawaran_3', 'nego_3',
        'no_pks_lama', 'start_date_lama', 'end_date_lama', 'harga_lama',
        'keterangan', 'update_by', 'tgl_update',
    ];

    protected $casts = [
        'source_details' => 'array',
        'tahun_renewal' => 'integer',
        'start_date_baru' => 'date', 'end_date_baru' => 'date',
        'tgl_bak_baru' => 'date', 'tgl_terima_sip' => 'date',
        'start_date_lama' => 'date', 'end_date_lama' => 'date',
        'tgl_update' => 'date',
    ];
}
