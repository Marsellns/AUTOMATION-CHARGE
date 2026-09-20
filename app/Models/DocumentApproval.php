<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentApproval extends Model
{
    protected $fillable = [
        'document_id', 'step', 'approver_id', 'approver_name',
        'action', 'comments', 'acted_at',
    ];

    protected $casts = ['acted_at' => 'datetime', 'step' => 'integer'];

    public function document()
    {
        return $this->belongsTo(DocumentCirculation::class, 'document_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
