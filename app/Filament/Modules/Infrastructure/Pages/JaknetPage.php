<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class JaknetPage extends InfrastructureReportPage
{
    protected static array $reportRoutes = ['infrastruktur.jaknet.*', 'infrastruktur.upload'];

    protected static ?string $uploadDataset = 'jaknet';

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Jaknet & Dapot', 'infrastruktur.jaknet.index', 'infrastruktur.jaknet.*', 'heroicon-o-document-text'];
    }
}
