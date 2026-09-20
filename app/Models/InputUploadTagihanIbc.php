<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InputUploadTagihanIbc extends Model
{
    use HasFactory;

    protected $table = 'input_upload_tagihan_ibc';

    public const TYPES = [
        'X0,E3', 'X0,E4', 'X0,E8',
        'X1,E1', 'X1,E12', 'X1,E2', 'X1,E9',
        'X2,E1', 'X2,E12', 'X2,E2', 'X2,E9',
        'X6,E1', 'X6,E7',
    ];

    protected $fillable = [
        'type',
        'site_id',
        'no_faktur_pajak',
        'tgl_faktur_pajak',
        'no_bast',
        'tgl_bast',
        'meter_awal',
        'meter_akhir',
        'periode',
        'no_invoice_bm',
        'tgl_bayar_to_bm',
        'no_invoice_rpj',
        'dpp_ppn_b',
        'ppj_pju_c',
        'materai_d',
        'others_e',
        'total_tagihan_f',
        'gross_up_g',
        'ppn_h',
        'dpp_wht_i',
        'wht_j',
        'total_payment_k',
    ];

    protected $casts = [
        'tgl_faktur_pajak' => 'date',
        'tgl_bast'         => 'date',
        'tgl_bayar_to_bm'  => 'date',
        'meter_awal'       => 'decimal:2',
        'meter_akhir'      => 'decimal:2',
        'dpp_ppn_b'        => 'decimal:2',
        'ppj_pju_c'        => 'decimal:2',
        'materai_d'        => 'decimal:2',
        'others_e'         => 'decimal:2',
        'total_tagihan_f'  => 'decimal:2',
        'gross_up_g'       => 'decimal:2',
        'ppn_h'            => 'decimal:2',
        'dpp_wht_i'        => 'decimal:2',
        'wht_j'            => 'decimal:2',
        'total_payment_k'  => 'decimal:2',
    ];
}
