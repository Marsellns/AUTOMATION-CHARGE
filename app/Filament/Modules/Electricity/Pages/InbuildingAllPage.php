<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class InbuildingAllPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.inbuilding.inbuilding-all.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Inbuilding All', 'electricity.inbuilding.inbuilding-all.index', 'electricity.inbuilding.inbuilding-all.*', 'heroicon-o-table-cells', 6 => 'Listrik Inbuilding'];
    }
}
