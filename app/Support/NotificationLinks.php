<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationLinks
{
    public static function read(array $data): string
    {
        if (($data['title'] ?? null) === 'Peringatan site Loss' && ($period = self::lossPeriod($data)) !== null) {
            return route('notifications.site-loss', $period);
        }

        if ($url = self::infrastructureAlert($data)) {
            return $url;
        }

        if (($data['title'] ?? null) === 'Peringatan anomali tagihan listrik'
            && is_string($data['source'] ?? null)) {
            $source = mb_strtolower(trim($data['source']), 'UTF-8');
            if (str_starts_with($source, 'centralized')) {
                return route('electricity.centralized.anomali.index');
            }
            if (str_starts_with($source, 'inbuilding')) {
                return route('electricity.inbuilding.anomali.index');
            }
        }

        return self::resolve($data['url'] ?? null, [
            'notifications.site-loss',
            'electricity.centralized.anomali.index',
            'electricity.inbuilding.anomali.index',
            'infrastruktur.index',
            'infrastruktur.sewa-lahan.index',
            'infrastruktur.combat.index',
            'presales.show',
        ]) ?? route('notifications.index');
    }

    private static function infrastructureAlert(array $data): ?string
    {
        $category = $data['category'] ?? match ($data['title'] ?? null) {
            'Peringatan Sewa Lahan' => 'sewa_lahan',
            'Peringatan Site TP' => 'site_tp',
            'Peringatan Site Telkomsel' => 'site_telkomsel',
            'Peringatan Combat' => 'combat',
            default => null,
        };

        $destination = match ($category) {
            'sewa_lahan' => ['infrastruktur.sewa-lahan.index', null],
            'site_tp' => ['infrastruktur.sewa-lahan.index', InfrastructureOwnership::TP],
            'site_telkomsel' => ['infrastruktur.sewa-lahan.index', InfrastructureOwnership::TELKOMSEL],
            'combat' => ['infrastruktur.combat.index', null],
            default => null,
        };
        if ($destination === null) {
            return null;
        }

        [$route, $ownership] = $destination;
        $parameters = ['filter_field' => 'lease_alert', 'filter_value' => 'active', 'unique_sites' => 1];
        if ($ownership !== null) {
            $parameters['ownership_scope'] = $ownership;
        }

        return route($route, $parameters).'#infrastructure-data';
    }

    public static function download(array $data): ?string
    {
        if (($data['title'] ?? null) === 'Peringatan site Loss' && ($period = self::lossPeriod($data)) !== null) {
            return route('notifications.site-loss.export', $period);
        }

        return self::resolve($data['download_url'] ?? null, [
            'notifications.site-loss.export',
            'electricity.centralized.anomali.export-excel',
            'electricity.inbuilding.anomali.export-excel',
        ]);
    }

    private static function lossPeriod(array $data): ?array
    {
        $month = filter_var($data['month'] ?? null, FILTER_VALIDATE_INT);
        $year = filter_var($data['year'] ?? null, FILTER_VALIDATE_INT);

        return $month !== false && $year !== false && $month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100
            ? ['bulan' => $month, 'tahun' => $year]
            : null;
    }

    private static function resolve(mixed $storedUrl, array $allowedRoutes): ?string
    {
        if (! is_string($storedUrl) || preg_match('/[\x00-\x20\\\\]/', $storedUrl)) {
            return null;
        }

        $parts = parse_url($storedUrl);
        if ($parts === false || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        // Stored notification domains belong to the source installation.
        // Resolve only known application routes and regenerate their URLs.
        try {
            $matched = Route::getRoutes()->match(Request::create('http://localhost'.$path, 'GET'));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return null;
        }
        if (! in_array($matched->getName(), $allowedRoutes, true)) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $url = route($matched->getName(), array_merge($query, $matched->parameters()));

        return isset($parts['fragment']) ? $url.'#'.rawurlencode(rawurldecode($parts['fragment'])) : $url;
    }
}
