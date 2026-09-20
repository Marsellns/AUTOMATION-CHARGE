<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bapss extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bapss';

    protected $fillable = [
        'site_code', 'site_name', 'tgl_bapss', 'tgl_dismantle', 'remark',
        'pdf_bapss', 'pdf_ba_dismantle', 'update_by', 'tgl_update',
    ];

    protected $casts = ['tgl_bapss' => 'date', 'tgl_dismantle' => 'date', 'tgl_update' => 'datetime'];
}
