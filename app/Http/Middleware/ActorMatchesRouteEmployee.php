<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ODO-APP-01: on routes that carry the acting employee in the URL ({employeeId}), a mobile
 * app token only works for that employee. Without this, any logged-in app user could approve
 * or reject back-dated odometer entries in someone else's name by changing the URL.
 * Runs after ApiAuthenticate; web sessions and the internal key are not affected.
 * Honours the same kill switch (API_AUTH_ENFORCE=false logs instead of blocking).
 */
class ActorMatchesRouteEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('api_auth_via') !== 'mobile_token') {
            return $next($request);
        }

        $routeEmp = (string) $request->route('employeeId');
        $tokenEmp = (string) $request->attributes->get('api_auth_empid');
        if ($routeEmp === '' || $routeEmp === $tokenEmp) {
            return $next($request);
        }

        Log::warning('App token used for another employee', [
            'path' => $request->path(), 'token_emp' => $tokenEmp, 'route_emp' => $routeEmp, 'ip' => $request->ip(),
        ]);
        if (! config('app.api_auth_enforce', true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false, 'Code' => '403',
            'message' => 'This login cannot act for another employee.',
            'msg' => 'This login cannot act for another employee.',
        ], 403);
    }
}
