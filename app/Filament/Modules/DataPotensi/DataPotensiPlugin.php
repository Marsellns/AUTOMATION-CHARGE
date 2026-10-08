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
            Pages\SiteOwnerPage::class,
            Pages\DataSitePage::class,
            Pages\AssetTowerPage::class,
            Pages\SearchAllResourcePage::class,
        ];
    }
}
