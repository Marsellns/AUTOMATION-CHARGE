<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class ListrikAllPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.centralized.listrik-all.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Listrik All', 'electricity.centralized.listrik-all.index', 'electricity.centralized.listrik-all.*', 'heroicon-o-table-cells', 6 => 'Listrik Centralized'];
    }
}
