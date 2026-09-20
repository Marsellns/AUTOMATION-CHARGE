<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListrikAll extends Model
{
    use HasFactory;

    protected $table = 'listrik_all';

    protected $fillable = [
        'id_pelanggan',
        'site_id',
        'site_name',
        'gol_tarif',
        'unit_pln',
        'tahun',
        'jan', 'feb', 'mar', 'apr', 'mei', 'jun',
        'jul', 'ags', 'sep', 'okt', 'nov', 'des',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'jan' => 'decimal:2',
        'feb' => 'decimal:2',
        'mar' => 'decimal:2',
        'apr' => 'decimal:2',
        'mei' => 'decimal:2',
        'jun' => 'decimal:2',
        'jul' => 'decimal:2',
        'ags' => 'decimal:2',
        'sep' => 'decimal:2',
        'okt' => 'decimal:2',
        'nov' => 'decimal:2',
        'des' => 'decimal:2',
    ];
}
