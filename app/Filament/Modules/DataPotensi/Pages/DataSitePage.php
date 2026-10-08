<?php

namespace App\Filament\Modules\DataPotensi\Pages;

use App\Filament\Modules\ModuleReportPage;

class DataSitePage extends ModuleReportPage
{
    protected static array $reportRoutes = ['data-potensi.data-site.*'];

    public static function reportNavigation(): ?array
    {
        return ['Data Potensi', 'Data Site', 'data-potensi.data-site.index', 'data-potensi.data-site.*', 'heroicon-o-map-pin'];
    }
}
