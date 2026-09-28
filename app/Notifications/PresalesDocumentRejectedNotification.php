<?php

namespace App\Notifications;

use App\Models\DocumentApproval;
use App\Models\DocumentCirculation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PresalesDocumentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DocumentCirculation $document,
        private readonly DocumentApproval $rejection,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $step = (int) $this->rejection->step;
        $stepName = DocumentCirculation::STEPS[$step] ?? 'tahap '.$step;

        return [
            'type' => 'presales_document_rejected',
            'title' => 'Dokumen Presales ditolak',
            'message' => sprintf(
                '%s ditolak oleh %s pada tahap %s. Alasan: %s',
                $this->document->document_title,
                $this->rejection->approver_name,
                $stepName,
                $this->rejection->comments ?: '-'
            ),
            'document_id' => $this->document->id,
            'document_title' => $this->document->document_title,
            'document_number' => $this->document->document_number,
            'approval_step' => $step,
            'rejected_by' => $this->rejection->approver_name,
            'rejection_reason' => $this->rejection->comments,
            'url' => route('presales.show', $this->document),
        ];
    }
}
