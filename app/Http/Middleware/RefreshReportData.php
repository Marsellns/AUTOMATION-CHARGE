<?php

namespace App\Http\Middleware;

use App\Services\SiteStatusSummaryService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RefreshReportData
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $action = (string) $request->route()?->getActionName();
        if (! $request->isMethodSafe() && str_starts_with($action, 'App\\Http\\Controllers\\') && $request->user() && $response->getStatusCode() < 400
            && ! $request->session()->has('errors')) {
            app(SiteStatusSummaryService::class)->invalidateCache();
        }

        return $response;
    }
}
