<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADV-WEB-01 (13 Nov 2026): the web advance screens call the legacy mobile endpoints (advance_request.php ...)
 * from the browser. Those routes had no session, so a logged-in web user got 401 "Login required".
 * This starts the web session only when the request carries the session cookie: browser calls are recognised,
 * and the mobile app (no cookie) does not create a session row on every call.
 */
class StartSessionIfCookie extends StartSession
{
    public function handle($request, Closure $next): Response
    {
        if (! $request->cookies->has((string) config('session.cookie'))) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
