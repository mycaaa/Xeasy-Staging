<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LV-U03 (11 Nov 2026): the API answers in four shapes ({"Code":"300","msg":..}, {"success":..,"data":..},
 * {"status":..} and {"code":..}). The mobile app in the stores reads those shapes, so they stay as they are by
 * default. A client that sends the header "X-Xeasy-Response: v2" (or ?response=v2) gets every JSON answer in one
 * envelope:
 *
 *   { "ok": true|false, "code": 200|400|401|403|404|422|500, "message": "...", "data": {...}|[...]|null,
 *     "errors": {...}|null, "legacy_code": "300"|"100"|null }
 *
 * ok follows the HTTP status and the legacy markers (Code 300 / success true / status success). Everything else the
 * controller returned is kept under data. The next app release switches to v2 (docs/API_RESPONSE.md); after that
 * the default can change.
 */
class UnifiedApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $want = strtolower((string) ($request->header('X-Xeasy-Response') ?: $request->query('response', '')));
        if ($want !== 'v2' || ! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        $response->setData(self::envelope(is_array($payload) ? $payload : ['value' => $payload], $response->getStatusCode()));
        $response->headers->set('X-Xeasy-Response', 'v2');

        return $response;
    }

    public static function envelope(array $p, int $http): array
    {
        $legacy = isset($p['Code']) ? (string) $p['Code'] : (isset($p['code']) && ! is_int($p['code']) ? (string) $p['code'] : null);
        if ($legacy === null && isset($p['status']) && is_scalar($p['status']) && ctype_digit((string) $p['status'])) {
            $legacy = (string) $p['status']; // {"status":"100","msg":..}
        }
        $ok = $http < 400;
        if ($legacy !== null) {
            $ok = $ok && in_array($legacy, ['300', '200'], true);
        }
        if (array_key_exists('success', $p)) {
            $ok = $ok && (bool) $p['success'];
        }
        if (isset($p['status']) && is_string($p['status']) && ! ctype_digit($p['status'])) {
            $ok = $ok && in_array(strtolower($p['status']), ['success', 'ok', 'true'], true);
        }
        // a bare {"msg":"Parameter Missing!"} carries no success marker: read the message
        $bare = count(array_diff(array_keys($p), ['msg', 'message', 'Message'])) === 0;
        if ($bare && preg_match('/missing|invalid|fail|error|not (found|allowed|valid)|denied|required|wrong/i', (string) ($p['msg'] ?? $p['message'] ?? $p['Message'] ?? ''))) {
            $ok = false;
        }
        $message = (string) ($p['message'] ?? $p['msg'] ?? $p['Message'] ?? ($ok ? 'OK' : 'Request failed'));
        $errors = $p['errors'] ?? null;
        if (array_key_exists('data', $p) && count(array_diff(array_keys($p), ['data', 'success', 'status', 'message', 'msg', 'Code', 'code', 'errors'])) === 0) {
            $data = $p['data'];
        } else {
            $data = array_diff_key($p, array_flip(['success', 'status', 'message', 'msg', 'Message', 'Code', 'errors']));
            $data = $data === [] ? null : $data;
        }
        $code = $http >= 400 ? $http : ($ok ? 200 : 400);

        return ['ok' => $ok, 'code' => $code, 'message' => $message, 'data' => $data, 'errors' => $errors, 'legacy_code' => $legacy];
    }
}
