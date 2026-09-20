<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteFinancial extends Model
{
    use HasFactory;

    protected $table = 'site_financials';

    protected $fillable = [
        'site_id', 'revenue', 'cost', 'profit_status', 'periode',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
        'cost' => 'decimal:2',
    ];
}
