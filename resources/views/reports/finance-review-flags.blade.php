{{-- F-02 / F-06 / F-08: Finance review flags --}}
@extends('layouts.app')

@section('styles')
<style>
    .rf-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .rf-table th { background: #15648C; color: #fff; padding: 6px; font-size: 11px; border: 1px solid #ddd; white-space: nowrap; }
    .rf-table td { padding: 5px 6px; border: 1px solid #ddd; vertical-align: top; }
    .rf-table td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .rf-note { color: #4b5563; font-size: 12px; }
    .rf-sub { color: #4b5563; font-size: 11px; }
    .rf-tag { display: inline-block; padding: 0 6px; border-radius: 8px; font-size: 11px; background: #fff4e5; color: #8a4b00; }
</style>
@endsection

@section('content')
@php $money = fn ($v) => '₹'.number_format((float) $v, 0); @endphp
<div class="col-md-12 h-100 p-3 mt-5">
    <div class="row card mt-2">
        <div class="col-lg-12 shadow p-3">
            <h5 class="mb-1">Finance review flags</h5>
            <p class="rf-note mb-2">Lists to review, not findings against anyone. A flag means the claims are worth a look; many will have a good reason (a group trip, a tariff that is a round number). Year {{ $year }}, worked out {{ $at }} (kept for an hour; <a href="{{ route('finance.review.flags', ['tab' => $tab, 'refresh' => 1]) }}">refresh</a>). Thresholds are in the claim rules settings.</p>
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link {{ $tab === 'peer' ? 'active' : '' }}" href="{{ route('finance.review.flags', ['tab' => 'peer']) }}">Peer outliers ({{ count($peer['rows']) }})</a></li>
                <li class="nav-item"><a class="nav-link {{ $tab === 'backfill' ? 'active' : '' }}" href="{{ route('finance.review.flags', ['tab' => 'backfill']) }}">Bulk back-fill ({{ count($backfill['sessions']) }} + {{ count($backfill['keyed']) }})</a></li>
                <li class="nav-item"><a class="nav-link {{ $tab === 'round' ? 'active' : '' }}" href="{{ route('finance.review.flags', ['tab' => 'round']) }}">Round amounts ({{ count($round['rows']) }})</a></li>
            </ul>
        </div>
    </div>

    <div class="row card mt-2"><div class="col-lg-12 shadow p-3">
    @if ($tab === 'peer')
        <p class="rf-note">{{ $peer['rule'] }} <a href="{{ route('finance.review.flags.export', ['tab' => 'peer']) }}">Download CSV</a></p>
        <table class="rf-table" aria-label="Peer outliers">
            <thead><tr><th scope="col">Emp code</th><th scope="col">Employee</th><th scope="col">Department</th><th scope="col">Grade</th><th scope="col">Outlier months</th><th scope="col">Above the fence</th><th scope="col">Largest months</th></tr></thead>
            <tbody>
            @forelse ($peer['rows'] as $r)
                <tr>
                    <td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td>
                    <td>{{ $r['department'] }} @if ($r['unmapped'])<span class="rf-tag" title="Compared only with other unmapped staff">not mapped</span>@endif</td>
                    <td>{{ $r['grade'] }}</td><td class="n">{{ $r['outlier_months'] }}</td><td class="n">{{ $money($r['excess']) }}</td>
                    <td class="rf-sub">@foreach (array_slice($r['months'], 0, 3) as $m)Month {{ $m['month'] }}: {{ $m['metric'] }} {{ $money($m['amount']) }} (fence {{ $money($m['fence']) }})<br>@endforeach</td>
                </tr>
            @empty
                <tr><td colspan="7">No employee above the thresholds.</td></tr>
            @endforelse
            </tbody>
        </table>
    @elseif ($tab === 'backfill')
        <p class="rf-note">{{ $backfill['rule'] }} The upload time can move when a claim is edited later, so a session can look later than it was.</p>
        <h6 class="mt-2">Upload sessions with many old bills <a class="small" href="{{ route('finance.review.flags.export', ['tab' => 'backfill']) }}">Download CSV</a></h6>
        <table class="rf-table" aria-label="Bulk back-fill sessions">
            <thead><tr><th scope="col">Hour</th><th scope="col">Emp code</th><th scope="col">Employee</th><th scope="col">Department</th><th scope="col">Claims</th><th scope="col">Old bills</th><th scope="col">Amount</th><th scope="col">Night (23:00–05:00)</th></tr></thead>
            <tbody>
            @forelse ($backfill['sessions'] as $r)
                <tr><td>{{ $r['hour'] }}</td><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['department'] }}</td><td class="n">{{ $r['claims'] }}</td><td class="n">{{ $r['old'] }}</td><td class="n">{{ $money($r['amount']) }}</td><td>{{ $r['night'] ? 'Yes' : '' }}</td></tr>
            @empty
                <tr><td colspan="8">No session above the thresholds.</td></tr>
            @endforelse
            </tbody>
        </table>
        <h6 class="mt-3">Claims keyed for other employees by payment or admin accounts <a class="small" href="{{ route('finance.review.flags.export', ['tab' => 'keyed']) }}">Download CSV</a></h6>
        <table class="rf-table" aria-label="Claims keyed for others by payment or admin accounts">
            <thead><tr><th scope="col">Emp code</th><th scope="col">Keyed by</th><th scope="col">Roles</th><th scope="col">Claims for others</th><th scope="col">Claimants</th><th scope="col">Amount</th><th scope="col">Last entry</th></tr></thead>
            <tbody>
            @forelse ($backfill['keyed'] as $r)
                <tr><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['roles'] }}</td><td class="n">{{ $r['claims'] }}</td><td class="n">{{ $r['claimants'] }}</td><td class="n">{{ $money($r['amount']) }}</td><td>{{ $r['last'] }}</td></tr>
            @empty
                <tr><td colspan="7">None this year.</td></tr>
            @endforelse
            </tbody>
        </table>
    @else
        <p class="rf-note">{{ $round['rule'] }} <a href="{{ route('finance.review.flags.export', ['tab' => 'round']) }}">Download CSV</a></p>
        <table class="rf-table" aria-label="Round amounts">
            <thead><tr><th scope="col">Emp code</th><th scope="col">Employee</th><th scope="col">Department</th><th scope="col">Lines</th><th scope="col">Round lines</th><th scope="col">Expected</th><th scope="col">z</th><th scope="col">Round amount</th></tr></thead>
            <tbody>
            @forelse ($round['rows'] as $r)
                <tr><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['department'] }}</td><td class="n">{{ $r['lines'] }}</td><td class="n">{{ $r['round'] }}</td><td class="n">{{ $r['expected'] }}</td><td class="n">{{ $r['z'] }}</td><td class="n">{{ $money($r['amount']) }}</td></tr>
            @empty
                <tr><td colspan="8">No employee above the thresholds.</td></tr>
            @endforelse
            </tbody>
        </table>
    @endif
    </div></div>
</div>
@endsection
