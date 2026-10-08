<?php

namespace App\Filament\Modules\Infrastructure\Pages;

class SiteTpPage extends SewaLahanPage
{
    protected static array $reportQuery = ['ownership_scope' => 'TP'];

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Site TP', 'infrastruktur.sewa-lahan.index', 'infrastruktur.sewa-lahan.*', 'heroicon-o-building-office', ['ownership_scope' => 'TP'], 'Sewa Lahan'];
    }
}
