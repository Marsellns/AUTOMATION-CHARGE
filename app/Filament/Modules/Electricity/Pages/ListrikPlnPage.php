<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class ListrikPlnPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.centralized.listrik-pln.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Listrik PLN', 'electricity.centralized.listrik-pln.index', 'electricity.centralized.listrik-pln.*', 'heroicon-o-bolt', 6 => 'Listrik Centralized'];
    }
}
