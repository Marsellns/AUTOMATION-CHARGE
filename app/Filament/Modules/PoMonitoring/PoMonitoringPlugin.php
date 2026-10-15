<?php

namespace App\Filament\Modules\PoMonitoring;

use App\Filament\Modules\ReportModulePlugin;

class PoMonitoringPlugin extends ReportModulePlugin
{
    public function getId(): string
    {
        return 'simaster-po-monitoring';
    }

    public function pages(): array
    {
        return [
            Pages\PoDashboardPage::class,
            Pages\PoVarcostPage::class,
            Pages\PoHqPage::class,
            Pages\PresalesPage::class,
        ];
    }

    public function dashboardGroups(): array
    {
        return ['PO Monitoring' => 'po-monitoring.dashboard'];
    }
}
