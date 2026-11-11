@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white"><i class="ri-briefcase-line me-2"></i>Activity Analytics Report</h5>
                <a href="javascript:void(0)" id="exportBtn" class="btn btn-sm btn-success">
                    <i class="ri-file-excel-line me-1"></i>Export Month-Wise
                </a>
            </div>

            {{-- Tabs --}}
            <ul class="nav nav-tabs" role="tablist" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tabActivityWise" role="tab">
                        <i class="ri-pie-chart-line me-1"></i>Activity-wise Analysis
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabDaily" role="tab">
                        <i class="ri-calendar-line me-1"></i>Daily Summary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabEmployee" role="tab">
                        <i class="ri-user-line me-1"></i>Employee Tracking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabPipeline" role="tab">
                        <i class="ri-flow-chart-line me-1"></i>Status Pipeline
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabPerformance" role="tab">
                        <i class="ri-bar-chart-2-line me-1"></i>Performance Metrics
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                {{-- TAB 1: Activity-wise Analysis --}}
                <div class="tab-pane fade show active" id="tabActivityWise" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Department</label>
                                <select id="deptFilter" class="form-select form-select-sm">
                                    <option value="">-- Select All --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Vertical</label>
                                <select id="verticalFilter" class="form-select form-select-sm">
                                    <option value="">-- Select All --</option>
                                    @foreach($verticals as $v)
                                        <option value="{{ $v->id }}">{{ $v->vertical_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Function</label>
                                <select id="functionFilter" class="form-select form-select-sm">
                                    <option value="">-- Select All --</option>
                                    @foreach($functions as $func)
                                        <option value="{{ $func->id }}">{{ $func->function_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Month</label>
                                <select id="monthFilter" class="form-select form-select-sm">
                                    <option value="">-- All Months --</option>
                                    <option value="4">April</option><option value="5">May</option>
                                    <option value="6">June</option><option value="7">July</option>
                                    <option value="8">August</option><option value="9">September</option>
                                    <option value="10">October</option><option value="11">November</option>
                                    <option value="12">December</option><option value="1">January</option>
                                    <option value="2">February</option><option value="3">March</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Claim Type</label>
                                <select id="claimTypeFilter" class="form-select form-select-sm">
                                    <option value="">-- Select All --</option>
                                    @foreach($claimTypes as $ct)
                                        <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-1">
                                <button class="btn btn-outline-secondary btn-sm px-2 text-nowrap d-flex align-items-center"
                                        type="button"
                                        data-bs-toggle="offcanvas"
                                        data-bs-target="#additionalFiltersCanvas"
                                        aria-controls="additionalFiltersCanvas">
                                    <i class="ri-equalizer-line me-1"></i>
                                    <span>More Filters</span>
                                    <span id="activeFilterCount" class="badge bg-danger-subtle text-danger ms-1">0</span>
                                </button>
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadActivityWise()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                                <button class="btn btn-secondary btn-sm" onclick="clearFilters()">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        </div>
                        <div id="activityWiseSummary" class="mb-3 p-2 bg-light rounded"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Activity Type</th>
                                        <th>Count</th>
                                        <th>Employees</th>
                                        <th>Claimed</th>
                                        <th>Verified</th>
                                        <th>Approved</th>
                                        <th>Financed</th>
                                        <th>% of Total</th>
                                    </tr>
                                </thead>
                                <tbody id="activityWiseBody" style="font-size:11px;">
                                    <tr><td colspan="9" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 2: Daily Summary --}}
                <div class="tab-pane fade" id="tabDaily" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadDailySummary()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Total Upload</th>
                                        <th>Claimed</th>
                                        <th>Verified</th>
                                        <th>Approved</th>
                                        <th>Financed</th>
                                        <th>Avg per Claim</th>
                                    </tr>
                                </thead>
                                <tbody id="dailyBody" style="font-size:11px;">
                                    <tr><td colspan="8" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 3: Employee Tracking --}}
                <div class="tab-pane fade" id="tabEmployee" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Department</label>
                                <select id="empDeptFilter" class="form-select form-select-sm">
                                    <option value="">-- Select All --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Top</label>
                                <select id="empTop" class="form-select form-select-sm">
                                    <option value="10">Top 10</option>
                                    <option value="20">Top 20</option>
                                    <option value="50">Top 50</option>
                                    <option value="0">All</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadEmployeeTracking()">
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
                                        <th>Code</th>
                                        <th>Department</th>
                                        <th>Activities</th>
                                        <th>Claimed Amt</th>
                                        <th>Verified Amt</th>
                                        <th>Approved Amt</th>
                                        <th>Financed Amt</th>
                                        <th>Pending</th>
                                    </tr>
                                </thead>
                                <tbody id="employeeBody" style="font-size:11px;">
                                    <tr><td colspan="10" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 4: Status Pipeline --}}
                <div class="tab-pane fade" id="tabPipeline" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadPipeline()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div id="pipelineSummary" class="row mb-3"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Amount</th>
                                        <th>% of Total</th>
                                        <th>Avg per Claim</th>
                                        <th>Employees Involved</th>
                                    </tr>
                                </thead>
                                <tbody id="pipelineBody" style="font-size:11px;">
                                    <tr><td colspan="7" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 5: Performance Metrics --}}
                <div class="tab-pane fade" id="tabPerformance" role="tabpanel">
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6 d-flex align-items-end gap-1">
                                <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadPerformance()">
                                    <i class="ri-search-line me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div id="performanceMetrics" class="row mb-3"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="bg-primary text-white" style="font-size:11px;">
                                    <tr>
                                        <th>#</th>
                                        <th>Metric</th>
                                        <th>Value</th>
                                        <th>Target</th>
                                        <th>Status</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody id="performanceBody" style="font-size:11px;">
                                    <tr><td colspan="6" class="text-center text-muted py-4">Click "Search" to load data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials.organogram-filters-drawer')

