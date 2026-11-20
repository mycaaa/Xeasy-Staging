{{-- BTC-V2 (19 Nov 2026): the BTC case window, shared by the BTC Request page and the Finance screens (Bill Upload,
     Data Extraction, Verifier 1, Finance Approval, Treasury). Needs: $statuses, $tdsSections, $tdsNatures,
     $tdsNatureByCategory, $vendors, $vendorTypesByCategory, $showFlow (BtcController::caseWindowData()). --}}
<style>
    .btc-step { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eef2ff; margin: 2px 0; font-size: 11px; }
    .btc-step.done { background: #dcfce7; } .btc-step.now { background: #dde6ff; color: #1e3a8a; }
    .btc-log { font-size: 11px; border-left: 2px solid #c7d2fe; padding-left: 8px; margin-bottom: 6px; }
    .btc-label { font-size: 11px; color: #6b7280; margin-bottom: 0; }
    .btc-val { font-size: 13px; }
    #btcCaseModal .modal-xl { max-width: min(96vw, 1600px); }
</style>
{{-- Case detail / actions --}}
<div class="modal fade" id="btcCaseModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down"><div class="modal-content">
        <div class="modal-header py-2"><h6 class="modal-title" id="btcCaseTitle">Case</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="btcCaseBody"></div>
    </div></div>
</div>
@push('scripts')
<script>
$(function () {
    const CSRF = $('meta[name="csrf-token"]').attr('content');
    const BTC_VENDORS = @json($vendors);
    const BTC_VENDOR_TYPES = @json($vendorTypesByCategory);
    const READER = @json((bool) config('btc.reader.enabled', true) && \Illuminate\Support\Facades\Route::has('btc.reader.read'));
    // BTC-READER-01: fill the empty bill fields from the invoice (chosen file, else the bill attached to the case)
    $(document).on('click', '.btc-read-inv', function () {
        const $f = $(this).closest('form'), $b = $(this), $m = $f.find('.btc-read-msg');
        const fd = new FormData(); fd.append('case_id', $f.data('case') || '');
        $f.find('input[name="file_kinds[]"]').each(function () { if (this.value === 'bill') { const fi = $(this).next('input[type=file]')[0]; if (fi && fi.files[0]) fd.append('file', fi.files[0]); } });
        $b.prop('disabled', true); $m.removeClass('text-danger text-success').addClass('text-muted').text('Reading the invoice…');
        $.ajax({url: @json(\Illuminate\Support\Facades\Route::has('btc.reader.read') ? route('btc.reader.read') : ''), method: 'POST', data: fd, processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
            .done(function (r) {
                if (!r.success) { $m.removeClass('text-muted').addClass('text-danger').text(r.message || 'Could not read the invoice; enter the details.'); return; }
                const filled = [], kept = [];
                Object.entries(r.fields || {}).forEach(([k, v]) => { const $i = $f.find('[name="' + k + '"]'); if (!$i.length) return; if (String($i.val() || '').trim() === '' || $i.val() === '0') { $i.val(v); filled.push(k.replace(/_/g, ' ')); } else if (String($i.val()) !== String(v)) { kept.push(k.replace(/_/g, ' ') + ' (invoice: ' + v + ')'); } });
                $m.removeClass('text-muted').addClass('text-success').text((filled.length ? 'Filled: ' + filled.join(', ') + '. ' : 'Nothing new to fill. ') + (kept.length ? 'Kept what you typed for ' + kept.join('; ') + '. ' : '') + 'Check before saving.');
            })
            .fail(function (x) { $m.removeClass('text-muted').addClass('text-danger').text((x.responseJSON && (x.responseJSON.message)) || 'Could not read the invoice; enter the details.'); })
            .always(function () { $b.prop('disabled', false); });
    });
    const STATUS = @json($statuses);
    const TDS = @json($tdsSections);
    const NATURES = @json($tdsNatures ?? []); // BTC-TDS-01
    const NATURE_BY_CAT = @json($tdsNatureByCategory ?? []);
    const fmt = v => (v === null || v === undefined || v === '') ? '' : Number(v).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = s => $('<div>').text(s ?? '').html();
    // BTC-AMT-01: amounts up to 99,99,999.99 with two decimals; advance not above the quotation
    const BTC_AMT_MAX = 9999999.99;
    function amtError(el) {
        const v = String(el.value || '').trim();
        if (v === '') return '';
        if (!/^\d+(\.\d{1,2})?$/.test(v)) return 'Enter an amount with up to two decimals.';
        if (parseFloat(v) > BTC_AMT_MAX) return 'Amount cannot be more than ₹99,99,999.99.';
        return '';
    }
    function checkAmounts($form) {
        let ok = true;
        $form.find('input[type=number]').filter((i, el) => /amount|paid|adjusted|tds_on/.test(el.name)).each(function () {
            let msg = amtError(this);
            if (!msg && this.name === 'advance_amount') {
                const q = parseFloat($form.find('[name=quotation_amount]').val() || '0'), adv = parseFloat(this.value || '0');
                if (q > 0 && adv > q) msg = 'Advance needed cannot be more than the quotation amount.';
            }
            $(this).toggleClass('is-invalid', !!msg).next('.invalid-feedback').remove();
            if (msg) { $(this).after('<div class="invalid-feedback">' + msg + '</div>'); ok = false; }
        });
        return ok;
    }
    $(document).on('input', '#btcActForm input[type=number]', function () {
        $(this).attr({min: 0, max: BTC_AMT_MAX});
        const $f = $(this).closest('form'); const ok = checkAmounts($f);
        $f.find('#btcDo').prop('disabled', !ok);
    });
    // case detail
    const SHOW_FLOW = @json($showFlow);   // BTC-REQ-08
    // BTC-V2 (19 Nov 2026): the revised stages
    const FLOW_A = ['booked', 'bill_with_dms', 'bill_with_finance'];
    const FLOW = ['bill_submitted', 'extracted', 'finance_verified', 'focus_done', 'fin_approved', 'paid', 'closed'];
    const kv = (l, v) => v ? `<div class="col-md-3"><p class="btc-label">${l}</p><div class="btc-val">${v}</div></div>` : '';
    // BTC-FIN-02: Dr/Cr lines as a table
    const journalTable = (lines) => `<table class="table table-sm table-bordered small mb-0"><tr class="table-light"><th>Ledger</th><th>Sub-ledger</th><th class="text-end">Debit ₹</th><th class="text-end">Credit ₹</th><th>TDS</th></tr>${lines.map(l => `<tr><td>${esc(l.ledger)}</td><td>${esc(l.sub)}</td><td class="text-end">${l.dr ? fmt(l.dr) : ''}</td><td class="text-end">${l.cr ? fmt(l.cr) : ''}</td><td>${esc(l.tds)}</td></tr>`).join('')}<tr class="fw-semibold"><td colspan="2">Total</td><td class="text-end">${fmt(lines.reduce((s, l) => s + Number(l.dr || 0), 0))}</td><td class="text-end">${fmt(lines.reduce((s, l) => s + Number(l.cr || 0), 0))}</td><td></td></tr></table>`;
    // BTC-REQ-19 (17 Nov 2026): vendor chosen from the vendor list (searchable), or a new name typed
    const vendorPick = (c) => {
        const want = BTC_VENDOR_TYPES[c.category] || [];
        const list = BTC_VENDORS.filter(v => !want.length || v.types.some(t => want.includes(t)));
        return `<div class="col-md-5"><label class="btc-label">Vendor / hotel (from the vendor list) <span class="text-danger">*</span></label><select class="form-select form-select-sm btc-vpick" name="vendor_id"><option value="">— not listed: type the name —</option>${list.map(v => `<option value="${v.id}" ${(c.vendor_id && String(v.id) === String(c.vendor_id)) || (v.code && v.code === c.vendor_code) ? 'selected' : ''}>${esc(v.name + (v.gst ? ' · ' + v.gst : ''))}</option>`).join('')}</select></div>
            <div class="col-md-4"><label class="btc-label">or vendor name <span class="text-danger">*</span> (<a href="https://connect.vnrin.in/register" target="_blank" rel="noopener">register</a>)</label><input class="form-control form-control-sm" name="vendor_name" value="${c.vendor_id ? '' : esc(c.vendor_name || '')}"></div>`;
    };
    // BTC-V2 Step 2: the Finance data format, filled from the invoice; correct what was read wrongly
    // BTC-SCAN-01 (19 Nov 2026): the punch form of the bill type - the fields as printed on the bill
    function punchForm(c, d) {
        const P = d.punch; if (!P) return '';
        const cells = Object.entries(P.fields).map(([k, l]) => {
            const n = (P.numbers || []).includes(k), date = /Date$/.test(k);
            let v = P.values[k] || ''; if (date && /^\d{4}-\d{2}-\d{2}/.test(v)) v = v.substring(0, 10);
            return `<div class="col-md-3 col-lg-2"><label class="btc-label">${esc(l)}</label><input class="form-control form-control-sm" name="punch[${esc(k)}]" type="${date && /^\d{4}-\d{2}-\d{2}$/.test(v || '2000-01-01') ? 'date' : (n ? 'number' : 'text')}" ${n ? 'step="0.01"' : ''} value="${esc(v)}"></div>`;
        }).join('');
        return `<div class="col-12"><div class="fw-semibold small mt-1">Punch form: ${esc(P.title)} <span class="text-muted fw-normal">- as printed on the bill${P.saved ? '' : ' (filled from the bill reader; check each field)'}</span></div></div>${cells}
            <div class="col-12"><div class="fw-semibold small mt-2">Finance data <span class="text-muted fw-normal">- fields left empty are filled from the punch form when saved</span></div></div>`;
    }
    // BTC-SCAN-01: Finance level verification - each field checked against the hard copy
    function verifyChecks(c, d) {
        const F = d.extract_fields || {}, num = ['taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'other_charges', 'bill_amount'];
        const rows = Object.entries(F).map(([k, l]) => `<tr><td class="text-muted">${esc(l)}</td><td>${num.includes(k) ? fmt(c[k]) : esc(k === 'bill_date' ? (c[k] || '').substring(0, 10) : c[k])}</td>
            <td class="text-nowrap"><label class="me-2"><input type="radio" name="checks[${k}]" value="ok"> Matches</label><label class="text-danger"><input type="radio" name="checks[${k}]" value="diff"> Differs</label></td></tr>`).join('');
        return `<div class="col-12"><table class="table table-sm table-bordered small mb-1"><tr class="table-light"><th>Field</th><th>Punched</th><th>Hard copy <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="$(this).closest('table').find('input[value=ok]').prop('checked', true)">all match</button></th></tr>${rows}</table>
            <input type="hidden" name="checks_form" value="1"><label class="small"><input type="checkbox" name="hard_copy_seen" value="1"> I have checked the received hard copy of the bill</label></div>`;
    }
    function extractFields(c, d) {
        const F = d.extract_fields || {}, read = (d.extraction && d.extraction.read) || {};
        const num = ['taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'other_charges', 'bill_amount'];
        const out = Object.entries(F).map(([k, l]) => {
            const v = k === 'bill_date' ? (c[k] || '').substring(0, 10) : (c[k] ?? '');
            const r = read[k] !== undefined && String(read[k]) !== String(v) ? `<div class="small text-muted">read: ${esc(read[k])}</div>` : '';
            const w = k === 'bill_particulars' ? 'col-md-6' : (k === 'vendor_name' ? 'col-md-4' : 'col-md-2');
            return `<div class="${w}"><label class="btc-label">${esc(l)}</label><input class="form-control form-control-sm" name="${k}" type="${num.includes(k) ? 'number' : (k === 'bill_date' ? 'date' : 'text')}" ${num.includes(k) ? 'step="0.01"' : ''} value="${esc(v)}">${r}</div>`;
        }).join('');
        const dup = (d.duplicates || []).length ? `<div class="col-12 text-danger small">Possible duplicate invoice: ${d.duplicates.map(x => esc(x.case_no + ' (' + x.bill_no + ', ₹' + fmt(x.bill_amount) + ')')).join(', ')}</div>` : '';
        return out + dup + `<div class="col-12"><button type="button" class="btn btn-sm btn-outline-success" id="btcReread"><i class="ri-scan-2-line"></i> Read the invoice again</button> <span class="small text-muted" id="btcRereadMsg">Only empty fields are filled.</span></div>`;
    }
    function actionForm(a, d) {
        const c = d.case, f = [];
        const dl = (k, l, v, opts) => `<div class="col-md-3"><label class="btc-label">${l}</label><input class="form-control form-control-sm" name="dim_${k}" value="${esc(v || '')}" list="btcDl_${k}"><datalist id="btcDl_${k}">${(opts || []).map(o => `<option value="${esc(o)}">`).join('')}</datalist></div>`;
        const inp = (n, l, t = 'text', v = '') => `<div class="col-md-3"><label class="btc-label">${l}</label><input class="form-control form-control-sm" type="${t}" name="${n}" value="${esc(v)}"></div>`;
        const rmk = (req) => `<div class="col-md-6"><label class="btc-label">Remark${req ? ' (required)' : ''}</label><input class="form-control form-control-sm" name="remark"></div>`;
        const file = (k, l) => `<div class="col-md-4"><label class="btc-label">${l}</label><input type="hidden" name="file_kinds[]" value="${k}"><input type="file" class="form-control form-control-sm" name="files[]" accept=".pdf,.jpg,.jpeg,.png"></div>`;
        // BTC-READER-01 (18 Nov 2026): read the chosen (or attached) invoice and fill the bill fields
        const readInv = () => READER ? `<div class="col-12"><button type="button" class="btn btn-sm btn-outline-success btc-read-inv"><i class="ri-scan-2-line"></i> Read invoice and fill the fields</button> <span class="small text-muted btc-read-msg">Choose the bill file first, or it reads the bill already attached.</span></div>` : '';
        const today = () => new Date().toISOString().substring(0, 10);
        switch (a) {
            case 'submit':
                // BTC-REQ-13 (17 Nov 2026): attach the quotation from the case itself
                if (c.route === 'employee_advance') f.push(inp('quotation_amount', 'Quotation amount ₹', 'number', c.quotation_amount), inp('advance_amount', 'Advance required ₹', 'number', c.advance_amount), file('quotation', 'Quotation' + ((d.files || []).some(x => x.kind === 'quotation') ? ' (one attached; add another)' : '')));
                else f.push(file('supporting', 'Attach a file (optional)'));
                f.push(rmk(false)); break;
            case 'manager_approve': if (c.route === 'employee_advance' && Number(c.advance_amount) > 0) f.push(inp('advance_approved', 'Advance approved ₹ (requested ₹' + fmt(c.advance_amount) + ')', 'number', c.advance_amount)); f.push(rmk(false)); break;
            case 'change_vendor': f.push(vendorPick(c), rmk(true)); break;   // BTC-REQ-19
            case 'confirm_booking': case 'modify_booking': f.push(vendorPick(c),
                inp('booking_ref', 'Booking reference', 'text', c.booking_ref), inp('booking_amount', 'Booking amount ₹ (0 allowed)', 'number', c.booking_amount ?? (c.estimated_amount || c.quotation_amount || 0)),
                ...(Number(c.advance_paid_amount) > 0 ? [] : [inp('advance_amount', 'Advance to vendor ₹ (0 = none)', 'number', c.advance_amount || 0)]),   // BTC-REQ-27 inp('booking_date', 'Booking date', 'date', (c.booking_date || '').substring(0, 10)),
                inp('service_from', 'Activity from', 'date', (c.service_from || '').substring(0, 10)), inp('service_to', 'Activity to', 'date', (c.service_to || '').substring(0, 10)),
                ...(c.category === 'air' ? [inp('pnr', 'PNR', 'text', c.pnr), inp('travel_sector', 'Sector', 'text', c.travel_sector), inp('travel_date', 'Travel date', 'date', (c.travel_date || '').substring(0, 10))] : []),
                file('supporting', 'Booking confirmation'), rmk(a === 'modify_booking')); break;
            case 'cancel_booking': f.push(rmk(true)); break;
            case 'forward_to_dms': f.push(inp('bill_forwarded_dms_on', 'Physical bill handed over to DMS on', 'date', today()), inp('bill_no', 'Bill no. (if known)', 'text', c.bill_no), inp('bill_amount', 'Bill total ₹ (if known)', 'number', c.bill_amount), rmk(false)); break;   // BTC-V2
            case 'complete_activity': f.push(inp('activity_completed_on', 'Activity completed on', 'date'), rmk(false)); break;
            case 'record_refund': f.push(inp('advance_refund', 'Refund received ₹ (balance ₹' + fmt(d.advance.balance) + ')', 'number'), inp('advance_refund_on', 'Received on', 'date'), rmk(false)); break;
            case 'manager_reject': case 'cancel': f.push(rmk(true)); break;
            case 'advance_paid': f.push(inp('advance_paid_amount', 'Actual advance paid ₹ (approved ₹' + fmt(c.advance_approved ?? c.advance_amount) + ')', 'number', c.advance_approved ?? c.advance_amount), inp('advance_payment_ref', 'Payment reference / UTR'), inp('advance_paid_on', 'Advance paid on', 'date'), inp('advance_activity_id', 'Xeasy Advance ID (if raised there)', 'number'), inp('tds_on_advance', 'TDS deducted on advance ₹', 'number', 0)); break;
            case 'booked': f.push(rmk(false)); break;
            case 'submit_bill': f.push(`<div class="col-12 small text-muted">Upload the bill (PDF or photo). Xeasy reads it and sends it to Finance.</div>`, file('bill', 'Final bill'), file('supporting', 'Supporting'));   // BTC-V2: the data is read from the bill
                if (c.category === 'air') f.push(inp('pnr', 'PNR', 'text', c.pnr), inp('travel_sector', 'Sector', 'text', c.travel_sector), inp('travel_date', 'Travel date', 'date', (c.travel_date || '').substring(0, 10)), file('boarding_pass', 'Boarding pass (if available)')); break;
            case 'focus_entry': {   // BTC-V2: the Focus entry on the Verifier 1 screen
                const dup = (d.duplicates || []).length ? `<div class="col-12 text-danger small">Possible duplicate: ${d.duplicates.map(x => esc(x.case_no + ' (' + x.bill_no + ', ₹' + fmt(x.bill_amount) + ')')).join(', ')} <label class="ms-2"><input type="checkbox" name="duplicate_override" value="1"> not a duplicate</label></div>` : '';
                f.push(inp('expense_ledger', 'Expense ledger', 'text', c.expense_ledger || d.default_ledger), inp('cost_centre', 'Cost centre', 'text', c.cost_centre || c.department_name), inp('taxable_amount', 'Taxable ₹', 'number', c.taxable_amount || c.bill_amount),
                    `<div class="col-md-5"><label class="btc-label">Nature of payment (decides TDS)</label><select class="form-select form-select-sm" name="tds_nature" onchange="(function(s){const n=NATURES[s.value]; const r=s.form.querySelector('[name=tds_rate]'); if(n&&r){ const pan=(${JSON.stringify(c.vendor_gstin || '')}).toUpperCase(); const ind=pan.length===15&&['P','H'].includes(pan[5]); r.value=n.rate[ind?0:1]; }})(this)">${Object.entries(NATURES).map(([k, n]) => `<option value="${k}" ${k === (c.tds_nature || NATURE_BY_CAT[c.category] || 'none') ? 'selected' : ''}>${n.label}</option>`).join('')}</select></div>`,
                    inp('tds_rate', 'TDS rate %', 'number', c.tds_rate || 0), inp('advance_adjusted', 'Advance to set off ₹ (paid ₹' + fmt(c.advance_amount) + ', TDS on it ₹' + fmt(c.tds_on_advance) + ')', 'number', c.advance_amount || 0),
                    dup,
                    // BTC-DUP-02 / TDS-02 / GST-01 (17 Nov 2026)
                    (d.claim_overlaps || []).length ? `<div class="col-12 text-danger small">Also in the employee's own expense claim: ${d.claim_overlaps.map(o => esc(o.type + ' ' + o.date + ' ₹' + fmt(o.amount) + ' (' + o.status + ', ExpId ' + o.exp_id + ')')).join('; ')} <label class="ms-2"><input type="checkbox" name="claim_overlap_override" value="1"> not the same expense</label></div>` : '',
                    d.vendor_year ? `<div class="col-12 small text-muted">This vendor in FY ${esc(d.vendor_year.fy)} (other bills): ₹${fmt(d.vendor_year.total)} in ${d.vendor_year.count} bill(s). <label class="ms-2"><input type="checkbox" name="tds_threshold_override" value="1"> TDS not applicable (give remark)</label></div>` : '',
                    // BTC-FIN-02 (17 Nov 2026): accounting dimensions, narration, JV numbers and a Dr/Cr preview
                    ...(d.dims ? [dl('function', 'Function', c.dim_function || d.dims.defaults.function, d.dims.options.function), dl('vertical', 'Vertical', c.dim_vertical || d.dims.defaults.vertical, d.dims.options.vertical),
                        dl('region', 'Region', c.dim_region || d.dims.defaults.region, d.dims.options.region), dl('crop', 'Crop', c.dim_crop, d.dims.options.crop),
                        inp('dim_activity', 'Activity', 'text', c.dim_activity), inp('jv_no', 'JV no.', 'text', c.jv_no), inp('tds_jv_no', 'TDS JV no.', 'text', c.tds_jv_no),
                        `<div class="col-md-9"><label class="btc-label">Narration</label><input class="form-control form-control-sm" name="narration" maxlength="500" value="${esc(c.narration || '')}" placeholder="${esc(c.case_no + ' ' + (c.vendor_name || '') + ' bill ' + (c.bill_no || '') + ' for ' + c.emp_code + ' ' + c.emp_name)}"></div>`,
                        `<div class="col-12"><button type="button" class="btn btn-sm btn-outline-secondary" id="btcPreview"><i class="ri-eye-line"></i> Preview accounting (Dr / Cr)</button><div id="btcPreviewOut" class="mt-2"></div></div>`] : []),
                    d.gstin_ok === false ? `<div class="col-12 text-danger small">Vendor GSTIN ${esc(c.vendor_gstin)} is not a valid GSTIN (format / check digit) - check before claiming GST input.</div>` : '',
                    rmk(false));
                break;
            }
            case 'pay': {
                // BTC-PAY-01 (17 Nov 2026): mode, UTR, bank reference; a part payment leaves the rest due
                const due = Math.round((Number(c.net_payable || 0) - Number(c.paid_amount || 0)) * 100) / 100;
                f.push(inp('paid_amount', 'Amount paid now ₹ (due ₹' + fmt(due) + (Number(c.paid_amount) > 0 ? ', already paid ₹' + fmt(c.paid_amount) : '') + ')', 'number', due),
                    inp('paid_on', 'Paid on', 'date', new Date().toISOString().substring(0, 10)),
                    `<div class="col-md-3"><label class="btc-label">Mode</label><select class="form-select form-select-sm" name="payment_mode"><option value="">Choose</option>${Object.entries(d.payment_modes || {}).map(([k, l]) => `<option value="${k}">${esc(l)}</option>`).join('')}</select></div>`,
                    inp('utr_number', 'UTR / cheque no.'), inp('payment_bank_ref', 'Bank reference (optional)'), file('payment_proof', 'Payment advice'), rmk(false));
                break;
            }
            case 'payment_failed': f.push(`<div class="col-md-6"><label class="btc-label">Payment that failed / came back</label><select class="form-select form-select-sm" name="payment_id">${(d.payments || []).filter(p => p.status === 'success').map(p => `<option value="${p.id}">₹${fmt(p.amount)} · ${esc(p.on || '')} · ${esc(p.mode || '')} · UTR ${esc(p.utr || '')}</option>`).join('')}</select></div>`, rmk(true)); break;
            case 'waive_document': f.push(`<div class="col-md-6"><label class="btc-label">Waive (cannot be obtained)</label><div>${d.pending.map(p => { const k = p.startsWith('original') ? 'original' : 'boarding_pass'; return `<label class="me-3"><input type="checkbox" name="waive[]" value="${k}"> ${esc(p)}</label>`; }).join('')}</div></div>`, rmk(true)); break;
            case 'verify1_return': f.push(verifyChecks(c, d), `<div class="col-12 small text-muted">Mark the fields that differ; they are given as the reason if no remark is typed.</div>`, rmk(false)); break;
            case 'hold': case 'release_hold': case 'verify1_reject': case 'fin_reject': f.push(rmk(true)); break;
            case 'close': f.push(rmk(false)); break;
            case 'return': f.push(`<div class="col-12 small text-muted">Back to the requester to upload a correct bill.</div>`, rmk(true)); break;
            // BTC-V2 (19 Nov 2026): the revised steps
            case 'dms_receive': f.push(inp('dms_received_on', 'Physical bill received by DMS on', 'date', today()), inp('dms_to_finance_on', 'Handed over to Finance on', 'date', today()), inp('dms_doc_no', 'DMS entry no. (optional)', 'text', c.dms_doc_no), rmk(false)); break;
            case 'finance_upload': f.push(`<div class="col-md-3"><label class="btc-label">Bill received from</label><select class="form-select form-select-sm" name="bill_source">${Object.entries(d.bill_sources || {}).filter(([k]) => k !== 'employee').map(([k, l]) => `<option value="${k}" ${k === (c.route === 'admin_booking' ? 'dms' : 'vendor') ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></div>`, file('bill', 'Scanned invoice'), file('supporting', 'Supporting'), file('supporting', 'Supporting'), rmk(false)); break;
            case 'save_extraction': case 'confirm_extraction': f.push(punchForm(c, d), extractFields(c, d), rmk(false)); break;
            // BTC-SCAN-01: Finance Advance Approval and the scanned bill not to be processed
            case 'adv_fin_approve': f.push(`<div class="col-12 small">Advance ₹${fmt(c.advance_approved ?? c.advance_amount)} to ${esc(c.vendor_name || c.emp_name || '')}${c.quotation_amount ? ' (quotation ₹' + fmt(c.quotation_amount) + ')' : ''}.</div>`, rmk(false)); break;
            case 'adv_instruct': f.push(inp('adv_instruction_ref', 'Payment instruction ref. (optional)'), rmk(false)); break;
            case 'adv_fin_reject': case 'discard_scan': f.push(rmk(true)); break;
            case 'verify1': {
                const dup = (d.duplicates || []).length ? `<div class="col-12 text-danger small">Possible duplicate: ${d.duplicates.map(x => esc(x.case_no + ' (' + x.bill_no + ', ₹' + fmt(x.bill_amount) + ')')).join(', ')} <label class="ms-2"><input type="checkbox" name="duplicate_override" value="1"> not a duplicate</label></div>` : '';
                const ov = (d.claim_overlaps || []).length ? `<div class="col-12 text-danger small">Also in the employee's own expense claim: ${d.claim_overlaps.map(o => esc(o.type + ' ' + o.date + ' ₹' + fmt(o.amount) + ' (' + o.status + ', ExpId ' + o.exp_id + ')')).join('; ')} <label class="ms-2"><input type="checkbox" name="claim_overlap_override" value="1"> not the same expense</label></div>` : '';
                f.push(`<div class="col-12 small">Check every field with the received hard copy of the bill (and the scan on the left). Verify when all match; if a field differs, return it for correction.</div>`, verifyChecks(c, d), dup, ov, rmk(false));
                break;
            }
            case 'fin_approve': f.push(`<div class="col-12 small">Net payable ₹${fmt(c.net_payable)}${c.jv_no ? ' · JV ' + esc(c.jv_no) : ''}${c.focus_exported_at ? ' · Focus export ' + esc(c.focus_exported_at.substring(0, 10)) : ' · <span class="text-danger">Focus entry not exported yet</span>'}</div>`, rmk(false)); break;
            case 'fin_return': f.push(`<div class="col-md-4"><label class="btc-label">Return to</label><select class="form-select form-select-sm" name="return_to"><option value="finance_verified">Verifier 1 (Focus entry)</option><option value="bill_submitted">Data extraction (invoice data)</option></select></div>`, rmk(true)); break;
            case 'receive_document': f.push(`<div class="col-md-3"><label class="btc-label">Document</label><select class="form-select form-select-sm" name="document"><option value="original">Original bill</option><option value="boarding_pass">Boarding pass</option></select></div>`, inp('received_on', 'Received on', 'date'), file('original', 'Scan (optional)')); break;
        }
        return f.join('');
    }
    const LABEL = {change_vendor: 'Update vendor', forward_to_dms: 'Physical bill handed to DMS', dms_receive: 'Received by DMS, handed to Finance', confirm_booking: 'Confirm booking', modify_booking: 'Change booking', cancel_booking: 'Cancel booking', complete_activity: 'Activity completed', record_refund: 'Record refund', submit: 'Submit', manager_approve: 'Approve', manager_reject: 'Reject', advance_paid: 'Advance paid', booked: 'Mark booked', submit_bill: 'Upload bill', finance_upload: 'Upload scanned bill', save_extraction: 'Save punch', confirm_extraction: 'Save punch, send to Finance verification', verify1: 'Verify', adv_fin_approve: 'Approve advance', adv_fin_reject: 'Reject advance', adv_instruct: 'Instruct payment', discard_scan: 'Not a BTC bill / scanned twice', verify1_return: 'Return for correction', verify1_reject: 'Reject', focus_entry: 'Save Focus entry, send for approval', fin_approve: 'Approve', fin_return: 'Return', fin_reject: 'Reject', pay: 'Record payment', close: 'Close', payment_failed: 'Payment failed / returned', waive_document: 'Waive document', hold: 'Put on hold', release_hold: 'Release hold', return: 'Return to requester', receive_document: 'Document received', cancel: 'Cancel case'};

    // BTC-V2: the extracted invoice data in the Finance data format (with what the reader found)
    function extractTable(c, d) {
        const F = d.extract_fields || {}, x = d.extraction || {};
        if (!x.status && !c.bill_no) return '';
        const st = {read: 'Read', partial: 'Partly read', failed: 'Not read', corrected: 'Corrected by Finance', pending: 'Being read'}[x.status] || '';
        const cells = Object.entries(F).map(([k, l]) => `<tr><td class="text-muted">${esc(l)}</td><td>${['taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'other_charges', 'bill_amount'].includes(k) ? fmt(c[k]) : esc(k === 'bill_date' ? (c[k] || '').substring(0, 10) : c[k])}</td></tr>`).join('');
        const P = d.punch, pv = P && P.saved ? Object.entries(P.fields).filter(([k]) => (P.values[k] || '') !== '') : [];
        const punched = pv.length ? `<p class="btc-label">Punched (${esc(P.title)})</p><table class="table table-sm table-bordered small mb-2"><tr>${pv.map(([k, l]) => `<td><span class="text-muted">${esc(l)}</span><br>${esc(P.values[k])}</td>`).join('')}</tr></table>` : '';
        return punched + `<div class="mb-2"><p class="btc-label">Extracted invoice data ${st ? '<span class="badge bg-light text-dark">' + st + '</span>' : ''} ${x.note ? '<span class="text-muted small">' + esc(x.note) + '</span>' : ''}</p>
            <table class="table table-sm table-bordered small mb-1">${cells}</table>${(x.missing || []).length ? '<div class="text-danger small">Required, still empty: ' + esc(x.missing.join(', ')) + '</div>' : ''}${x.breakup ? '<div class="text-danger small">' + esc(x.breakup) + '</div>' : ''}</div>`;
    }
    function openCase(id) {
        $.get('{{ url('btc/case') }}/' + id, function (d) {
            const c = d.case, steps = (c.route === 'admin_booking' ? FLOW_A : (c.route === 'direct_bill' ? ['scanned'] : [])).concat(FLOW), at = steps.indexOf(c.status);
            $('#btcCaseTitle').text(c.case_no + ' · ' + d.status_label);
            const flow = steps.map((s, i) => `<span class="btc-step ${i < at ? 'done' : (i === at ? 'now' : '')}">${STATUS[s]}</span>`).join(' › ');
            const files = d.files.map(f => `<a class="me-3" target="_blank" href="${f.url}"><i class="ri-file-line"></i> ${esc(f.kind.replace('_', ' '))}: ${esc(f.name)}</a>`).join('') || '<span class="text-muted">none</span>';
            const logs = d.logs.map(l => `<div class="btc-log"><b>${esc(l.at)}</b> ${esc(l.by)} · ${esc(l.action.replace(/_/g, ' '))} → ${esc(l.to)}${l.remark ? '<br>' + esc(l.remark) : ''}</div>`).join('');
            const acts = d.actions.map(a => `<button class="btn btn-sm ${['return', 'manager_reject', 'cancel', 'discard_scan', 'adv_fin_reject', 'verify1_return', 'verify1_reject', 'fin_return', 'fin_reject', 'payment_failed', 'hold'].includes(a) ? 'btn-outline-danger' : 'btn-outline-primary'} me-1 mb-1" data-act="${a}">${LABEL[a] || a}</button>`).join('');
            const body = `${SHOW_FLOW ? '<div class="mb-2">' + flow + '</div>' : ''}
                <div class="row g-2 mb-2">${kv('Route', esc(d.route_label))}${kv('Category', esc(d.category_label))}${kv('Employee', esc(c.emp_code + ' - ' + c.emp_name))}${kv('Department', esc(c.department_name))}
                ${kv('Purpose', esc(c.purpose))}${c.request_remarks ? kv('Remarks', esc(c.request_remarks)) : ''}${kv('Vendor', esc(c.vendor_name) + (c.new_vendor ? ' (new)' : ''))}${kv('Service', esc((c.service_from || '').substring(0, 10) + ' – ' + (c.service_to || '').substring(0, 10)))}${kv('Manager', esc(d.manager))}
                ${kv('Quotation ₹', fmt(c.quotation_amount))}${kv('Advance ₹', fmt(c.advance_amount))}${kv('Bill', esc((c.bill_no || '') + ' ' + (c.bill_date || '').substring(0, 10)))}${kv('Bill ₹', fmt(c.bill_amount))}
                ${kv('TDS ₹', fmt(c.tds_amount))}${kv('Net payable ₹', fmt(c.net_payable))}${kv('PNR / sector', esc((c.pnr || '') + ' ' + (c.travel_sector || '')))}${kv('Bill source', esc((d.stamps || {}).source))}${kv('Uploaded', esc((d.stamps || {}).uploaded))}${kv('DMS', esc((d.stamps || {}).dms))}${kv('Verifier 1', esc((d.stamps || {}).verify1))}${kv('Focus entry', esc((d.stamps || {}).focus))}${kv('Finance approval', esc((d.stamps || {}).approved))}
                ${kv('Paid', c.paid_amount ? '₹' + fmt(c.paid_amount) + ' on ' + esc((c.paid_on || '').substring(0, 10)) + ' · ' + esc(c.utr_number) : '')}${kv('Waiting for', esc(d.pending.join(', ')))}
                ${c.pax_count ? kv('Pax', esc(String(c.pax_count))) : ''}${c.travellers ? kv('Employees', esc((() => { try { return JSON.parse(c.travellers).map(t => t.code + ' ' + t.name).join(', '); } catch (e) { return ''; } })())) : ''}${c.guests ? kv('Guests', esc(c.guests)) : ''}${c.bill_forwarded_dms_on ? kv('Bill to DMS', esc(c.bill_forwarded_dms_on.substring(0, 10))) : ''}${kv('Pending with', esc(d.pending_with))}${kv('Estimated ₹', fmt(c.estimated_amount))}${kv('Booking', c.booking_ref ? esc(c.booking_ref + ' · ₹' + fmt(c.booking_amount) + ' · ' + (c.booking_date || '').substring(0, 10) + ' · ' + (c.booking_status || '')) : '')}${kv('Activity completed', esc((c.activity_completed_on || '').substring(0, 10)))}</div>
                ${Number(d.advance.requested) > 0 ? `<div class="mb-2"><p class="btc-label">Advance</p><table class="table table-sm table-bordered small mb-0"><tr class="table-light"><th>Requested</th><th>Approved</th><th>Actually paid</th><th>TDS on advance</th><th>Outstanding</th><th>Adjusted against invoice</th><th>Refund received</th><th>Balance outstanding</th><th>Finance approval</th></tr>
                    <tr><td>${fmt(d.advance.requested)}</td><td>${d.advance.approved === null ? '-' : fmt(d.advance.approved)}</td><td>${fmt(d.advance.paid)}${c.advance_payment_ref ? '<div class="text-muted">' + esc(c.advance_payment_ref) + '</div>' : ''}</td><td>${fmt(d.advance.tds)}</td><td>${fmt(d.advance.outstanding)}</td><td>${fmt(d.advance.adjusted)}</td><td>${fmt(d.advance.refund)}</td><td class="fw-semibold">${fmt(d.advance.balance)}</td><td>${esc(({approved: 'Approved', instructed: 'Instructed', rejected: 'Rejected'})[(d.adv || {}).stage] || (c.status === 'advance_requested' ? 'Pending' : '-'))}${(d.adv || {}).approved ? '<div class="text-muted">' + esc(d.adv.approved) + '</div>' : ''}${(d.adv || {}).instructed ? '<div class="text-muted">Instructed ' + esc(d.adv.instructed) + (d.adv.ref ? ' · ' + esc(d.adv.ref) : '') + '</div>' : ''}</td></tr></table></div>` : ''}
                ${(d.exceptions || []).length ? `<div class="alert alert-warning py-1 px-2 small mb-2"><b>Exceptions:</b><ul class="mb-0">${d.exceptions.map(e => '<li><b>' + esc(e.label) + ':</b> ' + esc(e.detail) + '</li>').join('')}</ul><a href="{{ route('btc.exceptions') }}">Exception Queue</a></div>` : ''}
                ${(d.journal || []).length ? `<div class="mb-2"><p class="btc-label">Accounting (Focus JV${c.jv_no ? ' ' + esc(c.jv_no) : ''})</p>${journalTable(d.journal)}${[c.dim_function, c.dim_vertical, c.dim_region, c.dim_activity, c.dim_crop].filter(Boolean).length ? '<div class="small text-muted">' + esc([c.dim_function, c.dim_vertical, c.dim_region, c.dim_activity, c.dim_crop].filter(Boolean).join(' · ')) + '</div>' : ''}</div>` : ''}
                ${c.on_hold ? `<div class="alert alert-danger py-1 px-2 small mb-2"><b>Financial hold:</b> ${esc(c.hold_reason || '')} - payment, approval and closure are blocked until released.</div>` : ''}
                ${(d.claim_overlaps || []).length ? `<div class="alert alert-warning py-1 px-2 small mb-2"><b>Also claimed by the employee:</b> ${d.claim_overlaps.map(o => esc(o.type + ' ' + o.date + ' ₹' + fmt(o.amount) + ' (' + o.status + ')')).join('; ')}</div>` : ''}
                ${(d.overdue_advances || []).length ? `<div class="alert alert-warning py-1 px-2 small mb-2"><b>Unsettled earlier advance(s):</b> ${d.overdue_advances.map(esc).join(', ')}</div>` : ''}
                ${(d.payments || []).length ? `<div class="mb-2"><p class="btc-label">Payments</p><table class="table table-sm table-bordered small mb-0"><tr class="table-light"><th>Date</th><th>Mode</th><th>UTR</th><th>Bank ref</th><th class="text-end">Amount ₹</th><th>Status</th></tr>${d.payments.map(p => `<tr class="${p.status === 'failed' ? 'text-decoration-line-through text-muted' : ''}"><td>${esc(p.on || '')}</td><td>${esc(p.mode || '')}</td><td>${esc(p.utr || '')}</td><td>${esc(p.bank_ref || '')}</td><td class="text-end">${fmt(p.amount)}</td><td>${p.status === 'failed' ? 'Failed: ' + esc(p.failed_reason || '') : 'Paid'}</td></tr>`).join('')}</table></div>` : ''}
                ${(d.closure_gaps || []).length && c.status === 'paid' ? `<div class="alert alert-secondary py-1 px-2 small mb-2"><b>Before closing:</b><ul class="mb-0">${d.closure_gaps.map(g => '<li>' + esc(g) + '</li>').join('')}</ul></div>` : ''}
                ${c.doc_waived_kinds ? `<div class="small text-muted mb-2">Documents waived (${esc(c.doc_waived_kinds.replace('boarding_pass', 'boarding pass').replace('original', 'original bill'))}): ${esc(c.doc_waiver_reason || '')}</div>` : ''}
                ${extractTable(c, d)}
                <div class="mb-2"><p class="btc-label">Files</p>${files}</div>
                <div class="mb-2">${acts || '<span class="text-muted small">No step for you at this stage.</span>'}</div>
                <form id="btcActForm" class="row g-2 mb-3" style="display:none" enctype="multipart/form-data"></form>
                <p class="btc-label">History</p>${logs}`;
            // BTC-V2: the original invoice beside the data, to compare (Finance side)
            const bf = d.bill_file;
            $('#btcCaseBody').html(bf && SHOW_FLOW ? `<div class="row g-3"><div class="col-lg-5"><p class="btc-label">Original invoice · <a target="_blank" rel="noopener" href="${bf.url}">open in a new tab</a></p>${bf.pdf ? `<iframe src="${bf.url}" title="Invoice" style="width:100%;height:75vh;border:1px solid #e5e7eb;border-radius:6px"></iframe>` : `<img src="${bf.url}" alt="Invoice" style="max-width:100%;border:1px solid #e5e7eb;border-radius:6px">`}</div><div class="col-lg-7">${body}</div></div>` : body);
            $('#btcCaseBody [data-act]').on('click', function () {
                const a = $(this).data('act');
                $('#btcActForm').data('case', c.id).html(actionForm(a, d) + `<div class="col-12"><input type="hidden" name="action" value="${a}"><button type="button" class="btn btn-sm btn-primary" id="btcDo">${LABEL[a]}</button></div>`).show();
                $('#btcPreview').on('click', function () {   // BTC-FIN-02: nothing is saved
                    $.ajax({url: '{{ url('btc/case') }}/' + c.id + '/preview', method: 'POST', data: new FormData($('#btcActForm')[0]), processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
                        .done(r => $('#btcPreviewOut').html(journalTable(r.lines) + `<div class="small mt-1">TDS ₹${fmt(r.tds)} (${esc(r.section)}) · Net payable ₹${fmt(r.net)}</div>`))
                        .fail(x => $('#btcPreviewOut').html('<div class="text-danger small">' + esc((x.responseJSON && x.responseJSON.message) || 'Preview failed') + '</div>'));
                });
                $('#btcActForm .btc-vpick').each(function () { if ($.fn.select2) $(this).select2({dropdownParent: $('#btcCaseModal'), width: '100%', allowClear: true, placeholder: 'Type to search the vendor'}); });
                $('#btcReread').on('click', function () {   // BTC-V2: read the attached invoice again (fills empty fields)
                    $('#btcRereadMsg').text('Reading…');
                    $.ajax({url: '{{ url('btc/case') }}/' + c.id + '/reread', method: 'POST', headers: {'X-CSRF-TOKEN': CSRF}})
                        .done(r => { Swal.fire(r.success ? 'Read' : 'Not read', r.message, r.success ? 'success' : 'warning'); openCase(c.id); })
                        .fail(x => $('#btcRereadMsg').text((x.responseJSON && x.responseJSON.message) || 'Could not read the invoice.'));
                });
                $('#btcDo').on('click', function () {
                    if (!checkAmounts($('#btcActForm'))) return;   // BTC-AMT-01
                    const $af = $('#btcActForm');   // BTC-REQ-27: vendor name is mandatory when booking / updating the vendor
                    if ($af.find('.btc-vpick').length && !$af.find('.btc-vpick').val() && !String($af.find('[name=vendor_name]').val() || '').trim()) { Swal.fire('Vendor needed', 'Choose the vendor from the list or type its name.', 'warning'); return; }
                    $.ajax({url: '{{ url('btc/case') }}/' + c.id + '/act', method: 'POST', data: new FormData($('#btcActForm')[0]), processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
                        .done(r => { Swal.fire('Done', r.message, 'success'); openCase(c.id); $(document).trigger('btc:changed'); })
                        .fail(x => Swal.fire('Not done', (x.responseJSON && x.responseJSON.message) || 'Error', 'warning'));
                });
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('btcCaseModal')).show();
        });
    }
    window.BtcCase = {open: openCase};
});
</script>
@endpush
