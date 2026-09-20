<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListrikInbuilding extends Model
{
    use HasFactory;

    protected $table = 'listrik_inbuilding';

    protected $fillable = [
        'site_id',
        'site_name',
        'status',
        'nama_bm',
        'no_npwp',
        'alamat',
        'telkomsel_tp',
        'daya',
        'harga_per_kwh',
        'update_by',
        'tanggal',
    ];

    protected $casts = [
        'daya' => 'integer',
        'harga_per_kwh' => 'decimal:2',
        'tanggal' => 'date',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentIbc::class, 'site_id', 'site_id');
    }
}
