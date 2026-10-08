<?php

namespace App\Filament\Modules\Accounts;

use App\Filament\Modules\ReportModulePlugin;
use App\Filament\Pages\AccountApprovals;

class AccountsPlugin extends ReportModulePlugin
{
    public function getId(): string
    {
        return 'simaster-accounts';
    }

    public function pages(): array
    {
        return [
            AccountApprovals::class,
            Pages\NotificationsPage::class,
            Pages\SiteLossNotificationsPage::class,
        ];
    }
}
