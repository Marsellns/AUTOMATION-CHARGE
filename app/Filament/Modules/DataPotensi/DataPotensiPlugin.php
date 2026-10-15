<?php

namespace App\Filament\Modules\DataPotensi;

use App\Filament\Modules\ReportModulePlugin;

class DataPotensiPlugin extends ReportModulePlugin
{
    public function getId(): string
    {
        return 'simaster-data-potensi';
    }

    public function pages(): array
    {
        return [
            Pages\FeasibilityDashboardPage::class,
            Pages\SiteOwnerPage::class,
            Pages\DataSitePage::class,
            Pages\AssetTowerPage::class,
            Pages\SearchAllResourcePage::class,
        ];
    }

    public function dashboardGroups(): array
    {
        return ['Feasibility Site' => 'data-potensi.dashboard'];
    }
}
