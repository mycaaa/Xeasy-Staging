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
     * APPR-LOAD-01 (14 Nov 2026): when the "legacy API" is this app itself, answer the call in the same PHP process
     * instead of an HTTP call from the server to itself. The self-call needs a second free PHP worker and goes out
     * through the web server and back; under load or with a short gateway timeout it failed, and screens showed
     * "Failed to load data". Returns null when the call has to go over HTTP (another server, or not common/claim.php).
     */
    public static function inProcess(string $url, array $params, string $method = 'GET'): ?array
    {
        if (! self::isOwn() || ! config('services.xeasy_legacy.in_process', true)) {
            return null;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        $action = str_ends_with($path, '/claim.php') ? 'claim' : (str_ends_with($path, '/common.php') ? 'common' : null);
        if ($action === null) {
            return null;
        }
        if (! isset($params['ComId']) && preg_match('#/api/(\d+)/(common|claim)\.php$#', $path, $m)) {
            $params['ComId'] = (int) $m[1];
        }
        $sub = \Illuminate\Http\Request::create($url, strtoupper($method), $params);
        $key = (string) config('app.internal_api_key', '');
        if ($key !== '') {
            $sub->headers->set('X-Xeasy-Internal', $key);
        }
        try {
            $res = app(\App\Http\Controllers\Api\Mobile\LegacyProxyController::class)->{$action}($sub);
            $json = json_decode((string) $res->getContent(), true);

            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            Log::warning('LegacyApi in-process '.$action.' '.($params['value'] ?? '').': '.$e->getMessage());

            return null;
        }
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
            // WEB-TOKEN-01: the token kept at web login goes stale when the employee logs in on the app
            // (LoginController writes a new one); use the employee's current token
            $emp = (int) (session('employee_id') ?? 0);
            if ($emp > 0) {
                $current = \Illuminate\Support\Facades\DB::connection(session('hrims_connection') ?: 'hrims')
                    ->table('hrm_employee')->where('EmployeeID', $emp)->value('xeasy_tokenid');
                if (is_string($current) && strlen(trim($current)) > 5) {
                    $token = trim($current);
                }
            }
        } catch (\Throwable $e) {
            $token = $token ?? null;
        }

        return (is_string($token) && strlen(trim($token)) > 5) ? trim($token) : null;
    }
}
