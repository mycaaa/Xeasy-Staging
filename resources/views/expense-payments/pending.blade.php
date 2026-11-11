{{-- resources/views/expense-payments/pending.blade.php --}}
@extends('layouts.app')
<script src="{{ asset('vendor/cdn/jquery@3.6.0/dist/jquery.min.js') }}"></script>
@section('styles')
    <style>
        /* Activity Balance Column Styles */
        .activity-balance-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 2px;
        }

        .activity-balance-container::-webkit-scrollbar {
            width: 4px;
        }

        .activity-balance-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .activity-balance-container::-webkit-scrollbar-thumb {
            background: #15648C;
            border-radius: 2px;
        }

        .balance-card {
            transition: all 0.3s ease;
        }

        .balance-card:hover {
            transform: translateX(2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .remaining-amount {
            font-size: 11px;
            font-weight: 800;
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        /* Status colors */
        .balance-full {
            color: #2e7d32;
        }

        .balance-partial {
            color: #f57c00;
        }

        .balance-empty {
            color: #999;
        }
    </style>
    <style>
        .status-tabs {
            border-bottom: 1px solid #e5e7eb;
            margin-top: 10px;
            padding: 0 5px;
        }

        .status-tabs label {
            display: inline-block;
            padding: 8px 14px;
            margin-right: 8px;
            font-size: 14px;
            cursor: pointer;
            color: #2563eb;
            border-bottom: 2px solid transparent;
            margin-bottom: 0;
        }

        .status-tabs input[type="radio"] {
            display: none;
        }

        .status-tabs input[type="radio"]:checked+label {
            font-weight: 600;
            border-bottom: 2px solid #2563eb;
            color: #000;
        }

        .estable {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .estable th {
            background-color: #15648C;
            color: white;
            padding: 4px 2px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #ddd;
            white-space: nowrap;
            font-size: 9px;
            line-height: 1.2;
        }

        .estable td {
            padding: 3px 2px;
            border: 1px solid #ddd;
            vertical-align: top;
            font-size: 10px;
        }

        .estable tbody tr:hover {
            background-color: #f5f5f5;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 3px;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 4px 12px;
        }

        .ledger-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .ledger-label {
            font-weight: 600;
        }

        .ledger-sub {
            font-size: 10px;
            color: #666;
        }

        .activity-advance-row {
            background: #e3f2fd;
            padding: 6px;
            margin-bottom: 6px;
            border-radius: 4px;
            border-left: 3px solid #1976d2;
        }

        .expense-advance-row {
            background: #f3e5f5;
            padding: 6px;
            margin-bottom: 6px;
            border-radius: 4px;
            border-left: 3px solid #9c27b0;
        }

        .carried-advance-row {
            background: #ffebee;
            padding: 6px;
            margin-bottom: 6px;
            border-radius: 4px;
            border-left: 3px solid #e53935;
        }

        .adjustment-input {
            width: 100%;
            height: 22px;
            text-align: center;
            border: 1px solid #4caf50;
            border-radius: 3px;
            padding: 2px;
            font-size: 9px;
            background-color: white;
        }

        .adjustment-input:focus {
            outline: none;
            border-color: #2196f3;
            box-shadow: 0 0 3px rgba(33, 150, 243, 0.5);
        }

        /* Style for general balance inputs */
        .adj-activity-general {
            border-color: #f44336;
        }

        .total-adj-display {
            background: #e8f5e9;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: 600;
            color: #2e7d32;
            margin-top: 4px;
            text-align: center;
            font-size: 11px;
        }

        .print-link {
            color: #000099;
            cursor: pointer;
            text-decoration: underline;
            margin: 0 2px;
        }

        .view-link {
            color: #007bff;
            cursor: pointer;
            text-decoration: underline;
        }

        .payable-amount {
            text-align: center;
            font-weight: bold;
            color: #d32f2f;
            font-size: 14px;
        }

        .month-select {
            font-size: 13px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            padding: 20px 0;
            font-family: Times New Roman;
            font-size: 15px;
            font-weight: bold;
        }

        .pagination li {
            margin: 0 2px;
        }

        .pagination li a,
        .pagination li span {
            padding: 6px 12px;
            border: 1px solid #ddd;
            color: #007bff;
            text-decoration: none;
            display: inline-block;
        }

        .pagination li.active span {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination li.disabled span {
            color: #6c757d;
            pointer-events: none;
        }

        /* Compact Adjustment UI */
        .adjustment-box {
            font-size: 8px;
            line-height: 1.3;
            padding: 4px 5px;
            margin-bottom: 4px;
            border-radius: 3px;
        }

        .adjustment-box .title {
            font-size: 8px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .adjustment-box .amount {
            font-size: 8px;
            margin-bottom: 2px;
        }

        .adjustment-box input {
            height: 20px;
            font-size: 9px;
            padding: 1px 3px;
            width: 100%;
        }
    </style>
@endsection

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row py-3 bg-white border-bottom">
         <div class="btn-group" role="group">
                <a href="{{ route('expense-payments.index', ['chkval' => 1, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval', 1) == 1 ? 'btn-primary' : 'btn-outline-primary' }}">
                    Pending
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 2, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 2 ? 'btn-success' : 'btn-outline-success' }}">
                    Approved
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 6, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 6 ? 'btn-secondary' : 'btn-outline-secondary' }}">
                    Holded
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 3, 'v' => request('v', 0), 'export_filter' => 0]) }}"
                    class="btn btn-sm {{ request('chkval') == 3 ? 'btn-warning' : 'btn-outline-warning' }}">
                    Instructed Payment
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 4, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 4 ? 'btn-info' : 'btn-outline-info' }}">
                    Confirmed Payments
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 5, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 5 ? 'btn-danger' : 'btn-outline-danger' }}">
                    Unsettled Payments
                </a>
            </div>
            <div class="row">
                <div class="col-lg-12 shadow">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <!-- Title and Controls in One Row -->
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <b style="font-size: 16px;">Pending for Payment</b>
                                </div>

                                <!-- Integrated Filter Form -->
                                <form method="GET" action="{{ route('expense-payments.index') }}" id="filterForm"
                                    class="d-flex align-items-center gap-2 flex-wrap">
                                    <input type="hidden" name="chkval" value="1">

                                  <select name="courier_status" class="form-select form-select-sm" style="width: auto;">
    <option value="">All</option>
    <option value="available" {{ request('courier_status') == 'available' ? 'selected' : '' }}>
        Courier Available
    </option>
    <option value="not_available" {{ request('courier_status') == 'not_available' ? 'selected' : '' }}>
        Courier Not Available
    </option>
</select>

                                    <select name="prev_month_verified" class="form-select form-select-sm" style="width: auto;">
    <option value="">Prev Month - All</option>
    <option value="verified" {{ request('prev_month_verified') == 'verified' ? 'selected' : '' }}>
        Prev Month Verified
    </option>
    <option value="not_verified" {{ request('prev_month_verified') == 'not_verified' ? 'selected' : '' }}>
        Prev Month Not Verified
    </option>
