<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataAssetTower extends Model
{
    use HasFactory;

    protected $table = 'data_asset_towers';

    protected $fillable = [
        'site_id', 'site_name', 'site_company', 'site_type', 'grouping',
        'brand', 'part_name', 'owner', 'ownership_status', 'note',
        'tower_height', 'building_height', 'tower_type', 'update_by',
        'tanggal_update',
    ];

    protected $casts = [
        'tower_height' => 'decimal:2',
        'building_height' => 'decimal:2',
        'tanggal_update' => 'date',
    ];
}
