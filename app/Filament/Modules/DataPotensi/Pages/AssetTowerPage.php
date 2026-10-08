<?php

namespace App\Filament\Modules\DataPotensi\Pages;

use App\Filament\Modules\ModuleReportPage;

class AssetTowerPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['data-potensi.asset-tower.*'];

    public static function reportNavigation(): ?array
    {
        return ['Data Potensi', 'Asset Tower', 'data-potensi.asset-tower.index', 'data-potensi.asset-tower.*', 'heroicon-o-signal'];
    }
}
