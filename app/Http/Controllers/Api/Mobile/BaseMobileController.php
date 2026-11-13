<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Base controller for all Mobile API endpoints.
 * Provides shared helper methods for database connections,
 * token validation, and response formatting.
 */
abstract class BaseMobileController extends Controller
{
    public function __construct()
    {
        // Force PDO to return all values as strings (matching legacy mysqli_fetch_assoc behavior)
        // mobile_expense = test/separate DB for mobile APIs
        // hrims = shared with web (same DB)
        $connections = ['mobile_expense', 'hrims'];
        foreach ($connections as $connName) {
            try {
                $pdo = DB::connection($connName)->getPdo();
                if ($pdo) {
                    $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
                }
            } catch (\Exception $e) {
                // Connection not configured or not reachable - skip
            }
        }
    }

    /**
     * Get the expense database connection based on CompanyId.
     * Uses mobile-specific connections for isolated testing.
     */
    protected function getExpenseConnection(int $comId): string
    {
        return match ($comId) {
            1 => 'mobile_expense',
            3 => 'mobile_expense_nursery',
            11 => 'mobile_expense_vvnr',
            default => 'mobile_expense',
        };
    }

    /**
     * Get HRIMS database connection name.
     * HRIMS stays the same (shared with web) - no separate test DB needed.
     */
    protected function getHrimsConnection(): string
    {
        return 'hrims';
    }

    /**
     * Validate token from request against employee record.
     * Returns the employee record if valid, null otherwise.
     */
    protected function validateToken(Request $request): ?object
    {
        $empId = (int) $request->input('empid');
        $tokenId = $request->input('tokenid', '');

        // LEGACY-02: server-to-server relay from this app's own web screens (LegacyApi::http()).
        // The web user is already logged in; the relay key never leaves the server.
        $relayKey = (string) config('app.internal_api_key', '');
        $givenKey = (string) $request->header('X-Xeasy-Internal', '');
        if (! empty($empId) && $relayKey !== '' && $givenKey !== '' && hash_equals($relayKey, $givenKey)) {
            return DB::connection($this->getHrimsConnection())->table('hrm_employee')->where('EmployeeID', $empId)->first();
        }

        // ADV-WEB-01: a logged-in web user calling these endpoints from a web screen, for themselves
        if (! empty($empId) && \Illuminate\Support\Facades\Auth::guard('web')->check()
            && (int) \Illuminate\Support\Facades\Auth::guard('web')->user()->employee_id === $empId) {
            return DB::connection($this->getHrimsConnection())->table('hrm_employee')->where('EmployeeID', $empId)->first();
        }

        if (empty($empId) || empty($tokenId)) {
            return null;
        }

        // Strip special characters from token (matching legacy behavior)
        $specialChars = [':', '-', '`', '~', '^', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '{', '}', '_', '+'];
        $token = str_replace($specialChars, '', $tokenId);

        $hrimsConn = $this->getHrimsConnection();
        $employee = DB::connection($hrimsConn)
            ->table('hrm_employee')
            ->where('EmployeeID', $empId)
            ->where(function ($query) use ($token) {
                $query->where('xeasy_tokenid', $token)
                    ->orWhere('xeasy_tokenid_ios', $token);
            })
            ->first();

        return $employee;
    }

    /**
     * Return a success JSON response (matching legacy Code: 300 format).
     */
    protected function success(array $data = [], string $msg = 'Success'): JsonResponse
    {
        return response()->json(array_merge(['Code' => '300', 'msg' => $msg], $data));
    }

    /**
     * Return an error JSON response (matching legacy Code: 100 format).
     */
    protected function error(string $msg = 'Something went wrong', string $code = '100'): JsonResponse
    {
        return response()->json(['Code' => $code, 'msg' => $msg]);
    }

    /**
     * Get YearId from request with fallback.
     */
    protected function getYearId(Request $request): int
    {
        return (int) $request->input('YearId', $request->input('yearid', 7));
    }

    /**
     * Get CompanyId from request.
     */
    protected function getComId(Request $request): int
    {
        return (int) $request->input('ComId', 1);
    }

    /**
     * Dynamic expense claims table name based on year.
     */
    protected function claimsTable(int $yearId): string
    {
        return "y{$yearId}_expenseclaims";
    }

    /**
     * Dynamic claim uploads table name based on year.
     */
    protected function uploadsTable(int $yearId): string
    {
        return "y{$yearId}_claimuploads";
    }

    /**
     * Dynamic expense remark table name based on year.
     */
    protected function remarkTable(int $yearId): string
    {
        return "y{$yearId}_expenseremark";
    }

