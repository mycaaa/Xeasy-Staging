@extends('layouts.standalone')

@section('title', 'Account Summary')

@push('head')
    <!-- Bootstrap CSS -->
    <link href="{{ asset('vendor/cdn/bootstrap@5.3.2/dist/css/bootstrap.min.css') }}" rel="stylesheet">
    
    <!-- Icons -->
    <link href="{{ asset('vendor/cdn/bootstrap-icons@1.11.3/font/bootstrap-icons.css') }}" rel="stylesheet">

    <style>
        body { background:#f1f3f5; }
        .table thead th {
            background:#eef2f6;
            font-size:11px;
            vertical-align:middle;
        }
        .table tbody td {
            font-size:11px;
            font-weight: 500;
        }
           .table tfoot td {
            font-size:11px;
            font-weight: 500;
        }
        .text-purple { color:#6f42c1; }
        .card {
            border-radius:8px;
            box-shadow:0 2px 6px rgba(0,0,0,0.05);
        }
        .current-month {
            background-color: #fff3cd !important;
        }
        .adjustment-badge {
            font-size:9px;
            padding:2px 4px;
            border-radius:3px;
            background-color: #e7f3ff;
            color: #004085;
        }

        .adjustment-container {
    max-width: 250px;
}

.adjustment-item {
    border-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.adjustment-item.activity-item {
    border-left-width: 4px;
}

.adjustment-item.expense-item {
    border-left-width: 4px;
}

.adjustment-item .fw-bold {
    line-height: 1.2;
}

.adjustment-total {
    font-size: 10px;
}

/* .advance-container {
    background: #fff3cd;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
} */

.table-responsive {
    overflow-x: auto;
    overflow-y: hidden;
    white-space: nowrap;
}

.table-responsive table {
    min-width: 1400px; /* adjust based on total columns */
}

.table td, .table th {
    white-space: nowrap;
    vertical-align: middle;
}
    </style>
@endpush

@section('content')
<div class="container-fluid mt-3">
    <div class="card">
        <div class="card-header bg-white shadow-sm rounded-3 p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap">

        <!-- Left Side -->
        <div>
            <h5 class="mb-1 text-primary fw-bold">
                <i class="bi bi-receipt me-2"></i> Account Summary
            </h5>
            <small class="text-muted">Employee Financial Overview</small>
        </div>

        <!-- Right Side Employee Info -->
        <div class="text-end">
            <div class="fw-semibold text-dark">
                {{ getUser($employeeId) }}
            </div>

            <div class="mt-1">
                <span class="badge bg-primary me-2">
                    Code: {{ getCode($employeeId) }}
                </span>

                <span class="badge bg-success">
                    Grade: {{ getGrade($employeeId) }}
                </span>
            </div>
        </div>

    </div>
</div>

    <!-- KPI Summary Banner for Advances & Balances -->
    <div class="row g-2 p-3 pb-0">
        <div class="col-md">
            <div class="p-2 border rounded bg-light text-center h-100">
                <div class="text-muted text-uppercase fw-semibold" style="font-size:10px; letter-spacing:0.5px;">TOTAL ADVANCES PAID</div>
                <div class="fw-bold fs-6 text-primary mt-1">₹{{ number_format($totalAdvancesPaid ?? 0, 0) }}</div>
                @php
                    $actualAdvCount = 0;
                    if (isset($employeeAdvances)) {
                        foreach ($employeeAdvances as $a) {
                            $isOpening = is_array($a) ? ($a['is_ledger_opening'] ?? 0) : ($a->is_ledger_opening ?? 0);
                            if (empty($isOpening)) {
                                $actualAdvCount++;
                            }
                        }
                    }
                @endphp
                <small class="text-muted" style="font-size:9px;">{{ $actualAdvCount }} advance record(s)</small>
            </div>
        </div>
        <div class="col-md">
            <div class="p-2 border rounded bg-light text-center h-100">
                <div class="text-muted text-uppercase fw-semibold" style="font-size:10px; letter-spacing:0.5px;">TOTAL ADJUSTED</div>
                <div class="fw-bold fs-6 text-success mt-1">₹{{ number_format($totalAdvancesAdjusted ?? 0, 0) }}</div>
                <small class="text-muted" style="font-size:9px;">Against expense claims</small>
            </div>
        </div>
        <div class="col-md">
            <div class="p-2 border rounded h-100 {{ ($totalUnadjustedAdvance ?? 0) > 0 ? 'bg-danger bg-opacity-10 border-danger' : 'bg-light' }} text-center">
                <div class="text-muted text-uppercase fw-semibold" style="font-size:10px; letter-spacing:0.5px;">UNADJUSTED ADVANCE</div>
                <div class="fw-bold fs-6 mt-1 {{ ($totalUnadjustedAdvance ?? 0) > 0 ? 'text-danger' : 'text-dark' }}">
                    ₹{{ number_format($totalUnadjustedAdvance ?? 0, 0) }}
                </div>
                <small class="{{ ($totalUnadjustedAdvance ?? 0) > 0 ? 'text-danger fw-bold' : 'text-muted' }}" style="font-size:9px;">
                    {{ ($totalUnadjustedAdvance ?? 0) > 0 ? '⚠️ Pending Adjustment' : 'All adjusted' }}
                </small>
            </div>
        </div>
        {{-- <div class="col-md">
            <div class="p-2 border rounded bg-light text-center h-100">
                <div class="text-muted text-uppercase fw-semibold" style="font-size:10px; letter-spacing:0.5px;">LEDGER BALANCE</div>
                <div class="fw-bold fs-6 text-purple mt-1">₹{{ number_format($currentLedgerBalance ?? 0, 0) }}</div>
                <small class="text-muted" style="font-size:9px;">Opening Ledger</small>
            </div>
        </div> --}}
        <div class="col-md">
            <div class="p-2 border rounded bg-light text-center h-100">
                <div class="text-muted text-uppercase fw-semibold" style="font-size:10px; letter-spacing:0.5px;">FIXED IMPREST</div>
                <div class="fw-bold fs-6 text-info mt-1">
                    @php
                        $topFixedImprest = 0;
                        if(isset($fixedImprestRows) && $fixedImprestRows->isNotEmpty()) {
                            $topFixedImprest = (float)($fixedImprestRows->first()->fixed_imprest_amt_1 ?? 0);
                        }
                    @endphp
                    ₹{{ number_format($topFixedImprest, 0) }}
                </div>
                <small class="text-muted" style="font-size:9px;">Revolving Float</small>
            </div>
        </div>
    </div>

        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                  <thead class="text-center">
    <tr>
        <th rowspan="2">Month</th>

        <!-- Claims Section -->
        <th colspan="3">Claims</th>

        <!-- Finance Section -->
        <th colspan="4">Finance Amount</th>

        <!-- Payment Section -->
        <th rowspan="2" style="min-width: 180px;">Opening <br> Balance/Carried</th>

        <!-- Advance Adjustment Section -->
        <th colspan="3">Adjustment</th>

        <!-- Final Section -->
        <th rowspan="2">Payable</th>
        <th rowspan="2">Fixed Imprest</th>
    </tr>
    <tr>
        <!-- Claims -->
        <th>Claimed<br>Amount</th>
        <th>Verified<br>Amount</th>
        <th>Approved<br>Amount</th>

        <!-- Finance -->
        <th>Finance<br>Amount</th>
        <th>Expense<br>Fin-DA</th>
        <th>DA<br>Amount</th>

        <!-- Payment -->
        <th>Paid<br>Amount</th>

        <!-- Advance Adjustment -->
        <th style="min-width: 170px;">Advance<br><small class="text-muted fw-normal">(Received)</small></th>
        <th style="min-width: 170px;">Calculated<br><small class="text-muted fw-normal">(Adjusted)</small></th>
        <th style="min-width: 170px;">Balance<br><small class="text-muted fw-normal">(Remaining)</small></th>
    </tr>
</thead>

                    <tbody>
                        @php
                            /* ── All accumulators initialised as floats to avoid int+string errors ── */
                            $totals = [
                                'claimed'  => 0.0,
                                'verified' => 0.0,
                                'approved' => 0.0,
                                'finance'  => 0.0,
                                'expense'  => 0.0,
                                'da'       => 0.0,
                                'paid'     => 0.0,
                                'balance'  => 0.0,
                                'unadj_adv'=> 0.0,
                                'adj_adv'  => 0.0,
                            ];
                            $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            $currentMonth = (int) date('n');
                            
                            // Get fixed imprest amount (should be same for all months)
                            $fixedImprestAmount = 0.0;
                            $fixedImprestDate = null;
                            $fixedImprestAmount_two = 0.0;
                            $fixedImprestDate_two = null;
                            if(isset($fixedImprestRows) && $fixedImprestRows->isNotEmpty()) {
                                $firstRow = $fixedImprestRows->first();
                                $fixedImprestAmount = (float) ($firstRow->fixed_imprest_amt_1 ?? 0);
                                $fixedImprestDate = $firstRow->fixed_imprest_date_1 ?? null;

                                $fixedImprestAmount_two = (float) ($firstRow->fixed_imprest_amt_2 ?? 0);
                                $fixedImprestDate_two = $firstRow->fixed_imprest_date_2 ?? null;
                            }

                            // Build lookup map of all employee advances by activity_id
                            $advLookup = [];
                            if(isset($employeeAdvances)) {
                                foreach($employeeAdvances as $eadv) {
                                    $advLookup[(string)$eadv->activity_id] = $eadv;
                                }
                            }
                        @endphp

                        @foreach($expenseData as $index => $data)
                            @php
                                $month = (int) $data->Month;
                                $isCurrentMonth = ($month == $currentMonth);
                                $monthStatus = trim($data->Status ?? '');
                                $isOpenMonth = (strtolower($monthStatus) === 'open');

                                // Get adjustment values — all cast to float to prevent int+string errors
                                $advancePaymentAmount    = (float) ($data->advance_payment_amount    ?? 0);
                                $totalActivityAdjustment = (float) ($data->total_activity_adjustment ?? 0);
                                $actualExpenseAmount     = (float) ($data->actual_expense_amount     ?? 0);
                                $expenseAdjAmt           = (float) ($data->total_expense_adjustment  ?? 0);

                                // Main calculations — all cast to float
                                $claim    = (float) ($data->Claim_Amount    ?? 0);
                                $verified = (float) ($data->Verified_Amount ?? 0);
                                $approved = (float) ($data->Approved_Amount ?? 0);
                                $finance  = (float) ($data->Finance_Amount  ?? 0);
                                $da       = (float) ($daDataByMonth[$month] ?? 0);
                                $expense  = max(0.0, $finance - $da);
                                $paid     = (float) ($data->Fin_PayAmt      ?? 0);
                               
                                // Calculate remaining expense to be adjusted
                                $otherAdj = (float) ($data->other_adjustment ?? 0);
                                $balance = $finance - $paid - $totalActivityAdjustment - $da - $expenseAdjAmt - $otherAdj;

                                // Update totals
                                $totals['claimed']   += (float) $claim;
                                $totals['verified']  += (float) $verified;
                                $totals['approved']  += (float) $approved;
                                $totals['finance']   += (float) $finance;
                                $totals['expense']   += (float) $expense;
                                $totals['da']        += (float) $da;
                                $totals['paid']      += (float) $paid;
                                $totals['balance']   += (float) $balance;
                                $totals['unadj_adv'] += (float) $advancePaymentAmount;
                                $totals['adj_adv']   += (float) $totalActivityAdjustment;

                                // Advances disbursed in THIS month
                                $advancesInMonth = $advancesByMonth[$month] ?? [];
                                $advancesInMonth = array_filter($advancesInMonth, function($a) {
                                    if(!empty($a->is_imprest) && $a->is_imprest == 1) return false;
                                    if(stripos($a->purpose ?? '', 'imprest') !== false) return false;
                                    if(!empty($a->is_ledger_opening)) return false;
                                    return true;
                                });
                                $currentMonthActivityIds = [];
                                foreach($advancesInMonth as $mAdv) {
                                    if(!empty($mAdv->activity_id)) {
                                        $currentMonthActivityIds[(string)$mAdv->activity_id] = true;
                                    }
                                }

                                // Carried advances from PRIOR months into this month
                                $priorUnadjustedAdvances = [];
                                if(isset($employeeAdvances)) {
                                    foreach($employeeAdvances as $eadv) {
                                        if(!empty($eadv->is_imprest) && $eadv->is_imprest == 1) {
                                            continue;
                                        }
                                        if(stripos($eadv->purpose ?? '', 'imprest') !== false) {
                                            continue;
                                        }

                                        $eadvActId = (string)$eadv->activity_id;
                                        // Do not carry forward an advance that was disbursed in this current month
                                        if(!empty($eadvActId) && isset($currentMonthActivityIds[$eadvActId])) {
                                            continue;
                                        }

                                        $eadvDate = $eadv->payment_date ?: ($eadv->created_at ? substr($eadv->created_at, 0, 10) : null);
                                        if ($eadvDate && \App\Support\ZeroDate::has($eadvDate)) {
                                            $cYear = (int) date('Y', strtotime($eadvDate));
                                            $cMonth = (int) date('n', strtotime($eadvDate));
                                            $fyStart = ($cMonth >= 4) ? $cYear : ($cYear - 1);
                                            $eadvYear = max(1, $fyStart - 2018);
                                            $eadvMonth = $cMonth;
                                        } else {
                                            $eadvMonth = (int) ($eadv->request_month ?? 4);
                                            $eadvYear  = (int) ($eadv->year_id ?? $yearId);
                                        }

                                        // Standard financial year comparison: months 4-12, then 1-3
                                        $eadvRank = ($eadvYear * 100) + (($eadvMonth >= 4) ? $eadvMonth : $eadvMonth + 12);
                                        $currRank = ($yearId * 100) + (($month >= 4) ? $month : $month + 12);

                                        $isLedgerOpening = !empty($eadv->is_ledger_opening);

                                        // Only advances disbursed prior to the current month can be carried
                                        // (For ledger opening advances, they represent the opening balance at FY start, so eligible from Month 4 onwards)
                                        if(!$isLedgerOpening && $eadvRank >= $currRank) {
                                            continue;
                                        }

                                        // Calculate the advance's remaining balance at the start of this month
                                        $origAmt = (float) ($eadv->payment_amount ?: $eadv->amount);
                                        $adjBeforeThisMonth = 0.0;
                                        if(!empty($eadv->adjustments) && is_array($eadv->adjustments)) {
                                            foreach($eadv->adjustments as $adjRec) {
                                                $aY = (int) ($adjRec['year'] ?? 0);
                                                $aM = (int) ($adjRec['month'] ?? 0);
                                                $adjRank = ($aY * 100) + (($aM >= 4) ? $aM : $aM + 12);
                                                if($adjRank < $currRank) {
                                                    $adjBeforeThisMonth += (float) ($adjRec['amount'] ?? 0);
                                                }
                                            }
                                        } else {
                                            $adjBeforeThisMonth = (float) ($eadv->adjustment_amount ?? 0);
                                        }

                                        $balAtStart = max(0.0, $origAmt - $adjBeforeThisMonth);
                                        if($balAtStart > 0) {
                                            $cloneAdv = clone $eadv;
                                            $cloneAdv->current_carried_balance = $balAtStart;
                                            $priorUnadjustedAdvances[] = $cloneAdv;
                                        }
                                    }
                                }
                            @endphp

                            <tr class="{{ $isCurrentMonth ? 'current-month' : '' }}">
                                <td class="fw-bold">
                                    {{ $months[$month] ?? $month }}
                                    <div class="text-muted small">{{ $data->Status ?? 'Closed' }}</div>
                                </td>

                                <td class="text-end text-primary">
                                    {{ number_format($claim, 0) }}
                                    @if(!empty($data->Crdate) && \App\Support\ZeroDate::has($data->Crdate))
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($data->Crdate)->format('d M Y') }}</div>
                                    @endif
                                </td>

                                <td class="text-end text-purple">
                                    {{ number_format($verified, 0) }}
                                    @if(!empty($data->Verified_Date) && \App\Support\ZeroDate::has($data->Verified_Date))
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($data->Verified_Date)->format('d M Y') }}</div>
                                    @endif
                                </td>

                                <td class="text-end text-success">
                                    {{ number_format($approved, 0) }}
                                    @if(!empty($data->Approved_Date) && \App\Support\ZeroDate::has($data->Approved_Date))
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($data->Approved_Date)->format('d M Y') }}</div>
                                    @endif
                                </td>

                                <td class="text-end text-info">
                                    {{ number_format($finance, 0) }}
                                    @if(!empty($data->Finance_Date) && \App\Support\ZeroDate::has($data->Finance_Date))
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($data->Finance_Date)->format('d M Y') }}</div>
                                    @endif
                                </td>

                                <td class="text-end text-danger">
                                    {{ number_format($expense, 0) }}
                                </td>

                                <td class="text-end text-warning">
                                    {{ number_format($da, 0) }}
                                </td>

                                <td class="text-end text-success">
                                    {{ number_format($paid, 0) }}
                                    @if(!empty($data->Fin_PayDate) && \App\Support\ZeroDate::has($data->Fin_PayDate))
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($data->Fin_PayDate)->format('d M Y') }}</div>
                                    @endif
                                </td>

                                <!-- 1. Opening Balance / Carried Column (DEDUPLICATED BY ACTIVITY ID) -->
                                <td class="text-end" style="min-width: 180px;">
                                    @php
                                        $carriedByActivity = [];

                                        if (!$isOpenMonth) {
                                            // Source A: Snapshot carried advances
                                            $carriedData = function_exists('getCarriedAdvances') ? getCarriedAdvances($employeeId, $month, $yearId) : ['advances' => []];
                                            if(!empty($carriedData['advances'])) {
                                                foreach($carriedData['advances'] as $cAdv) {
                                                    $actId = (int)($cAdv['advance_id'] ?? 0);
                                                    $amt = (float)($cAdv['amount'] ?? 0);

                                                    // Skip imprest entries
                                                    if(!empty($cAdv['is_imprest']) && $cAdv['is_imprest'] == 1) {
                                                        continue;
                                                    }
                                                    if(strtolower($cAdv['category'] ?? '') === 'imprest' || stripos($cAdv['name'] ?? '', 'imprest') !== false || stripos($cAdv['purpose'] ?? '', 'imprest') !== false) {
                                                        continue;
                                                    }

                                                    // Skip if this activity was disbursed in the current month
                                                    if($actId > 0 && isset($currentMonthActivityIds[$actId])) {
                                                        continue;
                                                    }

                                                    if($amt > 0) {
                                                        $matchedAdv = (!empty($actId) && isset($advLookup[(string)$actId])) ? $advLookup[(string)$actId] : null;
                                                        $actName = '';
                                                        $isLedgerItem = false;
                                                        if($matchedAdv) {
                                                            if (!empty($matchedAdv->is_ledger_opening)) {
                                                                $actName = (!empty($matchedAdv->purpose) && $matchedAdv->purpose !== 'Ledger Balance') ? $matchedAdv->purpose : 'Opening Balance';
                                                                $isLedgerItem = true;
                                                            } else {
                                                                $actIdDisp = $matchedAdv->activity_id ?? $matchedAdv->id ?? $actId;
                                                                $actName = ($matchedAdv->activity_type ?? 'Advance') . ' #' . $actIdDisp;
                                                            }
                                                        } elseif(strtolower($cAdv['category'] ?? '') === 'ledger' || $actId === 0) {
                                                            $actName = 'Opening Balance';
                                                            $isLedgerItem = true;
                                                        } elseif($actId > 0) {
                                                            $actName = 'Advance #' . $actId;
                                                        } else {
                                                            $actName = function_exists('getActivityNameById') ? getActivityNameById(0) : 'Advance';
                                                            if(empty($actName) || $actName === 'Act :') {
                                                                $actName = 'Advance';
                                                            }
                                                        }

                                                        $key = !empty($actId) ? (string)$actId : ('unk_' . count($carriedByActivity));
                                                        $carriedByActivity[$key] = [
                                                            'activity_id' => $actId,
                                                            'name'        => $actName,
                                                            'type'        => $isLedgerItem ? 'Opening Balance' : ucfirst($cAdv['category'] ?? 'Carried'),
                                                            'amount'      => $amt,
                                                            'original'    => (float)($cAdv['original'] ?? ($matchedAdv ? $matchedAdv->payment_amount : $amt)),
                                                            'date'        => $cAdv['payment_date'] ?? ($matchedAdv ? $matchedAdv->payment_date : null),
                                                            'is_opening'  => $isLedgerItem,
                                                        ];
                                                    }
                                                }
                                            }

                                            // Source B: Prior unadjusted advances (DEDUPLICATE: only show once per activity id)
                                            if(!empty($priorUnadjustedAdvances)) {
                                                foreach($priorUnadjustedAdvances as $puAdv) {
                                                    $actId = (string)$puAdv->activity_id;

                                                    // Skip if this activity was disbursed in the current month
                                                    if(!empty($actId) && isset($currentMonthActivityIds[$actId])) {
                                                        continue;
                                                    }

                                                    $puBal = isset($puAdv->current_carried_balance)
                                                        ? (float)$puAdv->current_carried_balance
                                                        : (($puAdv->remaining_balance !== null) 
                                                            ? (float)$puAdv->remaining_balance 
                                                            : max(0.0, (float)$puAdv->payment_amount - (float)$puAdv->adjustment_amount));

                                                    if($puBal > 0) {
                                                        $key = (string)$actId;
                                                        if(isset($carriedByActivity[$key])) {
                                                            continue;
                                                        }

                                                        // Also check if an entry in Source A already matches this amount (legacy/mismatched ID)
                                                        $foundMatch = false;
                                                        foreach($carriedByActivity as $cKey => &$cVal) {
                                                            if(abs((float)$cVal['amount'] - $puBal) < 0.01) {
                                                                if(empty($cVal['activity_id']) || $cVal['activity_id'] == '0') {
                                                                    $cVal['activity_id'] = $actId;
                                                                    if (!empty($puAdv->is_ledger_opening)) {
                                                                        $cVal['name'] = (!empty($puAdv->purpose) && $puAdv->purpose !== 'Ledger Balance') ? $puAdv->purpose : 'Opening Balance';
                                                                        $cVal['type'] = 'Opening Balance';
                                                                        $cVal['is_opening'] = true;
                                                                    } else {
                                                                        $cVal['name'] = ($puAdv->activity_type ?? 'Advance') . ' #' . $actId;
                                                                    }
                                                                }
                                                                $foundMatch = true;
                                                                break;
                                                            }
                                                        }
                                                        unset($cVal);

                                                        if(!$foundMatch) {
                                                            $isOpening = !empty($puAdv->is_ledger_opening);
                                                            $dispName = $isOpening
                                                                ? ((!empty($puAdv->purpose) && $puAdv->purpose !== 'Ledger Balance') ? $puAdv->purpose : 'Opening Balance')
                                                                : (($puAdv->activity_type ?? 'Advance') . ' #' . $puAdv->activity_id);

                                                            $carriedByActivity[$key] = [
                                                                'activity_id' => $actId,
                                                                'name'        => $dispName,
                                                                'type'        => $isOpening ? 'Opening Balance' : 'Carried',
                                                                'amount'      => $puBal,
                                                                'original'    => (float)$puAdv->payment_amount,
                                                                'date'        => $puAdv->payment_date,
                                                                'is_opening'  => $isOpening,
                                                            ];
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $carriedDetails = array_values($carriedByActivity);
                                        $carriedTotal = 0.0;
                                        foreach($carriedDetails as $cd) {
                                            $carriedTotal += (float)$cd['amount'];
                                        }
                                        $hasCarriedBalance = (!$isOpenMonth && $carriedTotal > 0 && count($carriedDetails) > 0);
                                    @endphp

                                    @if($hasCarriedBalance && count($carriedDetails) > 0)
                                        <div class="adjustment-container text-start">
                                            @foreach($carriedDetails as $carried)
                                                @php
                                                    $isOpeningItem = !empty($carried['is_opening']) || ($carried['type'] ?? '') === 'Opening Balance';
                                                @endphp
                                                <div class="adjustment-item mb-1 p-1" style="border-left: 3px solid {{ $isOpeningItem ? '#6f42c1' : '#6f42c1' }}; background: {{ $isOpeningItem ? '#f8f5fd' : '#f8f5fd' }};">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-truncate" style="font-size: 10px; max-width: 120px;" title="{{ $carried['name'] }}">
                                                            {{ $carried['name'] }}
                                                        </span>
                                                        <span class="fw-bold text-purple" style="font-size: 11px;">
                                                            ₹{{ number_format($carried['amount'], 0) }}
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 8px;">
                                                        <span>Orig: ₹{{ number_format($carried['original'], 0) }}</span>
                                                        @if(!empty($carried['date']) && \App\Support\ZeroDate::has($carried['date']))
                                                            <span>{{ \Carbon\Carbon::parse($carried['date'])->format('d M y') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                            @if(count($carriedDetails) > 1)
                                                <div class="text-end pt-1" style="border-top: 1px dashed #ccc; font-size: 10px;">
                                                    <span class="fw-bold text-purple">Total: ₹{{ number_format($carriedTotal, 0) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- 2. Advance (Received / Disbursed this Month) -->
                                <td class="text-end" style="min-width: 170px;">
                                    @php
                                        $hasMonthAdvance = (!$isOpenMonth && count($advancesInMonth) > 0);
                                        $monthAdvTotal = 0;
                                    @endphp

                                    @if($hasMonthAdvance)
                                        <div class="adjustment-container text-start">
                                            @foreach($advancesInMonth as $mAdv)
                                                @php
                                                    $mPaid = (float)($mAdv->payment_amount ?: $mAdv->amount);
                                                    $monthAdvTotal += $mPaid;
                                                @endphp
                                                <div class="adjustment-item mb-1 p-1" style="border-left: 3px solid #0d6efd; background: #edf4ff;">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-truncate" style="font-size: 10px; max-width: 110px;" title="{{ $mAdv->purpose ?: ($mAdv->activity_type ?? 'Advance') }}">
                                                            {{ $mAdv->activity_type ?? 'Advance' }} #{{ $mAdv->activity_id }}
                                                        </span>
                                                        <span class="fw-bold text-primary" style="font-size: 11px;">
                                                            ₹{{ number_format($mPaid, 0) }}
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-between text-muted" style="font-size: 8px;">
                                                        <span>Paid: {{ $mAdv->payment_date ? \Carbon\Carbon::parse($mAdv->payment_date)->format('d M y') : 'N/A' }}</span>
                                                        <span class="badge bg-primary bg-opacity-75" style="font-size: 7px;">{{ $mAdv->payment_status }}</span>
                                                    </div>
                                                    @if(!empty($mAdv->purpose))
                                                        <div class="text-muted text-truncate" style="font-size: 8px; max-width: 150px;" title="{{ $mAdv->purpose }}">
                                                            {{ $mAdv->purpose }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- 3. Calculated / Adjusted Column -->
                                <td class="text-end" style="min-width: 190px;">
                                    @php
                                        $hasAdjustments     = false;
                                        $activityTotal      = 0.0;
                                        $expenseTotal       = 0.0;
                                        $activityDetails    = [];
                                        $expenseDetails     = [];
                                        $seenAdjActivityIds = [];
                                    @endphp

                                    @if(!$isOpenMonth && !empty($data->activity_adjustments_json) && $data->activity_adjustments_json != 'null' && $data->activity_adjustments_json != '{}')
                                        @php
                                            $adjustments = json_decode($data->activity_adjustments_json, true);
                                            if(is_array($adjustments)) {
                                                foreach($adjustments as $adjKey => $adj) {
                                                    if(isset($adj['amount']) && $adj['amount'] > 0) {
                                                        $hasAdjustments  = true;
                                                        $adjAmt          = (float) $adj['amount'];
                                                        $activityTotal  += $adjAmt;
                                                        $rawActId        = (string)($adj['advance_activity_id'] ?? '');
                                                        $adjActId        = $rawActId;

                                                        // If it's a carried adjustment (e.g. 'carried_0'), resolve from $carriedDetails
                                                        $matchedCarried = null;
                                                        if(empty($adjActId) || $adjActId === '0' || strpos($rawActId, 'carried_') === 0 || strpos((string)$adjKey, 'carried_') === 0) {
                                                            $cIdx = (int) str_replace('carried_', '', $rawActId);
                                                            $matchedCarried = $carriedDetails[$cIdx] ?? ($carriedDetails[0] ?? null);
                                                            if($matchedCarried && !empty($matchedCarried['activity_id'])) {
                                                                $adjActId = (string)$matchedCarried['activity_id'];
                                                            }
                                                        }

                                                        if(!empty($adjActId)) {
                                                            $seenAdjActivityIds[(string)$adjActId] = true;
                                                        }

                                                        // Lookup advance details from advLookup or fallback query
                                                        $matchedAdjAdv = (!empty($adjActId) && isset($advLookup[(string)$adjActId])) ? $advLookup[(string)$adjActId] : null;
                                                        if(!$matchedAdjAdv && is_numeric($adjActId) && (int)$adjActId > 0) {
                                                            $matchedAdjAdv = DB::connection(session('expense_connection') ?: 'expense')
                                                                ->table('advance_request_activities as ara')
                                                                ->leftJoin('adv_activity_types as aat', 'ara.activity_type_id', '=', 'aat.id')
                                                                ->where('ara.id', (int)$adjActId)
                                                                ->select(['ara.*', 'ara.id as activity_id', 'aat.type_name as activity_type'])
                                                                ->first();
                                                            if($matchedAdjAdv) {
                                                                $advLookup[(string)$adjActId] = $matchedAdjAdv;
                                                            }
                                                        }

                                                        $matchedActId = $matchedAdjAdv ? ($matchedAdjAdv->activity_id ?? $matchedAdjAdv->id ?? $adjActId) : $adjActId;
                                                        if($matchedAdjAdv) {
                                                            if (!empty($matchedAdjAdv->is_ledger_opening)) {
                                                                $activityName = (!empty($matchedAdjAdv->purpose) && $matchedAdjAdv->purpose !== 'Ledger Balance') ? $matchedAdjAdv->purpose : 'Opening Balance';
                                                            } else {
                                                                $activityName = ($matchedAdjAdv->activity_type ?? 'Advance') . ' #' . $matchedActId;
                                                            }
                                                        } elseif($matchedCarried) {
                                                            $activityName = $matchedCarried['name'];
                                                        } else {
                                                            $activityName = function_exists('getActivityNameById') && is_numeric($adjActId) ? getActivityNameById((int)$adjActId) : 'Activity';
                                                        }

                                                        if(empty($activityName) || $activityName === 'Act :') {
                                                            $activityName = !empty($adjActId) ? ('Advance #' . $adjActId) : 'Activity Adj';
                                                        }

                                                        $origAmt    = (float) ($adj['original_amount'] ?? ($matchedCarried['original'] ?? ($matchedAdjAdv ? ($matchedAdjAdv->payment_amount ?: $matchedAdjAdv->amount) : $adjAmt)));
                                                        $remAmt     = isset($adj['remaining_amount']) ? (float)$adj['remaining_amount'] : ($matchedCarried ? max(0.0, (float)$matchedCarried['amount'] - $adjAmt) : ($matchedAdjAdv ? (float)$matchedAdjAdv->remaining_balance : max(0.0, $origAmt - $adjAmt)));
                                                        $adjDate    = $matchedAdjAdv ? $matchedAdjAdv->adjustment_date : ($data->Fin_PayDate ?: null);
                                                        $adjRemarks = $matchedAdjAdv ? $matchedAdjAdv->adjustment_remarks : null;
                                                        $pDate      = $matchedAdjAdv ? $matchedAdjAdv->payment_date : ($matchedCarried['date'] ?? null);
                                                        $purpose    = $matchedAdjAdv ? $matchedAdjAdv->purpose : ($matchedCarried['type'] ?? 'Carried Adjustment');

                                                        $activityDetails[] = [
                                                            'name'           => $activityName,
                                                            'amount'         => $adjAmt,
                                                            'original'       => $origAmt,
                                                            'remaining'      => $remAmt,
                                                            'date'           => $adjDate,
                                                            'remarks'        => $adjRemarks,
                                                            'disbursed_date' => $pDate,
                                                            'purpose'        => $purpose,
                                                            'id'             => $adjActId,
                                                        ];
                                                    }
                                                }
                                            }
                                        @endphp
                                    @endif

                                    @if(!empty($data->expense_adjustments_json) && $data->expense_adjustments_json != 'null' && $data->expense_adjustments_json != '{}')
                                        @php
                                            $expAdjustments = json_decode($data->expense_adjustments_json, true);
                                            if(is_array($expAdjustments)) {
                                                foreach($expAdjustments as $expKey => $exp) {
                                                    if(isset($exp['amount']) && $exp['amount'] > 0) {
                                                        $hasAdjustments = true;
                                                        $expActId       = (int) ($exp['advance_activity_id'] ?? $expKey);
                                                        if($expActId > 0) {
                                                            $seenAdjActivityIds[$expActId] = true;
                                                        }
                                                        $matchedExpAdv = ($expActId > 0 && isset($advLookup[$expActId])) ? $advLookup[$expActId] : null;
                                                        if(!$matchedExpAdv && $expActId > 0) {
                                                            $matchedExpAdv = DB::connection(session('expense_connection') ?: 'expense')
                                                                ->table('advance_request_activities as ara')
                                                                ->leftJoin('adv_activity_types as aat', 'ara.activity_type_id', '=', 'aat.id')
                                                                ->where('ara.id', $expActId)
                                                                ->select(['ara.*', 'ara.id as activity_id', 'aat.type_name as activity_type'])
                                                                ->first();
                                                            if($matchedExpAdv) {
                                                                $advLookup[$expActId] = $matchedExpAdv;
                                                            }
                                                        }
                                                        $expActIdDisplay = $matchedExpAdv ? ($matchedExpAdv->activity_id ?? $matchedExpAdv->id ?? $expActId) : $expActId;
                                                        $expName = $matchedExpAdv ? (($matchedExpAdv->activity_type ?? 'Expense Adj') . ' #' . $expActIdDisplay) : ($exp['expense_name'] ?? ('Expense Adj #' . $expActId));

                                                        $expAmt        = (float) $exp['amount'];
                                                        $expenseTotal += $expAmt;
                                                        $origAmt       = (float) ($exp['original_amount'] ?? ($matchedExpAdv ? ($matchedExpAdv->payment_amount ?: $matchedExpAdv->amount) : $expAmt));
                                                        $remAmt        = isset($exp['remaining_amount']) ? (float)$exp['remaining_amount'] : ($matchedExpAdv ? (float)$matchedExpAdv->remaining_balance : max(0.0, $origAmt - $expAmt));

                                                        $expenseDetails[] = [
                                                            'name'           => $expName,
                                                            'amount'         => $expAmt,
                                                            'original'       => $origAmt,
                                                            'remaining'      => $remAmt,
                                                            'date'           => $matchedExpAdv ? $matchedExpAdv->adjustment_date : null,
                                                            'remarks'        => $matchedExpAdv ? $matchedExpAdv->adjustment_remarks : null,
                                                            'disbursed_date' => $matchedExpAdv ? $matchedExpAdv->payment_date : null,
                                                            'purpose'        => $matchedExpAdv ? $matchedExpAdv->purpose : null,
                                                            'id'             => $expActId,
                                                        ];
                                                    }
                                                }
                                            }
                                        @endphp
                                    @endif

                                    {{-- Also check reconciled adjustments from $employeeAdvances for this year and month --}}
                                    @php
                                        if(isset($employeeAdvances)) {
                                            foreach($employeeAdvances as $eadv) {
                                                $isEadvPaid = strtolower(trim($eadv->payment_status ?? '')) === 'paid';
                                                if (!$isEadvPaid) continue;

                                                $eadvActId = (string)$eadv->activity_id;
                                                $matchedAdjForMonth = 0.0;
                                                if (!empty($eadv->adjustments) && is_array($eadv->adjustments)) {
                                                    foreach ($eadv->adjustments as $singleAdj) {
                                                        if ((int)($singleAdj['year'] ?? 0) === (int)$yearId && (int)($singleAdj['month'] ?? 0) === (int)$month) {
                                                            $matchedAdjForMonth += (float)($singleAdj['amount'] ?? 0);
                                                        }
                                                    }
                                                } elseif ((int)($eadv->year_id ?? 0) === (int)$yearId && (int)($eadv->adjusted_against_month ?? 0) === (int)$month) {
                                                    $matchedAdjForMonth = (float)($eadv->adjustment_amount ?? 0);
                                                }

                                                if($matchedAdjForMonth > 0 && !isset($seenAdjActivityIds[$eadvActId])) {
                                                    $hasAdjustments = true;
                                                    $seenAdjActivityIds[$eadvActId] = true;
                                                    $activityTotal += $matchedAdjForMonth;

                                                    $origAmt = (float)($eadv->payment_amount ?: $eadv->amount);
                                                    $remAmt  = ($eadv->remaining_balance !== null) ? (float)$eadv->remaining_balance : max(0.0, $origAmt - $matchedAdjForMonth);

                                                    $activityDetails[] = [
                                                        'name'           => ($eadv->activity_type ?? 'Advance') . ' #' . $eadvActId,
                                                        'amount'         => $matchedAdjForMonth,
                                                        'original'       => $origAmt,
                                                        'remaining'      => $remAmt,
                                                        'date'           => $eadv->adjustment_date,
                                                        'remarks'        => $eadv->adjustment_remarks,
                                                        'disbursed_date' => $eadv->payment_date,
                                                        'purpose'        => $eadv->purpose,
                                                        'id'             => $eadvActId,
                                                    ];
                                                }
                                            }
                                        }
                                    @endphp

                                    @if($hasAdjustments)
                                        <div class="adjustment-container text-start">
                                            @foreach($activityDetails as $activity)
                                                <div class="adjustment-item mb-2 p-1 rounded" style="border-left: 3px solid #198754; background: #f0fdf4; border-top: 1px solid #e1f2e7; border-right: 1px solid #e1f2e7; border-bottom: 1px solid #e1f2e7;">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-truncate text-success" style="font-size: 10px; max-width: 120px;" title="{{ $activity['name'] }}">
                                                            <i class="bi bi-check2-circle me-1"></i>{{ $activity['name'] }}
                                                        </span>
                                                        <span class="badge bg-success" style="font-size: 10px;">
                                                            -₹{{ number_format($activity['amount'], 0) }}
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 8px;">
                                                        <span><strong>Orig:</strong> ₹{{ number_format($activity['original'], 0) }}</span>
                                                        <span><strong>Rem:</strong> ₹{{ number_format($activity['remaining'], 0) }}</span>
                                                    </div>
                                                    @if(!empty($activity['disbursed_date']))
                                                        <div class="text-muted" style="font-size: 8px;">
                                                            <strong>Paid on:</strong> {{ \Carbon\Carbon::parse($activity['disbursed_date'])->format('d M Y') }}
                                                        </div>
                                                    @endif
                                                    @if(!empty($activity['date']))
                                                        <div class="text-muted" style="font-size: 8px;">
                                                            <strong>Adj Date:</strong> {{ \Carbon\Carbon::parse($activity['date'])->format('d M Y') }}
                                                        </div>
                                                    @endif
                                                    @if(!empty($activity['remarks']))
                                                        <div class="mt-1 text-success fst-italic" style="font-size: 8px; border-top: 1px dashed #c3e6cb; padding-top: 2px;">
                                                            <strong>Remark:</strong> {{ $activity['remarks'] }}
                                                        </div>
                                                    @endif
                                                    @if(!empty($activity['purpose']) && $activity['purpose'] !== $activity['remarks'])
                                                        <div class="text-muted text-truncate" style="font-size: 8px;" title="{{ $activity['purpose'] }}">
                                                            <strong>Purpose:</strong> {{ $activity['purpose'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach

                                            @foreach($expenseDetails as $expAdj)
                                                <div class="adjustment-item mb-2 p-1 rounded" style="border-left: 3px solid #0f5132; background: #f0fdf4; border-top: 1px solid #e1f2e7; border-right: 1px solid #e1f2e7; border-bottom: 1px solid #e1f2e7;">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-truncate text-success" style="font-size: 10px; max-width: 120px;" title="{{ $expAdj['name'] }}">
                                                            <i class="bi bi-receipt-cutoff me-1"></i>{{ $expAdj['name'] }}
                                                        </span>
                                                        <span class="badge bg-success" style="font-size: 10px;">
                                                            -₹{{ number_format($expAdj['amount'], 0) }}
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 8px;">
                                                        <span><strong>Orig:</strong> ₹{{ number_format($expAdj['original'], 0) }}</span>
                                                        <span><strong>Rem:</strong> ₹{{ number_format($expAdj['remaining'], 0) }}</span>
                                                    </div>
                                                    @if(!empty($expAdj['disbursed_date']))
                                                        <div class="text-muted" style="font-size: 8px;">
                                                            <strong>Paid on:</strong> {{ \Carbon\Carbon::parse($expAdj['disbursed_date'])->format('d M Y') }}
                                                        </div>
                                                    @endif
                                                    @if(!empty($expAdj['date']))
                                                        <div class="text-muted" style="font-size: 8px;">
                                                            <strong>Adj Date:</strong> {{ \Carbon\Carbon::parse($expAdj['date'])->format('d M Y') }}
                                                        </div>
                                                    @endif
                                                    @if(!empty($expAdj['remarks']))
                                                        <div class="mt-1 text-success fst-italic" style="font-size: 8px; border-top: 1px dashed #c3e6cb; padding-top: 2px;">
                                                            <strong>Remark:</strong> {{ $expAdj['remarks'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach

                                            @if(!$isOpenMonth && !empty($data->other_adjustment) && (float)$data->other_adjustment > 0)
                                                <div class="adjustment-item mb-1 p-1 rounded" style="border-left: 3px solid #fd7e14; background: #fff8f0; border: 1px solid #ffe8cc;">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold" style="font-size: 10px; color:#c05621;">
                                                            <i class="bi bi-dash-circle me-1"></i>Other Adj
                                                        </span>
                                                        <span class="badge bg-warning text-dark" style="font-size: 10px;">-₹{{ number_format((float)$data->other_adjustment, 0) }}</span>
                                                    </div>
                                                </div>
                                            @endif

                                            @if(count($activityDetails) + count($expenseDetails) > 1)
                                                <div class="text-end pt-1" style="border-top:1px dashed #ccc; font-size:10px;">
                                                    <span class="fw-bold text-success">Total: -₹{{ number_format($activityTotal + $expenseTotal + (float)($data->other_adjustment ?? 0), 0) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @elseif(!$isOpenMonth && $totalActivityAdjustment > 0)
                                        <div class="adjustment-item mb-1 p-1 rounded" style="border-left: 3px solid #198754; background: #f0fdf4;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold text-success" style="font-size: 10px;">Activity Adj</span>
                                                <span class="fw-bold text-success" style="font-size: 11px;">-₹{{ number_format($totalActivityAdjustment, 0) }}</span>
                                            </div>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- 4. Balance (Remaining Unadjusted Advance Balance) -->
                                <td class="text-end" style="min-width: 170px;">
                                    @php
                                        // Calculate month end closing advance balance without double-counting adjustments
                                        $monthAdj = ($activityTotal > 0 || $expenseTotal > 0)
                                            ? ($activityTotal + $expenseTotal + (float)($data->other_adjustment ?? 0))
                                            : ((float)($data->total_activity_adjustment ?? 0) + (float)($data->other_adjustment ?? 0));
                                        $closingAdvBalance = max(0.0, ($carriedTotal + $monthAdvTotal) - $monthAdj);
                                    @endphp

                                    @if(!$isOpenMonth && $closingAdvBalance > 0)
                                        <div class="adjustment-container text-start">
                                            <div class="p-1 rounded" style="background:#fff3cd; border-left: 3px solid #ffc107;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-semibold text-muted" style="font-size: 10px;">Unadjusted:</span>
                                                    <span class="fw-bold text-danger" style="font-size: 11px;">₹{{ number_format($closingAdvBalance, 0) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>

                                <!-- Payable Column -->
                                <td class="text-end fw-semibold {{ $balance < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ max(0, number_format($balance, 0)) }}
                                    @if(!empty($data->Fin_PayDate) && \App\Support\ZeroDate::has($data->Fin_PayDate))
                                        <div class="text-muted small">{{ \Carbon\Carbon::parse($data->Fin_PayDate)->format('d M Y') }}</div>
                                    @endif
                                </td>

                                <!-- Fixed Imprest Column -->
                                <td class="text-end">
                                    @if($fixedImprestAmount > 0)
                                        {{ number_format($fixedImprestAmount, 0) }}
                                        @if($fixedImprestDate)
                                            <div class="text-muted small">
                                                {{ \Carbon\Carbon::parse($fixedImprestDate)->format('d M Y') }}
                                            </div>
                                        @endif
                                    @endif
                                    @if($fixedImprestAmount_two > 0)
                                        <div class="mt-1">{{ number_format($fixedImprestAmount_two, 0) }}</div>
                                        @if($fixedImprestDate_two)
                                            <div class="text-muted small">
                                                {{ \Carbon\Carbon::parse($fixedImprestDate_two)->format('d M Y') }}
                                            </div>
                                        @endif
                                    @endif
                                    @if($fixedImprestAmount <= 0 && $fixedImprestAmount_two <= 0)
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="fw-bold">TOTAL</td>
                            <td class="text-end text-primary">{{ number_format($totals['claimed'], 0) }}</td>
                            <td class="text-end">{{ number_format($totals['verified'], 0) }}</td>
                            <td class="text-end text-success">{{ number_format($totals['approved'], 0) }}</td>
                            <td class="text-end text-info">{{ number_format($totals['finance'], 0) }}</td>
                            <td class="text-end text-danger">{{ number_format($totals['expense'], 0) }}</td>
                            <td class="text-end text-warning">{{ number_format($totals['da'], 0) }}</td>
                            <td class="text-end text-success">{{ number_format($totals['paid'], 0) }}</td>
                            <td class="text-end">-</td>
                            <td class="text-end text-primary">₹{{ number_format($totalAdvancesPaid ?? 0, 0) }}</td>
                            <td class="text-end text-success">₹{{ number_format($totalAdvancesAdjusted ?? 0, 0) }}</td>
                            <td class="text-end text-danger">₹{{ number_format($totalUnadjustedAdvance ?? 0, 0) }}</td>
                            <td class="text-end {{ $totals['balance'] < 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format(max(0, $totals['balance']), 0) }}
                            </td>
                            <td class="text-end">-</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- ADVANCES & ADJUSTMENTS OVERVIEW PANEL                         -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div class="card mt-3 mb-4">
        <div class="card-header bg-white shadow-sm rounded-3 p-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 text-primary fw-bold">
                    <i class="bi bi-wallet2 me-2"></i> Employee Advances & Adjustments Log
                </h6>
                <small class="text-muted">Complete record of advances disbursed, adjusted amounts, and outstanding balances</small>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-1">
                <span class="badge bg-primary me-1">Paid: ₹{{ number_format($totalAdvancesPaid ?? 0, 0) }}</span>
                <span class="badge bg-success me-1">Adjusted: ₹{{ number_format($totalAdvancesAdjusted ?? 0, 0) }}</span>
                <span class="badge {{ ($totalUnadjustedAdvance ?? 0) > 0 ? 'bg-danger' : 'bg-secondary' }} me-2">
                    Unadjusted: ₹{{ number_format($totalUnadjustedAdvance ?? 0, 0) }}
                </span>
                <button type="button" class="btn btn-sm btn-outline-success shadow-sm" onclick="exportAccountSummaryAdvancesTable()" title="Export Advances & Adjustments Log to Excel">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                </button>
            </div>
        </div>

        <div class="card-body p-2">
            @if(isset($employeeAdvances) && count($employeeAdvances) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover align-middle mb-0" id="advancesAccountSummaryTable" style="min-width: 1000px;">
                        <thead class="table-light text-center" style="font-size: 11px;">
                            <tr>
                                <th>#</th>
                                <th>Act ID</th>
                                <th>Req ID</th>
                                <th>Advance Type</th>
                                <th>Payment Date</th>
                                <th>Age</th>
                                <th>Paid Amount</th>
                                <th>Adjusted Amount</th>
                                <th>Unadjusted Balance</th>
                                <th>Status</th>
                                <th>Purpose / Remarks</th>
                                <th>UTR Number</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 11px;">
                            @php
                                $advIndex = 1;
                                $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            @endphp
                            @foreach($employeeAdvances as $adv)
                                @php
                                    $pAmt = (float)($adv->reconciled_paid ?? ($adv->payment_amount ?: $adv->amount));
                                    $aAmt = (float)($adv->reconciled_adjusted ?? ($adv->adjustment_amount ?? 0));
                                    $rBal = (float)($adv->reconciled_remaining ?? (($adv->remaining_balance !== null && $adv->remaining_balance !== '') ? $adv->remaining_balance : max(0.0, $pAmt - $aAmt)));
                                    
                                    $pDate = $adv->payment_date ?: ($adv->created_at ? substr($adv->created_at, 0, 10) : null);
                                    $ageDays = $pDate ? (int) floor(\Carbon\Carbon::parse($pDate)->diffInDays(now())) : 0;
                                    $isPaid = strtolower(trim($adv->payment_status ?? '')) === 'paid';
                                    $isOver2Months = ($ageDays > 60 && $rBal > 0 && $isPaid);

                                    if(!$isPaid) {
                                        $statusLabel = 'Pending Payment';
                                        $statusClass = 'bg-secondary';
                                    } elseif($rBal <= 0 || ($aAmt >= $pAmt && $pAmt > 0)) {
                                        $statusLabel = 'Fully Adjusted';
                                        $statusClass = 'bg-success';
                                    } elseif($aAmt > 0 && $rBal > 0) {
                                        $statusLabel = 'Partially Adjusted';
                                        $statusClass = 'bg-warning text-dark';
                                    } else {
                                        $statusLabel = 'Completely Unadjusted';
                                        $statusClass = 'bg-danger';
                                    }
                                @endphp
                                <tr class="{{ $isOver2Months ? 'table-warning' : '' }}">
                                    <td class="text-center">{{ $advIndex++ }}</td>
                                    <td class="text-center fw-bold">{{ $adv->activity_id }}</td>
                                    <td class="text-center text-muted">{{ $adv->request_id ?: '-' }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ $adv->activity_type ?: 'Activity Advance' }}
                                        </span>
                                        @if($adv->is_imprest)
                                            <span class="badge bg-info" style="font-size: 8px;">Imprest</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{ $pDate ? \Carbon\Carbon::parse($pDate)->format('d M Y') : 'N/A' }}
                                    </td>
                                    <td class="text-center">
                                        {{ $ageDays }}d
                                        @if($isOver2Months)
                                            <span class="badge bg-danger ms-1" style="font-size: 8px;" title="Over 2 months old & unadjusted">Over 2M</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold text-primary">₹{{ number_format($pAmt, 0) }}</td>
                                    <td class="text-end text-success">
                                        <div class="fw-bold">₹{{ number_format($aAmt, 0) }}</div>
                                        @if($aAmt > 0)
                                            <div class="mt-1" style="font-size: 8px;">
                                                @if(!empty($adv->claim_settlements) && is_array($adv->claim_settlements))
                                                    @foreach($adv->claim_settlements as $cs)
                                                        @php
                                                            $mNum = (int)($cs['month'] ?? 0);
                                                            $mName = $monthNames[$mNum] ?? ($mNum ? 'Month ' . $mNum : '');
                                                        @endphp
                                                        @if($mName)
                                                            <span class="badge bg-success bg-opacity-10 text-success border border-success d-inline-block me-1 mb-1" style="font-size: 8px;">
                                                                Month: {{ $mName }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                @elseif(!empty($adv->adjusted_against_month))
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success d-inline-block me-1 mb-1" style="font-size: 8px;">
                                                        Month: {{ $monthNames[$adv->adjusted_against_month] ?? $adv->adjusted_against_month }}
                                                    </span>
                                                @endif
                                                @if(!empty($adv->adjustment_date))
                                                    <div class="text-muted mt-1">
                                                        {{ \Carbon\Carbon::parse($adv->adjustment_date)->format('d M y') }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold {{ $rBal > 0 ? 'text-danger' : 'text-success' }}">
                                        ₹{{ number_format($rBal, 0) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $statusClass }}" style="font-size: 9px;">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="text-wrap" style="max-width: 250px;">
                                        <div>{{ $adv->purpose ?: '-' }}</div>
                                        @if(!empty($adv->adjustment_remarks))
                                            <div class="text-success mt-1" style="font-size: 9px;">
                                                <i class="bi bi-check2-all me-1"></i>{{ $adv->adjustment_remarks }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center text-muted small">
                                        {{ $adv->utr_number ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold" style="font-size: 11px;">
                            <tr>
                                <td colspan="6" class="text-center">TOTAL</td>
                                <td class="text-end text-primary">₹{{ number_format($totalAdvancesPaid ?? 0, 0) }}</td>
                                <td class="text-end text-success">₹{{ number_format($totalAdvancesAdjusted ?? 0, 0) }}</td>
                                <td class="text-end {{ ($totalUnadjustedAdvance ?? 0) > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($totalUnadjustedAdvance ?? 0, 0) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                    No advances on record for this employee.
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="{{ asset('vendor/cdn/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/xlsx@0.18.5/dist/xlsx.full.min.js') }}"></script>
<script>
function exportAccountSummaryAdvancesTable() {
    const tbl = document.getElementById('advancesAccountSummaryTable');
    if (!tbl) {
        alert('Advances table not found.');
        return;
    }
    if (typeof XLSX === 'undefined') {
        alert('Excel library is loading, please try again.');
        return;
    }
    const wb = XLSX.utils.table_to_book(tbl, { sheet: "Advances & Adjustments" });
    const empCode = "{{ $employeeId ?? 'Emp' }}";
    const dateStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `Advances_Log_${empCode}_${dateStr}.xlsx`);
}
</script>
@endsection
