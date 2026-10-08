<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\IpUtils;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Service providers boot after cached configuration is available.
        // Trust only the explicitly configured reverse proxies.
        $trustedProxies = trim((string) config('app.trusted_proxies', ''));
        if ($trustedProxies !== '') {
            TrustProxies::at($trustedProxies);
        }

        $request = request();
        $isHttpsRequest = $request->isSecure() || $this->hasTrustedForwardedHttps($request);

        // Local development supports both HTTP and the HTTPS tunnel. In
        // production, preserve the configured secure-cookie requirement.
        if ($this->app->environment('local')) {
            config(['session.secure' => $isHttpsRequest]);
        }

        if (config('app.force_https') && ($isHttpsRequest || $this->app->environment('production'))) {
            URL::forceScheme('https');
        }
    }

    private function hasTrustedForwardedHttps(Request $request): bool
    {
        $forwardedProto = strtolower(trim(explode(',', (string) $request->header('X-Forwarded-Proto'), 2)[0]));
        if ($forwardedProto !== 'https') {
            return false;
        }

        $trustedProxies = preg_split('/[,\s]+/', trim((string) config('app.trusted_proxies', '')), -1, PREG_SPLIT_NO_EMPTY);
        $remoteAddress = (string) $request->server('REMOTE_ADDR');

        return $remoteAddress !== '' && $trustedProxies !== [] && IpUtils::checkIp($remoteAddress, $trustedProxies);
    }
}
