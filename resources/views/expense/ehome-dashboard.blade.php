<style>
    .eh-header {
        background: #737373d9;
        color: #fff;
        padding: 9px 14px;
        border-radius: 6px 6px 0 0;
        font-weight: 600;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .eh-header-open {
        background-color: #2c6e91d9;
    }

    .eh-header-closed {
        background-color: #4b8f29d9;
    }

    .eh-table thead th {
        background: #f3f6f9;
        color: #141414;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
        padding: 7px 10px;

    }

    .eh-table tbody tr {
        font-size: 12px;
        background: #fff;
    }

    .eh-table tbody tr:hover {
        background: #f0f5fb;
    }

    .eh-table td {
        text-align: center;
        vertical-align: middle;
        padding: 6px 10px;
        border-color: #dee2e6;
    }



    .eh-expand-panel {
        background: #eaf4fa;
        border-top: 2px solid #2c6e91d9;
    }

    .eh-expand-panel-closed {
        background: #f0f9eb;
        border-top: 2px solid #4b8f29d9;
    }



    .eh-nested-head th {
        background: #2c6e91d9;
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
        vertical-align: middle;
        padding: 5px 8px;
        /* border-color: #3a7fa5; */
    }

    .eh-nested-head-closed th {
        background: #4b8f29d9;
    }

    .eh-table .eh-nested-row td {
        font-size: 11px;
        padding: 5px 8px;
        background: #fff;
    }

    .eh-table .eh-nested-row:hover td {
        background: #eef6ff;
    }



    .eh-month {
        background: #2c6e91d9;
        color: #fff;
        border-radius: 4px;
        padding: 1px 10px;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: 1px;
        display: inline-block;
    }

    .eh-month-closed {
        background: #4b8f29d9;
        color: #fff;
        border-radius: 4px;
        padding: 1px 10px;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: 1px;
        display: inline-block;
    }



    .eh-toggle {
        cursor: pointer;
        user-select: none;
    }

    .eh-toggle .eh-arrow {
        display: inline-block;
        transition: transform .2s;
        color: #2c6e91;
        font-size: 15px;
        font-weight: 700;
    }

    .eh-toggle.open .eh-arrow {
        transform: rotate(90deg);
    }

    /* closed section arrow color */
    #closedMonthsTable .eh-arrow {
        color: #4b8f29d9;
    }



    .sp {
        display: inline-block;
        border-radius: 20px;
        padding: 2px 10px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .3px;
    }

    .sp-pending {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffc107;
    }

    .sp-filled {
        background: #cff4fc;
        color: #055160;
        border: 1px solid #0dcaf0;
    }

    .sp-ok {
        background: #d1e7dd;
        color: #0a3622;
        border: 1px solid #198754;
    }

    .sp-denied {
        background: #f8d7da;
        color: #58151c;
        border: 1px solid #dc3545;
    }

    .sp-verified {
        background: #cfe2ff;
        color: #084298;
        border: 1px solid #0d6efd;
    }

    .sp-approved {
        background: #d1e7dd;
        color: #0a3622;
        border: 1px solid #198754;
    }

    .sp-paid {
        background: #d0f0e8;
        color: #0a3d2e;
        border: 1px solid #0d6e4f;
    }

    .sp-open {
        background: #cff4fc;
        color: #055160;
        border: 1px solid #0dcaf0;
    }



    .chat-badge {
        background: #dc3545c7;
        color: #fff;
        border-radius: 20px;
        padding: 1px 7px;
        font-size: 10px;
        font-weight: 700;
    }



    .eh-btn-view {
        background: #0a6ebd;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 2px 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .eh-btn-view:hover {
        background: #085da0;
        color: #fff;
    }

    .eh-btn-exp {
        background: transparent;
        color: #2c6e91;
        border: 1px solid #2c6e91;
        border-radius: 4px;
        padding: 2px 9px;
        font-size: 11px;
        font-weight: 600;
    }

    .eh-btn-exp:hover {
        background: #2c6e91d9;
        color: #fff;
    }

    .eh-btn-exp-closed {
        color: #4b8f29d9;
        border-color: #4b8f29d9;
    }

    .eh-btn-exp-closed:hover {
        background: #4b8f29d9;
        color: #fff;
    }

    .eh-btn-submit {
        background: #4b8f29d9;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 2px 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .eh-btn-submit:hover {
        background: #3d7520;
        color: #fff;
    }

    .eh-btn-okay {
        background: #d7f3e6;
        color: #065f46;
        border: none;
        border-radius: 4px;
        padding: 2px 9px;
        font-size: 11px;
        font-weight: 600;
    }

    .eh-btn-deny {
        background: #fde2e2;
        color: #9b1c1c;
        border: none;
        border-radius: 4px;
        padding: 2px 9px;
        font-size: 11px;
        font-weight: 600;
    }



    .cnt-badge {
        background: #2c6e91d9;
        color: #fff;
        border-radius: 20px;
        padding: 1px 10px;
        font-size: 11px;
        font-weight: 700;
    }

    .cnt-badge-closed {
        background: #4b8f29d9;
        color: #fff;
        border-radius: 20px;
        padding: 1px 10px;
        font-size: 11px;
        font-weight: 700;
    }

    .cnt-badge-draft {}
