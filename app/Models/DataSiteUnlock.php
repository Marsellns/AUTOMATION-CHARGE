<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Infrastruktur management — 06 Data Site Unlock.
 */
class DataSiteUnlock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_code', 'site_name', 'site_class', 'city', 'batch',
        'status', 'final_status', 'update_by', 'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
