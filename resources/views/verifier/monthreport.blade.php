{{-- 16 Nov #6: Verifier > Month Report - choose employee, month and year; the month's bills from the database --}}
@extends('layouts.app')

@section('content')
@php
    $fy = explode('-', (string) $yearLabel);
    $calYear = fn ($m) => $m >= 4 ? ($fy[0] ?? '') : ($fy[1] ?? '');
    $monthName = fn ($m) => $m ? date('F', mktime(0, 0, 0, $m, 1)).' '.$calYear($m) : '';
    $inr = fn ($n) => number_format((float) $n, 2);
    $zero = fn ($d) => ! $d || ! \App\Support\ZeroDate::has($d) ? '' : \Carbon\Carbon::parse($d)->format('d-m-Y');
@endphp
<style>
    @media print {
        .no-print, .app-menu, #page-topbar, footer, .footer { display: none !important; }
        .page-content { padding: 0 !important; margin: 0 !important; }
        .card { box-shadow: none !important; border: 0 !important; }
    }
    .mr-table th { font-size: .74rem; background: #f8fafc; }
    .mr-table td { font-size: .8rem; }
</style>
<div class="page-content">
    <div class="container-fluid">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <h5 class="mb-0 me-auto"><i class="ri-calendar-check-line me-1"></i> Month Report</h5>
                @if ($report)
                    <button type="button" class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()"><i class="ri-printer-line me-1"></i>Print</button>
                @endif
            </div>
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end no-print mb-3">
                    <div class="col-md-5">
                        <label class="form-label small mb-1" for="mrEmp">Employee</label>
                        <input class="form-control form-control-sm" list="mrEmpList" id="mrEmp" name="e" required autocomplete="off"
                               value="{{ $report ? ($report['emp']->EmpCode.' - '.$report['emp']->name) : $empInput }}" placeholder="Type employee code or name">
                        <datalist id="mrEmpList">
                            @foreach ($employees as $x)<option value="{{ $x->EmpCode }} - {{ $x->name }}"></option>@endforeach
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1" for="mrYear">Year</label>
                        <select class="form-select form-select-sm" id="mrYear" name="y">
                            @foreach ($years as $yr)<option value="{{ $yr->YearId }}" @selected($yr->YearId == $yearId)>{{ $yr->Year }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1" for="mrMonth">Month</label>
                        <select class="form-select form-select-sm" id="mrMonth" name="m" required>
                            <option value="">Choose month…</option>
                            @foreach ([3, 2, 1, 12, 11, 10, 9, 8, 7, 6, 5, 4] as $mm)
                                <option value="{{ $mm }}" @selected($mm == $month)>{{ $monthName($mm) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Show report</button></div>
                </form>

                @if ($error)
                    <div class="alert alert-warning py-2 small">{{ $error }}</div>
                @elseif (! $report)
                    <p class="text-muted small mb-0">Choose an employee, year and month to see that month's bills with the claimed, verified, approved and finance amounts.</p>
                @else
                    @php $mr = $report['month']; $rows = collect($report['rows']); @endphp
                    <div class="d-flex flex-wrap justify-content-between align-items-end mb-2 gap-2">
                        <div>
                            <div class="fw-semibold">{{ $report['emp']->name ?? '' }} <span class="text-muted">({{ $report['emp']->EmpCode ?? $employeeId }})</span></div>
                            <div class="small text-muted">Expense report - {{ $monthName($month) }}</div>
                        </div>
                        <div class="small text-end">
                            @if ($mr)
                                Month status: <strong>{{ $mr->Status }}</strong>
                                @if ($zero($mr->DateOfSubmit)) · submitted {{ $zero($mr->DateOfSubmit) }} @endif
                                @if ($zero($mr->Verified_Date ?? null)) · verified {{ $zero($mr->Verified_Date) }} @endif
                                @if ($zero($mr->Approved_Date ?? null)) · approved {{ $zero($mr->Approved_Date) }} @endif
                                @if ($zero($mr->Fin_PayDate ?? null)) · paid {{ $zero($mr->Fin_PayDate) }} ({{ $inr($mr->Fin_PayAmt ?? 0) }}) @endif
                            @else
                                <span class="text-muted">No month record</span>
                            @endif
                        </div>
                    </div>
                    @if ($rows->isEmpty())
                        <div class="alert alert-light border py-2 small">No bills in {{ $monthName($month) }} for this employee.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mr-table mb-0">
                                <thead><tr><th>#</th><th>Exp ID</th><th>Bill date</th><th>Claim type</th><th class="text-end">Claimed</th><th class="text-end">Verified</th><th class="text-end">Approved</th><th class="text-end">Finance</th><th>Status</th><th>Remarks</th></tr></thead>
                                <tbody>
                                @foreach ($rows as $i => $r)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $r->ExpId }}</td>
                                        <td class="text-nowrap">{{ $zero($r->BillDate) }}</td>
                                        <td>{{ $r->ClaimName }}</td>
                                        <td class="text-end">{{ $inr($r->FilledTAmt) }}</td>
                                        <td class="text-end">{{ $inr($r->VerifyTAmt) }}</td>
                                        <td class="text-end">{{ $inr($r->ApprTAmt) }}</td>
                                        <td class="text-end">{{ (float) $r->FinancedTAmt > 0 ? $inr($r->FinancedTAmt) : '-' }}</td>
                                        <td>{{ (int) $r->FilledOkay === 2 ? 'Denied' : $r->ClaimStatus }}</td>
                                        <td class="small text-muted" style="max-width:260px">{{ collect([$r->DateEntryRemark, $r->VerifyTRemark, $r->ApprTRemark])->filter()->implode(' · ') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td colspan="4" class="text-end">Total ({{ $rows->count() }} bills)</td>
                                        <td class="text-end">{{ $inr($rows->sum('FilledTAmt')) }}</td>
                                        <td class="text-end">{{ $inr($rows->sum('VerifyTAmt')) }}</td>
                                        <td class="text-end">{{ $inr($rows->sum('ApprTAmt')) }}</td>
                                        <td class="text-end">{{ $inr($rows->sum('FinancedTAmt')) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                    @if ($mr)
                                        <tr class="small text-muted">
                                            <td colspan="4" class="text-end">Month record</td>
                                            <td class="text-end">{{ $inr($mr->Claim_Amount ?? 0) }}</td>
                                            <td class="text-end">{{ $inr($mr->Verified_Amount ?? 0) }}</td>
                                            <td class="text-end">{{ $inr($mr->Approved_Amount ?? 0) }}</td>
                                            <td class="text-end">{{ $inr($mr->Finance_Amount ?? 0) }}</td>
                                            <td colspan="2">month totals count only bills the employee confirmed</td>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>
                        </div>
                    @endif
                    <div class="small text-muted mt-2">{{ \App\Support\ReportStamp::text() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
