<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PoHq extends Model
{
    // Audit trail: setiap create/update/delete lewat modul PO HQ adalah
    // input MANUAL, jadi TIDAK dibungkus disableLogging() — otomatis
    // tercatat siapa (causer), kapan, dan nilai sebelum/sesudahnya.
    use LogsActivity;

    protected $table = 'po_hq';

    protected $fillable = [
        'po_number',
        'agreement_number',
        'vendor_name',
        'description',
        'expense_type',
        'status',
        'location',
    ];

    public const EXPENSE_TYPES = ['Capex', 'Opex'];

    public const STATUSES = ['Draft', 'On Process', 'Approved', 'Rejected', 'Closed'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([...$this->fillable])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
