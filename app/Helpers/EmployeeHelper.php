<?php

// app/Helpers/EmployeeHelper.php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

if (!function_exists('getUser')) {
    function getUser($employeeId)
    {
        try {
            $result = DB::connection('hrims')->selectOne('
                SELECT Fname, Sname, Lname
                FROM hrm_employee
                WHERE EmployeeID = ?
            ', [$employeeId]);

            if ($result) {
                return trim($result->Fname . ' ' . $result->Sname . ' ' . $result->Lname);
            }
        } catch (\Exception $e) {
            Log::error('getUser error: ' . $e->getMessage());
        }

        return '';
    }
}

// if (! function_exists('getUserByEmail')) {
//     function getUserByEmail($employeeId)
//     {
//         try {
//             $result = DB::connection('hrims')->selectOne('
//                 SELECT Fname, Sname, Lname
//                 FROM hrm_employee as he
//                 join hrm_employee_genral as heg on heg.EmployeeID = he.EmployeeID
//                 WHERE EmployeeID = ?
//             ', [$employeeId]);

//             if ($result) {
//                 return trim($result->Fname.' '.$result->Sname.' '.$result->Lname);
//             }
//         } catch (\Exception $e) {
//             Log::error('getUser error: '.$e->getMessage());
//         }

//         return '';
//     }
// }

if (!function_exists('getUserAttStatus')) {
    function getUserAttStatus($employeeId, $date)
    {
        try {
            $result = DB::connection('hrims')->selectOne('
                SELECT AttValue
                FROM hrm_employee_attendance
                WHERE EmployeeID = ? AND AttDate = ?
            ', [$employeeId, $date]);

            if ($result) {
                return $result->AttValue;
            }
        } catch (\Exception $e) {
            Log::error('getUser error: ' . $e->getMessage());
        }

        return '';
    }
}

if (!function_exists('getUserExpEligibility')) {
    function getUserExpEligibility($employeeId)
    {
        try {
            $result = DB::connection('hrims')->selectOne('
                SELECT *
                FROM hrm_employee_eligibility
                WHERE EmployeeID = ?
            ', [$employeeId]);

            if ($result) {
                return $result;
            }
        } catch (\Exception $e) {
            Log::error('getUser error: ' . $e->getMessage());
        }

        return '';
    }
}

if (!function_exists('getMultipleEmployeeHotelStays')) {
    /**
     * Get hotel stays where multiple employees stayed
     *
     * @param  int|null  $expId  Optional ExpId to filter specific expense
     * @return array|object|null
     */
    function getMultipleEmployeeHotelStays($expId = null)
    {
        try {
            $query = DB::connection('hrims')->table('y7_g1_expensefilldata')
                ->select('did', 'ExpId', 'DocName', 'HotelName', 'Arrival', 'Departure', 'NoOfPAX', 'PaxDetails')
                ->where('NoOfPAX', '>', 1);

            if ($expId) {
                $query->where('ExpId', $expId);
                $result = $query->first();
            } else {
                $result = $query->get();
            }

            // Parse PaxDetails if needed (assuming comma-separated or JSON)
            if ($result) {
                if ($expId && $result) {
                    $result->PaxDetailsArray = parsePaxDetails($result->PaxDetails);

                    return $result;
                } else {
                    foreach ($result as $item) {
                        $item->PaxDetailsArray = parsePaxDetails($item->PaxDetails);
                    }

                    return $result;
                }
            }

            return $expId ? null : collect();

        } catch (\Exception $e) {
            Log::error('getMultipleEmployeeHotelStays error: ' . $e->getMessage());

            return $expId ? null : collect();
        }
    }
}

if (!function_exists('parsePaxDetails')) {
    /**
     * Parse PaxDetails string to array
     * Supports comma-separated values or JSON format
     *
     * @param  string|null  $paxDetails
     * @return array
     */
    function parsePaxDetails($paxDetails)
    {
        if (empty($paxDetails)) {
            return [];
        }

        // Try to decode as JSON first
        $decoded = json_decode($paxDetails, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // If not JSON, try comma-separated values
        if (strpos($paxDetails, ',') !== false) {
            return array_map('trim', explode(',', $paxDetails));
        }

        // Return as single value array
        return [$paxDetails];
    }
}

if (!function_exists('getCode')) {
    function getCode($employeeId)
    {
        try {
            $result = DB::connection('hrims')->selectOne('
                SELECT EmpCode
                FROM hrm_employee
                WHERE EmployeeID = ?
            ', [$employeeId]);

            return $result ? $result->EmpCode : '';
        } catch (\Exception $e) {
            Log::error('getCode error: ' . $e->getMessage());

            return '';
        }
    }
}

if (!function_exists('getAccount')) {
    function getAccount($employeeId)
    {
        try {
            $result = DB::connection('hrims')->selectOne('
                SELECT AccountNo
                FROM hrm_employee_general
                WHERE EmployeeID = ?
            ', [$employeeId]);

            return $result ? $result->AccountNo : '';
        } catch (\Exception $e) {
            Log::error('getCode error: ' . $e->getMessage());

            return '';
        }
    }
}

if (!function_exists('getStsUser')) {
    function getStsUser($employeeId, $employee = null)
    {
        try {
            if (!$employee) {
                $employee = DB::connection('hrims')->selectOne('
                    SELECT EmpStatus, DateOfResignation, DateOfSepration
                    FROM hrm_employee
                    WHERE EmployeeID = ?
                ', [$employeeId]);
            }

            if (!$employee) {
                return '';
            }

            // Check if DateOfSepration is a valid date (not zero, not old)
            $dateOfSepration = $employee->DateOfSepration ?? null;
            if (
                !empty($dateOfSepration)
                && \App\Support\ZeroDate::has($dateOfSepration)
                && $dateOfSepration != '1970-01-01'
                && $dateOfSepration != '1971-01-01'
                && strtotime($dateOfSepration) > strtotime('2000-01-01')
            ) {
                $today = strtotime(date('Y-m-d'));
                $separationTimestamp = strtotime($dateOfSepration);

                // If past -> Deactive
                if ($separationTimestamp < $today) {
                    return 'D';
                } else {
                    // Today or future -> Resigned (confirmed but not yet left)
                    return 'R';
                }
            }

            // Check DateOfResignation if present on hrm_employee
            $dateOfResignation = $employee->DateOfResignation ?? null;
            if (
                !empty($dateOfResignation)
                && \App\Support\ZeroDate::has($dateOfResignation)
                && $dateOfResignation != '1970-01-01'
                && $dateOfResignation != '1971-01-01'
                && strtotime($dateOfResignation) > strtotime('2000-01-01')
            ) {
                if (($employee->EmpStatus ?? '') === 'D' || ($employee->EmpStatus ?? '') === 'De') {
                    return 'D';
                }
                return 'R';
            }

            if (($employee->EmpStatus ?? '') === 'D' || ($employee->EmpStatus ?? '') === 'De') {
                return 'D';
            }

            // Optional check in separation table for pending/approved resignation in progress
            try {
                $separation = DB::connection('hrims')->selectOne("
                    SELECT
                        Emp_RelievingDate,
                        Rep_Approved,
                        Hod_Approved,
                        HR_Approved,
                        Emp_Resignation_Status
                    FROM hrm_employee_separation
                    WHERE EmployeeID = ?
                      AND Rep_Approved != 'C'
                      AND Hod_Approved != 'C'
                      AND HR_Approved != 'C'
                    LIMIT 1
                ", [$employeeId]);

                if ($separation) {
                    // Determine if resignation is fully approved.
                    $isApproved = (
                        $separation->Rep_Approved === 'A' &&
                        $separation->Hod_Approved === 'A' &&
                        $separation->HR_Approved === 'A'
                    );

                    // Fully approved: check relieving date to decide D or R
                    if ($isApproved) {
                        $relievingDate = $separation->Emp_RelievingDate;
                        if (!empty($relievingDate) && \App\Support\ZeroDate::has($relievingDate)) {
                            $relievingTimestamp = strtotime($relievingDate);
                            $today = strtotime(date('Y-m-d'));
                            if ($relievingTimestamp !== false && $relievingTimestamp < $today) {
                                return 'D';
                            }
                        }
                    }

                    // Active separation record exists (approved or pending) → Resigned
                    return 'R';
                }
            } catch (\Throwable $e) {
                // Ignore separation table query issues gracefully
            }

            return ($employee->EmpStatus ?? '') ?: 'A';
        } catch (\Exception $e) {
            Log::error('getStsUser error: ' . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('getEmployeeStatus')) {
    function getEmployeeStatus($empStatus = null, $employeeId = null)
    {
        if (!empty($employeeId)) {
            return getStsUser($employeeId);
        }
        if ($empStatus !== null && $empStatus !== '') {
            return $empStatus;
        }

        return 'A';
    }
}

if (!function_exists('getEmployeeStatusLabel')) {
    function getEmployeeStatusLabel($empStatus = null, $employeeId = null)
    {
        $sts = getEmployeeStatus($empStatus, $employeeId);
        if ($sts === 'A') {
            return 'Active';
        } elseif ($sts === 'R') {
            return 'Resigned';
        }

        return 'Inactive';
    }
}


if (!function_exists('getGrade')) {
    function getGrade($employeeId)
    {
        try {
            $result = DB::connection('hrims')->selectOne('
                SELECT gr.grade_name
                FROM hrm_employee_general g
                INNER JOIN core_grades gr ON g.GradeId = gr.id
                WHERE g.EmployeeID = ?
            ', [$employeeId]);

            return $result ? $result->grade_name : '';
        } catch (\Exception $e) {
            Log::error('getGrade error: ' . $e->getMessage());

            return '';
        }
    }
}

if (!function_exists('getGradeId')) {
    function getGradeId($employeeId)
    {

        try {
            $result = DB::connection('hrims')->selectOne('
                SELECT GradeId
                FROM hrm_employee_general
                WHERE EmployeeID = ?
            ', [$employeeId]);

            //  dd($employeeId);
            return $result ? $result->GradeId : '';
        } catch (\Exception $e) {
            Log::error('getGrade error: ' . $e->getMessage());

            return '';
        }
    }
}

if (!function_exists('getEmployeeFixedimpresst')) {
    function getEmployeeFixedimpresst($employeeId)
    {
        try {
            $result = DB::connection(session('expense_connection'))->selectOne('
    SELECT * FROM employee_ledger_balances
    WHERE employee_id = ?
    ORDER BY id DESC
    LIMIT 1
', [$employeeId]);

            if (!$result) {
                return [];
            }

            $r = (array) $result;

            // echo "<pre>";print_r($r);echo "</pre>";
            // Map DB fields to legacy keys expected by Blade/old PHP
            return [
                'balance' => $r['balance'] ?? 0,
                'id' => $r['id'] ?? 1,

                'advance_amt_1' => $r['advance_amt_1'] ?? ($r['advance_amount_1'] ?? 0),
                'advance_purpose_1' => $r['advance_purpose_1'] ?? '',
                'advance_month_1' => $r['advance_month_1'] ?? '',
                'advance_activity_id_1' => $r['advance_activity_id_1'] ?? 0,
                'advance_date_1' => $r['advance_date_1'] ?? '',

                'advance_amt_2' => $r['advance_amt_2'] ?? ($r['advance_amount_2'] ?? 0),
                'advance_purpose_2' => $r['advance_purpose_2'] ?? '',
                'advance_month_2' => $r['advance_month_2'] ?? '',
                'advance_activity_id_2' => $r['advance_activity_id_2'] ?? 0,
                'advance_date_2' => $r['advance_date_2'] ?? '',

                'advance_amt_3' => $r['advance_amt_3'] ?? ($r['advance_amount_3'] ?? 0),
                'advance_purpose_3' => $r['advance_purpose_3'] ?? '',
                'advance_month_3' => $r['advance_month_3'] ?? '',
                'advance_activity_id_3' => $r['advance_activity_id_3'] ?? 0,
                'advance_date_3' => $r['advance_date_3'] ?? '',

                'advance_amt_4' => $r['advance_amt_4'] ?? ($r['advance_amount_4'] ?? 0),
                'advance_purpose_4' => $r['advance_purpose_4'] ?? '',
                'advance_month_4' => $r['advance_month_4'] ?? '',
                'advance_activity_id_4' => $r['advance_activity_id_4'] ?? 0,
                'advance_date_4' => $r['advance_date_4'] ?? '',

                'fixed_imprest_amt_1' => $r['fixed_imprest_amt_1'] ?? 0,
                'fixed_imprest_date_1' => $r['fixed_imprest_date_1'] ?? '',
                'fixed_imprest_amt_2' => $r['fixed_imprest_amt_2'] ?? 0,
                'fixed_imprest_date_2' => $r['fixed_imprest_date_2'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('getEmployeeFixedimpresst error: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('getEmployeeLedgerBalance')) {
    function getEmployeeLedgerBalance($employeeId)
    {
        try {
            $result = DB::connection(session('expense_connection'))->selectOne('
    SELECT * FROM employee_ledger_balances
    WHERE employee_id = ?
    ORDER BY (remaining_balances_json IS NULL) DESC, id DESC
    LIMIT 1
', [$employeeId]);

            if (!$result) {
                return [];
            }

            $r = (array) $result;

            $hasRemainingJson = !empty($r['remaining_balances_json']);

            // Map DB fields to legacy keys expected by Blade/old PHP
            // Note: If remaining_balances_json is present, opening advances have already been
            // processed/settled in a previous month and carried forward via carried_balance_json.
            return [
                'balance' => $hasRemainingJson ? 0 : ($r['balance'] ?? 0),
                'id' => $r['id'] ?? 1,

                'advance_amt_1' => $hasRemainingJson ? 0 : ($r['advance_amt_1'] ?? ($r['advance_amount_1'] ?? 0)),
                'advance_purpose_1' => $hasRemainingJson ? '' : ($r['advance_purpose_1'] ?? ''),
                'advance_month_1' => $hasRemainingJson ? '' : ($r['advance_month_1'] ?? ''),
                'advance_activity_id_1' => $hasRemainingJson ? 0 : ($r['advance_activity_id_1'] ?? 0),
                'advance_date_1' => $hasRemainingJson ? '' : ($r['advance_date_1'] ?? ''),

                'advance_amt_2' => $hasRemainingJson ? 0 : ($r['advance_amt_2'] ?? ($r['advance_amount_2'] ?? 0)),
                'advance_purpose_2' => $hasRemainingJson ? '' : ($r['advance_purpose_2'] ?? ''),
                'advance_month_2' => $hasRemainingJson ? '' : ($r['advance_month_2'] ?? ''),
                'advance_activity_id_2' => $hasRemainingJson ? 0 : ($r['advance_activity_id_2'] ?? 0),
                'advance_date_2' => $hasRemainingJson ? '' : ($r['advance_date_2'] ?? ''),

                'advance_amt_3' => $hasRemainingJson ? 0 : ($r['advance_amt_3'] ?? ($r['advance_amount_3'] ?? 0)),
                'advance_purpose_3' => $hasRemainingJson ? '' : ($r['advance_purpose_3'] ?? ''),
                'advance_month_3' => $hasRemainingJson ? '' : ($r['advance_month_3'] ?? ''),
                'advance_activity_id_3' => $hasRemainingJson ? 0 : ($r['advance_activity_id_3'] ?? 0),
                'advance_date_3' => $hasRemainingJson ? '' : ($r['advance_date_3'] ?? ''),

                'advance_amt_4' => $hasRemainingJson ? 0 : ($r['advance_amt_4'] ?? ($r['advance_amount_4'] ?? 0)),
                'advance_purpose_4' => $hasRemainingJson ? '' : ($r['advance_purpose_4'] ?? ''),
                'advance_month_4' => $hasRemainingJson ? '' : ($r['advance_month_4'] ?? ''),
                'advance_activity_id_4' => $hasRemainingJson ? 0 : ($r['advance_activity_id_4'] ?? 0),
                'advance_date_4' => $hasRemainingJson ? '' : ($r['advance_date_4'] ?? ''),

                // Fixed imprest is permanent and always active
                'fixed_imprest_amt_1' => $r['fixed_imprest_amt_1'] ?? 0,
                'fixed_imprest_date_1' => $r['fixed_imprest_date_1'] ?? '',
                'fixed_imprest_amt_2' => $r['fixed_imprest_amt_2'] ?? 0,
                'fixed_imprest_date_2' => $r['fixed_imprest_date_2'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('getEmployeeLedgerBalance error: ' . $e->getMessage());

            return [];
        }
    }
}

// if (!function_exists('getEmployeeLedgerBalance')) {
//     function getEmployeeLedgerBalance($employeeId)
//     {
//         try {
//             $result = DB::connection(session('expense_connection'))->selectOne("
//                 SELECT * FROM employee_ledger_balances
//                 WHERE employee_id = ?
//                 ORDER BY id DESC
//                 LIMIT 1
//             ", [$employeeId]);

//             if (!$result) return [];

//             $r = (array) $result;

//             $ledgerRows = [];
//             $totalBalance = 0;

//             // 🔥 Case 1: Use remaining_balances_json if available
//             if (
//                 isset($r['closing_balance']) &&
//                 $r['remaining_balances_json'] !== null &&
//                 $r['remaining_balances_json'] !== ''
//             ) {

//                 $json = json_decode($r['remaining_balances_json'], true);

//                 if (is_array($json)) {
//                     foreach ($json as $category => $items) {
//                         foreach ($items as $item) {

//                             $advanceId = $item['advance_id'] ?? null;

//                             $row = [
//                                 'type' => $category,
//                                 'advance_id' => $advanceId,
//                                 'original_amount' => $item['original_amount'] ?? 0,
//                                 'remaining_amount' => $item['remaining_amount'] ?? 0,
//                             ];

//                             // 🔎 For ledger/expense/carried → fetch activity purpose + month + date
//                             if (!empty($advanceId) && $category !== 'paid') {
//                                 $activity = DB::connection(session('expense_connection'))->selectOne("
//                                     SELECT
//                                         an.ActivityName as purpose,
//                                         ar.month,
//                                         ar.created_at
//                                     FROM advance_request_activities ara
//                                     JOIN advance_request_employees areq
//                                         ON areq.id = ara.advance_request_employee_id
//                                     JOIN advance_requests ar
//                                         ON ar.id = areq.advance_request_id
//                                     LEFT JOIN activity_name an
//                                         ON an.ActivityNameId = ar.activity_name_id
//                                     WHERE ara.id = ?
//                                 ", [$advanceId]);

//                                 if ($activity) {
//                                     $row['purpose'] = $activity->purpose ?? 'Activity Advance';
//                                     $row['month'] = $activity->month ?? null;
//                                     $row['date'] = $activity->created_at ?? null;
//                                 }
//                             }

//                             // 🧾 ONLY for paid rows → fetch ClaimName
//                             if ($category === 'paid' && !empty($advanceId)) {
//                                 $claim = DB::connection(session('expense_connection'))->selectOne("
//                                     SELECT ClaimName
//                                     FROM claimtype
//                                     WHERE ClaimId = ?
//                                     LIMIT 1
//                                 ", [$advanceId]);

//                                 if ($claim) {
//                                     $row['purpose'] = $claim->ClaimName;
//                                 }
//                             }

//                             $totalBalance += $row['remaining_amount'];
//                             $ledgerRows[] = $row;
//                         }
//                     }
//                 }
//             } else {
//                 // 🔁 Case 2: Fallback calculation
//                 $balance = $r['balance'] ?? 0;
//                 $fixed1 = $r['fixed_imprest_amt_1'] ?? 0;
//                 $fixed2 = $r['fixed_imprest_amt_2'] ?? 0;
//                  $advPurpose = $r['advance_purpose_1'] ?? $r['advance_purpose_2']??'Activity';

//                 $remaining = $balance - $fixed1 - $fixed2;

//                 $ledgerRows[] = [
//                     'type' => 'opening_balance',
//                     'purpose' => $advPurpose,
//                     'remaining_amount' => $remaining,
//                     'note' => 'Derived: balance - fixed_imprest_amt_1 - fixed_imprest_amt_2'
//                 ];

//                 $totalBalance = $remaining;
//             }

//             return [
//                 'id' => $r['id'] ?? 1,
//                 'ledger' => $ledgerRows,
//                 'balance' => $totalBalance,

//                 // Fixed imprest unchanged
//                 'fixed_imprest_amt_1' => $r['fixed_imprest_amt_1'] ?? 0,
//                 'fixed_imprest_date_1' => $r['fixed_imprest_date_1'] ?? '',
//                 'fixed_imprest_amt_2' => $r['fixed_imprest_amt_2'] ?? 0,
//                 'fixed_imprest_date_2' => $r['fixed_imprest_date_2'] ?? '',
//             ];

//         } catch (\Exception $e) {
//             Log::error('getEmployeeLedgerBalance error: ' . $e->getMessage());
//             return [];
//         }
//     }
// }

if (!function_exists('getFinancialMonth')) {
    function getFinancialMonth($month)
    {
        return ($month >= 4) ? $month - 3 : $month + 9;
    }
}

if (!function_exists('isImprestAdvanceRecord')) {
    /**
     * Determine whether an advance record is an imprest advance (fixed/revolving float)
     * which must NEVER be adjusted against monthly reimbursement claims.
     */
    function isImprestAdvanceRecord($record): bool
    {
        if (empty($record)) {
            return false;
        }

        $record = (array) $record;

        // Use is_imprest boolean flag
        if (!empty($record['is_imprest']) || !empty($record['ar_is_imprest']) || !empty($record['ara_is_imprest'])) {
            return true;
        }

        $typeCode = strtolower(trim((string) ($record['type_code'] ?? '')));
        if ($typeCode === 'imp') {
            return true;
        }

        return false;
    }
}

if (!function_exists('isSuperAdmin')) {
    /**
     * Check if a user (or the current authenticated user) is Super Admin.
     */
    function isSuperAdmin($user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return false;
        }

        if ((int)$user->id === 1) {
            return true;
        }

        if (session('user_type') === 'Super Admin' || session('user_type') === 'super_admin') {
            return true;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super_admin'])) {
            return true;
        }

        if (method_exists($user, 'hasRole') && ($user->hasRole('Super Admin') || $user->hasRole('super_admin'))) {
            return true;
        }

        if (method_exists($user, 'can') && $user->can('super_admin')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('buildAdvanceImprestExclusionSql')) {
    /**
     * Build SQL WHERE conditions to exclude imprest advances safely across DB connections.
     */
    function buildAdvanceImprestExclusionSql($conn = null, $reqAlias = 'ar', $actAlias = 'ara', $typeAlias = 'art'): string
    {
        $clauses = [];

        if ($reqAlias) {
            $clauses[] = "({$reqAlias}.is_imprest = 0 OR {$reqAlias}.is_imprest IS NULL)";
        }

        if ($actAlias) {
            $clauses[] = "({$actAlias}.is_imprest = 0 OR {$actAlias}.is_imprest IS NULL)";
        }

        if ($typeAlias) {
            $clauses[] = "(LOWER(COALESCE({$typeAlias}.type_code, '')) != 'imp')";
        }

        return !empty($clauses) ? ' AND ' . implode(' AND ', $clauses) . ' ' : '';
    }
}

if (!function_exists('getCurrentMonthActivityAdvances')) {
    function getCurrentMonthActivityAdvances($employeeId, $month)
    {
        try {
            $conn = session('expense_connection') ?: 'expense';
            $imprestExclusion = buildAdvanceImprestExclusionSql($conn, 'ar', 'ara', 'art');

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
                LEFT JOIN advance_request_types art ON art.id = ar.advance_type_id
                LEFT JOIN claimtype ct ON ct.ClaimId = ar.activity_name_id
                WHERE ara.payment_status IN ('Paid','Unsettled')
                AND (art.type_code = 'Act' AND (ara.is_tour_travel = 'N' OR ara.is_tour_travel IS NULL OR ara.is_tour_travel = '' OR ara.is_tour_travel IN ('0', 0)))
                {$imprestExclusion}
                AND ar.deleted_at IS NULL
                AND ara.deleted_at IS NULL
                AND (are.employee_id = ?)
                AND ar.month = ?
            ";

            $results = DB::connection($conn)->select($sql, [$employeeId, $month]);
            if (empty($results) && $conn !== 'expense') {
                try {
                    $results = DB::connection('expense')->select($sql, [$employeeId, $employeeId, $month]);
                } catch (\Throwable $e) {}
            }

            $filtered = [];
            foreach ($results as $record) {
                $row = (array) $record;
                $row['is_imprest'] = $row['is_imprest'] ?? 0;
                if (!isImprestAdvanceRecord($row)) {
                    $filtered[] = $row;
                }
            }

            return $filtered;
        } catch (\Exception $e) {
            Log::error('getCurrentMonthActivityAdvances error: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('getPrevMonthExpenseAdvanceForAdjustment')) {
    /**
     * For company 11 (VVNR pays on the approved amount, then verifies).
     * Per month: (Fin_PayAmt + total_adjustment) - (Verified_Amount - DA) = paid in excess, recovered
     * in a later payment (only if positive).
     *
     * VVNR-ADJ-01 (Finance decision 7 Nov 2026): from November 2026 the excess of EVERY earlier verified
     * month of the year is offered for recovery automatically until it has been adjusted - not only
     * the month just before - and a month that is not verified yet is never offered (before, an
     * unverified month counted its whole payment as excess). April-September 2026-27 stay out: Finance
     * reconciles those by hand (config claim_rules.vvnr_auto_adjust).
     */
    function getPrevMonthExpenseAdvanceForAdjustment($employeeId, $currentMonth)
    {
        try {
            $yearId = (int) session('year_id');
            $connection = session('expense_connection');
            $employeeId = (int) $employeeId;
            $currentMonth = (int) $currentMonth;
            $fy = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
            $pos = array_search($currentMonth, $fy, true);
            $cfg = (array) config('claim_rules.vvnr_auto_adjust', []);
            $exclude = array_map('intval', (array) (($cfg['exclude_months'] ?? [])[$yearId] ?? []));
            $multi = now()->toDateString() >= (string) ($cfg['from'] ?? '2026-11-01');

            if ($pos === false || $pos === 0) {
                return []; // April: last year's March is settled with last year's records
            }
            $candidates = $multi ? array_slice($fy, 0, $pos) : [$fy[$pos - 1]];
            $candidates = array_values(array_diff($candidates, $exclude));
            if (!$candidates) {
                return [];
            }

            $tableName = 'y' . $yearId . '_monthexpensefinal';
            $rows = DB::connection($connection)->table($tableName)
                ->where('EmployeeID', $employeeId)->where('YearId', $yearId)
                ->get(['Month', 'Fin_PayAmt', 'total_adjustment', 'Verified_Amount', 'Verified_Date', 'Fin_AdvancePay', 'expense_adjustments_json'])
                ->keyBy('Month');

            // months whose excess was already taken in a later payment
            $done = [];
            foreach ($rows as $r) {
                $json = json_decode((string) ($r->expense_adjustments_json ?? ''), true);
                foreach (is_array($json) ? $json : [] as $key => $item) {
                    $id = is_array($item) ? (string) ($item['expense_advance_id'] ?? $key) : (string) $key;
                    $amt = is_array($item) ? (float) ($item['adjustment_amount'] ?? $item['amount'] ?? $item['advance_amount'] ?? 0) : (float) $item;
                    if (preg_match('/^prev_month_(\d{1,2})$/', $id, $m) && $amt > 0) {
                        $done[(int) $m[1]] = true;
                    }
                }
            }

            $out = [];
            $claimsTable = 'y' . $yearId . '_expenseclaims';
            foreach ($candidates as $m) {
                $r = $rows[$m] ?? null;
                if (!$r || isset($done[$m])) {
                    continue;
                }
                $verified = (float) ($r->Verified_Amount ?? 0);
                $vDate = (string) ($r->Verified_Date ?? '');
                if ($verified <= 0 || $vDate === '' || str_starts_with($vDate, '0000')) {
                    continue; // not verified yet: nothing to compare with
                }
                $finPayAmt = (float) ($r->Fin_PayAmt ?? 0);
                $totalAdjustment = (float) ($r->total_adjustment ?? 0);
                if ($finPayAmt <= 0 && $totalAdjustment <= 0) {
                    continue;
                }
                $daResult = DB::connection($connection)->selectOne("
                    SELECT COALESCE(SUM(FinancedTAmt), 0) as DA_Amount
                    FROM {$claimsTable}
                    WHERE CrBy = ? AND ClaimMonth = ? AND ClaimStatus != 'Deactivate' AND FilledOkay = 1 AND (ClaimId = 19 OR ClaimId = 20)
                ", [$employeeId, $m]);
                $daAmount = $daResult ? (float) ($daResult->DA_Amount ?? 0) : 0;
                $excess = ($finPayAmt + $totalAdjustment) - ($verified - $daAmount);
                if ($excess < 1) {
                    continue;
                }
                $out[] = [
                    'advance_amount' => (int) round($excess),
                    'month' => $m,
                    'month_name' => date('M', mktime(0, 0, 0, $m, 1)),
                    'payment_date' => null,
                    'expense_advance_id' => 'prev_month_' . $m,
                    'source' => 'prev_month_calculation',
                    'paid_amount' => (int) round($finPayAmt + $totalAdjustment),
                    'fin_pay_amount' => (int) round($finPayAmt),
                    'total_adjustment' => (int) round($totalAdjustment),
                    'verified_amount' => (int) round($verified),
                    'da_amount' => (int) round($daAmount),
                ];
            }

            return $out;
        } catch (\Exception $e) {
            Log::error('getPrevMonthExpenseAdvanceForAdjustment error: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('getCurrentMonthExpenseAdvances')) {
    function getCurrentMonthExpenseAdvances($employeeId, $month)
    {
        try {
            $conn = session('expense_connection') ?: 'expense';
            $imprestExclusion = buildAdvanceImprestExclusionSql($conn, 'ar', 'ara', 'art');

            $sql = "
                SELECT
                    ara.id as expense_advance_id,
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
                LEFT JOIN advance_request_types art ON art.id = ar.advance_type_id
                WHERE ara.payment_status IN ('Paid', 'Unsettled')
                AND (
                    art.type_code = 'Exp'
                    OR LOWER(COALESCE(art.type_code, '')) = 'exp'
                    OR LOWER(COALESCE(art.type_name, '')) LIKE '%exp%'
                    OR LOWER(COALESCE(art.type_name, '')) LIKE '%auto%'
                    OR ara.is_tour_travel IN ('Y', 'y', '1', 1)
                    OR COALESCE(ar.advance_type_id, 0) IN (0, 2)
                    OR COALESCE(ar.activity_name_id, 0) = 0
                )
                AND (art.type_code != 'Act' OR art.type_code IS NULL)
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
                if (!isImprestAdvanceRecord($row)) {
                    $filtered[] = $row;
                }
            }

            return $filtered;
        } catch (\Exception $e) {
            Log::error('getCurrentMonthExpenseAdvances error: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('getAllPaidAdvances')) {

    function getAllPaidAdvances($employeeId, $month)
    {
        try {
            $conn = session('expense_connection') ?: 'expense';
            $imprestExclusion = buildAdvanceImprestExclusionSql($conn, 'ar', 'ara', 'art');

            $sql = "
                SELECT
                    ar.id AS advance_request_id,
                    ar.month,
                    ar.year_id,
                    ar.activity_name_id,
                    ct.ClaimName AS activity_name,
                    are.employee_id,
                    ara.id AS activity_id,
                    COALESCE(ara.is_imprest, ar.is_imprest, 0) as is_imprest,
                    ara.purpose,
                    ara.payment_remark as remark,
                    ar.created_by,
                    ar.req_app_submit_id,
                    COALESCE(
                        ara.finance_approved_amount,
                        ara.payment_amount,
                        ara.final_amount,
                        ara.amount
                    ) AS payment_amount,
                    ara.payment_date,
                    ara.payment_status,
                    art.type_code,
                    art.type_name
                FROM advance_requests ar
                INNER JOIN advance_request_employees are
                    ON are.advance_request_id = ar.id
                INNER JOIN advance_request_activities ara
                    ON ara.advance_request_employee_id = are.id
                LEFT JOIN advance_request_types art
                    ON art.id = ar.advance_type_id
                LEFT JOIN claimtype ct
                    ON ct.ClaimId = ar.activity_name_id
                WHERE (are.employee_id = ?)
                   AND (art.type_code = 'Act' AND (ara.is_tour_travel = 'N' OR ara.is_tour_travel IS NULL OR ara.is_tour_travel = '' OR ara.is_tour_travel IN ('0', 0)))
                  AND ara.payment_status IN ('Paid','Unsettled')
                  {$imprestExclusion}
                  AND ar.month = ?
                  AND ar.deleted_at IS NULL
                  AND are.deleted_at IS NULL
                  AND ara.deleted_at IS NULL
                ORDER BY ar.year_id ASC, ar.month ASC, ara.payment_date ASC
            ";

            $results = DB::connection($conn)->select($sql, [$employeeId, $month]);
            if (empty($results) && $conn !== 'expense') {
                try {
                    $results = DB::connection('expense')->select($sql, [$employeeId, $month]);
                } catch (\Throwable $e) {}
            }

            $filtered = [];
            foreach ($results as $row) {
                $arr = (array) $row;
                $arr['is_imprest'] = $arr['is_imprest'] ?? 0;
                if (!isImprestAdvanceRecord($arr)) {
                    $filtered[] = $arr;
                }
            }

            return $filtered;
        } catch (\Exception $e) {
            \Log::error('getAllPaidAdvances error: ' . $e->getMessage());

            return [];
        }
    }
}

function getAllMonthPaidAdvances($employeeId)
{
    $conn = session('expense_connection');
    $imprestExclusion = function_exists('buildAdvanceImprestExclusionSql')
        ? buildAdvanceImprestExclusionSql($conn, 'ar', 'ara', 'art')
        : '';

    $sql = "
        SELECT
            SUM(
                COALESCE(
                    ara.finance_approved_amount,
                    ara.payment_amount,
                    ara.final_amount,
                    ara.amount
                )
            ) AS total_paid
        FROM advance_requests ar
        INNER JOIN advance_request_employees are
            ON are.advance_request_id = ar.id
        INNER JOIN advance_request_activities ara
            ON ara.advance_request_employee_id = are.id
        LEFT JOIN advance_request_types art
            ON art.id = ar.advance_type_id
        WHERE are.employee_id = ?
        AND ara.payment_status IN ('Paid','Unsettled')
        {$imprestExclusion}
        AND ar.deleted_at IS NULL
        AND are.deleted_at IS NULL
        AND ara.deleted_at IS NULL
        AND ara.adjustment_amount = 0
    ";

    $result = DB::connection($conn)->selectOne($sql, [$employeeId]);

    return $result->total_paid ?? 0;
}

if (!function_exists('getActivityFinancedAmounts')) {
    function getActivityFinancedAmounts($employeeId, $month, $yearId = null)
    {
        if ($yearId === null) {
            $yearId = session('year_id');
        }

        try {
            $tableName = 'y' . $yearId . '_expenseclaims';

            $results = DB::connection(session('expense_connection'))->select("
                SELECT
                    ec.ClaimId,
                    ct.ClaimName,
                    SUM(ec.FinancedTAmt) AS total_financed
                FROM {$tableName} ec
                LEFT JOIN claimtype ct
                    ON ec.ClaimId = ct.ClaimId
                WHERE ec.ClaimMonth = ?
                  AND ec.CrBy = ?
                  AND ec.ClaimStatus != 'Deactivate'
                  AND ec.FinancedTAmt > 0
                   AND ct.cgId = 7
                GROUP BY ec.ClaimId, ct.ClaimName
            ", [$month, $employeeId]);

            return array_map(fn($record) => (array) $record, $results);
        } catch (\Exception $e) {
            Log::error('getActivityFinancedAmounts error: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('getAllActivityFinancedAmounts')) {

    function getAllActivityFinancedAmounts($employeeId)
    {
        try {
            // Sum financed amounts across ALL financial year tables
            $allYearIds = DB::connection(session('expense_connection'))
                ->table('financialyear')
                ->pluck('YearId')
                ->toArray();

            $grandTotal = 0;

            foreach ($allYearIds as $yearId) {
                $tableName = 'y' . $yearId . '_expenseclaims';

                try {
                    $result = DB::connection(session('expense_connection'))->selectOne("
                        SELECT SUM(ec.FinancedTAmt) AS total_financed
                        FROM {$tableName} ec
                        LEFT JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
                        WHERE ec.CrBy = ?
                          AND ec.ClaimStatus != 'Deactivate'
                          AND ec.FinancedTAmt > 0
                          AND ct.cgId = 7
                    ", [$employeeId]);

                    $grandTotal += $result->total_financed ?? 0;
                } catch (\Exception $tableEx) {
                    continue;
                }
            }

            return $grandTotal;
        } catch (\Exception $e) {
            Log::error('getActivityFinancedAmounts error: ' . $e->getMessage());

            return 0;
        }
    }
}

if (!function_exists('getCarriedAdvances')) {
    function getCarriedAdvances($employeeId, $currentMonth, $targetYearId = null)
    {
        try {
            $conn = session('expense_connection') ?: 'expense';
            $yearId = $targetYearId ?: (session('year_id') ?: 8);

            // In Indian FY (April=4 to March=3):
            // 4 (Apr) has no prior month in same FY -> check March of previous FY
            // 5 (May) -> 4 (Apr), 6 (Jun) -> 5 (May), ..., 12 (Dec) -> 11 (Nov)
            // 1 (Jan) -> 12 (Dec), 2 (Feb) -> 1 (Jan), 3 (Mar) -> 2 (Feb)
            $fyPriorMonth = [
                4  => ['year' => $yearId - 1, 'month' => 3],
                5  => ['year' => $yearId,     'month' => 4],
                6  => ['year' => $yearId,     'month' => 5],
                7  => ['year' => $yearId,     'month' => 6],
                8  => ['year' => $yearId,     'month' => 7],
                9  => ['year' => $yearId,     'month' => 8],
                10 => ['year' => $yearId,     'month' => 9],
                11 => ['year' => $yearId,     'month' => 10],
                12 => ['year' => $yearId,     'month' => 11],
                1  => ['year' => $yearId,     'month' => 12],
                2  => ['year' => $yearId,     'month' => 1],
                3  => ['year' => $yearId,     'month' => 2],
            ];

            $priorInfo = $fyPriorMonth[(int)$currentMonth] ?? null;
            if (!$priorInfo) {
                return ['advances' => [], 'total' => 0];
            }

            $table = 'y' . $priorInfo['year'] . '_monthexpensefinal';
            try {
                $row = DB::connection($conn)->table($table)
                    ->where('EmployeeID', $employeeId)
                    ->where('Month', $priorInfo['month'])
                    ->orderByDesc('id')
                    ->first();
            } catch (\Exception $e) {
                $row = null;
            }
            if (!$row || empty($row->carried_balance_json)) {
                return ['advances' => [], 'total' => 0];
            }

            $jsonData = json_decode($row->carried_balance_json, true);

            if (is_string($jsonData)) {
                $jsonData = json_decode($jsonData, true);
            }
            $carriedAdvances = [];
            $totalCarriedAdvance = 0;

            $allAdvances = array_merge(
                $jsonData['ledger'] ?? [],
                $jsonData['paid'] ?? [],
                $jsonData['expense'] ?? [],
                $jsonData['carried'] ?? []
            );

            foreach ($allAdvances as $index => $adv) {
                $remaining = $adv['remaining_amount'] ?? 0;

                if ($remaining > 0) {
                    $carriedAdvances[] = [
                        'amount' => (int) $remaining,
                        'original' => (int) ($adv['original_amount'] ?? 0),
                        'advance_id' => (int) ($adv['advance_id'] ?? 0),
                        'category' => $adv['category'] ?? '',
                        'month' => $adv['month'] ?? '',
                        'payment_date' => $adv['payment_date'] ?? '',
                        'index' => $index + 1,
                    ];

                    $totalCarriedAdvance += $remaining;
                }
            }

            // dd( $carriedAdvances);
            return [
                'advances' => $carriedAdvances,
                'total' => $totalCarriedAdvance,
            ];
        } catch (\Exception $e) {
            Log::error('getCarriedAdvances error: ' . $e->getMessage());

            return ['advances' => [], 'total' => 0];
        }
    }
}

if (!function_exists('getCarriedAdvancesforadjustment')) {
    function getCarriedAdvancesforadjustment($employeeId, $currentMonth)
    {
        try {
            $yearId = session('year_id');
            $companyId = session('company_id');

            // Fetch all year IDs ordered newest → oldest for cross-year fallback
            $allYearIds = DB::connection(session('expense_connection'))
                ->table('financialyear')
                ->orderByDesc('YearId')
                ->pluck('YearId')
                ->toArray();

            // =====================================================
            // ✅ COMPANY 11 LOGIC (PREVIOUS MONTH CALCULATION)
            // =====================================================
            if ($companyId == 11) {

                $prevMonth = $currentMonth - 1;
                $prevYearId = $yearId;

                // Handle January → look in previous financial year
                if ($currentMonth == 1) {
                    $prevMonth = 12;
                    // Find the year ID that comes just before the current one
                    $currentIndex = array_search($yearId, $allYearIds);
                    $prevYearId = ($currentIndex !== false && isset($allYearIds[$currentIndex + 1]))
                        ? $allYearIds[$currentIndex + 1]
                        : $yearId - 1; // fallback if not found
                }

                $table = 'y' . $prevYearId . '_monthexpensefinal';

                try {
                    $row = DB::connection(session('expense_connection'))
                        ->table($table)
                        ->where('EmployeeID', $employeeId)
                        ->where('Month', $prevMonth)
                        ->where('Fin_AppDate', '!=', '0000-00-00')
                        ->where('Fin_AppDate', '!=', '')
                        ->where('Fin_AppDate', '!=', '1970-01-01')
                        ->whereNotNull('DocNo')
                        ->where('DocNo', '!=', '')
                        ->whereNotNull('PostDate')
                        ->where('PostDate', '!=', '')
                        ->where('PostDate', '!=', '0000-00-00')
                        ->orderByDesc('Fin_PayDate')
                        ->first();
                } catch (\Exception $e) {
                    $row = null;
                }

                if (!$row) {
                    return ['advances' => [], 'total' => 0];
                }

                $paidAmount = (float) ($row->Fin_PayAmt ?? 0) + (float) ($row->total_adjustment ?? 0);
                $verifiedAmount = (float) ($row->Verified_Amount ?? 0);

                // Fetch DA amount from expenseclaims for the previous month
                // (ClaimId 19 = TA, 20 = DA — same as getPrevMonthExpenseAdvanceForAdjustment)
                $expenseClaimsTable = 'y' . $prevYearId . '_expenseclaims';
                try {
                    $daResult = DB::connection(session('expense_connection'))->selectOne("
                        SELECT COALESCE(SUM(FinancedTAmt), 0) AS DA_Amount
                        FROM {$expenseClaimsTable}
                        WHERE CrBy = ?
                          AND ClaimMonth = ?
                          AND ClaimStatus != 'Deactivate'
                          AND FilledOkay = 1
                          AND (ClaimId = 19 OR ClaimId = 20)
                    ", [$employeeId, $prevMonth]);
                    $daAmount = $daResult ? (float) ($daResult->DA_Amount ?? 0) : 0;
                } catch (\Exception $e) {
                    $daAmount = 0;
                }

                // Correct formula: (Fin_PayAmt + Fin_AdvancePay) - (Verified_Amount - DA_Amount)
                $remaining = $paidAmount - ($verifiedAmount - $daAmount);

                if ($remaining <= 0) {
                    return ['advances' => [], 'total' => 0];
                }

                return [
                    'advances' => [
                        [
                            'amount' => (int) $remaining,
                            'original' => (int) $paidAmount,
                            'advance_id' => 0,
                            'category' => 'paid',
                            'month' => $prevMonth,
                            'payment_date' => $row->Fin_PayDate ?? '',
                            'index' => 1,
                        ]
                    ],
                    'total' => $remaining,
                ];
            }

            // =====================================================
            // ✅ DEFAULT LOGIC — scan ALL years newest → oldest
            //    until we find a row with a valid carried_balance_json
            // =====================================================
            $row = null;

            foreach ($allYearIds as $scanYearId) {
                $table = 'y' . $scanYearId . '_monthexpensefinal';

                try {
                    $candidate = DB::connection(session('expense_connection'))
                        ->table($table)
                        ->where('EmployeeID', $employeeId)
                        ->whereNotIn('Fin_AppDate', ['0000-00-00', '', '1970-01-01'])
                        ->whereNotNull('carried_balance_json')
                        ->where('carried_balance_json', '!=', '')
                        ->orderByRaw('
                            CASE
                                WHEN Month >= 4 THEN Month
                                ELSE Month + 12
                            END DESC
                        ')
                        ->first();

                    if ($candidate && !empty($candidate->carried_balance_json)) {
                        $row = $candidate;
                        break; // found the most recent carried balance — stop scanning
                    }
                } catch (\Exception $tableEx) {
                    continue; // table doesn't exist for this year
                }
            }

            if (!$row || empty($row->carried_balance_json)) {
                return ['advances' => [], 'total' => 0];
            }

            $jsonData = json_decode($row->carried_balance_json, true);

            if (is_string($jsonData)) {
                $jsonData = json_decode($jsonData, true);
            }

            $carriedAdvances = [];
            $totalCarriedAdvance = 0;

            $allAdvances = array_merge(
                $jsonData['ledger'] ?? [],
                $jsonData['paid'] ?? [],
                $jsonData['expense'] ?? [],
                $jsonData['carried'] ?? []
            );

            foreach ($allAdvances as $index => $adv) {
                if (function_exists('isImprestAdvanceRecord') && isImprestAdvanceRecord($adv)) {
                    continue;
                }

                $remaining = $adv['remaining_amount'] ?? 0;

                if ($remaining > 0) {
                    $carriedAdvances[] = [
                        'amount' => (int) $remaining,
                        'original' => (int) ($adv['original_amount'] ?? 0),
                        'advance_id' => (int) ($adv['advance_id'] ?? 0),
                        'category' => $adv['category'] ?? '',
                        'month' => $adv['month'] ?? '',
                        'payment_date' => $adv['payment_date'] ?? '',
                        'index' => $index + 1,
                    ];

                    $totalCarriedAdvance += $remaining;
                }
            }

            return [
                'advances' => $carriedAdvances,
                'total' => $totalCarriedAdvance,
            ];

        } catch (\Exception $e) {
            Log::error('getCarriedAdvancesforadjustment error: ' . $e->getMessage());

            return ['advances' => [], 'total' => 0];
        }
    }
}

if (!function_exists('getAllCarriedAdvances')) {

    function getAllCarriedAdvances($employeeId)
    {
        try {
            // Query all available financial year tables to get carried balances
            // across all years, not just the current session year.
            $allYearIds = DB::connection(session('expense_connection'))
                ->table('financialyear')
                ->pluck('YearId')
                ->toArray();
            // dd($allYearIds);
            $bestRow = null;

            foreach ($allYearIds as $yearId) {
                $tableName = 'y' . $yearId . '_monthexpensefinal';

                // Check table exists before querying
                try {
                    $row = DB::connection(session('expense_connection'))
                        ->table($tableName)
                        ->where('EmployeeID', $employeeId)
                        ->whereIn('payment_status', ['ConfirmedPayment', 'InstructedPayment', 'Unsettled'])
                        ->whereNotNull('carried_balance_json')
                        ->orderByDesc('payment_approved_date')
                        ->first();

                    if ($row && !empty($row->carried_balance_json)) {
                        // Keep the most recently approved row across all years
                        if (
                            $bestRow === null ||
                            $row->payment_approved_date > $bestRow->payment_approved_date
                        ) {
                            $bestRow = $row;
                        }
                    }
                } catch (\Exception $tableEx) {
                    // Table may not exist for this year — skip silently
                    continue;
                }
            }

            if (!$bestRow || empty($bestRow->carried_balance_json)) {
                return 0;
            }

            $jsonData = json_decode($bestRow->carried_balance_json, true);

            // handle double encoded json
            if (is_string($jsonData)) {
                $jsonData = json_decode($jsonData, true);
            }

            $totalCarriedAdvance = 0;

            foreach (['ledger', 'paid', 'expense', 'carried'] as $key) {

                foreach ((array) ($jsonData[$key] ?? []) as $adv) {

                    $remaining = (float) ($adv['remaining_amount'] ?? 0);

                    if ($remaining > 0) {
                        $totalCarriedAdvance += $remaining;
                    }
                }
            }

            return $totalCarriedAdvance;
        } catch (\Exception $e) {

            Log::error('getCarriedAdvances error: ' . $e->getMessage());

            return 0;
        }
    }
}

// if (! function_exists('getCarriedAdvances')) {
//     function getCarriedAdvances($employeeId, $currentMonth)
//     {
//         // return 0;
//         try {
//             $ledgerData = getEmployeeLedgerBalance($employeeId);
//             $carriedAdvances = [];
//             $totalCarriedAdvance = 0;

//             if ($ledgerData) {
//                 for ($j = 1; $j <= 4; $j++) {
//                     $advAmount = $ledgerData['advance_amt_'.$j] ?? 0;
//                     $advPurpose = $ledgerData['advance_purpose_'.$j] ?? '';
//                     $advMonth = $ledgerData['advance_month_'.$j] ?? '';
//                     $advActivityId = $ledgerData['advance_activity_id_'.$j] ?? 0;
//                     $advDate = $ledgerData['advance_date_'.$j] ?? '';

//                     if (! empty($advAmount) && $advAmount > 0 && ! empty($advPurpose) && ! empty($advMonth)) {
//                         // Parse month from format "Oct/25" or "10/2025"
//                         $advMonthParts = explode('/', $advMonth);
//                         $advMonthNum = 0;

//                         if (is_numeric($advMonthParts[0])) {
//                             $advMonthNum = intval($advMonthParts[0]);
//                         } else {
//                             $monthMap = [
//                                 'Jan' => 1,
//                                 'Feb' => 2,
//                                 'Mar' => 3,
//                                 'Apr' => 4,
//                                 'May' => 5,
//                                 'Jun' => 6,
//                                 'Jul' => 7,
//                                 'Aug' => 8,
//                                 'Sep' => 9,
//                                 'Oct' => 10,
//                                 'Nov' => 11,
//                                 'Dec' => 12,
//                             ];
//                             $advMonthNum = isset($monthMap[$advMonthParts[0]]) ? $monthMap[$advMonthParts[0]] : 0;
//                         }

//                         // If advance is from previous month (carried forward)
//                         $currentFYMonth = getFinancialMonth($currentMonth);
//                         $advanceFYMonth = getFinancialMonth($advMonthNum);

//                         if ($advMonthNum > 0 && $advanceFYMonth < $currentFYMonth) {
//                             $carriedAdvances[] = [
//                                 'amount' => (int) $advAmount,
//                                 'purpose' => $advPurpose,
//                                 'month' => $advMonth,
//                                 'activity_id' => (int) $advActivityId,
//                                 'date' => $advDate,
//                                 'index' => $j,
//                             ];
//                             $totalCarriedAdvance += $advAmount;
//                         }
//                     }
//                 }
//             }

//             return [
//                 'advances' => $carriedAdvances,
//                 'total' => $totalCarriedAdvance,
//             ];
//         } catch (\Exception $e) {
//             Log::error('getCarriedAdvances error: '.$e->getMessage());

//             return ['advances' => [], 'total' => 0];
//         }
//     }
// }

if (!function_exists('getFixedImprest')) {
    function getFixedImprest($employeeId)
    {
        try {
            $ledgerData = getEmployeeFixedimpresst($employeeId);
            // dd($ledgerData);
            $fixed1 = isset($ledgerData['fixed_imprest_amt_1']) ? (float) $ledgerData['fixed_imprest_amt_1'] : 0;
            $fixed2 = isset($ledgerData['fixed_imprest_amt_2']) ? (float) $ledgerData['fixed_imprest_amt_2'] : 0;
            $fixed1Date = isset($ledgerData['fixed_imprest_date_1']) ? $ledgerData['fixed_imprest_date_1'] : '';
            $fixed2Date = isset($ledgerData['fixed_imprest_date_2']) ? $ledgerData['fixed_imprest_date_2'] : '';

            return [
                'amount_1' => $fixed1,
                'date_1' => $fixed1Date,
                'amount_2' => $fixed2,
                'date_2' => $fixed2Date,
                'total' => $fixed1 + $fixed2,
            ];
        } catch (\Exception $e) {
            Log::error('getFixedImprest error: ' . $e->getMessage());

            return [
                'amount_1' => 0,
                'date_1' => '',
                'amount_2' => 0,
                'date_2' => '',
                'total' => 0,
            ];
        }
    }
}

if (!function_exists('getClaimType')) {
    function getClaimType($claimId)
    {
        try {
            $result = DB::connection('hrims')->selectOne('
                SELECT ClaimName
                FROM claimtype
                WHERE ClaimId = ?
            ', [$claimId]);

            return $result ? $result->ClaimName : '';
        } catch (\Exception $e) {
            Log::error('getClaimType error: ' . $e->getMessage());

            return '';
        }
    }
}

if (!function_exists('getEmployeeLedgerBalanceSummary')) {
    /**
     * Compute the same balance figures the modal uses, server-side.
     * Mirrors getEmployeeLedgerDetails() in ExpensePaymentController exactly.
     *
     * Returns:
     *   total_imprest   — sum of fixed_imprest_amt_1 + fixed_imprest_amt_2
     *   advance_balance — running balance from ledger_advance + paid_advance - carried/adjustment
     *   total_balance   — total_imprest + advance_balance  (matches modal "Final Balance")
     */
    function getEmployeeLedgerBalanceSummary($employeeId): array
    {
        try {
            $controller = app(\App\Http\Controllers\ExpensePaymentController::class);

            return $controller->computeEmployeeLedgerBalance($employeeId);
        } catch (\Exception $e) {
            Log::error('getEmployeeLedgerBalanceSummary error: ' . $e->getMessage());

            return [
                'total_imprest'   => 0.0,
                'advance_balance' => 0.0,
                'total_balance'   => 0.0,
            ];
        }
    }
}

if (!function_exists('formatVvnrPaymentRemark')) {
    /**
     * For VVNR (Company 11): Format payment remarks to accurately reflect:
     * 1. Paid amount
     * 2. Previous month adjustment with specific amount and month (e.g. Adjusted with previous month (July) expenses Rs. 1003)
     * 3. Any additional activity or expense advance adjustments
     * 4. DA payment amount separately mentioned (e.g. DA Rs. 0 or DA Rs. 2400)
     */
    function formatVvnrPaymentRemark($employeeId, $month, $yearId, $paidAmount, $existingRemark = '', $mef = null, $connection = null)
    {
        try {
            $connection = $connection ?: (session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection());

            // 1. Fetch current month record if not provided
            if (!$mef) {
                $tableName = "y{$yearId}_monthexpensefinal";
                $mef = DB::connection($connection)->table($tableName)
                    ->where('EmployeeID', $employeeId)
                    ->where('Month', $month)
                    ->first();
            }

            $paidAmt = (float) $paidAmount;
            if ($paidAmt <= 0 && $mef) {
                $paidAmt = (float) ($mef->Fin_PayAmt ?? ($mef->payment_amount ?? 0));
            }

            $totAdj = (float) ($mef->total_adjustment ?? 0);
            $expAdj = (float) ($mef->total_expense_adjustment ?? 0);
            $actAdj = (float) ($mef->total_activity_adjustment ?? 0);

            // 2. Fetch DA for this month from expenseclaims
            $claimsTable = "y{$yearId}_expenseclaims";
            $daAmt = 0;
            try {
                $daRow = DB::connection($connection)->selectOne("
                    SELECT COALESCE(SUM(FinancedTAmt), 0) as da_fin, COALESCE(SUM(VerifyTAmt), 0) as da_ver 
                    FROM {$claimsTable} 
                    WHERE CrBy = ? AND ClaimMonth = ? AND ClaimStatus != 'Deactivate' AND FilledOkay = 1 AND (ClaimId = 19 OR ClaimId = 20)
                ", [$employeeId, $month]);
                if ($daRow) {
                    $daAmt = (float) ($daRow->da_fin > 0 ? $daRow->da_fin : $daRow->da_ver);
                }
            } catch (\Exception $e) {
                // Ignore claims table query failure
            }

            // 3. Determine previous month details
            $prevMonth = ($month > 1) ? ($month - 1) : 12;
            $prevYearId = ($month > 1) ? $yearId : ($yearId - 1);
            $prevMonthName = date('F', mktime(0, 0, 0, $prevMonth, 1));

            // 4. Calculate or retrieve previous month adjustment
            $prevAdjAmt = 0;

            // Check if expense_adjustments_json has explicit prev_month adjustment
            if ($mef && !empty($mef->expense_adjustments_json)) {
                $json = json_decode($mef->expense_adjustments_json, true);
                if (is_array($json)) {
                    foreach ($json as $item) {
                        if (($item['source'] ?? '') === 'prev_month_calculation' || str_starts_with($item['expense_advance_id'] ?? '', 'prev_month_')) {
                            $prevAdjAmt = (float) ($item['adjustment_amount'] ?? ($item['advance_amount'] ?? $prevAdjAmt));
                        }
                    }
                }
            }

            // Fallback: Calculate via getPrevMonthExpenseAdvanceForAdjustment logic if prevAdjAmt is 0
            if ($prevAdjAmt == 0 && ($totAdj > 0 || $expAdj > 0 || ($mef && ((float) ($mef->Approved_Amount ?? 0) > $paidAmt)))) {
                $prevTable = "y{$prevYearId}_monthexpensefinal";
                try {
                    $prevMef = DB::connection($connection)->selectOne("
                        SELECT Fin_PayAmt, total_adjustment, Verified_Amount, Approved_Amount 
                        FROM {$prevTable} 
                        WHERE EmployeeID = ? AND Month = ?
                    ", [$employeeId, $prevMonth]);

                    if ($prevMef) {
                        $pPaid = (float) ($prevMef->Fin_PayAmt ?? 0) + (float) ($prevMef->total_adjustment ?? 0);
                        $pVer = (float) ($prevMef->Verified_Amount ?? 0) ?: (float) ($prevMef->Approved_Amount ?? 0); // VVNR keeps Verified_Amount at 0

                        $prevClaimsTable = "y{$prevYearId}_expenseclaims";
                        $pDaRow = DB::connection($connection)->selectOne("
                            SELECT COALESCE(SUM(FinancedTAmt), 0) as da_fin, COALESCE(SUM(VerifyTAmt), 0) as da_ver 
                            FROM {$prevClaimsTable} 
                            WHERE CrBy = ? AND ClaimMonth = ? AND ClaimStatus != 'Deactivate' AND FilledOkay = 1 AND (ClaimId = 19 OR ClaimId = 20)
                        ", [$employeeId, $prevMonth]);
                        $pDa = (float) ($pDaRow ? ($pDaRow->da_fin > 0 ? $pDaRow->da_fin : $pDaRow->da_ver) : 0);

                        $calcPrevAdv = $pPaid - ($pVer - $pDa);
                        if ($calcPrevAdv > 0) {
                            $prevAdjAmt = round($calcPrevAdv);
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            // Also check if existing remark already had a specific previous month adjustment mentioned
            if ($prevAdjAmt == 0 && !empty($existingRemark)) {
                if (preg_match('/Adjusted with previous month.*?expenses Rs\.?\s*([0-9.]+)/i', $existingRemark, $m)) {
                    $prevAdjAmt = (float) $m[1];
                }
            }

            // 5. Build remark components
            $paidStr = (floor($paidAmt) == $paidAmt) ? number_format($paidAmt, 0, '', '') : number_format($paidAmt, 2);
            $parts = ["Paid Rs. {$paidStr}"];

            $adjParts = [];
            if ($prevAdjAmt > 0) {
                $prevAdjStr = (floor($prevAdjAmt) == $prevAdjAmt) ? number_format($prevAdjAmt, 0, '', '') : number_format($prevAdjAmt, 2);
                $adjParts[] = "Adjusted with previous month ({$prevMonthName}) expenses Rs. {$prevAdjStr}";
            }

            if ($actAdj > 0) {
                $actStr = (floor($actAdj) == $actAdj) ? number_format($actAdj, 0, '', '') : number_format($actAdj, 2);
                $adjParts[] = "Activity Adv. Rs. {$actStr}";
            }

            if ($expAdj > 0 && $prevAdjAmt == 0) {
                $expStr = (floor($expAdj) == $expAdj) ? number_format($expAdj, 0, '', '') : number_format($expAdj, 2);
                $adjParts[] = "Expense Adv. Rs. {$expStr}";
            }

            // VVNR-REMARK-01: an adjustment recorded on the month (total_adjustment) that is not
            // an advance is the previous month's difference; show it with its month (Finance mail
            // 30 Oct: "Rs 1,003 of July adjusted in August but remark says No Adjustment").
            $otherAdj = round($totAdj - $expAdj - $actAdj - $prevAdjAmt, 2);
            if ($otherAdj > 0) {
                $oStr = (floor($otherAdj) == $otherAdj) ? number_format($otherAdj, 0, '', '') : number_format($otherAdj, 2);
                $adjParts[] = "Adjusted with previous month ({$prevMonthName}) expenses Rs. {$oStr}";
            }

            if (!empty($adjParts)) {
                $parts[] = implode(', ', $adjParts);
            } else {
                $parts[] = 'No adjustments';
            }

            // DA is shown separately (and only when there is DA)
            if ($daAmt > 0) {
                $daStr = (floor($daAmt) == $daAmt) ? number_format($daAmt, 0, '', '') : number_format($daAmt, 2);
                $parts[] = "DA Rs. {$daStr}";
            }

            return implode(', ', $parts);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('formatVvnrPaymentRemark error: ' . $e->getMessage());
            return !empty($existingRemark) ? $existingRemark : "Paid Rs. {$paidAmount}";
        }
    }
}

