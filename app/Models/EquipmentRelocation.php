<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentRelocation extends Model
{
    protected $fillable = [
        'donor_uniq_key', 'donor_acceptor', 'site_target_source', 'pic',
        'progress', 'remark', 'created_by', 'updated_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
