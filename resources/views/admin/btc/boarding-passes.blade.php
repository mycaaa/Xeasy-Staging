{{-- BOARDING-02 (13 Nov 2026): boarding passes of air tickets booked directly by the company (BTC) --}}
@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h4 class="card-title mb-0 flex-grow-1"><i class="ri-plane-line me-1"></i>Boarding Pass <span class="text-muted fs-13 fw-normal">tickets booked by the company</span></h4>
                <a href="{{ route('btc.index') }}" class="btn btn-sm btn-outline-secondary">BTC cases</a>
                @if ($desk)
                    <a href="{{ route('btc.vendor.links') }}" class="btn btn-sm btn-outline-primary">Vendor links</a>
                    <a href="{{ route('btc.export.boarding') }}" class="btn btn-sm btn-outline-success"><i class="ri-file-excel-2-line me-1"></i>Pending list (Excel)</a>
                @endif
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    @if ($desk)
                        Every air ticket the company booked needs the traveller's boarding pass (proof of travel) before the expense is booked.
                        Upload it here, or send the travel vendor its link.
                    @else
                        Air tickets the company booked for you. Upload the boarding pass (PDF or photo) after you travel; Finance books the expense only once it is in.
                    @endif
                </p>
                @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
                @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif

                <form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Show">
                        @foreach (['pending' => 'Pending ('.number_format($counts['pending']).')', 'received' => 'Received ('.number_format($counts['received']).')', 'all' => 'All'] as $k => $label)
                            <a href="{{ request()->fullUrlWithQuery(['show' => $k, 'page' => null]) }}" class="btn {{ $show === $k ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                    <input type="hidden" name="show" value="{{ $show }}">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:280px" placeholder="Case, PNR, sector, employee, vendor" aria-label="Search">
                    <button class="btn btn-sm btn-outline-secondary">Search</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle small mb-2">
                        <thead class="table-light">
                            <tr>
                                <th>Travel date</th><th>Case</th>
                                @if ($desk)<th>Employee</th>@endif
                                <th>PNR</th><th>Sector</th><th>Vendor</th><th>Boarding pass</th><th style="min-width:250px">Upload</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($cases as $c)
                            @php $days = $c->travel_date ? (int) $c->travel_date->diffInDays(now(), false) : null; @endphp
                            <tr>
                                <td class="text-nowrap">{{ optional($c->travel_date)->format('d-m-Y') ?: '—' }}</td>
                                <td class="text-nowrap">{{ $c->case_no }}</td>
                                @if ($desk)<td>{{ $c->emp_name }} <span class="text-muted">{{ $c->emp_code }}</span></td>@endif
                                <td>{{ $c->pnr ?: '—' }}</td>
                                <td>{{ $c->travel_sector ?: '—' }}</td>
                                <td>{{ $c->vendor_name ?: '—' }}</td>
                                <td>
                                    @if ($c->boarding_pass_received_on)
                                        <span class="badge bg-success">Received {{ \Carbon\Carbon::parse($c->boarding_pass_received_on)->format('d-m-Y') }}</span>
                                    @elseif ($days !== null && $days < 0)
                                        <span class="badge bg-secondary">Travel in {{ abs($days) }} {{ \Illuminate\Support\Str::plural('day', abs($days)) }}</span>
                                    @else
                                        <span class="badge {{ ($days ?? 0) > 7 ? 'bg-danger' : 'bg-warning text-dark' }}">Pending{{ $days !== null ? ' · '.$days.' '.\Illuminate\Support\Str::plural('day', $days).' since travel' : '' }}</span>
                                    @endif
                                    @foreach ($c->files as $f)
                                        <div><a href="{{ route('btc.file', $f->id) }}" target="_blank" rel="noopener"><i class="ri-attachment-2"></i> {{ \Illuminate\Support\Str::limit($f->file_name, 28) }}</a></div>
                                    @endforeach
                                </td>
                                <td>
                                    <form method="post" action="{{ route('btc.boarding.upload', $c->id) }}" enctype="multipart/form-data" class="d-flex gap-1">
                                        @csrf
                                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" required aria-label="Boarding pass file for {{ $c->case_no }}">
                                        <button class="btn btn-sm btn-primary text-nowrap">{{ $c->boarding_pass_received_on ? 'Add file' : 'Upload' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $desk ? 8 : 7 }}" class="text-center text-muted py-4">
                                {{ $show === 'pending' ? 'No boarding passes pending.' : 'No company-booked air tickets found.' }}
                            </td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $cases->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
