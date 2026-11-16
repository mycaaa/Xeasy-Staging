{{-- CLAIM-SCORE-03: Claim Score Settings + rollout (Super Admin) --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid cs">
@include('claim-score._assets')
<div class="stack">
    <div class="topbar"><div><h2>Claim Score Settings</h2><p class="muted small mb-0">How the monthly claim score is worked out. Changes apply to every score from the next page load; each change is logged. The stage star bands are on <a href="{{ url('admin/claim-rating') }}">Claim Rating Rules</a>.</p></div></div>
    @if (session('success'))<div class="alert alert-success mb-0">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger mb-0">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger mb-0">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('claim-score.settings.save') }}" class="stack">@csrf
        <div class="g2">
            <div class="card2"><div class="card-h"><h3>Pillar weights</h3><span class="small">Must total 100 · now <b id="wTot"></b></span></div>
                @foreach (\App\Services\ClaimScore\ClaimScoreService::PILLARS as $k => $p)
                    <div class="d-flex align-items-center gap-2 mb-2"><label for="w-{{ $k }}" style="width:110px">{{ $p[0] }}</label>
                        <input type="range" class="form-range" min="0" max="70" step="5" id="w-{{ $k }}" name="weights[{{ $k }}]" value="{{ old('weights.'.$k, $S['weights'][$k] ?? $p[1]) }}" oninput="this.nextElementSibling.textContent=this.value;csTot()">
                        <b style="width:34px;text-align:right">{{ old('weights.'.$k, $S['weights'][$k] ?? $p[1]) }}</b></div>
                @endforeach
            </div>
            <div class="card2"><h3 class="mb-2">Ranking and display</h3>
                <div class="row g-2">
                    <div class="col-sm-6"><label class="small fw-semibold" for="peer">Ranking group</label><select class="form-select form-select-sm" id="peer" name="peer">
                        @foreach (['dept' => 'Department', 'deptband' => 'Department + grade band', 'band' => 'Grade band', 'company' => 'Whole company'] as $v => $l)<option value="{{ $v }}" @selected($S['peer'] === $v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-sm-6"><label class="small fw-semibold" for="min">Minimum claims to be ranked</label><input class="form-control form-control-sm" type="number" id="min" name="min_claims" min="0" max="100" value="{{ $S['min_claims'] }}"></div>
                    <div class="col-sm-6"><label class="small fw-semibold" for="vis">Names on the leaderboard</label><select class="form-select form-select-sm" id="vis" name="visibility">
                        @foreach (['top' => 'Top 10 named, others hidden', 'all' => 'Everyone named', 'none' => 'Only the viewer named'] as $v => $l)<option value="{{ $v }}" @selected($S['visibility'] === $v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-sm-6"><label class="small fw-semibold" for="dup">Penalty per confirmed duplicate bill</label><input class="form-control form-control-sm" type="number" id="dup" name="dup_penalty" min="0" max="500" step="10" value="{{ $S['dup_penalty'] }}"></div>
                    @foreach (['Platinum', 'Gold', 'Silver', 'Bronze'] as $l)
                        <div class="col-3"><label class="small fw-semibold" for="lv-{{ $l }}">{{ $l }} from</label><input class="form-control form-control-sm" type="number" id="lv-{{ $l }}" name="levels[{{ $l }}]" min="0" max="1000" value="{{ $S['levels'][$l] }}"></div>
                    @endforeach
                </div></div>
        </div>
        <div class="card2"><div class="card-h"><h3>Parameters</h3><span class="muted small">Tick to count it in its pillar</span></div>
            <div class="g2">@foreach (\App\Services\ClaimScore\ClaimScoreService::PILLARS as $k => $p)<div><h3 style="color:var({{ $p[2] }})" class="mb-1">{{ $p[0] }}</h3>
                @foreach (\App\Services\ClaimScore\ClaimScoreService::PARAMS as $pk => $q)@if ($q[0] === $k)
                    <label class="d-flex gap-2 py-1 border-bottom"><input type="checkbox" class="form-check-input mt-1" name="params[{{ $pk }}]" value="1" @checked(! empty($S['params'][$pk])) @disabled(! $q[4])>
                        <span><b class="small">{{ $q[1] }}</b>@if (! $q[4]) <span class="chip warn">no data yet</span>@endif<br><span class="small muted">{{ $q[2] }} · source {{ $q[3] }}</span></span></label>
                @endif @endforeach</div>@endforeach</div></div>
        <div><button class="btn btn-primary">Save settings</button></div>
    </form>

    <div class="g2">
        <div class="card2"><div class="card-h"><h3>Effect: {{ $month }} by level</h3><span class="muted small">{{ $ranked }} ranked · average {{ $avg }}</span></div>
            <table class="t">@foreach ($mix as $l => $c)<tr><td>{{ $l }}</td><td class="r num">{{ $c }}</td><td style="width:55%"><div class="meter"><i style="width:{{ $ranked ? $c / $ranked * 100 : 0 }}%;background:var(--accent)"></i></div></td></tr>@endforeach</table>
            <p class="small muted mt-2 mb-0">The first-month trial run is for setting these bands: if most people are Gold or above, raise the thresholds or give timeliness more weight.</p></div>
        <div class="card2"><div class="card-h"><h3>Effect: company top 10 in {{ $month }}</h3></div>
            <table class="t">@foreach ($top as $i => $r)<tr><td>{{ $i + 1 }}</td><td class="small"><b>{{ $r['p']['nm'] }}</b><br><span class="muted">{{ $r['p']['d'] }} · {{ $r['p']['g'] }}</span></td><td class="r"><b>{{ $r['score'] }}</b></td></tr>@endforeach</table></div>
    </div>
    <div class="g2">
        <div class="card2"><h3 class="mb-2">Change history</h3>
            <table class="t">@forelse ($logs as $l)<tr><td class="small text-nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('d-m-Y H:i') }}<br><span class="muted">{{ $l->name }}</span></td><td class="small" style="white-space:pre-line;word-break:break-word">{{ $l->changes }}</td></tr>@empty<tr><td class="muted">No change yet.</td></tr>@endforelse</table></div>
        <div class="card2"><h3 class="mb-2">Rollout</h3>
            <ol class="small mb-2"><li><b>Month 1, trial run:</b> scores visible to Super Admin only (keep the Claim Score menu limited), tune the bands on live data.</li>
                <li><b>Month 2, own score:</b> open My Claim Score to everyone.</li><li><b>Month 3, leaderboard:</b> open My Team and the department leaderboard.</li><li><b>Quarter end:</b> first recognition of the top three in each department.</li></ol>
            <p class="small muted mb-0">Scores are saved every night after the stage ratings (<code>claims:score</code>) into <code>claim_scores</code>, one row per employee per month.</p></div>
    </div>
</div>
</div></div>
<script>function csTot() { let t = 0; document.querySelectorAll('input[name^="weights"]').forEach(i => t += +i.value); const b = document.getElementById('wTot'); b.textContent = t; b.style.color = t === 100 ? '' : 'var(--bad)'; } csTot();</script>
@endsection
