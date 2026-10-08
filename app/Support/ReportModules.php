<?php

namespace App\Support;

use App\Filament\Modules\ReportModuleRegistry;
use Illuminate\Routing\Route;
use Illuminate\View\View;
use ReflectionMethod;
use ReflectionNamedType;

/** Compatibility facade; navigation is owned by the individual Filament modules. */
class ReportModules
{
    public static function navigation(): array
    {
        return ReportModuleRegistry::navigation();
    }

    public static function dashboardGroups(): array
    {
        return ReportModuleRegistry::dashboardGroups();
    }

    /** Only existing, named GET controllers returning a View can be rendered. */
    public static function isReportRoute(?Route $route): bool
    {
        if (! $route || ! $route->getName() || ! in_array('GET', $route->methods(), true)) {
            return false;
        }

        $action = $route->getActionName();
        if (! str_starts_with($action, 'App\\Http\\Controllers\\')
            || str_starts_with($action, 'App\\Http\\Controllers\\Auth\\')
            || ! str_contains($action, '@')) {
            return false;
        }

        [$controller, $method] = explode('@', $action, 2);
        $type = (new ReflectionMethod($controller, $method))->getReturnType();

        return $type instanceof ReflectionNamedType && $type->getName() === View::class;
    }
}
