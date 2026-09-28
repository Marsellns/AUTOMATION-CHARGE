<?php

namespace App\Providers;

use Illuminate\Http\Request;
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
        $request = request();
        $isHttpsRequest = $request->isSecure() || $this->hasTrustedForwardedHttps($request);

        // SESSION_SECURE_COOKIE must follow the actual request scheme. A
        // global `true` value makes local HTTP sessions disappear in the
        // browser while the same app is also exposed through HTTPS ngrok.
        config(['session.secure' => $isHttpsRequest]);

        if (config('app.force_https') && $isHttpsRequest) {
            URL::forceScheme('https');
        }
    }

    private function hasTrustedForwardedHttps(Request $request): bool
    {
        $forwardedProto = strtolower(trim(explode(',', (string) $request->header('X-Forwarded-Proto'), 2)[0]));
        if ($forwardedProto !== 'https') {
            return false;
        }

        $trustedProxies = preg_split('/[,\s]+/', trim((string) env('TRUSTED_PROXIES', '')), -1, PREG_SPLIT_NO_EMPTY);
        $remoteAddress = (string) $request->server('REMOTE_ADDR');

        return $remoteAddress !== '' && $trustedProxies !== [] && IpUtils::checkIp($remoteAddress, $trustedProxies);
    }
}