</style>

<div class="p-2" id="eHomeContainer" data-year-id="{{ $yearId }}" data-company-id="{{ $companyId }}" data-employee-id="{{ $employeeId }}">

    {{-- ══ EMPLOYEE INFO ══ --}}
    @if ($empInfo)
        @php
            $ec = $empInfo->EmpCode_New;
            $statusColor = ($empInfo->EmpStatus ?? '') === 'A' ? '#198754' : '#dc3545';
            $statusLabel = ($empInfo->EmpStatus ?? '') === 'A' ? 'Active' : 'Inactive';
        @endphp
        <div class="mb-2 px-2 py-2 rounded" style="background:#f0f4ff;border:1px solid #c7d4f0;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                <div class="d-flex align-items-center gap-2">
                    <div
                        style="width:34px;height:34px;border-radius:50%;background:#dde6ff;color:#1e3a8a;
                            display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">
                        {{ strtoupper(substr($empInfo->FullName ?? 'E', 0, 1)) }}
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size:13px;color:#1e3a5f;line-height:1.3;">
                            {{ $ec }} — {{ $empInfo->FullName ?? '' }}
                            <span class="ms-1 badge"
                                style="background:{{ $statusColor }};font-size:9px;vertical-align:middle;">{{ $statusLabel }}</span>
                            @if (!empty($yearValue))
                                <span class="ms-1 badge bg-primary"
                                    style="font-size:9px;vertical-align:middle;">FY {{ $yearValue }}</span>
                            @endif
                            {{-- EHOME-FY-01: say whose books these are when they are not the company signed in to --}}
                            @if ((int) ($companyId ?? 0) !== (int) session('company_id', 1))
                                <span class="ms-1 badge bg-info-subtle text-info" style="font-size:9px;vertical-align:middle;"
                                    title="This employee's claims are kept in {{ [1 => 'VNR Seeds', 3 => 'VNR Nursery', 11 => 'VVNR'][(int) $companyId] ?? 'another company' }}">{{ [1 => 'VNR Seeds', 3 => 'VNR Nursery', 11 => 'VVNR'][(int) $companyId] ?? 'Company '.$companyId }} claims</span>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:11px;line-height:1.3;">
                            {{ $empInfo->designation_name ?? '' }}
                            @if (!empty($empInfo->department_name))
                                · {{ $empInfo->department_name }}
                            @endif
                            @if (!empty($empInfo->grade_name))
                                · {{ $empInfo->grade_name }}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end" style="font-size:11px;color:#444;line-height:1.5;">
                    @if (!empty($empInfo->MobileNo_Vnr))
                        <div><i class="ri-phone-line me-1 text-primary"></i>{{ $empInfo->MobileNo_Vnr }}</div>
                    @endif
                    @if (!empty($empInfo->EmailId_Vnr))
                        <div><i class="ri-mail-line me-1 text-primary"></i>{{ $empInfo->EmailId_Vnr }}</div>
                    @endif
                    @if (!empty($empInfo->ReportingName))
                        <div><i class="ri-user-line me-1 text-primary"></i><strong>Reports to:</strong>
                            {{ $empInfo->ReportingName }}
                            @if (!empty($empInfo->ReportingContactNo))
                                ({{ $empInfo->ReportingContactNo }})
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════ DRAFTS ══════════════════════════════════════════ --}}
    <div class="mb-4">
        <div class="eh-header" id="ehomeDraftHeader">
            <i class="ri-draft-line"></i> Drafts
            <span class="cnt-badge-draft ms-1"></span>
        </div>
        <div class="table-responsive border border-top-0 rounded-bottom">
            <table class="table table-bordered mb-0 eh-table">
                <thead>
                    <tr>
                        <th style="width:36px;">#</th>
                        <th>Exp ID</th>
                        <th>Upload Date</th>
                        <th>Bill Date</th>
                        <th>Claim Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="ehomeDraftTableBody">
                    @forelse($drafts as $i => $d)
                        @php
                            $billDate = ($d['BillDate'] ?? null) ? \Carbon\Carbon::parse($d['BillDate'] ?? '')->format('d/m/y') : '—';
                            $crDate = ($d['CrDate'] ?? null) ? \Carbon\Carbon::parse($d['CrDate'] ?? '')->format('d/m/y') : '—';
                            $amount = ($d['FilledAmt'] ?? 0) > 0 ? '₹' . number_format((float) ($d['FilledAmt'] ?? 0)) : '—';
                            $chatCount = count($d['remarks'] ?? []);

                            // Status badge (matches old PHP logic)
                            $status = $d['Backdate_Odometer_Status'] ?? '';
                            $msg = $d['Backdate_Odometer_Msg'] ?? '';
                            $statusColor = match ($status) {
                                'P' => 'orange',
                                'A' => 'green',
                                'R' => 'red',
                                default => '#ccc',
                            };
                            $showStatus = !empty($status) && strtoupper($msg) !== 'STATUS UNKNOWN';

                            // Odometer badge
                            $appOdo = $d['App_Odometer'] ?? '';
                            $odoStatus = $d['Access_Running_KM_Status'] ?? '';
                            $odoColor = match ($appOdo) {
                                'Y' => 'green',
                                'N' => 'orange',
                                'R' => 'red',
                                default => '#ccc',
                            };
                            $showOdo = !empty($appOdo) && !empty($odoStatus);
                        @endphp
                        <tr>
                            <td><span class="cnt-badge-draft">{{ $i + 1 }}</span></td>
                            <td>
                                <span style="position:relative;display:inline-block;">
                                    <a href="#" class="view-claim fw-semibold text-primary"
                                        data-expid="{{ $d['ExpId'] ?? '' }}" data-claim-id="{{ $d['ClaimId'] ?? '' }}"
                                        data-cgid="{{ $d['ClaimId'] ?? '' }}" data-empid="{{ $employeeId }}">
                                        {{ $d['ExpId'] ?? '' }}
                                    </a>
                                    @if ($chatCount > 0)
                                        <span class="chat-badge"
                                            style="position:absolute;top:-6px;right:-14px;font-size:6px;padding:1px 4px;min-width:14px;text-align:center;line-height:1.4;border-radius:10px;">
                                            {{ $chatCount }}
                                        </span>
                                    @endif
                                </span>
                            </td>
                            <td>{{ $crDate }}</td>
                            <td>{{ $billDate }}</td>
                            <td>{{ $d['ClaimName'] ?? '—' }}</td>
                            <td><strong>{{ $amount }}</strong></td>
                            <td>
                                @if ($showStatus)
                                    <span class="badge"
                                        style="background:{{ $statusColor }};color:#fff;padding:3px 6px;border-radius:4px;font-size:10px;">
                                        {{ $msg }}
                                    </span>
                                @endif
                                @if ($showOdo)
                                    <div class="mt-1">
                                        <span class="badge"
                                            style="background:{{ $odoColor }};color:#fff;padding:3px 6px;border-radius:4px;font-size:10px;">
                                            {{ $odoStatus }}
                                        </span>
                                    </div>
                                @endif
                                @if (!$showStatus && !$showOdo)
                                    <span class="sp sp-pending">Draft</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted fst-italic py-3">No drafts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══════════════════════════════════════════ OPEN MONTHS ══════════════════════════════════════════ --}}
    @php
        $monthNames = [
            1 => 'JAN',
            2 => 'FEB',
            3 => 'MAR',
            4 => 'APR',
            5 => 'MAY',
            6 => 'JUN',
            7 => 'JUL',
            8 => 'AUG',
            9 => 'SEP',
            10 => 'OCT',
            11 => 'NOV',
            12 => 'DEC',
        ];
    @endphp
    <div class="mb-4">
        <div class="eh-header eh-header-open">
            <i class="ri-calendar-2-line"></i> Opened Months
        </div>
        <div class="table-responsive border border-top-0 rounded-bottom">
            <table class="table table-bordered mb-0 eh-table">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th>Month</th>
                        <th>Total Claims</th>
                        <th>Filled Amt</th>
                        <th title="Punched amount confirmed by the employee; the verifier checks it after Submit">Confirmed Amt</th>
                        <th>Submit</th>
                        <th style="width:50px;">Print</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($openList as $o)
                        @php
                            $mNum = (int) ($o['Month'] ?? 0);
                            $mName = $monthNames[$mNum] ?? $mNum;
                            $totals = $o['TotalFilled_Claim_with_Amount'] ?? [];
                            $verify = $o['TotalVerify_Claim_with_Amount'] ?? [];
                            $rowId = 'open-' . $mNum;
                            $canSubmit = ($o['submit_btn'] ?? 'N') === 'Y';
                        @endphp
                        <tr class="eh-month-row" data-month="{{ $mNum }}" data-type="open"
                            data-employee="{{ $employeeId }}" data-year-id="{{ $yearId }}" data-company-id="{{ $companyId }}" style="cursor:pointer;">
                            <td>
                                <button class="eh-expand-btn btn p-0 border-0 bg-transparent d-inline-flex align-items-center justify-content-center"
                                    data-target="{{ $rowId }}" data-month="{{ $mNum }}"
                                    data-type="open" data-employee="{{ $employeeId }}" data-year-id="{{ $yearId }}" data-company-id="{{ $companyId }}"
                                    style="font-size:16px; font-weight:700; color:#2c6e91; line-height:1; transition:transform .2s;"><i class="ri-arrow-right-s-fill"></i></button>
                            </td>
                            <td><span class="eh-month">{{ $mName }}</span></td>
                            <td><span class="cnt-badge">{{ $totals['TotClaim'] ?? 0 }}</span></td>
                            <td><strong>₹{{ number_format((float) ($totals['TotFilledAmt'] ?? 0)) }}</strong></td>
                            <td><strong>₹{{ number_format((float) ($verify['TotVerifyAmt'] ?? 0)) }}</strong></td>
                            <td>
                                @if ($canSubmit)
                                    <button class="eh-btn-submit ehome-submit-btn"
                                        data-month="{{ $mNum }}">Submit</button>
                                @else
                                    {{-- SUBMIT-BTN-01: say why it cannot be submitted yet --}}
                                    <span class="text-muted small" title="{{ $o['submit_note'] ?? '' }}">{{ $o['submit_note'] ?? '—' }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('expense.print.details', ['e' => $employeeId, 'y' => $yearId ?? session('year_id'), 'm' => $mNum, 'nn' => 1, 'c' => $companyId ?? session('company_id')]) }}"
                                    target="_blank" class="eh-btn-view"
                                    style="text-decoration:none;font-size:10px;padding:2px 7px;">
                                    <i class="ri-printer-line"></i>
                                </a>
                            </td>
                        </tr>
                        <tr class="d-none" id="{{ $rowId }}">
                            <td colspan="7" class="p-0">
                                <div class="eh-expand-panel p-2">
                                    <div class="text-center text-muted py-3 eh-day-loader">
                                        <div class="spinner-border spinner-border-sm"></div> Loading...
                                    </div>
                                    <table class="table table-bordered mb-0 eh-table d-none eh-day-table">
                                        <thead class="eh-nested-head">
                                            <tr>
                                                <th style="width:28px;"></th>
                                                <th colspan="2">Bill Date</th>
                                                <th>Claims</th>
                                                <th colspan="3">Total Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="eh-day-tbody"></tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted fst-italic py-3">No open months.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══════════════════════════════════════════ CLOSED MONTHS ══════════════════════════════════════════ --}}
    <div class="mb-2">
        <div class="eh-header eh-header-closed">
            <i class="ri-checkbox-circle-line"></i> Closed Months
        </div>
        <div class="table-responsive border border-top-0 rounded-bottom">
            <table class="table table-bordered mb-0 eh-table">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th>Month</th>
                        <th>Total Claims</th>
                        <th>Claim Amt</th>
                        <th>Courier No / Agency</th>
                        <th>Rcvd Date</th>
                        <th>Verified Amt / Date</th>
                        <th>Approved Amt / Date</th>
                        <th>Paid Amt / Date</th>
                        <th>Remark</th>
                        <th>Bank Dtl.</th>
                        <th style="width:50px;">Print</th>
                </thead>
                <tbody>
                    @forelse($closeList as $c)
                        @php
                            $mNum = (int) ($c['Month'] ?? 0);
                            $mName = $monthNames[$mNum] ?? $mNum;
                            $rowId = 'closed-' . $mNum . '-' . ($c['id'] ?? '0');
                            $verDate =
                                ($c['Verified_Date'] ?? '') && ($c['Verified_Date'] ?? '') !== '0000-00-00'
                                    ? \Carbon\Carbon::parse($c['Verified_Date'] ?? '')->format('d/m/y')
                                    : '—';
                            $appDate =
                                ($c['Approved_Date'] ?? '') && ($c['Approved_Date'] ?? '') !== '0000-00-00'
                                    ? \Carbon\Carbon::parse($c['Approved_Date'] ?? '')->format('d/m/y')
                                    : '—';
                            $payDate =
                                ($c['Fin_PayDate'] ?? '') && ($c['Fin_PayDate'] ?? '') !== '0000-00-00'
                                    ? \Carbon\Carbon::parse($c['Fin_PayDate'] ?? '')->format('d/m/y')
                                    : '—';
                            $rcvDate =
                                ($c['RecevingDate'] ?? '') && ($c['RecevingDate'] ?? '') !== '0000-00-00'
                                    ? \Carbon\Carbon::parse($c['RecevingDate'] ?? '')->format('d/m/y')
                                    : '—';
                        @endphp
                        <tr class="eh-month-row" data-month="{{ $mNum }}" data-type="closed"
                            data-employee="{{ $employeeId }}" data-year-id="{{ $yearId }}" data-company-id="{{ $companyId }}" style="cursor:pointer;">
                            <td>
                                <button class="eh-expand-btn btn p-0 border-0 bg-transparent d-inline-flex align-items-center justify-content-center"
                                    data-target="{{ $rowId }}" data-month="{{ $mNum }}"
                                    data-type="closed" data-employee="{{ $employeeId }}" data-year-id="{{ $yearId }}" data-company-id="{{ $companyId }}"
                                    style="font-size:16px; font-weight:700; color:#4b8f29d9; line-height:1; transition:transform .2s;"><i class="ri-arrow-right-s-fill"></i></button>
                            </td>
                            <td><span class="eh-month-closed">{{ $mName }}</span></td>
                            <td><span class="cnt-badge-closed">{{ $c['Total_Claim'] ?? 0 }}</span></td>
                            <td><strong>₹{{ number_format((float) ($c['Claim_Amount'] ?? 0)) }}</strong></td>
                            <td>
                                <span class="fw-semibold small">{{ $c['DocateNo'] ?? '—' }}</span>
                                @if (!empty($c['Agency'] ?? ''))
                                    <div class="text-muted" style="font-size:10px;">{{ $c['Agency'] ?? '' }}</div>
                                @endif
                            </td>
                            <td>{{ $rcvDate }}</td>
                            <td>₹{{ number_format((float) ($c['Verified_Amount'] ?? 0)) }} <span
                                    class="text-muted small">/
                                    {{ $verDate }}</span></td>
                            <td>₹{{ number_format((float) ($c['Approved_Amount'] ?? 0)) }} <span
                                    class="text-muted small">/
                                    {{ $appDate }}</span></td>
                            <td>₹{{ number_format((float) ($c['Fin_PayAmt'] ?? 0) + (float) ($c['Fin_AdvancePay'] ?? 0)) }} <span class="text-muted small">/ {{ $payDate }}</span></td>
                            <td class="text-start" style="max-width:180px;font-size:11px;word-break:break-word;">
                                @php
                                    $rem = trim($c['Fin_PayRemark'] ?? '');
                                    $cPaid = (float) ($c['Fin_PayAmt'] ?? 0);
                                    $cPayDate = trim($c['Fin_PayDate'] ?? '');
                                    $cPayStatus = trim($c['payment_status'] ?? '');
                                    $isPaid = ($cPaid > 0)
                                        || (!empty($cPayDate) && \App\Support\ZeroDate::has($cPayDate))
                                        || in_array(strtolower($cPayStatus), ['confirmedpayment', 'paid', 'confirmed']);

                                    if (!$isPaid) {
                                        // If not paid yet, suppress any erroneous "Paid Rs." remark
                                        if (stripos($rem, 'Paid Rs.') !== false) {
                                            $rem = '';
                                        }
                                    } else {
                                        if (!empty($rem) && $rem !== '—' && $rem !== '-' && stripos($rem, 'DA Rs') === false && stripos($rem, 'DA ') === false) {
                                            $cAppr = (float) ($c['Approved_Amount'] ?? ($c['Verified_Amount'] ?? 0));
                                            $cTotAdj = (float) ($c['total_adjustment'] ?? ((float) ($c['total_activity_adjustment'] ?? 0) + (float) ($c['total_expense_adjustment'] ?? 0)));
                                            if ($cAppr > 0 && $cPaid > 0 && ($cAppr - $cTotAdj - $cPaid) > 0) {
                                                $infDa = round($cAppr - $cTotAdj - $cPaid, 2);
                                                $daStr = (floor($infDa) == $infDa) ? number_format($infDa, 0, '', '') : number_format($infDa, 2);
                                                $rem .= ", DA Rs. {$daStr}";
                                            }
                                        }
                                    }
                                @endphp
                                {{ !empty($rem) ? $rem : '—' }}
                            </td>
                             <td>
                                <span class="fw-semibold small">{{ $c['utr_number'] ?? '—' }}</span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('expense.print.details', ['e' => $employeeId, 'y' => $yearId ?? session('year_id'), 'm' => $mNum, 'nn' => 2, 'c' => $companyId ?? session('company_id')]) }}"
                                    target="_blank" class="eh-btn-view"
                                    style="text-decoration:none;font-size:10px;padding:2px 7px;">
                                    <i class="ri-printer-line"></i>
                                </a>
                            </td>
                        </tr>
                        <tr class="d-none" id="{{ $rowId }}">
                            <td colspan="11" class="p-0">
                                <div class="eh-expand-panel-closed p-2">
                                    <div class="text-center text-muted py-3 eh-day-loader">
                                        <div class="spinner-border spinner-border-sm"></div> Loading...
                                    </div>
                                    <table class="table table-bordered mb-0 eh-table d-none eh-day-table">
                                        <thead class="eh-nested-head-closed">
                                            <tr>
                                                <th style="width:28px;"></th>
                                                <th colspan="2">Bill Date</th>
                                                <th>Claims</th>
                                                <th colspan="4">Total Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="eh-day-tbody"></tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted fst-italic py-3">No closed months.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
