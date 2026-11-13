{{-- resources/views/expense-payments/utr-exemptions.blade.php  (UTR-02) --}}
@extends('layouts.app')

@push('styles')
    <style>
        .estable { width: 100%; border-collapse: collapse; font-size: 12px; }
        .estable th { background-color: #dde6ff; color: #1e3a8a; padding: 8px 4px; text-align: center; border: 1px solid #ddd; white-space: nowrap; }
        .estable td { padding: 6px 4px; border: 1px solid #ddd; vertical-align: middle; }
        .estable tbody tr:hover { background-color: #f5f5f5; }
        .grp-title { font-size: 13px; font-weight: 600; margin: 18px 0 6px; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .ff-form input { font-size: 11px; padding: 2px 4px; height: 26px; }
        .badge-done { background: #d7f3e6; color: #065f46; }
        .badge-open { background: #fde2e2; color: #9b1c1c; }
    </style>
@endpush

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row py-3 bg-white border-bottom">
            <div class="btn-group" role="group">
                <a href="{{ route('expense-payments.index', ['chkval' => 4]) }}" class="btn btn-sm btn-outline-info">Confirmed Payments</a>
                <a href="{{ route('expense-payments.index', ['chkval' => 5]) }}" class="btn btn-sm btn-outline-danger">Unsettled Payments</a>
                <a href="{{ route('expense-payments.utr-exemptions') }}" class="btn btn-sm btn-dark">No-UTR Reasons</a>
            </div>
        </div>

        <div class="row card mt-2">
            <div class="col-lg-12 shadow p-3">
                <h5 class="mb-1">Months marked paid with ₹0 or no UTR</h5>
                <p class="text-muted mb-2" style="font-size:12px">
                    Give each month a reason. <b>DA claims only</b> and <b>Adjusted with advance</b> need no UTR.
                    <b>Payable with F&amp;F</b> stays open until the F&amp;F settlement is linked: its UTR becomes the month's UTR and the month is confirmed.
                </p>

                @if (! $ready)
                    <div class="alert alert-warning">The reason columns are not on this year's table yet. Run migration 2026_11_05_000015.</div>
                @else
                    @php
                        $titles = ['' => 'No reason given yet'] + $reasons;
                    @endphp
                    @foreach ($titles as $key => $title)
                        @php $rows = $groups[$key] ?? []; @endphp
                        <div class="grp-title">{{ $title }} <span class="badge bg-secondary">{{ count($rows) }}</span></div>
                        @if (! count($rows))
                            <div class="text-muted" style="font-size:12px">None.</div>
                            @continue
                        @endif
                        <div class="table-responsive">
                            <table class="estable">
                                <thead>
                                    <tr>
                                        <th>Emp code</th><th>Name</th><th>Status</th><th>Month</th>
                                        <th>Finance ₹</th><th>Paid ₹</th><th>Pay date</th><th>UTR</th>
                                        <th>Reason</th>
                                        @if ($key === 'ff_hold')<th>F&amp;F settlement (date · amount · UTR · voucher)</th>@endif
                                        <th>State</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $r)
                                        <tr data-emp="{{ $r->EmployeeID }}" data-month="{{ $r->Month }}">
                                            <td>{{ $r->emp->code ?? $r->EmployeeID }}</td>
                                            <td>{{ $r->emp->name ?? '' }}</td>
                                            <td>{{ $r->emp->status ?? '' }}</td>
                                            <td>{{ \Illuminate\Support\Carbon::createFromFormat('!m', (int) $r->Month)->format('M') }}</td>
                                            <td class="num">{{ number_format((float) $r->Finance_Amount, 0) }}</td>
                                            <td class="num">{{ number_format((float) $r->Fin_PayAmt, 0) }}</td>
                                            <td>{{ $r->Fin_PayDate && $r->Fin_PayDate > '2000-01-01' ? \Illuminate\Support\Carbon::parse($r->Fin_PayDate)->format('d-m-Y') : '' }}</td>
                                            <td style="font-family:monospace">{{ $r->utr_number ?: $r->bank_reference }}</td>
                                            <td>
                                                <select class="form-select form-select-sm js-reason" @if ($r->ff_utr) disabled @endif>
                                                    <option value="">— choose —</option>
                                                    @foreach ($reasons as $rk => $rl)
                                                        <option value="{{ $rk }}" @selected($r->utr_exempt_reason === $rk)>{{ $rl }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            @if ($key === 'ff_hold')
                                                <td>
                                                    @if ($r->ff_utr)
                                                        {{ \Illuminate\Support\Carbon::parse($r->ff_settlement_date)->format('d-m-Y') }} ·
                                                        ₹{{ number_format((float) $r->ff_settlement_amount, 0) }} ·
                                                        <span style="font-family:monospace">{{ $r->ff_utr }}</span>
                                                        {{ $r->ff_voucher_no ? '· '.$r->ff_voucher_no : '' }}
                                                    @else
                                                        <div class="d-flex gap-1 ff-form">
                                                            <input type="date" class="form-control js-ff-date" max="{{ now()->toDateString() }}">
                                                            <input type="number" step="0.01" min="0" class="form-control js-ff-amount" placeholder="Amount" aria-label="Amount">
                                                            <input type="text" class="form-control js-ff-utr" placeholder="UTR" aria-label="UTR">
                                                            <input type="text" class="form-control js-ff-voucher" placeholder="Voucher" aria-label="Voucher">
                                                            <button type="button" class="btn btn-sm btn-primary js-ff-save">Link</button>
                                                        </div>
                                                    @endif
                                                </td>
                                            @endif
                                            <td>
                                                <span class="badge {{ $r->settled ? 'badge-done' : 'badge-open' }}">{{ $r->settled ? 'No UTR due' : 'UTR owed' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const token = '{{ csrf_token() }}';
            function post(url, data) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: JSON.stringify(data)
                }).then(r => r.json().then(j => ({ ok: r.ok, j })));
            }
            document.querySelectorAll('.js-reason').forEach(sel => sel.addEventListener('change', () => {
                const tr = sel.closest('tr');
                let reference = null;
                if (sel.value === 'same_bank_ref') {
                    reference = (window.prompt('Bank reference number of the same-bank transfer:') || '').trim();
                    if (!reference) { location.reload(); return; }
                }
                post('{{ route('expense-payments.utr-exemptions.reason') }}', { employee_id: tr.dataset.emp, month: tr.dataset.month, reason: sel.value || null, reference: reference })
                    .then(({ ok, j }) => { if (!ok) { alert(j.message || 'Not saved'); } location.reload(); });
            }));
            document.querySelectorAll('.js-ff-save').forEach(btn => btn.addEventListener('click', () => {
                const tr = btn.closest('tr');
                const v = c => tr.querySelector(c).value.trim();
                if (!v('.js-ff-date') || !v('.js-ff-amount') || !v('.js-ff-utr')) { alert('Enter the F&F date, amount and UTR.'); return; }
                btn.disabled = true;
                post('{{ route('expense-payments.utr-exemptions.ff') }}', {
                    employee_id: tr.dataset.emp, month: tr.dataset.month,
                    ff_settlement_date: v('.js-ff-date'), ff_settlement_amount: v('.js-ff-amount'),
                    ff_utr: v('.js-ff-utr'), ff_voucher_no: v('.js-ff-voucher')
                }).then(({ ok, j }) => { if (!ok) { alert(j.message || 'Not saved'); btn.disabled = false; return; } location.reload(); });
            }));
        })();
    </script>
@endpush
