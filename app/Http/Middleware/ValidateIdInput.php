<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LV-S08 / S-03: one guard in front of every route (web, api, mobile, advance).
 *
 * Year, employee and claim ids are used to build table names (y{YearId}_expenseclaims) and
 * are pasted into SQL in older code paths. Any such field that is present must be a plain
 * number (or a comma list of numbers, or 'all'); anything else is refused before a
 * controller runs. Year ids must also be small (a YearId, not a calendar year).
 */
class ValidateIdInput
{
    /** Year id fields: number up to 99. */
    private const YEAR_KEYS = ['YearId', 'yearId', 'year_id', 'yearid', 'YearID', 'FYearId', 'ClaimYearId', 'claim_year_id', 'fy_id'];

    /** Employee / claim id fields: number(s). */
    private const ID_KEYS = [
        'EmployeeID', 'EmployeeId', 'employee_id', 'employeeId', 'empid', 'EmpId', 'empId', 'emp_id', 'EmpID',
        'ExpId', 'expid', 'expId', 'exp_id', 'ExpID', 'CrBy', 'crby', 'ClaimId', 'claim_id', 'claimId',
    ];

    private const ALLOWED_WORDS = ['', 'all', 'All', 'ALL', 'null', 'undefined'];

    public function handle(Request $request, Closure $next): Response
    {
        $bad = [];
        $all = array_merge($request->query->all(), $request->request->all(), $request->json()->all() ?: []);
        foreach ($all as $key => $value) {
            $isYear = in_array($key, self::YEAR_KEYS, true);
            if (! $isYear && ! in_array($key, self::ID_KEYS, true)) {
                continue;
            }
            foreach ((array) $value as $v) {
                if (is_array($v)) {
                    $bad[] = $key;
                    break;
                }
                if (! $this->ok($v, $isYear)) {
                    $bad[] = $key;
                    break;
                }
            }
        }
        if (! $bad) {
            return $next($request);
        }
        \Log::warning('LV-S08 refused non-numeric id input', ['path' => $request->path(), 'fields' => $bad, 'ip' => $request->ip()]);
        $msg = 'Invalid value for '.implode(', ', array_unique($bad)).'.';
        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json(['status' => false, 'success' => false, 'message' => $msg], 422);
        }

        return response($msg, 422);
    }

    private function ok($v, bool $isYear): bool
    {
        if ($v === null || is_int($v) || is_bool($v)) {
            return ! $isYear || (int) $v <= 99;
        }
        if (is_float($v)) {
            return floor($v) == $v && (! $isYear || $v <= 99);
        }
        $s = trim((string) $v);
        if (in_array($s, self::ALLOWED_WORDS, true)) {
            return true;
        }
        if (! preg_match('/^\d{1,12}(,\s*\d{1,12})*$/', $s)) {
            return false;
        }
        if ($isYear) {
            foreach (explode(',', $s) as $n) {
                if ((int) $n > 99) {
                    return false;
                }
            }
        }

        return true;
    }
}
