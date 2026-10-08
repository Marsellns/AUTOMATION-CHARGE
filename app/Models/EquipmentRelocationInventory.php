<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentRelocationInventory extends Model
{
    protected $table = 'equipment_relocation_inventory';

    protected $fillable = [
        'uniq_key', 'site_id', 'nop', 'region', 'to_name', 'ne_name',
        'equipment_group', 'equipment_type', 'category', 'board_name',
        'board_type', 'serial_number', 'utilization_status', 'safe_to_reloc',
    ];
}
