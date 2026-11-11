<?php

namespace App\Http\Middleware;

use App\Support\RecordAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-03: record-level check. Rules in config/record_access.php name the request fields and
 * route parameters that carry an employee id or a claim id; each one present must belong to the
 * user, someone below them in the reporting chain, or the user must hold a staff permission.
 * Runs after EnforceRouteAccess in the web group.
 */
class EnforceRecordAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user || ! config('record_access.enabled', true)) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        foreach ((array) config('record_access.rules', []) as $rule) {
            if (! preg_match($rule[0], $path)) {
                continue;
            }

            if (! empty($rule['approver']) && ! RecordAccess::isStaff($user) && ! RecordAccess::isApprover($user)) {
                return $this->deny($request, $user, $path, 'approver screen, no direct reports');
            }

            $route = $request->route();
            $employeeIds = [];
            foreach ((array) ($rule['employee'] ?? []) as $field) {
                $employeeIds[] = $request->input($field);
            }
            foreach ((array) ($rule['employee_route'] ?? []) as $param) {
                $employeeIds[] = $route ? $route->parameter($param) : null;
            }
            foreach ($employeeIds as $id) {
                if ($id !== null && $id !== '' && ctype_digit((string) $id) && ! RecordAccess::canViewEmployee($user, (int) $id)) {
                    return $this->deny($request, $user, $path, "employee $id");
                }
            }

            $year = null;
            foreach ((array) ($rule['year'] ?? []) as $field) {
                if (ctype_digit((string) $request->input($field))) {
                    $year = (int) $request->input($field);
                    break;
                }
            }
            $company = null;
            foreach ((array) ($rule['company'] ?? []) as $field) {
                if (ctype_digit((string) $request->input($field))) {
                    $company = (int) $request->input($field);
                    break;
                }
            }
            $claimIds = [];
            foreach ((array) ($rule['claim'] ?? []) as $field) {
                $claimIds[] = $request->input($field);
            }
            foreach ((array) ($rule['claim_route'] ?? []) as $param) {
                $claimIds[] = $route ? $route->parameter($param) : null;
            }
            foreach ($claimIds as $id) {
                if ($id !== null && $id !== '' && ctype_digit((string) $id) && ! RecordAccess::canViewClaim($user, (int) $id, $year, $company)) {
                    return $this->deny($request, $user, $path, "claim $id");
                }
            }

            break;
        }

        return $next($request);
    }

    private function deny(Request $request, $user, string $path, string $what): Response
    {
        Log::warning('SEC-03 record access refused', ['user_id' => $user->id, 'employee_id' => session('employee_id'), 'path' => $path, 'record' => $what]);
        if ($request->expectsJson() || $request->ajax() || ! $request->isMethod('GET')) {
            return response()->json(['success' => false, 'status' => false, 'message' => 'You can only open your own records or your team\'s.'], 403);
        }
        abort(403, 'You can only open your own records or your team\'s.');
    }
}
