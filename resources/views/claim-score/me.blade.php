{{-- CLAIM-SCORE-01: My Claim Score - own score, missions, badges, department leaderboard, how the score works --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid cs">
@include('claim-score._assets')
@php($P = $payload)
<div class="stack">
    <div class="topbar">
        <div><h2>My Claim Score</h2><p class="muted small mb-0">On-time, correct and complete claim submission, scored every month. Delays by verifiers, approvers or Finance never lower your score.</p></div>
        <form method="get">
            <label class="small muted" for="csMonth">Month</label>
            <select id="csMonth" name="month" onchange="this.form.submit()">@foreach ($P['months'] as $o)<option value="{{ $o['m'] }}" @selected($o['m'] === $P['month'])>{{ $o['label'] }}</option>@endforeach</select>
            @if ($isAdmin)
                <label class="small muted" for="csEmp">View as</label>
                <input id="csEmp" list="csPeople" placeholder="Name or code" style="width:210px" value="{{ $P['person']['nm'] ?? '' }}">
                <input type="hidden" name="emp" id="csEmpId" value="{{ $viewAs }}">
                <datalist id="csPeople">@foreach ($people as $p)<option value="{{ $p['nm'] }} · {{ $p['c'] }}" data-id="{{ $p['id'] }}"></option>@endforeach</datalist>
            @endif
        </form>
    </div>

    @if (! $P['row'])
        <div class="card2"><h3>No score for {{ $P['monthName'] }}</h3><p class="muted mb-0">{{ $P['person'] ? 'There are no claims for this month yet.' : 'Your login is not linked to an employee with claims in this company.' }} Pick another month above.</p></div>
    @else
    <div class="hero"><div class="hero-grid">
        <div class="ring" id="meRing"></div>
        <div style="min-width:0">
            <p class="sub">{{ $P['person']['d'] }} · {{ $P['person']['g'] }} · code {{ $P['person']['c'] }}@if ($P['person']['mgrName']) · reports to {{ $P['person']['mgrName'] }}@endif</p>
            <h2>{{ $P['person']['nm'] }}</h2>
            <div class="lvlrow" id="meLevel"></div>
            <p class="sub" id="meNext"></p>
            <div class="ladder" id="meLadder"></div>
        </div>
        <div class="hero-side" id="meStats"></div>
    </div></div>
    @if ($P['open'])<div class="note2">{{ $P['monthName'] }} is still open: the score changes until the submission deadline passes.</div>@endif

    <div class="card2"><div class="card-h"><h3>Missions for this month</h3><span class="muted small">The changes worth the most points for you</span></div><div class="missions" id="meMissions"></div></div>
    <div class="g2">
        <div class="card2"><div class="card-h"><h3>My shape</h3><span class="muted small">Each bar is your mark out of 100</span></div><div class="cs-shape" id="meShape"></div>
            <div class="small muted mt-2"><span style="display:inline-block;width:3px;height:12px;background:var(--ink);vertical-align:-2px"></span> Department average &nbsp; <span style="display:inline-block;width:3px;height:12px;background:var(--gold);vertical-align:-2px"></span> Best in department</div></div>
        <div class="card2"><div class="card-h"><h3>My score over the year</h3><span class="muted small">Coloured bands are the levels</span></div><div class="cw tall"><canvas id="cTrend"></canvas></div></div>
    </div>
    <div class="card2"><div class="card-h"><h3>Badges</h3><span class="muted small" id="meBadgeCount"></span></div><div class="badges" id="meBadges"></div></div>
    <div class="card2"><div class="card-h"><h3>Leaderboard · {{ $P['row']['group'] }}</h3><span class="muted small">{{ count($P['row']['board']) }} ranked · {{ ['top' => 'top 10 named', 'all' => 'everyone named', 'none' => 'only you named'][$P['row']['visibility']] ?? '' }}</span></div>
        <div class="podium" id="lbPodium"></div><div id="lbRows"></div></div>
    @endif

    <details class="card2"><summary style="cursor:pointer"><b>How the score works</b></summary>
        <div class="g2 mt-3">
            @foreach (\App\Services\ClaimScore\ClaimScoreService::PILLARS as $k => $p)
                <div><h3 style="color:var({{ $p[2] }})">{{ $p[0] }} · weight {{ collect($P['pillars'])->firstWhere('k', $k)['w'] }}</h3>
                    <ul class="small muted mt-1">@foreach (\App\Services\ClaimScore\ClaimScoreService::PARAMS as $q)@if ($q[0] === $k)<li><b style="color:var(--ink)">{{ $q[1] }}</b>: {{ $q[2] }}</li>@endif @endforeach</ul></div>
            @endforeach
        </div>
        <p class="small muted mb-0">Each parameter gives a mark out of 100; marks in a pillar are averaged and weighted into a score out of 1,000. You need {{ $P['minClaims'] }} claims in a month to be ranked. Levels: @foreach ($P['levels'] as $l){{ $l['n'] }} {{ $l['min'] }}+@if (! $loop->last), @endif @endforeach.</p>
    </details>
</div>
</div></div>
@if ($P['row'])
<script>
(function () {
    const P = @json($P), R = P.row, L = P.levels, C = CS;
    const lv = R.level;
    document.getElementById('meRing').innerHTML = C.ring(R.score, 'of 1,000', L);
    const d = R.prevScore != null ? R.score - R.prevScore : null;
    document.getElementById('meLevel').innerHTML = `${C.medal(lv.n, lv.c, 34)}<b>${C.esc(lv.n)}</b>${d != null ? `<span class="mv ${d >= 0 ? 'up' : 'dn'}">${d >= 0 ? '▲' : '▼'} ${Math.abs(d)} pts on ${C.esc(R.prevMonth)}</span>` : ''}`;
    const nxt = L.slice().reverse().find(x => x.min > R.score), top = R.missions[0];
    document.getElementById('meNext').textContent = nxt ? `${nxt.min - R.score} points to ${nxt.n}.${top ? ' Quickest gain: ' + top.title.toLowerCase() + ' (up to +' + top.gain + ').' : ''}` : 'Top level reached.';
    document.getElementById('meLadder').innerHTML = C.ladder(R.score, L);
    const rk = (a, b) => a ? `#${a} <small style="font-size:.8rem;opacity:.7">of ${b}</small>` : '–';
    const rmv = R.prevRank && R.rank ? R.prevRank - R.rank : null;
    document.getElementById('meStats').innerHTML = `
      <div class="stat"><span>Rank in ${C.esc(R.group)}</span><b class="num">${rk(R.rank, R.of)}</b>${rmv ? `<span class="mv ${rmv > 0 ? 'up' : 'dn'}">${rmv > 0 ? '▲' : '▼'} ${Math.abs(rmv)}</span>` : ''}</div>
      <div class="stat"><span>Company rank</span><b class="num">${rk(R.crank, R.cof)}</b></div>
      <div class="stat"><span>On-time streak</span><b class="num">🔥 ${R.streak} mo</b></div>
      <div class="stat"><span>Claims this month</span><b class="num">${R.claims}</b>${R.ranked ? '' : '<span>below the minimum of ' + P.minClaims + '</span>'}</div>`;
    document.getElementById('meMissions').innerHTML = R.missions.length ? R.missions.map(m => `<div class="mission"><div style="display:flex;justify-content:space-between;gap:8px"><b>${C.esc(m.title)}</b><span class="xp">+${m.gain} pts</span></div><p class="small muted mb-0">${C.esc(m.desc)}</p><div class="prog"><i style="width:${m.v}%"></i></div><span class="small muted">Current mark ${m.v} / 100</span></div>`).join('') : '<p class="muted mb-0">Nothing left to gain this month. Keep the streak going.</p>';
    document.getElementById('meShape').innerHTML = P.pillars.map(p => { const v = R.pil[p.k], a = R.avg[p.k], b = R.best[p.k];
        if (v == null) return `<div><div class="sh-top"><b>${p.name}</b><span class="muted">no data</span></div></div>`;
        const say = v >= 95 ? 'Full marks.' : v >= a + 5 ? 'Ahead of your department.' : v >= a - 5 ? 'Level with your department.' : 'Behind your department by ' + (a - v) + ' marks.';
        return `<div style="--pc:var(${p.c})"><div class="sh-top"><b>${p.name} <span class="muted small">weight ${p.w}</span></b><span style="color:var(${p.c})">${v}</span></div><div class="sh-track"><div class="sh-fill" style="width:${v}%"></div><span class="sh-mk" style="left:${a}%;background:var(--ink)"></span><span class="sh-mk" style="left:${b}%;background:var(--gold)"></span></div><span class="small muted">${say} Average ${a} · best ${b}.</span></div>`; }).join('');
    const icons = ['<path d="M18 30h18M27 21v18" stroke="#fff" stroke-width="3" stroke-linecap="round"/>', '<path d="M17 25h14l5 5v6H17z" fill="#fff"/>', '<path d="M18 30l6 6 12-13" stroke="#fff" stroke-width="3.5" fill="none" stroke-linecap="round"/>', '<path d="M27 18l9 4v7c0 6-4 9-9 11-5-2-9-5-9-11v-7z" fill="#fff"/>', '<text x="27" y="35" text-anchor="middle" font-size="14" font-weight="800" fill="#fff">3</text>', '<text x="27" y="35" text-anchor="middle" font-size="14" font-weight="800" fill="#fff">6</text>', '<path d="M27 17l3 6.5 7 1-5 5 1.2 7L27 33l-6.2 3.5 1.2-7-5-5 7-1z" fill="#fff"/>'];
    const cols = ['--a1', '--a2', '--a3', '--a4', '--gold', '--a5', '--plat'];
    document.getElementById('meBadges').innerHTML = R.badges.map((b, i) => `<div class="bdg ${b.on ? '' : 'off'}"><svg viewBox="0 0 54 54"><path d="M27 2l22 12.5v25L27 52 5 39.5v-25z" fill="${C.css(cols[i])}"/>${icons[i]}</svg><b>${C.esc(b.n)}</b><span>${b.on ? 'Earned in ' + P.monthName : C.esc(b.d) + ' · ' + b.pct + '%'}</span></div>`).join('');
    document.getElementById('meBadgeCount').textContent = `${R.badges.filter(b => b.on).length} of ${R.badges.length} earned in ${P.monthName}`;
    const lvOf = s => C.levelOf(s, L), G = R.board;
    const lab = x => x.nm ? C.esc(x.nm) + (x.me ? ' (you)' : '') : 'Colleague · ' + C.esc(x.g);
    document.getElementById('lbPodium').innerHTML = [G[1], G[0], G[2]].map((x, i) => x ? `<div class="pod r${[2, 1, 3][i]}">${x.nm ? C.avatar(x.nm, x.hue, lvOf(x.score), 'lg') : '<span class="av lg" style="background:var(--muted)">?</span>'}<span class="nm">${lab(x)}</span><div class="blk">${x.score}<small style="font-size:.66rem;display:block">#${x.rank}</small></div></div>` : '<div></div>').join('');
    const row = x => `<div class="row2 ${x.me ? 'me' : ''}"><span class="rk">${x.rank}</span>${x.nm ? C.avatar(x.nm, x.hue, lvOf(x.score)) : '<span class="av" style="background:var(--muted)">?</span>'}<span class="who"><b>${lab(x)}</b><span>${C.lvlTag(lvOf(x.score))} ${x.mv ? `<span class="chip ${x.mv > 0 ? 'good' : 'bad'}">${x.mv > 0 ? '▲' : '▼'} ${Math.abs(x.mv)}</span>` : ''}</span></span>${C.pbars(x.pil, P.pillars)}<span class="sc num">${x.score}</span></div>`;
    const me = G.findIndex(x => x.me);
    let h = G.slice(3, 10).map(row).join('');
    if (me >= 10) h += (me > 11 ? '<div class="text-center small muted">· · ·</div>' : '') + G.slice(Math.max(10, me - 1), me + 2).map(row).join('');
    if (G.length > Math.max(10, me + 2)) h += `<div class="text-center small muted py-1">${G.length - Math.max(10, me + 2)} more below</div>`;
    if (me < 0) h += `<div class="row2 me"><span class="rk">–</span>${C.avatar(P.person.nm, 1, lv)}<span class="who"><b>${C.esc(P.person.nm)} (you)</b><span>Not ranked this month: ${R.claims} claims, ranking needs ${P.minClaims}</span></span><span></span><span class="sc num">${R.score}</span></div>`;
    document.getElementById('lbRows').innerHTML = h;
    const bands = { id: 'bands', beforeDraw(c) { const y = c.scales.y, a = c.chartArea, ctx = c.ctx; ctx.save(); const asc = L.slice().reverse();
        asc.forEach((l, i) => { const hi = i + 1 < asc.length ? asc[i + 1].min : 1000, t = y.getPixelForValue(Math.min(hi, y.max)), b = y.getPixelForValue(Math.max(l.min, y.min)); if (b > t) { ctx.fillStyle = C.css(l.c); ctx.globalAlpha = .1; ctx.fillRect(a.left, t, a.right - a.left, b - t); } }); ctx.restore(); } };
    C.chart('cTrend', { type: 'line', plugins: [bands], data: { labels: R.hist.map(h => h.m), datasets: [
        { label: 'You', data: R.hist.map(h => h.me), borderColor: C.css('--accent'), backgroundColor: C.css('--accent'), borderWidth: 3, tension: .35, spanGaps: true },
        { label: 'Department average', data: R.hist.map(h => h.avg), borderColor: C.css('--teal'), borderDash: [5, 4], borderWidth: 2, tension: .35, pointRadius: 0, spanGaps: true }] },
        options: { maintainAspectRatio: false, scales: { y: { min: 300, max: 1000 } }, plugins: { legend: { position: 'bottom' } } } });
})();
</script>
@endif
@if ($isAdmin)
<script>
document.getElementById('csEmp')?.addEventListener('change', function () { const o = [...document.querySelectorAll('#csPeople option')].find(x => x.value === this.value); if (o) { document.getElementById('csEmpId').value = o.dataset.id; this.form.submit(); } });
</script>
@endif
@endsection
