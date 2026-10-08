<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class CentralizedPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.centralized.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Listrik Centralized', 'electricity.centralized.listrik-pln.index', 'electricity.centralized.*', 'heroicon-o-bolt', 6 => null];
    }
}
