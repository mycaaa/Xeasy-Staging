<?php

namespace App\Services\Reports;

use App\Helpers\OrganogramFilterHelper;
use Illuminate\Support\Facades\DB;

class OdometerAnalysisService
{
    private $companyId;
    private $yearId;
    private $expenseConn;
    private $hrimsConn;
    private $hrimsDb;

    public function __construct()
    {
        $this->companyId = session('company_id', 1);
        $this->yearId = session('year_id', 8);
        $this->expenseConn = session('expense_connection', 'expense');
        $this->hrimsConn = session('hrims_connection', 'hrims');
        $this->hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
    }

    /**
     * Build base query joining wheeler_entry -> expenseclaims -> employee -> employee_general
     */
    private function baseQuery()
    {
        $wheelerTable = "y{$this->yearId}_24_wheeler_entry";
        $claimTable = "y{$this->yearId}_expenseclaims";

        return DB::connection($this->expenseConn)
            ->table(DB::raw("{$wheelerTable} we"))
            ->join(DB::raw("{$claimTable} ec"), 'we.ExpId', '=', 'ec.ExpId')
            ->join(DB::raw("{$this->hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
            ->join(DB::raw("{$this->hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
            // Deactivated (rejected / withdrawn) and draft claims are not running: every other
            // claim report excludes them ('Deactivat' is a truncated legacy spelling in the data).
            ->whereNotIn('ec.ClaimStatus', ['Deactivate', 'Deactivat', 'Draft']);
    }

    /**
     * Apply common filters to a query
     */
    private function applyFilters($query, array $filters): void
    {
        OrganogramFilterHelper::applyToQuery($query, $filters, 'g', 'e.EmployeeID', $this->hrimsDb);

        if (!empty($filters['grade_id'])) {
            $ids = is_array($filters['grade_id']) ? $filters['grade_id'] : [$filters['grade_id']];
            $query->whereIn('g.GradeId', $ids);
        }
        if (!empty($filters['employee_id'])) {
            $ids = is_array($filters['employee_id']) ? $filters['employee_id'] : [$filters['employee_id']];
            $query->whereIn('ec.CrBy', $ids);
        }
        if (!empty($filters['vehicle_type'])) {
            if ($filters['vehicle_type'] === '2W') {
                $query->whereIn('ec.WType', [1, 2]);
            } elseif ($filters['vehicle_type'] === '4W') {
                $query->whereIn('ec.WType', [3, 4]);
            }
        }
        if (!empty($filters['months'])) {
            $query->where('ec.ClaimMonth', (int)$filters['months'])
                  ->where('ec.ClaimYearId', $this->yearId);
        }
    }

    /**
     * Get overall odometer summary statistics
     */
    public function getOdometerSummary(array $filters = []): array
    {
        try {
            $query = $this->baseQuery();
            $this->applyFilters($query, $filters);

            \Log::info('Odometer Summary Query Debug', [
                'yearId' => $this->yearId,
                'expenseConn' => $this->expenseConn,
                'hrimsDb' => $this->hrimsDb,
                'table' => "y{$this->yearId}_24_wheeler_entry",
            ]);

            $summary = $query->selectRaw('
                COUNT(DISTINCT we.WheelId) as total_claims,
                COALESCE(SUM(we.Totalkm), 0) as total_km,
                COALESCE(AVG(we.Totalkm), 0) as avg_km,
                COALESCE(MAX(we.Totalkm), 0) as max_km,
                COALESCE(MIN(we.Totalkm), 0) as min_km,
                COALESCE(SUM(we.FilledTAmt), 0) as total_amount
            ')->first();

            // 2W breakdown
            $query2w = $this->baseQuery()->whereIn('ec.WType', [1, 2]);
            $this->applyFilters($query2w, $filters);
            $twoW = $query2w->selectRaw('
                COUNT(DISTINCT we.WheelId) as claims_2w,
                COALESCE(SUM(we.Totalkm), 0) as total_km_2w
            ')->first();

            // 4W breakdown
            $query4w = $this->baseQuery()->whereIn('ec.WType', [3, 4]);
            $this->applyFilters($query4w, $filters);
            $fourW = $query4w->selectRaw('
                COUNT(DISTINCT we.WheelId) as claims_4w,
                COALESCE(SUM(we.Totalkm), 0) as total_km_4w
            ')->first();

            return [
                'total_claims' => (int)($summary->total_claims ?? 0),
                'total_km' => (float)($summary->total_km ?? 0),
                'avg_km' => (float)($summary->avg_km ?? 0),
                'max_km' => (float)($summary->max_km ?? 0),
                'min_km' => (float)($summary->min_km ?? 0),
                'total_amount' => (float)($summary->total_amount ?? 0),
                'claims_2w' => (int)($twoW->claims_2w ?? 0),
                'total_km_2w' => (float)($twoW->total_km_2w ?? 0),
                'claims_4w' => (int)($fourW->claims_4w ?? 0),
                'total_km_4w' => (float)($fourW->total_km_4w ?? 0),
            ];
        } catch (\Exception $e) {
            \Log::error('Odometer Summary Error: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'debug_year_id' => $this->yearId,
                'debug_conn' => $this->expenseConn,
                'debug_hrims' => $this->hrimsDb,
                'total_claims' => 0, 'total_km' => 0, 'avg_km' => 0,
                'max_km' => 0, 'min_km' => 0, 'total_amount' => 0,
                'claims_2w' => 0, 'total_km_2w' => 0,
                'claims_4w' => 0, 'total_km_4w' => 0,
            ];
        }
    }

    /**
     * Get employee-wise analysis
     */
    public function getEmployeeWiseAnalysis(array $filters = []): array
    {
        try {
            $query = $this->baseQuery()
                ->leftJoin(DB::raw("{$this->hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id');
            $this->applyFilters($query, $filters);

            $data = $query->selectRaw("
                ec.CrBy as employee_id,
                e.EmpCode_New as emp_code,
                CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) as employee_name,
                COALESCE(dept.department_name, 'N/A') as department_name,
                COUNT(DISTINCT we.WheelId) as total_claims,
                COALESCE(SUM(we.Totalkm), 0) as total_km,
                COALESCE(AVG(we.Totalkm), 0) as avg_km,
                COALESCE(SUM(CASE WHEN ec.WType IN (1,2) THEN we.Totalkm ELSE 0 END), 0) as km_2w,
                COALESCE(SUM(CASE WHEN ec.WType IN (3,4) THEN we.Totalkm ELSE 0 END), 0) as km_4w,
                COALESCE(SUM(we.FilledTAmt), 0) as total_amount
            ")
            ->groupBy('ec.CrBy', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'dept.department_name')
            ->orderByDesc('total_km')
            ->get();

            return $data->map(function ($row) {
                return [
                    'employee_id' => $row->employee_id,
                    'emp_code' => $row->emp_code,
                    'employee_name' => $row->emp_code . ' - ' . trim($row->employee_name),
                    'department_name' => $row->department_name,
                    'total_claims' => (int)$row->total_claims,
                    'total_km' => (float)$row->total_km,
                    'avg_km' => (float)$row->avg_km,
                    'km_2w' => (float)$row->km_2w,
                    'km_4w' => (float)$row->km_4w,
                    'total_amount' => (float)$row->total_amount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Employee Analysis Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get 2-wheeler wise analysis (WType = 1 or 2)
     */
    public function getTwoWheelerWiseAnalysis(array $filters = []): array
    {
        try {
            $query = $this->baseQuery()
                ->leftJoin(DB::raw("{$this->hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id')
                ->whereIn('ec.WType', [1, 2]);
            $this->applyFilters($query, $filters);

            $data = $query->selectRaw("
                ec.CrBy as employee_id,
                e.EmpCode_New as emp_code,
                CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) as employee_name,
                COALESCE(dept.department_name, 'N/A') as department_name,
                COUNT(DISTINCT we.WheelId) as total_claims,
                COALESCE(SUM(we.Totalkm), 0) as total_km,
                COALESCE(AVG(we.Totalkm), 0) as avg_km,
                COALESCE(AVG(we.Rate_PerKm), 0) as avg_rate_per_km,
                COALESCE(SUM(we.FilledTAmt), 0) as total_amount
            ")
            ->groupBy('ec.CrBy', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'dept.department_name')
            ->orderByDesc('total_km')
            ->get();

            return $data->map(function ($row) {
                return [
                    'employee_id' => $row->employee_id,
                    'emp_code' => $row->emp_code,
                    'employee_name' => $row->emp_code . ' - ' . trim($row->employee_name),
                    'department_name' => $row->department_name,
                    'total_claims' => (int)$row->total_claims,
                    'total_km' => (float)$row->total_km,
                    'avg_km' => (float)$row->avg_km,
                    'avg_rate_per_km' => (float)$row->avg_rate_per_km,
                    'total_amount' => (float)$row->total_amount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('2W Analysis Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get 4-wheeler wise analysis (WType = 3 or 4)
     */
    public function getFourWheelerWiseAnalysis(array $filters = []): array
    {
        try {
            $query = $this->baseQuery()
                ->leftJoin(DB::raw("{$this->hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id')
                ->whereIn('ec.WType', [3, 4]);
            $this->applyFilters($query, $filters);

            $data = $query->selectRaw("
                ec.CrBy as employee_id,
                e.EmpCode_New as emp_code,
                CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) as employee_name,
                COALESCE(dept.department_name, 'N/A') as department_name,
                COUNT(DISTINCT we.WheelId) as total_claims,
                COALESCE(SUM(we.Totalkm), 0) as total_km,
                COALESCE(AVG(we.Totalkm), 0) as avg_km,
                COALESCE(AVG(we.Rate_PerKm), 0) as avg_rate_per_km,
                COALESCE(SUM(we.FilledTAmt), 0) as total_amount
            ")
            ->groupBy('ec.CrBy', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'dept.department_name')
            ->orderByDesc('total_km')
            ->get();

            return $data->map(function ($row) {
                return [
                    'employee_id' => $row->employee_id,
                    'emp_code' => $row->emp_code,
                    'employee_name' => $row->emp_code . ' - ' . trim($row->employee_name),
                    'department_name' => $row->department_name,
                    'total_claims' => (int)$row->total_claims,
                    'total_km' => (float)$row->total_km,
                    'avg_km' => (float)$row->avg_km,
                    'avg_rate_per_km' => (float)$row->avg_rate_per_km,
                    'total_amount' => (float)$row->total_amount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('4W Analysis Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get department-wise analysis (grouped by department)
     */
    public function getDepartmentWiseAnalysis(array $filters = []): array
    {
        try {
            $query = $this->baseQuery()
                ->leftJoin(DB::raw("{$this->hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id');
            $this->applyFilters($query, $filters);

            $data = $query->selectRaw("
                dept.id as dept_id,
                COALESCE(dept.department_name, 'N/A') as department_name,
                COUNT(DISTINCT we.WheelId) as total_claims,
                COALESCE(SUM(we.Totalkm), 0) as total_km,
                COALESCE(AVG(we.Totalkm), 0) as avg_km,
                COALESCE(SUM(CASE WHEN ec.WType IN (1,2) THEN we.Totalkm ELSE 0 END), 0) as km_2w,
                COALESCE(SUM(CASE WHEN ec.WType IN (3,4) THEN we.Totalkm ELSE 0 END), 0) as km_4w,
                COALESCE(SUM(we.FilledTAmt), 0) as total_amount
            ")
            ->groupBy('dept.id', 'dept.department_name')
            ->orderByDesc('total_km')
            ->get();

            return $data->map(function ($row) {
                return [
                    'dept_id' => $row->dept_id,
                    'department_name' => $row->department_name,
                    'total_claims' => (int)$row->total_claims,
                    'total_km' => (float)$row->total_km,
                    'avg_km' => (float)$row->avg_km,
                    'km_2w' => (float)$row->km_2w,
                    'km_4w' => (float)$row->km_4w,
                    'total_amount' => (float)$row->total_amount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Odometer Department Wise Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get vertical-wise analysis (grouped by vertical)
     */
    public function getVerticalWiseAnalysis(array $filters = []): array
    {
        try {
            $query = $this->baseQuery()
                ->leftJoin(DB::raw("{$this->hrimsDb}.core_verticals ver"), 'g.EmpVertical', '=', 'ver.id');
            $this->applyFilters($query, $filters);

            $data = $query->selectRaw("
                ver.id as vertical_id,
                COALESCE(ver.vertical_name, 'N/A') as vertical_name,
                COUNT(DISTINCT we.WheelId) as total_claims,
                COALESCE(SUM(we.Totalkm), 0) as total_km,
                COALESCE(AVG(we.Totalkm), 0) as avg_km,
                COALESCE(SUM(CASE WHEN ec.WType IN (1,2) THEN we.Totalkm ELSE 0 END), 0) as km_2w,
                COALESCE(SUM(CASE WHEN ec.WType IN (3,4) THEN we.Totalkm ELSE 0 END), 0) as km_4w,
                COALESCE(SUM(we.FilledTAmt), 0) as total_amount
            ")
            ->groupBy('ver.id', 'ver.vertical_name')
            ->orderByDesc('total_km')
            ->get();

            return $data->map(function ($row) {
                return [
                    'vertical_id' => $row->vertical_id,
                    'vertical_name' => $row->vertical_name,
                    'total_claims' => (int)$row->total_claims,
                    'total_km' => (float)$row->total_km,
                    'avg_km' => (float)$row->avg_km,
                    'km_2w' => (float)$row->km_2w,
                    'km_4w' => (float)$row->km_4w,
                    'total_amount' => (float)$row->total_amount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Odometer Vertical Wise Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get month-wise grouped analysis for export
     */
    public function getMonthWiseAnalysis(array $filters = []): array
    {
        try {
            $query = $this->baseQuery()
                ->leftJoin(DB::raw("{$this->hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id')
                ->leftJoin(DB::raw("{$this->hrimsDb}.core_verticals ver"), 'g.EmpVertical', '=', 'ver.id');
            $this->applyFilters($query, $filters);

            $data = $query->selectRaw("
                ec.ClaimMonth as month,
                e.EmpCode_New as emp_code,
                CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) as employee_name,
                COALESCE(dept.department_name, 'N/A') as department_name,
                COALESCE(ver.vertical_name, 'N/A') as vertical_name,
                COUNT(DISTINCT we.WheelId) as total_claims,
                COALESCE(SUM(we.Totalkm), 0) as total_km,
                COALESCE(AVG(we.Totalkm), 0) as avg_km,
                COALESCE(SUM(CASE WHEN ec.WType IN (1,2) THEN we.Totalkm ELSE 0 END), 0) as km_2w,
                COALESCE(SUM(CASE WHEN ec.WType IN (3,4) THEN we.Totalkm ELSE 0 END), 0) as km_4w,
                COALESCE(SUM(we.FilledTAmt), 0) as total_amount
            ")
            ->groupBy('ec.ClaimMonth', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'dept.department_name', 'ver.vertical_name')
            ->orderBy('ec.ClaimMonth')
            ->orderBy('e.EmpCode_New')
            ->get();

            $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            return $data->map(function ($row) use ($months) {
                return [
                    'month' => (int)$row->month,
                    'month_name' => $months[(int)$row->month] ?? 'Month ' . $row->month,
                    'emp_code' => $row->emp_code,
                    'employee_name' => trim($row->employee_name),
                    'department_name' => $row->department_name,
                    'vertical_name' => $row->vertical_name,
                    'total_claims' => (int)$row->total_claims,
                    'total_km' => (float)$row->total_km,
                    'avg_km' => (float)$row->avg_km,
                    'km_2w' => (float)$row->km_2w,
                    'km_4w' => (float)$row->km_4w,
                    'total_amount' => (float)$row->total_amount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Odometer Month Wise Error: ' . $e->getMessage());
            return [];
        }
    }
}
