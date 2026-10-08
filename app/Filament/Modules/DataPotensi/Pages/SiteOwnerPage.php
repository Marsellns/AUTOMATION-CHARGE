<?php

namespace App\Filament\Modules\DataPotensi\Pages;

use App\Filament\Modules\ModuleReportPage;

class SiteOwnerPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['data-potensi.site-owner.*'];

    public static function reportNavigation(): ?array
    {
        return ['Data Potensi', 'Site Owner', 'data-potensi.site-owner.index', 'data-potensi.site-owner.*', 'heroicon-o-users'];
    }
}
