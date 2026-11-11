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
    $defaultRemark = 'Meals';

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
    $paxOtherStr   = $expense_detail->punching_data->PaxDetails_Other ?? $expense_detail->PaxDetails_Other ?? '';
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
    $isGroupMeal = true; // Claim ID 17 is Group Meal claim, always enable by default

    $masterParticipantCategories = ['Retailer', 'Dealer', 'Distributor', 'Consultant', 'Management', 'Guest', 'Other Employee not available in the list'];
    try {
        \App\Models\OtherParticipantCategory::ensureTableExists();
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

    // 2. Discover related Group Meal Claims on the same date where claimant is CrBy OR participant in PaxDetails
    $verifiedEmpIds = [$claimantEmpId];
    $paxCodesToResolve = $cleanPaxIds;

    $mealClaimsOnDate = collect();
    $mealFillMap = [];
    if (!empty($billDateDb)) {
        try {
            $mealClaimsOnDate = DB::connection($expConn)->table("y{$yearIdVal}_expenseclaims")
                ->where('ClaimId', 17)
                ->where('BillDate', $billDateDb)
                ->whereNotIn('ClaimStatus', ['Deactivate', 'D', 'Deleted'])
                ->get(['ExpId', 'CrBy', 'FilledTAmt', 'Amount', 'ClaimStatus', 'BillDate']);

            $mealExpIds = $mealClaimsOnDate->pluck('ExpId')->toArray();
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
                    $mealClaimsList[] = [
                        'exp_id' => $mc->ExpId,
                        'claim_id' => 17,
                        'claim_name' => 'Meals',
                        'amount' => $amt,
                        'verify_amount' => $vAmt,
                        'date' => $mc->BillDate,
                        'verify_date' => ($isMcVerified && $hasValidVerifyDate) ? $mc->VerifyDate : '',
                        'status' => $mc->ClaimStatus ?? 'Claimed'
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
    <div class="row modal-ncgid" data-modal-ncgid="{{ $cgId }}">
        <div class="col-md-4 mb-3">
            <small class="form-text text-muted">Bill Date <span class="text-danger">*</span></small>
            <input type="text" class="form-control" value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->BillDate ?? $expense_detail->BillDate ?? '') }}"
                id="bill_date" name="bill_date" />
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

        <div class="col-12 mb-3">
            <table class="table table-bordered" id="items_table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">Sr. No.</th>
                        <th>Particulars</th>
                        <th>Remark</th>
                        <th>Amount</th>
                        <th style="width: 80px;">
                            <button type="button" class="btn btn-sm btn-success" id="add_item">
                                <i class="ri-add-circle-fill"></i>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($expense_detail->punching_data->particulars) && count($expense_detail->punching_data->particulars) > 0)
                    @foreach($expense_detail->punching_data->particulars as $index => $item)
                    <tr>
                        <td class="text-center sr-no">{{ $index + 1 }}</td>
                        <td>
                            <input type="text" name="particulars[]" class="form-control"
                                value="{{ $item->Title }}" placeholder="Particulars" />
                        </td>
                        <td>
                            <input type="text" name="remark[]" class="form-control"
                                value="{{ $item->Remark }}" placeholder="Remark" />
                        </td>
                        <td>
                            <input type="number" name="amount[]" class="form-control amount_input"
                                value="{{ $item->Amount }}" placeholder="Amount" />
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-danger remove_item">
                                <i class="ri-delete-bin-fill"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                    @else
                    <tr>
                        <td class="text-center sr-no">1</td>
                        <td><input type="text" name="particulars[]" class="form-control" placeholder="Particulars" aria-label="Particulars" /></td>
                        <td><input type="text" name="remark[]" class="form-control" placeholder="Remark" aria-label="Remark" /></td>
                        <td><input type="number" name="amount[]" class="form-control amount_input" placeholder="Amount" aria-label="Amount" /></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-danger remove_item">
                                <i class="ri-delete-bin-fill"></i>
                            </button>
                        </td>
                    </tr>
                    @endif
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total:</th>
                        <th colspan="2">
                            <input type="text" id="totalAmount" class="form-control" readonly value="{{ $expense_detail->punching_data->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" />
                        </th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Filled Amount (₹):</th>
                        <th colspan="2">
                            <input type="text" class="form-control" readonly value="{{ $expense_detail->punching_data->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" />
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Group Meal Details Section (Editable Form Controls for Mediator / Data Punch User) -->
        @if(empty($isVerifierView))
            <div class="card border-success shadow-sm" id="modalGroupMealSection">
                @php
                    $initPaxCount = !empty($paxVal) ? (int)$paxVal : (count(array_filter(explode(',', $paxDetailsStr))));
                @endphp
                <div class="card-header bg-success-subtle text-success fw-bold d-flex justify-content-between align-items-center py-2">
                    <span><i class="ri-restaurant-line me-1"></i> Group Meal Details</span>
                    <span class="badge bg-success" id="modalMealParticipantCount" style="{{ $initPaxCount > 0 ? '' : 'display:none;' }}">Participants: {{ $initPaxCount }}</span>
                </div>
                <div class="card-body p-3">
                    <div class="row mb-3">
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="modalGroupMealCheckbox" name="group_meal" value="true" {{ $isGroupMeal ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold text-success ms-2 fs-13" for="modalGroupMealCheckbox">
                                    Enable Group Meal Claim
                                </label>
                            </div>
                        </div>
                    </div>
                    <div id="modalGroupMealFields" style="{{ $isGroupMeal ? 'display:block;' : 'display:none;' }}">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold fs-12">PAX No. <span class="text-danger meal-pax-asterisk" style="display: {{ $isGroupMeal ? 'inline' : 'none' }};">*</span></label>
                                <input type="number" name="PaxDetails" id="modalMealPaxInput" class="form-control form-control-sm" min="1" placeholder="Enter PAX" aria-label="Enter PAX" value="{{ $paxVal }}" {{ $isGroupMeal ? 'required' : '' }}>
                                <small class="text-muted d-block mt-1" style="font-size: 11px;"><i class="ri-information-line text-info me-1"></i>Enter the details excluding yourself</small>
                            </div>
                        </div>

                        <!-- 1. Employee Search Section -->
                        <div id="modalGroupMealParticipants" class="mb-3">
                            <label class="form-label fw-semibold small text-primary mb-1 fs-12">
                                <i class="ri-user-search-line me-1"></i> Select Employee Participants <span class="text-muted fw-normal ms-1" style="font-size: 11px;">(Enter the details excluding yourself)</span>
                            </label>
                            <div class="row mb-2">
                                <div class="col-md-8">
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="modalMealEmployeeSearch" class="form-control" placeholder="Search by Employee Code, Name, or Grade" aria-label="Search by Employee Code, Name, or Grade">
                                        <button class="btn btn-outline-success" type="button" id="modalMealSearchBtn">
                                            <i class="ri-search-line"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="modalMealSearchResults" class="list-group mb-2" style="max-height:160px;overflow-y:auto;"></div>
                            <div id="modalMealSelectedList" class="mb-2">
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
                                        <span class="badge bg-success me-1 mb-1 p-2">
                                            @if(!empty($displayName))
                                                {{ $displayName }} [{{ $displayCode }}]{!! $metaStr !!}
                                            @else
                                                Employee ({{ $displayCode }})
                                            @endif
                                            <i class="ri-close-line modal-meal-remove-emp ms-1" data-id="{{ $pIdTrim }}" data-code="{{ $displayCode }}" style="cursor:pointer;"></i>
                                        </span>
                                    @endforeach
                                @endif
                            </div>
                            <input type="hidden" name="meal_participants" id="modalMealParticipantsInput" value="{{ $cleanPaxStr }}">
                        </div>

                        <!-- 2. Other Participants (Sequence: Category -> No. of Other Pax -> Add Other Participant) -->
                        <div class="card border border-dashed p-3 mb-2 bg-light">
                            <div class="mb-2">
                                <label class="form-label fw-bold mb-0 text-dark small fs-12">
                                    <i class="ri-user-add-line text-success me-1"></i> Other Participants (Non-Employee / External Categories)
                                </label>
                                <div class="text-muted" style="font-size: 11px;">Select category and enter headcount to add external participants (Enter the details excluding yourself).</div>
                            </div>
                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold mb-1">Category <span class="text-danger">*</span></label>
                                    <select id="modalNewOtherMealCategory" class="form-select form-select-sm">
                                        <option value="">-- Select Category --</option>
                                        @foreach($masterParticipantCategories as $mCat)
                                            <option value="{{ $mCat }}">{{ $mCat }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @php
                                    $initialOtherCount = !empty($parsedOtherList) ? count($parsedOtherList) : 1;
                                @endphp
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">No. of Other Pax <span class="text-danger">*</span></label>
                                    <input type="number" id="modalNewOtherMealPaxCount" class="form-control form-control-sm" min="1" value="{{ max(1, $initialOtherCount) }}" placeholder="Pax Count" aria-label="Pax Count">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-sm btn-success w-100" id="addModalOtherMealParticipantBtn">
                                        <i class="ri-add-line me-1"></i> Add Other Participant
                                    </button>
                                </div>
                            </div>
                            <div id="modalOtherMealParticipantsContainer">
                                @if(!empty($paxOtherStr))
                                    @php
                                        $parsedOtherList = \App\Helpers\CommonHelper::parseOtherParticipants($paxOtherStr);
                                    @endphp
                                    @foreach($parsedOtherList as $oItem)
                                        @php
                                            $cat = trim($oItem['category'] ?? 'Guest');
                                            $cntName = trim($oItem['name'] ?? '');
                                            $details = trim($oItem['details'] ?? '');
                                        @endphp
                                        @if(!empty($cntName))
                                            <div class="row g-2 align-items-center mb-2 modal-other-meal-row">
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control form-control-sm modal-other-name" placeholder="Participant Name *" aria-label="Participant Name *" value="{{ $cntName }}" readonly style="background-color: #f8f9fa;">
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-select form-select-sm modal-other-cat" disabled style="background-color: #f8f9fa;">
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
                                                    <input type="text" class="form-control form-control-sm modal-other-details" placeholder="Org / Mobile / Remarks (Optional)" aria-label="Org / Mobile / Remarks (Optional)" value="{{ $details }}" readonly style="background-color: #f8f9fa;">
                                                </div>
                                                <div class="col-md-2 text-end d-flex justify-content-end align-items-center">
                                                    <button type="button" class="btn btn-outline-primary btn-sm edit-modal-other-meal-row me-1" title="Edit">
                                                        <i class="ri-edit-line"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm remove-modal-other-meal-btn" title="Remove">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                            <input type="hidden" name="meal_other_participants" id="modalMealOtherParticipants" value="{{ $paxOtherStr }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
            {{-- Preserve existing PAX details during Verifier/Finance view save --}}
            <input type="hidden" name="meal_participants" value="{{ $cleanPaxStr ?? $paxDetailsStr }}">
            <input type="hidden" name="meal_other_participants" value="{{ $paxOtherStr }}">
            <input type="hidden" name="PaxDetails" value="{{ $paxVal }}">
        @endif
    </div>
</div> {{-- End of #punch-form-section --}}

<!-- Verifier / Finance Participant Summary Table (Standalone Full-Width Div Outside Form) -->
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
                                    @if(($vRow['same_date_verified_amt'] ?? 0) > 0 && !empty($vRow['same_date_verified']) && $vRow['same_date_verified'] !== '—')
                                        <span class="badge bg-success-subtle text-success border border-success fw-bold">
                                            {!! $vRow['same_date_verified'] !!}
                                        </span>
                                    @else
                                        <span class="text-muted fw-semibold">—</span>
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
                                    @if(($vRow['dhq_dathq_verified_amt'] ?? 0) > 0 && !empty($vRow['dhq_dathq_verified']) && $vRow['dhq_dathq_verified'] !== '—')
                                        <span class="badge bg-success-subtle text-success border border-success fw-bold">
                                            {!! $vRow['dhq_dathq_verified'] !!}
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
    {{-- Tracker #19: external (other) participants were only a hidden field on the verifier screen --}}
    <x-modal.claim_view.participants_panel title="Other participants (group meal)" :pax="$paxVal" :others="$paxOtherStr" />
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

        function updateTotal() {
            let total = 0;
            $(".amount_input").each(function() {
                const val = parseFloat($(this).val());
                if (!isNaN(val)) total += val;
            });
            $("#totalAmount").val(total.toFixed(2));
        }

        function updateSerialNumbers() {
            $("#items_table tbody tr").each(function(index) {
                $(this).find(".sr-no").text(index + 1);
            });
        }

        $("#add_item").click(function() {
            const newRow = `
                <tr>
                    <td class="text-center sr-no"></td>
                    <td><input type="text" name="particulars[]" class="form-control" placeholder="Particulars" aria-label="Particulars" /></td>
                    <td><input type="text" name="remark[]" class="form-control" placeholder="Remark" aria-label="Remark" /></td>
                    <td><input type="number" name="amount[]" class="form-control amount_input" placeholder="Amount" aria-label="Amount" /></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger remove_item">
                            <i class="ri-delete-bin-fill"></i>
                        </button>
                    </td>
                </tr>`;
            $("#items_table tbody").append(newRow);
            updateSerialNumbers();
            updateTotal();
        });

        $(document).on("click", ".remove_item", function() {
            if ($("#items_table tbody tr").length > 0) {
                $(this).closest("tr").remove();
                updateSerialNumbers();
                updateTotal();
            } else {
                showAlert("info", "ri-information-line", "At least one row is required");
            }
        });

        $(document).on("input", ".amount_input", function() {
            updateTotal();
        });

        updateSerialNumbers();
        updateTotal();

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

        // ─── Group Meal Modal Handlers ─────────────────────────────────────
        $(document).on("change", "#modalGroupMealCheckbox", function() {
            if ($(this).is(":checked")) {
                $("#modalGroupMealFields").slideDown(200);
                $("#modalMealPaxInput").prop("required", true);
                $(".meal-pax-asterisk").show();
            } else {
                $("#modalGroupMealFields").slideUp(200);
                $("#modalMealPaxInput").prop("required", false).removeClass("is-invalid");
                $(".meal-pax-asterisk").hide();
            }
        });

        // Employee Search Handler
        $(document).on("click", "#modalMealSearchBtn", function() {
            performModalEmployeeSearch();
        });

        $(document).on("keypress", "#modalMealEmployeeSearch", function(e) {
            if (e.which === 13) {
                e.preventDefault();
                performModalEmployeeSearch();
            }
        });

        function performModalEmployeeSearch() {
            const query = $("#modalMealEmployeeSearch").val().trim();
            if (query.length < 2) {
                alert("Please enter at least 2 characters to search.");
                return;
            }

            const claimantId = String($("#employee_id").val() || "{{ $claimantId }}").trim();
            const claimantCode = String("{{ session('emp_code') }}").trim();

            $.ajax({
                url: "{{ url('employees/search-all') }}",
                method: "POST",
                data: {
                    _token: '{{ csrf_token() }}',
                    search: query,
                    exclude_claimant: true
                },
                success: function(response) {
                    let html = '';
                    const employees = response.employees || response.data || (Array.isArray(response) ? response : []);
                    const currentIds = $("#modalMealParticipantsInput").val() ? $("#modalMealParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];

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

                            if (isAdded) {
                                html += `
                                    <div class="list-group-item d-flex justify-content-between align-items-center bg-light py-2">
                                        <div>
                                            <strong>${name}</strong> <code class="ms-1">${code}</code>
                                            <div class="text-muted fs-11">${dept} ${grade ? '| Grade: '+grade : ''}</div>
                                        </div>
                                        <span class="badge bg-secondary">Added</span>
                                    </div>
                                `;
                            } else {
                                html += `
                                    <button type="button" class="list-group-item list-group-item-action modal-add-meal-emp py-2" 
                                            data-code="${code}" data-newcode="${newCode}" data-id="${empId}" data-name="${name}" data-grade="${grade}" data-dept="${dept}">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${name}</strong> <code class="ms-1">${code}</code>
                                                <div class="text-muted fs-11">${dept} ${grade ? '| Grade: '+grade : ''}</div>
                                            </div>
                                            <span class="badge bg-soft-success text-success"><i class="ri-add-line me-1"></i>Add</span>
                                        </div>
                                    </button>
                                `;
                            }
                        });
                    } else {
                        html = '<div class="list-group-item text-muted text-center py-2">No matching employees found.</div>';
                    }
                    $("#modalMealSearchResults").html(html);
                },
                error: function() {
                    $("#modalMealSearchResults").html('<div class="list-group-item text-danger text-center py-2">Error searching employees.</div>');
                }
            });
        }

        // Add Employee Participant
        $(document).off("click", ".modal-add-meal-emp").on("click", ".modal-add-meal-emp", function() {
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

            let currentIds = $("#modalMealParticipantsInput").val() ? $("#modalMealParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];
            
            // PAX Limit Validation
            const paxLimit = parseInt($("#modalMealPaxInput").val()) || 0;
            const otherCount = $(".modal-other-meal-row").length;
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
            $("#modalMealParticipantsInput").val(currentIds.join(','));

            const dept = String($(this).data("dept") || "").trim();
            const grade = String($(this).data("grade") || "").trim();
            const detailsArray = [];
            if (dept) detailsArray.push(`Dept: ${dept}`);
            if (grade) detailsArray.push(`Grade: ${grade}`);
            const detailsStr = detailsArray.length > 0 ? ` <small style="font-size:11px;opacity:0.85;">(${detailsArray.join(' - ')})</small>` : '';

            const badgeHtml = `
                <span class="badge bg-success me-1 mb-1 p-2">
                    ${name} [${code}]${detailsStr}
                    <i class="ri-close-line modal-meal-remove-emp ms-1" data-id="${valToStore}" data-code="${code}" style="cursor:pointer;"></i>
                </span>
            `;
            $("#modalMealSelectedList").append(badgeHtml);
            $("#modalMealSearchResults").html("");
            $("#modalMealEmployeeSearch").val("");
            updateModalPaxCount();
        });

        // Remove Employee Participant
        $(document).off("click", ".modal-meal-remove-emp").on("click", ".modal-meal-remove-emp", function() {
            const targetId = String($(this).data("id") || $(this).data("code") || "").trim();
            let currentIds = $("#modalMealParticipantsInput").val() ? $("#modalMealParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];
            currentIds = currentIds.filter(id => id !== targetId);
            $("#modalMealParticipantsInput").val(currentIds.join(','));
            $(this).closest(".badge").remove();
            updateModalPaxCount();
        });

        var masterParticipantCategories = {!! json_encode($masterParticipantCategories) !!};

        function renderCategoryOptions(catVal) {
            let options = '<option value="">-- Select Role / Category * --</option>';
            masterParticipantCategories.forEach(cat => {
                const selected = catVal === cat ? 'selected' : '';
                options += `<option value="${cat}" ${selected}>${cat}</option>`;
            });
            return options;
        }

        // Edit Other Participant Row
        $(document).off("click", ".edit-modal-other-meal-row").on("click", ".edit-modal-other-meal-row", function(e) {
            e.preventDefault();
            const targetRow = $(this).closest(".modal-other-meal-row");
            const nameInput = targetRow.find(".modal-other-name");
            const catSelect = targetRow.find(".modal-other-cat");
            const detailInput = targetRow.find(".modal-other-details");
            const isEditing = $(this).hasClass("btn-success");

            if (isEditing) {
                // Done editing -> lock
                $(this).removeClass("btn-success").addClass("btn-outline-primary").attr("title", "Edit").html('<i class="ri-edit-line"></i>');
                nameInput.prop("readonly", true).css("background-color", "#f8f9fa");
                catSelect.prop("disabled", true).css("background-color", "#f8f9fa");
                detailInput.prop("readonly", true).css("background-color", "#f8f9fa");
                serializeModalOtherParticipants();
                updateModalPaxCount();
            } else {
                // Start editing -> unlock
                $(this).removeClass("btn-outline-primary").addClass("btn-success").attr("title", "Done").html('<i class="ri-check-line"></i>');
                nameInput.prop("readonly", false).css("background-color", "#ffffff").focus();
                catSelect.prop("disabled", false).css("background-color", "#ffffff");
                detailInput.prop("readonly", false).css("background-color", "#ffffff");
            }
        });

        // Add Other Participant Row (Sequence: Category -> No. of Other Pax -> Add Other Participant)
        $(document).off("click", "#addModalOtherMealParticipantBtn").on("click", "#addModalOtherMealParticipantBtn", function(e) {
            e.preventDefault();
            e.stopPropagation();
            const catVal = $("#modalNewOtherMealCategory").val();
            if (!catVal) {
                alert("Please select a Category first.");
                $("#modalNewOtherMealCategory").focus();
                return;
            }
            const desiredCount = parseInt($("#modalNewOtherMealPaxCount").val()) || 1;
            if (desiredCount <= 0) {
                alert("Number of Other Pax must be at least 1.");
                return;
            }

            const totalPax = parseInt($("#modalMealPaxInput").val()) || 0;
            const empCodes = $("#modalMealParticipantsInput").val() ? $("#modalMealParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];
            const currentOtherCount = $(".modal-other-meal-row").length;

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
                    <div class="row g-2 align-items-center mb-2 modal-other-meal-row">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm modal-other-name" placeholder="Participant Name *" aria-label="Participant Name *">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm modal-other-cat">
                                ${renderCategoryOptions(catVal)}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm modal-other-details" placeholder="Org / Mobile / Remarks (Optional)" aria-label="Org / Mobile / Remarks (Optional)">
                        </div>
                        <div class="col-md-2 text-end d-flex justify-content-end align-items-center">
                            <button type="button" class="btn btn-success btn-sm edit-modal-other-meal-row me-1" title="Done">
                                <i class="ri-check-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-modal-other-meal-btn" title="Remove">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                `;
                $("#modalOtherMealParticipantsContainer").append(rowHtml);
            }
            updateModalPaxCount();
        });

        // Remove Other Participant Row
        $(document).off("click", ".remove-modal-other-meal-btn").on("click", ".remove-modal-other-meal-btn", function(e) {
            e.preventDefault();
            $(this).closest(".modal-other-meal-row").remove();
            serializeModalOtherParticipants();
            updateModalPaxCount();
        });

        $(document).off("input change", ".modal-other-cat, .modal-other-name, .modal-other-details").on("input change", ".modal-other-cat, .modal-other-name, .modal-other-details", function() {
            serializeModalOtherParticipants();
        });

        function serializeModalOtherParticipants() {
            let list = [];
            $(".modal-other-meal-row").each(function() {
                const name = $(this).find(".modal-other-name").val().trim();
                const category = $(this).find(".modal-other-cat").val() || 'Guest';
                const details = $(this).find(".modal-other-details").val().trim();
                if (name) {
                    list.push({ name, category, details });
                }
            });
            $("#modalMealOtherParticipants").val(list.length > 0 ? JSON.stringify(list) : '');
        }

        function updateModalPaxCount() {
            const empCodes = $("#modalMealParticipantsInput").val() ? $("#modalMealParticipantsInput").val().split(',').map(c => c.trim()).filter(Boolean) : [];
            const uniqueCodes = Array.from(new Set(empCodes));
            $("#modalMealParticipantsInput").val(uniqueCodes.join(','));

            const otherRows = $(".modal-other-meal-row").length;
            const total = uniqueCodes.length + otherRows;
            const totalPax = parseInt($("#modalMealPaxInput").val()) || 0;
            if (totalPax <= 0 && total > 0) {
                $("#modalMealPaxInput").val(total);
            }
            const displayCount = parseInt($("#modalMealPaxInput").val()) || total;
            if (displayCount > 0) {
                $("#modalMealParticipantCount").show().text(`Participants: ${displayCount}`);
            } else {
                $("#modalMealParticipantCount").hide().text('');
            }

            // Restrict / auto hide add button based on desired headcount & total pax
            const desiredOtherPax = parseInt($("#modalNewOtherMealPaxCount").val()) || 1;
            const otherPaxFulfilled = otherRows >= desiredOtherPax;
            const totalPaxReached = totalPax > 0 && total >= totalPax;

            if (totalPaxReached || otherPaxFulfilled) {
                $("#addModalOtherMealParticipantBtn").hide();
            } else {
                $("#addModalOtherMealParticipantBtn").show();
            }
        }

        $(document).off("input change", "#modalMealPaxInput, #modalNewOtherMealPaxCount").on("input change", "#modalMealPaxInput, #modalNewOtherMealPaxCount", function() {
            updateModalPaxCount();
        });

        // Initialize state on load
        serializeModalOtherParticipants();
        updateModalPaxCount();

        // Same-day claims breakdown modal on clicking badge
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
