<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class InbuildingAnomalyPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.inbuilding.anomali.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Anomali Inbuilding', 'electricity.inbuilding.anomali.index', 'electricity.inbuilding.anomali.*', 'heroicon-o-exclamation-triangle', 6 => 'Listrik Inbuilding'];
    }
}
