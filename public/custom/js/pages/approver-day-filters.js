/* APPR-FILTER-01 (13 Nov 2026): column filters on the approver's day-wise claim table.
 * A filter row under the header (text boxes; drop-downs for Diff and Status) hides the rows that do not
 * match; a claim's participant (PAX) row follows its claim. Ticked rows that get hidden are unticked, so
 * "Approve selected" only ever approves what is on screen. */
(function () {
    if (window.xeasyApproverFilters) { return; }
    // column index => kind ('text' | 'diff' | 'status'); 0 = tick box, 1 = #, 4 = attachments, 13 = action
    var COLS = { 2: 'text', 3: 'text', 5: 'text', 6: 'text', 7: 'diff', 8: 'text', 9: 'num', 11: 'text', 12: 'status' };

    function filterRow(ncols) {
        var html = '<tr class="apr-filter-row" style="background:#f8fafc;">';
        for (var i = 0; i < ncols; i++) {
            var k = COLS[i];
            if (k === 'diff') {
                html += '<th class="p-1"><select class="form-select form-select-sm apr-f" data-col="' + i + '" data-kind="diff" aria-label="Filter difference"><option value="">All</option><option value="-">Deducted</option><option value="+">Added</option><option value="0">No change</option></select></th>';
            } else if (k === 'status') {
                html += '<th class="p-1"><select class="form-select form-select-sm apr-f" data-col="' + i + '" data-kind="status" aria-label="Filter status"><option value="">All</option><option value="pending">Pending</option><option value="done">Done</option></select></th>';
            } else if (k) {
                html += '<th class="p-1"><input type="search" class="form-control form-control-sm apr-f" data-col="' + i + '" data-kind="' + k + '" placeholder="Filter" aria-label="Filter column"></th>';
            } else {
                html += '<th class="p-1"></th>';
            }
        }
        return html + '</tr>';
    }

    function cellText($tr, i) {
        var $td = $tr.children('td').eq(i);
        var $in = $td.find('input[type=number],input[type=text]');
        return ($in.length ? $in.val() : $td.text()).toString().toLowerCase().trim();
    }

    function apply($table) {
        var filters = $table.find('.apr-f').map(function () {
            var v = String($(this).val() || '').toLowerCase().trim();
            return v ? { col: +$(this).data('col'), kind: $(this).data('kind'), v: v } : null;
        }).get();
        var shown = 0, total = 0;
        $table.find('tbody > tr').not('.pax-detail-row').each(function () {
            var $tr = $(this), ok = true;
            total++;
            filters.forEach(function (f) {
                if (!ok) { return; }
                var t = cellText($tr, f.col);
                if (f.kind === 'diff') { ok = f.v === '0' ? (t === '-' || t === '') : t.charAt(0) === f.v; }
                else if (f.kind === 'status') { ok = t.indexOf(f.v) !== -1; }
                else { ok = t.replace(/[₹,]/g, '').indexOf(f.v.replace(/[₹,]/g, '')) !== -1; }
            });
            $tr.toggle(ok);
            if (!ok) {
                $tr.find('input[type=checkbox]').prop('checked', false).trigger('change');
                var $pax = $tr.next('.pax-detail-row'); if ($pax.length) { $pax.hide(); }
            } else { shown++; }
        });
        $table.closest('.modal-body').find('.apr-filter-count').text(filters.length ? ('Showing ' + shown + ' of ' + total) : '');
    }

    window.xeasyApproverFilters = {
        /** Call after the table is drawn into the container. */
        attach: function (container) {
            var $table = $(container).find('table.day-tbl').first();
            if (!$table.length || $table.find('.apr-filter-row').length) { return; }
            var ncols = $table.find('thead tr').first().children('th').length;
            $table.find('thead').append(filterRow(ncols));
            $table.on('input change', '.apr-f', function () { apply($table); });
            $table.closest('.modal-body').find('.apr-bulk-bar').append('<small class="ms-auto text-muted apr-filter-count"></small>');
        }
    };
})();
