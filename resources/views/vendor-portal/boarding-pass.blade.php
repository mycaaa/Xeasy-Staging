{{-- BOARDING-01: vendor boarding-pass portal (no login; the link is the credential) --}}
@extends('layouts.standalone')

@section('title', 'VNR · Boarding passes')

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>body{background:#f4f6fa}.wrap{max-width:980px;margin:24px auto;padding:0 16px}.head{background:#dde6ff;color:#1e3a8a;border-radius:10px 10px 0 0;padding:14px 18px}</style>
@endpush

@section('content')
<div class="wrap">
    <div class="head">
        <div class="fw-semibold fs-5">Boarding passes for VNR bookings</div>
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
        <div class="small text-muted">Questions: contact VNR Finance.</div>
    </div>
</div>
@endsection
