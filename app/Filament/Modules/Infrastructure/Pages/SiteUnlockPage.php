<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class SiteUnlockPage extends InfrastructureReportPage
{
    protected static array $reportRoutes = ['infrastruktur.site-unlock.*', 'infrastruktur.upload'];

    protected static ?string $uploadDataset = 'site-unlock';

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Data Site Unlock', 'infrastruktur.site-unlock.index', 'infrastruktur.site-unlock.*', 'heroicon-o-lock-open'];
    }
}
