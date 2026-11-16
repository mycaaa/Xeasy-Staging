{{-- CLAIM-SCORE-01: My Team - scores of the people below the manager, with department and company ranks --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid cs">
@include('claim-score._assets')
@php($P = $payload)
<div class="stack">
    <div class="topbar">
        <div><h2>My Team · Claim Score</h2><p class="muted small mb-0">The people who report to you, directly or further down. You see their marks and the main habit to fix, not their claim amounts.</p></div>
        <form method="get">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <label class="small muted" for="csMonth">Month</label>
            <select id="csMonth" name="month" onchange="this.form.submit()">@foreach ($P['months'] as $o)<option value="{{ $o['m'] }}" @selected($o['m'] === $P['month'])>{{ $o['label'] }}</option>@endforeach</select>
            @if ($isAdmin)<label class="small muted" for="csMgr">Manager ID</label><input id="csMgr" name="mgr" type="number" value="{{ $mgr }}" style="width:110px"><button class="btn2">Show</button>@endif
        </form>
    </div>
    @if (! count($P['rows']))
        <div class="card2"><h3>No team scores for {{ $P['monthName'] }}</h3><p class="muted mb-0">Nobody {{ $scope === 'all' ? 'below you' : 'reporting directly to you' }} has claims in this month. @if ($scope === 'direct')<a href="?scope=all&month={{ $P['month'] }}{{ $isAdmin ? '&mgr='.$mgr : '' }}">Show all levels below</a>@endif</p></div>
    @else
    <div class="hero"><div class="hero-grid">
        <div class="ring" id="tmRing"></div>
        <div style="min-width:0">
            <p class="sub">{{ $P['mgrDept'] }} · {{ $P['monthName'] }} · {{ $scope === 'all' ? 'everyone below in the reporting chain' : 'direct reports' }}</p>
            <h2>Team of {{ $P['mgrName'] }}</h2>
            <div class="lvlrow" id="tmLevel"></div>
            @php($q = fn ($s) => '?scope='.$s.'&month='.$P['month'].($isAdmin ? '&mgr='.$mgr : ''))
            <div class="scope" role="group" aria-label="Show team"><span class="lab">Show</span>
                <a href="{{ $q('direct') }}" class="{{ $scope === 'direct' ? 'on' : '' }}" aria-current="{{ $scope === 'direct' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="6" r="3"/><circle cx="6" cy="17" r="2.6"/><circle cx="18" cy="17" r="2.6"/></svg>Direct reports</a>
                <a href="{{ $q('all') }}" class="{{ $scope === 'all' ? 'on' : '' }}" aria-current="{{ $scope === 'all' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="4.5" r="2.4"/><circle cx="6" cy="12" r="2.2"/><circle cx="18" cy="12" r="2.2"/><circle cx="3.5" cy="19.5" r="1.8"/><circle cx="9" cy="19.5" r="1.8"/><circle cx="15" cy="19.5" r="1.8"/><circle cx="20.5" cy="19.5" r="1.8"/></svg>All levels below</a>
            </div>
        </div>
        <div class="hero-side" id="tmStats"></div>
    </div></div>
    <div class="g2">
        <div class="card2"><div class="card-h"><h3>Team by level</h3><span class="muted small">Ranked members</span></div><div class="cw"><canvas id="cMix"></canvas></div></div>
        <div class="card2"><div class="card-h"><h3>Team vs department vs company</h3><span class="muted small">Average pillar marks</span></div><div class="cw"><canvas id="cCmp"></canvas></div></div>
    </div>
    <div class="card2"><div class="card-h"><h3>Coaching alerts</h3><span class="muted small">Below Silver, or down 60+ points on last month</span></div><div id="tmAlerts"></div></div>
    <div class="card2"><div class="card-h"><h3>Team heatmap</h3><span class="muted small">Department rank and company rank among all ranked employees this month</span></div><div class="tscroll"><table class="t" id="tmHeat"></table></div></div>
    <div class="card2"><div class="card-h"><h3>Team race · {{ $P['monthName'] }}</h3><span class="muted small">Average score of each manager's direct team (3+ ranked members)</span></div><div class="cw tall"><canvas id="cRace"></canvas></div></div>
    @endif
</div>
</div></div>
@if (count($P['rows']))
<script>
(function () {
    const P = @json($P), C = CS, L = P.levels, lvOf = s => C.levelOf(s, L);
    document.getElementById('tmRing').innerHTML = C.ring(P.avg, 'team average', L);
    document.getElementById('tmLevel').innerHTML = `${C.medal(P.avgLevel.n, P.avgLevel.c, 30)}<b>${C.esc(P.avgLevel.n)}</b>${P.pavg != null ? `<span class="mv ${P.avg >= P.pavg ? 'up' : 'dn'}">${P.avg >= P.pavg ? '▲' : '▼'} ${Math.abs(P.avg - P.pavg)} pts</span>` : ''}`;
    document.getElementById('tmStats').innerHTML = `<div class="stat"><span>People with claims</span><b class="num">${P.rows.length}</b><span>${P.ranked} ranked</span></div>
      <div class="stat"><span>Team rank (direct teams)</span><b class="num">${P.teamRank ? '#' + P.teamRank : '–'} <small style="font-size:.8rem;opacity:.7">of ${P.teams}</small></b></div>
      <div class="stat"><span>Gold or Platinum</span><b class="num">${P.gold}</b></div>
      <div class="stat"><span>Need attention</span><b class="num">${P.attn}</b><span>below Silver</span></div>`;
    const al = P.rows.filter(t => t.score < (L.find(l => l.n === 'Silver') || {}).min || t.drop >= 60).sort((a, b) => a.score - b.score).slice(0, 8);
    document.getElementById('tmAlerts').innerHTML = al.length ? al.map(t => { const m = t.issue, title = m ? m.title : 'Keep going';
        const nudge = `Hi ${t.nm.split(' ')[0]}, your claim score for ${P.monthName} is ${t.score}. The biggest gain for you: ${title.toLowerCase()}${m ? ' (worth up to +' + m.gain + ' points)' : ''}.`;
        return `<div class="alert2 ${t.score < 500 ? '' : 'warn'}">${C.avatar(t.nm, t.hue, t.level)}<div><b>${C.esc(t.nm)}</b> <span class="muted small">${t.score} · ${C.esc(t.level.n)}${t.drop >= 60 ? ' · down ' + t.drop + ' on last month' : ''}</span><p class="mb-0 small">${C.esc(title)}. <span class="muted">${C.esc(m ? m.desc : '')}</span></p></div><button class="btn2" data-nudge="${C.esc(nudge)}">Copy nudge</button></div>`; }).join('') : '<p class="muted mb-0">Nobody needs a nudge this month.</p>';
    document.getElementById('tmAlerts').addEventListener('click', e => { const b = e.target.closest('[data-nudge]'); if (!b) return; navigator.clipboard?.writeText(b.dataset.nudge).then(() => { b.textContent = 'Copied'; setTimeout(() => b.textContent = 'Copy nudge', 1500); }, () => prompt('Copy the message', b.dataset.nudge)); });
    const heat = v => { if (v == null) return '<td class="c"><span class="muted">–</span></td>'; const h = v >= 85 ? '--good' : v >= 65 ? '--gold' : v >= 45 ? '--warn' : '--bad'; return `<td class="c"><div class="heat num" style="background:color-mix(in srgb, var(${h}) ${18 + Math.round((100 - Math.abs(v - 50)) / 4)}%, transparent)">${v}</div></td>`; };
    const spark = vs => { const pts = vs.map((v, i) => v == null ? null : [i * 14 + 2, 26 - (Math.max(300, v) - 300) / 700 * 24]).filter(Boolean); return pts.length > 1 ? `<svg viewBox="0 0 ${vs.length * 14} 28" width="${vs.length * 14}" height="28"><polyline points="${pts.map(p => p.join(',')).join(' ')}" fill="none" stroke="var(--accent)" stroke-width="2"/><circle cx="${pts[pts.length - 1][0]}" cy="${pts[pts.length - 1][1]}" r="3" fill="var(--accent)"/></svg>` : ''; };
    document.getElementById('tmHeat').innerHTML = `<thead><tr><th>Employee</th><th>Department rank</th><th>Company rank</th>${P.pillars.map(p => `<th class="c">${p.name}</th>`).join('')}<th class="r">Score</th><th>Trend</th><th>vs last month</th></tr></thead><tbody>` +
        P.rows.map(t => { const d = t.prev != null ? t.score - t.prev : null; return `<tr><td><div style="display:flex;gap:8px;align-items:center">${C.avatar(t.nm, t.hue, t.level)}<div><b class="small">${C.esc(t.nm)}</b><div class="muted small">${C.esc(t.g)} · ${t.claims} claims${t.via ? ' · via ' + C.esc(t.via) : ''}</div></div></div></td>
        <td class="num small">${t.ranked ? `<b>${t.drank}</b> / ${t.dof} <span class="muted">${C.esc(t.d)}</span>` : '<span class="muted">not ranked</span>'}</td><td class="num small">${t.ranked ? `<b>${t.crank}</b> / ${P.cof}` : '–'}</td>
        ${P.pillars.map(p => heat(t.pil[p.k])).join('')}<td class="r"><b class="num">${t.score}</b><div>${C.lvlTag(t.level)}</div></td><td>${spark(t.spark)}</td><td>${d == null ? '<span class="chip eq">new</span>' : `<span class="chip ${d >= 0 ? 'good' : 'bad'}">${d >= 0 ? '+' : ''}${d}</span>`}</td></tr>`; }).join('') + '</tbody>';
    C.chart('cMix', { type: 'doughnut', data: { labels: L.map(l => l.n), datasets: [{ data: P.mix, backgroundColor: L.map(l => C.css(l.c)), borderWidth: 3 }] }, options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right' } } } });
    C.chart('cCmp', { type: 'bar', data: { labels: P.pillars.map(p => p.name), datasets: [
        { label: 'This team', data: P.pillars.map(p => P.cmp.team[p.k]), backgroundColor: C.css('--accent'), borderRadius: 6 },
        { label: P.mgrDept || 'Department', data: P.pillars.map(p => P.cmp.dept[p.k]), backgroundColor: C.css('--teal'), borderRadius: 6 },
        { label: 'Company', data: P.pillars.map(p => P.cmp.company[p.k]), backgroundColor: C.css('--silver'), borderRadius: 6 }] },
        options: { maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } }, plugins: { legend: { position: 'bottom' } } } });
    C.chart('cRace', { type: 'bar', data: { labels: P.race.map(t => t.nm), datasets: [{ data: P.race.map(t => t.avg), backgroundColor: P.race.map(t => t.me ? C.css('--accent') : C.css('--teal') + '99'), borderRadius: 6 }] },
        options: { indexAxis: 'y', maintainAspectRatio: false, scales: { x: { min: 500, max: 1000 } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` ${c.raw} average · ${P.race[c.dataIndex].n} ranked` } } } } });
})();
</script>
@endif
@endsection
