<div class="row mb-3">
    <div class="col-md-12">
        <div class="d-flex justify-content-left">
            <div class="form-check form-check-inline">
                <input class="form-check-input view-toggle" type="radio"
                       name="view_mode" id="view_punch_form" value="punch_form" checked />
                <label class="form-check-label" for="view_punch_form">Form</label>
            </div>

            <div class="form-check form-check-inline">
                <input class="form-check-input view-toggle" type="radio"
                       name="view_mode" id="view_chat" value="chat" />
                <label class="form-check-label" for="view_chat">Chat</label>
            </div>
        </div>
    </div>
</div>
@php
    $remarkChat = collect($expense_detail->remark_chat ?? []);
    $defaultRemark = 'DA';

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

    $rawClaimantId = $expense_detail->CrBy ?? $expense_detail->EmployeeID ?? session('employee_id');
    $currentExpId = (int)($expense_detail->ExpId ?? 0);

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

    // 1. Resolve Claimant Employee Record
    $claimantRecord = null;
    try {
        $claimantRecord = DB::connection($hrimsConn)->table('hrm_employee as emp')
            ->leftJoin('hrm_employee_general as g', 'emp.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_departments as dept', 'g.DepartmentId', '=', 'dept.id')
            ->leftJoin('core_grades as grade', 'g.GradeId', '=', 'grade.id')
            ->where(function($q) use ($rawClaimantId) {
                $q->where('emp.EmployeeID', $rawClaimantId)
                  ->orWhere('emp.EmpCode', (string)$rawClaimantId)
                  ->orWhere('emp.EmpCode_New', (string)$rawClaimantId);
            })
            ->orderByRaw("CASE WHEN emp.EmployeeID = " . (int)$rawClaimantId . " THEN 0 ELSE 1 END")
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

    $claimantEmpId = $claimantRecord ? (int)$claimantRecord->EmployeeID : (int)$rawClaimantId;
    $claimantCode = $claimantRecord ? (!empty($claimantRecord->EmpCode_New) ? $claimantRecord->EmpCode_New : $claimantRecord->EmpCode) : (string)session('emp_code', $rawClaimantId);

    // 2. Discover related Group Meal Claims on the same date where claimant is CrBy OR participant in PaxDetails
    $verifiedEmpIds = [$claimantEmpId];
    $paxCodesToResolve = [];

    if (!empty($billDateDb)) {
        try {
            $mealClaimsOnDate = DB::connection($expConn)->table("y{$yearIdVal}_expenseclaims")
                ->where('ClaimId', 17)
                ->where('BillDate', $billDateDb)
                ->whereNotIn('ClaimStatus', ['Deactivate', 'D', 'Deleted'])
                ->get(['ExpId', 'CrBy', 'FilledTAmt', 'Amount', 'ClaimStatus', 'BillDate']);

            $mealExpIds = $mealClaimsOnDate->pluck('ExpId')->toArray();
            $mealFillMap = [];
            if (!empty($mealExpIds)) {
                $fillRows = DB::connection($expConn)->table("y{$yearIdVal}_g1_expensefilldata")
                    ->whereIn('ExpId', $mealExpIds)
                    ->get(['ExpId', 'PaxDetails', 'PaxDetails_Other']);
                foreach ($fillRows as $fr) {
                    $mealFillMap[$fr->ExpId] = $fr;
                }
            }

            foreach ($mealClaimsOnDate as $mc) {
                $rawPax = $mealFillMap[$mc->ExpId]->PaxDetails ?? '';
                $paxCodes = array_values(array_filter(array_map('trim', explode(',', $rawPax))));

                $isClaimantInMeal = ((int)$mc->CrBy === $claimantEmpId)
                    || in_array((string)$claimantEmpId, $paxCodes);

                if ($isClaimantInMeal) {
                    $verifiedEmpIds[] = (int)$mc->CrBy;
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

        // DHQ & DOS Eligibility
        $dhqDosEligStr = 'DHQ: ₹0 | DOS: ₹0';
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
                    $rawIn = trim((string)($r->DA_Inside_Hq ?? ''));
                    $rawOut = trim((string)($r->DA_Outside_Hq ?? ''));
                    if ($rawIn !== '' && $rawIn !== '0') $score += 1000;
                    if ($rawOut !== '' && $rawOut !== '0') $score += 1000;
                    $score += max($parseCleanAmount($rawIn), $parseCleanAmount($rawOut));
                    return $score;
                })->first();

                if ($eligRow) {
                    $rawDhq = trim((string)($eligRow->DA_Inside_Hq ?? ''));
                    $rawDos = trim((string)($eligRow->DA_Outside_Hq ?? ''));
                    $dhqVal = $parseCleanAmount($rawDhq);
                    $dosVal = $parseCleanAmount($rawDos);
                    $dhqDisplay = $dhqVal > 0 ? '₹' . number_format($dhqVal, 0) : (!empty($rawDhq) && $rawDhq !== '0' ? $rawDhq : '₹0');
                    $dosDisplay = $dosVal > 0 ? '₹' . number_format($dosVal, 0) : (!empty($rawDos) && $rawDos !== '0' ? $rawDos : '₹0');
                    $dhqDosEligStr = 'DHQ: ' . $dhqDisplay . ' | DOS: ' . $dosDisplay;
                }
            }
        } catch (\Throwable $e) {}

        // Meal Claimed & Verified on Same Date (Only claims where this employee is CrBy)
        $mealAmt = 0;
        $mealVerifyAmt = 0;
        $mealBillDateStr = '';
        $mealVerifyDateStr = '';
        $mealClaimsList = [];
        $latestClaimStatus = '';

        if (!empty($billDateDb)) {
            try {
                $mcRows = DB::connection($expConn)->table("y{$yearIdVal}_expenseclaims")
                    ->where('CrBy', $eId)
                    ->where('ClaimId', 17)
                    ->where('BillDate', $billDateDb)
                    ->where('ExpId', '!=', $currentExpId)
                    ->whereNotIn('ClaimStatus', ['Deactivate', 'D', 'Deleted'])
                    ->get();

                // If this employee didn't submit a meal claim directly, check if they participated in another employee's Group Meal on this date
                if ($mcRows->isEmpty() && !empty($mealClaimsOnDate)) {
                    foreach ($mealClaimsOnDate as $otherMc) {
                        $rawPax = $mealFillMap[$otherMc->ExpId]->PaxDetails ?? '';
                        $paxCodes = array_values(array_filter(array_map('trim', explode(',', $rawPax))));
                        if (in_array((string)$eId, $paxCodes) || (!empty($eCode) && in_array((string)$eCode, $paxCodes))) {
                            $hostEmp = $allEmps->firstWhere('EmployeeID', $otherMc->CrBy);
                            $hostName = $hostEmp ? trim(($hostEmp->Fname ?? '') . ' ' . ($hostEmp->Lname ?? '')) : ('Emp #' . $otherMc->CrBy);
                            $otherMcCopy = clone $otherMc;
                            $otherMcCopy->is_participant_group_meal = true;
                            $otherMcCopy->host_name = $hostName;
                            $mcRows->push($otherMcCopy);
                        }
                    }
                }

                foreach ($mcRows as $mc) {
                    $amt = (float)($mc->FilledTAmt ?? $mc->Amount ?? $mc->FilledAmt ?? $mc->VerifyTAmt ?? $mc->ApprTAmt ?? $mc->FinancedTAmt ?? $mc->ReqTAmt ?? 0);
                    $mealAmt += $amt;
                    if (!empty($mc->BillDate) && empty($mealBillDateStr)) {
                        $mealBillDateStr = date('d-m-Y', strtotime($mc->BillDate));
                    }

                    // Verified Amount ONLY if status is verified/approved/paid AND VerifyDate is valid
                    $mcStatNorm = strtolower(trim((string)($mc->ClaimStatus ?? '')));
                    $isMcVerified = in_array($mcStatNorm, ['verified', 'approved', 'financed', 'paid', 'closed', 'v', 'a', 'f', 'p']);
                    $hasValidVerifyDate = (!empty($mc->VerifyDate) && \App\Support\ZeroDate::has($mc->VerifyDate) && \App\Support\ZeroDate::has($mc->VerifyDate));
                    $vAmt = 0;
                    if ($isMcVerified && $hasValidVerifyDate) {
                        $vAmt = isset($mc->VerifyTAmt) && $mc->VerifyTAmt !== null ? (float)$mc->VerifyTAmt : (float)$amt;
                        $mealVerifyAmt += $vAmt;
                        if (empty($mealVerifyDateStr)) {
                            $mealVerifyDateStr = date('d-m-Y', strtotime($mc->VerifyDate));
                        }
                    }

                    if (!empty($mc->ClaimStatus)) {
                        $latestClaimStatus = trim((string)$mc->ClaimStatus);
                    }
                    $isPartMeal = !empty($mc->is_participant_group_meal);
                    $claimNameLabel = $isPartMeal ? ('Group Meal (Host: ' . ($mc->host_name ?? 'Claimant') . ')') : 'Meals';
                    $mealClaimsList[] = [
                        'exp_id' => $mc->ExpId,
                        'claim_id' => 17,
                        'claim_name' => $claimNameLabel,
                        'amount' => $amt,
                        'verify_amount' => $vAmt,
                        'date' => $mc->BillDate,
                        'verify_date' => ($isMcVerified && $hasValidVerifyDate) ? $mc->VerifyDate : '',
                        'status' => $mc->ClaimStatus ?? 'Claimed',
                        'is_participant' => $isPartMeal,
                        'host_name' => $mc->host_name ?? null
                    ];
                }
            } catch (\Throwable $e) {}
        }

        $mealClaimedStr = '₹0';
        if ($mealAmt > 0) {
            $mealClaimedStr = '₹' . number_format($mealAmt, 0) . (!empty($mealBillDateStr) ? ' <small style="font-size: 10px; font-weight: normal; opacity: 0.9;">(' . $mealBillDateStr . ')</small>' : '');
        }

        $mealVerifiedStr = '—';
        if ($mealVerifyAmt > 0 && !empty($mealVerifyDateStr)) {
            $mealVerifiedStr = '₹' . number_format($mealVerifyAmt, 0) . ' <small style="font-size: 10px; font-weight: normal; opacity: 0.9;">(' . $mealVerifyDateStr . ')</small>';
        }

        // DHQ & DOS Claimed & Verified on Same Date
        $dhqAmt = 0;
        $dhqVerifyAmt = 0;
        $dhqDateStr = '';
        $dhqVerifyDateStr = '';

        $dosAmt = 0;
        $dosVerifyAmt = 0;
        $dosDateStr = '';
        $dosVerifyDateStr = '';

        $dhqDosClaimsList = [];
        if (!empty($billDateDb)) {
            try {
                $empClaimsOnDate = DB::connection($expConn)->table("y{$yearIdVal}_expenseclaims")
                    ->where('CrBy', $eId)
                    ->where('BillDate', $billDateDb)
                    ->whereIn('ClaimId', [19, 20])
                    ->whereNotIn('ClaimStatus', ['Deactivate', 'D', 'Deleted'])
                    ->get();

                foreach ($empClaimsOnDate as $cRow) {
                    $cId = (int)($cRow->ClaimId ?? 0);
                    $amt = (float)($cRow->FilledTAmt ?? $cRow->Amount ?? $cRow->FilledAmt ?? $cRow->VerifyTAmt ?? $cRow->ApprTAmt ?? $cRow->FinancedTAmt ?? $cRow->ReqTAmt ?? 0);
                    $bDate = !empty($cRow->BillDate) ? date('d-m-Y', strtotime($cRow->BillDate)) : '';

                    // Verified Amount ONLY if status is verified/approved/paid AND VerifyDate is valid
                    $cStatNorm = strtolower(trim((string)($cRow->ClaimStatus ?? '')));
                    $isRowVerified = in_array($cStatNorm, ['verified', 'approved', 'financed', 'paid', 'closed', 'v', 'a', 'f', 'p']);
                    $hasValidVerifyDate = (!empty($cRow->VerifyDate) && \App\Support\ZeroDate::has($cRow->VerifyDate) && \App\Support\ZeroDate::has($cRow->VerifyDate));
                    $vAmt = 0;
                    $vDate = '';
                    if ($isRowVerified && $hasValidVerifyDate) {
                        $vAmt = isset($cRow->VerifyTAmt) && $cRow->VerifyTAmt !== null ? (float)$cRow->VerifyTAmt : (float)$amt;
                        $vDate = date('d-m-Y', strtotime($cRow->VerifyDate));
                    }

                    if (!empty($cRow->ClaimStatus) && empty($latestClaimStatus)) {
                        $latestClaimStatus = trim((string)$cRow->ClaimStatus);
                    }

                    if ($cId === 19) {
                        $dhqAmt += $amt;
                        $dhqDateStr = $bDate;
                        if ($isRowVerified && $hasValidVerifyDate) {
                            $dhqVerifyAmt += $vAmt;
                            $dhqVerifyDateStr = $vDate;
                        }
                        $dhqDosClaimsList[] = [
                            'exp_id' => $cRow->ExpId,
                            'claim_id' => 19,
                            'claim_name' => 'DA@HQ (Inside HQ - DHQ)',
                            'amount' => $amt,
                            'verify_amount' => $vAmt,
                            'date' => $cRow->BillDate,
                            'verify_date' => ($isRowVerified && $hasValidVerifyDate) ? $cRow->VerifyDate : '',
                            'status' => $cRow->ClaimStatus ?? 'Claimed'
                        ];
                    } elseif ($cId === 20) {
                        $dosAmt += $amt;
                        $dosDateStr = $bDate;
                        if ($isRowVerified && $hasValidVerifyDate) {
                            $dosVerifyAmt += $vAmt;
                            $dosVerifyDateStr = $vDate;
                        }
                        $dhqDosClaimsList[] = [
                            'exp_id' => $cRow->ExpId,
                            'claim_id' => 20,
                            'claim_name' => 'DA (Outside HQ - DOS)',
                            'amount' => $amt,
                            'verify_amount' => $vAmt,
                            'date' => $cRow->BillDate,
                            'verify_date' => ($isRowVerified && $hasValidVerifyDate) ? $cRow->VerifyDate : '',
                            'status' => $cRow->ClaimStatus ?? 'Claimed'
                        ];
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Format DHQ / DOS Claimed String
        $dhqDosClaimedParts = [];
        if ($dhqAmt > 0) {
            $dhqDosClaimedParts[] = 'DHQ: ₹' . number_format($dhqAmt, 0) . (!empty($dhqDateStr) ? ' <small style="font-size: 10px; font-weight: normal; opacity: 0.9;">(' . $dhqDateStr . ')</small>' : '');
        }
        if ($dosAmt > 0) {
            $dhqDosClaimedParts[] = 'DOS: ₹' . number_format($dosAmt, 0) . (!empty($dosDateStr) ? ' <small style="font-size: 10px; font-weight: normal; opacity: 0.9;">(' . $dosDateStr . ')</small>' : '');
        }
        $dhqDosClaimedStr = !empty($dhqDosClaimedParts) ? implode(' | ', $dhqDosClaimedParts) : '₹0';

        // Format DHQ / DOS Verified String (ONLY if status is verified and has valid VerifyDate, otherwise —)
        $dhqDosVerifiedParts = [];
        if ($dhqVerifyAmt > 0 && !empty($dhqVerifyDateStr)) {
            $dhqDosVerifiedParts[] = 'DHQ: ₹' . number_format($dhqVerifyAmt, 0) . ' <small style="font-size: 10px; font-weight: normal; opacity: 0.9;">(' . $dhqVerifyDateStr . ')</small>';
        }
        if ($dosVerifyAmt > 0 && !empty($dosVerifyDateStr)) {
            $dhqDosVerifiedParts[] = 'DOS: ₹' . number_format($dosVerifyAmt, 0) . ' <small style="font-size: 10px; font-weight: normal; opacity: 0.9;">(' . $dosVerifyDateStr . ')</small>';
        }
        $dhqDosVerifiedStr = !empty($dhqDosVerifiedParts) ? implode(' | ', $dhqDosVerifiedParts) : '—';

        // Claim Status Mapping: strictly from y{year_id}_expenseclaims ClaimStatus column
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
            'eligibility' => $dhqDosEligStr,
            'same_date_claimed' => $mealClaimedStr,
            'same_date_claimed_amt' => $mealAmt,
            'same_date_verified' => $mealVerifiedStr,
            'same_date_verified_amt' => $mealVerifyAmt,
            'meal_claims_list' => $mealClaimsList,
            'dhq_dos_claimed' => $dhqDosClaimedStr,
            'dhq_dos_claimed_amt' => ($dhqAmt + $dosAmt),
            'dhq_dos_verified' => $dhqDosVerifiedStr,
            'dhq_dos_verified_amt' => ($dhqVerifyAmt + $dosVerifyAmt),
            'dhq_dos_claims_list' => $dhqDosClaimsList,
            'dhq_dathq_claimed' => $dhqDosClaimedStr,
            'dhq_dathq_claimed_amt' => ($dhqAmt + $dosAmt),
            'dhq_dathq_verified' => $dhqDosVerifiedStr,
            'dhq_dathq_verified_amt' => ($dhqVerifyAmt + $dosVerifyAmt),
            'dhq_dathq_claims_list' => $dhqDosClaimsList,
            'status' => $displayStatus,
            'status_badge' => $badgeColor,
        ];
    }
@endphp
<div id="punch-form-section">
    <div class="row mb-1 modal-ncgid" data-modal-ncgid="{{ $cgId }}">
    <div class="col-md-4">
        <small class="form-text text-muted">Bill Date <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="bill_date" name="bill_date"
            value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->BillDate ?? '') }}" />
    </div>
    <div class="col-md-4 mb-1">
        <small class="form-text text-muted">Amount <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="totalAmount" name="totalAmount"
            value="{{ $expense_detail->FilledTAmt ?? '' }}" style="min-width: 100%;" />
    </div>
    <div class="col-md-4 mb-1">
        <small class="form-text text-muted">Filled Amount (₹)</small>
        <input type="text" class="form-control" readonly value="{{ $expense_detail->punching_data->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" />
    </div>
    <div class="col-md-4 mb-1">
        <small class="form-text text-muted">Punching Remark</small>
        <input type="text" 
               class="form-control" 
               id="filledRemark" 
               name="filledRemark"
               value="{{ trim($expense_detail->Remark ?? '') !== '' ? $expense_detail->Remark : $defaultRemark }}"
               style="min-width: 100%;" />
    </div>
    </div>
