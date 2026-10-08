<?php

namespace App\Filament\Modules\Electricity;

use App\Filament\Modules\ReportModulePlugin;

class ElectricityPlugin extends ReportModulePlugin
{
    public function getId(): string
    {
        return 'simaster-electricity';
    }

    public function pages(): array
    {
        return [
            Pages\ElectricityDashboardPage::class,
            Pages\CentralizedPage::class,
            Pages\InbuildingPage::class,
            Pages\ListrikPlnPage::class,
            Pages\CentralizedPaymentPage::class,
            Pages\CentralizedAnomalyPage::class,
            Pages\BongkarRampungPage::class,
            Pages\ListrikAllPage::class,
            Pages\MasterInbuildingPage::class,
            Pages\InputTagihanIbcPage::class,
            Pages\InbuildingPaymentPage::class,
            Pages\InbuildingAnomalyPage::class,
            Pages\InbuildingAllPage::class,
        ];
    }

    public function dashboardGroups(): array
    {
        return ['Electricity' => 'electricity.dashboard'];
    }
}
