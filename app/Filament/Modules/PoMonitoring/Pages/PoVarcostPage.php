<?php

namespace App\Filament\Modules\PoMonitoring\Pages;

use App\Filament\Modules\ModuleReportPage;

class PoVarcostPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['po-varcost.*'];

    public static function reportNavigation(): ?array
    {
        return ['PO Monitoring', 'PO Varcost', 'po-varcost.index', 'po-varcost.*', 'heroicon-o-document-currency-dollar'];
    }
}
