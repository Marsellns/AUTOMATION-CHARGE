<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnomaliTagihanInbuilding extends Model
{
    use HasFactory;

    protected $table = 'anomali_tagihan_inbuilding';

    protected $fillable = [
        'site_id',
        'periode_sebelumnya',
        'tagihan_sebelumnya',
        'periode_saat_ini',
        'tagihan_saat_ini',
        'selisih',
        'kenaikan_persen',
    ];

    protected $casts = [
        'tagihan_sebelumnya' => 'decimal:2',
        'tagihan_saat_ini'   => 'decimal:2',
        'selisih'            => 'decimal:2',
        'kenaikan_persen'    => 'decimal:2',
    ];
}
