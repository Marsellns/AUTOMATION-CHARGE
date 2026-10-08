<?php

namespace App\Filament\Modules\PoMonitoring\Pages;

use App\Filament\Modules\ModuleReportPage;

class PresalesPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['presales.*'];

    public static function reportNavigation(): ?array
    {
        return ['PO Monitoring', 'Presales', 'presales.index', 'presales.*', 'heroicon-o-folder-open'];
    }
}
