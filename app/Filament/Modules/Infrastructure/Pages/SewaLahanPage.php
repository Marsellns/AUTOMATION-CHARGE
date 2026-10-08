<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class SewaLahanPage extends InfrastructureReportPage
{
    protected static array $reportRoutes = ['infrastruktur.sewa-lahan.*', 'infrastruktur.upload'];

    protected static ?string $uploadDataset = 'sewa-lahan';

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Sewa Lahan', 'infrastruktur.sewa-lahan.index', 'infrastruktur.sewa-lahan.*', 'heroicon-o-map-pin'];
    }
}
