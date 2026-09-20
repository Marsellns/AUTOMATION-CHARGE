<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnomaliTagihanPln extends Model
{
    use HasFactory;

    protected $table = 'anomali_tagihan_pln';

    protected $fillable = [
        'id_pelanggan',
        'site_id',
        'site_name',
        'bulan',
        'tahun',
        'tagihan_sebelumnya',
        'tagihan_saat_ini',
        'selisih',
        'kenaikan_persen',
    ];

    protected $casts = [
        'bulan' => 'integer',
        'tahun' => 'integer',
        'tagihan_sebelumnya' => 'decimal:2',
        'tagihan_saat_ini' => 'decimal:2',
        'selisih' => 'decimal:2',
        'kenaikan_persen' => 'decimal:2',
    ];
}
