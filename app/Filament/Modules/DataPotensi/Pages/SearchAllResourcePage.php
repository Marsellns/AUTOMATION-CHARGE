<?php

namespace App\Filament\Modules\DataPotensi\Pages;

use App\Filament\Modules\ModuleReportPage;

class SearchAllResourcePage extends ModuleReportPage
{
    protected static array $reportRoutes = ['data-potensi.search-all-resource.*'];

    public static function reportNavigation(): ?array
    {
        return ['Data Potensi', 'Search All Resource', 'data-potensi.search-all-resource.index', 'data-potensi.search-all-resource.*', 'heroicon-o-magnifying-glass'];
    }
}
