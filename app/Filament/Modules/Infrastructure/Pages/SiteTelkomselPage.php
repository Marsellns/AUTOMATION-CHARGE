<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class SiteTelkomselPage extends SewaLahanPage
{
    protected static array $reportQuery = ['ownership_scope' => 'Telkomsel'];

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Site Telkomsel', 'infrastruktur.sewa-lahan.index', 'infrastruktur.sewa-lahan.*', 'heroicon-o-building-office', ['ownership_scope' => 'Telkomsel'], 'Sewa Lahan'];
    }
}
