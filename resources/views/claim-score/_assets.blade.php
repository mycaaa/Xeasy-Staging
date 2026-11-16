{{-- CLAIM-SCORE-01: shared look of the Claim Score pages (scoped to .cs) and small drawing helpers --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap">
<script src="{{ asset('vendor/cdn/chart.js@4.4.0/dist/chart.umd.js') }}"></script>
<style>
.cs { --bg:#f1f3f8; --surface:#fff; --sunk:#eceff6; --line:#dde2ec; --ink:#18213a; --muted:#5f6b84; --accent:#5b3fc4; --accent-soft:#ece7fb; --teal:#15648c;
  --hero1:#23185f; --hero2:#3a2a9a; --hero3:#15648c; --on-hero:#fff; --on-hero-2:rgba(255,255,255,.75); --hero-track:rgba(255,255,255,.16);
  --me:#fff4cf; --good:#18885a; --good-soft:#e0f3ea; --warn:#b26a00; --warn-soft:#fbefd9; --bad:#c43b2e; --bad-soft:#fbe3e0;
  --plat:#6d7fb0; --gold:#d29b12; --silver:#8d99a8; --bronze:#b36a3c; --low:#c43b2e; --p1:#15648c; --p2:#7b52d6; --p3:#18885a; --p4:#d07a10;
  --a1:#5b3fc4; --a2:#15648c; --a3:#18885a; --a4:#c2410c; --a5:#be185d; --a6:#0e7490; --fh:"Space Grotesk","Segoe UI",system-ui,sans-serif;
  color: var(--ink); font-size: 14px; line-height: 1.5; }
.cs * { box-sizing: border-box; }
.cs h2, .cs h3 { font-family: var(--fh); margin: 0; letter-spacing: -.01em; color: var(--ink); }
.cs h2 { font-size: 1.15rem; } .cs h3 { font-size: .95rem; }
.cs .muted { color: var(--muted); } .cs .small { font-size: .8rem; } .cs .num { font-variant-numeric: tabular-nums; }
.cs .stack { display: grid; gap: 16px; }
.cs .g2 { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); }
.cs .card2 { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 16px 18px; min-width: 0; }
.cs .card-h { display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: baseline; justify-content: space-between; margin-bottom: 12px; }
.cs .cw { position: relative; height: 250px; } .cs .cw.tall { height: 300px; }
.cs .topbar { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; justify-content: space-between; }
.cs .topbar form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.cs .topbar select, .cs .topbar input { border: 1px solid var(--line); background: var(--surface); border-radius: 8px; padding: 5px 8px; font-size: .84rem; }
.cs .hero { position: relative; overflow: hidden; border-radius: 20px; color: var(--on-hero); background: radial-gradient(120% 140% at 0% 0%, var(--hero2) 0%, var(--hero1) 55%, var(--hero3) 120%); padding: 22px 24px; }
.cs .hero h2 { color: #fff; font-size: 1.35rem; } .cs .hero .sub { color: var(--on-hero-2); font-size: .85rem; margin: 0; }
.cs .hero-grid { display: grid; grid-template-columns: auto 1fr auto; gap: 18px 28px; align-items: center; }
@media (max-width: 860px) { .cs .hero-grid { grid-template-columns: auto 1fr; } .cs .hero-side { grid-column: 1 / -1; } }
@media (max-width: 520px) { .cs .hero-grid { grid-template-columns: 1fr; } }
.cs .ring { position: relative; width: 176px; height: 176px; }
.cs .ring svg { width: 100%; height: 100%; }
.cs .ring .c { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
.cs .ring .c b { font: 700 2.7rem var(--fh); line-height: 1; color: #fff; } .cs .ring .c span { font-size: .72rem; color: var(--on-hero-2); }
.cs .lvlrow { display: flex; align-items: center; gap: 10px; margin: 10px 0 4px; } .cs .lvlrow b { font: 700 1.1rem var(--fh); }
.cs .ladder { position: relative; margin-top: 14px; max-width: 520px; }
.cs .ladder .track { display: flex; height: 10px; border-radius: 6px; overflow: hidden; background: var(--hero-track); }
.cs .ladder .pin { position: absolute; top: -5px; width: 4px; height: 20px; border-radius: 2px; background: #fff; transform: translateX(-2px); }
.cs .ladder .ticks { position: relative; height: 16px; font-size: .68rem; color: var(--on-hero-2); margin-top: 5px; }
.cs .ladder .ticks span { position: absolute; transform: translateX(-50%); white-space: nowrap; }
.cs .ladder .ticks span:first-child { transform: none; } .cs .ladder .ticks span:last-child { transform: translateX(-100%); }
.cs .hero-side { display: grid; grid-template-columns: repeat(2, minmax(110px, 1fr)); gap: 10px; }
.cs .stat { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.14); border-radius: 14px; padding: 10px 12px; }
.cs .stat b { display: block; font: 700 1.35rem var(--fh); line-height: 1.15; color: #fff; } .cs .stat span { font-size: .72rem; color: var(--on-hero-2); }
.cs .mv { display: inline-flex; gap: 3px; font-size: .74rem; font-weight: 700; border-radius: 999px; padding: 1px 8px; }
.cs .mv.up { background: rgba(76,198,141,.22); color: #8ff0bd; } .cs .mv.dn { background: rgba(242,132,122,.22); color: #ffb3ab; }
.cs .missions { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
.cs .mission { border: 1px solid var(--line); border-radius: 14px; padding: 13px 14px; display: grid; gap: 8px; background: linear-gradient(180deg, var(--surface), var(--sunk)); }
.cs .mission .xp { font: 700 .82rem var(--fh); color: var(--accent); background: var(--accent-soft); border-radius: 999px; padding: 2px 9px; white-space: nowrap; }
.cs .prog { height: 8px; border-radius: 6px; background: var(--sunk); overflow: hidden; border: 1px solid var(--line); }
.cs .prog i { display: block; height: 100%; background: linear-gradient(90deg, var(--accent), var(--teal)); }
.cs .badges { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
.cs .bdg { display: grid; justify-items: center; text-align: center; gap: 4px; padding: 10px 8px; border-radius: 14px; border: 1px solid var(--line); }
.cs .bdg svg { width: 54px; height: 54px; } .cs .bdg b { font-size: .8rem; } .cs .bdg span { font-size: .7rem; color: var(--muted); }
.cs .bdg.off { opacity: .55; } .cs .bdg.off svg { filter: grayscale(1); }
.cs .cs-shape { display: grid; gap: 16px; position: static; }
.cs .sh-top { display: flex; justify-content: space-between; align-items: baseline; } .cs .sh-top > span { font: 700 1.15rem var(--fh); } .cs .sh-top b span { font: 400 .74rem inherit; }
.cs .sh-track { position: relative; height: 18px; border-radius: 10px; background: var(--sunk); margin: 4px 0; }
.cs .sh-fill { position: absolute; inset: 0 auto 0 0; border-radius: 10px; background: var(--pc); opacity: .9; }
.cs .sh-mk { position: absolute; top: -5px; bottom: -5px; width: 3px; border-radius: 2px; transform: translateX(-1.5px); }
.cs .podium { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; align-items: end; max-width: 560px; margin: 4px auto 14px; }
.cs .pod { display: grid; justify-items: center; gap: 4px; text-align: center; min-width: 0; }
.cs .pod .nm { font-size: .78rem; font-weight: 700; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cs .pod .blk { width: 100%; border-radius: 12px 12px 4px 4px; display: grid; place-content: center; color: #fff; font: 700 1.15rem var(--fh); }
.cs .pod.r1 .blk { height: 104px; background: linear-gradient(180deg, #e5b52a, #9c7409); } .cs .pod.r2 .blk { height: 78px; background: linear-gradient(180deg, #a3aebb, #66717d); } .cs .pod.r3 .blk { height: 60px; background: linear-gradient(180deg, #c98352, #7e4826); }
.cs .av { width: 38px; height: 38px; border-radius: 50%; display: grid; place-content: center; color: #fff; font-weight: 800; font-size: .78rem; flex: none; position: relative; }
.cs .av.lg { width: 52px; height: 52px; font-size: 1rem; }
.cs .av .md { position: absolute; right: -5px; bottom: -5px; width: 20px; height: 20px; }
.cs .row2 { display: grid; grid-template-columns: 36px 40px minmax(0,1fr) auto auto; gap: 10px; align-items: center; padding: 8px 6px; border-bottom: 1px solid var(--line); }
.cs .row2.me { background: var(--me); border-radius: 10px; }
.cs .row2 .rk { font: 700 .95rem var(--fh); text-align: center; color: var(--muted); }
.cs .row2 .who b { display: block; font-size: .86rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; } .cs .row2 .who span { font-size: .72rem; color: var(--muted); }
.cs .row2 .sc { font: 700 1.05rem var(--fh); text-align: right; min-width: 52px; }
.cs .pbars { display: flex; gap: 3px; align-items: flex-end; height: 24px; } .cs .pbars i { width: 8px; border-radius: 2px 2px 0 0; display: block; }
.cs .chip { display: inline-flex; gap: 3px; font-size: .72rem; font-weight: 700; border-radius: 6px; padding: 1px 7px; }
.cs .chip.good { background: var(--good-soft); color: var(--good); } .cs .chip.bad { background: var(--bad-soft); color: var(--bad); } .cs .chip.warn { background: var(--warn-soft); color: var(--warn); } .cs .chip.eq { background: var(--sunk); color: var(--muted); }
.cs .lvl { display: inline-flex; align-items: center; gap: 4px; font-size: .72rem; font-weight: 700; border-radius: 999px; padding: 1px 9px 1px 3px; border: 1.5px solid var(--lc); color: var(--lc); white-space: nowrap; }
.cs .lvl svg { width: 16px; height: 16px; }
.cs table.t { width: 100%; border-collapse: collapse; } .cs .t td.emp { min-width: 210px; }
.cs .t td.emp b { white-space: nowrap; }
.cs .t th, .cs .t td { padding: 7px 8px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.cs .t th { font-size: .72rem; color: var(--muted); font-weight: 600; white-space: nowrap; } .cs .t .r { text-align: right; } .cs .t .c { text-align: center; }
.cs .tscroll { overflow-x: auto; }
.cs .heat { border-radius: 8px; font: 700 .8rem var(--fh); text-align: center; min-width: 46px; padding: 6px 4px; }
.cs .alert2 { display: grid; grid-template-columns: 40px 1fr auto; gap: 10px; align-items: start; border: 1px solid var(--line); border-left: 4px solid var(--bad); border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; }
.cs .alert2.warn { border-left-color: var(--warn); }
.cs .btn2 { border: 0; border-radius: 8px; padding: 7px 12px; font-weight: 600; font-size: .8rem; cursor: pointer; background: var(--accent-soft); color: var(--accent); }
.cs .scope { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
.cs .scope .scope-lab { font-size: .72rem; font-weight: 600; color: var(--on-hero-2); }
.cs .scope a { display: inline-flex; align-items: center; gap: 7px; font: 700 .86rem inherit; color: #fff; background: rgba(255,255,255,.12); border: 1.5px solid rgba(255,255,255,.6); border-radius: 999px; padding: 7px 14px; text-decoration: none; }
.cs .scope a:hover { background: rgba(255,255,255,.24); } .cs .scope a.on { background: #fff; color: var(--hero1); border-color: #fff; box-shadow: 0 4px 14px rgba(0,0,0,.25); }
.cs .scope svg { width: 18px; height: 18px; fill: currentColor; }
.cs .subtabs { display: flex; flex-wrap: wrap; gap: 4px; background: var(--sunk); border-radius: 12px; padding: 4px; }
.cs .subtabs button { border: 0; background: none; border-radius: 9px; padding: 7px 12px; font-weight: 600; font-size: .82rem; color: var(--muted); cursor: pointer; }
.cs .subtabs button.on { background: var(--surface); color: var(--accent); box-shadow: 0 1px 4px rgba(15,23,42,.12); }
.cs .cases { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
.cs .flag { min-width: 0; border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; display: grid; gap: 4px; border-top: 4px solid var(--fc, var(--bad)); }
.cs .flag > b { font: 700 1.4rem var(--fh); } .cs .flag code { word-break: break-all; font-size: .74rem; }
.cs .flag select, .cs .flag input { width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 4px 6px; margin-top: 4px; }
.cs .meter { height: 6px; border-radius: 4px; background: var(--sunk); overflow: hidden; min-width: 70px; } .cs .meter i { display: block; height: 100%; background: var(--bad); }
.cs .note2 { background: var(--accent-soft); border-radius: 12px; padding: 10px 14px; font-size: .84rem; }
</style>
<script>
window.CS = (function () {
    const css = n => getComputedStyle(document.querySelector('.cs')).getPropertyValue(n).trim();
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const initials = n => String(n || '?').split(' ').filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    function medal(lv, color, size = 40) {
        const c = css(color) || '#999', id = 'm' + Math.random().toString(36).slice(2, 7);
        const glyph = lv === 'Needs attention' ? '<text x="20" y="25.5" text-anchor="middle" font-size="14" font-weight="800" fill="#fff">!</text>'
            : '<path d="M20 9.5l2.9 6 6.5.9-4.7 4.6 1.1 6.5L20 24.4l-5.8 3.1 1.1-6.5-4.7-4.6 6.5-.9z" fill="#fff"/>';
        return `<svg viewBox="0 0 40 46" width="${size}" height="${size * 1.15}" aria-hidden="true"><defs><radialGradient id="${id}" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#fff" stop-opacity=".55"/><stop offset=".45" stop-color="${c}"/><stop offset="1" stop-color="${c}"/></radialGradient></defs><path d="M12 30l-4 14 7-3 4 5 3-14z" fill="${c}" opacity=".7"/><path d="M28 30l4 14-7-3-4 5-3-14z" fill="${c}" opacity=".55"/><circle cx="20" cy="19" r="16" fill="url(#${id})" stroke="${c}" stroke-width="2"/>${glyph}</svg>`;
    }
    const lvlTag = l => `<span class="lvl" style="--lc:var(${l.c})">${medal(l.n, l.c, 16)}${esc(l.n)}</span>`;
    function ring(s, sub, levels) {
        const r = 74, C = 2 * Math.PI * r, f = Math.max(0, Math.min(1, s / 1000)), l = levelOf(s, levels), id = 'rg' + Math.random().toString(36).slice(2, 7);
        const ticks = levels.filter(x => x.min > 0).map(x => { const a = x.min / 1000 * 2 * Math.PI - Math.PI / 2; return `<line x1="${88 + Math.cos(a) * 64}" y1="${88 + Math.sin(a) * 64}" x2="${88 + Math.cos(a) * 84}" y2="${88 + Math.sin(a) * 84}" stroke="rgba(255,255,255,.35)" stroke-width="2"/>`; }).join('');
        return `<svg viewBox="0 0 176 176" role="img" aria-label="Score ${s} of 1000"><defs><linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffe08a"/><stop offset="1" stop-color="${css(l.c)}"/></linearGradient></defs><circle cx="88" cy="88" r="${r}" fill="none" stroke="rgba(255,255,255,.16)" stroke-width="14"/><circle cx="88" cy="88" r="${r}" fill="none" stroke="url(#${id})" stroke-width="14" stroke-linecap="round" stroke-dasharray="${(C * f).toFixed(1)} ${C.toFixed(1)}" transform="rotate(-90 88 88)"/>${ticks}</svg><div class="c"><b class="num">${s}</b><span>${esc(sub)}</span></div>`;
    }
    const levelOf = (s, levels) => levels.find(l => s >= l.min) || levels[levels.length - 1];
    function ladder(s, levels) {
        const asc = levels.slice().reverse(), stops = asc.map((l, i) => [l.min, i + 1 < asc.length ? asc[i + 1].min : 1000, l.c]);
        return `<div class="track">${stops.map(([a, b, c]) => `<i style="width:${(b - a) / 10}%;background:var(${c})"></i>`).join('')}</div><div class="pin" style="left:${Math.min(100, s / 10)}%"></div><div class="ticks">${[0, ...asc.slice(1).map(l => l.min), 1000].map(x => `<span style="left:${x / 10}%">${x}</span>`).join('')}</div>`;
    }
    const avatar = (name, hue, lvl, cls = '') => `<span class="av ${cls}" style="background:var(--a${hue || 1})">${esc(initials(name))}${lvl ? `<span class="md">${medal(lvl.n, lvl.c, 20)}</span>` : ''}</span>`;
    const pbars = (pil, pillars) => `<span class="pbars" aria-hidden="true">${pillars.map(p => `<i style="height:${Math.max(2, (pil[p.k] || 0) / 100 * 24)}px;background:var(${p.c})"></i>`).join('')}</span>`;
    const charts = {};
    function chart(id, cfg) { if (charts[id]) charts[id].destroy(); const el = document.getElementById(id); if (el && window.Chart) charts[id] = new Chart(el, cfg); }
    const fmt = n => Number(n || 0).toLocaleString('en-IN');
    const rupee = n => '₹' + Math.round(n || 0).toLocaleString('en-IN');
    const lakh = n => n >= 1e7 ? '₹' + (n / 1e7).toFixed(2) + ' cr' : n >= 1e5 ? '₹' + (n / 1e5).toFixed(1) + ' lakh' : rupee(n);
    return { css, esc, medal, lvlTag, ring, levelOf, ladder, avatar, pbars, chart, fmt, rupee, lakh };
})();
</script>
