{{-- CLAIM-SCORE-02: Finance & Forensics board (Finance and Super Admin) --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid cs">
@include('claim-score._assets')
<div class="stack">
    <div class="hero">
        <div class="topbar"><div><p class="sub">{{ session('company_id', 1) == 11 ? 'VVNR' : 'VNR' }} · this financial year · figures as of {{ $fin['generated'] }} (refreshed every 30 minutes)</p><h2>Finance &amp; Forensics</h2>
            <p class="sub" style="max-width:720px">Money at risk, approval controls, policy breaches, cuts at each stage, red-flag patterns and repeated late submitters. None of this feeds the employee score.</p></div>
            <a href="?refresh=1" class="btn2" style="text-decoration:none">Refresh now</a></div>
        <div class="hero-side mt-3" id="finStats" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))"></div>
    </div>
    <nav class="subtabs" id="finTabs">
        @foreach (['controls' => 'Controls & money at risk', 'late' => 'Repeated late submission', 'cuts' => 'Cuts', 'flags' => 'Red flags', 'round' => 'Round amounts', 'stage' => 'Finance stage & payments'] as $k => $l)
            <button type="button" data-ft="{{ $k }}">{{ $l }}</button>
        @endforeach
    </nav>
    <div id="fs-controls" class="stack"><div class="note2" id="ccProgress"></div><div id="ccGroups" class="stack"></div>
        <div class="card2"><div class="card-h"><h3>Approvers who rarely cut</h3><span class="muted small">1,000+ claims approved, cuts on fewer than 0.5%</span></div><div class="tscroll"><table class="t" id="ccAppr"></table></div></div></div>
    <div id="fs-late" class="stack" hidden><div class="card2"><div class="card-h"><h3>Employees who missed the submission deadline in 2 or more months</h3><span class="muted small" id="lateNote"></span></div><div class="tscroll"><table class="t" id="lateTbl"></table></div></div></div>
    <div id="fs-cuts" class="stack" hidden>
        <div class="g2"><div class="card2"><div class="card-h"><h3>Amount cut by month</h3><span class="muted small">Bars ₹ cut by verifiers · line % of verified amount</span></div><div class="cw"><canvas id="cFinMonth"></canvas></div></div>
            <div class="card2"><div class="card-h"><h3>Where the cuts are</h3><span class="muted small">₹ cut by claim type</span></div><div class="cw"><canvas id="cFinType"></canvas></div></div></div>
        <div class="card2"><div class="card-h"><h3>Highest reduction rate</h3><span class="muted small">₹20,000+ verified this year</span></div><div class="tscroll"><table class="t" id="finEmp"></table></div></div>
        <div class="card2"><div class="card-h"><h3>Verifiers</h3></div><div class="tscroll"><table class="t" id="finVer"></table></div></div>
    </div>
    <div id="fs-flags" class="stack" hidden><div class="cases" id="finFlags"></div>
        <div class="card2"><div class="card-h"><h3>Possible duplicate bills</h3><span class="muted small">Same employee, claim type, bill date and amount, ₹200+</span></div><div class="tscroll"><table class="t" id="finDup"></table></div></div>
        <div class="card2"><div class="card-h"><h3>Unusually large bills</h3><span class="muted small">More than 3× the employee's usual amount for the claim type, ₹2,000+</span></div><div class="tscroll"><table class="t" id="finOut"></table></div></div></div>
    <div id="fs-round" class="stack" hidden><div class="card2"><div class="card-h"><h3>Round-number claims by type</h3><span class="muted small">Share of ₹100+ bills that are exact multiples of ₹500</span></div><div class="cw tall"><canvas id="cFinRound"></canvas></div></div>
        <div class="card2"><div class="card-h"><h3>Employees with the most round-number bills</h3><span class="muted small">30+ bills of ₹100+</span></div><div class="tscroll"><table class="t" id="finRoundEmp"></table></div></div></div>
    <div id="fs-stage" class="stack" hidden>
        <div class="g2"><div class="card2"><div class="card-h"><h3>Stage speed by month</h3><span class="muted small">Average days</span></div><div class="cw"><canvas id="cFinStage"></canvas></div></div>
            <div class="card2"><div class="card-h"><h3>Financed, not yet paid</h3></div><div class="cw"><canvas id="cFinPay"></canvas></div></div></div>
        <div class="card2"><div class="card-h"><h3>Month by month</h3></div><div class="tscroll"><table class="t" id="finPayTbl"></table></div></div>
        <div class="card2"><div class="card-h"><h3>People at the Finance and verification steps</h3><span class="muted small">50+ claims</span></div><div class="tscroll"><table class="t" id="finPeople"></table></div></div></div>
