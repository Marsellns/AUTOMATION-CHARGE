<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPln extends Model
{
    use HasFactory;

    protected $table = 'payment_pln';

    public const STATUS = ['Done', 'Pending'];
    public const PHASA = ['1 Phasa', '3 Phasa'];

    protected $fillable = [
        'id_pelanggan', 'site_id', 'site_name', 'daya', 'phasa', 'gol_tarif',
        'unit_pln', 'harga', 'update_by', 'tanggal_status', 'status', 'bulan', 'tahun',
    ];

    protected $casts = [
        'daya' => 'integer',
        'harga' => 'decimal:2',
        'tanggal_status' => 'date',
        'bulan' => 'integer',
        'tahun' => 'integer',
    ];
}
