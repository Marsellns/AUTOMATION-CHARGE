<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class CentralizedPaymentPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.centralized.payment.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Payment Centralized', 'electricity.centralized.payment.index', 'electricity.centralized.payment.*', 'heroicon-o-credit-card', 6 => 'Listrik Centralized'];
    }
}
