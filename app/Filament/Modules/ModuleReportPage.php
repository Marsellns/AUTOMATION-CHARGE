<?php

namespace App\Filament\Modules;

use App\Filament\Pages\OperationalReport;
use App\Support\OperationalDatasets;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/** A module-owned Filament page served through its existing Laravel routes. */
abstract class ModuleReportPage extends OperationalReport
{
    protected static array $reportRoutes = [];

    protected static array $reportQuery = [];

    public static function reportNavigation(): ?array
    {
        return null;
    }

    public static function routeMatchScore(Route $route, array $query = []): ?int
    {
        $routeName = $route->getName();
        if ($routeName === 'operations.create') {
            $dataset = $route->parameter('dataset');
            $routeName = OperationalDatasets::DEFINITIONS[$dataset][2] ?? null;
            if ($routeName === null) {
                return null;
            }
        }
        foreach (static::$reportQuery as $key => $value) {
            if (($query[$key] ?? null) !== $value) {
                return null;
            }
        }

        $score = null;
        foreach (static::$reportRoutes as $pattern) {
            if (Str::is($pattern, $routeName)) {
                $score = max($score ?? 0, strlen($pattern) + count(static::$reportQuery) * 100);
            }
        }

        return $score;
    }

    public static function reportComponentName(): string
    {
        $segments = explode('\\', Str::after(static::class, __NAMESPACE__.'\\'));

        return 'simaster.modules.'.implode('.', array_map(fn ($segment) => Str::kebab($segment), $segments));
    }

    public static function registerRoutes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        // Controllers keep their URLs, bindings, validation and middleware.
    }

    protected function acceptsSourceRoute(?Route $route, array $query): bool
    {
        return ReportModuleRegistry::pageForRoute($route, $query) === static::class;
    }
}
