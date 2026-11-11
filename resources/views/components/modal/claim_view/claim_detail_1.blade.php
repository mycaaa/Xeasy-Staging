<div class="row mb-3">
    <div class="col-md-12">
        <div class="d-flex justify-content-left">
            <div class="form-check form-check-inline">
                <input class="form-check-input view-toggle" type="radio" name="view_mode" id="view_punch_form"
                    value="punch_form" checked />
                <label class="form-check-label" for="view_punch_form">Form</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input view-toggle" type="radio" name="view_mode" id="view_chat" value="chat" />
                <label class="form-check-label" for="view_chat">Chat</label>
            </div>
        </div>
    </div>
</div>

@php
    $remarkChat = collect($expense_detail->remark_chat ?? []);
    $defaultRemark = 'Lodging';

    $yearIdVal = session('year_id') ?: ($expense_detail->ClaimYearId ?? 8);
    $billDateDb = !empty($expense_detail->BillDate) && \App\Support\ZeroDate::has($expense_detail->BillDate)
        ? $expense_detail->BillDate
        : (!empty($expense_detail->punching_data->BillDate) && \App\Support\ZeroDate::has($expense_detail->punching_data->BillDate)
            ? $expense_detail->punching_data->BillDate
            : null);
    if ($billDateDb) {
        try {
            $billDateDb = \Carbon\Carbon::parse($billDateDb)->format('Y-m-d');
        } catch (\Throwable $e) {}
    }

    $claimantId = (string)($expense_detail->CrBy ?? $expense_detail->EmployeeID ?? session('employee_id'));
    $claimantCode = (string)session('emp_code');

    $paxVal = $expense_detail->punching_data->NoOfPAX ?? $expense_detail->NoOfPAX ?? '';
    $paxDetailsStr = $expense_detail->punching_data->PaxDetails ?? $expense_detail->PaxDetails ?? '';
    $paxOtherStr = $expense_detail->punching_data->PaxDetails_Other ?? $expense_detail->PaxDetails_Other ?? '';
    $paxArray = array_values(array_unique(array_filter(array_map('trim', explode(',', $paxDetailsStr)))));

    $paxDetailsMap = [];
    $paxEmployeeIds = array_values(array_unique(array_filter(array_map(function($k) {
        $trimVal = trim((string)$k);
        return is_numeric($trimVal) ? (int)$trimVal : null;
    }, $paxArray))));

    if (!empty($paxEmployeeIds)) {
        try {
            $hrimsConn = session('hrims_connection') ?: 'hrims';
            $fetchEmps = function($conn) use ($paxEmployeeIds) {
                return DB::connection($conn)->table('hrm_employee as emp')
                    ->leftJoin('hrm_employee_general as g', 'emp.EmployeeID', '=', 'g.EmployeeID')
                    ->leftJoin('core_departments as dept', 'g.DepartmentId', '=', 'dept.id')
                    ->leftJoin('core_grades as grade', 'g.GradeId', '=', 'grade.id')
                    ->whereIn('emp.EmployeeID', $paxEmployeeIds)
                    ->orderByRaw("CASE WHEN emp.EmpStatus = 'A' THEN 0 ELSE 1 END")
                    ->select(
                        'emp.EmployeeID',
                        'emp.EmpCode',
                        'emp.EmpCode_New',
                        'emp.Fname',
                        'emp.Sname',
                        'emp.Lname',
                        'emp.EmpStatus',
                        'dept.department_name as department',
                        'grade.grade_name as grade'
                    )
                    ->get();
            };

            $paxEmps = null;
            try {
                $paxEmps = $fetchEmps($hrimsConn);
            } catch (\Throwable $e) {
                if ($hrimsConn !== 'hrims') {
                    try { $paxEmps = $fetchEmps('hrims'); } catch (\Throwable $ex) {}
                }
            }

            if ($paxEmps) {
                foreach ($paxEmps as $pe) {
                    $empId = (string)$pe->EmployeeID;
                    $code = !empty($pe->EmpCode_New) ? $pe->EmpCode_New : (!empty($pe->EmpCode) ? $pe->EmpCode : $pe->EmployeeID);
                    $name = trim(($pe->Fname ?? '') . ' ' . ($pe->Sname ?? '') . ' ' . ($pe->Lname ?? ''));
                    $info = [
                        'id' => $empId,
                        'code' => $code,
                        'name' => $name,
                        'dept' => $pe->department ?? '',
                        'grade' => $pe->grade ?? ''
                    ];
                    $paxDetailsMap[$empId] = $info;
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB error, fall back gracefully
        }
    }

    $cleanPaxIds = [];
    foreach ($paxArray as $pKey) {
        $pKeyTrim = trim((string)$pKey);
        $empInfo = $paxDetailsMap[$pKeyTrim] ?? null;
        if ($empInfo && !empty($empInfo['id'])) {
            if ((int)$empInfo['id'] === (int)$claimantId) {
                continue;
            }
            $cleanPaxIds[] = (int)$empInfo['id'];
        } else {
            if (is_numeric($pKeyTrim) && (int)$pKeyTrim !== (int)$claimantId) {
                $cleanPaxIds[] = (int)$pKeyTrim;
            }
        }
    }
    $cleanPaxIds = array_values(array_unique(array_filter($cleanPaxIds)));
    $cleanPaxStr = implode(',', $cleanPaxIds);
    $isGroupLodging = !empty($expense_detail->punching_data->lodging_group) || !empty($paxVal) || !empty($cleanPaxStr) || !empty($paxOtherStr);

    $masterParticipantCategories = ['Retailer', 'Dealer', 'Distributor', 'Consultant', 'Management', 'Guest', 'Other Employee not available in the list'];
    try {
        $dbCats = \App\Models\OtherParticipantCategory::active()->get(['name']);
        if ($dbCats->count() > 0) {
            $masterParticipantCategories = $dbCats->pluck('name')->toArray();
        }
    } catch (\Throwable $e) {}

    $refHeader = request()->headers->get('referer') ?? '';
    $isVerifierView = (
        (isset($viewContext) && in_array($viewContext, ['verify', 'approve', 'finance'])) ||
        in_array(request()->input('view_context'), ['verify', 'approve', 'finance']) ||
        request()->has('is_verifier') ||
        str_contains($refHeader, 'verify') ||
        str_contains($refHeader, 'approve') ||
        str_contains($refHeader, 'fin-') ||
        str_contains($refHeader, 'finance')
    );

    $expConn = session('expense_connection') ?: 'expense';
    $hrimsConn = session('hrims_connection') ?: 'hrims';
    $currentExpId = (int)($expense_detail->ExpId ?? 0);

    // 1. Resolve Claimant Employee Record
    $claimantRecord = null;
    try {
        $claimantRecord = DB::connection($hrimsConn)->table('hrm_employee as emp')
            ->leftJoin('hrm_employee_general as g', 'emp.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_departments as dept', 'g.DepartmentId', '=', 'dept.id')
            ->leftJoin('core_grades as grade', 'g.GradeId', '=', 'grade.id')
            ->where(function($q) use ($claimantId) {
                $q->where('emp.EmployeeID', $claimantId)
                  ->orWhere('emp.EmpCode', (string)$claimantId)
                  ->orWhere('emp.EmpCode_New', (string)$claimantId);
            })
            ->orderByRaw("CASE WHEN emp.EmployeeID = " . (int)$claimantId . " THEN 0 ELSE 1 END")
            ->select(
                'emp.EmployeeID',
                'emp.EmpCode',
                'emp.EmpCode_New',
                'emp.Fname',
                'emp.Sname',
                'emp.Lname',
                'dept.department_name as department',
                'grade.grade_name as grade'
            )
            ->first();
    } catch (\Throwable $e) {}

    $claimantEmpId = $claimantRecord ? (int)$claimantRecord->EmployeeID : (int)$claimantId;
    $claimantCode = $claimantRecord ? (!empty($claimantRecord->EmpCode_New) ? $claimantRecord->EmpCode_New : $claimantRecord->EmpCode) : (string)session('emp_code', $claimantId);

    // 2. Discover related Group Lodging Claims on the same date where claimant is CrBy OR participant in PaxDetails
    $verifiedEmpIds = [$claimantEmpId];
    $paxCodesToResolve = $cleanPaxIds;

    $lodgingClaimsOnDate = collect();
    $lodgingFillMap = [];
    if (!empty($billDateDb)) {
        try {
            $lodgingClaimsOnDate = DB::connection($expConn)->table("y{$yearIdVal}_expenseclaims")
                ->where('ClaimId', 1)
                ->where('BillDate', $billDateDb)
                ->whereNotIn('ClaimStatus', ['Deactivate', 'D', 'Deleted'])
                ->get(['ExpId', 'CrBy', 'FilledTAmt', 'Amount', 'VerifyTAmt', 'VerifyDate', 'ClaimStatus', 'BillDate']);

            $lodgingExpIds = $lodgingClaimsOnDate->pluck('ExpId')->toArray();
            if (!empty($lodgingExpIds)) {
                $fillRows = DB::connection($expConn)->table("y{$yearIdVal}_g1_expensefilldata")
                    ->whereIn('ExpId', $lodgingExpIds)
                    ->get(['ExpId', 'PaxDetails', 'PaxDetails_Other']);
                foreach ($fillRows as $fr) {
                    $lodgingFillMap[$fr->ExpId] = $fr;
                }
            }

            foreach ($lodgingClaimsOnDate as $lc) {
                $rawPax = $lodgingFillMap[$lc->ExpId]->PaxDetails ?? '';
                $paxCodes = array_values(array_filter(array_map('trim', explode(',', $rawPax))));

                $isClaimantInLodging = ((int)$lc->CrBy === $claimantEmpId)
                    || in_array((string)$claimantEmpId, $paxCodes);

                if ($isClaimantInLodging) {
                    $verifiedEmpIds[] = (int)$lc->CrBy;
                    foreach ($paxCodes as $pc) {
                        $paxCodesToResolve[] = $pc;
                    }
                }
            }
        } catch (\Throwable $e) {}
    }

    // Resolve any participant codes to EmployeeIDs
    if (!empty($paxCodesToResolve)) {
        try {
            $paxEmps = DB::connection($hrimsConn)->table('hrm_employee')
                ->whereIn('EmployeeID', $paxCodesToResolve)
                ->pluck('EmployeeID')
                ->toArray();
            foreach ($paxEmps as $peId) {
                $verifiedEmpIds[] = (int)$peId;
            }
        } catch (\Throwable $e) {}
    }

    $verifiedEmpIds = array_values(array_unique(array_filter($verifiedEmpIds)));

    // 3. Fetch HR details (Department & Grade) for all verified employees
    $allEmps = collect();
    if (!empty($verifiedEmpIds)) {
        try {
            $allEmps = DB::connection($hrimsConn)->table('hrm_employee as emp')
                ->leftJoin('hrm_employee_general as g', 'emp.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin('core_departments as dept', 'g.DepartmentId', '=', 'dept.id')
                ->leftJoin('core_grades as grade', 'g.GradeId', '=', 'grade.id')
                ->whereIn('emp.EmployeeID', $verifiedEmpIds)
                ->orderByRaw("CASE WHEN emp.EmployeeID = {$claimantEmpId} THEN 0 ELSE 1 END")
                ->select(
                    'emp.EmployeeID',
                    'emp.EmpCode',
                    'emp.EmpCode_New',
                    'emp.Fname',
                    'emp.Sname',
                    'emp.Lname',
                    'dept.department_name as department',
                    'grade.grade_name as grade'
                )
                ->get();
        } catch (\Throwable $e) {}
    }

    // 4. Build verifier participant rows
    $verifierParticipantRows = [];
    $parseCleanAmount = function($val) {
        if (empty($val)) return 0;
        if (is_numeric($val)) return (float)$val;
        $str = preg_replace('/rs\.?/i', '', (string)$val);
        $cleaned = ltrim(preg_replace('/[^0-9.]/', '', $str), '.');
        $parts = explode('.', $cleaned);
        if (count($parts) > 2) {
            $dec = array_pop($parts);
            $cleaned = implode('', $parts) . '.' . $dec;
        }
        return is_numeric($cleaned) ? (float)$cleaned : 0;
    };

    foreach ($allEmps as $emp) {
        $eId = (int)$emp->EmployeeID;
        $eCode = !empty($emp->EmpCode_New) ? $emp->EmpCode_New : $emp->EmpCode;
        $eName = trim(($emp->Fname ?? '') . ' ' . ($emp->Sname ?? '') . ' ' . ($emp->Lname ?? ''));
        $isClaimant = ($eId === $claimantEmpId);

        // 1. Eligibility
        $eligStr = '₹0';
        try {
            $targetComId = (int)(session('company_id') ?: 1);
            $eligRows = DB::connection($hrimsConn)->table('hrm_employee_eligibility')
                ->where('EmployeeID', $eId)
                ->get();

            if ($eligRows->isNotEmpty()) {
                $eligRow = $eligRows->sortByDesc(function($r) use ($targetComId, $parseCleanAmount) {
                    $score = 0;
                    if ((int)($r->CompanyID ?? 0) === $targetComId) $score += 100000;
                    if (($r->Status ?? '') === 'A') $score += 50000;
                    $catA = $parseCleanAmount($r->Lodging_CategoryA ?? 0);
                    $catB = $parseCleanAmount($r->Lodging_CategoryB ?? 0);
                    $catC = $parseCleanAmount($r->Lodging_CategoryC ?? 0);
                    $score += max($catA, $catB, $catC);
                    return $score;
                })->first();

                if ($eligRow) {
                    $valA = $parseCleanAmount($eligRow->Lodging_CategoryA ?? '');
                    $valB = $parseCleanAmount($eligRow->Lodging_CategoryB ?? '');
                    $valC = $parseCleanAmount($eligRow->Lodging_CategoryC ?? '');

                    $catParts = [];
                    if ($valA > 0) $catParts[] = 'Cat A: ₹' . number_format($valA, 0);
                    if ($valB > 0) $catParts[] = 'Cat B: ₹' . number_format($valB, 0);
                    if ($valC > 0) $catParts[] = 'Cat C: ₹' . number_format($valC, 0);

                    if (!empty($catParts)) {
                        if ($valA > 0 && $valA == $valB && $valB == $valC) {
                            $eligStr = '₹' . number_format($valA, 0);
                        } else {
                            $eligStr = implode(' | ', $catParts);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 2. Lodging Claimed & Verified on Same Date
        $lodgingAmt = 0;
        $lodgingVerifyAmt = 0;
        $lodgingBillDateStr = '';
        $lodgingVerifyDateStr = '';
        $lodgingClaimsList = [];
        $latestClaimStatus = '';

        if (!empty($billDateDb)) {
            try {
                $sameTypeClaims = DB::connection($expConn)->table("y{$yearIdVal}_expenseclaims")
                    ->where('CrBy', $eId)
                    ->where('ClaimId', 1)
                    ->where('BillDate', $billDateDb)
                    ->where('ExpId', '!=', $currentExpId)
                    ->whereNotIn('ClaimStatus', ['Deactivate', 'D', 'Deleted'])
                    ->get();

                foreach ($sameTypeClaims as $stc) {
                    $amt = (float)($stc->FilledTAmt ?? $stc->Amount ?? $stc->FilledAmt ?? $stc->VerifyTAmt ?? $stc->ApprTAmt ?? $stc->FinancedTAmt ?? $stc->ReqTAmt ?? 0);
                    $lodgingAmt += $amt;
                    if (!empty($stc->BillDate) && empty($lodgingBillDateStr)) {
                        $lodgingBillDateStr = date('d-m-Y', strtotime($stc->BillDate));
                    }

                    // Verified Amount ONLY if status is verified/approved/paid AND VerifyDate is valid
                    $stcStatNorm = strtolower(trim((string)($stc->ClaimStatus ?? '')));
                    $isStcVerified = in_array($stcStatNorm, ['verified', 'approved', 'financed', 'paid', 'closed', 'v', 'a', 'f', 'p']);
                    $hasValidVerifyDate = (!empty($stc->VerifyDate) && \App\Support\ZeroDate::has($stc->VerifyDate) && \App\Support\ZeroDate::has($stc->VerifyDate));
                    $vAmt = 0;
                    if ($isStcVerified && $hasValidVerifyDate) {
                        $vAmt = isset($stc->VerifyTAmt) && $stc->VerifyTAmt !== null ? (float)$stc->VerifyTAmt : (float)$amt;
                        $lodgingVerifyAmt += $vAmt;
                        if (empty($lodgingVerifyDateStr)) {
                            $lodgingVerifyDateStr = date('d-m-Y', strtotime($stc->VerifyDate));
                        }
                    }

                    if (!empty($stc->ClaimStatus)) {
                        $latestClaimStatus = trim((string)$stc->ClaimStatus);
                    }
                    $lodgingClaimsList[] = [
                        'exp_id' => $stc->ExpId,
                        'claim_id' => 1,
                        'claim_name' => 'Lodging',
                        'amount' => $amt,
                        'verify_amount' => $vAmt,
                        'date' => $stc->BillDate,
                        'verify_date' => ($isStcVerified && $hasValidVerifyDate) ? $stc->VerifyDate : '',
                        'status' => $stc->ClaimStatus ?? 'Claimed'
                    ];
                }
            } catch (\Throwable $e) {}
        }

        $lodgingClaimedStr = '₹0';
        if ($lodgingAmt > 0) {
            $lodgingClaimedStr = '₹' . number_format($lodgingAmt, 0) . (!empty($lodgingBillDateStr) ? ' <small style="font-size: 10px; font-weight: normal; opacity: 0.9;">(' . $lodgingBillDateStr . ')</small>' : '');
        }

        $lodgingVerifiedStr = '—';
        if ($lodgingVerifyAmt > 0 && !empty($lodgingVerifyDateStr)) {
            $lodgingVerifiedStr = '₹' . number_format($lodgingVerifyAmt, 0) . ' <small style="font-size: 10px; font-weight: normal; opacity: 0.9;">(' . $lodgingVerifyDateStr . ')</small>';
        }

        // 3. Claim Status Mapping: strictly from y{year_id}_expenseclaims ClaimStatus column
        if ($isClaimant && empty($latestClaimStatus)) {
            $latestClaimStatus = trim((string)($expense_detail->ClaimStatus ?? 'Draft'));
        }

        $normClaimStatus = strtolower($latestClaimStatus);

        if (in_array($normClaimStatus, ['paid', 'financed', 'closed', 'p', 'f'])) {
            $displayStatus = 'Paid';
            $badgeColor = 'success';
        } elseif (in_array($normClaimStatus, ['approved', 'a'])) {
            $displayStatus = 'Approved';
            $badgeColor = 'primary';
        } elseif (in_array($normClaimStatus, ['verified', 'v'])) {
            $displayStatus = 'Verified';
            $badgeColor = 'info text-dark';
        } elseif (in_array($normClaimStatus, ['filled', 'open'])) {
            $displayStatus = ($normClaimStatus === 'open') ? 'Open' : 'Filled';
            $badgeColor = 'warning text-dark';
        } elseif (in_array($normClaimStatus, ['draft', 'submitted', 'd', 's']) || empty($normClaimStatus)) {
            $displayStatus = 'Draft';
            $badgeColor = 'secondary';
        } else {
            $displayStatus = ucfirst($latestClaimStatus);
            $badgeColor = 'secondary';
        }

        $verifierParticipantRows[] = [
            'emp_id' => $eId,
            'code' => $eCode,
            'name' => $eName,
            'dept' => $emp->department ?? '',
            'grade' => $emp->grade ?? '',
            'is_claimant' => $isClaimant,
            'eligibility' => $eligStr,
            'same_date_claimed' => $lodgingClaimedStr,
            'same_date_claimed_amt' => $lodgingAmt,
            'same_date_verified' => $lodgingVerifiedStr,
            'same_date_verified_amt' => $lodgingVerifyAmt,
            'lodging_claims_list' => $lodgingClaimsList,
            'status' => $displayStatus,
            'status_badge' => $badgeColor,
        ];
    }
@endphp

<div id="punch-form-section">
    <div class="row modal-ncgid" data-modal-ncgid="{{ $cgId }}">
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Billing Person <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="billing_person" name="billing_person"
                value="{{ $expense_detail->punching_data->BillingName ?? ($employee_detail['employee_detail']['full_name'] ?? '') }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Billing Address</small>
            <input type="text" class="form-control" id="billing_address" name="billing_address"
                value="{{ $expense_detail->punching_data->BillingAddress ?? '' }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">City - Category <span class="text-danger">*</span></small>
            <select class="form-control" id="city_category_select" name="city_category_select">
                <option value="">Select City</option>
                <option value="0">Other</option>
            </select>
            <input type="hidden" class="form-control" id="city_category" name="city_category"
                value="{{ $expense_detail->punching_data->CityCategory ?? '' }}" />
            <div id="other_city_div" style="display:none;" class="mt-2">
                <input type="text" class="form-control" id="other_city_name" name="other_city_name"
                    value="{{ ($expense_detail->punching_data->ccid ?? '') == '' && ($expense_detail->punching_data->CityName ?? '') ? $expense_detail->punching_data->CityName : '' }}"
                    placeholder="Enter City Name" />
            </div>
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Hotel Name <span class="text-danger">*</span></small>
            <select class="form-control" id="hotel_name_select" name="hotel_name_select">
                <option value="">Select Hotel</option>
                <option value="0">Other</option>
            </select>
            <input type="hidden" id="hotel_name" name="hotel_name"
                value="{{ $expense_detail->punching_data->HotelName ?? '' }}" />
            <div id="other_hotel_div" style="display:none;" class="mt-2">
                <input type="text" class="form-control" id="other_hotel_name" name="other_hotel_name"
                    placeholder="Enter Hotel Name" aria-label="Enter Hotel Name" />
            </div>
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Hotel Contact</small>
            <input type="text" class="form-control" id="hotel_contact" name="hotel_contact"
                value="{{ $expense_detail->punching_data->HotelContact ?? '' }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Hotel Email</small>
            <input type="email" class="form-control" id="hotel_email" name="hotel_email"
                value="{{ $expense_detail->punching_data->HotelEmail ?? '' }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Hotel Address</small>
            <input type="text" class="form-control" id="hotel_address" name="hotel_address"
                value="{{ $expense_detail->punching_data->HotelAddress ?? '' }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Meal Plan</small>
            <select class="form-control" id="meal_plan" name="meal_plan">
                <option value="AP" {{ (($expense_detail->punching_data->Plan ?? '') == 'AP') ? 'selected' : '' }}>American
                    Plan</option>
                <option value="MAP" {{ (($expense_detail->punching_data->Plan ?? '') == 'MAP') ? 'selected' : '' }}>
                    Modified American Plan</option>
                <option value="EP" {{ (($expense_detail->punching_data->Plan ?? '') == 'EP') ? 'selected' : '' }}>European
                    Plan</option>
                <option value="CP" {{ (($expense_detail->punching_data->Plan ?? '') == 'CP') ? 'selected' : '' }}>
                    Continental Plan</option>
            </select>
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Bill Date <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="bill_date" name="bill_date" value="{{
    \App\Helpers\CommonHelper::toDisplayFormat(
        (
            !empty($expense_detail->punching_data->BillDate) &&
            \App\Support\ZeroDate::has($expense_detail->punching_data->BillDate) &&
            strtotime($expense_detail->punching_data->BillDate) &&
            date('Y', strtotime($expense_detail->punching_data->BillDate)) >= 2000
        )
        ? $expense_detail->punching_data->BillDate
        : (
            !empty($expense_detail->BillDate) &&
            \App\Support\ZeroDate::has($expense_detail->BillDate) &&
            strtotime($expense_detail->BillDate) &&
            date('Y', strtotime($expense_detail->BillDate)) >= 2000
            ? $expense_detail->BillDate
            : ''
        )
    )
}}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Bill Number</small>
            <input type="text" class="form-control" id="bill_no" name="bill_no"
                value="{{ $expense_detail->punching_data->BillNo ?? '' }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Arrival Date / Time <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="arrival_datetime" name="arrival_datetime"
                value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->Arrival ?? '') }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Departure Date / Time <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="departure_datetime" name="departure_datetime"
                value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->Departure ?? '') }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Duration (Days)</small>
            <input type="text" class="form-control" id="stay_duration" name="stay_duration"
                value="{{ $expense_detail->punching_data->StayDuration ?? '' }}" readonly />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">GST / Tax Rate (%)</small>
            <input type="number" step="0.01" class="form-control" id="gst_rate" name="gst_rate"
                value="{{ $expense_detail->punching_data->GST ?? '' }}" />
        </div>
        @php
            $gstinVal = $expense_detail->punching_data->GSTIN ?? '';
            $gstinChk = \App\Support\Gstin::check($gstinVal);
        @endphp
        <div class="col-md-4 mb-1">
            {{-- GST-01: vendor GSTIN from the bill, checked offline (format, state, check digit) --}}
            <small class="form-text text-muted">Hotel / Vendor GSTIN
                @if ($gstinChk['status'] === 'valid')
                    <span class="badge bg-success-subtle text-success">Valid · {{ $gstinChk['state'] }}</span>
                @elseif ($gstinChk['status'] !== 'missing')
                    <span class="badge bg-danger-subtle text-danger">Invalid</span>
                @endif
            </small>
            <input type="text" maxlength="15" class="form-control text-uppercase" id="gstin" name="gstin" placeholder="15-character GSTIN, if printed on the bill" aria-label="15-character GSTIN, if printed on the bill"
                value="{{ $gstinVal }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Billing Instruction</small>
            <select class="form-control" id="billing_instruction" name="billing_instruction">
                <option value="Direct" {{ (($expense_detail->punching_data->BillingInstruction ?? '') == 'Direct') ? 'selected' : '' }}>Direct</option>
                <option value="Bill to Company" {{ (($expense_detail->punching_data->BillingInstruction ?? '') == 'Bill to Company') ? 'selected' : '' }}>Bill to Company</option>
            </select>
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Payment Mode <span class="text-danger">*</span></small>
            <select class="form-control" id="payment_mode" name="payment_mode">
                <option value="">Select</option>
                <option value="Online" {{ (($expense_detail->punching_data->Mode ?? '') == 'Online') ? 'selected' : '' }}>
                    Online</option>
                <option value="Cash" {{ (($expense_detail->punching_data->Mode ?? '') == 'Cash') ? 'selected' : '' }}>Cash
                </option>
            </select>
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Total Amount (₹) <span class="text-danger">*</span></small>
            <input type="number" step="0.01" class="form-control" id="totalAmount" name="totalAmount"
                value="{{ $expense_detail->FilledTAmt ?? '' }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Filled Amount (₹)</small>
            <input type="text" class="form-control" readonly
                value="{{ $expense_detail->punching_data->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Punching Remark</small>
            <input type="text" class="form-control" id="filledRemark" name="filledRemark"
                value="{{ trim($expense_detail->Remark ?? '') !== '' ? $expense_detail->Remark : $defaultRemark }}" />
        </div>


        @if(empty($isVerifierView))
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Pax Details</small>
            <select class="form-control select2-multiple" id="pax_details" name="pax_details[]" multiple>
                @php
                    $selectedPax = !empty($expense_detail->punching_data->PaxDetails)
                        ? explode(',', $expense_detail->punching_data->PaxDetails)
                        : [];
                @endphp
                @foreach($employees ?? [] as $emp)
                    <option value="{{ $emp->EmployeeID }}" {{ in_array($emp->EmployeeID, $selectedPax) ? 'selected' : '' }}>
                        {{ $emp->full_name }} - {{ $emp->EmpCode }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif


        @if(empty($isVerifierView))
        <div class="col-12 mb-3 mt-2">
            <div class="card border-primary shadow-sm" id="modalGroupLodgingSection">
                <div class="card-header bg-primary-subtle text-primary fw-bold d-flex justify-content-between align-items-center py-2">
                    <span><i class="ri-hotel-line me-1"></i> Group Lodging Details</span>
                    <span class="badge bg-primary" id="modalLodgingParticipantCount">Participants: {{ !empty($paxVal) ? $paxVal : (count(array_filter(explode(',', $paxDetailsStr)))) }}</span>
                </div>
                <div class="card-body p-3">
                    <div class="row mb-3">
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="modalGroupLodgingCheckbox" name="lodging_group" value="true" {{ $isGroupLodging ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold text-primary ms-2" for="modalGroupLodgingCheckbox">
                                    Enable Group Lodging Claim
                                </label>
                            </div>
                        </div>
                    </div>
                    <div id="modalGroupLodgingFields" style="{{ $isGroupLodging ? 'display:block;' : 'display:none;' }}">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold fs-12">PAX No. <span class="text-danger lodging-pax-asterisk" style="display: {{ $isGroupLodging ? 'inline' : 'none' }};">*</span></label>
                                <input type="number" name="PaxDetails" id="modalLodgingPaxInput" class="form-control form-control-sm" min="1" placeholder="Enter PAX" aria-label="Enter PAX" value="{{ $paxVal }}" {{ $isGroupLodging ? 'required' : '' }}>
                                <small class="text-muted d-block mt-1" style="font-size: 11px;"><i class="ri-information-line text-info me-1"></i>Enter the details excluding yourself</small>
                            </div>
                        </div>

                        <div id="modalGroupLodgingParticipants" class="mb-3">
                            <label class="form-label fw-semibold small text-primary mb-1 fs-12">
                                <i class="ri-user-search-line me-1"></i> Select Employee Participants <span class="text-muted fw-normal ms-1" style="font-size: 11px;">(Enter the details excluding yourself)</span>
                            </label>
                            <div class="row mb-2">
                                <div class="col-md-8">
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="modalLodgingEmployeeSearch" class="form-control" placeholder="Search by Employee Code, Name, or Grade" aria-label="Search by Employee Code, Name, or Grade">
                                        <button class="btn btn-outline-primary" type="button" id="modalLodgingSearchBtn">
                                            <i class="ri-search-line"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="modalLodgingSearchResults" class="list-group mb-2" style="max-height:160px;overflow-y:auto;"></div>
                            <div id="modalLodgingSelectedList" class="mb-2">
                                @if(!empty($cleanPaxIds))
                                    @foreach($cleanPaxIds as $pId)
                                        @php
                                            $pIdTrim = trim((string)$pId);
                                            $empInfo = $paxDetailsMap[$pIdTrim] 
                                                ?? $paxDetailsMap[ltrim($pIdTrim, '0')] 
                                                ?? $paxDetailsMap[(string)((int)$pIdTrim)] 
                                                ?? null;

                                            $displayName = $empInfo['name'] ?? '';
                                            $displayCode = $empInfo['code'] ?? $pIdTrim;
                                            $deptStr = !empty($empInfo['dept']) ? 'Dept: ' . $empInfo['dept'] : '';
                                            $gradeStr = !empty($empInfo['grade']) ? 'Grade: ' . $empInfo['grade'] : '';
                                            $meta = array_filter([$deptStr, $gradeStr]);
                                            $metaStr = !empty($meta) ? ' <small style="font-size:11px;opacity:0.85;">(' . e(implode(' - ', $meta)) . ')</small>' : '';
                                        @endphp
                                        <span class="badge bg-primary me-1 mb-1 p-2">
                                            @if(!empty($displayName))
                                                {{ $displayName }} [{{ $displayCode }}]{!! $metaStr !!}
                                            @else
                                                Employee ({{ $displayCode }})
                                            @endif
                                            <i class="ri-close-line modal-lodging-remove-emp ms-1" data-id="{{ $pIdTrim }}" data-code="{{ $displayCode }}" style="cursor:pointer;"></i>
                                        </span>
                                    @endforeach
                                @endif
                            </div>
                            <input type="hidden" name="lodging_participants" id="modalLodgingParticipantsInput" value="{{ $cleanPaxStr }}">
                        </div>

                        <!-- 2. Other Participants (Sequence: Category -> No. of Other Pax -> Add Other Participant) -->
                        <div class="card border border-dashed p-3 mb-2 bg-light">
                            <div class="mb-2">
                                <label class="form-label fw-bold mb-0 text-dark small fs-12">
                                    <i class="ri-user-add-line text-primary me-1"></i> Other Participants (Non-Employee / External Categories)
                                </label>
                                <div class="text-muted" style="font-size: 11px;">Select category and enter headcount to add external participants (Enter the details excluding yourself).</div>
                            </div>
                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold mb-1">Category <span class="text-danger">*</span></label>
                                    <select id="modalNewOtherLodgingCategory" class="form-select form-select-sm">
                                        <option value="">-- Select Category --</option>
                                        @foreach($masterParticipantCategories as $mCat)
                                            <option value="{{ $mCat }}">{{ $mCat }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @php
                                    $initialLodgingOtherCount = !empty($parsedOtherList) ? count($parsedOtherList) : 1;
                                @endphp
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">No. of Other Pax <span class="text-danger">*</span></label>
                                    <input type="number" id="modalNewOtherLodgingPaxCount" class="form-control form-control-sm" min="1" value="{{ max(1, $initialLodgingOtherCount) }}" placeholder="Pax Count" aria-label="Pax Count">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-sm btn-primary w-100" id="addModalOtherLodgingParticipantBtn">
                                        <i class="ri-add-line me-1"></i> Add Other Participant
                                    </button>
                                </div>
                            </div>
                            <div id="modalOtherLodgingParticipantsContainer">
                                @if(!empty($paxOtherStr))
                                    @php
                                        $parsedOtherList = \App\Helpers\CommonHelper::parseOtherParticipants($paxOtherStr);
                                    @endphp
                                    @foreach($parsedOtherList as $oItem)
                                        @php
                                            $cat = trim($oItem['category'] ?? '');
                                            $cntName = trim($oItem['name'] ?? '');
                                            $details = trim($oItem['details'] ?? '');
                                        @endphp
                                        @if(!empty($cntName))
                                            <div class="row g-2 align-items-center mb-2 modal-other-lodging-row">
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control form-control-sm modal-other-lodging-name" placeholder="Participant Name *" aria-label="Participant Name *" value="{{ $cntName }}" readonly style="background-color: #f8f9fa;">
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-select form-select-sm modal-other-lodging-cat" disabled style="background-color: #f8f9fa;">
                                                        <option value="">-- Select Role / Category * --</option>
                                                        @foreach($masterParticipantCategories as $mCat)
                                                            <option value="{{ $mCat }}" {{ strtolower($cat) === strtolower($mCat) ? 'selected' : '' }}>{{ $mCat }}</option>
                                                        @endforeach
                                                        @if(!in_array($cat, $masterParticipantCategories) && !empty($cat))
                                                            <option value="{{ $cat }}" selected>{{ $cat }}</option>
                                                        @endif
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" class="form-control form-control-sm modal-other-lodging-details" placeholder="Org / Mobile / Remarks (Optional)" aria-label="Org / Mobile / Remarks (Optional)" value="{{ $details }}" readonly style="background-color: #f8f9fa;">
                                                </div>
                                                <div class="col-md-2 text-end d-flex justify-content-end align-items-center">
                                                    <button type="button" class="btn btn-outline-primary btn-sm edit-modal-other-lodging-row me-1" title="Edit">
                                                        <i class="ri-edit-line"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm remove-modal-other-lodging-btn" title="Remove">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                            <input type="hidden" name="lodging_other_participants" id="modalLodgingOtherParticipants" value="{{ $paxOtherStr }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
            {{-- Preserve existing PAX details during Verifier/Finance view save --}}
            <input type="hidden" name="lodging_participants" value="{{ $cleanPaxStr ?? $paxDetailsStr }}">
            <input type="hidden" name="lodging_other_participants" value="{{ $paxOtherStr }}">
            <input type="hidden" name="PaxDetails" value="{{ $paxVal }}">
        @endif
    </div>
</div> {{-- End of #punch-form-section --}}

<!-- Verifier / Finance Participant Summary Table (Standalone Full-Width Div Outside Form) -->
@if(!empty($isVerifierView))
<div id="participant-verification-summary-section" class="w-100 my-3 clearfix" style="width: 100% !important; clear: both; display: block;">
    <div class="card border border-info shadow-sm mb-2 w-100" style="width: 100% !important;">
        <div class="card-header bg-info-subtle text-info fw-bold d-flex justify-content-between align-items-center py-2 px-3" style="font-size: 12px; width: 100%;">
            <span><i class="ri-user-shared-line me-1"></i> Participant Verification Summary (Verifier &amp; Finance View)</span>
            <span class="badge bg-info text-white fw-bold">{{ count($verifierParticipantRows) }} Employee(s)</span>
        </div>
        <div class="card-body p-0 w-100" style="width: 100% !important;">
            <div class="table-responsive w-100" style="width: 100% !important; overflow-x: auto;">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-center w-100" style="font-size: 12px; width: 100% !important; table-layout: auto;">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-start ps-3">Employee</th>
                            <th>Lodging Eligibility</th>
                            <th>Lodging Claimed</th>
                            <th>Lodging Verified</th>
                            <th>Claim Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($verifierParticipantRows as $vRow)
                            <tr>
                                <td class="text-start ps-3 fw-semibold">
                                    <div>
                                        {{ $vRow['name'] }} <small class="text-muted">[{{ $vRow['code'] }}]</small>
                                        @if($vRow['is_claimant'])
                                            <span class="badge bg-primary ms-1" style="font-size:10px;">Claimant</span>
                                        @endif
                                    </div>
                                    @if(!empty($vRow['dept']) || !empty($vRow['grade']))
                                        <small class="text-muted d-block" style="font-size:11px; font-weight:normal;">
                                            @if(!empty($vRow['dept'])) Dept: {{ $vRow['dept'] }} @endif
                                            @if(!empty($vRow['dept']) && !empty($vRow['grade'])) &bull; @endif
                                            @if(!empty($vRow['grade'])) Grade: {{ $vRow['grade'] }} @endif
                                        </small>
                                    @endif
                                </td>
                                <td class="fw-bold text-success">{{ $vRow['eligibility'] }}</td>
                                <td>
                                    @if(($vRow['same_date_claimed_amt'] ?? 0) > 0)
                                        <span class="badge bg-danger view-same-day-claims-btn"
                                            data-emp-name="{{ $vRow['name'] }}"
                                            data-emp-code="{{ $vRow['code'] }}"
                                            data-bill-date="{{ $billDateDb }}"
                                            data-category="Lodging"
                                            data-claims='@json($vRow["lodging_claims_list"] ?? [])'
                                            style="cursor:pointer;"
                                            title="Click to view itemized claim details">
                                            {!! $vRow['same_date_claimed'] !!} <i class="ri-eye-line ms-1"></i>
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark">₹0</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($vRow['same_date_verified_amt'] ?? 0) > 0 && !empty($vRow['same_date_verified']) && $vRow['same_date_verified'] !== '—')
                                        <span class="badge bg-success-subtle text-success border border-success fw-bold">
                                            {!! $vRow['same_date_verified'] !!}
                                        </span>
                                    @else
                                        <span class="text-muted fw-semibold">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $vRow['status_badge'] ?? 'secondary' }} fw-bold">
                                        {{ $vRow['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

<x-modal.claim_view.remark_chat :remarks="$remarkChat" />

<input type="hidden" id="employee_id" value="{{ $expense_detail->CrBy ?? '' }}" />
<input type="hidden" id="expfid" value="{{ $expense_detail->punching_data->did ?? '' }}" />

<script>
    $(document).ready(function () {
        // Initialize flatpickr for dates
        flatpickr("#bill_date", {
            dateFormat: "d-m-Y",
            maxDate: "today",
        });

        flatpickr("#arrival_datetime, #departure_datetime", {
            enableTime: true,
            dateFormat: "d-m-Y H:i",
            maxDate: "today",
            time_24hr: true,
            onChange: calculateDuration
        });

        // Calculate duration between arrival and departure
        function calculateDuration() {
            const arrivalVal = $("#arrival_datetime").val();
            const departureVal = $("#departure_datetime").val();

            if (!arrivalVal || !departureVal) {
                $("#stay_duration").val("");
                return;
            }

            function parseDMYHI(str) {
                const [datePart, timePart] = str.split(' ');
                if (!datePart) return null;
                const [d, m, y] = datePart.split('-');
                const [h, i] = (timePart || '00:00').split(':');
                return new Date(`${y}-${m}-${d}T${h}:${i}:00`);
            }

            const start = parseDMYHI(arrivalVal);
            const end = parseDMYHI(departureVal);

            if (!start || !end || isNaN(start) || isNaN(end) || end <= start) {
                $("#stay_duration").val("");
                return;
            }

            const diffDays = (end - start) / (1000 * 60 * 60 * 24);
            $("#stay_duration").val(diffDays.toFixed(2));
        }

        // Initialize Select2 for multiple select
        $('.select2-multiple').select2({
            placeholder: 'Select Employees',
            allowClear: true
        });

        $('#arrival_datetime, #departure_datetime').on('change', calculateDuration);

        const prefilledCity = {
            ccid: @json($expense_detail->punching_data->ccid ?? ''),
            cityName: @json($expense_detail->punching_data->CityName ?? ''),
            cityCategory: @json($expense_detail->punching_data->CityCategory ?? ''),
        };
        const prefilledHotel = {
            name: @json($expense_detail->punching_data->HotelName ?? ''),
        };

        // Load cities via AJAX
        $.getJSON('/city-list', function (response) {
            const cities = response.data || [];
            const $sel = $('#city_category_select');
            $sel.empty().append('<option value="">Select City</option>');
            let matchFound = false;

            cities.forEach(function (c) {
                const isSelected = (c.ccid == prefilledCity.ccid && prefilledCity.ccid) ? 'selected' : '';
                if (isSelected) matchFound = true;
                $sel.append(`<option value="${c.ccid}" data-category="${c.city_category}" ${isSelected}>${c.city_name} - ${c.city_category}</option>`);
            });
            $sel.append('<option value="0">Other</option>');

            $sel.select2({ allowClear: true, dropdownParent: $('#claimDetailModal'), placeholder: 'Select City', minimumInputLength: 0 });

            if (!matchFound && prefilledCity.cityName) {
                $sel.val('0').trigger('change.select2');
                $('#other_city_div').show();
                $('#other_city_name').val(prefilledCity.cityName);
            } else if (matchFound && prefilledCity.ccid) {
                $('#city_category').val(prefilledCity.cityCategory);
            }
        });

        $('#city_category_select').on('change', function () {
            const val = $(this).val();
            if (val === '0') {
                $('#other_city_div').show();
                $('#city_category').val('');
                $('#hotel_name_select').empty().append('<option value="">Select Hotel</option><option value="0">Other</option>').trigger('change.select2');
            } else {
                $('#other_city_div').hide();
                $('#other_city_name').val('');
                const category = $(this).find(':selected').data('category');
                $('#city_category').val(category);
            }
        });

        function loadHotels(cityId) {
            if (!cityId || cityId === '0') return;
            $.ajax({
                url: '/get-hotels-by-city',
                method: 'POST',
                data: { city_id: cityId, _token: $('meta[name="csrf-token"]').attr('content') },
                success: function (response) {
                    buildHotelSelect(response.hotels || []);
                }
            });
        }

        function buildHotelSelect(hotels) {
            const $sel = $('#hotel_name_select');
            $sel.empty().append('<option value="">Select Hotel</option>');
            let matchFound = false;
            hotels.forEach(function (hotel) {
                const isSelected = hotel.hotel_name === prefilledHotel.name ? 'selected' : '';
                if (isSelected) matchFound = true;
                $sel.append(`<option value="${hotel.hid}" ${isSelected}>${hotel.hotel_name}</option>`);
            });
            $sel.append('<option value="0">Other</option>');
            $sel.select2({ allowClear: true, dropdownParent: $('#claimDetailModal'), placeholder: 'Select Hotel', minimumInputLength: 0 });

            if (!matchFound && prefilledHotel.name) {
                $sel.val('0').trigger('change.select2');
                $('#other_hotel_div').show();
                $('#other_hotel_name').val(prefilledHotel.name);
            }
        }

        // Load all hotels on init for prefill
        $.ajax({
            url: '/get-hotels',
            method: 'POST',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success: function (response) {
                buildHotelSelect(response.hotels || []);
            }
        });

        $('#hotel_name_select').on('change', function () {
            const val = $(this).val();
            if (val === '0') {
                $('#other_hotel_div').show();
                $('#hotel_name').val('');
            } else {
                $('#other_hotel_div').hide();
                $('#other_hotel_name').val('');
                $('#hotel_name').val($(this).find(':selected').text().split(' (')[0]);
                if (val) loadHotelDetails(val);
            }
        });

        function loadHotelDetails(hotelId) {
            $.ajax({
                url: '/get-hotel-details',
                method: 'POST',
                data: { hotel_id: hotelId, _token: $('meta[name="csrf-token"]').attr('content') },
                success: function (response) {
                    if (response.hotel) {
                        $('#hotel_address').val(response.hotel.hotel_address || '');
                        $('#hotel_contact').val(response.hotel.HotelContact || '');
                        $('#hotel_email').val(response.hotel.HotelEmail || '');
                    }
                }
            });
        }

        // Toggle between Form and Chat views
        $('.view-toggle').on('change', function () {
            if ($(this).val() === 'punch_form') {
                $('#punch-form-section').show();
                $('#chat-section').hide();
            } else {
                $('#punch-form-section').hide();
                $('#chat-section').show();
            }
        });

        // Group Lodging Switch Toggle
        $('#modalGroupLodgingCheckbox').on('change', function() {
            if ($(this).is(':checked')) {
                $('#modalGroupLodgingFields').slideDown(200);
                $('#modalLodgingPaxInput').prop('required', true);
                $('.lodging-pax-asterisk').show();
            } else {
                $('#modalGroupLodgingFields').slideUp(200);
                $('#modalLodgingPaxInput').prop('required', false).removeClass('is-invalid');
                $('.lodging-pax-asterisk').hide();
            }
        });

        // Lodging Employee Search Handler
        $(document).on("click", "#modalLodgingSearchBtn", function() {
            performModalLodgingEmployeeSearch();
        });

        $(document).on("keypress", "#modalLodgingEmployeeSearch", function(e) {
            if (e.which === 13) {
                e.preventDefault();
                performModalLodgingEmployeeSearch();
            }
        });

        function performModalLodgingEmployeeSearch() {
            const query = $("#modalLodgingEmployeeSearch").val().trim();
            if (query.length < 2) {
                alert("Please enter at least 2 characters to search.");
                return;
            }

            const claimantId = String($("#employee_id").val() || "{{ $claimantId }}").trim();
            const claimantCode = String("{{ session('emp_code') }}").trim();

            $.ajax({
                url: "{{ url('employees/search') }}",
                method: "POST",
                data: {
                    _token: '{{ csrf_token() }}',
                    search: query,
                    exclude_claimant: true
                },
                success: function(response) {
                    let html = '';
                    const employees = response.employees || response.data || (Array.isArray(response) ? response : []);
                    const currentIds = $("#modalLodgingParticipantsInput").val() ? $("#modalLodgingParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];

                    if (employees && employees.length > 0) {
                        employees.forEach(emp => {
                            const code = String(emp.EmpCode_New || emp.EmpCode || emp.emp_code || emp.EmployeeID || '').trim();
                            const newCode = String(emp.EmpCode_New || '').trim();
                            const empId = String(emp.EmployeeID || '').trim();
                            const name = emp.full_name || emp.EmployeeName || `${emp.Fname || ''} ${emp.Lname || ''}`.trim() || emp.emp_name || emp.name || '';
                            const grade = emp.grade_name || emp.grade || '';
                            const dept = emp.department_name || emp.department || '';

                            // Exclude claimant
                            if ((claimantId && empId && empId === claimantId) || 
                                (claimantCode && (code === claimantCode || newCode === claimantCode))) {
                                return;
                            }

                            const isAdded = currentIds.includes(empId) || (code && currentIds.includes(code)) || (newCode && currentIds.includes(newCode));

                            const isResigned = (emp.status_label === 'Resigned' || emp.status === 'R' || emp.EmpStatus === 'R' || Boolean(emp.is_resigned));
                            const resignedBadge = isResigned ? '<span class="badge bg-warning-subtle text-warning ms-1">Resigned</span>' : '';

                            if (isAdded) {
                                html += `
                                    <div class="list-group-item d-flex justify-content-between align-items-center bg-light py-2">
                                        <div>
                                            <strong>${name}</strong> <code class="ms-1">${code}</code>${resignedBadge}
                                            <div class="text-muted fs-11">${dept} ${grade ? '| Grade: '+grade : ''}</div>
                                        </div>
                                        <span class="badge bg-secondary">Added</span>
                                    </div>
                                `;
                            } else {
                                html += `
                                    <button type="button" class="list-group-item list-group-item-action modal-add-lodging-emp py-2" 
                                            data-code="${code}" data-newcode="${newCode}" data-id="${empId}" data-name="${name}" data-grade="${grade}" data-dept="${dept}">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${name}</strong> <code class="ms-1">${code}</code>${resignedBadge}
                                                <div class="text-muted fs-11">${dept} ${grade ? '| Grade: '+grade : ''}</div>
                                            </div>
                                            <span class="badge bg-soft-primary text-primary"><i class="ri-add-line me-1"></i>Add</span>
                                        </div>
                                    </button>
                                `;
                            }
                        });
                    } else {
                        html = '<div class="list-group-item text-muted text-center py-2">No matching employees found.</div>';
                    }
                    $("#modalLodgingSearchResults").html(html);
                },
                error: function() {
                    $("#modalLodgingSearchResults").html('<div class="list-group-item text-danger text-center py-2">Error searching employees.</div>');
                }
            });
        }

        // Add Employee Participant
        $(document).on("click", ".modal-add-lodging-emp", function() {
            const code = String($(this).data("code") || "").trim();
            const newCode = String($(this).data("newcode") || "").trim();
            const empId = String($(this).data("id") || "").trim();
            const name = String($(this).data("name") || "").trim();
            const claimantId = String($("#employee_id").val() || "{{ $claimantId }}").trim();
            const claimantCode = String("{{ session('emp_code') }}").trim();

            // Exclude Claimant Validation
            if ((claimantId && empId && empId === claimantId) || 
                (claimantCode && (code === claimantCode || newCode === claimantCode))) {
                alert("Claimant cannot be selected as a participant.");
                return;
            }

            let currentIds = $("#modalLodgingParticipantsInput").val() ? $("#modalLodgingParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];
            
            // PAX Limit Validation
            const paxLimit = parseInt($("#modalLodgingPaxInput").val()) || 0;
            const otherCount = $(".modal-other-lodging-row").length;
            if (paxLimit > 0 && (currentIds.length + otherCount) >= paxLimit) {
                alert(`Cannot add more participants than PAX limit (${paxLimit}).`);
                return;
            }

            // Deduplication Check
            if (currentIds.includes(empId) || (code && currentIds.includes(code)) || (newCode && currentIds.includes(newCode))) {
                alert(`Employee ${name} is already added.`);
                return;
            }

            const valToStore = empId || code;
            currentIds.push(valToStore);
            currentIds = Array.from(new Set(currentIds));
            $("#modalLodgingParticipantsInput").val(currentIds.join(','));

            const dept = String($(this).data("dept") || "").trim();
            const grade = String($(this).data("grade") || "").trim();
            const detailsArray = [];
            if (dept) detailsArray.push(`Dept: ${dept}`);
            if (grade) detailsArray.push(`Grade: ${grade}`);
            const detailsStr = detailsArray.length > 0 ? ` <small style="font-size:11px;opacity:0.85;">(${detailsArray.join(' - ')})</small>` : '';

            const badgeHtml = `
                <span class="badge bg-primary me-1 mb-1 p-2">
                    ${name} [${code}]${detailsStr}
                    <i class="ri-close-line modal-lodging-remove-emp ms-1" data-id="${valToStore}" data-code="${code}" style="cursor:pointer;"></i>
                </span>
            `;
            $("#modalLodgingSelectedList").append(badgeHtml);
            $("#modalLodgingSearchResults").html("");
            $("#modalLodgingEmployeeSearch").val("");
            updateModalLodgingPaxCount();
        });

        // Remove Employee Participant
        $(document).off("click", ".modal-lodging-remove-emp").on("click", ".modal-lodging-remove-emp", function() {
            const targetId = String($(this).data("id") || $(this).data("code") || "").trim();
            let currentIds = $("#modalLodgingParticipantsInput").val() ? $("#modalLodgingParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];
            currentIds = currentIds.filter(id => id !== targetId);
            $("#modalLodgingParticipantsInput").val(currentIds.join(','));
            $(this).closest(".badge").remove();
            updateModalLodgingPaxCount();
        });

        var masterParticipantCategories = {!! json_encode($masterParticipantCategories) !!};

        function renderLodgingCategoryOptions(catVal) {
            let options = '<option value="">-- Select Role / Category * --</option>';
            masterParticipantCategories.forEach(cat => {
                const selected = catVal === cat ? 'selected' : '';
                options += `<option value="${cat}" ${selected}>${cat}</option>`;
            });
            return options;
        }

        // Edit Other Lodging Participant Row
        $(document).off("click", ".edit-modal-other-lodging-row").on("click", ".edit-modal-other-lodging-row", function(e) {
            e.preventDefault();
            const targetRow = $(this).closest(".modal-other-lodging-row");
            const nameInput = targetRow.find(".modal-other-lodging-name");
            const catSelect = targetRow.find(".modal-other-lodging-cat");
            const detailInput = targetRow.find(".modal-other-lodging-details");
            const isEditing = $(this).hasClass("btn-success");

            if (isEditing) {
                // Done editing -> lock
                $(this).removeClass("btn-success").addClass("btn-outline-primary").attr("title", "Edit").html('<i class="ri-edit-line"></i>');
                nameInput.prop("readonly", true).css("background-color", "#f8f9fa");
                catSelect.prop("disabled", true).css("background-color", "#f8f9fa");
                detailInput.prop("readonly", true).css("background-color", "#f8f9fa");
                serializeModalOtherLodgingParticipants();
                updateModalLodgingPaxCount();
            } else {
                // Start editing -> unlock
                $(this).removeClass("btn-outline-primary").addClass("btn-success").attr("title", "Done").html('<i class="ri-check-line"></i>');
                nameInput.prop("readonly", false).css("background-color", "#ffffff").focus();
                catSelect.prop("disabled", false).css("background-color", "#ffffff");
                detailInput.prop("readonly", false).css("background-color", "#ffffff");
            }
        });

        // Add Other Participant Row (Sequence: Category -> No. of Other Pax -> Add Other Participant)
        $(document).off("click", "#addModalOtherLodgingParticipantBtn").on("click", "#addModalOtherLodgingParticipantBtn", function(e) {
            e.preventDefault();
            e.stopPropagation();
            const catVal = $("#modalNewOtherLodgingCategory").val();
            if (!catVal) {
                alert("Please select a Category first.");
                $("#modalNewOtherLodgingCategory").focus();
                return;
            }
            const desiredCount = parseInt($("#modalNewOtherLodgingPaxCount").val()) || 1;
            if (desiredCount <= 0) {
                alert("Number of Other Pax must be at least 1.");
                return;
            }

            const totalPax = parseInt($("#modalLodgingPaxInput").val()) || 0;
            const empCodes = $("#modalLodgingParticipantsInput").val() ? $("#modalLodgingParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];
            const currentOtherCount = $(".modal-other-lodging-row").length;

            if (totalPax > 0 && (empCodes.length + currentOtherCount) >= totalPax) {
                alert(`Cannot add more participants. Total PAX limit (${totalPax}) has been reached.`);
                return;
            }

            let needed = desiredCount - currentOtherCount;
            if (needed <= 0) {
                alert(`All ${desiredCount} field(s) for Other Pax are already added. To add more, increase the 'No. of Other Pax' count.`);
                return;
            }

            if (totalPax > 0) {
                const availableUnderPax = totalPax - (empCodes.length + currentOtherCount);
                if (needed > availableUnderPax) {
                    alert(`Only ${availableUnderPax} slot(s) remaining for total PAX limit (${totalPax}). Adding ${availableUnderPax} participant row(s).`);
                    needed = availableUnderPax;
                }
            }

            for (let i = 0; i < needed; i++) {
                const rowHtml = `
                    <div class="row g-2 align-items-center mb-2 modal-other-lodging-row">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm modal-other-lodging-name" placeholder="Participant Name *" aria-label="Participant Name *">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm modal-other-lodging-cat">
                                ${renderLodgingCategoryOptions(catVal)}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm modal-other-lodging-details" placeholder="Org / Mobile / Remarks (Optional)" aria-label="Org / Mobile / Remarks (Optional)">
                        </div>
                        <div class="col-md-2 text-end d-flex justify-content-end align-items-center">
                            <button type="button" class="btn btn-success btn-sm edit-modal-other-lodging-row me-1" title="Done">
                                <i class="ri-check-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-modal-other-lodging-btn" title="Remove">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                `;
                $("#modalOtherLodgingParticipantsContainer").append(rowHtml);
            }
            updateModalLodgingPaxCount();
        });

        // Remove Other Participant Row
        $(document).off("click", ".remove-modal-other-lodging-btn").on("click", ".remove-modal-other-lodging-btn", function(e) {
            e.preventDefault();
            $(this).closest(".modal-other-lodging-row").remove();
            serializeModalOtherLodgingParticipants();
            updateModalLodgingPaxCount();
        });

        $(document).off("input change", ".modal-other-lodging-cat, .modal-other-lodging-name, .modal-other-lodging-details").on("input change", ".modal-other-lodging-cat, .modal-other-lodging-name, .modal-other-lodging-details", function() {
            serializeModalOtherLodgingParticipants();
        });

        function serializeModalOtherLodgingParticipants() {
            let list = [];
            $(".modal-other-lodging-row").each(function() {
                const name = $(this).find(".modal-other-lodging-name").val().trim();
                const category = $(this).find(".modal-other-lodging-cat").val() || 'Guest';
                const details = $(this).find(".modal-other-lodging-details").val().trim();
                if (name) {
                    list.push({ name, category, details });
                }
            });
            $("#modalLodgingOtherParticipants").val(list.length > 0 ? JSON.stringify(list) : '');
        }

        function updateModalLodgingPaxCount() {
            const empCodes = $("#modalLodgingParticipantsInput").val() ? $("#modalLodgingParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];
            const uniqueCodes = Array.from(new Set(empCodes));
            $("#modalLodgingParticipantsInput").val(uniqueCodes.join(','));

            const otherRows = $(".modal-other-lodging-row").length;
            const total = uniqueCodes.length + otherRows;
            const totalPax = parseInt($("#modalLodgingPaxInput").val()) || 0;
            if (totalPax <= 0 && total > 0) {
                $("#modalLodgingPaxInput").val(total);
            }
            const displayCount = parseInt($("#modalLodgingPaxInput").val()) || total;
            if (displayCount > 0) {
                $("#modalLodgingParticipantCount").show().text(`Participants: ${displayCount}`);
            } else {
                $("#modalLodgingParticipantCount").hide().text('');
            }

            // Restrict / auto hide add button based on desired headcount & total pax
            const desiredOtherPax = parseInt($("#modalNewOtherLodgingPaxCount").val()) || 1;
            const otherPaxFulfilled = otherRows >= desiredOtherPax;
            const totalPaxReached = totalPax > 0 && total >= totalPax;

            if (totalPaxReached || otherPaxFulfilled) {
                $("#addModalOtherLodgingParticipantBtn").hide();
            } else {
                $("#addModalOtherLodgingParticipantBtn").show();
            }
        }

        $(document).off("input change", "#modalLodgingPaxInput, #modalNewOtherLodgingPaxCount").on("input change", "#modalLodgingPaxInput, #modalNewOtherLodgingPaxCount", function() {
            updateModalLodgingPaxCount();
        });

        // Initialize state on load
        serializeModalOtherLodgingParticipants();
        updateModalLodgingPaxCount();

        // Helper to format date display (YYYY-MM-DD to DD-MM-YYYY)
        function formatClaimDateDisplay(dStr) {
            if (!dStr || dStr === '0000-00-00') return '';
            try {
                const parts = String(dStr).split('-');
                if (parts.length === 3 && parts[0].length === 4) {
                    return `${parts[2]}-${parts[1]}-${parts[0]}`;
                }
                return dStr;
            } catch(e) { return dStr; }
        }

        // Same-day claims breakdown modal on clicking badge
        $(document).off('click', '.view-same-day-claims-btn').on('click', '.view-same-day-claims-btn', function() {
            const name = $(this).data('emp-name') || '';
            const code = $(this).data('emp-code') || '';
            const date = $(this).data('bill-date') || '';
            const cat  = $(this).data('category') || 'Lodging';
            const rawClaims = $(this).attr('data-claims') || '[]';
            let claims = [];
            try { claims = typeof rawClaims === 'object' ? rawClaims : JSON.parse(rawClaims); } catch(e) { claims = []; }

            if (!claims || !claims.length) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('No Claims', 'No claim records found for this date.', 'info');
                } else {
                    alert('No claim records found for this date.');
                }
                return;
            }

            let rows = '';
            claims.forEach(c => {
                const amtFormatted = '₹' + Number(c.amount||0).toLocaleString('en-IN');
                const statusVal = String(c.status || 'Draft').trim();
                const normStat = statusVal.toLowerCase();
                const isVerifiedStatus = ['verified', 'approved', 'financed', 'paid', 'closed', 'v', 'a', 'f', 'p'].includes(normStat);
                const hasValidVerifyDate = Boolean(c.verify_date && c.verify_date !== '0000-00-00' && c.verify_date !== '0000-00-00 00:00:00');
                const isFullyVerified = isVerifiedStatus && hasValidVerifyDate;

                let vAmt = isFullyVerified ? Number(c.verify_amount || c.amount || 0) : 0;
                const vAmtFormatted = (isFullyVerified && vAmt > 0) ? ('₹' + vAmt.toLocaleString('en-IN')) : '—';

                const billDateFormatted = formatClaimDateDisplay(c.date || date);
                const verifyDateFormatted = isFullyVerified ? formatClaimDateDisplay(c.verify_date) : '';

                let statusBadge = 'secondary';
                let displayStat = 'Draft';
                if (['paid', 'financed', 'closed', 'p', 'f'].includes(normStat)) {
                    displayStat = 'Paid';
                    statusBadge = 'success';
                } else if (['approved', 'a'].includes(normStat)) {
                    displayStat = 'Approved';
                    statusBadge = 'primary';
                } else if (['verified', 'v'].includes(normStat)) {
                    displayStat = 'Verified';
                    statusBadge = 'info text-dark';
                } else if (['filled', 'open'].includes(normStat)) {
                    displayStat = (normStat === 'open') ? 'Open' : 'Filled';
                    statusBadge = 'warning text-dark';
                } else {
                    displayStat = 'Draft';
                    statusBadge = 'secondary';
                }

                rows += `<tr>
                    <td><strong class="text-primary">#${c.exp_id}</strong></td>
                    <td class="text-start fw-semibold">${c.claim_name || cat}</td>
                    <td>
                        <strong class="text-danger">${amtFormatted}</strong>
                        ${billDateFormatted ? `<small class="d-block text-secondary" style="font-size:10px; font-weight:normal;">(${billDateFormatted})</small>` : ''}
                    </td>
                    <td>
                        ${isFullyVerified && vAmt > 0 ? `
                            <strong class="text-success">${vAmtFormatted}</strong>
                            ${verifyDateFormatted ? `<small class="d-block text-success" style="font-size:10px; font-weight:normal;">(${verifyDateFormatted})</small>` : ''}
                        ` : '<span class="text-muted fw-semibold">—</span>'}
                    </td>
                    <td><span class="badge bg-${statusBadge} fw-bold">${displayStat}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 open-claim-from-pax-btn" data-exp-id="${c.exp_id}" data-claim-id="${c.claim_id}">
                            <i class="ri-eye-line"></i> View
                        </button>
                    </td>
                </tr>`;
            });

            const formattedDateHeader = formatClaimDateDisplay(date);
            const contentHtml = `
                <div class="text-start mb-2 px-1" style="font-size:13px;">
                    <strong>Employee:</strong> <span class="text-dark fw-bold">${name}</span> <span class="badge bg-light text-dark border ms-1">[${code}]</span> &bull; 
                    <strong>Date:</strong> <span class="badge bg-dark text-white ms-1">${formattedDateHeader}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle text-center mb-0" style="font-size:12px;">
                        <thead class="table-dark">
                            <tr>
                                <th>Exp ID</th>
                                <th>Category</th>
                                <th>Claimed Amount</th>
                                <th>Verified Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: `${cat} - Same Day Claims`,
                    html: contentHtml,
                    width: '720px',
                    showCloseButton: true,
                    showConfirmButton: false
                });
            }
        });

        // State for in-modal document previewer
        let paxPreviewFiles = [];
        let paxPreviewActiveIndex = 0;
        let paxZoomLevel = 1;
        let paxRotationDeg = 0;
        let paxTransX = 0, paxTransY = 0;
        let paxIsDragging = false, paxStartX = 0, paxStartY = 0;

        function getCleanExtension(urlOrPath) {
            if (!urlOrPath || typeof urlOrPath !== 'string') return '';
            try {
                const clean = urlOrPath.split('?')[0].split('#')[0];
                const match = clean.match(/\.([a-zA-Z0-9]+)$/);
                return match ? match[1].toLowerCase() : '';
            } catch(e) {
                return '';
            }
        }

        function isImageFile(doc, fileUrl, fileName) {
            const ext = getCleanExtension(doc?.file_name) || getCleanExtension(doc?.file_path) || getCleanExtension(doc?.s3_path) || getCleanExtension(fileUrl) || getCleanExtension(fileName);
            const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'jfif', 'ico', 'tif', 'tiff', 'heic', 'heif'];
            if (imageExts.includes(ext)) return true;
            return /\.(jpe?g|png|gif|webp|bmp|svg|jfif)($|\?|#)/i.test(fileUrl) || /\.(jpe?g|png|gif|webp|bmp|svg|jfif)$/i.test(fileName);
        }

        function isPdfFile(doc, fileUrl, fileName) {
            const ext = getCleanExtension(doc?.file_name) || getCleanExtension(doc?.file_path) || getCleanExtension(doc?.s3_path) || getCleanExtension(fileUrl) || getCleanExtension(fileName);
            if (ext === 'pdf') return true;
            return /\.pdf($|\?|#)/i.test(fileUrl) || /\.pdf$/i.test(fileName);
        }

        function updatePaxPreviewTransform() {
            const $img = $('#paxPreviewMainImg');
            if ($img.length) {
                $img.css({
                    'transform': `translate(${paxTransX}px, ${paxTransY}px) scale(${paxZoomLevel}) rotate(${paxRotationDeg}deg)`,
                    'transform-origin': 'center center',
                    'transition': paxIsDragging ? 'none' : 'transform 0.15s ease-out'
                });
                $('#paxPreviewZoomVal').text(Math.round(paxZoomLevel * 100) + '%');
            }
        }

        function switchPaxDocument(index) {
            paxPreviewActiveIndex = index;
            paxZoomLevel = 1;
            paxRotationDeg = 0;
            paxTransX = 0;
            paxTransY = 0;

            $('.pax-doc-thumb-item').removeClass('border-primary shadow-sm bg-primary-subtle').addClass('border-light-subtle');
            $(`.pax-doc-thumb-item[data-doc-index="${index}"]`).removeClass('border-light-subtle').addClass('border-primary shadow-sm bg-primary-subtle');

            const doc = paxPreviewFiles[index];
            if (!doc) {
                $('#paxDocViewerContainer').html('<div class="d-flex align-items-center justify-content-center h-100 text-muted p-4"><i class="ri-file-damage-line fs-2 me-2"></i> Document not available</div>');
                $('#paxDocViewerToolbar').hide();
                return;
            }

            const fileUrl = doc.file_url || doc.file_path || '';
            const fileName = doc.file_name || doc.file_path || ('Document #' + (index + 1));
            const isImage = isImageFile(doc, fileUrl, fileName);
            const isPdf = isPdfFile(doc, fileUrl, fileName);

            $('#paxDocFileNameText').text(fileName);
            $('#paxDocIndexBadge').text((index + 1) + ' / ' + paxPreviewFiles.length);

            if (isImage || (!isPdf && !getCleanExtension(fileUrl) && !getCleanExtension(fileName))) {
                // If it's an image (or extension not known), attempt to render image preview
                $('#paxDocViewerContainer').html(`
                    <div id="paxDocImgStage" class="d-flex align-items-center justify-content-center w-100 h-100 overflow-hidden position-relative" style="background:#0f172a; min-height:480px; width:100%; height:100%; cursor:grab; user-select:none;">
                        <img id="paxPreviewMainImg" src="${fileUrl}" alt="${fileName}" style="max-width:100%; max-height:100%; object-fit:contain; pointer-events:auto;" />
                    </div>
                `);
                $('#paxDocViewerToolbar').show();
                updatePaxPreviewTransform();

                // Setup pan & drag
                const $stage = $('#paxDocImgStage');
                $stage.off('mousedown').on('mousedown', function(e) {
                    if (e.target.id === 'paxPreviewMainImg' || e.target.id === 'paxDocImgStage') {
                        paxIsDragging = true;
                        paxStartX = e.clientX - paxTransX;
                        paxStartY = e.clientY - paxTransY;
                        $stage.css('cursor', 'grabbing');
                        e.preventDefault();
                    }
                });

                $(document).off('mousemove.paxDrag').on('mousemove.paxDrag', function(e) {
                    if (paxIsDragging) {
                        paxTransX = e.clientX - paxStartX;
                        paxTransY = e.clientY - paxStartY;
                        updatePaxPreviewTransform();
                    }
                });

                $(document).off('mouseup.paxDrag').on('mouseup.paxDrag', function() {
                    if (paxIsDragging) {
                        paxIsDragging = false;
                        $stage.css('cursor', 'grab');
                    }
                });

                $('#paxPreviewMainImg').on('error', function() {
                    // If image fails to load, attempt iframe preview
                    $('#paxDocViewerContainer').html(`
                        <div class="w-100 h-100 position-relative" style="min-height:500px; width:100%; height:100%; background:#f1f5f9;">
                            <iframe src="${fileUrl}" class="w-100 h-100 border-0 rounded" style="min-height:500px; width:100%; height:100%;" allowfullscreen></iframe>
                        </div>
                    `);
                    $('#paxDocViewerToolbar').hide();
                });

            } else if (isPdf) {
                $('#paxDocViewerContainer').html(`
                    <div class="w-100 h-100 position-relative" style="min-height:500px; width:100%; height:100%; background:#f1f5f9;">
                        <iframe src="${fileUrl}#toolbar=1" class="w-100 h-100 border-0 rounded" style="min-height:500px; width:100%; height:100%;" allowfullscreen></iframe>
                    </div>
                `);
                $('#paxDocViewerToolbar').hide();
            } else {
                $('#paxDocViewerContainer').html(`
                    <div class="w-100 h-100 d-flex flex-column position-relative" style="min-height:500px; width:100%; height:100%; background:#f8fafc;">
                        <iframe src="${fileUrl}" class="w-100 flex-grow-1 border-0 rounded" style="min-height:440px; width:100%; height:100%;" allowfullscreen></iframe>
                        <div class="p-2 bg-white border-top d-flex justify-content-between align-items-center">
                            <span class="small text-muted text-truncate" style="max-width:250px;"><i class="ri-file-line me-1"></i>${fileName}</span>
                            <div class="d-flex gap-2">
                                <a href="${fileUrl}" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2"><i class="ri-external-link-line me-1"></i> Open in New Tab</a>
                                <a href="${fileUrl}" download class="btn btn-sm btn-primary py-1 px-2"><i class="ri-download-line me-1"></i> Download</a>
                            </div>
                        </div>
                    </div>
                `);
                $('#paxDocViewerToolbar').hide();
            }
        }

        // Toolbar Events
        $(document).off('click', '#paxBtnZoomIn').on('click', '#paxBtnZoomIn', function() {
            paxZoomLevel = Math.min(paxZoomLevel + 0.25, 4.0);
            updatePaxPreviewTransform();
        });
        $(document).off('click', '#paxBtnZoomOut').on('click', '#paxBtnZoomOut', function() {
            paxZoomLevel = Math.max(paxZoomLevel - 0.25, 0.4);
            updatePaxPreviewTransform();
        });
        $(document).off('click', '#paxBtnRotateLeft').on('click', '#paxBtnRotateLeft', function() {
            paxRotationDeg = (paxRotationDeg - 90) % 360;
            updatePaxPreviewTransform();
        });
        $(document).off('click', '#paxBtnRotateRight').on('click', '#paxBtnRotateRight', function() {
            paxRotationDeg = (paxRotationDeg + 90) % 360;
            updatePaxPreviewTransform();
        });
        $(document).off('click', '#paxBtnResetZoom').on('click', '#paxBtnResetZoom', function() {
            paxZoomLevel = 1;
            paxRotationDeg = 0;
            paxTransX = 0;
            paxTransY = 0;
            updatePaxPreviewTransform();
        });
        $(document).off('click', '.pax-doc-thumb-item').on('click', '.pax-doc-thumb-item', function() {
            const idx = parseInt($(this).data('doc-index'), 10);
            switchPaxDocument(idx);
        });

        $(document).off('click', '.open-claim-from-pax-btn').on('click', '.open-claim-from-pax-btn', function() {
            const expId = $(this).data('exp-id');
            const claimId = $(this).data('claim-id');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: `Loading Claim #${expId}...`,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }

            $.get('/claim-detail', { claim_id: claimId, expid: expId }, function(data) {
                let htmlContent = data.html || '';
                let rawFiles = data.uploaded_files || [];
                let flatFiles = [];
                if (Array.isArray(rawFiles)) {
                    flatFiles = rawFiles;
                } else if (typeof rawFiles === 'object' && rawFiles !== null) {
                    Object.values(rawFiles).forEach(group => {
                        if (group && Array.isArray(group.files)) {
                            flatFiles.push(...group.files);
                        } else if (group && (group.file_url || group.file_path)) {
                            flatFiles.push(group);
                        }
                    });
                }
                // UAT E-05: label the lodging online payment receipt (UploadSequence 110).
                paxPreviewFiles = flatFiles.map(f => (f && f.is_payment_receipt)
                    ? Object.assign({}, f, { file_name: (f.file_label || 'Online Payment Receipt') + ': ' + (f.file_name || f.file_path || '') })
                    : f);

                if ($('#paxClaimPreviewModal').length === 0) {
                    $('body').append(`
                        <div class="modal fade" id="paxClaimPreviewModal" tabindex="-1" aria-labelledby="paxClaimPreviewModalLabel" aria-hidden="true" style="z-index: 99999;">
                            <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
                                <div class="modal-content" style="background:#f8fafc;">
                                    <div class="modal-header bg-dark text-white py-2 px-3 d-flex align-items-center justify-content-between">
                                        <h5 class="modal-title fs-14 fw-bold mb-0 text-white" id="paxClaimPreviewModalLabel">
                                            <i class="ri-file-list-3-line me-1"></i> Claim Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0" id="paxClaimPreviewModalBody"></div>
                                </div>
                            </div>
                        </div>
                    `);
                }

                $('#paxClaimPreviewModalLabel').html(`
                    <span class="d-flex align-items-center gap-2">
                        <i class="ri-file-text-line text-warning"></i>
                        <span>Claim Details &bull; <strong class="text-info">#${expId}</strong></span>
                    </span>
                `);

                let splitLayoutHtml = '';
                const hasDocs = paxPreviewFiles.length > 0;

                let docThumbsHtml = '';
                if (hasDocs) {
                    paxPreviewFiles.forEach((f, i) => {
                        const u = f.file_url || f.file_path || '';
                        const name = f.file_name || f.file_path || ('Document #' + (i + 1));
                        const isImg = isImageFile(f, u, name);
                        const isPdf = isPdfFile(f, u, name);
                        const iconHtml = isImg ? `<img alt="Bill or document image" src="${u}" style="width:34px;height:34px;object-fit:cover;border-radius:4px;" onerror="$(this).replaceWith('<i class=\\'ri-image-line fs-4 text-primary\\'></i>')">` : `<i class="ri-${isPdf ? 'file-pdf' : 'file-text'}-line fs-4 ${isPdf ? 'text-danger' : 'text-primary'}"></i>`;
                        
                        docThumbsHtml += `
                            <button type="button" class="btn btn-sm btn-light border pax-doc-thumb-item d-flex align-items-center gap-2 py-1 px-2 text-start ${i === 0 ? 'border-primary shadow-sm bg-primary-subtle' : 'border-light-subtle'}" data-doc-index="${i}" title="${name}">
                                ${iconHtml}
                                <span class="small fw-semibold text-truncate" style="max-width:110px;">${name}</span>
                            </button>
                        `;
                    });
                }

                splitLayoutHtml = `
                    <div class="row g-0 h-100">
                        <!-- Left: Document Viewer -->
                        <div class="col-lg-5 col-md-6 border-end bg-white d-flex flex-column" style="min-height:550px;">
                            <div class="p-2 border-bottom bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary px-2 py-1"><i class="ri-attachment-line me-1"></i>Document Viewer</span>
                                    <span class="small text-muted fw-semibold" id="paxDocFileNameText">${hasDocs ? (paxPreviewFiles[0].file_name || paxPreviewFiles[0].file_path || 'Document #1') : 'No attachments'}</span>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary" id="paxDocIndexBadge">${hasDocs ? ('1 / ' + paxPreviewFiles.length) : '0 / 0'}</span>
                            </div>

                            ${hasDocs && paxPreviewFiles.length > 1 ? `
                                <div class="px-2 py-1 border-bottom bg-light-subtle d-flex gap-2 overflow-auto" style="white-space:nowrap;">
                                    ${docThumbsHtml}
                                </div>
                            ` : ''}

                            <!-- Canvas / Viewport -->
                            <div class="flex-grow-1 position-relative d-flex flex-column justify-content-center align-items-center overflow-hidden" id="paxDocViewerContainer" style="min-height:480px; background:#0f172a;">
                                ${!hasDocs ? `
                                    <div class="d-flex flex-column align-items-center justify-content-center text-white-50 p-4 text-center">
                                        <i class="ri-folder-open-line fs-1 mb-2"></i>
                                        <span class="fw-semibold">No documents attached with this claim</span>
                                    </div>
                                ` : ''}
                            </div>

                            <!-- Image Controls Toolbar -->
                            <div class="p-2 bg-dark text-white d-flex align-items-center justify-content-center gap-1 border-top" id="paxDocViewerToolbar" style="${hasDocs ? '' : 'display:none;'}">
                                <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" id="paxBtnZoomOut" title="Zoom Out"><i class="ri-zoom-out-line"></i></button>
                                <span class="badge bg-secondary px-2" id="paxPreviewZoomVal" style="min-width:46px;">100%</span>
                                <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" id="paxBtnZoomIn" title="Zoom In"><i class="ri-zoom-in-line"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 ms-1" id="paxBtnResetZoom" title="Reset Zoom"><i class="ri-refresh-line"></i></button>
                                <div class="vr bg-secondary mx-1" style="height:20px;"></div>
                                <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" id="paxBtnRotateLeft" title="Rotate Counter-Clockwise"><i class="ri-anticlockwise-line"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" id="paxBtnRotateRight" title="Rotate Clockwise"><i class="ri-clockwise-line"></i></button>
                            </div>
                        </div>

                        <!-- Right: Claim Form Details -->
                        <div class="col-lg-7 col-md-6 p-3 overflow-auto" style="max-height:calc(100vh - 55px); background:#ffffff;">
                            ${htmlContent}
                        </div>
                    </div>
                `;

                $('#paxClaimPreviewModalBody').html(splitLayoutHtml);

                if (typeof Swal !== 'undefined') {
                    Swal.close();
                }

                const previewModalEl = document.getElementById('paxClaimPreviewModal');
                const previewModal = bootstrap.Modal.getInstance(previewModalEl) || new bootstrap.Modal(previewModalEl);
                previewModal.show();

                if (hasDocs) {
                    switchPaxDocument(0);
                }
            }).fail(function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Unable to load claim details.', 'error');
                } else {
                    alert('Unable to load claim details.');
                }
            });
        });
    });
</script>