<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIbc extends Model
{
    use HasFactory;

    protected $table = 'payment_ibc';

    protected $fillable = [
        'site_id',
        'site_name',
        'nama_bm',
        'daya',
        'status',
        'jumlah_tagihan',
        'invoice',
        'update_by',
        'tanggal_update_status',
        'bulan',
        'tahun',
    ];

    protected $casts = [
        'daya' => 'integer',
        'jumlah_tagihan' => 'decimal:2',
        'tanggal_update_status' => 'date',
        'bulan' => 'integer',
        'tahun' => 'integer',
    ];

    public function listrikInbuilding(): BelongsTo
    {
        return $this->belongsTo(ListrikInbuilding::class, 'site_id', 'site_id');
    }
}
