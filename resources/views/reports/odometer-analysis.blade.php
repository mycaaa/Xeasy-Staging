@extends('layouts.app')
@section('content')
<div class="page-content">
    @include('partials.organogram-filters-drawer')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white"><i class="ri-speedometer-2-line me-2"></i>Odometer Analysis Report</h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                        <i class="ri-equalizer-line me-1"></i> More Filters
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="activeFilterCount">0</span>
                    </button>
                    <a href="javascript:void(0)" id="exportMonthWiseBtn" class="btn btn-sm btn-warning">
                        <i class="ri-calendar-line me-1"></i>Export Month-Wise
                    </a>
                    <a href="javascript:void(0)" id="exportBtn" class="btn btn-sm btn-success">
                        <i class="ri-file-excel-line me-1"></i>Export All
                    </a>
                </div>
            </div>

            {{-- Tabs --}}
            <ul class="nav nav-tabs" role="tablist" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tabSummary" role="tab">
                        <i class="ri-bar-chart-2-line me-1"></i>Summary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabEmployee" role="tab">
                        <i class="ri-user-line me-1"></i>Employee Wise
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tab2W" role="tab">
                        <i class="ri-e-bike-2-line me-1"></i>2-Wheeler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tab4W" role="tab">
                        <i class="ri-car-line me-1"></i>4-Wheeler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabDept" role="tab">
                        <i class="ri-organization-chart me-1"></i>Department Wise
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabVertical" role="tab">
                        <i class="ri-layout-grid-fill me-1"></i>Vertical Wise
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                {{-- TAB 1: SUMMARY --}}
                <div class="tab-pane fade show active" id="tabSummary" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Department</label>
                                <select id="summaryDept" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vertical</label>
                                <select id="summaryVert" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($verticals as $vert)
                                        <option value="{{ $vert->id }}">{{ $vert->vertical_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Function</label>
                                <select id="summaryFunc" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($functions as $func)
                                        <option value="{{ $func->id }}">{{ $func->function_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Month</label>
                                <select id="summaryMonth" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vehicle Type</label>
                                <select id="summaryVehicle" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="2W">2-Wheeler</option>
                                    <option value="4W">4-Wheeler</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadSummary()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                                <button class="btn btn-secondary btn-sm" onclick="clearSummaryFilters()">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:12px;">
                                    <tr><th>Metric</th><th>Value</th></tr>
                                </thead>
                                <tbody id="summaryBody" style="font-size:12px;">
                                    <tr><td colspan="2" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 2: EMPLOYEE WISE --}}
                <div class="tab-pane fade" id="tabEmployee" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Department</label>
                                <select id="empDept" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vertical</label>
                                <select id="empVert" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($verticals as $vert)
                                        <option value="{{ $vert->id }}">{{ $vert->vertical_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Function</label>
                                <select id="empFunc" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($functions as $func)
                                        <option value="{{ $func->id }}">{{ $func->function_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Month</label>
                                <select id="empMonth" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vehicle Type</label>
                                <select id="empVehicle" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="2W">2-Wheeler</option>
                                    <option value="4W">4-Wheeler</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadEmployeeWise()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Employee</th>
                                        <th>Total Claims</th>
                                        <th>Total KM</th>
                                        <th>Avg KM</th>
                                        <th>2W KM</th>
                                        <th>4W KM</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="employeeBody" style="font-size:11px;">
                                    <tr><td colspan="8" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 3: 2-WHEELER --}}
                <div class="tab-pane fade" id="tab2W" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Department</label>
                                <select id="twoWDept" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vertical</label>
                                <select id="twoWVert" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($verticals as $vert)
                                        <option value="{{ $vert->id }}">{{ $vert->vertical_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Function</label>
                                <select id="twoWFunc" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($functions as $func)
                                        <option value="{{ $func->id }}">{{ $func->function_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Month</label>
                                <select id="twoWMonth" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadTwoWheeler()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Employee</th>
                                        <th>Claims</th>
                                        <th>Total KM</th>
                                        <th>Avg KM</th>
                                        <th>Rate/KM</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="twoWBody" style="font-size:11px;">
                                    <tr><td colspan="7" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 4: 4-WHEELER --}}
                <div class="tab-pane fade" id="tab4W" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Department</label>
                                <select id="fourWDept" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vertical</label>
                                <select id="fourWVert" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($verticals as $vert)
                                        <option value="{{ $vert->id }}">{{ $vert->vertical_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Function</label>
                                <select id="fourWFunc" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($functions as $func)
                                        <option value="{{ $func->id }}">{{ $func->function_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Month</label>
                                <select id="fourWMonth" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadFourWheeler()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Employee</th>
                                        <th>Claims</th>
                                        <th>Total KM</th>
                                        <th>Avg KM</th>
                                        <th>Rate/KM</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="fourWBody" style="font-size:11px;">
                                    <tr><td colspan="7" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 5: DEPARTMENT WISE --}}
                <div class="tab-pane fade" id="tabDept" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vertical</label>
                                <select id="deptVert" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($verticals as $vert)
                                        <option value="{{ $vert->id }}">{{ $vert->vertical_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Function</label>
                                <select id="deptFunc" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($functions as $func)
                                        <option value="{{ $func->id }}">{{ $func->function_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Month</label>
                                <select id="deptMonth" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vehicle Type</label>
                                <select id="deptVehicle" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="2W">2-Wheeler</option>
                                    <option value="4W">4-Wheeler</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadDeptWise()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Department</th>
                                        <th>Claims</th>
                                        <th>Total KM</th>
                                        <th>Avg KM</th>
                                        <th>2W KM</th>
                                        <th>4W KM</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="deptBody" style="font-size:11px;">
                                    <tr><td colspan="8" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 6: VERTICAL WISE --}}
                <div class="tab-pane fade" id="tabVertical" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Department</label>
                                <select id="vertDept" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Function</label>
                                <select id="vertFunc" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @foreach($functions as $func)
                                        <option value="{{ $func->id }}">{{ $func->function_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Month</label>
                                <select id="vertMonth" class="form-select form-select-sm">
                                    <option value="">-- All --</option>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vehicle Type</label>
                                <select id="vertVehicle" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="2W">2-Wheeler</option>
                                    <option value="4W">4-Wheeler</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadVerticalWise()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Vertical</th>
                                        <th>Claims</th>
                                        <th>Total KM</th>
                                        <th>Avg KM</th>
                                        <th>2W KM</th>
                                        <th>4W KM</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="vertBody" style="font-size:11px;">
                                    <tr><td colspan="8" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function fmt(n) { return Number(n || 0).toLocaleString('en-IN'); }
function fmtDec(n) { return Number(n || 0).toLocaleString('en-IN', {maximumFractionDigits: 2}); }

function clearSummaryFilters() {
    $('#summaryDept, #summaryVert, #summaryFunc, #summaryMonth, #summaryVehicle').val('');
    loadSummary();
}

function getActiveOrganogramFilters() {
    return window.getOrganogramFilterValues ? window.getOrganogramFilterValues() : {};
}

function loadSummary() {
    const data = {
        department_id: $('#summaryDept').val(),
        vertical_id: $('#summaryVert').val(),
        function_id: $('#summaryFunc').val(),
        months: $('#summaryMonth').val(),
        vehicle_type: $('#summaryVehicle').val(),
        ...getActiveOrganogramFilters()
    };
    $('#summaryBody').html('<tr><td colspan="2" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("odometer.summary") }}',
        method: 'GET',
        data: data,
        success: function(res) {
            if (res.success && res.data) {
                const d = res.data;
                if (d.error) {
                    $('#summaryBody').html('<tr><td colspan="2" class="text-center text-danger py-4">Query Error: ' + d.error + '<br>Year ID: ' + d.debug_year_id + ', Connection: ' + d.debug_conn + '</td></tr>');
                    return;
                }
                let html = '';
                html += `<tr><td><strong>Total Claims</strong></td><td>${fmt(d.total_claims)}</td></tr>`;
                html += `<tr><td><strong>Total KM</strong></td><td>${fmt(d.total_km)}</td></tr>`;
                html += `<tr><td><strong>Average KM per Claim</strong></td><td>${fmtDec(d.avg_km)}</td></tr>`;
                html += `<tr><td><strong>Max KM</strong></td><td>${fmt(d.max_km)}</td></tr>`;
                html += `<tr><td><strong>Min KM</strong></td><td>${fmt(d.min_km)}</td></tr>`;
                html += `<tr class="table-info"><td><strong>2-Wheeler Claims</strong></td><td>${fmt(d.claims_2w)}</td></tr>`;
                html += `<tr class="table-info"><td><strong>2-Wheeler KM</strong></td><td>${fmt(d.total_km_2w)}</td></tr>`;
                html += `<tr class="table-warning"><td><strong>4-Wheeler Claims</strong></td><td>${fmt(d.claims_4w)}</td></tr>`;
                html += `<tr class="table-warning"><td><strong>4-Wheeler KM</strong></td><td>${fmt(d.total_km_4w)}</td></tr>`;
                html += `<tr class="table-success"><td><strong>Total Amount</strong></td><td><strong>\u20B9${fmt(d.total_amount)}</strong></td></tr>`;
                $('#summaryBody').html(html);
            } else {
                $('#summaryBody').html('<tr><td colspan="2" class="text-center text-muted py-4">No data found</td></tr>');
            }
        },
        error: function(xhr) {
            $('#summaryBody').html('<tr><td colspan="2" class="text-center text-danger py-4">Error: ' + (xhr.responseJSON?.message || 'Unknown error') + '</td></tr>');
        }
    });
}

function loadEmployeeWise() {
    const data = {
        department_id: $('#empDept').val(),
        vertical_id: $('#empVert').val(),
        function_id: $('#empFunc').val(),
        months: $('#empMonth').val(),
        vehicle_type: $('#empVehicle').val(),
        ...getActiveOrganogramFilters()
    };
    $('#employeeBody').html('<tr><td colspan="8" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("odometer.employee-wise") }}',
        method: 'GET',
        data: data,
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach((row, i) => {
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td>${row.employee_name || '-'}</td>
                        <td>${fmt(row.total_claims)}</td>
                        <td class="text-end">${fmt(row.total_km)}</td>
                        <td class="text-end">${fmtDec(row.avg_km)}</td>
                        <td class="text-end">${fmt(row.km_2w)}</td>
                        <td class="text-end">${fmt(row.km_4w)}</td>
                        <td class="text-end">\u20B9${fmt(row.total_amount)}</td>
                    </tr>`;
                });
                $('#employeeBody').html(html);
            } else {
                $('#employeeBody').html('<tr><td colspan="8" class="text-center text-muted py-4">No data found</td></tr>');
            }
        },
        error: function(xhr) {
            $('#employeeBody').html('<tr><td colspan="8" class="text-center text-danger py-4">Error loading data</td></tr>');
        }
    });
}

function loadTwoWheeler() {
    const data = {
        department_id: $('#twoWDept').val(),
        vertical_id: $('#twoWVert').val(),
        function_id: $('#twoWFunc').val(),
        months: $('#twoWMonth').val(),
        ...getActiveOrganogramFilters()
    };
    $('#twoWBody').html('<tr><td colspan="7" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("odometer.two-wheeler") }}',
        method: 'GET',
        data: data,
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach((row, i) => {
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td>${row.employee_name || '-'}</td>
                        <td>${fmt(row.total_claims)}</td>
                        <td class="text-end">${fmt(row.total_km)}</td>
                        <td class="text-end">${fmtDec(row.avg_km)}</td>
                        <td class="text-end">\u20B9${fmtDec(row.avg_rate_per_km)}</td>
                        <td class="text-end">\u20B9${fmt(row.total_amount)}</td>
                    </tr>`;
                });
                $('#twoWBody').html(html);
            } else {
                $('#twoWBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No data found</td></tr>');
            }
        },
        error: function(xhr) {
            $('#twoWBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Error loading data</td></tr>');
        }
    });
}

function loadFourWheeler() {
    const data = {
        department_id: $('#fourWDept').val(),
        vertical_id: $('#fourWVert').val(),
        function_id: $('#fourWFunc').val(),
        months: $('#fourWMonth').val(),
        ...getActiveOrganogramFilters()
    };
    $('#fourWBody').html('<tr><td colspan="7" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("odometer.four-wheeler") }}',
        method: 'GET',
        data: data,
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach((row, i) => {
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td>${row.employee_name || '-'}</td>
                        <td>${fmt(row.total_claims)}</td>
                        <td class="text-end">${fmt(row.total_km)}</td>
                        <td class="text-end">${fmtDec(row.avg_km)}</td>
                        <td class="text-end">\u20B9${fmtDec(row.avg_rate_per_km)}</td>
                        <td class="text-end">\u20B9${fmt(row.total_amount)}</td>
                    </tr>`;
                });
                $('#fourWBody').html(html);
            } else {
                $('#fourWBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No data found</td></tr>');
            }
        },
        error: function(xhr) {
            $('#fourWBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Error loading data</td></tr>');
        }
    });
}

function loadDeptWise() {
    const data = {
        vertical_id: $('#deptVert').val(),
        function_id: $('#deptFunc').val(),
        months: $('#deptMonth').val(),
        vehicle_type: $('#deptVehicle').val(),
        ...getActiveOrganogramFilters()
    };
    $('#deptBody').html('<tr><td colspan="8" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("odometer.department-wise") }}',
        method: 'GET',
        data: data,
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach((row, i) => {
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td>${row.department_name || '-'}</td>
                        <td>${fmt(row.total_claims)}</td>
                        <td class="text-end">${fmt(row.total_km)}</td>
                        <td class="text-end">${fmtDec(row.avg_km)}</td>
                        <td class="text-end">${fmt(row.km_2w)}</td>
                        <td class="text-end">${fmt(row.km_4w)}</td>
                        <td class="text-end">\u20B9${fmt(row.total_amount)}</td>
                    </tr>`;
                });
                $('#deptBody').html(html);
            } else {
                $('#deptBody').html('<tr><td colspan="8" class="text-center text-muted py-4">No data found</td></tr>');
            }
        },
        error: function(xhr) {
            $('#deptBody').html('<tr><td colspan="8" class="text-center text-danger py-4">Error loading data</td></tr>');
        }
    });
}

function loadVerticalWise() {
    const data = {
        department_id: $('#vertDept').val(),
        function_id: $('#vertFunc').val(),
        months: $('#vertMonth').val(),
        vehicle_type: $('#vertVehicle').val(),
        ...getActiveOrganogramFilters()
    };
    $('#vertBody').html('<tr><td colspan="8" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("odometer.vertical-wise") }}',
        method: 'GET',
        data: data,
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach((row, i) => {
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td>${row.vertical_name || '-'}</td>
                        <td>${fmt(row.total_claims)}</td>
                        <td class="text-end">${fmt(row.total_km)}</td>
                        <td class="text-end">${fmtDec(row.avg_km)}</td>
                        <td class="text-end">${fmt(row.km_2w)}</td>
                        <td class="text-end">${fmt(row.km_4w)}</td>
                        <td class="text-end">\u20B9${fmt(row.total_amount)}</td>
                    </tr>`;
                });
                $('#vertBody').html(html);
            } else {
                $('#vertBody').html('<tr><td colspan="8" class="text-center text-muted py-4">No data found</td></tr>');
            }
        },
        error: function(xhr) {
            $('#vertBody').html('<tr><td colspan="8" class="text-center text-danger py-4">Error loading data</td></tr>');
        }
    });
}

// Export handler
$(document).ready(function() {
    $('#exportBtn').on('click', function() {
        const filters = {
            months: $('#summaryMonth').val() || '',
            department_id: $('#summaryDept').val() || '',
            vertical_id: $('#summaryVert').val() || '',
            vehicle_type: $('#summaryVehicle').val() || '',
            ...getActiveOrganogramFilters()
        };
        const query = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("odometer.export") }}?' + query;
    });

    $('#exportMonthWiseBtn').on('click', function() {
        const filters = {
            department_id: $('#summaryDept').val() || '',
            vertical_id: $('#summaryVert').val() || '',
            vehicle_type: $('#summaryVehicle').val() || '',
            ...getActiveOrganogramFilters()
        };
        const query = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("odometer.export-month-wise") }}?' + query;
    });

    $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
        const activeTab = $('.nav-tabs .nav-link.active').attr('href');
        if (activeTab === '#tabSummary') loadSummary();
        else if (activeTab === '#tabEmployee') loadEmployeeWise();
        else if (activeTab === '#tab2W') loadTwoWheeler();
        else if (activeTab === '#tab4W') loadFourWheeler();
        else if (activeTab === '#tabDept') loadDeptWise();
        else if (activeTab === '#tabVertical') loadVerticalWise();
    });
});
</script>
@include('partials.organogram-filters-js')
@endpush
