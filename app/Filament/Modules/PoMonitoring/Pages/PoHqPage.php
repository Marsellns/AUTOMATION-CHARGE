<?php

namespace App\Filament\Modules\PoMonitoring\Pages;

use App\Filament\Modules\ModuleReportPage;

class PoHqPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['po-hq.*'];

    public static function reportNavigation(): ?array
    {
        return ['PO Monitoring', 'PO HQ', 'po-hq.index', 'po-hq.*', 'heroicon-o-document-text'];
    }
}
