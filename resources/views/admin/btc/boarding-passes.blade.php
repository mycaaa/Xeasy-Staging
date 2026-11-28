{{-- BOARDING-02 (13 Nov 2026): boarding passes of air tickets booked directly by the company (BTC) --}}
@extends('layouts.app')

@section('content')
<div class="page-content">
    <link rel="stylesheet" href="{{ asset('assets/css/btc-ui.css') }}?v=20261128a">{{-- BTC-UI-01 --}}
    <script src="{{ asset('assets/js/btc-grid.js') }}?v=20261128a" defer></script>
    <div class="container-fluid xe-screen btc-ui">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h4 class="card-title mb-0 flex-grow-1"><i class="ri-plane-line me-1"></i>Boarding Pass <span class="text-muted fs-13 fw-normal">tickets booked by the company</span></h4>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bpUploadModal"><i class="ri-upload-2-line me-1"></i>Upload boarding pass</button>
                <a href="{{ route('btc.index') }}" class="btn btn-sm btn-outline-secondary">BTC cases</a>
                @if ($desk)
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
                {{-- BOARDING-04 (17 Nov 2026): many files or a whole folder at once --}}
                <div class="border rounded p-2 mb-3 bg-light">
                    <div class="fw-semibold small mb-1"><i class="ri-folder-upload-line me-1"></i>Upload many at once</div>
                    <p class="small text-muted mb-2">Name each file with the PNR (e.g. <code>ABC123.pdf</code>) or the BTC number (e.g. <code>BTC-26-00013.pdf</code>). Each file is matched to one pending booking; files that match none or several are listed and not saved.</p>
                    <form method="post" action="{{ route('btc.boarding.bulk') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">@csrf
                        <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" style="max-width:300px" aria-label="Boarding pass files">
                        <span class="small text-muted">or a folder:</span>
                        <input type="file" name="files[]" multiple webkitdirectory directory class="form-control form-control-sm" style="max-width:300px" aria-label="Boarding pass folder">
                        <button class="btn btn-sm btn-primary">Upload all</button>
                    </form>
                    @if (session('bulk_result'))
                        <table class="table table-sm small mt-2 mb-0"><tbody>@foreach (session('bulk_result') as $r)<tr class="{{ $r['ok'] ? 'table-success' : 'table-warning' }}"><td>{{ $r['file'] }}</td><td>{{ $r['note'] }}</td></tr>@endforeach</tbody></table>
                    @endif
                </div>

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
                @include('admin.btc.pages._pager', ['p' => $cases])
            </div>
        </div>
    </div>
</div>

{{-- 16 Nov #4: upload a boarding pass for any open air booking --}}
<div class="modal fade" id="bpUploadModal" tabindex="-1" aria-labelledby="bpUploadTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" enctype="multipart/form-data" id="bpUploadForm" action="">
            @csrf
            <div class="modal-header py-2">
                <h6 class="modal-title" id="bpUploadTitle"><i class="ri-upload-2-line me-1"></i>Upload boarding pass</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if ($uploadCases->isEmpty())
                    <p class="small mb-2">There is no air booking {{ $desk ? '' : 'for you ' }}in BTC yet. A boarding pass belongs to an air ticket the company booked:
                        create the BTC case first with category <strong>Travel Desk – Air</strong>, then upload the boarding pass here after the journey.</p>
                    <a href="{{ route('btc.index') }}" class="btn btn-sm btn-outline-primary">Go to BTC cases</a>
                @else
                    <label class="form-label small mb-1" for="bpCase">Air booking</label>
                    <select class="form-select form-select-sm mb-2" id="bpCase" required>
                        <option value="">Choose the booking…</option>
                        @foreach ($uploadCases as $u)
                            <option value="{{ route('btc.boarding.upload', $u->id) }}">
                                {{ $u->case_no }} · {{ $u->emp_name }} · {{ $u->travel_sector ?: 'sector not set' }} · {{ $u->pnr ? 'PNR '.$u->pnr : 'no PNR' }}{{ $u->travel_date ? ' · '.\Carbon\Carbon::parse($u->travel_date)->format('d-m-Y') : '' }}{{ $u->boarding_pass_received_on ? ' · already received (adds a file)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <label class="form-label small mb-1" for="bpFile">Boarding pass (PDF or photo, up to 5 MB)</label>
                    <input type="file" class="form-control form-control-sm" name="file" id="bpFile" accept=".pdf,.jpg,.jpeg,.png" required>
                @endif
            </div>
            @if ($uploadCases->isNotEmpty())
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-primary">Upload</button>
                </div>
            @endif
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('bpCase'), form = document.getElementById('bpUploadForm');
        if (sel) { sel.addEventListener('change', function () { form.action = this.value; }); }
    });
</script>
@endsection
