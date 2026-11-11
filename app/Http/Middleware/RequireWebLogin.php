<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login check for every route in the web group (audit fix SEC-01).
 *
 * Many web routes (Bill-to-Company, resigned-employee reminder, approver, claims, open-month,
 * payment-verification, reports, print) were defined outside the 'auth' group, so they
 * answered without logging in. This middleware closes them all; only the paths below stay public.
 * cron/* stays reachable without a session only with ?key=<CRON_KEY> (from .env).
 */
class RequireWebLogin
{
    /** Paths that must work before login. */
    private const PUBLIC_PATHS = [
        '/', 'login', 'xeasy_login', 'logout', 'up', 'sanctum/csrf-cookie', 'privacy',
    ];

    /** Path prefixes that must work before login (framework / static). */
    private const PUBLIC_PREFIXES = [
        'storage/', '_debugbar/',
        // BOARDING-01: vendor boarding-pass portal; the random link (stored hashed, expiring, revocable) is the credential
        'vendor/boarding-pass/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $path = $path === '' ? '/' : $path;

        if (in_array($path, self::PUBLIC_PATHS, true)) {
            return $next($request);
        }
        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        if (str_starts_with($path, 'cron/')) {
            $key = (string) config('app.cron_key', '');
            $given = (string) $request->query('key', '');
            if ($key !== '' && $given !== '' && hash_equals($key, $given)) {
                return $next($request);
            }
            return response()->json(['success' => false, 'message' => 'Not allowed'], 403);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Login required'], 401);
        }

        return redirect()->guest(url('/login'));
    }
}
