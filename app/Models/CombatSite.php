<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CombatSite extends Model
{
    use HasFactory, SoftDeletes;

    /** 12 tahap Status Dokumen (dropdown form Edit). */
    public const STATUS_DOKUMEN = [
        'A. Negosiasi', 'B. Tandatangan BAK di Owner', 'C. Tandatangan BAK di NOS',
        'D. Finalisasi PKS', 'E. Sirkulir PKS di Owner', 'F. Sirkulir PKS di Tsel',
        'G. Pending legal by owner', 'H. Pending legal by NBAD (PKS Sitak)',
        'I. PKS Done sedang Menunggu inv reimburse', 'J. Reimburse RPJ Done',
        'K. Site Dismantle', 'L. Relokasi',
    ];

    protected $fillable = [
        'source_details',
        'site_code', 'site_name', 'tahun_justi_dirnet', 'status_dokumen', 'status_perpanjangan',
        'no_pks_baru', 'start_date_baru', 'end_date_baru', 'harga_baru', 'total_harga_baru',
        'penawaran_1', 'nego_1', 'penawaran_2', 'nego_2', 'penawaran_3', 'nego_3',
        'no_pks_lama', 'start_date_lama', 'end_date_lama', 'harga_lama',
        'nomor_surat', 'keterangan', 'update_by', 'tanggal',
    ];

    protected $casts = [
        'source_details' => 'array',
        'tahun_justi_dirnet' => 'integer',
        'start_date_baru' => 'date', 'end_date_baru' => 'date',
        'start_date_lama' => 'date', 'end_date_lama' => 'date',
        'tanggal' => 'date',
    ];
}
