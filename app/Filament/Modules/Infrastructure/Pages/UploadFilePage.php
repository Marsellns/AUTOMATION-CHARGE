<?php

namespace App\Filament\Modules\Infrastructure\Pages;

use App\Filament\Modules\ModuleReportPage;

class UploadFilePage extends ModuleReportPage
{
    protected static array $reportRoutes = ['infrastruktur.upload-file.*'];

    public static function reportNavigation(): ?array
    {
        return ['Infrastruktur Management', 'Upload File PDF', 'infrastruktur.upload-file.index', 'infrastruktur.upload-file.*', 'heroicon-o-arrow-up-tray'];
    }
}
