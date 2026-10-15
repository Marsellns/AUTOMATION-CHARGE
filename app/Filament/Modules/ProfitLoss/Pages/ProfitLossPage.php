<?php

namespace App\Filament\Modules\ProfitLoss\Pages;

use App\Filament\Modules\ModuleReportPage;

class ProfitLossPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['pnl.*'];

    public static function reportNavigation(): ?array
    {
        return ['Profit & Loss', 'Analisis Profit & Loss', 'pnl.index', 'pnl.*', 'heroicon-o-banknotes'];
    }
}
