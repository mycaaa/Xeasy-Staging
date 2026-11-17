{{-- BTC-BANK-01 (17 Nov 2026): vendor bank accounts - entered by one person, verified by another --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => 'Vendor Bank Accounts', 'icon' => 'ri-bank-card-line',
        'sub' => 'BTC payments go only to a verified account. One person enters, another (Treasury checker) verifies; a change replaces the old account only once verified.'])
    @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
    @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
    @if (! $ready)<div class="alert alert-warning">Run the database change first (Database setup).</div>@endif

    @if ($ready && $canEnter)
        <section class="xe-section mb-3">
            <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-add-line"></i>Enter or change bank details</span></div>
            <div class="xe-section-body">
                <form method="post" action="{{ route('btc.vendor-banks.save') }}" class="row g-2" autocomplete="off">@csrf
                    <div class="col-md-4"><label class="form-label small mb-1" for="vbVendor">Vendor</label>
                        <select id="vbVendor" name="vendor_id" class="form-select form-select-sm"><option value="">Not in the vendor list (type the name)</option>@foreach ($vendors as $v)<option value="{{ $v->id }}" data-gst="{{ $v->gst_no }}">{{ $v->vendor_name }}{{ $v->gst_no ? ' · '.$v->gst_no : '' }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label small mb-1" for="vbName">Vendor name</label><input id="vbName" name="vendor_name" class="form-control form-control-sm" value="{{ old('vendor_name') }}" required></div>
                    <div class="col-md-4"><label class="form-label small mb-1" for="vbGst">GSTIN</label><input id="vbGst" name="vendor_gstin" class="form-control form-control-sm" value="{{ old('vendor_gstin') }}" maxlength="20"></div>
                    <div class="col-md-4"><label class="form-label small mb-1" for="vbAcName">Account holder name (as in the bank)</label><input id="vbAcName" name="account_name" class="form-control form-control-sm" value="{{ old('account_name') }}" required></div>
                    <div class="col-md-2"><label class="form-label small mb-1" for="vbAc">Account no.</label><input id="vbAc" name="account_no" class="form-control form-control-sm" inputmode="numeric" required></div>
                    <div class="col-md-2"><label class="form-label small mb-1" for="vbAc2">Account no. again</label><input id="vbAc2" name="account_no_confirm" class="form-control form-control-sm" inputmode="numeric" required onpaste="return false"></div>
                    <div class="col-md-2"><label class="form-label small mb-1" for="vbIfsc">IFSC</label><input id="vbIfsc" name="ifsc" class="form-control form-control-sm text-uppercase" maxlength="11" value="{{ old('ifsc') }}" required></div>
                    <div class="col-md-2"><label class="form-label small mb-1" for="vbBank">Bank / branch</label><input id="vbBank" name="bank_name" class="form-control form-control-sm" value="{{ old('bank_name') }}"></div>
                    <div class="col-md-8"><label class="form-label small mb-1" for="vbRmk">Source of the details (cancelled cheque / letter on letterhead)</label><input id="vbRmk" name="remark" class="form-control form-control-sm" value="{{ old('remark') }}"></div>
                    <div class="col-md-4 d-flex align-items-end"><button class="xe-btn xe-btn-blue"><i class="ri-save-line"></i>Save for verification</button></div>
                </form>
            </div>
        </section>
    @endif

    <form method="get" class="d-flex gap-2 mb-2">
        <select name="status" class="form-select form-select-sm" style="max-width:200px" aria-label="Status"><option value="">All</option>@foreach (['pending' => 'Waiting for verification', 'verified' => 'Verified', 'rejected' => 'Rejected', 'replaced' => 'Replaced'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:260px" placeholder="Vendor or GSTIN" aria-label="Search">
        <button class="xe-btn xe-btn-blue">Filter</button>
    </form>
    <section class="xe-section"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Vendor</th><th>Account</th><th>Entered</th><th>Status</th><th style="min-width:260px">Verify</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->vendor_name }}<div class="small text-muted">{{ $r->vendor_gstin }}</div></td>
                    <td>{{ $r->account_name }}<div class="small text-muted">XXXX{{ $r->account_last4 }} · {{ $r->ifsc }}{{ $r->bank_name ? ' · '.$r->bank_name : '' }}</div>@if ($r->remark)<div class="small text-muted">{{ $r->remark }}</div>@endif</td>
                    <td class="small">{{ $r->entered_by_name }}<div class="text-muted">{{ substr((string) $r->created_at, 0, 16) }}</div></td>
                    <td><span class="badge {{ ['verified' => 'bg-success', 'pending' => 'bg-warning text-dark', 'rejected' => 'bg-danger', 'replaced' => 'bg-secondary'][$r->status] ?? 'bg-light' }}">{{ ucfirst($r->status) }}</span>@if ($r->verified_by_name)<div class="small text-muted">{{ $r->verified_by_name }}, {{ substr((string) $r->verified_at, 0, 10) }}</div>@endif</td>
                    <td>
                        @if ($r->status === 'pending' && $canVerify && (int) $r->entered_by !== (int) auth()->id())
                            <form method="post" action="{{ route('btc.vendor-banks.decide', $r->id) }}" class="d-flex gap-1">@csrf
                                <input name="remark" class="form-control form-control-sm" placeholder="Checked against (e.g. cancelled cheque)" aria-label="Remark">
                                <button name="decision" value="verify" class="btn btn-sm btn-success">Verify</button>
                                <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        @elseif ($r->status === 'pending')
                            <span class="small text-muted">Another Treasury checker verifies</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No bank accounts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
    @if ($rows instanceof \Illuminate\Contracts\Pagination\Paginator){{ $rows->links() }}@endif
</div></div>
@push('scripts')
<script>
document.getElementById('vbVendor')?.addEventListener('change', function () {
    const o = this.selectedOptions[0];
    if (this.value) { document.getElementById('vbName').value = o.text.split(' · ')[0]; document.getElementById('vbGst').value = o.dataset.gst || ''; }
});
</script>
@endpush
@endsection
