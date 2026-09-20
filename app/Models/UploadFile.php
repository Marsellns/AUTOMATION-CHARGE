<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Infrastruktur management — 08 Upload File PDF.
 *
 * Tabel umum untuk menyimpan file PDF yang diupload user.
 */
class UploadFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'keterangan', 'file_path', 'update_by', 'update_time',
    ];

    protected $casts = [
        'update_time' => 'datetime',
    ];
}
