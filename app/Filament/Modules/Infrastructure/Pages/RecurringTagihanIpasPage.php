<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class RecurringTagihanIpasPage extends InfrastructureReportPage
{
    protected static array $reportRoutes = ['infrastruktur.recurring-tagihan-ipas.*', 'infrastruktur.upload'];

    protected static ?string $uploadDataset = 'recurring-tagihan-ipas';

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Recurring (Tagihan Ipas)', 'infrastruktur.recurring-tagihan-ipas.index', 'infrastruktur.recurring-tagihan-ipas.*', 'heroicon-o-document-currency-dollar'];
    }
}
