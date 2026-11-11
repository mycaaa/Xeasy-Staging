<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * LV-S04 (audit, 6 Nov 2026): login check for routes/mobile-api.php, which before had none
 * (most controllers re-checked the token themselves, some did not, and the employee id was
 * taken from the request). Rules in config/mobile_api_auth.php.
 */
class MobileApiAuthenticate extends ApiAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS') || $this->isPublic($request)) {
            return $next($request);
        }
        $enforce = (bool) config('mobile_api_auth.enforce', true);

        $via = $this->authenticatedVia($request);
        if ($via === null) {
            Log::warning('Mobile API request without valid login', ['path' => $request->path(), 'ip' => $request->ip(), 'enforced' => $enforce]);
            if ($enforce) {
                return response()->json(['success' => false, 'Code' => '401', 'msg' => 'Login required', 'message' => 'Login required'], 401);
            }

            return $next($request);
        }
        $request->attributes->set('api_auth_via', $via);

        // The acting employee is the token's employee
        if ($via === 'mobile_token') {
            $tokenEmp = (int) $request->attributes->get('api_auth_empid');
            foreach ((array) config('mobile_api_auth.actor_fields', []) as $f) {
                $given = $request->input($f);
                if ($given === null || $given === '' || is_array($given)) {
                    continue;
                }
                if ((string) (int) $given !== (string) $given || (int) $given !== $tokenEmp) {
                    Log::warning('Mobile API actor mismatch', ['path' => $request->path(), 'field' => $f, 'given' => (string) $given, 'token_emp' => $tokenEmp, 'enforced' => $enforce]);
                    if ($enforce) {
                        return response()->json(['success' => false, 'Code' => '403', 'msg' => 'Not allowed for this employee', 'message' => 'Not allowed for this employee'], 403);
                    }
                }
            }
        }

        return $next($request);
    }

    private function isPublic(Request $request): bool
    {
        $path = preg_replace('#^api/#', '', ltrim($request->path(), '/'));
        foreach ((array) config('mobile_api_auth.public_paths', []) as $p) {
            if ($path === $p || ($p !== '' && str_contains($p, '*') && fnmatch($p, $path))) {
                return true;
            }
        }
        if (preg_match('#(^|/)common\.php$#', $path)) {
            $value = strtolower((string) $request->input('value', ''));
            if (in_array($value, (array) config('mobile_api_auth.public_values', []), true)) {
                return true;
            }
        }

        return false;
    }
}
