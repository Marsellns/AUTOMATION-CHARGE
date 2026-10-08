<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class BongkarRampungPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.centralized.bongkar-rampung-mandiri.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Bongkar Rampung', 'electricity.centralized.bongkar-rampung-mandiri.index', 'electricity.centralized.bongkar-rampung-mandiri.*', 'heroicon-o-wrench-screwdriver', 6 => 'Listrik Centralized'];
    }
}
