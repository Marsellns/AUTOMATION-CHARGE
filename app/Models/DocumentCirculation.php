<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCirculation extends Model
{
    protected $table = 'document_circulations';

    protected $fillable = [
        'document_title', 'document_number', 'file_name', 'file_path',
        'status', 'current_step', 'uploaded_by', 'uploaded_by_name',
        'rejected_reason',
    ];

    protected $casts = [
        'current_step' => 'integer',
    ];

    public const STEPS = [
        1 => 'Uploaded',
        2 => 'Manager NOP',
        3 => 'Manager SQ',
        4 => 'Manager NOS',
        5 => 'Manager NBAE',
    ];

    public const ROLE_TO_STEP = [
        'manager_nop' => 2,
        'manager_sq' => 3,
        'manager_nos' => 4,
        'manager_nbae' => 5,
        'manager nop' => 2,
        'manager sq' => 3,
        'manager nos' => 4,
        'manager nbae' => 5,
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvals()
    {
        return $this->hasMany(DocumentApproval::class, 'document_id')->orderBy('step');
    }

    public function currentStepName(): string
    {
        return self::STEPS[$this->current_step] ?? 'Selesai';
    }
}
