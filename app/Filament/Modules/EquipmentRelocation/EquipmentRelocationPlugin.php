<?php

namespace App\Filament\Modules\EquipmentRelocation;

use App\Filament\Modules\ReportModulePlugin;

class EquipmentRelocationPlugin extends ReportModulePlugin
{
    public function getId(): string
    {
        return 'simaster-equipment-relocation';
    }

    public function pages(): array
    {
        return [
            Pages\EquipmentRelocationPage::class,
        ];
    }
}
