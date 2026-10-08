<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class MasterInbuildingPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.inbuilding.listrik-inbuilding.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Master Inbuilding', 'electricity.inbuilding.listrik-inbuilding.index', 'electricity.inbuilding.listrik-inbuilding.*', 'heroicon-o-building-office', 6 => 'Listrik Inbuilding'];
    }
}
