{{-- PUNCH-TRACK-01: punching change-track report --}}
@extends('layouts.app')

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row card mt-2">
            <div class="col-lg-12 shadow p-3">
                <h5 class="mb-1">Punching change track</h5>
                <p class="text-muted small mb-2">Every field changed while punching a claim, newest first. <b>First punch</b>: the claim's head, bill date, month or amount changed when the detail was first punched. <b>Re-punch</b>: an already punched claim was edited.</p>
                <form method="get" class="row g-2 mb-2">
                    <div class="col-md-2"><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><input type="text" name="emp" value="{{ request('emp') }}" class="form-control form-control-sm" placeholder="Emp code or name" aria-label="Emp code or name"></div>
                    <div class="col-md-2"><input type="text" name="by" value="{{ request('by') }}" class="form-control form-control-sm" placeholder="Changed by" aria-label="Changed by"></div>
                    <div class="col-md-1"><input type="number" name="expid" value="{{ request('expid') }}" class="form-control form-control-sm" placeholder="ExpId" aria-label="ExpId"></div>
                    <div class="col-md-1">
                        <select name="mode" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="first_punch" @selected(request('mode') === 'first_punch')>First punch</option>
                            <option value="re_punch" @selected(request('mode') === 're_punch')>Re-punch</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-sm btn-primary">Show</button>
                        <a class="btn btn-sm btn-success" href="{{ route('punch.change.report.export', request()->query()) }}"><i class="ri-file-excel-2-line"></i> Excel</a>
                    </div>
                </form>
                <div class="small text-muted mb-1">{{ number_format($rows->total()) }} changes</div>
                <div class="table-responsive" style="max-height:70vh">
                    <table class="table table-sm table-bordered small mb-1">
                        <thead class="table-light" style="position:sticky;top:0">
                            <tr><th>Changed on</th><th>Emp code</th><th>Employee</th><th>ExpId</th><th>Head</th><th>Month</th><th>Field</th><th>Old value</th><th>New value</th><th>Type</th><th>Changed by</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($r->created_at)->format('d-m-Y H:i') }}</td>
                                    <td>{{ $r->EmpCode }}</td>
                                    <td>{{ $r->emp_name }}</td>
                                    <td>{{ $r->exp_id }}</td>
                                    <td>{{ $r->ClaimName ?? $r->claim_id }}</td>
                                    <td>{{ $r->claim_month }}</td>
                                    <td>{{ $r->field }}</td>
                                    <td>{{ $r->old_value }}</td>
                                    <td>{{ $r->new_value }}</td>
                                    <td>{{ $r->mode === 'first_punch' ? 'First punch' : 'Re-punch' }}</td>
                                    <td>{{ $r->changed_by_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-center text-muted">No punching changes in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $rows->links() }}
                <x-report-stamp />
            </div>
        </div>
    </div>
@endsection
