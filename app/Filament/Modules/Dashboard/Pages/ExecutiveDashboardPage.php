<?php

namespace App\Filament\Modules\Dashboard\Pages;

use App\Filament\Modules\ModuleReportPage;

class ExecutiveDashboardPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['dashboard'];

    public static function reportNavigation(): ?array
    {
        return ['Dashboard', 'Dashboard Utama', 'dashboard', 'dashboard', 'heroicon-o-chart-bar'];
    }
}
