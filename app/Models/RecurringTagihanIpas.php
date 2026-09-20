<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringTagihanIpas extends Model
{
    use HasFactory;

    protected $table = 'recurring_tagihan_ipas';

    protected $fillable = [
        'source_details',
        'site_code', 'site_name', 'tp', 'contract_type', 'termin', 'periode_ke',
        'termin_start', 'termin_end', 'amount', 'batch_name',
    ];

    protected $casts = [
        'source_details' => 'array',
        'termin_start' => 'date',
        'termin_end' => 'date',
    ];
}
