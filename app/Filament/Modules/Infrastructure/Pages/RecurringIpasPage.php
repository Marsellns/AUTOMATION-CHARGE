<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class RecurringIpasPage extends InfrastructureReportPage
{
    protected static array $reportRoutes = ['infrastruktur.recurring-ipas.*', 'infrastruktur.upload'];

    protected static ?string $uploadDataset = 'recurring-ipas';

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Recurring (ANT & Ipas)', 'infrastruktur.recurring-ipas.index', 'infrastruktur.recurring-ipas.*', 'heroicon-o-arrow-path'];
    }
}
