<?php

namespace App\Filament\Modules\Electricity\Pages;

use App\Filament\Modules\ModuleReportPage;

class InputTagihanIbcPage extends ModuleReportPage
{
    protected static array $reportRoutes = ['electricity.inbuilding.input-tagihan-ibc.*'];

    public static function reportNavigation(): ?array
    {
        return ['Electricity', 'Input Tagihan IBC', 'electricity.inbuilding.input-tagihan-ibc.index', 'electricity.inbuilding.input-tagihan-ibc.*', 'heroicon-o-pencil-square', 6 => 'Listrik Inbuilding'];
    }
}
