<?php
// app/Helpers/ExpenseHelper.php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

if (!function_exists('getUser')) {
    function getUser($employeeId)
    {
        $result = DB::selectOne('SELECT Fname, Sname, Lname FROM hrm_employee WHERE EmployeeID = ?', [$employeeId]);

        if ($result) {
            return trim($result->Fname . ' ' . $result->Sname . ' ' . $result->Lname);
        }

        return '';
    }
}

if (!function_exists('getCode')) {
    function getCode($employeeId)
    {
        $result = DB::selectOne('SELECT EmpCode FROM hrm_employee WHERE EmployeeID = ?', [$employeeId]);

        return $result ? $result->EmpCode : '';
    }
}

// getStsUser() is defined in EmployeeHelper.php (loaded first via composer autoload)

if (!function_exists('getGrade')) {
    function getGrade($employeeId)
    {
        $result = DB::connection('hrims')->selectOne('
            SELECT gr.grade_name
            FROM hrm_employee_general g
            INNER JOIN core_grades gr ON g.GradeId = gr.id
            WHERE g.EmployeeID = ?
        ', [$employeeId]);

        return $result ? $result->grade_name : '';
    }
}

if (!function_exists('getFinancialMonth')) {
    function getFinancialMonth($month)
    {
        return ($month >= 4) ? $month - 3 : $month + 9;
    }
}

if (!function_exists('formatNumber')) {
    function formatNumber($num)
    {
        return number_format($num);
    }
}

if (!function_exists('getCurrentMonthActivityAdvances')) {
    function getCurrentMonthActivityAdvances($employeeId, $month)
    {
        $conn = session('expense_connection');
        $imprestExclusion = function_exists('buildAdvanceImprestExclusionSql')
            ? buildAdvanceImprestExclusionSql($conn, 'ar', 'ara', 'art')
            : '';

        $sql = "
            SELECT
                ara.id as activity_advance_id,
                ar.activity_name_id,
                ct.ClaimName as ActivityName,
                art.type_code,
                art.type_name,
                COALESCE(ara.is_imprest, ar.is_imprest, 0) as is_imprest,
                ara.purpose,
                ara.payment_remark as remark,
                ar.created_by,
                ar.req_app_submit_id,
                COALESCE(ara.payment_amount, ara.final_amount, ara.amount) AS advance_amount,
                ara.payment_date,
                ar.month
            FROM advance_request_activities ara
            INNER JOIN advance_request_employees are ON are.id = ara.advance_request_employee_id
            INNER JOIN advance_requests ar ON ar.id = are.advance_request_id
            INNER JOIN advance_request_types art ON art.id = ar.advance_type_id
            LEFT JOIN claimtype ct ON ct.ClaimId = ar.activity_name_id
            WHERE ara.payment_status = 'Paid'
            AND art.type_code = 'Act'
            AND art.is_active = 1
            {$imprestExclusion}
            AND ar.deleted_at IS NULL
            AND ara.deleted_at IS NULL
            AND (are.employee_id = ?)
            AND ar.month = ?
        ";

        $results = DB::connection($conn)->select($sql, [$employeeId, $month]);
        if (empty($results) && $conn !== 'expense') {
            try {
                $results = DB::connection('expense')->select($sql, [$employeeId, $month]);
            } catch (\Throwable $e) {}
        }

        $filtered = [];
        foreach ($results as $record) {
            $row = (array) $record;
            $row['is_imprest'] = $row['is_imprest'] ?? 0;
            if (function_exists('isImprestAdvanceRecord')) {
                if (!isImprestAdvanceRecord($row)) {
                    $filtered[] = $row;
                }
            } else {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }
}

if (!function_exists('getActivityNameById')) {
    function getActivityNameById($id)
    {
        if (empty($id)) {
            return 'Act :';
        }

        $name = DB::table('claimtype')
            ->where('ClaimId', $id)
            ->value('ClaimName');

        return $name ?? 'Act :';
    }
}

if (!function_exists('getFinancialAmounts')) {
    function getFinancialAmounts($employeeId, $month, $yearId)
    {

        $tableName = 'y' . $yearId . '_expenseclaims';
        if (session('company_id') == '11') {
            $results = DB::connection(session('expense_connection'))->select("
            SELECT
                ec.activity_type,
                aat.type_name,
                SUM(CASE WHEN ct.cgId = 7 THEN ec.ApprTAmt ELSE 0 END) AS FinalActivityAmount,
                SUM(CASE
        WHEN ct.cgId = 1
             AND ec.ClaimId NOT IN (19, 20)
        THEN ec.ApprTAmt
        ELSE 0
    END) AS FinalExpenseAmount,

                SUM(CASE WHEN ec.ClaimId IN (19, 20) THEN ec.ApprTAmt ELSE 0 END) AS FinalDAAmount
            FROM {$tableName} ec
            LEFT JOIN claimtype ct
                ON ec.ClaimId = ct.ClaimId
            LEFT JOIN adv_activity_types aat
                ON ec.activity_type = aat.id
                AND ec.activity_type != 0
            WHERE ec.ClaimMonth = ?
              AND ec.CrBy = ?
              AND ec.ClaimStatus != 'Deactivate'
              AND ec.ApprTAmt > 0
            GROUP BY ec.activity_type, aat.type_name
            ORDER BY ec.activity_type ASC
        ", [$month, $employeeId]);
        } else {
            $results = DB::connection(session('expense_connection'))->select("
            SELECT
                ec.activity_type,
                aat.type_name,
                SUM(CASE WHEN ct.cgId = 7 THEN ec.FinancedTAmt ELSE 0 END) AS FinalActivityAmount,
                SUM(CASE WHEN ct.cgId = 1 THEN ec.FinancedTAmt ELSE 0 END) AS FinalExpenseAmount,
                SUM(CASE WHEN ec.ClaimId IN (19, 20) THEN ec.FinancedTAmt ELSE 0 END) AS FinalDAAmount
            FROM {$tableName} ec
            LEFT JOIN claimtype ct
                ON ec.ClaimId = ct.ClaimId
            LEFT JOIN adv_activity_types aat
                ON ec.activity_type = aat.id
                AND ec.activity_type != 0
            WHERE ec.ClaimMonth = ?
              AND ec.CrBy = ?
              AND ec.ClaimStatus != 'Deactivate'
              AND ec.FinancedTAmt > 0
            GROUP BY ec.activity_type, aat.type_name
            ORDER BY ec.activity_type ASC
        ", [$month, $employeeId]);
        }

        // dd($results);
        return $results ?: [];
    }
}
