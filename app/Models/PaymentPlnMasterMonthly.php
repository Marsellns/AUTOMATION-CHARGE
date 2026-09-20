<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlnMasterMonthly extends Model
{
    protected $table = 'payment_pln_master_monthly';

    protected $guarded = [];

    protected $casts = [
        'bulan' => 'integer',
        'tahun' => 'integer',
        'amount' => 'decimal:2',
        'is_paid' => 'boolean',
    ];
}
