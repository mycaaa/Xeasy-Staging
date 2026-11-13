@extends('layouts.app')

@section('content')
<div class="page-content">
<div class="container-fluid">

    {{-- Header --}}
    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold"><i class="ri-group-line text-primary me-2"></i>Employee Master Report</h4>
                <p class="text-muted mb-0 fs-13">All employees with department, grade, designation, HQ and contact details.</p>
            </div>
            <a href="{{ route('employee.report.export') }}?{{ http_build_query(request()->all()) }}"
               class="btn btn-success btn-sm">
                <i class="ri-file-excel-2-line me-1"></i> Export Excel
            </a>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card" style="border-left:4px solid #3b82f6;">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3" style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:50%;flex-shrink:0;">
                        <i class="ri-group-line"></i>
                    </span>
                    <div>
                        <p class="text-muted mb-0 fs-12 text-uppercase fw-semibold">Total Employees</p>
                        <h4 class="mb-0">{{ number_format($total) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-left:4px solid #22c55e;">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3" style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:50%;flex-shrink:0;">
                        <i class="ri-user-follow-line"></i>
                    </span>
                    <div>
                        <p class="text-muted mb-0 fs-12 text-uppercase fw-semibold">Active</p>
                        <h4 class="mb-0 text-success">{{ number_format($active) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-left:4px solid #ef4444;">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-3" style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:50%;flex-shrink:0;">
                        <i class="ri-user-unfollow-line"></i>
                    </span>
                    <div>
                        <p class="text-muted mb-0 fs-12 text-uppercase fw-semibold">Inactive / Left</p>
                        <h4 class="mb-0 text-danger">{{ number_format($inactive) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('employee.report') }}" id="employeeReportForm" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1 fs-12 fw-semibold">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           value="{{ request('search') }}" placeholder="Name / Emp Code" aria-label="Name / Emp Code">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 fs-12 fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="A" {{ request('status') == 'A' ? 'selected' : '' }}>Active</option>
                        <option value="D" {{ request('status') == 'D' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 fs-12 fw-semibold">Department</label>
                    <select name="department" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments ?? [] as $key => $dept)
                            @php
                                $dId = is_object($dept) ? $dept->id : (is_array($dept) ? ($dept['id'] ?? $key) : $key);
                                $dName = is_object($dept) ? $dept->department_name : (is_array($dept) ? ($dept['department_name'] ?? $dept) : $dept);
                            @endphp
                            <option value="{{ $dId }}" {{ request('department') == $dId ? 'selected' : '' }}>{{ $dName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 fs-12 fw-semibold">Grade</label>
                    <select name="grade" class="form-select form-select-sm">
                        <option value="">All Grades</option>
                        @foreach($grades as $id => $name)
                        <option value="{{ $id }}" {{ request('grade') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 fs-12 fw-semibold">Designation</label>
                    <select name="designation" class="form-select form-select-sm">
                        <option value="">All Designations</option>
                        @foreach($designations as $id => $name)
                        <option value="{{ $id }}" {{ request('designation') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 fs-12 fw-semibold">Vertical</label>
                    <select name="vertical" class="form-select form-select-sm">
                        <option value="">All Verticals</option>
                        @foreach($verticals ?? [] as $key => $vert)
                            @php
                                $vId = is_object($vert) ? $vert->id : (is_array($vert) ? ($vert['id'] ?? $key) : $key);
                                $vName = is_object($vert) ? $vert->vertical_name : (is_array($vert) ? ($vert['vertical_name'] ?? $vert) : $vert);
                            @endphp
                            <option value="{{ $vId }}" {{ request('vertical') == $vId ? 'selected' : '' }}>{{ $vName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ri-filter-line me-1"></i> Filter
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                        <i class="ri-equalizer-line me-1"></i> More Filters
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="organogramFilterCountBadge" style="display:none;">0</span>
                    </button>
                    <a href="{{ route('employee.report') }}" class="btn btn-soft-secondary btn-sm">
                        <i class="ri-refresh-line me-1"></i> Reset
                    </a>
                    <span class="text-muted fs-12 align-self-center ms-2">
                        Showing {{ $employees->firstItem() }}–{{ $employees->lastItem() }} of {{ number_format($employees->total()) }} employees
                    </span>
                </div>
            </form>
        </div>
    </div>
    @include('partials.organogram-filters-drawer')

    {{-- Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" style="font-size:12px;">
                    <thead class="table" style="position:sticky;top:0;z-index:1; background-color: #dde6ff; color: #1e3a8a;">
                        <tr>
                            <th class="text-center" style="width:45px;">#</th>
                            <th>Emp Code</th>
                            <th>Name</th>
                            <th class="text-center">Status</th>
                            <th>Department</th>
                            <th class="text-center">Grade</th>
                            <th>Designation</th>
                            <th>Vertical</th>
                            <th>HQ / Territory</th>
                            <th>Zone</th>
                            <th>Region</th>
                            <th>State</th>
                            <th>Mobile</th>
                            <th>Reporting Manager</th>
                            <th class="text-center">DOJ</th>
                            <th class="text-center">Resignation</th>
                            <th class="text-center">Separation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $i => $emp)
                        @php
                            $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01', null];
                            $empSts = getEmployeeStatus(null, $emp->EmployeeID ?? null);
                            $isInactive = ($empSts !== 'A');
                            $doj = ($emp->date_joining && !in_array($emp->date_joining, $blankDates))
                                ? \Carbon\Carbon::parse($emp->date_joining)->format('d M Y')
                                : '—';
                            $dor = ($emp->DateOfResignation && !in_array($emp->DateOfResignation, $blankDates))
                                ? \Carbon\Carbon::parse($emp->DateOfResignation)->format('d M Y')
                                : null;
                            $dos = ($emp->DateOfSepration && !in_array($emp->DateOfSepration, $blankDates))
                                ? \Carbon\Carbon::parse($emp->DateOfSepration)->format('d M Y')
                                : null;
                        @endphp
                        <tr class="{{ $isInactive ? 'table-light text-muted' : '' }}">
                            <td class="text-center text-muted">{{ $employees->firstItem() + $i }}</td>
                            <td>
                                <span class="badge bg-light text-dark border" style="font-size:11px;">
                                    {{ $emp->emp_code ?: '—' }}
                                </span>
                            </td>
                            <td class="fw-semibold" style="color:{{ $isInactive ? '#94a3b8' : '#1e293b' }};">
                                {{ trim($emp->emp_name) }}
                            </td>
                            <td class="text-center">
                                @if($empSts === 'A')
                                    <span class="badge bg-success-subtle text-success" style="font-size:10px;">Active</span>
                                @elseif($empSts === 'R')
                                    <span class="badge bg-warning-subtle text-warning" style="font-size:10px;">Resigned</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger" style="font-size:10px;">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $emp->department_name ?: '—' }}</td>
                            <td class="text-center">
                                @if($emp->grade_name)
                                @php
                                    $gc = in_array(strtoupper($emp->grade_name), ['J1','J2'])
                                        ? 'bg-info-subtle text-info'
                                        : (in_array(strtoupper($emp->grade_name), ['J3','J4','M1','M2','M3'])
                                            ? 'bg-warning-subtle text-warning'
                                            : 'bg-secondary-subtle text-secondary');
                                @endphp
                                <span class="badge {{ $gc }}" style="font-size:10px;">{{ strtoupper($emp->grade_name) }}</span>
                                @else —
                                @endif
                            </td>
                            <td>{{ $emp->designation_name ?: '—' }}</td>
                            <td>{{ $emp->vertical_name ?: '—' }}</td>
                            <td>{{ $emp->hq_name ?: '—' }}</td>
                            <td>{{ $emp->zone_name ?: '—' }}</td>
                            <td>{{ $emp->region_name ?: '—' }}</td>
                            <td>{{ $emp->state_name ?: '—' }}</td>
                            <td>{{ $emp->mobile ?: '—' }}</td>
                            <td style="font-size:11px;color:#475569;">{{ $emp->reporting_name ?: '—' }}</td>
                            <td class="text-center" style="font-size:11px;">{{ $doj }}</td>
                            <td class="text-center" style="font-size:11px;">
                                @if($dor)
                                    <span class="text-danger">{{ $dor }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center" style="font-size:11px;">
                                @if($dos)
                                    <span class="text-danger">{{ $dos }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="15" class="text-center py-5 text-muted">
                                <i class="ri-group-line fs-1 d-block mb-2"></i>
                                No employees found for the selected filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($employees->hasPages())
        <div class="card-footer py-2 d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size:12px;">
            <div class="text-muted">
                Showing <strong>{{ $employees->firstItem() }}</strong>–<strong>{{ $employees->lastItem() }}</strong>
                of <strong>{{ number_format($employees->total()) }}</strong> employees
            </div>
            <div class="d-flex align-items-center gap-1">
                {{-- Previous --}}
                @if($employees->onFirstPage())
                    <span class="btn btn-sm btn-light disabled" style="font-size:11px;padding:3px 10px;">
                        <i class="ri-arrow-left-s-line"></i> Prev
                    </span>
                @else
                    <a href="{{ $employees->previousPageUrl() }}" class="btn btn-sm btn-soft-primary" style="font-size:11px;padding:3px 10px;">
                        <i class="ri-arrow-left-s-line"></i> Prev
                    </a>
                @endif

                {{-- Page numbers --}}
                @foreach($employees->getUrlRange(max(1, $employees->currentPage()-2), min($employees->lastPage(), $employees->currentPage()+2)) as $page => $url)
                    @if($page == $employees->currentPage())
                        <span class="btn btn-sm btn-primary" style="font-size:11px;padding:3px 10px;min-width:32px;">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="btn btn-sm btn-soft-secondary" style="font-size:11px;padding:3px 10px;min-width:32px;">{{ $page }}</a>
                    @endif
                @endforeach

                {{-- Next --}}
                @if($employees->hasMorePages())
                    <a href="{{ $employees->nextPageUrl() }}" class="btn btn-sm btn-soft-primary" style="font-size:11px;padding:3px 10px;">
                        Next <i class="ri-arrow-right-s-line"></i>
                    </a>
                @else
                    <span class="btn btn-sm btn-light disabled" style="font-size:11px;padding:3px 10px;">
                        Next <i class="ri-arrow-right-s-line"></i>
                    </span>
                @endif
            </div>
        </div>
        @endif
    </div>

</div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .btn, form, .card-footer { display: none !important; }
    .page-content { padding: 0 !important; }
    table { font-size: 10px !important; }
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    function appendOrganogramToForm() {
        const form = $('#employeeReportForm');
        form.find('.organogram-hidden-input').remove();
        if (typeof window.getOrganogramFilterValues === 'function') {
            const filters = window.getOrganogramFilterValues();
            $.each(filters, function(key, val) {
                if (Array.isArray(val)) {
                    val.forEach(function(v) {
                        form.append($('<input type="hidden" class="organogram-hidden-input">').attr('name', key + '[]').val(v));
                    });
                } else if (val) {
                    form.append($('<input type="hidden" class="organogram-hidden-input">').attr('name', key).val(val));
                }
            });
        }
    }

    $(document).on('organogramFiltersApplied', function() {
        appendOrganogramToForm();
        $('#employeeReportForm').submit();
    });

    $(document).on('organogramFiltersReset', function() {
        appendOrganogramToForm();
        $('#employeeReportForm').submit();
    });

    $('#employeeReportForm').on('submit', function() {
        appendOrganogramToForm();
    });
});
</script>
@include('partials.organogram-filters-js')
@endpush
