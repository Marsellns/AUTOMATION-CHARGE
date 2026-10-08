<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class InbuildingPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.inbuilding.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Listrik Inbuilding', 'electricity.inbuilding.listrik-inbuilding.index', 'electricity.inbuilding.*', 'heroicon-o-building-office', 6 => null];
    }
}