</select>

                                    <!-- Search Field -->
                                    <input type="text" class="form-control form-control-sm" id="employee_search"
                                        name="employee_search" value="{{ request('employee_search') }}"
                                        placeholder="Search by name or code..." aria-label="Search by name or code" style="width: 250px;">

                                    <!-- Month Selector - Auto submit on change -->
                                    <select class="form-select form-select-sm" id="SelMonth" name="v"
                                        style="width: auto;" onchange="this.form.submit()">
                                        <option value="0" {{ request('v', 0) == 0 ? 'selected' : '' }}>All Months
                                        </option>
                                        <option value="1" {{ request('v') == 1 ? 'selected' : '' }}>January</option>
                                        <option value="2" {{ request('v') == 2 ? 'selected' : '' }}>February</option>
                                        <option value="3" {{ request('v') == 3 ? 'selected' : '' }}>March</option>
                                        <option value="4" {{ request('v') == 4 ? 'selected' : '' }}>April</option>
                                        <option value="5" {{ request('v') == 5 ? 'selected' : '' }}>May</option>
                                        <option value="6" {{ request('v') == 6 ? 'selected' : '' }}>June</option>
                                        <option value="7" {{ request('v') == 7 ? 'selected' : '' }}>July</option>
                                        <option value="8" {{ request('v') == 8 ? 'selected' : '' }}>August</option>
                                        <option value="9" {{ request('v') == 9 ? 'selected' : '' }}>September</option>
                                        <option value="10" {{ request('v') == 10 ? 'selected' : '' }}>October</option>
                                        <option value="11" {{ request('v') == 11 ? 'selected' : '' }}>November</option>
                                        <option value="12" {{ request('v') == 12 ? 'selected' : '' }}>December</option>
                                    </select>

                                    <!-- Approved Date Filter -->
                                    <select name="approved_date" class="form-select form-select-sm" style="width: auto;">
                                        <option value="">All Approved Dates</option>
                                        @foreach($approvedDates ?? [] as $date)
                                            <option value="{{ $date }}" {{ request('approved_date') == $date ? 'selected' : '' }}>
                                                {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <!-- Apply Button (optional, only needed for search) -->
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Apply
                                    </button>

                                    <!-- Reset Button -->
                                    <a href="{{ route('expense-payments.index', ['chkval' => 1]) }}"
                                        class="btn btn-secondary btn-sm">
                                        Reset
                                    </a>

                                    <!-- Refresh Button -->
                                    <a href="javascript:location.reload(true)" class="btn btn-secondary btn-sm">
                                        <i class="fa fa-refresh" aria-hidden="true">Refresh</i>
                                    </a>

                                    <!-- Add this button to your filter form, near the Refresh button -->
<a href="{{ route('expense-payments.export-excel', array_merge(request()->all(), ['chkval' => 1])) }}"
   class="btn btn-success btn-sm">
    <i class="fa fa-file-excel-o" aria-hidden="true"></i> Export to Excel
</a>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div>
                        <table class="estable table table-bordered">
                            <thead class="sticky-top">
                                <tr>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        S.No</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:300px;vertical-align:middle;background-color: #15648C;color:white">
                                        Claimant</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:50px;vertical-align:middle;background-color: #15648C;color:white">
                                        EmpCode</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:50px;vertical-align:middle;background-color: #15648C;color:white">
                                        Grade</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Status</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:50px;vertical-align:middle;background-color: #15648C;color:white">
                                        Month</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:60px;vertical-align:middle;background-color: #15648C;color:white">
                                        Total<br>Claims</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:100px;vertical-align:middle;background-color: #15648C;color:white">
                                        Print</th>
                                    <th colspan="4"
                                        style="font-size: 10px;text-align:center;background-color: #15648C;color:white">
                                        Finance Amount</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:200px;vertical-align:middle;background-color: #15648C;color:white">
                                        Activity Opr Balance</th>
                                    <th colspan="4"
                                        style="font-size: 10px;text-align:center;background-color: #15648C;color:white">
                                        Advance Adjustment</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:150px;vertical-align:middle;background-color: #15648C;color:white">
                                        Activity Balance</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:80px;vertical-align:middle;background-color: #15648C;color:white" title="B-04: DA (DA@HQ / DAOutStation@HQ) is excluded unless grade id > 67">
                                        Actual Expense<br><small>(excl. DA*)</small></th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:80px;vertical-align:middle;background-color: #15648C;color:white">
                                        Payable Amount (After Adjustment)<br><small>(excl. DA*)</small></th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:60px;vertical-align:middle;background-color: #15648C;color:white">
                                        Fixed Imprest</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:60px;vertical-align:middle;background-color: #15648C;color:white">
                                        Account Summary</th>
                                    <th rowspan="2"
                                        style="font-size: 10px;width:60px;vertical-align:middle;background-color: #15648C;color:white">
                                        Action</th>
                                </tr>
                                <tr>
                                    <th style="font-size: 10px;width:80px;background-color: #15648C;color:white">Activity
                                    </th>
                                    <th style="font-size: 10px;width:80px;background-color: #15648C;color:white">DA</th>
                                    <th style="font-size: 10px;width:80px;background-color: #15648C;color:white">Expense
                                    </th>
                                    <th style="font-size: 10px;width:80px;background-color: #15648C;color:white">Total</th>
                                    <th style="font-size: 10px;width:115px;background-color: #15648C;color:white">Activity
                                        Adv</th>
                                    <th style="font-size: 10px;width:115px;background-color: #15648C;color:white">Expense
                                        Adv</th>
                                    {{-- <th style="font-size: 10px;width:115px;background-color: #15648C;color:white">Carried Adv</th> --}}
                                    <th style="font-size: 10px;width:100px;background-color: #e65100;color:white">Other Adj
                                    </th>
                                    <th style="font-size: 10px;width:80px;background-color: #15648C;color:white">Total Adj
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $page = request('page', 1);
                                    $i = ($page - 1) * 1000 + 1;
                                @endphp

                                @forelse($paginatedRecords as $record)
                                    @php
                                        $employeeStatus = getStsUser(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                        );
                                        $rowBgColor = in_array($employeeStatus, ['D', 'R']) ? '#FFFFA6' : '';

                                        // Get financial amounts
                                        $financialAmounts = getFinancialAmounts(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                            $record['Month'] ?? ($record['month'] ?? 1),
                                            session('year_id'),
                                        );

                                        // Initialize totals
                                        $finalActivityAmount = 0;
                                        $finalExpenseAmount = 0;
                                        $finalDAAmount = 0;

                                        // Sum all grouped rows
                                        foreach ($financialAmounts as $fa) {
                                            $finalActivityAmount += $fa->FinalActivityAmount ?? 0;
                                            $finalExpenseAmount += $fa->FinalExpenseAmount ?? 0;
                                            $finalDAAmount += $fa->FinalDAAmount ?? 0;
                                        }
                                        $gradeId = getGradeId($record['EmployeeID']);
                                        // print($gradeId);
                                        $netExpense =
                                            $gradeId > 67 ? $finalExpenseAmount : $finalExpenseAmount - $finalDAAmount;
                                        $grandTotal = $finalActivityAmount + $netExpense + $finalDAAmount;
                                        $difference = $finalActivityAmount + $netExpense;

                                        // Get advances
                                        $activityAdvances = getCurrentMonthActivityAdvances(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                            $record['Month'] ?? ($record['month'] ?? 1),
                                        );
                                        $expenseAdvances = getCurrentMonthExpenseAdvances(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                            $record['Month'] ?? ($record['month'] ?? 1),
                                        );

                                        // For company 11: use prev month calculation (paidAmt - (verifiedAmt - DA))
                                        if ((int) session('company_id') === 11) {
                                            $prevMonthAdvance = getPrevMonthExpenseAdvanceForAdjustment(
                                                $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                                $record['Month'] ?? ($record['month'] ?? 1),
                                            );
                                            if (!empty($prevMonthAdvance)) {
                                                $expenseAdvances = array_merge($expenseAdvances, $prevMonthAdvance);
                                            }
                                        }
                                        $allPaidAdvances = getAllPaidAdvances(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                            $record['Month'] ?? ($record['month'] ?? 1),
                                        );

                                        // Ensure imprest advance (fixed/revolving float) never comes for adjustment
                                        if (function_exists('isImprestAdvanceRecord')) {
                                            $activityAdvances = array_values(array_filter($activityAdvances, function ($adv) {
                                                return !isImprestAdvanceRecord($adv);
                                            }));
                                            $expenseAdvances = array_values(array_filter($expenseAdvances, function ($adv) {
                                                return !isImprestAdvanceRecord($adv);
                                            }));
                                            $allPaidAdvances = array_values(array_filter($allPaidAdvances, function ($adv) {
                                                return !isImprestAdvanceRecord($adv);
                                            }));
                                        }

                                        // Get ledger and carried advances
                                        $ledgerData = getEmployeeLedgerBalance(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                        );

                                        // For company 11, previous month calculation is handled via getPrevMonthExpenseAdvanceForAdjustment
                                        $carriedData = ((int) session('company_id') === 11)
                                            ? ['advances' => [], 'total' => 0]
                                            : getCarriedAdvancesforadjustment(
                                                $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                                $record['Month'] ?? ($record['month'] ?? 1),
                                            );
                                        // print_r($carriedData);
                                        $carriedAdvances = $carriedData['advances'];

                                        if (function_exists('isImprestAdvanceRecord')) {
                                            $carriedAdvances = array_values(array_filter($carriedAdvances, function ($adv) {
                                                return !isImprestAdvanceRecord($adv);
                                            }));
                                        }

                                        // Get activity financed amounts
                                        $activityFinancedData = getActivityFinancedAmounts(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                            $record['Month'] ?? ($record['month'] ?? 1),
                                            session('year_id'),
                                        );

                                        $activityFinanced = [];
                                        $currentMonthActivityIds = [];

                                        // 1️⃣ Add advances first (use composite key to allow multiple advances per activity)
                                        $actAdvIndex = 0;
                                        foreach ($activityAdvances as $actAdv) {
                                            $claimId = $actAdv['activity_name_id'];
                                            $compositeKey = $claimId . '_' . $actAdvIndex;

                                            $activityFinanced[$compositeKey] = [
                                                'claim_id' => $claimId,
                                                'financed' => 0,
                                                'advance' => $actAdv['advance_amount'],
                                                'activity_name' => $actAdv['ActivityName'],
                                                'payment_date' => $actAdv['payment_date'],
                                                'advance_month' => $actAdv['month'],
                                                'activity_advance_id' => $actAdv['activity_advance_id'] ?? null,
                                            ];

                                            if (!in_array($claimId, $currentMonthActivityIds)) {
                                                $currentMonthActivityIds[] = $claimId;
                                            }
                                            $actAdvIndex++;
                                        }

                                        // 2️⃣ Merge financed data (even if no advance exists)
                                        foreach ($activityFinancedData as $actFin) {
                                            $claimId = $actFin['ClaimId'];

                                            // Check if any composite key exists for this activity
                                            $foundKey = null;
                                            foreach ($activityFinanced as $key => $val) {
                                                if (($val['claim_id'] ?? null) == $claimId) {
                                                    $foundKey = $key;
                                                    break;
                                                }
                                            }

                                            if ($foundKey === null) {
                                                // No advance exists → still create row with activity ID as key
                                                $activityFinanced['noAdv_' . $claimId] = [
                                                    'claim_id' => $claimId,
                                                    'financed' => $actFin['total_financed'],
                                                    'advance' => 0,
                                                    'activity_name' => $actFin['ClaimName'],
                                                    'payment_date' => null,
                                                    'advance_month' => null,
                                                ];
                                            } else {
                                                // Advance exists → update financed on the first match
                                                $activityFinanced[$foundKey]['financed'] = $actFin['total_financed'];
                                            }
                                        }

                                        // Get activities without advance
                                        $activityWithoutAdvance = [];
                                        foreach ($activityFinancedData as $actFin) {
                                            $hasAdvance = false;
                                            foreach ($activityFinanced as $key => $val) {
                                                if (($val['claim_id'] ?? null) == $actFin['ClaimId'] && ($val['advance'] ?? 0) > 0) {
                                                    $hasAdvance = true;
                                                    break;
                                                }
                                            }
                                            if (!$hasAdvance && !isset($activityFinanced['noAdv_' . $actFin['ClaimId']])) {
                                                $activityWithoutAdvance[$actFin['ClaimId']] = [
                                                    'financed' => $actFin['total_financed'],
                                                    'activity_name' => $actFin['ClaimName'],
                                                ];
                                            }
                                        }

                                        // Match carried advances
                                        $matchedCarriedAdvances = [];
                                        $unmatchedCarriedAdvances = [];

                                        foreach ($carriedAdvances as $carriedAdv) {
                                            $isMatched = false;

                                            // if ($carriedAdv['purpose'] == 'Activity' && $carriedAdv['activity_id'] > 0) {
                                            // if (in_array($carriedAdv['activity_id'], $currentMonthActivityIds)) {
                                            // $matchedCarriedAdvances[$carriedAdv['activity_id']][] = $carriedAdv;
                                            // $isMatched = true;
                                            // }
                                            // }

                                            if (!$isMatched) {
                                                $unmatchedCarriedAdvances[] = $carriedAdv;
                                            }
                                        }

                                        // Calculate remaining old carried for expense
                                        $remainingOldCarriedForExpense = 0;
                                        foreach ($activityFinanced as $actId => $actData) {
                                            $carriedForActivity = 0;

                                            if (isset($matchedCarriedAdvances[$actId])) {
                                                foreach ($matchedCarriedAdvances[$actId] as $matched) {
                                                    $carriedForActivity += $matched['amount'];
                                                }
                                            }

                                            if ($carriedForActivity > 0) {
                                                $usedForActivity = min($actData['financed'], $carriedForActivity);
                                                $remainingOldCarriedForExpense += max(
                                                    0,
                                                    $carriedForActivity - $usedForActivity,
                                                );
                                            }
                                        }

                                        if ($remainingOldCarriedForExpense > 0) {
                                            $unmatchedCarriedAdvances[] = [
                                                'amount' => $remainingOldCarriedForExpense,
                                                'purpose' => 'Old Balance',
                                                'month' => '',
                                                'activity_id' => 0,
                                                'date' => '',
                                                'index' => 0,
                                            ];
                                        }

                                        // Get fixed imprest
                                        $fixedImprest = getFixedImprest(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                        );
                                        // print_r($fixedImprest);
                                        $employeeId = $record['EmployeeID'] ?? ($record['employee_id'] ?? 0);
                                        $month = $record['Month'] ?? ($record['month'] ?? 1);
                                        $yearId =
                                            $record['YearId'] ?? ($record['year_id'] ?? session('year_id', date('Y')));
                                        $totalClaim = $record['Total_Claim'] ?? ($record['total_claim'] ?? 0);

                                        // Check if current month activity advance exists
                                        $currentActivityAdvanceExists = false;
                                        foreach ($activityFinanced as $actId => $actData) {
                                            if ($actData['advance'] > 0) {
                                                $currentActivityAdvanceExists = true;
                                                break;
                                            }
                                        }
                                    @endphp

                                    @php
                                        // ---- TOTAL ADVANCES ----
                                        $totalActivityAdvance = 0;
                                        foreach ($activityFinanced as $act) {
                                            $totalActivityAdvance += $act['advance'] ?? 0;
                                        }

                                        $totalExpenseAdvance = 0;
                                        foreach ($expenseAdvances as $exp) {
                                            $totalExpenseAdvance += $exp['advance_amount'] ?? 0;
                                        }

                                        $totalCarriedAdvance = 0;
                                        foreach ($unmatchedCarriedAdvances as $car) {
                                            $totalCarriedAdvance += $car['amount'] ?? 0;
                                        }

                                        // Total Adjustment Available (includes other adjustment)
                                        $otherAdj = (float) ($record['other_adjustment'] ?? 0);
                                        $totalAdjustmentAvailable =
                                            $totalActivityAdvance + $totalExpenseAdvance + $totalCarriedAdvance + $otherAdj;

                                        // Actual expense without DA
                                        $actualExpenseWithoutDA = $difference;

                                        // Payable amount after adjustment
                                        $payableAfterAdjustment = max(
                                            0,
                                            $actualExpenseWithoutDA - $totalAdjustmentAvailable,
                                        );

                                        // ---- REMAINING BALANCES (Initial view) ----
                                        $remainingActivityBalance = $totalActivityAdvance;
                                        $remainingExpenseBalance = $totalExpenseAdvance + $totalCarriedAdvance;
                                    @endphp



                                    <tr id="row_{{ $i }}" style="background-color: {{ $rowBgColor }};">
                                        <td style="text-align: center;font-size: 10px;">{{ $i }}</td>
                                        <td style="text-align: left;font-size: 10px;">{{ getUser($employeeId) }}</td>
                                        <td style="text-align: center;font-size: 10px;">{{ getCode($employeeId) }}</td>
                                        <td style="text-align: center;font-size: 10px;">{{ getGrade($employeeId) }}</td>
                                        <td style="text-align: center;font-size: 10px;">{{ $employeeStatus }}</td>
                                        <td style="text-align: center;font-size: 10px;">
                                            <a href="#"
                                                onclick="showmonthdet('{{ $month }}','Open','{{ $employeeId }}','Approved')"
                                                style="color: #007bff;">
                                                {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                            </a>
                                            <input type="hidden" id="sts{{ $employeeId }}{{ $month }}Approved"
                                                value="close">
                                        </td>
                                        <td style="text-align: center;font-size: 10px;">
                                            <b>{{ number_format($totalClaim) }}</b>
                                        </td>
                                        <td style="text-align: center; font-size: 10px; color: #1976d2; display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                            <div style="display:flex;gap:6px;">
                                                <span
                                                    onclick="FunPrint({{ $employeeId }}, {{ $month }}, {{ $yearId }}, 1)"
                                                    class="print-link cursor-pointer">One</span>
                                                &nbsp;&nbsp;&nbsp; |
                                                <span
                                                    onclick="FunPrint({{ $employeeId }}, {{ $month }}, {{ $yearId }}, 2)"
                                                    class="print-link cursor-pointer">Two</span>
                                            </div>
                                           
                                        </td>

                                        {{-- Finance Amount Columns --}}
                                        <td style="vertical-align: top; padding: 5px; text-align: center;font-size: 10px;">

                                            {{-- Activities WITH Advance --}}
                                            @foreach ($activityFinanced as $actId => $actData)
                                                @if ($actData['financed'] > 0)
                                                    <div class="mb-3">
                                                        {{-- {{ print_r($actData) }} --}}
                                                        <strong
                                                            style="font-size: 10px;">{{ number_format($actData['financed']) }}</strong>
                                                        @if (!empty($actData['payment_date']) && \App\Support\ZeroDate::has($actData['payment_date']))
                                                            <div style="font-size:8px;color:#666;">
                                                                {{ \Carbon\Carbon::parse($actData['payment_date'])->format('d-m-Y') }}
                                                            </div>
                                                        @endif
                                                        <div
                                                            style="font-size: 8px; color: #1976d2; margin-top: 4px; text-align: left;">
                                                            {{ $actData['activity_name'] }}
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach

                                            {{-- Activities WITHOUT Advance --}}
                                            @foreach ($activityWithoutAdvance as $actId => $actData)
                                                <div class="mb-3">
                                                    <strong
                                                        style="font-size: 10px;">{{ number_format($actData['financed']) }}</strong>
                                                    <div
                                                        style="font-size: 8px; color: #666; margin-top: 2px; text-align: left;">
                                                        {{ $actData['activity_name'] }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </td>
                                        <td style="text-align: center;font-size: 10px;">
                                            {{ number_format($finalDAAmount) }}</td>
                                        <td style="text-align: center;font-size: 10px;">{{ number_format($netExpense) }}
                                        </td>
                                        <td style="text-align: center;font-size: 10px;">
                                            <strong>{{ number_format($grandTotal) }}</strong>
                                        </td>

                                        {{-- Activity Opr Balance --}}
                                        <td style="vertical-align: top; padding: 3px; font-size: 9px; width: 140px;">
                                            @include('expense-payments.partials.activity-opr-balance', [
                                                'ledgerData' => $ledgerData,
                                                'carriedAdvances' => $carriedAdvances,
                                                'allPaidAdvances' => $allPaidAdvances,
                                                'expenseAdvances' => $expenseAdvances,
                                                'employeeId' => $employeeId,
                                                'rowId' => $i,
                                            ])
                                        </td>



                                        {{-- Activity Adv Adjustment --}}
                                        <td style="vertical-align: top; padding: 3px; font-size: 9px; width: 180px;">
                                            @include('expense-payments.partials.activity-advance', [
                                                'activityFinanced' => $activityFinanced,
                                                'allPaidAdvances' => $allPaidAdvances,
                                                'matchedCarriedAdvances' => $matchedCarriedAdvances,
                                                'ledgerData' => $ledgerData,
                                                'carriedAdvances' => $carriedAdvances,
                                                'expenseAdvances' => $expenseAdvances,
                                                'finalActivityAmount' => $finalActivityAmount,
                                                'netExpense' => $netExpense,
                                                'currentMonthActivityAdvanceExists' => $currentActivityAdvanceExists,
                                                'employeeId' => $employeeId,
                                                'rowId' => $i,
                                                'totalClaim' => $totalClaim,
                                            ])
                                        </td>

                                        {{-- Expense Adv Adjustment --}}
                                        <td style="vertical-align: top; padding: 3px; font-size: 9px; width: 140px;">
                                            @include('expense-payments.partials.expense-advance', [
                                                'expenseAdvances' => $expenseAdvances,
                                                'netExpense' => $netExpense,
                                                'rowId' => $i,
                                            ])
                                        </td>

                                        {{-- Other Adj (does NOT affect ledger) --}}
                                        <td style="text-align: center; font-size: 9px; vertical-align: middle; padding: 3px;">
                                            <input type="number" step="0.01"
                                                class="form-control form-control-sm other-adj-input"
                                                style="width:70px; font-size:9px; padding:2px; margin:0 auto; border-color:#e65100;"
                                                data-id="{{ $record['id'] }}"
                                                data-row-id="{{ $i }}"
                                                value="{{ $record['other_adjustment'] ?? 0 }}"
                                                placeholder="0"
                                                oninput="recalcRowTotals({{ $i }}, {{ $actualExpenseWithoutDA }}, {{ $totalActivityAdvance + $totalExpenseAdvance + $totalCarriedAdvance }})">
                                            <input type="text"
                                                class="form-control form-control-sm mt-1 other-adj-remark-input"
                                                style="width:100%; font-size:8px; padding:1px 3px;"
                                                data-id="{{ $record['id'] }}"
                                                value="{{ $record['other_adjustment_remark'] ?? '' }}"
                                                placeholder="Remark" aria-label="Remark">
                                        </td>

                                        {{-- Total Adj --}}
                                        <td style="vertical-align: middle; text-align: center;font-size: 10px;">
                                            <div class="total-adj-display" id="total_adj_all_{{ $i }}">
                                                {{ number_format($totalAdjustmentAvailable) }}
                                            </div>
                                        </td>

                                        {{-- Activity Balance Column --}}
                                        <td id="ledger_cell_{{ $i }}"
                                            style="text-align: left; font-size: 10px; vertical-align: top; padding: 5px; width: 250px; background: #f8f9fa;">
                                            <div class="activity-balance-container">
                                                @include('expense-payments.partials.activity-balance', [
                                                    'ledgerData' => $ledgerData,
                                                    'activityFinanced' => $activityFinanced,
                                                    'allPaidAdvances' => $allPaidAdvances,
                                                    'expenseAdvances' => $expenseAdvances,
                                                    'carriedAdvances' => $unmatchedCarriedAdvances,
                                                    'employeeId' => $employeeId,
                                                    'rowId' => $i,
                                                ])
                                            </div>
                                        </td>

                                        {{-- Actual Expense (Without DA) --}}
                                        <td style="text-align: center; font-weight: bold; font-size: 10px;"
                                            id="actual_diff_{{ $i }}">
                                            {{ number_format($difference) }}
                                        </td>

                                        {{-- Payable Amount (After Adjustment) --}}
                                        <td style="text-align: center;font-size: 10px;" id="diff_{{ $i }}">
                                            <span class="payable-amount">
                                                {{ number_format($payableAfterAdjustment) }}
                                            </span>
                                        </td>

                                        {{-- Fixed Imprest --}}
                                        <td style="text-align: center; font-size: 10px;"
                                            id="fixed_imprest_{{ $i }}">
                                            @php
                                                $imprestData = (!empty($ledgerData['fixed_imprest_amt_1']) || !empty($ledgerData['fixed_imprest_amt_2']))
                                                    ? $ledgerData
                                                    : $fixedImprest;
                                            @endphp
                                            @include('expense-payments.partials.fixed-imprest', [
                                                'fixedImprest' => $imprestData,
                                                'ledgerData' => $imprestData,
                                                'rowId' => $i,
                                            ])
                                        </td>

                                        {{-- Account Summary --}}
                                        <td style="text-align: center;font-size: 10px;color: #0b5c5c">
                                            <span
                                                onclick="accSummary({{ $employeeId }}, {{ $month }}, {{ $yearId }})"
                                                class="view-link cursor-pointer">View</span>
                                        </td>

                                        {{-- Action --}}
                                        <td style="text-align: center;font-size: 10px;">
                                            <button type="button" class="btn btn-sm btn-success"
                                                onclick="approveClaimMonth(
                                        {{ $difference }},
                                        '{{ $month }}',
                                        '{{ $employeeId }}',
                                        {{ $i }}
                                    )">
                                                Approve
                                            </button>
                                        </td>
                                    </tr>
                                    <tr id="{{ $employeeId }}{{ $month }}Approved"></tr>
                                    @php $i++; @endphp
                                @empty
                                    <tr>
                                        <td colspan="22" style="text-align: center; padding: 30px; font-size: 14px;">
                                            No pending payment records found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    @if ($paginatedRecords->total() > 0)
                        <div
                            style="text-align: center; padding: 20px 0; font-family: Times New Roman; font-size:15px; font-weight:bold;">
                            @php
                                $currentPage = $paginatedRecords->currentPage();
                                $totalPages = $paginatedRecords->lastPage();
                                // Preserve all filter parameters except 'page'
                                $queryParams = request()->except('page');
                                $queryString = http_build_query($queryParams);
                            @endphp

                            @if ($currentPage > 1)
                                <a href="{{ $paginatedRecords->url($currentPage - 1) }}&{{ $queryString }}"
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff; text-decoration: none;">&lt;
                                    Previous</a>
                            @else
                                <span
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #6c757d;">&lt;
                                    Previous</span>
                            @endif

                            @for ($p = 1; $p <= $totalPages; $p++)
                                @if ($p == $currentPage)
                                    <span
                                        style="margin: 0 2px; padding: 6px 12px; border: 1px solid #007bff; background-color: #007bff; color: white;">{{ $p }}</span>
                                @else
                                    <a href="{{ $paginatedRecords->url($p) }}&{{ $queryString }}"
                                        style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff; text-decoration: none;">{{ $p }}</a>
                                @endif
                            @endfor

                            @if ($currentPage < $totalPages)
                                <a href="{{ $paginatedRecords->url($currentPage + 1) }}&{{ $queryString }}"
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff; text-decoration: none;">Next
                                    &gt;</a>
                            @else
                                <span
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #6c757d;">Next
                                    &gt;</span>
                            @endif

                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <span style="color: #ee4545;">(Total {{ $paginatedRecords->total() }} Records)</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @include('expense-payments.partials.modals')
        @include('components.modals.employee-running-km')

        {{-- Claim Detail Modal --}}
        <div class="modal fade" id="claimDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white py-2">
                        <h6 class="modal-title">Claim Details</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0" id="claimDetailContainer">
                        <div class="p-3 text-center">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    <script src="{{ asset('custom/js/pages/eligibility_panel.js') }}"></script>
    <script type="text/javascript">
        // Setup CSRF token for all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        function openPopUpWindow(url) {
            window.open(
                url,
                "_blank",
                "toolbar=no,scrollbars=yes,resizable=yes,top=80,left=200,width=800,height=450"
            );
        }

        // Month selection function
        function FselMonth() {
            var chkval = document.getElementById('chkval')?.value || $('#chkval').val() || 1;
            var SelMonth = $('#SelMonth').val();
            var url = new URL(window.location.href);
            url.searchParams.set('v', SelMonth);
            url.searchParams.set('chkval', chkval);
            window.location.href = url.toString();
        }

        // Print function
        // function FunPrint(employeeId, month, year, format) {
        //     if (!employeeId || !month || !year) {
        //         alert('Invalid parameters for printing');
        //         return false;
        //     }

        //     var url = "{{ url('printdetails') }}?e=" + employeeId + "&m=" + month + "&y=" + year + "&n=" + format;
        //     var printWindow = window.open(url, '_blank', 'width=1200,height=600,scrollbars=yes,resizable=yes');

        //     if (!printWindow || printWindow.closed || typeof printWindow.closed == 'undefined') {
        //         alert('Popup blocker detected! Please allow popups for this site.');
        //     }

        //     return false;
        // }

        function FunPrint(employeeId, month, year, format) {
            if (!employeeId || !month || !year) {
                alert('Invalid parameters for printing');
                return false;
            }

            var url = "{{ url('expense/print-details') }}" +
                "?e=" + employeeId +
                "&y=" + year +
                "&m=" + month +
                "&nn=" + format +
                "&c=" + "{{ session('company_id') }}";

            var printWindow = window.open(
                url,
                '_blank',
                'width=1200,height=600,scrollbars=yes,resizable=yes'
            );

            if (!printWindow || printWindow.closed || typeof printWindow.closed == 'undefined') {
                alert('Popup blocker detected! Please allow popups for this site.');
            }

            return false;
        }

        // Number validation
        function isNumberKey(evt) {
            var charCode = (evt.which) ? evt.which : event.keyCode;
            if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57))
                return false;
            return true;
        }

        // Show month details
        function showmonthdet(month, sts, emp, csts) {
            var status = document.getElementById('sts' + emp + month + csts);
            var modal = document.getElementById(emp + month + csts);
            if (status && status.value == 'close') {
                $.post("{{ url('claimlistajax') }}", {
                        _token: '{{ csrf_token() }}',
                        act: "monthdettofinance",
                        month: month,
                        sts: sts,
                        csts: csts,
                        emp: emp
                    },
                    function(data) {
                        if (modal) modal.innerHTML = data;
                    }
                );
                if (status) status.value = "open";
            } else if (status && status.value == 'open') {
                if (modal) modal.innerHTML = '';
                if (status) status.value = "close";
            }
        }

        // Account summary
        function accSummary(e, m, y) {
            window.open("{{ url('account-summary') }}?e=" + e + "&m=" + m + "&y=" + y,
                "AccSummary", "menubar=no,scrollbars=yes,resizable=no,directories=no,width=800,height=600");
        }

        // Show claim details in modal (images, PDFs, form details)
        function showexpdet(expid, claimId) {
            var modal = new bootstrap.Modal(document.getElementById('claimDetailModal'));
            modal.show();

            $('#claimDetailContainer').html('<div class="p-3 text-center"><div class="spinner-border text-primary" role="status"></div><br>Loading claim details...</div>');

            $.get("{{ url('claim-detail') }}", {
                claim_id: claimId,
                expid: expid
            }, function(data) {
                let eligibilityHtml = typeof buildEligibilityPanel === 'function' ? buildEligibilityPanel(data.eligibility) : '';

                let finalHtml = `
                <div class="row m-0">
                    <!-- LEFT: FILES (Images/PDFs) -->
                    <div class="col-md-4 border-end p-2" style="max-height:600px; overflow-y:auto;">
                        <h6 class="mb-2 fw-bold">Uploaded Files</h6>
                `;

                if (data.uploaded_files && data.uploaded_files.length > 0) {
                    data.uploaded_files.forEach(function(file) {
                        if (file.file_url && (file.file_url.endsWith('.pdf') || file.file_url.includes('.pdf'))) {
                            finalHtml += `
                            <div class="mb-2 text-center">
                                <a href="${file.file_url}" target="_blank" class="btn btn-sm btn-outline-danger w-100">
                                    <i class="fa fa-file-pdf-o"></i> ${file.file_name || 'View PDF'}
                                </a>
                            </div>
                            `;
                        } else {
                            finalHtml += `
                            <div class="mb-2 text-center">
                                <img alt="Bill or document image" src="${file.file_url}"
                                     class="img-fluid border rounded"
                                     style="max-height:200px; object-fit:contain; cursor:pointer;"
                                     onclick="window.open('${file.file_url}','_blank')">
                                <br>
                                <small class="text-muted">${file.file_name || ''}</small>
                            </div>
                            `;
                        }
                    });
                } else {
                    finalHtml += `<p class="text-muted">No files uploaded</p>`;
                }

                finalHtml += `
                    </div>
                    <!-- RIGHT: FORM/DETAILS -->
                    <div class="col-md-8 p-2" style="max-height:600px; overflow-y:auto;">
                        ${data.html}
                        ${eligibilityHtml}
                    </div>
                </div>
                `;

                $('#claimDetailContainer').html(finalHtml);
            }).fail(function(xhr) {
                console.error('Error:', xhr.responseText);
                $('#claimDetailContainer').html('<div class="alert alert-danger m-3">Error loading claim details. Please try again.</div>');
            });
        }

        // Finance claim individual approval/rejection
        function financeClaim(t, expid) {
            var f = parseInt($('#' + expid + 'financetamt').val());
            var remark = $('#' + expid + 'financetremark').val();
            var claimedAmt = parseInt($('#' + expid + '__FilledTAmt').text().trim()) || 0;
            var verifiedAmt = parseInt($('#' + expid + '__VerifyTAmt').text().trim()) || 0;
            var approvedAmt = parseInt($('#' + expid + '__ApprTAmt').text().trim()) || 0;

            var maxAmt = Math.max(claimedAmt, verifiedAmt, approvedAmt);
            var errorMessage = '';

            if (isNaN(f) || f > maxAmt || f < 0) {
                errorMessage = 'Amount cannot be greater than ₹' + maxAmt + '.';
            }

            if (!errorMessage && f !== maxAmt) {
                if (!remark) {
                    errorMessage = 'Remark is required when the amount is changed.';
                } else if (/[^a-zA-Z0-9\s]/.test(remark)) {
                    errorMessage = 'Remark cannot contain special characters.';
                } else if (remark.toLowerCase() === 'ok') {
                    errorMessage = 'The text "ok" is not allowed in the remark.';
                }
            }

            if (errorMessage) {
                alert(errorMessage);
                return;
            }

            var msg = t == 'A' ? 'Approve' : 'Reject';
            var agree = confirm("Are you sure you want to " + msg + " this claim?");

            if (agree) {
                $.ajax({
                    url: "{{ route('claims.approve-finance') }}",
                    type: "POST",
                    data: {
                        act: "financeClaim",
                        expid: expid,
                        ftamt: f,
                        financetremark: remark,
                        t: t,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.message == 'financed' || data.message == 'success') {
                            $('#' + expid + 'Status').html('<span class="text-success fw-bold">Ok</span>');
                            $('#' + expid + 'btn').html('View');
                            alert('Approved Successfully');
                            $('#financection' + expid).html('');
                        }
                        if (data.message == 'Rejected') {
                            $('#' + expid + 'Status').html('<span class="text-danger fw-bold">Rejected</span>');
                            $('#' + expid + 'btn').html('View');
                            alert('Rejected Successfully');
                            $('#financection' + expid).html('');
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + (xhr.responseText || 'Something went wrong'));
                    }
                });
            }
        }

        // Number validation for finance amount input
        function isNumber(evt) {
            evt = (evt) ? evt : window.event;
            var charCode = (evt.which) ? evt.which : evt.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57)) {
                return false;
            }
            return true;
        }

        // Check range for finance amount (don't allow more than original)
        function checkrange(obj, maxVal) {
            var val = parseInt(obj.value) || 0;
            var max = parseInt(maxVal) || 0;
            if (val > max) {
                obj.value = max;
            }
        }

        // Format number function
        function formatNumber(num) {
            return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // MAIN RECALCULATION FUNCTION
        // Update recalculateRow to call the balance update
        function recalculateRow($row) {
            if (!$row || !$row.length) return;

            const rowId = $row.attr('id').replace('row_', '');

            // Calculate all adjustments (same as before)
            let totalSameActivityAdj = 0;
            let totalGeneralActivityAdj = 0;
            let totalExpenseAdj = 0;
            let totalCarriedAdj = 0;

            $(`[id^="adj_activity_same_${rowId}_"]`).each(function() {
                totalSameActivityAdj += Number($(this).val()) || 0;
            });

            $(`[id^="adj_general_activity_${rowId}_"]`).each(function() {
                totalGeneralActivityAdj += Number($(this).val()) || 0;
            });



            $(`[id^="adj_expense_${rowId}_"]`).each(function() {
                totalExpenseAdj += Number($(this).val()) || 0;
            });

            $(`[id^="adj_carried_activity_${rowId}"]`).each(function() {
                totalCarriedAdj += Number($(this).val()) || 0;
            });

            $(`[id^="adj_carried_expense_${rowId}"]`).each(function() {
                totalCarriedAdj += Number($(this).val()) || 0;
            });

            const totalActivityAdj = totalSameActivityAdj + totalGeneralActivityAdj;
            const totalAdjustment = totalActivityAdj + totalExpenseAdj + totalCarriedAdj;

            // Get actual expense and calculate payable
            const actualExpenseText = $('#actual_diff_' + rowId).text().replace(/[^0-9.-]/g, '');
            const actualExpense = Number(actualExpenseText) || 0;
            const payable = Math.max(0, actualExpense - totalAdjustment);

            // Update displays
            $('#diff_' + rowId + ' span').text('₹' + formatNumber(Math.round(payable)));
            $('#total_adj_all_' + rowId).text(formatNumber(Math.round(totalAdjustment)));

            // Update expense total
            const $totalAdjExpense = $('#total_adj_expense_' + rowId);
            if ($totalAdjExpense.length) {
                $totalAdjExpense.text('₹' + formatNumber(Math.round(totalExpenseAdj + totalCarriedAdj)));
            }

            // Update activity remaining amounts
            updateActivityRemaining(rowId);

            // Update general balance display
            updateGeneralBalance(rowId);

            // Update same activity available balances
            updateSameActivityBalances(rowId);

            // CRITICAL: Update all remaining balances in the activity balance column
            const totalRemaining = updateAllRemainingBalances(rowId);

            console.log('Row ' + rowId + ' updated:', {
                adjustments: totalAdjustment,
                payable: payable,
                remainingBalance: totalRemaining
            });
        }



        // Update all remaining balances after adjustments
        function updateAllRemainingBalances(rowId) {
            const $balanceCell = $(`#ledger_cell_${rowId}`);
            if (!$balanceCell.length) return 0;

            let totalRemaining = 0;
            const updatedCards = {};

            // === PAID ADVANCES: Use activity_advance_id for direct 1:1 matching ===
            $(`[id^="adj_activity_same_${rowId}_"]`).each(function() {
                const $input = $(this);
                const actAdvId = $input.data('activity-advance-id');
                const originalMax = Number($input.data('original-max')) || 0;
                const currentVal = Number($input.val()) || 0;
                const remaining = Math.max(0, originalMax - currentVal);

                // Direct match: find balance card by unique activity_advance_id
                if (actAdvId) {
                    const $balanceSpan = $(`#balance_paid_${rowId}_${actAdvId}`);
                    if ($balanceSpan.length) {
                        $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
                        const $card = $balanceSpan.closest('.balance-card');
                        if ($card.length) {
                            updatedCards[$card.data('advance-id')] = true;
                            if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                            else if (remaining < originalMax) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                            else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
                        }
                        totalRemaining += remaining;
                        return; // next input
                    }
                }

                // Fallback: try matching by claim-id with index
                const claimId = $input.data('claim-id') || $input.data('activity-id');
                let $balanceSpan = $balanceCell.find(`.balance-card[data-category="paid"]`).filter(function() {
                    return !updatedCards[$(this).data('advance-id')];
                }).find(`[id^="balance_paid_${rowId}_"]`).first();

                if ($balanceSpan.length) {
                    $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
                    const $card = $balanceSpan.closest('.balance-card');
                    if ($card.length) {
                        updatedCards[$card.data('advance-id')] = true;
                        if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                        else if (remaining < originalMax) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                        else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
                    }
                    totalRemaining += remaining;
                }
            });

            // === LEDGER ADVANCES ===
            $(`[id^="adj_general_activity_${rowId}_"]`).each(function() {
                const $input = $(this);
                const genActId = $input.data('genral-actid');
                const index = $input.data('index');
                const original = Number($input.data('original')) || 0;
                const inputVal = Number($input.val()) || 0;
                const remaining = Math.max(0, original - inputVal);

                const $balanceSpan = $(`#balance_ledger_${rowId}_${genActId}_${index}`);
                if ($balanceSpan.length) {
                    $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
                    const $card = $balanceSpan.closest('.balance-card');
                    if ($card.length) {
                        if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                        else if (remaining < original) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                        else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
                    }
                    totalRemaining += remaining;
                }
            });

            // === EXPENSE ADVANCES ===
            $(`[id^="adj_expense_${rowId}_"]`).each(function() {
                const $input = $(this);
                const expId = $input.data('expense-id');
                const original = Number($input.data('advance')) || 0;
                const inputVal = Number($input.val()) || 0;
                const remaining = Math.max(0, original - inputVal);

                const $balanceSpan = $(`#balance_expense_${rowId}_${expId}`);
                if ($balanceSpan.length) {
                    $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
                    const $card = $balanceSpan.closest('.balance-card');
                    if ($card.length) {
                        if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                        else if (remaining < original) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                        else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
                    }
                    totalRemaining += remaining;
                }
            });

            // === CARRIED ADVANCES ===
            $(`[id^="adj_carried_activity_${rowId}"]`).each(function() {
                const $input = $(this);
                const advanceId = $input.data('advance-id');
                const original = Number($input.data('carried-amt')) || 0;
                const inputVal = Number($input.val()) || 0;
                const remaining = Math.max(0, original - inputVal);

                const $balanceSpan = $(`#balance_carried_${rowId}_${advanceId}`);
                if ($balanceSpan.length) {
                    $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
                    const $card = $balanceSpan.closest('.balance-card');
                    if ($card.length) {
                        if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                        else if (remaining < original) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                        else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
                    }
                    totalRemaining += remaining;
                }
            });

            // Add remaining from unmatched paid/ledger/expense cards
            $balanceCell.find('.balance-card').each(function() {
                const $card = $(this);
                if (!updatedCards[$card.data('advance-id')]) {
                    totalRemaining += Number($card.data('original')) || 0;
                }
            });

            // Update total remaining display
            const $totalDisplay = $(`#total_remaining_display_${rowId}`);
            if ($totalDisplay.length) {
                $totalDisplay.text('₹' + formatNumber(Math.round(totalRemaining)));
                $totalDisplay.css('color', totalRemaining <= 0 ? '#999' : 'white');
            }

            return totalRemaining;
        }


        // Update activity remaining amounts
        function updateActivityRemaining(rowId) {
            $(`[id^="adj_activity_same_${rowId}_"]`).each(function() {
                const $input = $(this);
                const actId = $input.data('activity-id');
                const financed = Number($input.data('financed')) || 0;
                const sameAmount = Number($input.val()) || 0;
                const generalAmount = Number($(`#adj_general_activity_${rowId}`).val()) || 0;

                // Remaining after adjustments
                const remaining = Math.max(0, financed - sameAmount - generalAmount);

                const $remainingSpan = $(`#activity_remaining_${rowId}_${actId}`);
                if ($remainingSpan.length) {
                    $remainingSpan.text('₹' + formatNumber(remaining));
                    $remainingSpan.css('color', remaining > 0 ? '#1976d2' : '#999');
                }
            });
        }


        //   function updateExpenceRemaining(rowId) {
        //     $(`[id^="adj_expense_${rowId}_"]`).each(function() {
        //         const $input = $(this);
        //         const expId = $input.data('expense-id');
        //         const expadvance = Number($input.data('advance')) || 0;
        //         // const sameAmount = Number($input.val()) || 0;
        //         const expInputVal = Number($(`#adj_expense_${rowId}_${expId}`).val()) || 0;

        //         // Remaining after adjustments
        //         const remaining = Math.max(0, expadvance - expInputVal);

        //         const $remainingSpan = $(`#balance_expense_${rowId}_${expId}`);
        //         if ($remainingSpan.length) {
        //             $remainingSpan.text('₹' + formatNumber(remaining));
        //             $remainingSpan.css('color', remaining > 0 ? '#1976d2' : '#999');
        //         }
        //     });
        // }


        // REPLACE THIS FUNCTION
        function updateExpenceRemaining(rowId) {
            $(`[id^="adj_expense_${rowId}_"]`).each(function() {
                const $input = $(this);
                const expId = $input.data('expense-id');
                const expadvance = Number($input.data('advance')) || 0;
                const expInputVal = Number($(this).val()) || 0;

                // Remaining after adjustments
                const remaining = Math.max(0, expadvance - expInputVal);

                const $remainingSpan = $(`#balance_expense_${rowId}_${expId}`);
                if ($remainingSpan.length) {
                    $remainingSpan.text('₹' + formatNumber(Math.round(remaining)));
                    $remainingSpan.css('color', remaining > 0 ? '#1976d2' : '#999');
                }
            });
        }


        function updateGeneralBalance(rowId) {
            $(`[id^="adj_general_activity_${rowId}_"]`).each(function() {
                const $input = $(this);
                const genActId = $input.data('genral-actid');
                const index = $input.data('index');
                const genActVal = Number($input.data('original')) || 0;

                // const sameAmount = Number($input.val()) || 0;
                const genActInputVal = Number($(`#adj_general_activity_${rowId}_${genActId}_${index}`).val()) || 0;

                console.log("genActId");
                console.log(genActId);
                console.log(genActVal);
                // Remaining after adjustments
                const remaining = Math.max(0, genActVal - genActInputVal);

                const $remainingSpan = $(`#balance_ledger_${rowId}_${genActId}_${index}`);
                if ($remainingSpan.length) {
                    $remainingSpan.text('₹' + formatNumber(remaining));
                    $remainingSpan.css('color', remaining > 0 ? '#1976d2' : '#999');
                }
            });
        }



        function updateCarriedBalance(rowId) {
            $(`[id^="adj_carried_activity_${rowId}_"]`).each(function() {
                const $input = $(this);
                const genActId = $input.data('advance-id');
                const genActVal = Number($input.data('carried-amt')) || 0;

                // const sameAmount = Number($input.val()) || 0;
                const genActInputVal = Number($(`#adj_carried_activity_${rowId}_${genActId}`).val()) || 0;

                console.log("genActId");
                console.log(genActId);
                console.log(genActVal);
                console.log(genActInputVal);
                // Remaining after adjustments
                const remaining = Math.max(0, genActVal - genActInputVal);

                const $remainingSpan = $(`#balance_carried_${rowId}_${genActId}`);
                if ($remainingSpan.length) {
                    $remainingSpan.text('₹' + formatNumber(remaining));
                    $remainingSpan.css('color', remaining > 0 ? '#1976d2' : '#999');
                }
            });
        }




        // Update same activity available balances
        function updateSameActivityBalances(rowId) {
            $(`[id^="adj_activity_same_${rowId}_"]`).each(function() {
                const $input = $(this);
                const actId = $input.data('activity-id');
                const actAdvId = $input.data('activity-advance-id');
                const originalMax = Number($input.data('original-max')) || 0;
                const currentVal = Number($input.val()) || 0;
                const remainingBalance = Math.max(0, originalMax - currentVal);

                // Update available display (internal to the advance card)
                const $availableSpan = $(`#same_available_${rowId}_${actId}`);
                if ($availableSpan.length) {
                    $availableSpan.text(formatNumber(remainingBalance));
                }
                
                // Update the balance span in the activity-balance column
                // Use unique activity_advance_id (ara.id) for direct match
                if (actAdvId) {
                    const $balanceSpan = $(`#balance_paid_${rowId}_${actAdvId}`);
                    if ($balanceSpan.length) {
                        $balanceSpan.text('₹' + formatNumber(remainingBalance));
                        $balanceSpan.css('color', remainingBalance > 0 ? '#1b5e20' : '#999');
                    }
                }

                // Update input max
                $input.attr('max', originalMax);
                $input.data('current-max', remainingBalance);

                // Update card opacity
                const $card = $(`#activity_card_${rowId}_${actId}`);
                if ($card.length) {
                    $card.css('opacity', remainingBalance > 0 ? '1' : '0.5');
                }
            });
        }

        // Update ledger balances with FIFO and show remaining balances
        function updateLedgerBalances(rowId, totalActivityUsed) {
            const advanceElements = $(`[id^="total_adj_adv_row_${rowId}_el_"]`);
            if (advanceElements.length === 0) return;

            let remainingToDeduct = totalActivityUsed;
            let totalRemaining = 0;

            // Track used amounts per advance
            const usedAmounts = [];

            advanceElements.each(function(index) {
                const $hidden = $(this);
                const originalAmount = Number($hidden.val()) || 0;
                const $balanceSpan = $(`#ledger_balance_${rowId}_el_${index}`);
                const $usedSpan = $(`#used_amount_${rowId}_${index}`);
                const $progressBar = $(`#progress_bar_${rowId}_${index}`);
                const $card = $(`#advance_card_${rowId}_${index}`);

                let remainingBalance = originalAmount;
                let usedAmount = 0;

                // Deduct from this advance if there's still adjustment left
                if (remainingToDeduct > 0 && remainingBalance > 0) {
                    const deductFromThis = Math.min(remainingBalance, remainingToDeduct);
                    remainingBalance -= deductFromThis;
                    usedAmount = deductFromThis;
                    remainingToDeduct -= deductFromThis;
                }

                // Calculate usage percentage for progress bar
                const usagePercentage = (usedAmount / originalAmount) * 100;

                // Update displays
                if ($balanceSpan.length) {
                    $balanceSpan.text('₹' + formatNumber(Math.round(remainingBalance)));

                    // Update used amount display
                    if ($usedSpan.length) {
                        $usedSpan.text('₹' + formatNumber(Math.round(usedAmount)));
                    }

                    // Update progress bar
                    if ($progressBar.length) {
                        $progressBar.css('width', usagePercentage + '%');
                    }

                    // Visual feedback based on usage
                    if (remainingBalance <= 0) {
                        // Fully used
                        $balanceSpan.css('color', '#999');
                        $balanceSpan.closest('div').css('color', '#999');
                        if ($card.length) {
                            $card.css('opacity', '0.5');
                            $card.css('background', '#f5f5f5');
                            $card.css('border-left-color', '#999');
                        }
                    } else if (usedAmount > 0) {
                        // Partially used
                        $balanceSpan.css('color', '#f57c00');
                        if ($card.length) {
                            $card.css('opacity', '0.9');
                            $card.css('background', $card.data('type') === 'current_activity' ? '#ffe0b2' :
                                '#bbdefb');
                        }
                    } else {
                        // Not used
                        $balanceSpan.css('color', '#2e7d32');
                        if ($card.length) {
                            $card.css('opacity', '1');
                            $card.css('background', $card.data('type') === 'current_activity' ? '#fff3e0' :
                                '#e3f2fd');
                        }
                    }
                }

                totalRemaining += remainingBalance;
                usedAmounts.push(usedAmount);
            });

            // Update total activity balance
            const $totalActivity = $(`#total_activity_balance_${rowId}`);
            if ($totalActivity.length) {
                $totalActivity.text('₹' + formatNumber(Math.round(totalRemaining)));

                // Change color based on remaining amount
                if (totalRemaining <= 0) {
                    $totalActivity.css('color', '#999');
                } else {
                    $totalActivity.css('color', 'white');
                }
            }

            // Log remaining balances for debugging
            console.log(`Row ${rowId} - Remaining Balances:`, {
                totalRemaining: totalRemaining,
                usedAmounts: usedAmounts,
                remainingToDeduct: remainingToDeduct
            });

            return {
                totalRemaining,
                usedAmounts
            };
        }

        // Update activity remaining text
        function updateActivityRemainingText(rowId) {
            $(`[id^="activity_remaining_${rowId}_"]`).each(function() {
                const $span = $(this);
                const original = Number($span.data('original')) || 0;
                const text = $span.text().replace(/[^0-9.-]/g, '');
                const current = Number(text) || 0;

                if (current <= 0) {
                    $span.css('color', '#999');
                } else {
                    $span.css('color', '#1976d2');
                }
            });
        }
        // Event handlers
        $(document).ready(function() {
            // General activity input
            $(document).on('input', '[id^="adj_general_activity_"]', function() {
                const $input = $(this);
                const rowId = $input.data('row-id');
                const maxAllowed = Number($input.attr('max')) || 0;
                const currentVal = Number($input.val()) || 0;
                console.log('hello keshav');
                if (currentVal > maxAllowed) {
                    $input.val(maxAllowed);
                }

                if (rowId) {
                    updateAllBalances(rowId);
                }
            });

            // Same activity input
            $(document).on('input', '[id^="adj_activity_same_"]', function() {
                const $input = $(this);

                const advanceId = $input.data('advance-id');
                const originalMax = Number($input.data('original-max')) || 0;

                let currentVal = Number($input.val()) || 0;

                // ✅ Step 1: Calculate total of OTHER inputs with same advance-id
                let otherTotal = 0;

                $(`[data-advance-id="${advanceId}"]`).find('input').not($input).each(function() {
                    otherTotal += Number($(this).val()) || 0;
                });

                // ✅ Step 2: Remaining allowed for this input
                let remaining = originalMax - otherTotal;

                if (remaining < 0) remaining = 0;

                // ✅ Step 3: Restrict current input
                if (currentVal > remaining) {
                    currentVal = remaining;
                    $input.val(currentVal);
                }

                // ✅ Step 4: Dynamically update max attribute
                $input.attr('max', remaining);

                // Optional: update UI
                const rowId = $input.data('row-id');
                if (rowId) {
                    updateAllBalances(rowId);
                }
            });

            // Expense input
            $(document).on('input', '[id^="adj_expense_"]', function() {
                const $input = $(this);
                const rowId = $input.data('row-id');

                if (rowId) {
                    updateAllBalances(rowId);
                }
            });

            // Carried adjustments
            $(document).on('input', '[id^="adj_carried_activity_"]', function() {
                const $input = $(this);
                const rowId = $input.data('row-id');
                const maxAllowed = $input.data('max-total') || 0;
                // const maxAllowed = Number($input.attr('max')) || 0;
                const currentVal = Number($input.val()) || 0;
                console.log("rowId");
                console.log(maxAllowed);
                console.log(currentVal);
                console.log(rowId);
                if (currentVal > maxAllowed) {
                    $input.val(maxAllowed);
                }

                if (rowId) {
                    updateAllBalances(rowId);
                }
            });

            // Initialize all rows
            $('tr[id^="row_"]').each(function() {
                const rowId = $(this).attr('id').replace('row_', '');
                updateAllBalances(rowId);
            });
        });


        // function approveClaimMonth(baseAmount, month, empId, rowNo) {
        //     if (!confirm('Are you sure you want to approve this claim?')) {
        //         return;
        //     }

        //     // Collect adjustment data in the format expected by controller
        //     let activityAdjustments = {};
        //     let expenseAdjustments = {};

        //     let carriedActivityAdjustment = 0;
        //     let carriedExpenseAdjustment = 0;
        //     let totalActivityAdjustment = 0;
        //     let totalExpenseAdjustment = 0;


        //     // ===== COLLECT ACTIVITY SAME ADJUSTMENTS =====
        //     $(`[id^="adj_activity_same_${rowNo}_"]`).each(function() {
        //         const $input = $(this);
        //         const activityId = $input.data('activity-id');
        //         const val = Number($input.val()) || 0;

        //         if (val > 0 && activityId) {
        //             // You need to get the advance_activity_id - you might need to add this data attribute
        //             // For now, we'll use the activityId as a placeholder
        //             activityAdjustments[activityId] = {
        //                 amount: val,
        //                 advance_activity_id: activityId // You need to ensure this is the correct advance_activity_id
        //             };
        //             totalActivityAdjustment += val;
        //         }
        //     });

        //     // ===== Rmeaining balances or carried balances =====
        //     const remainingBalances = {
        //         ledger: [],
        //         paid: [],
        //         expense: [],
        //         carried: []
        //     };

        //     $(`#ledger_cell_${rowNo} .balance-card`).each(function() {
        //         const $card = $(this);
        //         const advanceId = $card.data('advance-id');
        //         const category = $card.data('category');
        //         const month = $card.data('month');
        //         const payment_date = $card.data('payment-date');
        //         const originalAmount = Number($card.data('original')) || 0;

        //         const $balanceSpan = $(`#balance_${advanceId}`);
        //         let remainingAmount = originalAmount;

        //         if ($balanceSpan.length) {
        //             const balanceText = $balanceSpan.text().replace(/[^0-9.-]/g, '');
        //             remainingAmount = Number(balanceText) || 0;
        //         }
        //         const realId = advanceId.split('_').pop();
        //         if (remainingAmount > 0 && remainingBalances[category]) {
        //             remainingBalances[category].push({
        //                 advance_id: realId,
        //                 original_amount: originalAmount,
        //                 remaining_amount: remainingAmount,
        //                 category: category,
        //                 month: month,
        //                 payment_date: payment_date

        //             });
        //         }
        //     });

        //     // Calculate total carried forward amount
        //     const totalCarriedForward =
        //         (remainingBalances.ledger || []).reduce((s, i) => s + i.remaining_amount, 0) +
        //         (remainingBalances.paid || []).reduce((s, i) => s + i.remaining_amount, 0) +
        //         (remainingBalances.expense || []).reduce((s, i) => s + i.remaining_amount, 0) +
        //         (remainingBalances.carried || []).reduce((s, i) => s + i.remaining_amount, 0);



        //     // ===== COLLECT GENERAL ACTIVITY ADJUSTMENTS =====
        //     $(`[id^="adj_general_activity_${rowNo}_"]`).each(function() {
        //         const $input = $(this);
        //         const genActId = $input.data('genral-actid');
        //         const val = Number($input.val()) || 0;

        //         if (val > 0 && genActId) {
        //             // For general activity, we need to know which advance_activity_id it belongs to
        //             activityAdjustments['general_' + genActId] = {
        //                 amount: val,
        //                 advance_activity_id: genActId
        //             };
        //             totalActivityAdjustment += val;
        //         }
        //     });

        //     // ===== COLLECT EXPENSE ADJUSTMENTS =====
        //     $(`[id^="adj_expense_${rowNo}_"]`).each(function() {
        //         const $input = $(this);
        //         const expenseId = $input.data('expense-id');
        //         const val = Number($input.val()) || 0;

        //         if (val > 0 && expenseId) {
        //             expenseAdjustments[expenseId] = {
        //                 amount: val,
        //                 advance_activity_id: expenseId // Make sure this is the correct advance_activity_id
        //             };
        //             totalExpenseAdjustment += val;
        //         }
        //     });

        //     // ===== COLLECT CARRIED ACTIVITY ADJUSTMENTS =====
        //     $(`[id^="adj_carried_activity_${rowNo}"]`).each(function(index) {
        //         const $input = $(this);
        //         const val = Number($input.val()) || 0;

        //         if (val > 0) {
        //             carriedActivityAdjustment += val;
        //             // Create a unique key for carried activity
        //             const carriedKey = 'carried_activity_' + index;
        //             activityAdjustments[carriedKey] = {
        //                 amount: val,
        //                 advance_activity_id: 'carried_' + index
        //             };
        //             totalActivityAdjustment += val;
        //         }
        //     });

        //     // ===== COLLECT CARRIED EXPENSE ADJUSTMENTS =====
        //     $(`[id^="adj_carried_expense_${rowNo}"]`).each(function(index) {
        //         const $input = $(this);
        //         const val = Number($input.val()) || 0;

        //         if (val > 0) {
        //             carriedExpenseAdjustment += val;
        //             // Create a unique key for carried expense
        //             const carriedKey = 'carried_expense_' + index;
        //             expenseAdjustments[carriedKey] = {
        //                 amount: val,
        //                 advance_activity_id: 'carried_expense_' + index
        //             };
        //             totalExpenseAdjustment += val;
        //         }
        //     });

        //     // Calculate total adjustment
        //     const totalAdjustment = totalActivityAdjustment + totalExpenseAdjustment;

        //     // Get actual expense amount (without DA) from your display
        //     const actualExpenseText = $('#actual_diff_' + rowNo).text().replace(/[^0-9.-]/g, '');
        //     const actualExpenseAmount = Number(actualExpenseText) || 0;

        //     // Get ledger balance at approval (from your activity balance column)
        //     // You might need to calculate this from your remaining balances
        //     const ledgerBalanceAtApproval = calculateTotalRemainingBalance(rowNo);

        //     // Calculate payment amount (actual expense minus total adjustments)
        //     const paymentAmount = Math.max(0, actualExpenseAmount - totalAdjustment);

        //     // Show processing state
        //     $('#row_' + rowNo).css('opacity', '0.5');
        //     const $btn = $('#row_' + rowNo).find('button');
        //     const originalBtnText = $btn.html();
        //     $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        //     // Log the data being sent (for debugging)
        //     console.log('Sending approval data:', {
        //         employee_id: empId,
        //         month: month,
        //         payment_amount: paymentAmount,
        //         ledger_balance_at_approval: ledgerBalanceAtApproval,
        //         actual_expense_amount: actualExpenseAmount,
        //         activity_adjustments: JSON.stringify(activityAdjustments),
        //         expense_adjustments: JSON.stringify(expenseAdjustments),
        //         carried_activity_adjustment: carriedActivityAdjustment,
        //         carried_expense_adjustment: carriedExpenseAdjustment,
        //         total_activity_adjustment: totalActivityAdjustment,
        //         total_expense_adjustment: totalExpenseAdjustment,
        //         total_adjustment: totalAdjustment,
        //         remaining_balances: JSON.stringify(remainingBalances),
        //         total_adjustment: totalAdjustment,
        //     });
        //     // return;
        //     // Send approval request
        //     $.ajax({
        //         url: '{{ route('expense-payments.approve') }}',
        //         type: 'POST',
        //         data: {
        //             _token: '{{ csrf_token() }}',
        //             employee_id: empId,
        //             month: month,
        //             payment_amount: paymentAmount,
        //             ledger_balance_at_approval: ledgerBalanceAtApproval,
        //             actual_expense_amount: actualExpenseAmount,
        //             activity_adjustments: JSON.stringify(activityAdjustments),
        //             expense_adjustments: JSON.stringify(expenseAdjustments),
        //             carried_activity_adjustment: carriedActivityAdjustment,
        //             carried_expense_adjustment: carriedExpenseAdjustment,
        //             total_activity_adjustment: totalActivityAdjustment,
        //             total_expense_adjustment: totalExpenseAdjustment,
        //             total_adjustment: totalAdjustment,
        //             remaining_balances: JSON.stringify(remainingBalances),
        //             total_adjustment: totalAdjustment,
        //         },
        //         success: function(response) {
        //             // Your controller returns plain text 'success' on success
        //             if (response === 'success') {
        //                 alert('Claim approved successfully!');
        //                 // Remove the row or update UI
        //                 $('#row_' + rowNo).fadeOut(500, function() {
        //                     $(this).remove();
        //                 });
        //             } else {
        //                 alert('Error: ' + response);
        //                 $('#row_' + rowNo).css('opacity', '1');
        //                 $btn.prop('disabled', false).html(originalBtnText);
        //             }
        //         },
        //         error: function(xhr, status, error) {
        //             let errorMsg = 'Error: ';
        //             if (xhr.responseText) {
        //                 errorMsg += xhr.responseText;
        //             } else {
        //                 errorMsg += error;
        //             }
        //             alert(errorMsg);
        //             $('#row_' + rowNo).css('opacity', '1');
        //             $btn.prop('disabled', false).html(originalBtnText);
        //         }
        //     });
        // }



        function recalcRowTotals(rowNo, actualExpense, baseAdjustment) {
            const otherAdj = Number($(`#row_${rowNo} .other-adj-input`).val()) || 0;
            const totalAdj = baseAdjustment + otherAdj;
            const payable = Math.max(0, actualExpense - totalAdj);

            $(`#total_adj_all_${rowNo}`).text(totalAdj.toLocaleString('en-IN'));
            $(`#diff_${rowNo} .payable-amount`).text(payable.toLocaleString('en-IN'));
        }

        function saveOtherAdjustment(id) {
            const amountInput = document.querySelector(`.other-adj-input[data-id="${id}"]`);
            const remarkInput = document.querySelector(`.other-adj-remark-input[data-id="${id}"]`);
            const amount = amountInput ? amountInput.value : 0;
            const remark = remarkInput ? remarkInput.value : '';

            $.ajax({
                url: '{{ route("expense-payments.update-other-adjustment") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    id: id,
                    other_adjustment: amount,
                    other_adjustment_remark: remark
                },
                success: function(response) {
                    if (response.success) {
                        alert('Other adjustment saved. Refreshing page...');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('Error saving adjustment: ' + (xhr.responseJSON?.message || 'Unknown error'));
                }
            });
        }

        function approveClaimMonth(baseAmount, month, empId, rowNo) {

    // Collect adjustment data in the format expected by controller
    let activityAdjustments = {};
    let expenseAdjustments = {};

    let carriedActivityAdjustment = 0;
    let carriedExpenseAdjustment = 0;
    let totalActivityAdjustment = 0;
    let totalExpenseAdjustment = 0;


    // ===== COLLECT ACTIVITY SAME ADJUSTMENTS =====
    $(`[id^="adj_activity_same_${rowNo}_"]`).each(function() {
        const $input = $(this);
        const activityId = $input.data('activity-id');
        const activityAdvanceId = $input.data('activity-advance-id') || activityId;
        const val = Number($input.val()) || 0;

        if (val > 0 && activityAdvanceId) {
            // Use the real DB id (activity_advance_id) as key, not composite key
            const dbId = String(activityAdvanceId).split('_')[0]; // Extract numeric ID if composite
            activityAdjustments[dbId] = {
                amount: val,
                advance_activity_id: dbId
            };
            totalActivityAdjustment += val;
        }
    });

    // ===== Rmeaining balances or carried balances =====
    const remainingBalances = {
        ledger: [],
        paid: [],
        expense: [],
        carried: []
    };

    $(`#ledger_cell_${rowNo} .balance-card`).each(function() {
        const $card = $(this);
        const advanceId = $card.data('advance-id');
        const category = $card.data('category');
        const month = $card.data('month');
        const payment_date = $card.data('payment-date');
        const originalAmount = Number($card.data('original')) || 0;

        const $balanceSpan = $(`#balance_${advanceId}`);
        let remainingAmount = originalAmount;

        if ($balanceSpan.length) {
            const balanceText = $balanceSpan.text().replace(/[^0-9.-]/g, '');
            remainingAmount = Number(balanceText) || 0;
        }
        const realId = advanceId.split('_').pop();
        if (remainingAmount > 0 && remainingBalances[category]) {
            remainingBalances[category].push({
                advance_id: realId,
                original_amount: originalAmount,
                remaining_amount: remainingAmount,
                category: category,
                month: month,
                payment_date: payment_date

            });
        }
    });

    // Calculate total carried forward amount
    const totalCarriedForward =
        (remainingBalances.ledger || []).reduce((s, i) => s + i.remaining_amount, 0) +
        (remainingBalances.paid || []).reduce((s, i) => s + i.remaining_amount, 0) +
        (remainingBalances.expense || []).reduce((s, i) => s + i.remaining_amount, 0) +
        (remainingBalances.carried || []).reduce((s, i) => s + i.remaining_amount, 0);



    // ===== COLLECT GENERAL ACTIVITY ADJUSTMENTS =====
    $(`[id^="adj_general_activity_${rowNo}_"]`).each(function() {
        const $input = $(this);
        const genActId = $input.data('genral-actid');
        const val = Number($input.val()) || 0;

        if (val > 0 && genActId) {
            activityAdjustments['general_' + genActId] = {
                amount: val,
                advance_activity_id: genActId
            };
            totalActivityAdjustment += val;
        }
    });

    // ===== COLLECT EXPENSE ADJUSTMENTS =====
    $(`[id^="adj_expense_${rowNo}_"]`).each(function() {
        const $input = $(this);
        const expenseId = $input.data('expense-id');
        const val = Number($input.val()) || 0;

        if (val > 0 && expenseId) {
            expenseAdjustments[expenseId] = {
                amount: val,
                advance_activity_id: expenseId
            };
            totalExpenseAdjustment += val;
        }
    });

    // ===== COLLECT CARRIED ACTIVITY ADJUSTMENTS =====
    $(`[id^="adj_carried_activity_${rowNo}"]`).each(function(index) {
        const $input = $(this);
        const val = Number($input.val()) || 0;

        if (val > 0) {
            carriedActivityAdjustment += val;
            const carriedKey = 'carried_activity_' + index;
            activityAdjustments[carriedKey] = {
                amount: val,
                advance_activity_id: 'carried_' + index
            };
            totalActivityAdjustment += val;
        }
    });

    // ===== COLLECT CARRIED EXPENSE ADJUSTMENTS =====
    $(`[id^="adj_carried_expense_${rowNo}"]`).each(function(index) {
        const $input = $(this);
        const val = Number($input.val()) || 0;

        if (val > 0) {
            carriedExpenseAdjustment += val;
            const carriedKey = 'carried_expense_' + index;
            expenseAdjustments[carriedKey] = {
                amount: val,
                advance_activity_id: 'carried_expense_' + index
            };
            totalExpenseAdjustment += val;
        }
    });

    // Calculate total adjustment (includes other adjustment)
    const otherAdjInput = $(`#row_${rowNo}`).find('.other-adj-input');
    const otherAdjRemarkInput = $(`#row_${rowNo}`).find('.other-adj-remark-input');
    const otherAdjustment = otherAdjInput.length ? (Number(otherAdjInput.val()) || 0) : 0;
    const otherAdjustmentRemark = otherAdjRemarkInput.length ? otherAdjRemarkInput.val().trim() : '';

    // Validate: if other adjustment > 0, remark is required
    if (otherAdjustment > 0 && otherAdjustmentRemark === '') {
        alert('Please enter a remark for Other Adjustment.');
        otherAdjRemarkInput.focus();
        return;
    }

    const totalAdjustment = totalActivityAdjustment + totalExpenseAdjustment + otherAdjustment;

    // Get actual expense amount (without DA) from your display
    const actualExpenseText = $('#actual_diff_' + rowNo).text().replace(/[^0-9.-]/g, '');
    const actualExpenseAmount = Number(actualExpenseText) || 0;

    // Get ledger balance at approval (from your activity balance column)
    const ledgerBalanceAtApproval = calculateTotalRemainingBalance(rowNo);

    // Calculate payment amount (actual expense minus total adjustments)
    const paymentAmount = Math.max(0, actualExpenseAmount - totalAdjustment);

    // LV-U02 / U-01: confirmation names the employee, month and amounts
    const fmt = v => Number(v || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const $tds = $('#row_' + rowNo).find('td'); const empLabel = (($tds.eq(1).text() || '').trim().replace(/\s+/g, ' ') + ' (' + ($tds.eq(2).text() || '').trim() + ')').replace(/^ \(\)$/, '') || ('Employee ' + empId);
    const monthLabel = new Date(2000, Number(month) - 1, 1).toLocaleString('en-IN', {month: 'long'});
    if (!confirm('Approve payment?\n\nEmployee: ' + empLabel + '\nMonth: ' + monthLabel +
        '\nActual expense (without DA): Rs. ' + fmt(actualExpenseAmount) +
        '\nAdvance / other adjustments: Rs. ' + fmt(totalAdjustment) +
        '\nPayment: Rs. ' + fmt(paymentAmount))) {
        return;
    }

    // Show processing state
    $('#row_' + rowNo).css('opacity', '0.5');
    const $btn = $('#row_' + rowNo).find('button');
    const originalBtnText = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

    // Log the data being sent (for debugging)
    console.log('Sending approval data:', {
        employee_id: empId,
        month: month,
        payment_amount: paymentAmount,
        ledger_balance_at_approval: ledgerBalanceAtApproval,
        actual_expense_amount: actualExpenseAmount,
        activity_adjustments: JSON.stringify(activityAdjustments),
        expense_adjustments: JSON.stringify(expenseAdjustments),
        carried_activity_adjustment: carriedActivityAdjustment,
        carried_expense_adjustment: carriedExpenseAdjustment,
        total_activity_adjustment: totalActivityAdjustment,
        total_expense_adjustment: totalExpenseAdjustment,
        total_adjustment: totalAdjustment,
        remaining_balances: JSON.stringify(remainingBalances),
        total_adjustment: totalAdjustment,
    });

    // Send approval request
    $.ajax({
        url: '{{ route('expense-payments.approve') }}',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            employee_id: empId,
            month: month,
            payment_amount: paymentAmount,
            ledger_balance_at_approval: ledgerBalanceAtApproval,
            actual_expense_amount: actualExpenseAmount,
            activity_adjustments: JSON.stringify(activityAdjustments),
            expense_adjustments: JSON.stringify(expenseAdjustments),
            carried_activity_adjustment: carriedActivityAdjustment,
            carried_expense_adjustment: carriedExpenseAdjustment,
            total_activity_adjustment: totalActivityAdjustment,
            total_expense_adjustment: totalExpenseAdjustment,
            other_adjustment: otherAdjustment,
            other_adjustment_remark: otherAdjustmentRemark,
            total_adjustment: totalAdjustment,
            remaining_balances: JSON.stringify(remainingBalances),
            company_id: '{{ session("company_id") }}', // Add company_id to request
        },
        success: function(response) {
            if (response === 'success') {
                alert('Claim approved successfully!');
                $('#row_' + rowNo).fadeOut(500, function() {
                    $(this).remove();
                });
            } else {
                alert('Error: ' + response);
                $('#row_' + rowNo).css('opacity', '1');
                $btn.prop('disabled', false).html(originalBtnText);
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'Error: ';
            if (xhr.responseText) {
                errorMsg += xhr.responseText;
            } else {
                errorMsg += error;
            }
            alert(errorMsg);
            $('#row_' + rowNo).css('opacity', '1');
            $btn.prop('disabled', false).html(originalBtnText);
        }
    });
}




// Add this function to your main blade file's JavaScript section
function updateCompany11Balances(rowId) {
    const isCompany11 = {{ (int) session('company_id') === 11 ? 'true' : 'false' }};

    if (!isCompany11) return;

    // For company 11, all advances are fully available for adjustment
    let totalAvailable = 0;

    // Calculate total from all balance cards
    $(`#ledger_cell_${rowId} .balance-card`).each(function() {
        const originalAmount = Number($(this).data('original')) || 0;
        totalAvailable += originalAmount;
    });

    // Update total display
    const $totalDisplay = $(`#total_remaining_display_${rowId}`);
    if ($totalDisplay.length) {
        $totalDisplay.text('₹' + formatNumber(Math.round(totalAvailable)));
    }

    return totalAvailable;
}

// Update the updateAllRemainingBalances function to handle company 11
function updateAllRemainingBalances(rowId) {
    const isCompany11 = {{ (int) session('company_id') === 11 ? 'true' : 'false' }};

    const $balanceCell = $(`#ledger_cell_${rowId}`);
    if (!$balanceCell.length) return 0;

    let totalRemaining = 0;
    const updatedCards = {};

    // === PAID ADVANCES: Use activity_advance_id for direct matching ===
    $(`[id^="adj_activity_same_${rowId}_"]`).each(function() {
        const $input = $(this);
        const actAdvId = $input.data('activity-advance-id');
        const originalMax = Number($input.data('original-max')) || 0;
        const currentVal = Number($input.val()) || 0;
        const remaining = Math.max(0, originalMax - currentVal);

        if (actAdvId) {
            const $balanceSpan = $(`#balance_paid_${rowId}_${actAdvId}`);
            if ($balanceSpan.length) {
                $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
                const $card = $balanceSpan.closest('.balance-card');
                if ($card.length) {
                    updatedCards[$card.data('advance-id')] = true;
                    if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                    else if (remaining < originalMax) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                    else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
                }
                totalRemaining += remaining;
                return;
            }
        }

        // Fallback
        $balanceCell.find(`.balance-card[data-category="paid"]`).each(function() {
            const $card = $(this);
            if (!updatedCards[$card.data('advance-id')]) {
                const $bs = $card.find(`[id^="balance_paid_"]`);
                if ($bs.length) {
                    $bs.text('₹' + formatNumber(Math.round(remaining)));
                    updatedCards[$card.data('advance-id')] = true;
                    if (remaining <= 0) { $bs.css('color', '#999'); $card.css('opacity', '0.5'); }
                    else if (remaining < originalMax) { $bs.css('color', '#f57c00'); $card.css('opacity', '1'); }
                    else { $bs.css('color', '#2e7d32'); $card.css('opacity', '1'); }
                    totalRemaining += remaining;
                    return false; // break
                }
            }
        });
    });

    // === LEDGER ADVANCES ===
    $(`[id^="adj_general_activity_${rowId}_"]`).each(function() {
        const $input = $(this);
        const genActId = $input.data('genral-actid');
        const index = $input.data('index');
        const original = Number($input.data('original')) || 0;
        const inputVal = Number($input.val()) || 0;
        const remaining = Math.max(0, original - inputVal);

        const $balanceSpan = $(`#balance_ledger_${rowId}_${genActId}_${index}`);
        if ($balanceSpan.length) {
            $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
            const $card = $balanceSpan.closest('.balance-card');
            if ($card.length) {
                updatedCards[$card.data('advance-id')] = true;
                if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                else if (remaining < original) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
            }
            totalRemaining += remaining;
        }
    });

    // === EXPENSE ADVANCES ===
    $(`[id^="adj_expense_${rowId}_"]`).each(function() {
        const $input = $(this);
        const expId = $input.data('expense-id');
        const original = Number($input.data('advance')) || 0;
        const inputVal = Number($input.val()) || 0;
        const remaining = Math.max(0, original - inputVal);

        const $balanceSpan = $(`#balance_expense_${rowId}_${expId}`);
        if ($balanceSpan.length) {
            $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
            const $card = $balanceSpan.closest('.balance-card');
            if ($card.length) {
                updatedCards[$card.data('advance-id')] = true;
                if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                else if (remaining < original) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
            }
            totalRemaining += remaining;
        }
    });

    // === CARRIED ADVANCES ===
    $(`[id^="adj_carried_activity_${rowId}"]`).each(function() {
        const $input = $(this);
        const advanceId = $input.data('advance-id');
        const original = Number($input.data('carried-amt')) || 0;
        const inputVal = Number($input.val()) || 0;
        const remaining = Math.max(0, original - inputVal);

        const $balanceSpan = $(`#balance_carried_${rowId}_${advanceId}`);
        if ($balanceSpan.length) {
            $balanceSpan.text('₹' + formatNumber(Math.round(remaining)));
            const $card = $balanceSpan.closest('.balance-card');
            if ($card.length) {
                updatedCards[$card.data('advance-id')] = true;
                if (remaining <= 0) { $balanceSpan.css('color', '#999'); $card.css('opacity', '0.5'); }
                else if (remaining < original) { $balanceSpan.css('color', '#f57c00'); $card.css('opacity', '1'); }
                else { $balanceSpan.css('color', '#2e7d32'); $card.css('opacity', '1'); }
            }
            totalRemaining += remaining;
        }
    });

    // Add unmatched cards
    $balanceCell.find('.balance-card').each(function() {
        const $card = $(this);
        if (!updatedCards[$card.data('advance-id')]) {
            totalRemaining += Number($card.data('original')) || 0;
        }
    });

    const $totalDisplay = $(`#total_remaining_display_${rowId}`);
    if ($totalDisplay.length) {
        $totalDisplay.text('₹' + formatNumber(Math.round(totalRemaining)));
        $totalDisplay.css('color', totalRemaining <= 0 ? '#999' : 'white');
    }

    return totalRemaining;
}

        // Helper function to calculate total remaining balance from your activity balance column
        function calculateTotalRemainingBalance(rowNo) {
            let totalRemaining = 0;

            // Look for all balance displays in the activity balance column
            $(`#ledger_cell_${rowNo} .remaining-amount`).each(function() {
                const text = $(this).text().replace(/[^0-9.-]/g, '');
                totalRemaining += Number(text) || 0;
            });

            return totalRemaining;
        }


        // REPLACE THIS ENTIRE FUNCTION
        function updateAllBalances(rowId) {
            console.log('Updating balances for row:', rowId);

            // Get general adjustment value
            const generalAdjustment = Number($(`#adj_general_activity_${rowId}`).val()) || 0;

            // Update general balance display
            updateGeneralBalance(rowId);

            updateCarriedBalance(rowId);

            // Update same activity available balances
            updateSameActivityBalances(rowId);

            // Update all advance remaining balances
            // updateAdvanceRemainingBalances(rowId, generalAdjustment);

            // Update activity remaining amounts
            updateActivityRemaining(rowId);

            updateExpenceRemaining(rowId)

            // Recalculate totals
            recalculateTotals(rowId);
        }



        // REPLACE THIS FUNCTION
        function recalculateTotals(rowId) {
            // Calculate all adjustments
            let totalSame = 0;
            let totalGeneral = 0;
            let totalExpense = 0;
            let totalCarried = 0;

            $(`[id^="adj_activity_same_${rowId}_"]`).each(function() {
                totalSame += Number($(this).val()) || 0;
            });

            $(`[id^="adj_general_activity_${rowId}_"]`).each(function() {
                totalGeneral += Number($(this).val()) || 0;
            });

            $(`[id^="adj_expense_${rowId}_"]`).each(function() {
                totalExpense += Number($(this).val()) || 0;
            });

            $(`[id^="adj_carried_activity_${rowId}"]`).each(function() {
                totalCarried += Number($(this).val()) || 0;
            });

            $(`[id^="adj_carried_expense_${rowId}"]`).each(function() {
                totalCarried += Number($(this).val()) || 0;
            });

            const totalActivityAdj = totalSame + totalGeneral;
            const totalAdjustment = totalActivityAdj + totalExpense + totalCarried;

            // Get actual expense
            const actualExpenseText = $('#actual_diff_' + rowId).text().replace(/[^0-9.-]/g, '');
            const actualExpense = Number(actualExpenseText) || 0;
            const payable = Math.max(0, actualExpense - totalAdjustment);

            // Update displays
            $('#diff_' + rowId + ' span').text('₹' + formatNumber(Math.round(payable)));
            $('#total_adj_all_' + rowId).text(formatNumber(Math.round(totalAdjustment)));

            // Update expense total
            const $totalAdjExpense = $('#total_adj_expense_' + rowId);
            if ($totalAdjExpense.length) {
                $totalAdjExpense.text('₹' + formatNumber(Math.round(totalExpense + totalCarried)));
            }
        }



        // // REPLACE THIS FUNCTION
        // function updateAdvanceRemainingBalances(rowId, generalAdjustment) {
        //     // Get all adjustments
        //     let totalSameActivity = 0;
        //     let totalExpenseAdj = 0;
        //     let totalCarriedAdj = 0;

        //     $(`[id^="adj_activity_same_${rowId}_"]`).each(function() {
        //         totalSameActivity += Number($(this).val()) || 0;
        //     });

        //     $(`[id^="adj_expense_${rowId}_"]`).each(function() {
        //         totalExpenseAdj += Number($(this).val()) || 0;
        //     });

        //     $(`[id^="adj_carried_activity_${rowId}"]`).each(function() {
        //         totalCarriedAdj += Number($(this).val()) || 0;
        //     });
        //     $(`[id^="adj_carried_expense_${rowId}"]`).each(function() {
        //         totalCarriedAdj += Number($(this).val()) || 0;
        //     });

        //     // Total activity adjustments
        //     const totalActivityAdj = totalSameActivity + generalAdjustment;

        //     console.log('Adjustments for remaining calc:', {
        //         activity: totalActivityAdj,
        //         expense: totalExpenseAdj,
        //         carried: totalCarriedAdj
        //     });

        //     // Update each balance card in the activity balance column
        //     let totalRemaining = 0;

        //     // Find all balance cards in the ledger cell
        //     $(`#ledger_cell_${rowId} .balance-card`).each(function() {
        //         const $card = $(this);
        //         const advanceId = $card.data('advance-id');
        //         const category = $card.data('category');
        //         const originalAmount = Number($card.data('original')) || 0;

        //         // Calculate used amount based on category
        //         let usedAmount = 0;

        //         // IMPORTANT: Distinguish between different types
        //         if (category === 'ledger') {
        //             // Ledger balances are used for activity adjustments
        //             usedAmount = totalActivityAdj;
        //         } else if (category === 'paid') {
        //             // Paid advances are specific to activities
        //             // Try to find matching activity adjustment
        //             const actId = advanceId.split('_')[1]; // Extract activity ID
        //             const sameInput = $(`#adj_activity_same_${rowId}_${actId}`);
        //             if (sameInput.length) {
        //                 usedAmount = Number(sameInput.val()) || 0;
        //             } else {
        //                 usedAmount = totalActivityAdj;
        //             }
        //         } else if (category === 'expense') {
        //             usedAmount = totalExpenseAdj;
        //         } else if (category === 'carried') {
        //             usedAmount = totalCarriedAdj;
        //         }

        //         // Calculate remaining
        //         const remainingAmount = Math.max(0, originalAmount - usedAmount);

        //         // Update the balance display
        //         const $balanceSpan = $(`#balance_${advanceId}`);
        //         if ($balanceSpan.length) {
        //             $balanceSpan.text('₹' + formatNumber(Math.round(remainingAmount)));

        //             // Update data attribute for approval function
        //             $balanceSpan.attr('data-remaining', remainingAmount);

        //             // Update color
        //             if (remainingAmount <= 0) {
        //                 $balanceSpan.css('color', '#999');
        //                 $card.css('opacity', '0.5');
        //             } else if (remainingAmount < originalAmount) {
        //                 $balanceSpan.css('color', '#f57c00');
        //                 $card.css('opacity', '1');
        //             } else {
        //                 $balanceSpan.css('color', '#2e7d32');
        //                 $card.css('opacity', '1');
        //             }
        //         }

        //         totalRemaining += remainingAmount;
        //     });

        //     // Update total remaining display
        //     const $totalDisplay = $(`#total_remaining_display_${rowId}`);
        //     if ($totalDisplay.length) {
        //         $totalDisplay.text('₹' + formatNumber(Math.round(totalRemaining)));

        //         // Store total for approval function
        //         $totalDisplay.attr('data-total-remaining', totalRemaining);
        //     }

        //     return totalRemaining;
        // }
    </script>
