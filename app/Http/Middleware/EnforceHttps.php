<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnforceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production') && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
