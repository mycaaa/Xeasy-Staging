<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ClaimController extends Controller
{
    public function index(Request $request)
    {
        $chkval = $request->get('chkval', 2);
        $v = $request->get('v', 0);
        $page = $request->get('page', 1);

        // Get departments for filter dropdown
        $departments = DB::connection('hrims')
            ->table('core_departments')
            ->orderBy('department_name')
            ->pluck('department_name')
            ->toArray();

        return view('claims.index', compact('chkval', 'v', 'page', 'departments'));
    }



    public function showExpenseDetails($expid)
    {
        $yearId = session('year_id');
        $connection = DB::connection(session('expense_connection'));
        $exp = $connection->selectOne("select ExpId, FilledOkay from y{$yearId}_expenseclaims where ExpId = ?", [$expid]);
        if (!$exp || (int)($exp->FilledOkay ?? 0) !== 1) {
            abort(404, 'Claim not found or not confirmed by claimant (FilledOkay is not 1).');
        }
        return view('claims.show-expense-details', compact('expid'));
    }

public function showClaimForm(Request $request)
{
    $claimid = $request->get('claimid');
    $expid = $request->get('expid');
    $upbtndis = $request->get('upbtndis');

    $yearId = session('year_id');
    $connection = DB::connection(session('expense_connection'));

    // Fetch the expense data (only if FilledOkay = 1)
    $exp = $connection->selectOne("select * from y{$yearId}_expenseclaims where ExpId = ? and FilledOkay = 1", [$expid]);

    if (!$exp) {
        return '<div class="alert alert-danger text-center m-2">Claim not found or not confirmed by claimant (FilledOkay is not 1).</div>';
    }

    // Fetch expense details
    $expDlt = $connection->selectOne("select * from y{$yearId}_expenseclaimsdetails where ExpId = ?", [$expid]);

    // Get claim type group
    $cg = DB::selectOne("select cgId from claimtype where ClaimId = ?", [$claimid]);
    $cgId = $cg ? $cg->cgId : 0;

    // Get group-specific fill data
    $expf = null;
    if($cgId > 0) {
        $expf = $connection->selectOne("select * from y{$yearId}_g{$cgId}_expensefilldata where ExpId = ?", [$expid]);
    }

    return view('claims.showclaimform', compact('claimid', 'expid', 'upbtndis', 'exp', 'expDlt', 'expf', 'yearId'));
}


public function getLimit(Request $request)
{
    $empid = $request->get('empid');
    $cid = $request->get('cid');

    $yearId = session('year_id');
    $connection = DB::connection(session('expense_connection'));

    // E-03: the local claimlimits table is not a source of limits any more (HRIMS eligibility is)
    if (! config('claim_rules.local_limits', false)) {
        return '<span id="limitspan">Limit: as per eligibility</span>';
    }
    $limit = $connection->selectOne("
        SELECT verifiedLimit
        FROM claimlimits
        WHERE EmployeeID = ? AND ClaimId = ?
    ", [$empid, $cid]);

    $limitAmount = $limit ? $limit->verifiedLimit : 0;

    return '<span id="limitspan">Limit: ' . number_format($limitAmount) . '</span>';
}

/** B-02 (10 Nov 2026): VerifyDate/VerifyBy and ApprDate/ApprBy are the real column names (the old ones do not exist, so
 * these two actions failed); approving carries the verified amount into ApprTAmt when the approver set none. */
public function verifyClaim(Request $request)
{
    $expid = $request->get('expid');
    $yearId = session('year_id');
    $connection = DB::connection(session('expense_connection'));

    $connection->update("
        UPDATE y{$yearId}_expenseclaims
        SET ClaimStatus = 'Verified',
            ClaimAtStep = 4,
            VerifyDate = NOW(),
            VerifyBy = ?
        WHERE ExpId = ?
    ", [session('EmployeeID'), $expid]);

    return 'verified';
}

public function approveClaim(Request $request)
{
    $expid = (int) $request->get('expid');
    $yearId = (int) session('year_id');
    $connection = DB::connection(session('expense_connection'));
    // FIN-APPR-01: through the status gate (VNR: approve only Verified claims)
    \App\Services\Claims\ClaimTransition::guard($connection, $yearId, [$expid], 'Approved', 'approver', 'finance_claim_list_approve');

    $connection->update("
        UPDATE y{$yearId}_expenseclaims
        SET ApprTAmt = IF(ClaimStatus = 'Verified' AND VerifyTAmt > 0 AND (ApprTAmt IS NULL OR ApprTAmt = 0 OR ApprTAmt = FilledTAmt), VerifyTAmt, ApprTAmt),
            ClaimStatus = 'Approved',
            ClaimAtStep = 5,
            ApprDate = NOW(),
            ApprBy = ?
        WHERE ExpId = ?
    ", [session('EmployeeID'), $expid]);

    return 'approved';
}

public function financeClaim(Request $request)
{
    $expid = (int) $request->get('expid');
    $yearId = (int) session('year_id');
    $connection = DB::connection(session('expense_connection'));
    // FIN-APPR-01: finance only Approved claims (status gate)
    \App\Services\Claims\ClaimTransition::guard($connection, $yearId, [$expid], 'Financed', 'finance', 'finance_claim_list');

    $connection->update("
        UPDATE y{$yearId}_expenseclaims
        SET ClaimStatus = 'Financed',
            ClaimAtStep = 6,
            FinancedDate = NOW(),
            FinancedBy = ?
        WHERE ExpId = ?
        AND FilledOkay = 1
    ", [session('EmployeeID'), $expid]);

    return 'financed';
}

private function generateClaimFormHTML($claimType, $exp, $yearId, $connection)
{
    // This is a simplified version - you'll need to adapt your existing form logic
    $html = '<tr>';

    // Add form fields based on claim type
    // You can include the existing HTML from your showclaimform.php here

    $html .= '<td colspan="2">
                <div class="form-group">
                    <label>Bill Date</label>
                    <input type="date" name="BillDate" class="form-control" value="' . $exp->BillDate . '">
                </div>
              </td>';

    $html .= '<td colspan="2">
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" name="FilledTAmt" class="form-control" value="' . $exp->FilledTAmt . '">
                </div>
              </td>';

    $html .= '</tr>';

    return $html;
}

public function imagePreview(Request $request)
{
    $imglink = $request->get('imglink');
    $inpage = $request->get('inpage');
    $expid = $request->get('expid');

    return view('claims.image-preview', compact('imglink', 'inpage', 'expid'));
}


public function showClaim(Request $request)
{
    $expid = $request->get('expid');
    $yearId = session('year_id');
    $connection = DB::connection(session('expense_connection'));

    // Get expense claim data (only if FilledOkay = 1)
    $exp = $connection->selectOne("select * from y{$yearId}_expenseclaims where ExpId = ? and FilledOkay = 1", [$expid]);

    if (!$exp) {
        abort(404, 'Claim not found or not confirmed by claimant (FilledOkay is not 1)');
    }

    // Get associated uploads
    $uploads = $connection->select("
        SELECT cu.*
        FROM y{$yearId}_claimuploads cu
        WHERE cu.ExpId = ?
        ORDER BY cu.UploadSequence ASC
    ", [$expid]);

    $data = array();
    if (count($uploads) > 0) {
        foreach ($uploads as $upload) {
            $data[] = $upload->FileName;
        }
    } else {
        if (isset($exp->Img_1) && !empty($exp->Img_1)) {
            $data[] = $exp->Img_1;
        }
    }

    $requestText = urlencode(json_encode($data));

    // Get claim type name
    $claimType = DB::selectOne("SELECT ClaimId, ClaimName FROM claimtype WHERE ClaimId = ?", [$exp->ClaimId]);

    // Get employee name from HRIMS
    $employee = DB::connection('hrims')
        ->selectOne("SELECT Fname, Sname, Lname FROM hrm_employee WHERE EmployeeID = ?", [$exp->CrBy]);
    $employeeName = $employee ? trim($employee->Fname.' '.$employee->Sname.' '.$employee->Lname) : '';

    // Determine if user can edit
    $canEdit = false;
    $role = session('role_id');
    $status = $exp->ClaimStatus;

    if(($role == 'M' && in_array($status, ['Draft', 'Submitted', 'Filled']))
        || ($role == 'V' && in_array($status, ['Filled', 'Verified']))
        || $role == 'S') {
        $canEdit = true;
    }

    return view('claims.showclaim', compact(
        'exp', 'expid', 'requestText', 'claimType', 'employeeName', 'canEdit', 'yearId', 'connection'
    ));
}


public function getFormValue(Request $request)
{
    $act = $request->get('act');
    $yearId = session('year_id');

    switch($act) {
        case 'get_citycategory':
            $ccid = $request->get('ccid');
            $result = DB::selectOne("SELECT city_category FROM city_category WHERE ccid = ?", [$ccid]);
            return $result->city_category ?? 'C';

        case 'get_hotel':
            $ccid = $request->get('ccid');
            $hotels = DB::select("SELECT * FROM hotel_list WHERE ccid = ?", [$ccid]);
            $html = '<select class="form-control" name="hotel_name" onchange="FunHotel(this.value)" required>';
            $html .= '<option value="">Select Hotel</option>';
            foreach($hotels as $hotel) {
                $html .= '<option value="' . e($hotel->hid) . '">' . e($hotel->hotel_name) . '</option>';
            }
            $html .= '<option value="0">Other</option>';
            $html .= '</select>';
            return $html;

        case 'get_HotelAddress':
            $hid = $request->get('hid');
            $result = DB::selectOne("SELECT hotel_address FROM hotel_list WHERE hid = ?", [$hid]);
            return $result->hotel_address ?? '';

        case 'get_hotelC':
            $hid = $request->get('hid');
            $result = DB::selectOne("SELECT HotelContact FROM hotel_list WHERE hid = ?", [$hid]);
            return $result->HotelContact ?? '';

        case 'get_hotelE':
            $hid = $request->get('hid');
            $result = DB::selectOne("SELECT HotelEmail FROM hotel_list WHERE hid = ?", [$hid]);
            return $result->HotelEmail ?? '';

        case 'get_agencyAddress':
            $v = $request->get('v');
            $result = DB::selectOne("SELECT address FROM agency WHERE name = ?", [$v]);
            return $result->address ?? '';

        default:
            return '';
    }
}



public function updateClaim(Request $request)
{
    $expid = $request->get('expid');
    $claimid = $request->get('ClaimId');
    $yearId = session('year_id');
    $connection = DB::connection(session('expense_connection'));

    // Prepare update data
    $updateData = [
        'BillDate' => $request->get('BillDate') ? (\App\Services\ClaimRules\ClaimSubmissionValidator::parseDate($request->get('BillDate')) ?? date('Y-m-d', strtotime($request->get('BillDate')))) : null, // BILLDATE-03 day-first
        'Remark' => $request->get('M_Remark'),
    ];

    // Set amount based on role
    if ($request->has('TotalAmt')) {
        $updateData['FilledTAmt'] = $request->get('TotalAmt');
    }

    // Set status based on save type
    $savetype = $request->get('savetype');
    if ($savetype == 'Draft') {
        $updateData['ClaimStatus'] = 'Draft';
    } else {
        $updateData['ClaimStatus'] = 'Filled';
    }

    // Update the expense claim
    $connection->table("y{$yearId}_expenseclaims")
        ->where('ExpId', $expid)
        ->update($updateData);

    // If there are detail fields (fdtitle, fdamount, fdremark)
    $detailCount = 0;
    for ($i = 1; $i <= 10; $i++) {
        if ($request->has("fdtitle{$i}")) {
            $detailCount++;
            $detailData = [
                'Title' => $request->get("fdtitle{$i}"),
                'Remark' => $request->get("fdremark{$i}"),
                'Amount' => $request->get("fdamount{$i}") ?? 0,
                'ExpId' => $expid,
            ];

            if ($request->has("fdid{$i}")) {
                // Update existing detail
                $connection->table("y{$yearId}_expenseclaimsdetails")
                    ->where('ecdId', $request->get("fdid{$i}"))
                    ->update($detailData);
            } else {
                // Insert new detail
                $connection->table("y{$yearId}_expenseclaimsdetails")->insert($detailData);
            }
        }
    }

    // If there are 24 wheeler entry fields
    if ($claimid == 7) {
        for ($i = 1; $i <= 5; $i++) {
            if ($request->has("Open{$i}")) {
                $entryData = [
                    'ExpId' => $expid,
                    'DistTraOpen' => $request->get("Open{$i}") ?? 0,
                    'DistTraClose' => $request->get("Close{$i}") ?? 0,
                    'Totalkm' => $request->get("KmRunning{$i}") ?? 0,
                    'Rate_PerKm' => $request->get("KMRate{$i}") ?? 0,
                    'FilledTAmt' => $request->get("Amt{$i}") ?? 0,
                    'JourneyStartDt' => $request->get('JourneyStartDt') ? date('Y-m-d H:i:s', strtotime($request->get('JourneyStartDt'))) : null,
                    'JourneyEndDt' => $request->get('JourneyEndDt') ? date('Y-m-d H:i:s', strtotime($request->get('JourneyEndDt'))) : null,
                    'VehicleReg' => $request->get('RegNo'),
                ];

                $existing = $connection->table("y{$yearId}_24_wheeler_entry")
                    ->where('ExpId', $expid)
                    ->where('DistTraOpen', $request->get("Open{$i}"))
                    ->first();

                if ($existing) {
                    $connection->table("y{$yearId}_24_wheeler_entry")
                        ->where('id', $existing->id)
                        ->update($entryData);
                } else {
                    $connection->table("y{$yearId}_24_wheeler_entry")->insert($entryData);
                }
            }
        }
    }

    // If there are group fill data fields
    $cg = DB::selectOne("select cgId from claimtype where ClaimId = ?", [$claimid]);
    if ($cg && $cg->cgId > 0) {
        $fillData = [];

        // Collect all form fields for this claim type
        // This will vary based on the claim type
        $fillableFields = ['JourneyFrom', 'JourneyUpto', 'Mode', 'Agent', 'PNRNo', 'TrainBusName', 'Class',
                           'BookingDate', 'JourneyStartDt', 'StayDuration', 'TravelInsurance', 'VehicleType',
                           'CityName', 'HotelName', 'HotelAddress', 'HotelContact', 'HotelEmail', 'Plan',
                           'Arrival', 'Departure', 'BillNo', 'NoOfPAX', 'PaxDetails', 'BillingInstruction',
                           'GST', 'Mobile', 'MobileService', 'RechargeAmt', 'PaymentMode', 'Crop', 'CropDetails',
                           'fms', 'dtp', 'retailer', 'location', 'DailyBasisCharges', 'FuelRate', 'KmsTrv',
                           'CalAmount', 'FixedAmount', 'DistTraOpen', 'DistTraClose', 'KmBasisCharges',
                           'AgencyName', 'AgencyAddress', 'Invoice', 'VehicleReg', 'ServiceProvider',
                           'WeightCharged', 'DocketNumber', 'ReceiverName', 'ReceiverAddress', 'SourceCity',
                           'Source2City', 'BookingStatus'];

        foreach ($fillableFields as $field) {
            if ($request->has($field)) {
                $fillData[$field] = $request->get($field);
            }
        }

        if (!empty($fillData)) {
            $fillData['ExpId'] = $expid;

            if (isset($expf->did)) {
                $connection->table("y{$yearId}_g{$cg->cgId}_expensefilldata")
                    ->where('did', $expf->did)
                    ->update($fillData);
            } else {
                $connection->table("y{$yearId}_g{$cg->cgId}_expensefilldata")->insert($fillData);
            }
        }
    }

    // Redirect back with success message
    if ($request->ajax()) {
        return response()->json(['success' => true, 'message' => 'Claim updated successfully']);
    }

    return redirect()->back()->with('success', 'Claim updated successfully');
}



// public function updateClaim(Request $request)
// {
//     $expid = $request->get('expid');
//     $yearId = session('year_id');
//     $connection = DB::connection(session('expense_connection'));

//     // Basic update
//     $updateData = [
//         'BillDate' => $request->get('BillDate') ? date('Y-m-d', strtotime($request->get('BillDate'))) : null,
//         'FilledTAmt' => $request->get('TotalAmt') ?? $request->get('FilledTAmt'),
//         'Remark' => $request->get('M_Remark'),
//         'ClaimStatus' => $request->get('savetype') == 'Draft' ? 'Draft' : 'Filled',
//     ];

//     $connection->table("y{$yearId}_expenseclaims")
//         ->where('ExpId', $expid)
//         ->update($updateData);

//     return redirect()->back()->with('success', 'Claim updated successfully');
// }


    public function displayRecords(Request $request)
    {
        $chkval = $request->get('chkval', 2);
        $month = $request->get('v', 0);
        $page = $request->get('page', 1);
        $search = trim($request->get('search', ''));
        $department = trim($request->get('department', ''));
        $offset = 15;
        $from = ($page - 1) * $offset;

        $yearId = session('year_id');
        $connection = DB::connection(session('expense_connection'));

        $cond = ($month == 0 || $month == '') ? '1=1' : "Month = " . intval($month);

        // Employee filter — search by name or code in HRIMS, get matching EmployeeIDs
        $empFilter = '';
        if (!empty($search) || !empty($department)) {
            $empQuery = DB::connection('hrims')
                ->table('hrm_employee as e')
                ->leftJoin('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin('core_departments as d', 'g.DepartmentId', '=', 'd.id');

            if (!empty($search)) {
                $empQuery->where(function ($q) use ($search) {
                    $q->where('e.EmpCode', 'like', "%{$search}%")
                      ->orWhere('e.EmpCode_New', 'like', "%{$search}%")
                      ->orWhereRaw("CONCAT(e.Fname, ' ', COALESCE(e.Sname,''), ' ', e.Lname) LIKE ?", ["%{$search}%"]);
                });
            }

            if (!empty($department)) {
                $empQuery->where('d.department_name', $department);
            }

            $matchingIds = $empQuery->pluck('e.EmployeeID')->toArray();

            if (empty($matchingIds)) {
                return response()->json([
                    'records' => [],
                    'total' => 0,
                    'current_page' => $page,
                    'per_page' => $offset,
                    'last_page' => 1
                ]);
            }

            $empFilter = "AND EmployeeID IN (" . implode(',', $matchingIds) . ")";
        }

        if ($chkval == 2) {
            $baseCond = "YearId = {$yearId}
                    AND Status = 'Closed'
                    AND Total_Claim > 0
                    AND Approved_Amount >= 0
                    AND Approved_Date != '0000-00-00'
                    AND (Finance_Date IS NULL OR Finance_Date = '0000-00-00')
                    AND {$cond} {$empFilter}";

            $sql = "SELECT * FROM y{$yearId}_monthexpensefinal
                    WHERE {$baseCond}
                    ORDER BY Month ASC, EmployeeID ASC
                    LIMIT {$from}, {$offset}";

            $countSql = "SELECT COUNT(*) as total FROM y{$yearId}_monthexpensefinal
                        WHERE {$baseCond}";
        } else {
            $baseCond = "YearId = {$yearId}
                    AND Status = 'Closed'
                    AND Total_Claim > 0
                    AND Approved_Amount >= 0
                    AND Approved_Date != '0000-00-00'
                    AND Finance_Amount >= 0
                    AND Finance_Date != '0000-00-00'
                    AND {$cond} {$empFilter}";

            $sql = "SELECT * FROM y{$yearId}_monthexpensefinal
                    WHERE {$baseCond}
                    ORDER BY Month ASC, EmployeeID ASC
                    LIMIT {$from}, {$offset}";

            $countSql = "SELECT COUNT(*) as total FROM y{$yearId}_monthexpensefinal
                        WHERE {$baseCond}";
        }

        $records = $connection->select($sql);
        $totalResult = $connection->select($countSql);
        $total_records = $totalResult[0]->total ?? 0;

        // Query-wide totals across all matching records
        $totalsSql = "SELECT 
                        COALESCE(SUM(Total_Claim), 0) as total_claims,
                        COALESCE(SUM(Claim_Amount), 0) as total_claimed,
                        COALESCE(SUM(Verified_Amount), 0) as total_verified,
                        COALESCE(SUM(Approved_Amount), 0) as total_approved,
                        COALESCE(SUM(Finance_Amount), 0) as total_finance
                      FROM y{$yearId}_monthexpensefinal
                      WHERE {$baseCond}";
        $totalsResult = $connection->selectOne($totalsSql);

        // Fetch employee from HRIMS DB
        foreach ($records as $record) {
            $user = DB::connection('hrims')->selectOne("
                SELECT e.Fname, e.Sname, e.Lname, e.EmpCode, e.EmpCode_New,
                       g.DepartmentId, g.DesigId, g.GradeId,
                       d.department_name, dg.designation_name, gr.grade_name,
                       st.state_name as branch_name
                FROM hrm_employee e
                LEFT JOIN hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                LEFT JOIN core_departments d ON g.DepartmentId = d.id
                LEFT JOIN core_designation dg ON g.DesigId = dg.id
                LEFT JOIN core_grades gr ON g.GradeId = gr.id
                LEFT JOIN core_states st ON g.CostCenter = st.id
                WHERE e.EmployeeID = ?
            ", [$record->EmployeeID]);

            $record->userName = $user
                ? trim($user->Fname . ' ' . ($user->Sname ?? '') . ' ' . $user->Lname)
                : '';
            $record->empCode = $user->EmpCode_New ?? ($user->EmpCode ?? '');
            $record->department = $user->department_name ?? '';
            $record->designation = $user->designation_name ?? '';
            $record->grade = $user->grade_name ?? '';
            $record->branch = $user->branch_name ?? '';
        }

        return response()->json([
            'records' => $records,
            'total' => $total_records,
            'totals' => [
                'claims' => (int)($totalsResult->total_claims ?? 0),
                'claimed' => (float)($totalsResult->total_claimed ?? 0),
                'verified' => (float)($totalsResult->total_verified ?? 0),
                'approved' => (float)($totalsResult->total_approved ?? 0),
                'finance' => (float)($totalsResult->total_finance ?? 0),
            ],
            'current_page' => $page,
            'per_page' => $offset,
            'last_page' => ceil($total_records / $offset)
        ]);
    }

    public function getMonthDetails(Request $request)
    {
        $month = $request->get('month');
        $emp = $request->get('emp');
        $sts = $request->get('sts');
        $csts = $request->get('csts');

        $yearId = session('year_id');
        $connection = DB::connection(session('expense_connection'));

        // Get all claims for this employee and month (Only confirmed claims where FilledOkay = 1)
        $claims = $connection->select("
            SELECT e.*, c.ClaimName
            FROM `y{$yearId}_expenseclaims` e
            LEFT JOIN claimtype c ON e.ClaimId = c.ClaimId
            WHERE e.CrBy = ?
            AND e.ClaimMonth = ?
            AND e.ClaimYearId = ?
            AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
            AND e.FilledBy > 0
            AND e.FilledOkay = 1
            ORDER BY e.BillDate ASC, e.ExpId ASC
        ", [$emp, $month, $yearId]);

        // Get claim groups for tabs
        $claimGroups = DB::select("SELECT cgId, cgCode, cgName FROM claimgroup ORDER BY cgId");

        // Group claims by claim type group
        $groupedClaims = [];
        foreach ($claims as $claim) {
            // Get claim type group
            $claimType = DB::selectOne("SELECT cgId FROM claimtype WHERE ClaimId = ?", [$claim->ClaimId]);
            $groupId = $claimType ? $claimType->cgId : 0;

            if (!isset($groupedClaims[$groupId])) {
                $groupedClaims[$groupId] = [];
            }
            $groupedClaims[$groupId][] = $claim;
        }

        // Generate HTML
        $html = $this->generateClaimsHTML($claims, $groupedClaims, $claimGroups, $month, $emp, $sts, $yearId, $connection);

        return response()->json(['html' => $html]);
    }

    private function generateClaimsHTML($claims, $groupedClaims, $claimGroups, $month, $emp, $sts, $yearId, $connection)
    {
        // Filter out any claim where FilledOkay is not 1
        $claims = array_values(array_filter($claims, fn($c) => (int)($c->FilledOkay ?? 0) === 1));

        if (empty($claims)) {
            return '<div class="alert alert-info text-center">No claims found for this month.</div>';
        }

        $sumClaimed  = array_sum(array_column($claims, 'FilledTAmt'));
        $sumVerified = array_sum(array_column($claims, 'VerifyTAmt'));
        $sumApproved = array_sum(array_column($claims, 'ApprTAmt'));
        $sumFinance  = array_sum(array_column($claims, 'FinancedTAmt'));
        $diffSummaryBadge = '';
        if ($sumVerified < $sumClaimed) {
            $diffSummaryBadge = '<span class="badge bg-danger fs-6 px-3 py-1">Net Deducted: -₹' . number_format($sumClaimed - $sumVerified) . '</span>';
        } elseif ($sumVerified > $sumClaimed) {
            $diffSummaryBadge = '<span class="badge bg-success fs-6 px-3 py-1">Net Added: +₹' . number_format($sumVerified - $sumClaimed) . '</span>';
        } else {
            $diffSummaryBadge = '<span class="badge bg-success fs-6 px-3 py-1"><i class="ri-checkbox-circle-line me-1"></i>All Amounts Match</span>';
        }

        $html = '<div class="claim-details-container" style="padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <!-- Month Overview & Diff Banner -->
                    <div class="card mb-2 border shadow-sm" style="background:#ffffff;border-left:4px solid #15648C !important;">
                        <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3 flex-wrap" style="font-size:12px;">
                                <span class="fw-bold text-dark"><i class="ri-calendar-line text-primary me-1"></i>Month Total Summary:</span>
                                <span>Claims: <strong class="text-dark">' . count($claims) . '</strong></span>
                                <span>Claimed: <strong class="text-dark">₹' . number_format($sumClaimed) . '</strong></span>
                                <span>Verified: <strong class="text-dark">₹' . number_format($sumVerified) . '</strong></span>
                                <span>Approved: <strong class="text-primary">₹' . number_format($sumApproved) . '</strong></span>
                                ' . ($sts != 'Open' ? '<span>Finance: <strong class="text-success">₹' . number_format($sumFinance) . '</strong></span>' : '') . '
                            </div>
                            <div>' . $diffSummaryBadge . '</div>
                        </div>
                    </div>

                    <!-- Screen Color Legend / Instruction Plate -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 p-2 bg-white rounded border shadow-sm" style="font-size: 11px; border-left: 4px solid #15648C !important;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-bold text-dark"><i class="ri-information-line text-primary fs-6 align-middle"></i> Color Legend:</span>
                            <span class="d-inline-flex align-items-center gap-1">
                                <span style="width:13px;height:13px;background:#FFFFFF;border:1.5px solid #cbd5e1;border-radius:3px;display:inline-block;"></span>
                                <span>Normal (Amounts Match)</span>
                            </span>
                            <span class="d-inline-flex align-items-center gap-1">
                                <span style="width:13px;height:13px;background:#FFF9D6;border:1.5px solid #eab308;border-radius:3px;display:inline-block;"></span>
                                <span class="fw-semibold" style="color:#854d0e;">Amount Difference (Claimed / Verified / Approved / Finance)</span>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="d-inline-flex align-items-center gap-1">
                                <span class="badge bg-warning text-dark" style="font-size:9px;padding:2px 6px;">Pending</span>
                                <span class="text-muted">Awaiting Action</span>
                            </span>
                            <span class="d-inline-flex align-items-center gap-1">
                                <span class="badge bg-success" style="font-size:9px;padding:2px 6px;">Approved</span>
                                <span class="text-muted">Finance Approved</span>
                            </span>
                            <span class="d-inline-flex align-items-center gap-1">
                                <span class="badge bg-danger" style="font-size:9px;padding:2px 6px;">Rejected</span>
                                <span class="text-muted">Finance Rejected</span>
                            </span>
                        </div>
                    </div>

                    <div class="claim-tabs" style="border-bottom: 2px solid #e0e0e0; margin-bottom: 15px; padding-bottom: 5px;">
                        <button class="btn btn-sm btn-primary active-tab" onclick="showClaimGroup(this, \'all\')" style="margin-right: 5px;">All Claims</button>';

        // Add group tabs
        foreach ($claimGroups as $group) {
            if (isset($groupedClaims[$group->cgId]) && count($groupedClaims[$group->cgId]) > 0) {
                $html .= '<button class="btn btn-sm btn-outline-primary" onclick="showClaimGroup(this, \'group_' . $group->cgId . '\')" style="margin-right: 5px;">' . $group->cgCode . ' (' . count($groupedClaims[$group->cgId]) . ')</button>';
            }
        }

        $html .= '</div>
                  <div id="all-claims-view">';

        // All claims table
        $html .= $this->generateClaimsTable($claims, $sts, $yearId, $connection);

        $html .= '</div>';

        // Individual group views (hidden initially)
        foreach ($claimGroups as $group) {
            if (isset($groupedClaims[$group->cgId]) && count($groupedClaims[$group->cgId]) > 0) {
                $html .= '<div id="group_' . $group->cgId . '_view" style="display: none;">';
                $html .= $this->generateClaimsTable($groupedClaims[$group->cgId], $sts, $yearId, $connection);
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        // Add JavaScript for tabs
        $html .= '<script>
                    function showClaimGroup(btn, groupId) {
                        // Update button styles
                        document.querySelectorAll(".claim-tabs .btn").forEach(function(btn) {
                            btn.classList.remove("btn-primary");
                            btn.classList.add("btn-outline-primary");
                        });
                        btn.classList.remove("btn-outline-primary");
                        btn.classList.add("btn-primary");

                        // Show selected view
                        document.getElementById("all-claims-view").style.display = "none";
                        document.querySelectorAll("[id$=\'_view\']").forEach(function(view) {
                            if(view.id !== groupId + "_view") {
                                view.style.display = "none";
                            }
                        });

                        if(groupId === "all") {
                            document.getElementById("all-claims-view").style.display = "block";
                        } else {
                            document.getElementById(groupId + "_view").style.display = "block";
                        }
                    }
                  </script>';

        return $html;
    }


    public function getClaimDetail(Request $request)
{
    $claimId = $request->get('claim_id');
    $expid = $request->get('expid');
    $yearId = session('year_id');
    $connection = DB::connection(session('expense_connection'));

    // Fetch expense data (only if FilledOkay = 1)
    $exp = $connection->selectOne("select * from y{$yearId}_expenseclaims where ExpId = ? and FilledOkay = 1", [$expid]);

    if (!$exp) {
        return response()->json(['html' => '<div class="alert alert-danger">Claim not found or not confirmed by claimant (FilledOkay is not 1)</div>', 'uploaded_files' => []]);
    }

    // Fetch uploads
    $uploads = $connection->select("select * from y{$yearId}_claimuploads where ExpId = ?", [$expid]);

    $uploadedFiles = [];
    foreach ($uploads as $upload) {
        $uploadedFiles[] = [
            'file_url' => "https://vnragro.com/File_Bkp/xeasyfile/{$yearId}/{$exp->CrBy}/" . rawurlencode($upload->FileName),
            'file_name' => $upload->FileName
        ];
    }

    // If no uploads, check Img_1
    if (empty($uploadedFiles) && isset($exp->Img_1) && !empty($exp->Img_1)) {
        $uploadedFiles[] = [
            'file_url' => "https://vnragro.com/File_Bkp/xeasyfile/{$yearId}/{$exp->CrBy}/" . rawurlencode($exp->Img_1),
            'file_name' => $exp->Img_1
        ];
    }

    // Get claim type group
    $cg = DB::selectOne("select cgId from claimtype where ClaimId = ?", [$exp->ClaimId]);
    $cgId = $cg ? $cg->cgId : 0;

    // Get expense details (THIS IS IMPORTANT FOR THE REMARK SECTION)
    $expDlt = $connection->selectOne("select * from y{$yearId}_expenseclaimsdetails where ExpId = ?", [$expid]);

    // Get group fill data
    $expf = null;
    if ($cgId > 0) {
        $expf = $connection->selectOne("select * from y{$yearId}_g{$cgId}_expensefilldata where ExpId = ?", [$expid]);
    }

    // Map claim ID to form view name
    $claimFormMap = [
        1 => 'lodging',
        2 => 'airfare',
        3 => 'localconveyance',
        4 => 'hiredvehicle',
        5 => 'phonefax',
        6 => 'postagecourier',
        7 => '24wheeler',
        8 => 'vehiclemaint',
        9 => 'vehiclefuel',
        10 => 'rstofd',
        11 => 'fdfv',
        12 => 'jeepcampain',
        13 => 'dealermeeting',
        14 => 'railfare',
        15 => 'busfare',
        16 => 'miscellaneous',
        17 => 'meal',
        18 => 'boardingpass',
        22 => 'retailer_crop_tour',
        42 => 'team_meeting_expense',
    ];

    $formName = $claimFormMap[$exp->ClaimId] ?? 'airfare';

    // Generate the form HTML - PASS ALL VARIABLES
    $html = view('claims.forms.form_' . $formName, [
        'exp' => $exp,
        'expDlt' => $expDlt,  // ← IMPORTANT: Pass expDlt for remarks
        'expf' => $expf,
        'yearId' => $yearId,
        'expid' => $expid
    ])->render();

    return response()->json([
        'html' => $html,
        'uploaded_files' => $uploadedFiles,
        'pax_data' => $expf ? [  // ← NEW: Return PAX data separately for JavaScript
            'PaxDetails' => $expf->PaxDetails ?? '',
        ] : null
    ]);
}

    private function generateClaimsTable($claims, $sts, $yearId, $connection)
    {
        // Ensure only claims with FilledOkay == 1 are displayed
        $claims = array_values(array_filter($claims, fn($c) => (int)($c->FilledOkay ?? 0) === 1));

        if (empty($claims)) {
            return '<div class="alert alert-info">No claims in this category.</div>';
        }

        $html = '<div class="table-responsive">
                    <table class="table table-bordered table-hover" style="font-size: 12px;">
                        <thead style="background-color: #15648C; color: white;">
                            <tr>
                                <th style="width: 35px; text-align: center;">S.No.</th>
                                <th style="width: 85px; text-align: center;">Claim ID</th>
                                <th>Claim Type</th>
                                <th style="width: 85px; text-align: center;">Upload Date</th>
                                <th style="width: 85px; text-align: center;">Bill Date</th>
                                <th style="width: 80px; text-align: right;">Claimed Amt</th>
                                <th style="width: 80px; text-align: right;">Verified Amt</th>
                                <th style="width: 75px; text-align: right;">Diff</th>
                                <th style="width: 130px;">Verifier Remark</th>
                                <th style="width: 80px; text-align: right;">Approved Amt</th>
                                <th style="width: 130px;">Approver Remark</th>
                                <th style="width: 95px; text-align: right;">Finance Amt</th>
                                <th style="width: 130px;">Finance Remark</th>
                                <th style="width: 85px; text-align: center;">Claim Status</th>
                                <th style="width: 70px; text-align: center;">Detail</th>
                                <th style="width: 90px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>';

        // Calculate totals for both top and bottom rows
        $totalClaimed = array_sum(array_column($claims, 'FilledTAmt'));
        $totalVerified = array_sum(array_column($claims, 'VerifyTAmt'));
        $totalApproved = array_sum(array_column($claims, 'ApprTAmt'));
        $totalFinanced = array_sum(array_column($claims, 'FinancedTAmt'));

        $totalDiff = $totalClaimed - $totalVerified;
        $totalDiffText = '-';
        $totalDiffClass = '';
        if ($totalVerified < $totalClaimed) {
            $totalDiffText = '-' . number_format($totalClaimed - $totalVerified);
            $totalDiffClass = 'text-danger fw-bold';
        } elseif ($totalVerified > $totalClaimed) {
            $totalDiffText = '+' . number_format($totalVerified - $totalClaimed);
            $totalDiffClass = 'text-success fw-bold';
        }

        $totalRowHtml = '<tr class="fin-total-row" style="background-color: #eaf2f8; font-weight: bold; border-top: 2px solid #15648C; border-bottom: 2px solid #15648C;">
                    <td colspan="5" style="text-align: right;"><strong>Total:</strong></td>
                    <td style="text-align: right;"><strong>' . number_format($totalClaimed) . '</strong></td>
                    <td style="text-align: right;"><strong>' . number_format($totalVerified) . '</strong></td>
                    <td style="text-align: right;" class="' . $totalDiffClass . '"><strong>' . $totalDiffText . '</strong></td>
                    <td></td>
                    <td style="text-align: right;"><strong>' . number_format($totalApproved) . '</strong></td>
                    <td></td>
                    <td style="text-align: right;"><strong class="fin-total-financed">' . number_format($totalFinanced) . '</strong></td>
                    <td colspan="4"></td>
                </tr>';

        // Add top total calculation row
        $html .= $totalRowHtml;

        $i = 1;
        foreach ($claims as $claim) {
            // Claim Status display based on finance_date
            $rawFinanceDate = $claim->FinancedDate ?? $claim->Finance_Date ?? null;
            $isValidFinanceDate = false;
            if (!empty($rawFinanceDate) && \App\Support\ZeroDate::has($rawFinanceDate) && \App\Support\ZeroDate::has($rawFinanceDate)) {
                $time = strtotime($rawFinanceDate);
                if ($time !== false && $time > 0) {
                    $isValidFinanceDate = true;
                }
            }

            $finStatus = '';
            $finStatusClass = '';
            if ($claim->ClaimStatus == 'Rej_Fin') {
                $finStatus = 'Rejected';
                $finStatusClass = 'text-danger fw-bold';
            } elseif ($isValidFinanceDate) {
                $finStatus = 'Approved';
                $finStatusClass = 'text-success fw-bold';
            } else {
                $finStatus = 'Pending';
                $finStatusClass = 'text-warning fw-bold';
            }

            // Amounts for comparison
            $claimedAmt  = (float)($claim->FilledTAmt ?? 0);
            $verifiedAmt = (float)($claim->VerifyTAmt ?? 0);
            $approvedAmt = (float)($claim->ApprTAmt ?? 0);

            if ($sts == 'Open') {
                $financeAmt = (!is_null($claim->FinancedTAmt) && $claim->FinancedTAmt !== '')
                    ? $claim->FinancedTAmt
                    : ($claim->ApprTAmt ?? 0);
                if (is_numeric($financeAmt)) {
                    $financeAmt = (float)$financeAmt == (int)$financeAmt ? (int)$financeAmt : (float)$financeAmt;
                }
            } else {
                $financeAmt = (float)($claim->FinancedTAmt ?? 0);
            }
            $currFinanceAmt = (float)$financeAmt;

            // Check for amount differences across stages
            $isDiffClaimVerify = (round($claimedAmt, 2) != round($verifiedAmt, 2));
            $isDiffVerifyAppr  = (round($verifiedAmt, 2) != round($approvedAmt, 2));
            $isDiffApprFin     = (round($approvedAmt, 2) != round($currFinanceAmt, 2));
            $isDiffClaimAppr   = (round($claimedAmt, 2) != round($approvedAmt, 2));
            $isDiffClaimFin    = (round($claimedAmt, 2) != round($currFinanceAmt, 2));

            $hasAmtDiff = ($isDiffClaimVerify || $isDiffVerifyAppr || $isDiffApprFin || $isDiffClaimAppr || $isDiffClaimFin);

            // Row background styling
            $rowBgColor = '#FFFFFF';
            $rowTitle = 'Amounts match';
            $diffBadge = '';

            if ($claim->ClaimStatus == 'Rej_Fin') {
                $rowBgColor = '#FFE5E9';
                $rowTitle = 'Claim Rejected by Finance';
            } elseif ($hasAmtDiff) {
                $rowBgColor = '#FFF9D6';
                $diffDetails = [];
                if ($isDiffClaimVerify) $diffDetails[] = 'Claimed (' . number_format($claimedAmt) . ') ≠ Verified (' . number_format($verifiedAmt) . ')';
                if ($isDiffVerifyAppr)  $diffDetails[] = 'Verified (' . number_format($verifiedAmt) . ') ≠ Approved (' . number_format($approvedAmt) . ')';
                if ($isDiffApprFin)     $diffDetails[] = 'Approved (' . number_format($approvedAmt) . ') ≠ Finance (' . number_format($currFinanceAmt) . ')';
                $rowTitle = 'Amount Difference: ' . implode('; ', $diffDetails);
                $diffBadge = '<span class="badge bg-warning text-dark ms-1" style="font-size:9px;padding:1px 4px;vertical-align:middle;" title="' . htmlspecialchars($rowTitle, ENT_QUOTES, 'UTF-8') . '">Diff</span>';
            }

            // Cell highlight styles
            $claimedStyle  = ($isDiffClaimVerify || $isDiffClaimAppr) ? 'font-weight:600;color:#92400e;' : '';
            $verifiedStyle = ($isDiffClaimVerify || $isDiffVerifyAppr) ? 'font-weight:600;color:#92400e;' : '';
            $approvedStyle = ($isDiffVerifyAppr || $isDiffApprFin || $isDiffClaimAppr) ? 'font-weight:600;color:#92400e;' : '';
            $financeStyle  = ($isDiffApprFin || $isDiffClaimFin) ? 'border-color:#eab308;background:#fffde7;' : '';

            $html .= '<tr id="claim_row_' . $claim->ExpId . '" class="claim-row"
                          data-expid="' . $claim->ExpId . '"
                          data-claimed="' . $claimedAmt . '"
                          data-verified="' . $verifiedAmt . '"
                          data-approved="' . $approvedAmt . '"
                          data-finance="' . $currFinanceAmt . '"
                          style="background-color: ' . $rowBgColor . ';"
                          title="' . htmlspecialchars($rowTitle, ENT_QUOTES, 'UTF-8') . '">
                        <td style="text-align: center;">' . $i . '</td>
                        <td style="text-align: center; white-space: nowrap;">
                            <a href="javascript:void(0)" onclick="showexpdet(' . $claim->ExpId . ',' . $claim->ClaimId . ')" class="text-primary fw-bold" style="cursor: pointer; text-decoration: underline;" title="View Full Details">
                                ' . $claim->ExpId . '
                            </a>' . $diffBadge . '
                        </td>
                        <td>' . ($claim->ClaimName ?? 'N/A') . '</td>
                        <td style="text-align: center;">' . ($claim->CrDate && \App\Support\ZeroDate::has($claim->CrDate) ? date("d-m-Y", strtotime($claim->CrDate)) : '-') . '</td>
                        <td style="text-align: center;">' . ($claim->BillDate && \App\Support\ZeroDate::has($claim->BillDate) ? date("d-m-Y", strtotime($claim->BillDate)) : '-') . '</td>
                        <td style="text-align: right;' . $claimedStyle . '" title="Claimed: ₹' . number_format($claimedAmt) . '">' . number_format($claim->FilledTAmt) . '</td>
                        <td style="text-align: right;' . $verifiedStyle . '" title="Verified: ₹' . number_format($verifiedAmt) . '">' . number_format($claim->VerifyTAmt ?? 0) . '</td>';

            $rowDiffText = '-';
            $rowDiffClass = '';
            if ($verifiedAmt < $claimedAmt) {
                $rowDiffText = '-' . number_format($claimedAmt - $verifiedAmt);
                $rowDiffClass = 'text-danger fw-bold';
            } elseif ($verifiedAmt > $claimedAmt) {
                $rowDiffText = '+' . number_format($verifiedAmt - $claimedAmt);
                $rowDiffClass = 'text-success fw-bold';
            }

            $html .= '<td style="text-align: right;" class="' . $rowDiffClass . '">' . $rowDiffText . '</td>
                        <td style="font-size: 11px; color: #495057;">' . htmlspecialchars($claim->VerifyTRemark ?? '', ENT_QUOTES, 'UTF-8') . '</td>
                        <td style="text-align: right;' . $approvedStyle . '" title="Approved: ₹' . number_format($approvedAmt) . '">' . number_format($claim->ApprTAmt ?? 0) . '</td>
                        <td style="font-size: 11px; color: #495057;">' . htmlspecialchars($claim->ApprTRemark ?? '', ENT_QUOTES, 'UTF-8') . '</td>';

            if ($sts == 'Open') {
                if (is_numeric($financeAmt)) {
                    $financeAmt = (float)$financeAmt == (int)$financeAmt ? (int)$financeAmt : (float)$financeAmt;
                }

                $isAmtChangedFromAppr = (round((float)$financeAmt, 2) != round((float)$approvedAmt, 2));
                $hasRemark = !empty(trim($claim->FinancedTRemark ?? ''));
                $remarkPlaceholder = ($isAmtChangedFromAppr && !$hasRemark) ? 'Remark required!' : 'Remark';
                $remarkStyle = ($isAmtChangedFromAppr && !$hasRemark) 
                    ? 'width: 120px; font-size:11px; border-color: #eab308; background-color: #fffde7;' 
                    : 'width: 120px; font-size:11px;';

                // Finance view - editable fields
                $html .= '<td style="text-align: center;">
                            <input type="text" class="form-control form-control-sm"
                                   id="financetamt_' . $claim->ExpId . '"
                                   value="' . $financeAmt . '"
                                   data-orig="' . $financeAmt . '"
                                   data-approved="' . $approvedAmt . '"
                                   style="width: 80px; text-align: right;' . $financeStyle . '"
                                   onkeypress="return isNumberKey(event)"
                                   title="Approved: ₹' . number_format($approvedAmt) . '">
                          </td>
                          <td>
                            <textarea class="form-control form-control-sm"
                                      id="financeremark_' . $claim->ExpId . '"
                                      rows="1" style="' . $remarkStyle . '"
                                      placeholder="' . $remarkPlaceholder . '">' . htmlspecialchars($claim->FinancedTRemark ?? '', ENT_QUOTES, 'UTF-8') . '</textarea>
                          </td>
                          <td style="text-align: center;">
                            <span id="' . $claim->ExpId . '_status" class="' . $finStatusClass . '">' . $finStatus . '</span>
                          </td>
                          <td style="text-align: center;">
                            <button class="btn btn-sm btn-primary py-0 px-2" onclick="showexpdet(' . $claim->ExpId . ',' . $claim->ClaimId . ')" title="View Details">
                                Detail
                            </button>
                          </td>
                          <td style="text-align: center;">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-sm btn-success" onclick="approveClaimFinance(' . $claim->ExpId . ')" title="Approve">
                                    <i class="ri-check-line"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="rejectClaimFinance(' . $claim->ExpId . ')" title="Reject">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                          </td>';
            } else {
                // Approved view
                $html .= '<td style="text-align: right;' . $financeStyle . '">' . number_format($claim->FinancedTAmt ?? 0) . '</td>
                          <td style="font-size: 11px; color: #495057;">' . htmlspecialchars($claim->FinancedTRemark ?? '', ENT_QUOTES, 'UTF-8') . '</td>
                          <td style="text-align: center;">
                            <span id="' . $claim->ExpId . '_status" class="' . $finStatusClass . '">' . $finStatus . '</span>
                          </td>
                          <td style="text-align: center;">
                            <button class="btn btn-sm btn-primary py-0 px-2" onclick="showexpdet(' . $claim->ExpId . ',' . $claim->ClaimId . ')" title="View Details">
                                Detail
                            </button>
                          </td>
                          <td style="text-align: center; font-size: 11px; color: #6c757d;">
                            ' . ($claim->FinancedDate && \App\Support\ZeroDate::has($claim->FinancedDate) ? date("d-m-Y", strtotime($claim->FinancedDate)) : '-') . '
                          </td>';
            }

            $html .= '</tr>';
            $i++;
        }

        // Add bottom total calculation row
        $html .= $totalRowHtml;

        $html .= '</tbody>
                </table>
            </div>';

        return $html;
    }

public function closeClaimMonth(Request $request)
{
    try {
        $month = (int) $request->get('month');
        $employeeId = (int) $request->get('crby');
        $yearId = (int) session('year_id', 8);

        // Get exact logged-in employee ID (no fallback dummy IDs)
        $financedBy = session('EmployeeID') 
            ?? session('employee_id') 
            ?? session('employee_details.EmployeeID') 
            ?? auth()->user()?->employee_id;

        if (empty($financedBy)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Logged in Employee ID could not be identified. Please log in again.'
            ], 403);
        }

        $connection = DB::connection(session('expense_connection', 'expense'));

        // 1. Check submitted claims from frontend (if details table was open when clicking Finance Close)
        $claimsData = $request->get('claims');
        if (is_array($claimsData)) {
            foreach ($claimsData as $c) {
                $cExpId = (int)($c['expid'] ?? 0);
                $cFtamt = (float)($c['ftamt'] ?? 0);
                $cRemark = trim($c['remark'] ?? '');
                $cApproved = (float)($c['approved'] ?? 0);

                if (round($cFtamt, 2) != round($cApproved, 2) && empty($cRemark)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Finance Remark is required for Claim ID #{$cExpId} because Finance Amount (₹" . number_format($cFtamt) . ") differs from Approved Amount (₹" . number_format($cApproved) . "). Please enter a remark before closing."
                    ], 422);
                }
            }
        }

        // 2. Check in database: Any claim for this employee/month where FinancedTAmt differs from ApprTAmt without FinancedTRemark
        $invalidInDb = $connection->table("y{$yearId}_expenseclaims")
            ->where('CrBy', $employeeId)
            ->where('ClaimMonth', $month)
            ->where('ClaimYearId', $yearId)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Rej_Fin')
            ->where('FilledOkay', 1)
            ->whereNotNull('FinancedTAmt')
            ->whereRaw('ROUND(FinancedTAmt, 2) != ROUND(IFNULL(ApprTAmt, 0), 2)')
            ->where(function ($q) {
                $q->whereNull('FinancedTRemark')->orWhereRaw("TRIM(FinancedTRemark) = ''");
            })
            ->pluck('ExpId')
            ->toArray();

        if (!empty($invalidInDb)) {
            return response()->json([
                'status' => 'error',
                'message' => "Finance Remark is required for Claim ID(s): " . implode(', ', $invalidInDb) . " because Finance Amount differs from Approved Amount. Please provide a remark before closing."
            ], 422);
        }

        // FIN-APPR-01 (6 Nov): a month can be closed by Finance only when every live claim in it is
        // approved (or already financed/closed). Claims still Filled/Submitted/Verified/Return are listed.
        $openInMonth = $connection->table("y{$yearId}_expenseclaims")
            ->where('CrBy', $employeeId)->where('ClaimMonth', $month)->where('ClaimYearId', $yearId)
            ->where('FilledOkay', 1)
            ->whereIn('ClaimStatus', config('claim_transitions.month_close_blocking', ['Draft', 'Open', 'Filled', 'Submitted', 'Return', 'Verified']))
            ->get(['ExpId', 'ClaimStatus']);
        if ($openInMonth->isNotEmpty()) {
            $list = $openInMonth->map(fn ($r) => "#{$r->ExpId} ({$r->ClaimStatus})")->take(30)->implode(', ');

            return response()->json([
                'status' => 'error',
                'message' => "The month can't be closed: {$openInMonth->count()} claim(s) are not yet approved: {$list}. Get them approved (or deactivated) first.",
                'open_claims' => $openInMonth->pluck('ExpId'),
            ], 422);
        }

        // B-01: Finance may finance only claims in a financeable status (checked and logged)
        $finIds = is_array($claimsData) ? array_values(array_filter(array_map(fn ($c) => (int) ($c['expid'] ?? 0), $claimsData))) : [];
        if ($finIds) {
            \App\Services\Claims\ClaimTransition::guard($connection, (int) $yearId, fn ($q) => $q->whereIn('ExpId', $finIds)->where('FilledOkay', 1), 'Financed', 'finance', 'finance_close_month');
        }
        \App\Services\Claims\ClaimTransition::guard($connection, (int) $yearId, fn ($q) => $q->where('CrBy', $employeeId)->where('ClaimMonth', $month)->where('ClaimYearId', $yearId)
            ->whereIn('ClaimStatus', ['Approved'])->where('FilledOkay', 1)->when($finIds, fn ($w) => $w->whereNotIn('ExpId', $finIds)),
            'Financed', 'finance', 'finance_close_month', null, 'bulk');

        // 3. Save any modified rows from the open table
        if (is_array($claimsData)) {
            foreach ($claimsData as $c) {
                $cExpId = (int)($c['expid'] ?? 0);
                $cFtamt = (float)($c['ftamt'] ?? 0);
                $cRemark = trim($c['remark'] ?? '');
                if ($cExpId > 0) {
                    $connection->table("y{$yearId}_expenseclaims")
                        ->where('ExpId', $cExpId)
                        ->where('FilledOkay', 1)
                        ->update([
                            'FinancedTAmt' => $cFtamt,
                            'FinancedTRemark' => $cRemark ?: null,
                            'FinancedDate' => now(),
                            'FinancedBy' => $financedBy,
                            'ClaimStatus' => 'Financed',
                            'ClaimAtStep' => 6
                        ]);
                }
            }
        }

        // Update all remaining approved/verified claims for this employee and month (Only confirmed where FilledOkay = 1)
        $connection->update("
            UPDATE y{$yearId}_expenseclaims
            SET ClaimStatus = 'Financed',
                ClaimAtStep = 6,
                FinancedDate = NOW(),
                FinancedBy = ?,
                FinancedTAmt = IFNULL(ApprTAmt, FilledTAmt)
            WHERE CrBy = ?
            AND ClaimMonth = ?
            AND ClaimYearId = ?
            AND ClaimStatus = 'Approved'
            AND FilledOkay = 1
        ", [$financedBy, $employeeId, $month, $yearId]);

        // Calculate total amount from all valid/financed claims for this month (Only confirmed where FilledOkay = 1)
        $claimTotal = (float) $connection->table("y{$yearId}_expenseclaims")
            ->where('CrBy', $employeeId)
            ->where('ClaimMonth', $month)
            ->where('ClaimYearId', $yearId)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Rej_Fin')
            ->where('FilledOkay', 1)
            ->sum(DB::raw('IFNULL(FinancedTAmt, IFNULL(ApprTAmt, FilledTAmt))'));

        // Fetch existing monthexpensefinal record to ensure DocId and DocNo are generated
        $mef = $connection->table("y{$yearId}_monthexpensefinal")
            ->where('YearId', $yearId)
            ->where('Month', $month)
            ->where('EmployeeID', $employeeId)
            ->first();

        $docId = $mef?->DocId;
        $docNo = $mef?->DocNo;
        if (empty($docId) || empty($docNo)) {
            $maxDocId = (int) $connection->table("y{$yearId}_monthexpensefinal")->max('DocId');
            $docId = $maxDocId + 1;
            $docNo = str_pad($docId, 4, '0', STR_PAD_LEFT);
        }

        // Close month in monthexpensefinal with exact finance employee ID, DocId, and DocNo
        $connection->update("
            UPDATE y{$yearId}_monthexpensefinal
            SET Finance_Date = CURDATE(),
                Finance_Amount = CASE 
                    WHEN IFNULL(Approved_Amount, 0) > 0 THEN Approved_Amount 
                    WHEN ? > 0 THEN ?
                    ELSE IFNULL(Claim_Amount, 0)
                END,
                Fin_AppBy = ?,
                Fin_AppDate = CURDATE(),
                Fin_AppTime = NOW(),
                Status = 'Closed',
                DocId = COALESCE(NULLIF(DocId, 0), ?),
                DocNo = COALESCE(NULLIF(DocNo, ''), ?),
                payment_status = 'Pending',
                Approved_Date = CASE 
                    WHEN Approved_Date IS NULL OR Approved_Date = '0000-00-00' THEN CURDATE() 
                    ELSE Approved_Date 
                END,
                Approved_Amount = CASE 
                    WHEN IFNULL(Approved_Amount, 0) > 0 THEN Approved_Amount 
                    WHEN ? > 0 THEN ?
                    ELSE IFNULL(Claim_Amount, 0)
                END,
                Fin_PayAmt = NULL,
                Fin_PayOption = NULL,
                Fin_PayBy = 0
            WHERE YearId = ?
            AND Month = ?
            AND EmployeeID = ?
        ", [$claimTotal, $claimTotal, $financedBy, $docId, $docNo, $claimTotal, $claimTotal, $yearId, $month, $employeeId]);

        return response()->json([
            'status' => 'success',
            'message' => 'closed',
            'detail' => 'All claims auto-approved & month closed'
        ]);
    } catch (\App\Services\Claims\ClaimTransitionDenied $e) {
        throw $e;
    } catch (\Throwable $e) {
        \Log::error('closeClaimMonth error: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to close month: ' . $e->getMessage()
        ], 500);
    }
}

    public function approveClaimFinance(Request $request)
    {
        try {
            $expid = (int) $request->get('expid');
            $ftamt = $request->get('ftamt');
            $remark = trim($request->get('remark') ?? $request->get('financetremark') ?? '');
            $type = $request->get('t', 'A'); // A = Approve, R = Reject

            $yearId = (int) session('year_id', 8);
            $connection = DB::connection(session('expense_connection', 'expense'));
            $financedBy = session('EmployeeID') 
                ?? session('employee_id') 
                ?? session('employee_details.EmployeeID') 
                ?? auth()->user()?->employee_id;

            if (empty($financedBy)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Logged in Employee ID could not be identified. Please log in again.'
                ], 403);
            }

            if ($type == 'R') {
                if (empty($remark)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Finance Remark is required for rejecting a claim.'
                    ], 422);
                }

                // Reject
                $connection->update("
                    UPDATE `y{$yearId}_expenseclaims`
                    SET FinancedTAmt = 0,
                        FinancedTRemark = ?,
                        FinancedDate = NOW(),
                        FinancedBy = ?,
                        ClaimStatus = 'Rej_Fin',
                        ClaimAtStep = 5
                    WHERE ExpId = ?
                ", [$remark, $financedBy, $expid]);

                return response()->json(['message' => 'Rejected', 'status' => 'rejected']);
            }

            // Fetch current claim to compare approved amount
            $claim = $connection->table("y{$yearId}_expenseclaims")
                ->where('ExpId', $expid)
                ->first(['ExpId', 'ApprTAmt', 'FilledTAmt', 'ClaimStatus', 'FilledOkay']);

            if (!$claim) {
                return response()->json(['status' => 'error', 'message' => 'Claim not found.'], 404);
            }

            $approvedAmt = (float)($claim->ApprTAmt ?? 0);
            $newFinanceAmt = (float)$ftamt;

            // VALIDATION: If finance amount differs from approved amount, finance remark is mandatory!
            if (round($newFinanceAmt, 2) != round($approvedAmt, 2) && empty($remark)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Finance Remark is required because Finance Amount (₹' . number_format($newFinanceAmt) . ') differs from Approved Amount (₹' . number_format($approvedAmt) . ').'
                ], 422);
            }

            // FIN-APPR-01: finance only Approved claims (status gate)
            \App\Services\Claims\ClaimTransition::guard($connection, (int) $yearId, [(int) $expid], 'Financed', 'finance', 'finance_claim_approve');

            // Approve (only if FilledOkay = 1)
            $connection->update("
                UPDATE `y{$yearId}_expenseclaims`
                SET FinancedTAmt = ?,
                    FinancedTRemark = ?,
                    FinancedDate = NOW(),
                    FinancedBy = ?,
                    ClaimStatus = 'Financed',
                    ClaimAtStep = 6
                WHERE ExpId = ?
                AND FilledOkay = 1
            ", [$ftamt, $remark ?: null, $financedBy, $expid]);

            return response()->json(['message' => 'success', 'status' => 'financed']);
        } catch (\App\Services\Claims\ClaimTransitionDenied $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('approveClaimFinance error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function rejectClaimFinance(Request $request)
    {
        try {
            $expid = (int) $request->get('expid');
            $remark = $request->get('remark');

            $yearId = (int) session('year_id', 8);
            $connection = DB::connection(session('expense_connection', 'expense'));
            $financedBy = session('EmployeeID') 
                ?? session('employee_id') 
                ?? session('employee_details.EmployeeID') 
                ?? auth()->user()?->employee_id;

            if (empty($financedBy)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Logged in Employee ID could not be identified. Please log in again.'
                ], 403);
            }

            $connection->update("
                UPDATE `y{$yearId}_expenseclaims`
                SET FinancedTAmt = 0,
                    FinancedTRemark = ?,
                    FinancedDate = NOW(),
                    FinancedBy = ?,
                    ClaimStatus = 'Rej_Fin',
                    ClaimAtStep = 5
                WHERE ExpId = ?
            ", [$remark, $financedBy, $expid]);

            return response()->json(['message' => 'success', 'status' => 'rejected']);
        } catch (\Throwable $e) {
            \Log::error('rejectClaimFinance error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
