<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login check for routes/api.php and routes/advance.php (audit fix SEC-01).
 *
 * A request is let through when ONE of these holds:
 *  1. A logged-in web session (same-origin AJAX from the web portal).
 *  2. A valid mobile app token: employee id + token matching hrm_employee.xeasy_tokenid
 *     (or xeasy_tokenid_ios) - the same check the mobile API controllers use.
 *     Accepted as request fields (empid / employee_id / EmployeeID + tokenid) or headers
 *     (X-Xeasy-Empid + X-Xeasy-Token, or Authorization: Bearer <token>).
 *  3. A server-to-server key: header X-Api-Key equal to INTERNAL_API_KEY in .env (if set).
 *
 * The kill switch API_AUTH_ENFORCE=false (default true) logs instead of blocking, so the
 * mobile app can be rolled out first if needed.
 */
class ApiAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $via = $this->authenticatedVia($request);
        if ($via !== null) {
            $request->attributes->set('api_auth_via', $via);
            return $next($request);
        }

        Log::warning('API request without valid login', [
            'path' => $request->path(),
            'ip' => $request->ip(),
            'enforced' => (bool) config('app.api_auth_enforce', true),
        ]);

        if (!config('app.api_auth_enforce', true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'Code' => '401',
            'msg' => 'Login required',
            'message' => 'Login required',
        ], 401);
    }

    protected function authenticatedVia(Request $request): ?string
    {
        if (Auth::guard('web')->check()) {
            return 'session';
        }

        $internalKey = (string) config('app.internal_api_key', '');
        $givenKey = (string) $request->header('X-Api-Key', '');
        if ($internalKey !== '' && $givenKey !== '' && hash_equals($internalKey, $givenKey)) {
            return 'internal_key';
        }

        $empId = $request->header('X-Xeasy-Empid')
            ?? $request->input('empid')
            ?? $request->input('employee_id')
            ?? $request->input('EmployeeID');
        $token = $request->header('X-Xeasy-Token')
            ?? $request->bearerToken()
            ?? $request->input('tokenid');

        if (!empty($empId) && !empty($token) && ctype_digit((string) $empId)) {
            // Same normalisation as BaseMobileController::validateToken (legacy behaviour)
            $special = [':', '-', '`', '~', '^', '!', '@', '#', '$', '%', '&', '*', '(', ')', '{', '}', '_', '+'];
            $clean = str_replace($special, '', (string) $token);
            if ($clean === '') {
                return null;
            }
            $ok = DB::connection('hrims')->table('hrm_employee')
                ->where('EmployeeID', (int) $empId)
                ->where(function ($q) use ($clean) {
                    $q->where('xeasy_tokenid', $clean)->orWhere('xeasy_tokenid_ios', $clean);
                })
                ->exists();
            if ($ok) {
                // ODO-APP-01: routes that take the acting employee in the URL check it against this
                $request->attributes->set('api_auth_empid', (int) $empId);
                return 'mobile_token';
            }
        }

        return null;
    }
}
