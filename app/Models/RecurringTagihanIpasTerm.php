<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringTagihanIpasTerm extends Model
{
    protected $table = 'recurring_tagihan_ipas_terms';

    protected $guarded = ['id'];

    protected $casts = ['source_details' => 'array', 'amount' => 'decimal:2'];
}
