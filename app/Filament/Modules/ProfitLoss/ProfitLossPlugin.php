<?php

namespace App\Filament\Modules\ProfitLoss;

use App\Filament\Modules\ReportModulePlugin;

class ProfitLossPlugin extends ReportModulePlugin
{
    public function getId(): string
    {
        return 'simaster-profit-loss';
    }

    public function pages(): array
    {
        return [Pages\ProfitLossPage::class];
    }

    public function dashboardGroups(): array
    {
        return ['Profit & Loss' => 'pnl.index'];
    }
}
