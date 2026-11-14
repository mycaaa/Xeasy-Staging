/* APPR-FILTER-01 (13 Nov 2026) / APPR-FILTER-02 (14 Nov 2026): column filters on the approver's day-wise claim table.
 * A filter row under the header hides the rows that do not match; a claim's participant (PAX) row follows its claim.
 * Ticked rows that get hidden are unticked, so "Approve selected" only ever approves what is on screen.
 *
 * APPR-FILTER-02 (QA, GM Sales):
 *  - columns are found by their header text, and every filter is labelled with its column;
 *  - Claim: a Claim Type drop-down (All, each type, Group claims) and a separate Exp ID search;
 *  - Date: a date picker (no letters);
 *  - Claimed / Verified / Appr. Amt: amounts only (digits, up to 2 decimals); "4000" and "₹4,000.00" match the same;
 *    a wrong entry is marked and ignored;
 *  - Diff: Deducted = Verified < Claimed, Added = Verified > Claimed, No change = equal (from the amounts);
 *  - Clear filters resets every box and drop-down. */
(function () {
    if (window.xeasyApproverFilters) { return; }

    var KINDS = [
        [/^claim$/i, 'claim'], [/^date$/i, 'date'], [/^claimed$/i, 'amount'], [/^verified$/i, 'amount'],
        [/^diff/i, 'diff'], [/verifier remark/i, 'text'], [/^appr\.? ?amt/i, 'amount'], [/emp\.? ?remark/i, 'text'],
        [/^status$/i, 'status']
    ];
    var AMOUNT_RE = /^\d+(\.\d{1,2})?$/;

    function esc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
    function num(v) {
        var t = String(v == null ? '' : v).replace(/[₹,\s]/g, '').replace(/^Rs\.?/i, '');
        return t === '' || isNaN(+t) ? null : +t;
    }

    function columns($table) {
        var cols = [];
        $table.find('thead tr').first().children('th').each(function (i) {
            var name = $.trim($(this).text()), kind = null;
            KINDS.forEach(function (k) { if (!kind && k[0].test(name)) { kind = k[1]; } });
            cols.push({ i: i, name: name, kind: kind });
        });
        return cols;
    }

    function claimTypes($table) {
        var seen = {};
        $table.find('tbody > tr').not('.pax-detail-row').each(function () {
            var c = $(this).data('claim') || $.trim($(this).children('td').eq(2).find('a').first().text());
            if (c) { seen[c] = true; }
        });
        return Object.keys(seen).sort();
    }

    function filterRow($table, cols) {
        var html = '<tr class="apr-filter-row" style="background:#f8fafc;">';
        cols.forEach(function (c) {
            var label = 'Filter ' + (c.name || 'column');
            if (c.kind === 'claim') {
                var opts = '<option value="">All claim types</option><option value="__group">Group claims (with participants)</option>';
                claimTypes($table).forEach(function (t) { opts += '<option value="' + esc(t) + '">' + esc(t) + '</option>'; });
                html += '<th class="p-1"><select class="form-select form-select-sm apr-f mb-1" data-col="' + c.i + '" data-kind="claimtype" aria-label="Filter claim type">' + opts + '</select>'
                    + '<input type="search" inputmode="numeric" class="form-control form-control-sm apr-f" data-col="' + c.i + '" data-kind="expid" placeholder="Exp ID" aria-label="Search Exp ID"></th>';
            } else if (c.kind === 'date') {
                html += '<th class="p-1"><input type="date" class="form-control form-control-sm apr-f" data-col="' + c.i + '" data-kind="date" aria-label="' + esc(label) + '" title="' + esc(label) + '"></th>';
            } else if (c.kind === 'amount') {
                html += '<th class="p-1"><input type="text" inputmode="decimal" class="form-control form-control-sm apr-f" data-col="' + c.i + '" data-kind="amount" placeholder="' + esc(c.name) + '" aria-label="' + esc(label) + ' (amount)"></th>';
            } else if (c.kind === 'diff') {
                html += '<th class="p-1"><select class="form-select form-select-sm apr-f" data-col="' + c.i + '" data-kind="diff" aria-label="Filter difference"><option value="">All</option><option value="-">Deducted</option><option value="+">Added</option><option value="0">No change</option></select></th>';
            } else if (c.kind === 'status') {
                html += '<th class="p-1"><select class="form-select form-select-sm apr-f" data-col="' + c.i + '" data-kind="status" aria-label="Filter status"><option value="">All</option><option value="pending">Pending</option><option value="done">Done</option></select></th>';
            } else if (c.kind === 'text') {
                html += '<th class="p-1"><input type="search" class="form-control form-control-sm apr-f" data-col="' + c.i + '" data-kind="text" placeholder="' + esc(c.name) + '" aria-label="' + esc(label) + '"></th>';
            } else {
                html += '<th class="p-1"></th>';
            }
        });
        return html + '</tr>';
    }

    function cell($tr, i) { return $tr.children('td').eq(i); }
    function cellText($tr, i) {
        var $td = cell($tr, i), $in = $td.find('input[type=number],input[type=text]');
        return ($in.length ? $in.val() : $td.text()).toString().toLowerCase().trim();
    }
    function cellAmount($tr, i) {
        var $td = cell($tr, i), $in = $td.find('input[type=number],input[type=text]');
        return num($in.length ? $in.val() : $td.text());
    }
    function rowDateIso($tr, i) {
        var d = $tr.data('date');
        if (d) { return String(d); }
        var m = $.trim(cell($tr, i).text()).match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/);
        return m ? (m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2)) : '';
    }
    function rowAmounts($tr, cols) {
        var c = $tr.data('claimed'), v = $tr.data('verified');
        if (c === undefined || v === undefined) {
            cols.forEach(function (col) {
                if (/^claimed$/i.test(col.name) && c === undefined) { c = cellAmount($tr, col.i); }
                if (/^verified$/i.test(col.name) && v === undefined) { v = cellAmount($tr, col.i); }
            });
        }
        return { c: num(c), v: num(v) };
    }

    function apply($table, cols) {
        var filters = [];
        $table.find('.apr-f').each(function () {
            var $f = $(this), kind = $f.data('kind'), raw = $.trim(String($f.val() || ''));
            $f.removeClass('is-invalid');
            if (raw === '') { return; }
            if (kind === 'amount') {
                var clean = raw.replace(/[₹,\s]/g, '').replace(/^Rs\.?/i, '');
                if (!AMOUNT_RE.test(clean)) { $f.addClass('is-invalid').attr('title', 'Enter an amount: digits, up to 2 decimals'); return; }
                filters.push({ col: +$f.data('col'), kind: kind, v: +clean });
            } else if (kind === 'expid') {
                var d = raw.replace(/\D/g, '');
                if (!d) { $f.addClass('is-invalid'); return; }
                filters.push({ col: +$f.data('col'), kind: kind, v: d });
            } else {
                filters.push({ col: +$f.data('col'), kind: kind, v: raw.toLowerCase() });
            }
        });
        var shown = 0, total = 0;
        $table.find('tbody > tr').not('.pax-detail-row').each(function () {
            var $tr = $(this), ok = true;
            if ($tr.children('td[colspan]').length) { return; }
            total++;
            filters.forEach(function (f) {
                if (!ok) { return; }
                if (f.kind === 'claimtype') {
                    ok = f.v === '__group' ? String($tr.data('group')) === '1' || $tr.find('.btn-pax-pill').length > 0
                        : String($tr.data('claim') || $.trim(cell($tr, f.col).find('a').first().text())).toLowerCase() === f.v;
                } else if (f.kind === 'expid') {
                    ok = String($tr.data('exp') || cell($tr, f.col).text().replace(/\D/g, '')).indexOf(f.v) !== -1;
                } else if (f.kind === 'date') {
                    ok = rowDateIso($tr, f.col) === f.v;
                } else if (f.kind === 'amount') {
                    var a = cellAmount($tr, f.col);
                    ok = a !== null && Math.abs(a - f.v) < 0.005;
                } else if (f.kind === 'diff') {
                    var am = rowAmounts($tr, cols);
                    if (am.c === null || am.v === null) { ok = false; }
                    else if (f.v === '-') { ok = am.v < am.c - 0.005; }
                    else if (f.v === '+') { ok = am.v > am.c + 0.005; }
                    else { ok = Math.abs(am.v - am.c) < 0.005; }
                } else if (f.kind === 'status') {
                    ok = cellText($tr, f.col).indexOf(f.v) !== -1;
                } else {
                    ok = cellText($tr, f.col).indexOf(f.v) !== -1;
                }
            });
            $tr.toggle(ok);
            if (!ok) {
                $tr.find('input[type=checkbox]').prop('checked', false).trigger('change');
                var $pax = $tr.next('.pax-detail-row'); if ($pax.length) { $pax.hide(); }
            } else { shown++; }
        });
        var $box = $table.closest('.modal-body');
        $box.find('.apr-filter-count').text(filters.length ? ('Showing ' + shown + ' of ' + total) : '');
        $box.find('.apr-filter-clear').toggle(filters.length > 0);
    }

    window.xeasyApproverFilters = {
        /** Call after the table is drawn into the container. */
        attach: function (container) {
            var $table = $(container).find('table.day-tbl').first();
            if (!$table.length || $table.find('.apr-filter-row').length) { return; }
            var cols = columns($table);
            $table.find('thead').append(filterRow($table, cols));
            $table.on('input change', '.apr-f', function () { apply($table, cols); });
            var $bar = $table.closest('.modal-body').find('.apr-bulk-bar');
            $bar.append('<small class="ms-auto text-muted apr-filter-count"></small>'
                + '<button type="button" class="btn btn-sm btn-link apr-filter-clear p-0 ms-2" style="display:none;">Clear filters</button>');
            $bar.on('click', '.apr-filter-clear', function () {
                $table.find('.apr-f').each(function () { $(this).val('').removeClass('is-invalid'); });
                apply($table, cols);
            });
        }
    };
})();
