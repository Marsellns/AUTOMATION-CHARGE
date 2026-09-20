<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BongkarRampungPln extends Model
{
    use HasFactory;

    protected $table = 'bongkar_rampung_pln';

    protected $fillable = [
        'listrik_pln_id', 'id_pelanggan', 'no_surat_boram', 'tanggal_surat_boram', 'mitra_boram',
    ];

    protected $casts = [
        'tanggal_surat_boram' => 'date',
    ];

    public function listrikPln()
    {
        return $this->belongsTo(ListrikPln::class, 'listrik_pln_id');
    }
}