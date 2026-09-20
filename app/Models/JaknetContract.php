<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Infrastruktur management — 05 Sewa Lahan (Jaknet & Dapot).
 */
class JaknetContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_code', 'site_name', 'no_pks', 'tanggal_mulai', 'tanggal_berakhir',
        'contact_person', 'contact_address', 'telp', 'nilai', 'nilai_per_tahun',
        'tahun_berakhir', 'tgl_update', 'is_site_unlock',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_berakhir' => 'date',
        'tahun_berakhir' => 'integer',
        'tgl_update' => 'date',
        'is_site_unlock' => 'boolean',
    ];
}