</div>
</div></div>
<script>
(function () {
    const F = @json($fin), CASES = @json($cases), C = CS, MN = F.monthNames, esc = C.esc, rupee = C.rupee, lakh = C.lakh, fmt = C.fmt;
    const ftype = id => (F.types || {})[id] || 'Type ' + id;
    const sum = (a, k) => a.reduce((s, x) => s + (+x[k] || 0), 0);
    const M = F.monthly || [], vcut = sum(M, 'vcutamt'), vfil = sum(M, 'vfil'), ctl = F.controls || {};
    const stg = k => (F.finstage || []).filter(s => s.k === k), wavg = (rows, f) => { const w = sum(rows, 'n'); return w ? rows.reduce((a, r) => a + r[f] * r.n, 0) / w : 0; };
    const unpaid = sum(F.payments || [], 'unpaid'), unpaidAmt = sum(F.payments || [], 'unpaid_amt');
    document.getElementById('finStats').innerHTML = `
      <div class="stat"><span>Cut by verifiers</span><b>${lakh(vcut)}</b><span>${vfil ? (vcut / vfil * 100).toFixed(2) : 0}% of ${lakh(vfil)} verified</span></div>
      <div class="stat"><span>Claims cut</span><b>${fmt(sum(M, 'vcut'))}</b><span>of ${fmt(sum(M, 'ver'))} verified</span></div>
      <div class="stat"><span>Cut after verification</span><b>${rupee(sum(M, 'acutamt') + sum(M, 'fcutamt'))}</b><span>${sum(M, 'acut')} by approvers · ${sum(M, 'fcut')} by Finance</span></div>
      <div class="stat"><span>Approved → financed</span><b>${wavg(stg('approve_to_finance'), 'days').toFixed(1)} days</b></div>
      <div class="stat"><span>Financed, not paid</span><b>${fmt(unpaid)}</b><span>claim months · ${lakh(unpaidAmt)}</span></div>
      <div class="stat"><span>Repeated late submitters</span><b>${fmt((F.late.rows || []).length)}</b><span>2+ months past the deadline</span></div>`;
    let tab = 'controls';
    function show(t) { tab = t; document.querySelectorAll('#finTabs button').forEach(b => b.classList.toggle('on', b.dataset.ft === t)); ['controls', 'late', 'cuts', 'flags', 'round', 'stage'].forEach(k => document.getElementById('fs-' + k).hidden = k !== t); R[t](); }
    document.getElementById('finTabs').addEventListener('click', e => { const b = e.target.closest('[data-ft]'); if (b) show(b.dataset.ft); });
    const n = (o, k) => +((o || {})[k] || 0);
    const handled = k => (CASES[k] && CASES[k].status && CASES[k].status !== 'open') ? 1 : 0;
    const R = {};
    R.controls = function () {
        const u = ctl.unsettled || {}, ad = ctl.advances, po = ctl.pay_order, fo = ctl.focus, ra = ctl.raised, ch = ctl.chain, lo = ctl.lodging, le = ctl.leave, sd = ctl.sunday_da, ex = ctl.after_exit, ni = ctl.noimg, sp = ctl.split;
        const utr = (ctl.utr || []), utrN = sum(utr, 'k'), utrA = sum(utr, 'amt');
        const G = [
            ['Money at risk now', '--bad', [
                ['unsettled', 'Payments that did not settle', fmt(n(u.claims, 'n') + n(u.advances, 'n')), `Marked Unsettled after the bank check: ${fmt(n(u.claims, 'n'))} claim months (${lakh(n(u.claims, 'amt'))}) and ${fmt(n(u.advances, 'n'))} advance payments (${lakh(n(u.advances, 'amt'))}).${n(u.bankfile, 'n') ? ' Bank-file mismatches still open: ' + fmt(n(u.bankfile, 'n')) + '.' : ''} Work list: Payment → Expense Payments → Unsettled, and Advance Approvals.`, 'payment_status = Unsettled'],
                ['advances', 'Advances not adjusted', lakh(n(ad, 'bal')), `${fmt(n(ad, 'n'))} advance lines open; ${fmt(n(ad, 'old60'))} (${lakh(n(ad, 'old60_bal'))}) paid more than 60 days ago.`, 'advance_request_activities'],
                ['utr', 'Payments with no real UTR', fmt(utrN), `${lakh(utrA)} recorded with a shared placeholder UTR: ${utr.map(x => '"' + esc(x.u) + '" ×' + x.k).join(', ') || 'none'}.`, 'monthexpensefinal.utr_number'],
                ['payorder', 'Paid before hard copy or approval', fmt(n(po, 'no_hardcopy') + n(po, 'before_appr')), `${fmt(n(po, 'no_hardcopy'))} claim months paid before the hard copy was received; ${fmt(n(po, 'before_appr'))} paid before approval.`, 'monthexpensefinal dates'],
                ['focus', 'Focus ledger mismatch', rupee(n(fo, 'amt')), `${fmt(n(fo, 'diff'))} of ${fmt(n(fo, 'runs'))} reconciliation runs differ from Xeasy.`, 'focus_ledger_recon_logs']]],
            ['Weak approval controls', '--warn', [
                ['raised', 'Amounts raised after filing', lakh(n(ra, 'appr_amt') + n(ra, 'ver_amt') + n(ra, 'fin_amt')), `Approvers raised ${fmt(n(ra, 'appr_up'))} claims (${lakh(n(ra, 'appr_amt'))}), verifiers ${fmt(n(ra, 'ver_up'))} (${lakh(n(ra, 'ver_amt'))}), Finance ${fmt(n(ra, 'fin_up'))}. Reviewers should only reduce.`, 'ApprTAmt > VerifyTAmt, VerifyTAmt > FilledTAmt'],
                ['nocut', 'Approvers who rarely cut', fmt((ctl.approvers || []).length), 'Approvers with 1,000+ claims and almost no cuts. Table below.', 'ApprBy, ApprDate'],
                ['chain', 'Approved outside the reporting line', fmt(n(ch, 'n')), `${lakh(n(ch, 'amt'))}; ${fmt(n(ch, 'skip'))} by the manager's manager, ${fmt(n(ch, 'n') - n(ch, 'skip'))} by another person. Check delegations.`, 'ApprBy vs RepEmployeeID']]],
            ['Policy breaches', '--a5', [
                ['lodging', 'Lodging above eligibility', fmt(n(lo, 'n')), `of ${fmt(n(lo, 'total'))} lodging bills approved above the top city rate, ${lakh(n(lo, 'over_amt'))} over; ${fmt(n(lo, 'over2x'))} more than double. Multi-night stays need a nights field to confirm.`, 'hrm_employee_eligibility'],
                ['leave', 'Bills on leave or absent days', fmt(n(le, 'n')), `${lakh(n(le, 'amt'))} from ${fmt(n(le, 'emps'))} employees on days marked absent or on leave; ${fmt(n(le, 'veh'))} vehicle claims (${fmt(n(le, 'km'))} km).`, 'hrm_employee_attendance'],
                ['sunday', 'DA at HQ on Sundays', fmt(n(sd, 'n')), `${lakh(n(sd, 'amt'))} of DA@HQ dated on a Sunday.`, 'claim type 19, bill weekday'],
                ['exit', 'Bills after separation', fmt(n(ex, 'n')), `${rupee(n(ex, 'amt'))} from ${fmt(n(ex, 'emps'))} employees dated after their separation date.`, 'hrm_employee.DateOfSepration']]],
            ['Weak evidence', '--a2', [
                ['noimg', 'Bills ₹500+ with no image', fmt(n(ni, 'n')), `${lakh(n(ni, 'amt'))} claimed by ${fmt(n(ni, 'emps'))} employees with no image or PDF (vehicle claims excluded).`, 'Img_1..5, offline images, PDFs'],
                ['split', 'Split small bills', fmt(n(sp, 'grp')), `sets of 4+ bills under ₹500 of one type on one day: ${fmt(n(sp, 'bills'))} bills, ${lakh(n(sp, 'amt'))}.`, 'same employee, type, day'],
                ['edits', 'Amount edit history', fmt(ctl.edits || 0), 'recorded amount changes.', 'claim_amount_change_logs']]]];
        const all = G.flatMap(g => g[2]), done = all.filter(c => CASES[c[0]] && CASES[c[0]].status !== 'open').length;
        document.getElementById('ccProgress').innerHTML = `<b>${done} of ${all.length}</b> checks handled. Set each to Checked, Recovered or Not an issue with a remark; it is saved with your name and the time.`;
        const OPTS = [['open', 'Open'], ['checked', 'Checked'], ['recovered', 'Recovered'], ['ok', 'Not an issue']];
        document.getElementById('ccGroups').innerHTML = G.map(([title, col, cards]) => `<div class="card2"><div class="card-h"><h3 style="color:var(${col})">${title}</h3></div><div class="cases">` +
            cards.slice().sort((a, b) => handled(a[0]) - handled(b[0])).map(([k, l, big, txt, src]) => {
                const c = CASES[k] || {}, h = c.status && c.status !== 'open';
                return `<div class="flag" style="--fc:var(${h ? '--good' : col});${h ? 'opacity:.75' : ''}"><b class="num">${big}</b><span><b style="font-size:.84rem">${esc(l)}</b><br><span class="small muted">${esc(txt)}</span></span><span class="small muted">Source: <code>${esc(src)}</code></span>
                <select data-case="${k}" aria-label="Status">${OPTS.map(([v, t]) => `<option value="${v}" ${(c.status || 'open') === v ? 'selected' : ''}>${t}</option>`).join('')}</select>
                <input data-remark="${k}" value="${esc(c.remark || '')}" placeholder="Remark" aria-label="Remark">${c.by ? `<span class="small muted">by ${esc(c.by)} · ${esc(String(c.updated_at || '').slice(0, 16))}</span>` : ''}</div>`; }).join('') + '</div></div>').join('');
        document.getElementById('ccAppr').innerHTML = `<thead><tr><th>Approver</th><th class="r">Claims approved</th><th class="r">Cut</th><th class="r">Approved same day as verified</th><th class="r">Most in one day</th></tr></thead><tbody>` +
            (ctl.approvers || []).map(a => `<tr><td><b class="small">${esc(a.nm)}</b></td><td class="r num">${fmt(a.n)}</td><td class="r"><span class="chip ${a.cut ? 'warn' : 'bad'}">${a.cut}</span></td><td class="r num">${fmt(a.sameday)} (${Math.round(a.sameday / a.n * 100)}%)</td><td class="r num">${a.maxday}</td></tr>`).join('') + '</tbody>';
    };
    const saveCase = (k, status, remark) => fetch(@json(route('claim-score.finance.case')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
        body: JSON.stringify({ key: k, status, remark }) }).then(r => r.json()).then(j => { CASES[k] = { status, remark, by: j.by, updated_at: j.at }; R.controls(); }).catch(() => alert('Could not save. Please try again.'));
    document.getElementById('fs-controls').addEventListener('change', e => { const s = e.target.closest('[data-case]'), r = e.target.closest('[data-remark]');
        if (s) saveCase(s.dataset.case, s.value, (document.querySelector(`[data-remark="${s.dataset.case}"]`) || {}).value || '');
        if (r) saveCase(r.dataset.remark, (document.querySelector(`[data-case="${r.dataset.remark}"]`) || {}).value || 'open', r.value); });
    R.late = function () {
        const L = F.late || { rows: [], closed: [] };
        document.getElementById('lateNote').textContent = `Closed months checked: ${L.closed.map(m => MN[m]).join(', ')} · ${L.rows.length} employees · most months late first`;
        document.getElementById('lateTbl').innerHTML = `<thead><tr><th>#</th><th>Employee</th><th>Department</th><th>Grade</th><th>Reports to</th><th class="r">Months late</th><th>Late in</th><th class="r">Late in a row now</th></tr></thead><tbody>` +
            L.rows.map((r, i) => `<tr><td class="num">${i + 1}</td><td><b class="small">${esc(r.nm)}</b><div class="muted small">code ${esc(r.c)}</div></td><td class="small">${esc(r.d)}</td><td class="small">${esc(r.g)}</td><td class="small">${esc(r.mgrName)}</td>
            <td class="r"><span class="chip ${r.late >= 4 ? 'bad' : 'warn'}">${r.late} of ${r.of}</span></td><td class="small">${r.months.map(m => MN[m]).join(', ')}</td><td class="r num">${r.run ? `<span class="chip bad">${r.run}</span>` : '–'}</td></tr>`).join('') + '</tbody>';
    };
    R.cuts = function () {
        C.chart('cFinMonth', { data: { labels: M.map(m => MN[m.m]), datasets: [
            { type: 'bar', label: '₹ cut', data: M.map(m => Math.round(m.vcutamt)), backgroundColor: C.css('--bad') + 'cc', borderRadius: 6, yAxisID: 'y' },
            { type: 'line', label: '% of verified amount', data: M.map(m => m.vfil ? +(m.vcutamt / m.vfil * 100).toFixed(2) : 0), borderColor: C.css('--accent'), tension: .35, yAxisID: 'y1' }] },
            options: { maintainAspectRatio: false, scales: { y1: { position: 'right', grid: { display: false } } }, plugins: { legend: { position: 'bottom' } } } });
        const T = (F.bytype || []).filter(x => x.vcutamt > 0).sort((a, b) => b.vcutamt - a.vcutamt).slice(0, 8);
        C.chart('cFinType', { type: 'bar', data: { labels: T.map(x => x.name), datasets: [{ data: T.map(x => Math.round(x.vcutamt)), backgroundColor: C.css('--a1'), borderRadius: 6 }] }, options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } } } });
        const E = (F.emp || []).filter(x => x.vfil >= 20000 && x.vcutamt > 0).map(x => ({ ...x, rate: x.vcutamt / x.vfil * 100 })).sort((a, b) => b.rate - a.rate).slice(0, 25), mx = E.length ? E[0].rate : 1;
        document.getElementById('finEmp').innerHTML = `<thead><tr><th>#</th><th>Employee</th><th>Department</th><th class="r">Claims</th><th class="r">Claims cut</th><th class="r">Verified ₹</th><th class="r">Cut ₹</th><th>Reduction rate</th></tr></thead><tbody>` +
            E.map((x, i) => `<tr><td>${i + 1}</td><td><b class="small">${esc(x.nm)}</b><div class="muted small">code ${esc(x.c)}</div></td><td class="small">${esc(x.d)}</td><td class="r num">${x.n}</td><td class="r num">${x.vcut}</td><td class="r num">${rupee(x.vfil)}</td><td class="r num">${rupee(x.vcutamt)}</td><td><div style="display:flex;gap:8px;align-items:center"><b class="num">${x.rate.toFixed(1)}%</b><div class="meter" style="flex:1"><i style="width:${x.rate / mx * 100}%"></i></div></div></td></tr>`).join('') + '</tbody>';
        const vd = Object.fromEntries((F.finpeople || []).filter(p => p.k === 'receive_to_verify').map(p => [p.p, p]));
        document.getElementById('finVer').innerHTML = `<thead><tr><th>Verifier</th><th class="r">Claims verified</th><th class="r">Cut</th><th class="r">Cut rate</th><th class="r">₹ cut</th><th class="r">Days to verify</th></tr></thead><tbody>` +
            (F.ver || []).map(v => `<tr><td><b class="small">${esc(v.nm)}</b></td><td class="r num">${fmt(v.n)}</td><td class="r num">${fmt(v.vcut)}</td><td class="r num">${(v.vcut / v.n * 100).toFixed(1)}%</td><td class="r num">${rupee(v.vcutamt)}</td><td class="r num">${vd[v.e] ? vd[v.e].days.toFixed(1) : '–'}</td></tr>`).join('') + '</tbody>';
    };
    R.flags = function () {
        const t = F.timing || {}, D = F.dups, O = F.outliers;
        document.getElementById('finFlags').innerHTML = [['--bad', fmt(D.groups), 'possible duplicate sets', `${D.extra} extra bills · ${lakh(D.extra_amount)}`], ['--a4', fmt(O.count), 'unusually large bills', lakh(O.amount)],
            ['--a5', fmt(t.future_bill), 'bills dated after upload', 'bill date later than upload'], ['--warn', fmt(t.old_bill), 'bills 60+ days old at upload', ''], ['--a2', t.n ? (t.sunday_bill / t.n * 100).toFixed(1) + '%' : '0%', 'bills dated on a Sunday', fmt(t.sunday_bill) + ' bills']]
            .map(([c, b, l, s]) => `<div class="flag" style="--fc:var(${c})"><b>${b}</b><span><b style="font-size:.82rem">${l}</b><br><span class="small muted">${s}</span></span></div>`).join('');
        document.getElementById('finDup').innerHTML = `<thead><tr><th>Employee</th><th>Claim type</th><th>Bill date</th><th class="r">Amount</th><th class="r">Times</th><th class="r">Extra ₹</th><th>Claim IDs</th></tr></thead><tbody>` +
            D.top.map(x => `<tr><td class="small"><b>${esc(x.nm)}</b></td><td class="small">${esc(ftype(x.t))}</td><td class="small">${x.bd}</td><td class="r num">${rupee(x.a)}</td><td class="r"><span class="chip bad">${x.k}×</span></td><td class="r num">${rupee((x.k - 1) * x.a)}</td><td class="small muted" style="word-break:break-all">${esc(x.ids)}</td></tr>`).join('') + '</tbody>';
        document.getElementById('finOut').innerHTML = `<thead><tr><th>Employee</th><th>Claim type</th><th>Bill date</th><th class="r">Amount</th><th class="r">Usual</th><th class="r">Times usual</th><th>Claim ID</th></tr></thead><tbody>` +
            O.top.map(x => `<tr><td class="small"><b>${esc(x.nm)}</b></td><td class="small">${esc(ftype(x.t))}</td><td class="small">${x.bd}</td><td class="r num">${rupee(x.a)}</td><td class="r num">${rupee(x.med)}</td><td class="r"><span class="chip ${x.x >= 10 ? 'bad' : 'warn'}">${x.x}×</span></td><td class="small">${x.id}</td></tr>`).join('') + '</tbody>';
    };
    R.round = function () {
        const T = (F.bytype || []).filter(x => x.ge100 >= 100).map(x => ({ ...x, s: x.r500 / x.ge100 * 100 })).sort((a, b) => b.s - a.s).slice(0, 14);
        C.chart('cFinRound', { type: 'bar', data: { labels: T.map(x => x.name), datasets: [{ data: T.map(x => +x.s.toFixed(1)), backgroundColor: T.map(x => x.s >= 15 ? C.css('--bad') : x.s >= 5 ? C.css('--warn') : C.css('--teal')), borderRadius: 6 }] }, options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } } } });
        const E = (F.emp || []).filter(x => x.ge100 >= 30).map(x => ({ ...x, s: x.r500 / x.ge100 * 100 })).sort((a, b) => b.s - a.s).slice(0, 20);
        document.getElementById('finRoundEmp').innerHTML = `<thead><tr><th>#</th><th>Employee</th><th>Department</th><th class="r">Bills ₹100+</th><th class="r">Multiples of ₹500</th><th>Share</th></tr></thead><tbody>` +
            E.map((x, i) => `<tr><td>${i + 1}</td><td class="small"><b>${esc(x.nm)}</b></td><td class="small">${esc(x.d)}</td><td class="r num">${x.ge100}</td><td class="r num">${x.r500}</td><td><b>${x.s.toFixed(0)}%</b></td></tr>`).join('') + '</tbody>';
    };
    R.stage = function () {
        const ms = F.months.filter(m => (F.finstage || []).some(s => s.m === m));
        const ser = (k, c, l) => ({ label: l, data: ms.map(m => ((F.finstage || []).find(s => s.m === m && s.k === k) || {}).days ?? null), borderColor: C.css(c), backgroundColor: C.css(c), tension: .35 });
        C.chart('cFinStage', { type: 'line', data: { labels: ms.map(m => MN[m]), datasets: [ser('receive_to_verify', '--bad', 'Received → verified'), ser('verify_to_approve', '--warn', 'Verified → approved'), ser('approve_to_finance', '--accent', 'Approved → financed'), ser('finance_to_payment', '--good', 'Financed → paid')] }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
        const Pm = F.payments || [];
        C.chart('cFinPay', { data: { labels: Pm.map(p => MN[p.m]), datasets: [{ type: 'bar', label: 'Unpaid ₹', data: Pm.map(p => Math.round(p.unpaid_amt || 0)), backgroundColor: C.css('--warn'), borderRadius: 6, yAxisID: 'y' }, { type: 'line', label: 'Claim months unpaid', data: Pm.map(p => p.unpaid || 0), borderColor: C.css('--bad'), yAxisID: 'y1' }] }, options: { maintainAspectRatio: false, scales: { y1: { position: 'right', grid: { display: false } } }, plugins: { legend: { position: 'bottom' } } } });
        document.getElementById('finPayTbl').innerHTML = `<thead><tr><th>Month</th><th class="r">Claim months</th><th class="r">Financed</th><th class="r">Financed ₹</th><th class="r">Unpaid</th><th class="r">Unpaid ₹</th><th class="r">Paid, no UTR</th><th class="r">Approved → financed</th><th class="r">Financed → paid</th></tr></thead><tbody>` +
            Pm.slice().reverse().map(p => `<tr><td>${MN[p.m]}</td><td class="r num">${p.months}</td><td class="r num">${p.financed}</td><td class="r num">${lakh(p.fin_amt)}</td><td class="r">${p.unpaid ? `<span class="chip ${p.unpaid > 10 ? 'bad' : 'warn'}">${p.unpaid}</span>` : 0}</td><td class="r num">${rupee(p.unpaid_amt)}</td><td class="r">${p.paid_no_utr ? `<span class="chip warn">${p.paid_no_utr}</span>` : '–'}</td><td class="r num">${p.appr_to_fin ?? '–'} d</td><td class="r num">${p.fin_to_pay ?? '–'} d</td></tr>`).join('') + '</tbody>';
        const SK = { approve_to_finance: 'Approved → financed', finance_to_payment: 'Financed → paid', receive_to_verify: 'Received → verified' };
        document.getElementById('finPeople').innerHTML = `<thead><tr><th>Person</th><th>Step</th><th class="r">Claims</th><th class="r">Average days</th><th class="r">Stars</th></tr></thead><tbody>` +
            (F.finpeople || []).map(p => `<tr><td class="small"><b>${esc(p.nm)}</b></td><td class="small">${SK[p.k]}</td><td class="r num">${fmt(p.n)}</td><td class="r num">${(+p.days).toFixed(1)}</td><td class="r"><span class="chip ${p.stars >= 4 ? 'good' : p.stars >= 3 ? 'warn' : 'bad'}">${(+p.stars).toFixed(1)}★</span></td></tr>`).join('') + '</tbody>';
    };
    show(location.hash.slice(1) && R[location.hash.slice(1)] ? location.hash.slice(1) : 'controls');
})();
</script>
@endsection
