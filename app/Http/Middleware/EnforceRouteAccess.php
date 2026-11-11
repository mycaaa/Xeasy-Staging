<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-02: permission check by path for admin screens that had only the login check.
 * Rules live in config/route_access.php. Runs after RequireWebLogin (web group).
 */
class EnforceRouteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user || ! config('route_access.enabled', true)) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        foreach ((array) config('route_access.rules', []) as $rule) {
            if (! preg_match($rule[0], $path)) {
                continue;
            }
            foreach ((array) $rule[1] as $perm) {
                if ($user->can($perm)) {
                    return $next($request);
                }
            }
            if (! empty($rule['redirect']) && $request->isMethod('GET') && ! $request->expectsJson() && ! $request->ajax()) {
                return redirect('/'.ltrim($rule['redirect'], '/'));
            }
            Log::warning('SEC-02 access refused', ['user_id' => $user->id, 'path' => $path, 'method' => $request->method(), 'needs' => $rule[1]]);
            if ($request->expectsJson() || $request->ajax() || ! $request->isMethod('GET')) {
                return response()->json(['success' => false, 'status' => false, 'message' => 'You do not have permission for this.'], 403);
            }
            abort(403, 'You do not have permission to open this page.');
        }

        return $next($request);
    }
}
