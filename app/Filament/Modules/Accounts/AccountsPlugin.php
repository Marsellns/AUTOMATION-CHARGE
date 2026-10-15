<?php

namespace App\Filament\Modules\Accounts;

use App\Filament\Modules\ReportModulePlugin;
use App\Filament\Pages\AccountApprovals;
use App\Filament\Pages\UserManagement;

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
            UserManagement::class,
            Pages\NotificationsPage::class,
            Pages\SiteLossNotificationsPage::class,
        ];
    }
}
