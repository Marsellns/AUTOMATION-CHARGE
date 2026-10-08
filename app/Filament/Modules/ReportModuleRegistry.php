<?php

namespace App\Filament\Modules;

use App\Filament\Modules\Accounts\AccountsPlugin;
use App\Filament\Modules\Dashboard\DashboardPlugin;
use App\Filament\Modules\DataPotensi\DataPotensiPlugin;
use App\Filament\Modules\Electricity\ElectricityPlugin;
use App\Filament\Modules\EquipmentRelocation\EquipmentRelocationPlugin;
use App\Filament\Modules\Infrastructure\InfrastructurePlugin;
use App\Filament\Modules\PoMonitoring\PoMonitoringPlugin;
use App\Support\ReportModules;
use Illuminate\Routing\Route;

/** Registration order preserves the existing sidebar order. */
class ReportModuleRegistry
{
    /** @return array<ReportModulePlugin> */
    public static function plugins(): array
    {
        return [
            new DashboardPlugin,
            new InfrastructurePlugin,
            new ElectricityPlugin,
            new PoMonitoringPlugin,
            new DataPotensiPlugin,
            new EquipmentRelocationPlugin,
            new AccountsPlugin,
        ];
    }

    /** @return array<class-string<ModuleReportPage>> */
    public static function pages(): array
    {
        $pages = [];
        foreach (self::plugins() as $plugin) {
            foreach ($plugin->pages() as $page) {
                if (is_subclass_of($page, ModuleReportPage::class)) {
                    $pages[] = $page;
                }
            }
        }

        return $pages;
    }

    public static function navigation(): array
    {
        return array_merge(...array_map(fn ($plugin) => $plugin->navigation(), self::plugins()));
    }

    public static function dashboardGroups(): array
    {
        return array_merge(...array_map(fn ($plugin) => $plugin->dashboardGroups(), self::plugins()));
    }

    /** @return class-string<ModuleReportPage>|null */
    public static function pageForRoute(?Route $route, array $query = []): ?string
    {
        if (! ReportModules::isReportRoute($route)) {
            return null;
        }

        $selected = null;
        $bestScore = -1;
        foreach (self::pages() as $page) {
            $score = $page::routeMatchScore($route, $query);
            if ($score !== null && $score > $bestScore) {
                $selected = $page;
                $bestScore = $score;
            }
        }

        return $selected;
    }
}
