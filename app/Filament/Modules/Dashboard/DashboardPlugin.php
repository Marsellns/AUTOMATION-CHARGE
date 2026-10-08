<?php

namespace App\Filament\Modules\Dashboard;

use App\Filament\Modules\ReportModulePlugin;

class DashboardPlugin extends ReportModulePlugin
{
    public function getId(): string
    {
        return 'simaster-dashboard';
    }

    public function pages(): array
    {
        return [
            Pages\ExecutiveDashboardPage::class,
            Pages\ProfitLossPage::class,
        ];
    }
}
