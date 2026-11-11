<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * REQ-001: Promotion / Imprest Automation Service
 *
 * Handles:
 * a) New Joiners   → auto-generate imprest request based on grade + department
 * b) Promotions    → detect grade change J1/J2 → J3/J4/M1 and request delta
 */
class ImprestAutomationService
{
    /**
     * Imprest eligibility matrix: grade → eligible amount (₹)
     * Applies to Sales and PD departments only.
     */
    private const ELIGIBLE_AMOUNTS = [
        'J1' => 20000,
        'J2' => 20000,
        'J3' => 40000,
        'J4' => 40000,
        'M1' => 40000,
    ];

    /**
     * Departments eligible for imprest automation.
     */
    private const ELIGIBLE_DEPARTMENTS = ['sales', 'pd', 'plant disease', 'production'];

    /**
     * Run all imprest automation checks. Called by cron command.
     */
    public function processAll(): array
    {
        $conn = session('expense_connection', 'expense');
        \App\Http\Controllers\admin\ImprestAutomationController::ensureTableExists($conn);
        \App\Http\Controllers\admin\ImprestAutomationController::ensureTableExists('mysql');

        return [
            'new_joiners' => $this->processNewJoiners(),
            'promotions'  => $this->processPromotions(),
        ];
    }

    // ────────────────────────────────────────────────────────────────────────
    // a) NEW JOINERS
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Detect employees who joined in the last 7 days and haven't had
     * an imprest request generated yet.
     */
    public function processNewJoiners(): string
    {
        try {
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $appDb   = config('database.connections.mysql.database', 'expense_app');

            $joiners = DB::connection('hrims')->select("
                SELECT
                    e.EmployeeID,
                    e.EmpCode_New  AS EmpCode,
                    CONCAT(TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname))) AS FullName,
                    e.DateOfJoining,
                    g.GradeId,
                    gr.grade_name  AS GradeName,
                    g.DepartmentId,
                    dept.department_name AS DeptName
                FROM {$hrimsDb}.hrm_employee e
                LEFT JOIN {$hrimsDb}.hrm_employee_general g   ON e.EmployeeID = g.EmployeeID
                LEFT JOIN {$hrimsDb}.core_grades gr            ON g.GradeId    = gr.id
                LEFT JOIN {$hrimsDb}.core_departments dept     ON g.DepartmentId = dept.id
                WHERE e.EmpStatus = 'A'
                  AND e.DateOfJoining >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND NOT EXISTS (
                      SELECT 1 FROM `{$appDb}`.imprest_requests
                      WHERE employee_id = e.EmployeeID
                        AND trigger_type = 'new_joiner'
                  )
            ");

            $count = 0;
            foreach ($joiners as $emp) {
                if (!$this->isEligibleDepartment($emp->DeptName ?? '')) {
                    continue;
                }
                $amount = $this->getEligibleAmount($emp->GradeName ?? '');
                if ($amount <= 0) {
                    continue;
                }
                $this->createImprestRequest($emp, 'new_joiner', $amount, 0);
                $count++;
            }

            return "{$count} new joiner imprest request(s) generated";
        } catch (\Exception $e) {
            Log::error('ImprestAutomationService@processNewJoiners: ' . $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // b) PROMOTIONS
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Detect employees promoted from J1/J2 → J3/J4/M1 in the last 7 days
     * where an additional ₹20,000 imprest should be released.
     */
    public function processPromotions(): string
    {
        try {
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $appDb   = config('database.connections.mysql.database', 'expense_app');

            // Check hrm_grade_history or hrm_employee_promotion table
            $promotions = DB::connection('hrims')->select("
                SELECT
                    e.EmployeeID,
                    e.EmpCode_New   AS EmpCode,
                    CONCAT(TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname))) AS FullName,
                    ph.promotion_date,
                    ph.previous_grade,
                    ph.new_grade,
                    gr.grade_name   AS CurrentGradeName,
                    dept.department_name AS DeptName
                FROM {$hrimsDb}.hrm_employee e
                LEFT JOIN {$hrimsDb}.hrm_employee_general g   ON e.EmployeeID = g.EmployeeID
                LEFT JOIN {$hrimsDb}.core_grades gr            ON g.GradeId    = gr.id
                LEFT JOIN {$hrimsDb}.core_departments dept     ON g.DepartmentId = dept.id
                LEFT JOIN {$hrimsDb}.hrm_promotion_history ph  ON e.EmployeeID = ph.EmployeeID
                WHERE e.EmpStatus = 'A'
                  AND ph.promotion_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND ph.previous_grade IN ('J1','J2')
                  AND ph.new_grade IN ('J3','J4','M1')
                  AND NOT EXISTS (
                      SELECT 1 FROM `{$appDb}`.imprest_requests
                      WHERE employee_id = e.EmployeeID
                        AND trigger_type = 'promotion'
                        AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  )
            ");

            $count = 0;
            foreach ($promotions as $emp) {
                if (!$this->isEligibleDepartment($emp->DeptName ?? '')) {
                    continue;
                }
                $prevAmount    = $this->getEligibleAmount($emp->previous_grade);
                $newAmount     = $this->getEligibleAmount($emp->new_grade);
                $deltaAmount   = $newAmount - $prevAmount;

                if ($deltaAmount <= 0) {
                    continue; // no additional amount needed
                }

                $this->createImprestRequest($emp, 'promotion', $newAmount, $prevAmount, $emp->previous_grade);
                $count++;
            }

            return "{$count} promotion imprest request(s) generated";
        } catch (\Exception $e) {
            Log::error('ImprestAutomationService@processPromotions: ' . $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    /** ADV-POL-01: fixed travel allowance only for the departments in the written policy (Sales, Trialing & PD). */
    private function isEligibleDepartment(string $dept): bool
    {
        $d = strtolower(trim($dept));
        foreach ((array) config('advance_policy.fixed_travel.departments', ['Sales', 'Trialing & PD']) as $ok) {
            if ($d === strtolower($ok)) {
                return true;
            }
        }

        return false;
    }

    private function getEligibleAmount(string $grade): int
    {
        $grade = strtoupper(trim($grade));
        $amounts = (array) config('advance_policy.fixed_travel.amounts', self::ELIGIBLE_AMOUNTS);

        return (int) ($amounts[$grade] ?? 0);
    }

    private function createImprestRequest(
        object $emp,
        string $triggerType,
        int    $eligibleAmount,
        int    $previousAmount,
        string $previousGrade = ''
    ): void {
        $requestAmount = $eligibleAmount - $previousAmount;

        DB::connection('mysql')->table('imprest_requests')->insert([
            'employee_id'      => $emp->EmployeeID,
            'emp_code'         => $emp->EmpCode ?? '',
            'emp_name'         => $emp->FullName ?? '',
            'department'       => $emp->DeptName ?? '',
            'grade'            => $emp->CurrentGradeName ?? $emp->new_grade ?? '',
            'trigger_type'     => $triggerType,
            'previous_grade'   => $previousGrade ?: null,
            'eligible_amount'  => $eligibleAmount,
            'previous_amount'  => $previousAmount,
            'request_amount'   => $requestAmount,
            'status'           => 'pending',
            'remarks'          => $triggerType === 'promotion'
                ? "Auto-generated: Promotion from {$previousGrade}. Release additional Rs." . number_format($requestAmount) . "."
                : "Auto-generated: New joiner imprest for grade " . ($emp->GradeName ?? '') . ".",
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        Log::info("ImprestRequest created [{$triggerType}]", [
            'employee' => $emp->FullName,
            'amount'   => $requestAmount,
        ]);
    }
}
