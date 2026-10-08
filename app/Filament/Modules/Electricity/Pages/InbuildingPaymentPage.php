<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class InbuildingPaymentPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.inbuilding.payment.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Payment Inbuilding', 'electricity.inbuilding.payment.index', 'electricity.inbuilding.payment.*', 'heroicon-o-credit-card', 6 => 'Listrik Inbuilding'];
    }
}
