<?php

namespace App\Filament\Modules\Infrastructure\Pages;

use App\Filament\Modules\ModuleReportPage;
use Illuminate\Routing\Route;

abstract class InfrastructureReportPage extends ModuleReportPage
{
    protected static ?string $uploadDataset = null;

    public static function routeMatchScore(Route $route, array $query = []): ?int
    {
        if ($route->getName() === 'infrastruktur.upload'
            && $route->parameter('dataset') !== static::$uploadDataset) {
            return null;
        }

        return parent::routeMatchScore($route, $query);
    }
}
