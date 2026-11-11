<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Models\FinancialYear;
class DatabaseSwitchController extends Controller
{
    /**
     * LV-S07 (audit, 6 Nov 2026): a user may switch only to a company they are entitled to:
     * Super Admin -> any; otherwise users.company_access_id (comma list) plus the user's own
     * employee company (HRIMS hrm_employee.CompanyId). Before, any logged-in user could switch
     * to any company by posting its id.
     */
    public static function allowedCompanies($user = null): array
    {
        $user = $user ?: auth()->user();
        if (! $user) {
            return [];
        }
        try {
            if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
                return [1, 3, 11];
            }
        } catch (\Throwable $e) {
        }
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($user->company_access_id ?? '')))));
        $emp = (int) (session('employee_id') ?: ($user->employee_id ?? 0));
        if ($emp > 0) {
            try {
                $own = (int) DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $emp)->value('CompanyId');
                if ($own > 0) {
                    $ids[] = $own;
                }
            } catch (\Throwable $e) {
            }
        }
        if (! $ids && session('company_id')) {
            $ids[] = (int) session('company_id');
        }

        return array_values(array_unique($ids));
    }

    private function denyCompany($companyId)
    {
        if (in_array((int) $companyId, self::allowedCompanies(), true)) {
            return null;
        }
        \Illuminate\Support\Facades\Log::warning('Company switch refused', ['user' => auth()->id(), 'company' => $companyId]);

        return response()->json(['success' => false, 'message' => 'You are not allowed to work in this company'], 403);
    }

    public function getCompanies()
    {
        $companies = array_intersect_key((array) config('company_databases.companies'), array_flip(self::allowedCompanies()));
        $companyList = array_map(
            function ($id, $company) {
                return ['id' => $id, 'name' => $company['name']];
            },
            array_keys($companies),
            $companies
        );
        return response()->json($companyList);
    }

    public function getFinancialYears($company)
    {
        if (!in_array($company, [1, 3, 11])) {
            return response()->json(['error' => 'Invalid company'], 400);
        }
        if ($deny = $this->denyCompany($company)) {
            return $deny;
        }
        try {
            // Use direct DB connection to avoid model connection issues
            if ($company == 11) {
                $connectionName = 'expense_vvnr';
            } elseif ($company == 3) {
                $connectionName = 'expense_nursery';
            } else {
                $connectionName = 'expense';
            }

            $years = DB::connection($connectionName)
                ->table('financialyear')
                ->orderBy('YearId', 'desc')
                ->get(['YearId', 'Year']);

            return response()->json([
                'success' => true,
                'years' => $years
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function switchDatabase(Request $request)
    {
        $request->validate(['company_id' => 'required|integer', 'year_id' => 'required|integer']);
        $companyId = (int) $request->input('company_id');
        $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
        if ($deny = $this->denyCompany($companyId)) {
            return $deny;
        }
        $company_name = config("company_databases.companies.$companyId.name", 'Unknown Company');
        try {
            \App\Helpers\DatabaseSwitcher::setCompanyConnections($companyId);
            Session::put('company_name', $company_name);
            Session::put('year_id', $yearId);
            Session::put('year_value', $year_value);
            return response()->json(['success' => true, 'message' => 'Database switched successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function switchCompanyYear(Request $request)
    {
        try {
            $request->validate([
                'company_id' => 'required|in:1,3,11',
                'year_id' => 'required|integer'
            ]);

            $companyId = (int) $request->company_id;
            $yearId = ($request->filled('year_id') ? (int) $request->year_id : null);
            if ($deny = $this->denyCompany($companyId)) {
                return $deny;
            }

            // Use direct DB connection to verify year exists
            if ($companyId == 11) {
                $connectionName = 'expense_vvnr';
            } elseif ($companyId == 3) {
                $connectionName = 'expense_nursery';
            } else {
                $connectionName = 'expense';
            }

            $year = DB::connection($connectionName)
                ->table('financialyear')
                ->where('YearId', $yearId)
                ->first(['YearId', 'Year']);

            if (!$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid financial year for selected company'
                ], 400);
            }

            // Update session with all necessary data
            $companyName = \App\Helpers\DatabaseSwitcher::getCompanyName($companyId);
            \App\Helpers\DatabaseSwitcher::setCompanyConnections($companyId);
            session([
                'company' => $companyName,
                'company_name' => $companyName,
                'year_id' => $yearId,
                'year_value' => $year->Year
            ]);

            // Set company connections AFTER session update
            \App\Helpers\DatabaseSwitcher::setCompanyConnections($companyId);

            // Force save session
            session()->save();

            return response()->json([
                'success' => true,
                'message' => 'Company and financial year switched successfully',
                'company' => $companyName,
                'year' => $year->Year,
                'reload' => true // Signal frontend to reload page
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
