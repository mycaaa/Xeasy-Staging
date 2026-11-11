{{-- FOCUS-RECON-01: Focus ledger vs Xeasy --}}
@extends('layouts.app')

@section('styles')
<style>
    .fr-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .fr-table th { background: #15648C; color: #fff; padding: 6px; font-size: 11px; border: 1px solid #ddd; white-space: nowrap; }
    .fr-table td { padding: 5px 6px; border: 1px solid #ddd; vertical-align: top; }
    .fr-table td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .fr-ok { color: #1b7a3d; } .fr-bad { color: #b42318; font-weight: 600; }
    .fr-badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 11px; white-space: nowrap; }
    .fr-Matched { background: #e7f6ec; color: #1b7a3d; }
    .fr-Amount { background: #fff4e5; color: #9a5b00; }
    .fr-Only { background: #fdecea; color: #b42318; }
    .fr-card { border: 1px solid #e3e6ea; border-radius: 6px; padding: 10px 12px; }
    .fr-lines { color: #6b7280; font-size: 11px; }
</style>
@endsection

@section('content')
@php
    $money = fn ($v) => ((float) $v < 0 ? '−' : '').'₹'.number_format(abs((float) $v), 2);
    $badge = fn ($s) => 'fr-badge fr-'.explode(' ', $s)[0];
@endphp
<div class="col-md-12 h-100 p-3 mt-5">
    <div class="row card mt-2">
        <div class="col-lg-12 shadow p-3">
            <h5 class="mb-1">Focus ledger vs Xeasy</h5>
            <p class="text-muted small mb-2">Upload an employee's ledger exported from Focus (the "Ledger of &lt;employee&gt;" Excel). Xeasy rebuilds the same ledger from its own records for the period of the file and shows every difference: missing or extra entries, amounts that differ, and the month the balances part. A file with several employee ledgers gives one result per employee.</p>
            @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('focus.ledger.recon.upload') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5"><label class="form-label small mb-0" for="ledger">Focus ledger (.xlsx)</label><input type="file" class="form-control form-control-sm" id="ledger" name="ledger" accept=".xlsx,.xls" required></div>
                <div class="col-md-2"><button class="btn btn-primary btn-sm" type="submit">Compare</button></div>
                @if ($result)<div class="col-md-5 text-end"><a class="btn btn-outline-success btn-sm" href="{{ route('focus.ledger.recon.export', ['r' => $token]) }}">Export to Excel</a></div>@endif
            </form>
        </div>
    </div>

    @if ($result)
        @foreach ($result['results'] as $r)
            <div class="row card mt-3"><div class="col-lg-12 shadow p-3">
            @if (isset($r['error']))
                <div class="alert alert-warning py-2 small mb-0">{{ $r['error'] }}</div>
            @else
                @php $s = $r['summary']; $diff = $r['difference']; @endphp
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0">{{ $r['employee']['name'] }} <span class="text-muted">· Code {{ $r['employee']['code'] }}</span></h6>
                        <div class="small text-muted">{{ $result['file'] }} · {{ \Carbon\Carbon::parse($r['from'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($r['to'])->format('d M Y') }}
                            · <a href="{{ url('/account-summary?e='.$r['employee']['id'].'&y='.$r['year_id']) }}" target="_blank">Account summary</a></div>
                    </div>
                    <div class="fr-card text-end">
                        <div class="small text-muted">Closing difference (Focus − Xeasy)</div>
                        <div class="h5 mb-0 {{ abs($diff) < 0.5 ? 'fr-ok' : 'fr-bad' }}">{{ abs($diff) < 0.5 ? 'No difference' : $money($diff) }}</div>
                    </div>
                </div>

                <table class="fr-table mt-2">
                    <thead><tr><th></th><th>Opening</th><th>Debit (paid to employee)</th><th>Credit (expense booked)</th><th>Closing</th></tr></thead>
                    <tbody>
                        <tr><td>Focus</td>@foreach (['opening','debit','credit','closing'] as $k)<td class="n">{{ $money($s['focus'][$k]) }}</td>@endforeach</tr>
                        <tr><td>Xeasy</td>@foreach (['opening','debit','credit','closing'] as $k)<td class="n">{{ $money($s['xeasy'][$k]) }}</td>@endforeach</tr>
                        <tr><td><strong>Difference</strong></td>@foreach (['opening','debit','credit','closing'] as $k)@php $d = round($s['focus'][$k] - $s['xeasy'][$k], 2); @endphp<td class="n {{ abs($d) < 0.5 ? 'fr-ok' : 'fr-bad' }}">{{ abs($d) < 0.5 ? '—' : $money($d) }}</td>@endforeach</tr>
                    </tbody>
                </table>

                @if ($r['bridge'])
                    <h6 class="mt-3 mb-1">Why the balances differ</h6>
                    <table class="fr-table">
                        <thead><tr><th>Item</th><th>Detail</th><th>Effect on Focus balance</th></tr></thead>
                        <tbody>
                            @foreach ($r['bridge'] as $b)<tr><td>{{ $b['what'] }}</td><td>{{ $b['detail'] }}</td><td class="n">{{ $money($b['effect']) }}</td></tr>@endforeach
                            <tr><td colspan="2"><strong>Total</strong> {!! abs($r['bridge_total'] - $diff) < 0.5 ? '<span class="fr-ok">(equals the closing difference)</span>' : '<span class="fr-bad">(does not equal the closing difference — see notes)</span>' !!}</td><td class="n"><strong>{{ $money($r['bridge_total']) }}</strong></td></tr>
                        </tbody>
                    </table>
                @endif

                @if ($r['notes'])
                    <div class="alert alert-info py-2 small mt-2 mb-0">@foreach ($r['notes'] as $n)<div>{{ $n }}</div>@endforeach</div>
                @endif

                <h6 class="mt-3 mb-1">Month-end balances <span class="small text-muted">(◀ marks the month where the difference changed)</span></h6>
                <table class="fr-table">
                    <thead><tr><th>Month end</th><th>Focus</th><th>Xeasy</th><th>Difference</th></tr></thead>
                    <tbody>@foreach ($r['months'] as $m)<tr><td>{{ \Carbon\Carbon::parse($m['month_end'])->format('d M Y') }} {!! $m['changed'] ? '<span class="fr-bad">◀</span>' : '' !!}</td><td class="n">{{ $money($m['focus']) }}</td><td class="n">{{ $money($m['xeasy']) }}</td><td class="n {{ abs($m['diff']) < 0.5 ? 'fr-ok' : 'fr-bad' }}">{{ abs($m['diff']) < 0.5 ? '—' : $money($m['diff']) }}</td></tr>@endforeach</tbody>
                </table>

                <h6 class="mt-3 mb-1">Debits: payments and advances</h6>
                <table class="fr-table">
                    <thead><tr><th>Status</th><th>Focus date</th><th>Focus voucher / narration</th><th>Focus</th><th>Xeasy date</th><th>Xeasy entry</th><th>Xeasy</th><th>Difference</th><th>Note</th></tr></thead>
                    <tbody>@foreach ($r['debits'] as $d)
                        <tr><td><span class="{{ $badge($d['status']) }}">{{ $d['status'] }}</span></td>
                            <td>{{ $d['focus_date'] ? \Carbon\Carbon::parse($d['focus_date'])->format('d M Y') : '' }}</td>
                            <td>{{ $d['focus_voucher'] }}<div class="fr-lines">{{ $d['focus_narration'] }}</div></td>
                            <td class="n">{{ $d['focus_amount'] ? $money($d['focus_amount']) : '' }}</td>
                            <td>{{ $d['xeasy_date'] ? \Carbon\Carbon::parse($d['xeasy_date'])->format('d M Y') : '' }}</td>
                            <td>{{ $d['xeasy_label'] }}<div class="fr-lines">{{ $d['xeasy_ref'] }}</div></td>
                            <td class="n">{{ $d['xeasy_amount'] ? $money($d['xeasy_amount']) : '' }}</td>
                            <td class="n {{ abs($d['diff']) < 0.5 ? '' : 'fr-bad' }}">{{ abs($d['diff']) < 0.5 ? '' : $money($d['diff']) }}</td>
                            <td class="small">{{ $d['note'] }}</td></tr>
                    @endforeach</tbody>
                </table>

                <h6 class="mt-3 mb-1">Credits: expenses booked, by month and head</h6>
                <table class="fr-table">
                    <thead><tr><th>Status</th><th>Expense month</th><th>Head (account)</th><th>Focus vouchers</th><th>Focus</th><th>Xeasy</th><th>Difference</th><th>Note</th></tr></thead>
                    <tbody>@foreach ($r['credits'] as $c)
                        <tr><td><span class="{{ $badge($c['status']) }}">{{ $c['status'] }}</span></td>
                            <td>{{ $c['month'] }}</td>
                            <td>{{ $c['head'] }}<div class="fr-lines">{{ $c['account'] }}</div>
                                @if ($c['status'] !== 'Matched')<div class="fr-lines">Focus: {{ implode('; ', $c['focus_lines']) ?: '—' }}<br>Xeasy: {{ implode('; ', $c['xeasy_lines']) ?: '—' }}</div>@endif</td>
                            <td class="small">{{ $c['vouchers'] }}<div class="fr-lines">{{ $c['xeasy_ref'] }}</div></td>
                            <td class="n">{{ $c['focus_amount'] ? $money($c['focus_amount']) : '' }}</td>
                            <td class="n">{{ $c['xeasy_amount'] ? $money($c['xeasy_amount']) : '' }}</td>
                            <td class="n {{ abs($c['diff']) < 0.5 ? '' : 'fr-bad' }}">{{ abs($c['diff']) < 0.5 ? '' : $money($c['diff']) }}</td>
                            <td class="small">{{ $c['note'] }}</td></tr>
                    @endforeach</tbody>
                </table>
            @endif
            </div></div>
        @endforeach
    @endif

    @if ($history->isNotEmpty())
        <div class="row card mt-3"><div class="col-lg-12 shadow p-3">
            <h6 class="mb-1">Recent comparisons</h6>
            <table class="fr-table">
                <thead><tr><th>When</th><th>Employee</th><th>File</th><th>Period</th><th>Focus closing</th><th>Xeasy closing</th><th>Difference</th><th>Items</th></tr></thead>
                <tbody>@foreach ($history as $h)<tr><td>{{ \Carbon\Carbon::parse($h->created_at)->format('d M Y H:i') }}</td><td>{{ $h->emp_code }}</td><td>{{ $h->file_name }}</td><td>{{ $h->period_from }} to {{ $h->period_to }}</td><td class="n">{{ $money($h->focus_closing) }}</td><td class="n">{{ $money($h->xeasy_closing) }}</td><td class="n {{ abs($h->difference) < 0.5 ? 'fr-ok' : 'fr-bad' }}">{{ abs($h->difference) < 0.5 ? '—' : $money($h->difference) }}</td><td class="n">{{ $h->items }}</td></tr>@endforeach</tbody>
            </table>
        </div></div>
    @endif
</div>
@endsection