</div>

<!-- Verifier / Finance Participant Summary Table (Same Date View) -->
@if(!empty($isVerifierView))
<div id="participant-verification-summary-section" class="w-100 my-3 clearfix" style="width: 100% !important; clear: both; display: block;">
    <div class="card border border-info shadow-sm mb-2 w-100" style="width: 100% !important;">
        <div class="card-header bg-info-subtle text-info fw-bold d-flex justify-content-between align-items-center py-2 px-3" style="font-size: 12px; width: 100%;">
            <span><i class="ri-user-shared-line me-1"></i> Participant Verification Summary - Same Date (Verifier &amp; Finance View)</span>
            <span class="badge bg-info text-white fw-bold">{{ count($verifierParticipantRows) }} Employee(s)</span>
        </div>
        <div class="card-body p-0 w-100" style="width: 100% !important;">
            <div class="table-responsive w-100" style="width: 100% !important; overflow-x: auto;">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-center w-100" style="font-size: 12px; width: 100% !important; table-layout: auto;">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-start ps-3">Employee</th>
                            <th>DHQ / DOS Eligibility</th>
                            <th>Meal Claimed</th>
                            <th>Meal Verified</th>
                            <th>DHQ / DOS Claimed</th>
                            <th>DHQ / DOS Verified</th>
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
                                            data-category="Meals"
                                            data-claims='@json($vRow["meal_claims_list"] ?? [])'
                                            style="cursor:pointer;"
                                            title="Click to view itemized claim details">
                                            {!! $vRow['same_date_claimed'] !!} <i class="ri-eye-line ms-1"></i>
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark">₹0</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($vRow['same_date_verified_amt'] ?? 0) > 0)
                                        <span class="badge bg-success-subtle text-success border border-success fw-bold">
                                            {!! $vRow['same_date_verified'] !!}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted">₹0</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($vRow['dhq_dathq_claimed_amt'] ?? 0) > 0)
                                        <span class="badge bg-warning text-dark view-same-day-claims-btn"
                                            data-emp-name="{{ $vRow['name'] }}"
                                            data-emp-code="{{ $vRow['code'] }}"
                                            data-bill-date="{{ $billDateDb }}"
                                            data-category="DHQ / DOS"
                                            data-claims='@json($vRow["dhq_dathq_claims_list"] ?? [])'
                                            style="cursor:pointer;"
                                            title="Click to view itemized claim details">
                                            {!! $vRow['dhq_dathq_claimed'] !!} <i class="ri-eye-line ms-1"></i>
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark">₹0</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($vRow['dhq_dathq_verified_amt'] ?? 0) > 0)
                                        <span class="badge bg-success-subtle text-success border border-success fw-bold">
                                            {!! $vRow['dhq_dathq_verified'] !!}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted">₹0</span>
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

<script>
    $(document).ready(function() {
        flatpickr("#bill_date", {
            dateFormat: "d-m-Y",
            maxDate: "today",
        });

        // Toggle between Punch Form and Chat
        $('.view-toggle').on('change', function() {
            if ($(this).val() === 'punch_form') {
                $('#punch-form-section').show();
                $('#chat-section').hide();
            } else {
                $('#punch-form-section').hide();
                $('#chat-section').show();
            }
        });

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
            const cat  = $(this).data('category') || 'Claim';
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
    });
</script>