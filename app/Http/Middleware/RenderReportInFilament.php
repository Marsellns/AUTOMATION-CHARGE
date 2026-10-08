<?php

namespace App\Http\Middleware;

use App\Filament\Pages\OperationalReport;
use App\Filament\Modules\ReportModuleRegistry;
use App\Support\ReportModules;
use Closure;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Http\Middleware\SetUpPanel;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RenderReportInFilament
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ReportModules::isReportRoute($request->route())) {
            return $next($request);
        }

        // Existing controller bindings, validation and role middleware run first.
        // The controller renders a fragment; the full page uses Filament's layout.
        return app(SetUpPanel::class)->handle($request, function (Request $request) use ($next): Response {
            return app(DispatchServingFilamentEvent::class)->handle($request, function (Request $request) use ($next): Response {
                $request->attributes->set('simaster.report_fragment', true);
                $response = $next($request);
                $view = method_exists($response, 'getOriginalContent') ? $response->getOriginalContent() : null;

                if (! $view instanceof View || $response->getStatusCode() !== 200) {
                    return $response;
                }

                $request->attributes->set('simaster.report_html', $response->getContent());
                $request->attributes->set('simaster.report_title', $view->getData()['pageTitle'] ?? null);
                $pageClass = ReportModuleRegistry::pageForRoute($request->route(), $request->query()) ?? OperationalReport::class;
                $page = app($pageClass)->__invoke();
                $page->headers->add($response->headers->all());
                $page->original = $view;

                return $page;
            });
        }, 'report');
    }
}
