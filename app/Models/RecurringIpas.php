<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Infrastruktur management — 04 Recurring (ANT & Ipas).
 */
class RecurringIpas extends Model
{
    use HasFactory;

    protected $table = 'recurring_ipas';

    protected $fillable = [
        'site_code', 'site_name', 'alamat', 'site_owner', 'rtp', 'tgl_update',
        'contract_description', 'source_id', 'sow_detail', 'sow_id',
        'year_amount', 'start_date', 'end_date',
    ];

    protected $casts = [
        'tgl_update' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'year_amount' => 'decimal:2',
    ];
}
