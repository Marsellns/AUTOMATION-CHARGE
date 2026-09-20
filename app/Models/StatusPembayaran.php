<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusPembayaran extends Model
{
    use HasFactory;

    protected $table = 'status_pembayaran';

    protected $fillable = [
        'listrik_pln_id', 'id_pelanggan', 'bulan', 'tahun', 'harga', 'remark', 'update_by', 'tanggal',
    ];

    protected $casts = [
        'bulan' => 'integer',
        'tahun' => 'integer',
        'harga' => 'decimal:2',
        'tanggal' => 'date',
    ];

    public function listrikPln()
    {
        return $this->belongsTo(ListrikPln::class, 'listrik_pln_id');
    }
}