@push('scripts')
<script>
const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function getFilters() {
    const organogram = (typeof window.getOrganogramFilterValues === 'function')
        ? window.getOrganogramFilterValues()
        : {};
    return {
        department_id: $('#deptFilter').val(),
        vertical_id: $('#verticalFilter').val(),
        function_id: $('#functionFilter').val(),
        month: $('#monthFilter').val(),
        claim_type_id: $('#claimTypeFilter').val(),
        ...organogram
    };
}

function clearFilters() {
    $('#deptFilter, #verticalFilter, #functionFilter, #monthFilter, #claimTypeFilter').val('');
    loadActivityWise();
}

function loadActivityWise() {
    const filters = getFilters();
    $('#activityWiseBody').html('<tr><td colspan="9" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("activity-report.activity-wise") }}',
        method: 'GET',
        data: filters,
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                let totalCount = 0, totalClaimed = 0, totalFinanced = 0;

                res.data.forEach(row => {
                    totalCount += parseInt(row.count) || 0;
                    totalClaimed += parseFloat(row.total_claimed) || 0;
                    totalFinanced += parseFloat(row.total_financed) || 0;
                });

                res.data.forEach((row, i) => {
                    const pct = totalFinanced > 0 ? ((parseFloat(row.total_financed) || 0) / totalFinanced * 100).toFixed(2) : 0;
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td>${row.activity_type || '-'}</td>
                        <td>${row.count || 0}</td>
                        <td>${row.employee_count || 0}</td>
                        <td class="text-end">${Number(row.total_claimed || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_verified || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_approved || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_financed || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end fw-bold text-primary">${pct}%</td>
                    </tr>`;
                });

                html += `<tr class="table-warning fw-bold">
                    <td colspan="2">TOTAL</td>
                    <td>${totalCount}</td>
                    <td colspan="1"></td>
                    <td class="text-end">${totalClaimed.toLocaleString('en-IN')}</td>
                    <td class="text-end" colspan="4">100%</td>
                </tr>`;

                $('#activityWiseBody').html(html);
                $('#activityWiseSummary').html(`<div class="text-center"><strong>Total Activities: ${totalCount} | Total Claimed: ₹${totalClaimed.toLocaleString('en-IN')} | Total Financed: ₹${totalFinanced.toLocaleString('en-IN')}</strong></div>`);
            } else {
                $('#activityWiseBody').html('<tr><td colspan="9" class="text-center text-muted py-4">No data found</td></tr>');
            }
        },
        error: function() {
            $('#activityWiseBody').html('<tr><td colspan="9" class="text-center text-danger py-4">Error loading data</td></tr>');
        }
    });
}

function loadDailySummary() {
    $('#dailyBody').html('<tr><td colspan="8" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("activity-report.daily-summary") }}',
        method: 'GET',
        data: {},
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach((row, i) => {
                    const avgAmount = row.count > 0 ? (parseFloat(row.total_claimed) / row.count).toFixed(2) : 0;
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td>${row.date || '-'}</td>
                        <td>${row.count || 0}</td>
                        <td class="text-end">${Number(row.total_claimed || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_verified || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_approved || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_financed || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(avgAmount).toLocaleString('en-IN')}</td>
                    </tr>`;
                });
                $('#dailyBody').html(html);
            } else {
                $('#dailyBody').html('<tr><td colspan="8" class="text-center text-muted py-4">No data found</td></tr>');
            }
        }
    });
}

