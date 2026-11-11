<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Target, token and HTTP client for the legacy-format claim API
 * (claim.php / common.php / extract-data.php).
 *
 * The employee web screens used to post straight to the production legacy
 * host with TLS verification off and a hard-coded device token. The target is
 * now config('services.xeasy_legacy.base_url'). When it is empty it defaults
 * to this app's own Laravel re-implementation (routes/mobile-api.php, mounted
 * under /api), so the server-side claim rules in ClaimSubmissionValidator run
 * and a test server never writes to production.
 */
class LegacyApi
{
    /** Base URL without a trailing slash, e.g. https://app.example/api */
    public static function baseUrl(): string
    {
        $own = rtrim((string) config('app.url'), '/') . '/api';
        $configured = trim((string) config('services.xeasy_legacy.base_url', ''));
        if ($configured === '') {
            return $own;
        }

        $host = strtolower((string) parse_url($configured, PHP_URL_HOST));
        $productionHosts = array_map('strtolower', (array) config('services.xeasy_legacy.production_hosts', []));
        $allowProduction = (bool) config('services.xeasy_legacy.allow_production', false);
        if ($host !== '' && in_array($host, $productionHosts, true) && ! $allowProduction) {
            Log::warning('XEASY_LEGACY_API_BASE_URL points at a production host but XEASY_LEGACY_ALLOW_PRODUCTION is off; using this app instead.', ['host' => $host]);

            return $own;
        }

        return rtrim($configured, '/');
    }

    /** Full URL for a legacy script, optionally under /{companyId}/. */
    public static function url(string $script, $companyId = null): string
    {
        $prefix = ($companyId !== null && $companyId !== '') ? '/' . (int) $companyId : '';

        return self::baseUrl() . $prefix . '/' . ltrim($script, '/');
    }

    /** True when legacy calls are served by this app itself (not an outside legacy host). */
    public static function isOwn(): bool
    {
        return self::baseUrl() === rtrim((string) config('app.url'), '/') . '/api';
    }

    /**
     * HTTP client with TLS verification on (or a configured CA bundle).
     *
     * LEGACY-02: when the target is this app, the call carries the internal relay key
     * (X-Xeasy-Internal = INTERNAL_API_KEY), so web users without a mobile app token
     * can still save and read through the in-app legacy API.
     */
    public static function http(?int $timeout = null): PendingRequest
    {
        $caBundle = (string) config('services.xeasy_legacy.ca_bundle', '');
        $client = Http::withOptions(['verify' => $caBundle !== '' ? $caBundle : true])
            ->timeout($timeout ?? (int) config('services.xeasy_legacy.timeout', 60));
        $key = (string) config('app.internal_api_key', '');
        if ($key !== '' && self::isOwn()) {
            $client = $client->withHeaders(['X-Xeasy-Internal' => $key, 'X-Api-Key' => $key]); // X-Api-Key passes MobileApiAuthenticate
        }

        return $client;
    }

    /**
     * Token for claim.php / common.php calls: the logged-in employee's own
     * xeasy token (set at login, AuthController), else the configured fallback.
     */
    public static function webToken(): string
    {
        return self::sessionToken() ?? (string) config('services.xeasy_legacy.web_token', '');
    }

    /** Token for extract-data.php calls (same rule, separate fallback). */
    public static function extractToken(): string
    {
        return self::sessionToken() ?? (string) config('services.xeasy_legacy.extract_token', '');
    }

    private static function sessionToken(): ?string
    {
        try {
            $token = session('token_id');
        } catch (\Throwable $e) {
            return null;
        }

        return (is_string($token) && strlen(trim($token)) > 5) ? trim($token) : null;
    }
}
