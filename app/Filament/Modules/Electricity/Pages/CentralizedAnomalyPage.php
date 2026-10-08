<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class CentralizedAnomalyPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.centralized.anomali.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Anomali Tagihan', 'electricity.centralized.anomali.index', 'electricity.centralized.anomali.*', 'heroicon-o-exclamation-triangle', 6 => 'Listrik Centralized'];
    }
}
