{{-- BOARDING-01: vendor boarding-pass portal (no login; the link is the credential) --}}
@extends('layouts.standalone')

@section('title', 'VNR · Vendor portal')

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>body{background:#f4f6fa}.wrap{max-width:980px;margin:24px auto;padding:0 16px}.head{background:#dde6ff;color:#1e3a8a;border-radius:10px 10px 0 0;padding:14px 18px}</style>
@endpush

@section('content')
<div class="wrap">
    <div class="head">
        <div class="fw-semibold fs-5">VNR vendor portal · boarding passes</div>
        <div class="small opacity-75">{{ $link->vendor_name }} · link valid until {{ \Carbon\Carbon::parse($link->expires_at)->format('d-m-Y') }}</div>
    </div>
    <div class="bg-white p-3 border border-top-0 rounded-bottom">
        <p class="small text-muted">Upload the boarding pass (PDF, JPG or PNG, up to 5 MB) for each booking below. Once uploaded, the booking leaves this list.</p>
        @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
        @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
        <div class="table-responsive">
            <table class="table table-sm table-bordered small align-middle">
                <thead class="table-light"><tr><th>Case</th><th>Traveller</th><th>PNR</th><th>Sector</th><th>Travel date</th><th style="width:330px">Boarding pass</th></tr></thead>
                <tbody>
                @forelse ($cases as $c)
                    <tr>
                        <td>{{ $c->case_no }}</td>
                        <td>{{ $c->emp_name }}</td>
                        <td>{{ $c->pnr }}</td>
                        <td>{{ $c->travel_sector }}</td>
                        <td>{{ optional($c->travel_date)->format('d-m-Y') }}</td>
                        <td>
                            <form method="post" action="{{ route('vendor.boarding.upload', [$token, $c->id]) }}" enctype="multipart/form-data" class="d-flex gap-1">
                                @csrf
                                <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" required>
                                <button class="btn btn-sm btn-primary">Upload</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Nothing pending. Thank you.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{-- BOARDING-04 (17 Nov 2026): many files or a whole folder at once, matched by PNR / BTC number in the file name --}}
        <div class="border rounded p-2 mb-3 bg-light">
            <div class="fw-semibold small mb-1">Upload many boarding passes at once</div>
            <p class="small text-muted mb-2">Name each file with the PNR (e.g. <code>ABC123.pdf</code>) or the case number (e.g. <code>BTC-26-00013.pdf</code>). Files that do not match one booking are listed and not saved.</p>
            <form method="post" action="{{ route('vendor.boarding.bulk', $token) }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">@csrf
                <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" style="max-width:320px" aria-label="Boarding pass files">
                <span class="small text-muted">or a folder:</span>
                <input type="file" name="files[]" multiple webkitdirectory directory class="form-control form-control-sm" style="max-width:320px" aria-label="Boarding pass folder">
                <button class="btn btn-sm btn-primary">Upload all</button>
            </form>
            @if (! empty($bulk))
                <table class="table table-sm small mt-2 mb-0"><tbody>@foreach ($bulk as $r)<tr class="{{ $r['ok'] ? 'table-success' : 'table-warning' }}"><td>{{ $r['file'] }}</td><td>{{ $r['note'] }}</td></tr>@endforeach</tbody></table>
            @endif
        </div>
    </div>

    {{-- BTC-VENDOR-01 (17 Nov 2026): invoices --}}
    <div class="head mt-4" style="border-radius:10px 10px 0 0"><div class="fw-semibold">Send your invoice</div><div class="small opacity-75">Bookings that are waiting for your invoice. The original bill still goes to VNR where asked.</div></div>
    <div class="bg-white p-3 border border-top-0 rounded-bottom">
        @if (isset($errors) && $errors->has('invoice'))<div class="alert alert-danger py-2">{{ $errors->first('invoice') }}</div>@endif
        @forelse ($billable as $c)
            <form method="post" action="{{ route('vendor.invoice.upload', [$token, $c->id]) }}" enctype="multipart/form-data" class="border rounded p-2 mb-2">@csrf
                <div class="small mb-2"><b>{{ $c->case_no }}</b> · {{ $c->emp_name }} · {{ $c->purpose }} · {{ optional($c->service_from)->format('d-m-Y') }}{{ $c->service_to ? ' to '.$c->service_to->format('d-m-Y') : '' }}{{ $c->booking_ref ? ' · booking '.$c->booking_ref : '' }}@if ($c->status === 'returned') · <span class="text-danger">returned for correction</span>@endif</div>
                <div class="row g-2">
                    <div class="col-md-2"><input name="invoice_no" class="form-control form-control-sm" placeholder="Invoice no." required aria-label="Invoice no."></div>
                    <div class="col-md-2"><input name="invoice_date" type="date" class="form-control form-control-sm" max="{{ now()->toDateString() }}" required aria-label="Invoice date"></div>
                    <div class="col-md-2"><input name="taxable_amount" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Taxable ₹" required aria-label="Taxable value"></div>
                    <div class="col-md-1"><input name="gst_amount" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="GST ₹" aria-label="GST"></div>
                    <div class="col-md-2"><input name="bill_amount" type="number" step="0.01" min="1" class="form-control form-control-sm" placeholder="Total ₹" required aria-label="Invoice total"></div>
                    <div class="col-md-3"><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" required aria-label="Invoice file"></div>
                    @if (! $c->vendor_gstin)<div class="col-md-3"><input name="gstin" class="form-control form-control-sm" placeholder="Your GSTIN (if registered)" maxlength="20" aria-label="GSTIN"></div>@endif
                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Send invoice</button></div>
                </div>
            </form>
        @empty
            <p class="text-muted small mb-0">No booking is waiting for an invoice.</p>
        @endforelse
    </div>

    {{-- BTC-VENDOR-01: payment status, latest first --}}
    <div class="head mt-4" style="border-radius:10px 10px 0 0"><div class="fw-semibold">Your invoices and payments</div></div>
    <div class="bg-white p-3 border border-top-0 rounded-bottom">
        <div class="table-responsive"><table class="table table-sm table-bordered small align-middle mb-2">
            <thead class="table-light"><tr><th>Case</th><th>Invoice</th><th class="text-end">Invoice ₹</th><th class="text-end">TDS ₹</th><th class="text-end">Payable ₹</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($billed as $c)
                @php
                    $p = $pays->get($c->id, collect());
                    $st = match (true) {
                        in_array($c->status, ['paid', 'closed'], true) => 'Paid',
                        (float) $c->paid_amount > 0 => 'Part paid',
                        in_array($c->status, ['with_treasury', 'dept_approved'], true) => 'Approved for payment',
                        $c->status === 'returned' => 'Returned - please correct',
                        default => 'Received - under verification',
                    };
                @endphp
                <tr>
                    <td>{{ $c->case_no }}</td><td>{{ $c->bill_no }}<div class="text-muted">{{ optional($c->bill_date)->format('d-m-Y') }}</div></td>
                    <td class="text-end">{{ number_format((float) $c->bill_amount, 2) }}</td><td class="text-end">{{ $c->finance_at ? number_format((float) $c->tds_amount, 2) : '-' }}</td>
                    <td class="text-end">{{ $c->finance_at ? number_format((float) $c->net_payable, 2) : '-' }}</td>
                    <td>{{ $st }}@foreach ($p as $x)<div class="text-muted">₹{{ number_format((float) $x->amount, 2) }} on {{ \Carbon\Carbon::parse($x->paid_on)->format('d-m-Y') }} · UTR {{ $x->utr_number }}</div>@endforeach</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">No invoices yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="small text-muted">Questions: contact VNR Finance.</div>
    </div>
</div>
@endsection
