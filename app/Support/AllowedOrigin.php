<?php

namespace App\Support;

/**
 * C-08: the CORS origin was hard-coded to expensenew.vnrseeds.co.in / expense.vnrseeds.co.in, so
 * moving the app to another host broke cross-origin calls. The allowed origins are now this app's
 * own APP_URL plus CORS_EXTRA_ORIGINS (comma list); the request's Origin is echoed only when listed.
 */
class AllowedOrigin
{
    public static function list(): array
    {
        $own = parse_url((string) config('app.url'));
        $list = [];
        if (! empty($own['host'])) {
            $list[] = ($own['scheme'] ?? 'https').'://'.$own['host'].(isset($own['port']) ? ':'.$own['port'] : '');
        }
        foreach (explode(',', (string) env('CORS_EXTRA_ORIGINS', 'https://expense.vnrseeds.co.in,https://expensenew.vnrseeds.co.in')) as $o) {
            if (trim($o) !== '') {
                $list[] = rtrim(trim($o), '/');
            }
        }

        return array_values(array_unique($list));
    }

    public static function for($request = null): string
    {
        $origin = rtrim((string) (($request ?? request())?->headers->get('Origin') ?? ''), '/');
        $list = self::list();

        return in_array($origin, $list, true) ? $origin : ($list[0] ?? '');
    }
}
