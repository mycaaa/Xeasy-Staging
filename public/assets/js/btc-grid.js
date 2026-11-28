/* BTC-GRID-02 (28 Nov 2026): BTC lists - click a heading to sort the rows shown (again to reverse);
   lists that are not paged by the server get "Rows per page" and page buttons (tables marked data-pager). */
(function () {
    'use strict';
    const MONTHS = {jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12};
    function key(td) {
        if (!td) return {t: 0, v: ''};
        const raw = (td.dataset.sort !== undefined ? td.dataset.sort : (td.querySelector('input:not([type=checkbox])') || {}).value ?? td.innerText).trim();
        const first = raw.split('\n')[0].trim();
        let m = first.match(/^(\d{2})-(\d{2})-(\d{4})(?:\s+(\d{2}):(\d{2}))?/);                     // dd-mm-yyyy [hh:mm]
        if (m) return {t: 1, v: Number(m[3] + m[2] + m[1] + (m[4] || '00') + (m[5] || '00'))};
        m = first.match(/^(\d{1,2})\s+([A-Za-z]{3})\w*\s+(\d{4})/);                              // 5 Nov 2026
        if (m && MONTHS[m[2].toLowerCase()]) return {t: 1, v: Number(m[3]) * 10000 + MONTHS[m[2].toLowerCase()] * 100 + Number(m[1])};
        const n = first.replace(/[₹,\s]/g, '').replace(/^Recover/i, '-');
        if (/^-?\d+(\.\d+)?$/.test(n)) return {t: 1, v: Number(n)};
        if (first === '-' || first === '—' || first === '') return {t: 2, v: ''};               // blanks last
        return {t: 3, v: first.toLowerCase()};
    }
    function cmp(a, b) {
        if (a.t === 2 || b.t === 2) return a.t === b.t ? 0 : (a.t === 2 ? 1 : -1);
        if (a.t === 1 && b.t === 1) return a.v - b.v;
        return String(a.v).localeCompare(String(b.v), 'en', {numeric: true});
    }
    function dataRows(tb) { return Array.from(tb.rows).filter(r => !(r.cells.length === 1 && r.cells[0].colSpan > 1)); }
    function setupSort(table) {
        const head = table.tHead; if (!head || !head.rows.length || table.dataset.ownSort !== undefined) return;
        const hr = head.rows[head.rows.length - 1];
        Array.from(hr.cells).forEach((th, idx) => {
            if (th.dataset.nosort !== undefined || th.querySelector('input,select,button') || !th.innerText.trim()) return;
            th.classList.add('btc-sortable'); th.tabIndex = 0; th.setAttribute('role', 'columnheader'); th.title = 'Sort by ' + th.innerText.trim();
            const go = () => {
                const tb = table.tBodies[0]; if (!tb) return;
                const dir = th.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
                Array.from(hr.cells).forEach(c => c.removeAttribute('aria-sort'));
                th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
                const rows = dataRows(tb);
                rows.map((r, i) => ({r, k: key(r.cells[idx]), i})).sort((a, b) => ((a.k.t === 2) - (b.k.t === 2)) || (cmp(a.k, b.k) * dir) || (a.i - b.i)).forEach(x => tb.appendChild(x.r));   // blanks stay last
                if (table._btcPage) table._btcPage(1);
            };
            th.addEventListener('click', e => { if (e.target.closest('a,input,button,select')) return; go(); });
            th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
        });
    }
    function setupPager(table) {
        const tb = table.tBodies[0]; if (!tb || table.dataset.pager === undefined) return;
        const rows = () => dataRows(tb); if (rows().length <= 10) return;
        const bar = document.createElement('div'); bar.className = 'btc-pager-bar';
        bar.innerHTML = '<span class="btc-pg-info"></span><label class="mb-0 d-flex align-items-center gap-2">Rows per page <select class="form-select form-select-sm" style="width:auto">'
            + [10, 25, 50, 100, 200].map(n => `<option ${n === 25 ? 'selected' : ''}>${n}</option>`).join('') + '</select></label><nav class="ms-md-auto"><ul class="pagination pagination-sm"></ul></nav>';
        (table.closest('section, .table-responsive, .btc-grid-wrap') || table).insertAdjacentElement('afterend', bar);
        let page = 1;
        const sel = bar.querySelector('select'), ul = bar.querySelector('ul'), info = bar.querySelector('.btc-pg-info');
        const draw = (p) => {
            const all = rows(), size = Number(sel.value), pages = Math.max(1, Math.ceil(all.length / size));
            page = Math.min(Math.max(1, p || page), pages);
            all.forEach((r, i) => { r.style.display = (i >= (page - 1) * size && i < page * size) ? '' : 'none'; });
            info.textContent = `Showing ${(page - 1) * size + 1}–${Math.min(page * size, all.length)} of ${all.length}`;
            const li = (n, l, dis, act) => `<li class="page-item ${dis ? 'disabled' : ''} ${act ? 'active' : ''}"><a class="page-link" href="#" data-p="${n}">${l}</a></li>`;
            let h = li(page - 1, '‹', page === 1);
            for (let n = 1; n <= pages; n++) if (n === 1 || n === pages || Math.abs(n - page) <= 1) h += li(n, n, false, n === page); else if (Math.abs(n - page) === 2) h += li(page, '…', true);
            ul.innerHTML = pages > 1 ? h + li(page + 1, '›', page === pages) : '';
        };
        sel.addEventListener('change', () => draw(1));
        ul.addEventListener('click', e => { const a = e.target.closest('[data-p]'); if (!a) return; e.preventDefault(); draw(Number(a.dataset.p)); });
        table._btcPage = draw; draw(1);
    }
    function init(root) {
        (root || document).querySelectorAll('.btc-ui table').forEach(t => {
            if (t.dataset.btcGrid || t.closest('.modal')) return;
            t.dataset.btcGrid = '1'; setupSort(t); setupPager(t);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init()); else init();
    window.BtcGrid = {init};
})();
