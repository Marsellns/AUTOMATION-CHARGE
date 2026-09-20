<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BongkarRampungMandiri extends Model
{
    use HasFactory;

    protected $table = 'bongkar_rampung_mandiri';

    protected $fillable = [
        'id_pelanggan',
        'site_id',
        'site_name',
    ];
}
