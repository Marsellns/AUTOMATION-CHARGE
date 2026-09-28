<?php

namespace App\Services;

use App\Models\DocumentApproval;
use App\Models\DocumentCirculation;
use App\Models\User;
use App\Notifications\PresalesDocumentRejectedNotification;

class PresalesDocumentNotificationService
{
    /**
     * Notify everyone who needs visibility of a rejected Presales document.
     * The actor is intentionally excluded because they already performed the
     * rejection; each recipient receives a link to the same document detail.
     */
    public function sendRejectionReminder(
        DocumentCirculation $document,
        DocumentApproval $rejection,
        User $actor,
    ): void {
        $managerRoleNames = array_keys(DocumentCirculation::ROLE_TO_STEP);

        $managerIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $managerRoleNames))
            ->pluck('id');

        $recipientIds = $managerIds
            ->push($document->uploaded_by)
            ->filter()
            ->unique()
            ->reject(fn ($id) => (string) $id === (string) $actor->getKey());

        User::query()
            ->whereKey($recipientIds)
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(
                new PresalesDocumentRejectedNotification($document, $rejection)
            ));
    }
}
