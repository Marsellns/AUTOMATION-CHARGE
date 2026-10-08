<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class BapssPage extends InfrastructureReportPage
{
    protected static array $reportRoutes = ['infrastruktur.bapss.*', 'infrastruktur.upload'];

    protected static ?string $uploadDataset = 'bapss';

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'BAPSS', 'infrastruktur.bapss.index', 'infrastruktur.bapss.*', 'heroicon-o-folder'];
    }
}
