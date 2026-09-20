<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Data Potensi — Site Owner (Dapot & ANT).
 */
class SiteOwner extends Model
{
    protected $fillable = [
        'site_code', 'site_name', 'site_class', 'alamat', 'city', 'nop',
        'coverage_type', 'status_mla', 'pln_connection', 'capacity', 'id_pel',
        'tower_height', 'site_owner', 'tgl_update',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'tower_height' => 'decimal:2',
        'tgl_update' => 'date',
    ];
}
