<?php

namespace App\Filament\Modules\Infrastructure;

use App\Filament\Modules\ReportModulePlugin;

class InfrastructurePlugin extends ReportModulePlugin
{
    public function getId(): string
    {
        return 'simaster-infrastructure';
    }

    public function pages(): array
    {
        return [
            Pages\InfrastructureDashboardPage::class,
            Pages\SewaLahanPage::class,
            Pages\SiteTelkomselPage::class,
            Pages\SiteTpPage::class,
            Pages\CombatPage::class,
            Pages\RecurringIpasPage::class,
            Pages\RecurringTagihanIpasPage::class,
            Pages\JaknetPage::class,
            Pages\SiteUnlockPage::class,
            Pages\BapssPage::class,
            Pages\UploadFilePage::class,
        ];
    }

    public function dashboardGroups(): array
    {
        return ['Infrastruktur Management' => 'infrastruktur.index'];
    }
}
