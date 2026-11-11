<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('test-odometer-debug', function () {
    $yearId = session('year_id', 6);
    $expenseConn = session('expense_connection', 'expense');
    $hrimsConn = session('hrims_connection', 'hrims');

    $output = '<h2>Odometer Analysis Debug</h2>';
    $output .= '<p>year_id: ' . $yearId . '</p>';
    $output .= '<p>expense_conn: ' . $expenseConn . '</p>';
    $output .= '<p>hrims_conn: ' . $hrimsConn . '</p>';

    $wheelerTable = "y{$yearId}_24_wheeler_entry";
    $claimTable = "y{$yearId}_expenseclaims";

    // Check expenseclaims ALL columns
    try {
        $claimExists = DB::connection($expenseConn)->select("SHOW TABLES LIKE '{$claimTable}'");
        $output .= '<h3>Claim Table: ' . $claimTable . '</h3>';
        $output .= '<p>Exists: ' . (count($claimExists) ? 'YES' : 'NO') . '</p>';

        if (count($claimExists)) {
            $cols = DB::connection($expenseConn)->select("SHOW COLUMNS FROM {$claimTable}");
            $output .= '<p>ALL Columns:</p><ul>';
            foreach ($cols as $c) $output .= '<li>' . $c->Field . ' (' . $c->Type . ')</li>';
            $output .= '</ul>';
        }
    } catch (\Exception $e) {
        $output .= '<p style="color:red">Claim error: ' . $e->getMessage() . '</p>';
    }

    // Check hrm_employee structure
    try {
        $empCols = DB::connection($hrimsConn)->select("SHOW COLUMNS FROM hrm_employee");
        $output .= '<h3>hrm_employee columns:</h3><ul>';
        foreach ($empCols as $c) {
            $relevant = ['EmployeeID', 'DepartmentId', 'Department', 'VerticalId', 'Vertical', 'FunctionId', 'GradeId', 'EmpCode', 'Fname', 'Sname', 'Lname'];
            if (in_array($c->Field, $relevant)) {
                $output .= '<li>' . $c->Field . ' (' . $c->Type . ')</li>';
            }
        }
        $output .= '</ul>';

        // Check a sample employee
        $sampleEmp = DB::connection($hrimsConn)->table('hrm_employee')->select('EmployeeID', 'DepartmentId', 'VerticalId')->whereNotNull('DepartmentId')->first();
        if ($sampleEmp) {
            $output .= '<p>Sample emp: EmployeeID=' . $sampleEmp->EmployeeID . ', DepartmentId=' . ($sampleEmp->DepartmentId ?? 'NULL') . ', VerticalId=' . ($sampleEmp->VerticalId ?? 'NULL') . '</p>';
        }
    } catch (\Exception $e) {
        $output .= '<p style="color:red">HRMS error: ' . $e->getMessage() . '</p>';
    }

    // Test a count query using WheelId (FIXED)
    try {
        $cnt = DB::connection($expenseConn)
            ->table("{$wheelerTable} as we")
            ->leftJoin("{$claimTable} as ec", 'we.ExpId', '=', 'ec.ExpId')
            ->selectRaw('COUNT(DISTINCT we.WheelId) as cnt, COALESCE(SUM(we.Totalkm), 0) as tot')
            ->first();
        $output .= '<h3>Fixed Test Join (WheelId)</h3>';
        $output .= '<p>cnt: ' . ($cnt->cnt ?? 0) . ', tot: ' . ($cnt->tot ?? 0) . '</p>';
    } catch (\Exception $e) {
        $output .= '<p style="color:red">Join error: ' . $e->getMessage() . '</p>';
    }

    return $output;
});
