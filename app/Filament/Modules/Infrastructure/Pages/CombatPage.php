<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class CombatPage extends InfrastructureReportPage
{
    protected static array $reportRoutes = ['infrastruktur.combat.*', 'infrastruktur.upload'];

    protected static ?string $uploadDataset = 'combat';

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Combat', 'infrastruktur.combat.index', 'infrastruktur.combat.*', 'heroicon-o-signal'];
    }
}
