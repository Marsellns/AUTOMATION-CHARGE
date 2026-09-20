<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoVarcost extends Model
{
    use HasFactory;

    public const EXPENSE_TYPES = ['Capex', 'Opex'];

    protected $table = 'po_varcost';

    public $timestamps = false;

    protected $fillable = [
        'po_number',
        'expense_type',
        'description',
        'gr_status',
        'po_year',
        'delivery_date',
        'update_by',
        'update_at',
    ];

    protected function casts(): array
    {
        return [
            'po_year' => 'integer',
            'delivery_date' => 'date',
            'update_at' => 'datetime',
        ];
    }
}
