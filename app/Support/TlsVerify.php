<?php

namespace App\Support;

/** LV-S11: the 'verify' option for outbound HTTP (was hard-coded false in 21 calls). */
class TlsVerify
{
    public static function option(): bool|string
    {
        $bundle = (string) config('services.http_tls.ca_bundle', '');
        if ($bundle !== '' && is_file($bundle)) {
            return $bundle;
        }
        $v = config('services.http_tls.verify', true);
        if (is_string($v)) {
            $v = ! in_array(strtolower($v), ['0', 'false', 'off', 'no'], true);
        }
        // never off in production
        return app()->environment('production') ? true : (bool) $v;
    }
}
