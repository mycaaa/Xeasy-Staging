{{-- UPI-PROOF-01: UPI payment proof of a claim (claim view of claimant / punch / verifier / approver / finance) --}}
<div class="card mt-3 upi-proof-panel" id="upi-proof-panel-{{ $claim->ExpId }}"
     data-exp="{{ $claim->ExpId }}" data-year="{{ $yearId }}" data-company="{{ $companyId }}"
     data-base="{{ url('claims') }}" data-proof-base="{{ url('upi-proof') }}">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <strong>UPI payment proof</strong>
        @if ($proofs->isNotEmpty())
            <span class="badge bg-success">{{ $proofs->count() }} attached &middot; ₹{{ number_format($upi_total, 2) }}</span>
        @elseif ($mode_is_upi)
            <span class="badge bg-warning text-dark">Bill says paid by UPI &mdash; no proof</span>
        @else
            <span class="badge bg-light text-muted">none</span>
        @endif
    </div>
    <div class="card-body py-2 small">
        @if ($payment_mode)
            <div class="mb-1">Payment mode on the bill: <strong>{{ $payment_mode }}</strong></div>
        @endif
        @if ($short)
            <div class="alert alert-warning py-1 px-2 mb-2">UPI proof total ₹{{ number_format($upi_total, 2) }} is less than the claim amount ₹{{ number_format($claim_amount, 2) }}.</div>
        @endif

        @if ($proofs->isNotEmpty())
            <table class="table table-sm table-bordered mb-2">
                <thead class="table-light"><tr><th>Paid on</th><th>UPI transaction no.</th><th>Payee</th><th class="text-end">Amount</th><th>Screenshot</th><th>Added</th>@if ($canAdd)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($proofs as $p)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($p->paid_on)->format('d-m-Y') }}</td>
                        <td class="font-monospace">{{ $p->utr }}</td>
                        <td>{{ $p->payee }}</td>
                        <td class="text-end">₹{{ number_format((float) $p->amount, 2) }}</td>
                        <td><a href="{{ url('upi-proof/file/'.$p->id) }}?company_id={{ $companyId }}&year_id={{ $yearId }}" target="_blank" rel="noopener">view</a></td>
                        <td>{{ \Carbon\Carbon::parse($p->created_at)->format('d-m-Y H:i') }} ({{ $p->added_via }})</td>
                        @if ($canAdd)<td><button type="button" class="btn btn-link btn-sm text-danger p-0 upi-proof-remove" data-id="{{ $p->id }}">remove</button></td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($canAdd)
            <form class="upi-proof-form row g-2 align-items-end" enctype="multipart/form-data" onsubmit="return false;">
                <div class="col-md-3"><label class="form-label mb-0">UPI transaction no. (UTR / UPI Ref No.)</label><input type="text" name="utr" class="form-control form-control-sm" maxlength="40" required></div>
                <div class="col-md-2"><label class="form-label mb-0">Amount (₹)</label><input type="number" step="0.01" min="1" name="amount" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label mb-0">Paid on</label><input type="date" name="paid_on" class="form-control form-control-sm" max="{{ date('Y-m-d') }}" value="{{ $claim->BillDate && $claim->BillDate > '2000-01-01' ? $claim->BillDate : '' }}" required></div>
                <div class="col-md-2"><label class="form-label mb-0">Paid to (name / UPI id)</label><input type="text" name="payee" class="form-control form-control-sm" maxlength="120"></div>
                <div class="col-md-2"><label class="form-label mb-0">Screenshot</label><input type="file" name="file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-primary w-100 upi-proof-save">Attach</button></div>
                <div class="col-12 upi-proof-msg small"></div>
            </form>
        @endif
    </div>
</div>
<script>
(function () {
    if (window.__upiProofBound) return;
    window.__upiProofBound = true;
    function token() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
    function send(panel, url, fd, msgBox) {
        fd.append('company_id', panel.dataset.company);
        fd.append('year_id', panel.dataset.year);
        fetch(url, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j.html) {
                    var tmp = document.createElement('div'); tmp.innerHTML = res.j.html;
                    var fresh = tmp.querySelector('.upi-proof-panel');
                    if (fresh) panel.replaceWith(fresh);
                } else if (msgBox) {
                    var m = res.j.message || (res.j.errors ? Object.values(res.j.errors).flat().join(' ') : 'Could not save.');
                    msgBox.innerHTML = '<span class="text-danger"></span>'; msgBox.firstChild.textContent = m;
                }
            })
            .catch(function () { if (msgBox) msgBox.textContent = 'Could not save. Check the connection and try again.'; });
    }
    document.addEventListener('click', function (e) {
        var save = e.target.closest('.upi-proof-save');
        if (save) {
            var form = save.closest('form'), panel = save.closest('.upi-proof-panel');
            if (!form.reportValidity()) return;
            var msg = form.querySelector('.upi-proof-msg'); msg.textContent = 'Saving...';
            send(panel, panel.dataset.base + '/' + panel.dataset.exp + '/upi-proof', new FormData(form), msg);
            return;
        }
        var rm = e.target.closest('.upi-proof-remove');
        if (rm) {
            var p = rm.closest('.upi-proof-panel');
            send(p, p.dataset.proofBase + '/' + rm.dataset.id + '/remove', new FormData(), p.querySelector('.upi-proof-msg'));
        }
    });
})();
</script>
