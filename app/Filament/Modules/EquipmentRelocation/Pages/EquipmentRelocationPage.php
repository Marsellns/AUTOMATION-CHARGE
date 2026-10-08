<?php

namespace App\Filament\Modules\EquipmentRelocation\Pages;

use App\Filament\Modules\ModuleReportPage;

class EquipmentRelocationPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['equipment-relocation.*'];

    public static function reportNavigation(): ?array
    {
        return ['Equipment', 'Equipment Relocation', 'equipment-relocation.index', 'equipment-relocation.*', 'heroicon-o-truck'];
    }
}