function loadEmployeeTracking() {
    const filters = {
        department_id: $('#empDeptFilter').val(),
        top_limit: $('#empTop').val(),
    };
    $('#employeeBody').html('<tr><td colspan="10" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("activity-report.employee-tracking") }}',
        method: 'GET',
        data: filters,
        success: function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach((row, i) => {
                    const pending = parseFloat(row.total_claimed || 0) - parseFloat(row.total_financed || 0);
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td>${row.employee_name || '-'}</td>
                        <td>${row.emp_code || '-'}</td>
                        <td>${row.department_name || '-'}</td>
                        <td>${row.count || 0}</td>
                        <td class="text-end">${Number(row.total_claimed || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_verified || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_approved || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${Number(row.total_financed || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end text-danger fw-bold">${Number(pending).toLocaleString('en-IN')}</td>
                    </tr>`;
                });
                $('#employeeBody').html(html);
            } else {
                $('#employeeBody').html('<tr><td colspan="10" class="text-center text-muted py-4">No data found</td></tr>');
            }
        }
    });
}

function loadPipeline() {
    $('#pipelineBody').html('<tr><td colspan="7" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("activity-report.pipeline") }}',
        method: 'GET',
        data: {},
        success: function(res) {
            if (res.success && res.data) {
                let html = '';
                let summaryHtml = '';
                const statuses = ['Claimed', 'Verified', 'Approved', 'Financed'];
                const colors = ['warning', 'info', 'primary', 'success'];

                statuses.forEach((status, idx) => {
                    const data = res.data[status.toLowerCase()] || {};
                    const pct = res.data.total_claimed > 0 ? ((data.amount || 0) / res.data.total_claimed * 100).toFixed(1) : 0;
                    const avgAmount = data.count > 0 ? (data.amount / data.count).toFixed(2) : 0;

                    html += `<tr>
                        <td>${idx + 1}</td>
                        <td><span class="badge bg-${colors[idx]}">${status}</span></td>
                        <td>${data.count || 0}</td>
                        <td class="text-end">${Number(data.amount || 0).toLocaleString('en-IN')}</td>
                        <td class="text-end">${pct}%</td>
                        <td class="text-end">${Number(avgAmount).toLocaleString('en-IN')}</td>
                        <td>${data.employees || 0}</td>
                    </tr>`;

                    summaryHtml += `<div class="col-md-3">
                        <div class="text-center p-2 border rounded">
                            <h4 class="mb-0 text-${colors[idx]}">${data.count || 0}</h4>
                            <small class="text-muted">${status} (₹${Number(data.amount || 0).toLocaleString('en-IN')})</small>
                        </div>
                    </div>`;
                });

                $('#pipelineBody').html(html);
                $('#pipelineSummary').html(summaryHtml);
            } else {
                $('#pipelineBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No data found</td></tr>');
            }
        }
    });
}

function loadPerformance() {
    $('#performanceBody').html('<tr><td colspan="6" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

    $.ajax({
        url: '{{ route("activity-report.performance") }}',
        method: 'GET',
        data: {},
        success: function(res) {
            if (res.success && res.data) {
                let html = '';
                const metrics = res.data;

                const verificationRate = metrics.claimed_count > 0 ? ((metrics.verified_count / metrics.claimed_count) * 100).toFixed(1) : 0;
                const approvalRate = metrics.verified_count > 0 ? ((metrics.approved_count / metrics.verified_count) * 100).toFixed(1) : 0;
                const financeRate = metrics.approved_count > 0 ? ((metrics.financed_count / metrics.approved_count) * 100).toFixed(1) : 0;
                const avgProcessingDays = metrics.avg_days_to_finance || 0;

                const perfMetrics = [
                    { name: 'Verification Rate', value: verificationRate + '%', target: '95%', status: verificationRate >= 95 ? 'success' : 'warning' },
                    { name: 'Approval Rate', value: approvalRate + '%', target: '90%', status: approvalRate >= 90 ? 'success' : 'warning' },
                    { name: 'Finance Rate', value: financeRate + '%', target: '85%', status: financeRate >= 85 ? 'success' : 'warning' },
                    { name: 'Avg Processing Days', value: avgProcessingDays.toFixed(1), target: '15 days', status: avgProcessingDays <= 15 ? 'success' : 'danger' },
                    { name: 'Total Activities', value: metrics.total_count || 0, target: '-', status: 'info' },
                    { name: 'Total Amount', value: '₹' + Number(metrics.total_amount || 0).toLocaleString('en-IN'), target: '-', status: 'info' },
                ];

                perfMetrics.forEach((m, i) => {
                    html += `<tr>
                        <td>${i + 1}</td>
                        <td><strong>${m.name}</strong></td>
                        <td><h5 class="mb-0">${m.value}</h5></td>
                        <td>${m.target}</td>
                        <td><span class="badge bg-${m.status}">${m.status.toUpperCase()}</span></td>
                        <td><small>${m.name === 'Verification Rate' ? `${metrics.verified_count}/${metrics.claimed_count}` : m.name === 'Approval Rate' ? `${metrics.approved_count}/${metrics.verified_count}` : m.name === 'Finance Rate' ? `${metrics.financed_count}/${metrics.approved_count}` : '-'}</small></td>
                    </tr>`;
                });

                $('#performanceBody').html(html);
            }
        }
    });
}

$(document).ready(function() {
    $('#exportBtn').on('click', function() {
        const filters = getFilters();
        const params = new URLSearchParams();
        Object.keys(filters).forEach(function(k) {
            var v = filters[k];
            if (Array.isArray(v)) { v.forEach(function(val){ params.append(k+'[]', val); }); }
            else if (v !== null && v !== undefined && v !== '') { params.append(k, v); }
        });
        window.location.href = '{{ route("activity-report.export-month-wise") }}?' + params.toString();
    });

    $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
        loadActivityWise();
    });
});
</script>
@include('partials.organogram-filters-js')
@endpush
@endsection
