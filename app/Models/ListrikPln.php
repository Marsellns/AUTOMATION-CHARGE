<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListrikPln extends Model
{
    use HasFactory;

    protected $table = 'listrik_pln';

    public const STATUS_AKTIF_SITE = ['Aktif', 'Tidak Aktif'];
    public const PHASA = ['1 Phasa', '3 Phasa'];
    public const STATUS_AMR = ['Ada', 'Tidak Ada'];
    public const JENIS_BAYAR = ['Prabayar', 'Pascabayar'];

    protected $fillable = [
        'id_pelanggan', 'site_id', 'site_name', 'alamat', 'status_aktif_site',
        'nama_pelanggan', 'daya_va', 'phasa', 'status_amr', 'gol_tarif',
        'unit_layanan_pln', 'jenis_bayar', 'tp_owner', 'nop', 'update_by', 'tanggal',
    ];

    protected $casts = [
        'daya_va' => 'integer',
        'tanggal' => 'date',
    ];

    public function statusPembayaran()
    {
        return $this->hasMany(StatusPembayaran::class, 'listrik_pln_id');
    }

    public function bongkarRampung()
    {
        return $this->hasMany(BongkarRampungPln::class, 'listrik_pln_id');
    }
}