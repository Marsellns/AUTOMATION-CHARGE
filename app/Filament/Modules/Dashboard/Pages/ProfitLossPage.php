<?php

namespace App\Filament\Modules\Dashboard\Pages;

use App\Filament\Modules\ModuleReportPage;

class ProfitLossPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['pnl.*'];

    public static function reportNavigation(): ?array
    {
        return ['Dashboard', 'Profit & Loss', 'pnl.index', 'pnl.*', 'heroicon-o-banknotes'];
    }
}
