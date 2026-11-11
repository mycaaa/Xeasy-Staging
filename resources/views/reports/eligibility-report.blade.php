{{-- ELIG-REPORT-01: all-employee eligibility report (read-only, from HRIMS) --}}
@extends('layouts.app')

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row card mt-2">
            <div class="col-lg-12 shadow p-3">
                <h5 class="mb-1">Employee eligibility report</h5>
                <p class="text-muted small mb-2">Lodging, DA, own-vehicle rates and limits and travel class for every employee, from the latest active eligibility in HRIMS.</p>
                <form method="get" class="row g-2 mb-2">
                    <div class="col-md-2">
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Emp code or name" aria-label="Emp code or name">
                    </div>
                    <div class="col-md-3">
                        <select name="department" class="form-select form-select-sm">
                            <option value="">All departments</option>
                            @foreach ($departments as $id => $name)
                                <option value="{{ $id }}" @selected((string) request('department') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="grade" class="form-select form-select-sm">
                            <option value="">All grades</option>
                            @foreach ($grades as $id => $name)
                                <option value="{{ $id }}" @selected((string) request('grade') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="A" @selected(request('status', 'A') === 'A')>Active employees</option>
                            <option value="R" @selected(request('status') === 'R')>Resigned</option>
                            <option value="D" @selected(request('status') === 'D')>Deactive</option>
                            <option value="all" @selected(request('status') === 'all')>All</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="missing" value="1" id="missing" @checked(request('missing') === '1')>
                            <label class="form-check-label" for="missing">Missing only</label>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-sm btn-primary">Show</button>
                        <a class="btn btn-sm btn-success" href="{{ route('eligibility.report.export', request()->query()) }}"><i class="ri-file-excel-2-line"></i> Excel</a>
                    </div>
                </form>
                <div class="small text-muted mb-1">{{ number_format($rows->total()) }} employees</div>
                <div class="table-responsive" style="max-height:70vh">
                    <table class="table table-sm table-bordered small mb-1">
                        <thead class="table-light" style="position:sticky;top:0">
                            <tr>
                                @foreach ($columns as $label)
                                    <th class="text-nowrap">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    @foreach (array_keys($columns) as $k)
                                        <td class="text-nowrap">{{ $r->{$k} ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($columns) }}" class="text-center text-muted">No employees match.</td></tr>
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