    /**
     * Dynamic month expense final table name based on year.
     */
    protected function monthFinalTable(int $yearId): string
    {
        return "y{$yearId}_monthexpensefinal";
    }

    /**
     * Convert all values in an associative array to strings.
     * This mimics mysqli_fetch_assoc behavior where all values are returned as strings.
     * Critical for Flutter app compatibility (uses setString for all SharedPreferences).
     */
    protected function stringifyRow(array $row): array
    {
        return array_map(function ($value) {
            if (is_null($value)) {
                return '';
            }
            return (string) $value;
        }, $row);
    }

    /**
     * Convert a collection of rows to arrays with all string values.
     * Mimics legacy PHP behavior where all DB results are strings.
     */
    protected function stringifyRows(array $rows): array
    {
        return array_map(function ($row) {
            return $this->stringifyRow((array) $row);
        }, $rows);
    }

    /**
     * Resolve a file path to an S3 URL or fallback URL.
     * Mimics legacy processMediaFiles / getOdometerFileUrl behavior.
     */
    protected function resolveFileUrl(?string $filename, int $yearId, $userId, bool $allowPdf = true): string
    {
        if (empty($filename)) {
            return '';
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'pdf' && !$allowPdf) {
            return ''; // Skip PDFs only when explicitly told (e.g., odometer images)
        }

        try {
            $s3Service = app(\App\Services\S3Service::class);
            $locations = [
                "Expense/{$yearId}/{$userId}/{$filename}",
                "Expense/6/{$userId}/{$filename}",
            ];

            foreach ($locations as $path) {
                if ($s3Service->fileExists($path)) {
                    return $s3Service->getFileViewUrl($path);
                }
            }
        } catch (\Exception $e) {
            // Fallback if S3 not available
        }

        // Fallback to vnragro.com
        $sanitized = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($filename));
        return "https://vnragro.com/File_Bkp/xeasyfile/{$yearId}/{$userId}/{$sanitized}";
    }

    /**
     * Check if a bill date falls on a holiday/leave/Sunday and return appropriate color code.
     * Replicates legacy checkHoliday() function.
     *
     * Returns hex color code (without #):
     * - 'ff9999' (red): ClaimId=7 (odometer) and BillDate != CrDate
     * - '8080ff' (blue): BillDate is Sunday or a holiday/leave day
     * - 'ffff99' (yellow): claimAmount != verifiedAmount
     * - 'ffffff' (white): normal
     */
    protected function checkHoliday(int $claimId, ?string $billDate, ?string $crDate, $employeeId, string $hrimsConn, $claimAmount, $verifiedAmount): string
    {
        $billDateFormatted = (\App\Services\ClaimRules\ClaimSubmissionValidator::parseDate($billDate ?? '') ?? date('Y-m-d', strtotime($billDate ?? ''))); // BILLDATE-03 day-first
        $crDateFormatted = date('Y-m-d', strtotime($crDate ?? ''));
        $year = date('Y');

        // Red: Odometer claim with mismatched dates
        if ($claimId == 7 && $billDateFormatted !== $crDateFormatted) {
            return 'ff9999';
        }

        // Blue: Sunday
        if (date('w', strtotime($billDateFormatted)) == 0) {
            return '8080ff';
        }

        // Blue: Holiday/Leave day
        try {
            $holidays = DB::connection($hrimsConn)
                ->table('hrm_employee_attendance')
                ->where('EmployeeID', $employeeId)
                ->whereIn('AttValue', ['HO', 'SL', 'PL', 'EL', 'FL', 'CL', 'ML'])
                ->where('Year', $year)
                ->pluck('AttDate')
                ->map(fn($d) => date('Y-m-d', strtotime($d)))
                ->toArray();

            if (empty($holidays)) {
                $holidays = DB::connection($hrimsConn)
                    ->table("hrm_employee_attendance_{$year}")
                    ->where('EmployeeID', $employeeId)
                    ->whereIn('AttValue', ['HO', 'SL', 'PL', 'EL', 'FL', 'CL', 'ML'])
                    ->where('Year', $year)
                    ->pluck('AttDate')
                    ->map(fn($d) => date('Y-m-d', strtotime($d)))
                    ->toArray();
            }

            if (in_array($billDateFormatted, $holidays)) {
                return '8080ff';
            }
        } catch (\Exception $e) {
            // If attendance table doesn't exist, skip holiday check
        }

        // Yellow: Amount mismatch
        if ((string) $claimAmount !== (string) $verifiedAmount) {
            return 'ffff99';
        }

        return 'ffffff';
    }
}
