@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1 fw-semibold" style="color:#15648C;">
                            <i class="ri-group-line me-2"></i>Manager Grouped Expense Type Report
                        </h4>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 small">
                                <li class="breadcrumb-item"><a href="{{ route('expense-type-report') }}">Expense Type Report</a></li>
                                <li class="breadcrumb-item active">Manager Grouped</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('expense-tree-report') }}" class="btn btn-sm btn-info text-white">
                            <i class="ri-node-tree me-1"></i>Expense Tree Report
                        </a>
                        <a href="{{ route('expense-type-report') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-bar-chart-2-line me-1"></i>Expense Type Report
                        </a>
                        <a href="{{ route('company-multi-year-expense-report') }}" class="btn btn-sm btn-outline-primary">
                            <i class="ri-calendar-line me-1"></i>Multi-Year Report
                        </a>
                        <button id="downloadBtn" class="btn btn-sm btn-success" onclick="downloadCSV()" disabled>
                            <i class="ri-download-2-line me-1"></i>Download CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filter Card --}}
        <div class="card mb-3">
            <div class="card-header bg-primary text-white py-2">
                <h6 class="mb-0 text-white"><i class="ri-filter-3-line me-1"></i>Search Filters</h6>
            </div>
            <div class="card-body py-3">
                <div class="row g-3 align-items-end">
                    {{-- Manager Select2 --}}
                    <div class="col-md-5">
                        <label class="form-label small fw-bold mb-1">
                            Manager / Employee <span class="text-danger">*</span>
                        </label>
                        <select id="managerSelect" class="form-select form-select-sm" style="width:100%;">
                            @if($initialEmployee)
                                <option value="{{ $initialEmployee['id'] }}" selected>{{ $initialEmployee['text'] }}</option>
                            @endif
                        </select>
                    </div>

                    {{-- Financial Year --}}
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Financial Year</label>
                        <select id="yearSelect" class="form-select form-select-sm">
                            @foreach($years as $year)
                                <option value="{{ $year->YearId }}"
                                    {{ $year->YearId == $selectedYearId ? 'selected' : '' }}>
                                    {{ $year->Year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Month --}}
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Month</label>
                        <select id="monthSelect" class="form-select form-select-sm">
                            <option value="">— All Months —</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                        </select>
                    </div>

                    {{-- Buttons --}}
                    <div class="col-md-3 d-flex gap-2">
                        <button id="searchBtn" class="btn btn-primary btn-sm px-4" onclick="loadReport()">
                            <i class="ri-search-line me-1"></i>Search
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="resetFilters()">
                            <i class="ri-refresh-line me-1"></i>Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- KPI Summary Cards --}}
        <div class="row g-3 mb-3" id="kpiSection" style="display:none !important;">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #15648C !important;">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                style="width:42px;height:42px;background:rgba(21,100,140,0.12);">
                                <i class="ri-team-line fs-5" style="color:#15648C;"></i>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Total Reportees</p>
                                <h5 class="mb-0 fw-bold" id="kpiReportees">—</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #1abc9c !important;">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                style="width:42px;height:42px;background:rgba(26,188,156,0.12);">
                                <i class="ri-file-list-3-line fs-5" style="color:#1abc9c;"></i>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Total Records</p>
                                <h5 class="mb-0 fw-bold" id="kpiRows">—</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f39c12 !important;">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                style="width:42px;height:42px;background:rgba(243,156,18,0.12);">
                                <i class="ri-file-paper-line fs-5" style="color:#f39c12;"></i>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Total Claim Count</p>
                                <h5 class="mb-0 fw-bold" id="kpiClaims">—</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #2ecc71 !important;">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                style="width:42px;height:42px;background:rgba(46,204,113,0.12);">
                                <i class="ri-money-rupee-circle-line fs-5" style="color:#2ecc71;"></i>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Total Finance Amt (₹)</p>
                                <h5 class="mb-0 fw-bold" id="kpiAmount">—</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Report Table --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between py-2"
                style="background:#15648C;">
                <h6 class="mb-0 text-white"><i class="ri-table-line me-1"></i>Report Data</h6>
                <div class="d-flex align-items-center gap-2">
                    <input type="text" id="tableSearch" class="form-control form-control-sm"
                        placeholder="Search in table..." aria-label="Search in table" style="width:200px;"
                        oninput="filterTable(this.value)">
                </div>
            </div>
            <div class="card-body p-0">
                {{-- Loader --}}
                <div id="tableLoader" class="text-center py-5" style="display:none;">
                    <div class="spinner-border text-primary" role="status" style="width:2.5rem;height:2.5rem;"></div>
                    <p class="mt-2 text-muted small">Fetching data across all companies…</p>
                </div>

                {{-- Empty State --}}
                <div id="emptyState" class="text-center py-5">
                    <i class="ri-search-eye-line fs-1 text-muted"></i>
                    <p class="mt-2 text-muted">Select a manager and click Search to load the report.</p>
                </div>

                {{-- Table --}}
                <div id="tableWrapper" style="display:none; overflow-x:auto; max-height:70vh; overflow-y:auto;">
                    <table class="table table-sm table-bordered mb-0" id="reportTable"
                        style="font-size:11px; min-width:1600px;">
                        <thead style="background:#15648C; color:#fff; position:sticky; top:0; z-index:5;">
                            <tr>
                                <th style="min-width:40px;">#</th>
                                <th style="min-width:75px;">Company</th>
                                <th style="min-width:90px;">Emp Code</th>
                                <th style="min-width:140px;">Employee Name</th>
                                <th style="min-width:70px;">Status</th>
                                <th style="min-width:100px;">Function</th>
                                <th style="min-width:100px;">Vertical</th>
                                <th style="min-width:110px;">Department</th>
                                <th style="min-width:110px;">Designation</th>
                                <th style="min-width:70px;">Grade</th>
                                <th style="min-width:90px;">Zone</th>
                                <th style="min-width:90px;">Region</th>
                                <th style="min-width:90px;">Territory</th>
                                <th style="min-width:60px;">Month</th>
                                <th style="min-width:120px;">Claim Type</th>
                                <th class="text-end" style="min-width:120px;">Finance Amt (₹)</th>
                                <th class="text-center" style="min-width:80px;">Claims</th>
                            </tr>
                        </thead>
                        <tbody id="reportBody">
                        </tbody>
                        <tfoot id="reportFoot" style="background:#e9f3fb; font-weight:bold; position:sticky; bottom:0;">
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center py-2" id="tableFooterBar"
                style="display:none !important; font-size:12px; color:#666;">
                <span id="rowCount"></span>
                <span class="text-muted">Data from VNR + VVNR + Nursery companies</span>
            </div>
        </div>

    </div>{{-- container-fluid --}}
</div>{{-- page-content --}}

@push('scripts')
<script>
    // ── Config ────────────────────────────────────────────────────────────────
    const SEARCH_URL   = '{{ route("expense-type-report.search-managers") }}';
    const API_BASE     = '/api/expense-type-report/export-grouped-by-manager';
    let   reportData   = [];
    let   currentManagerId   = null;
    let   currentYearId      = null;
    let   currentMonthFilter = null;

    // ── Initialize Select2 ───────────────────────────────────────────────────
    $(function () {
        $('#managerSelect').select2({
            placeholder: 'Type employee code or name...',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: SEARCH_URL,
                dataType: 'json',
                delay: 300,
                data: params => ({ q: params.term }),
                processResults: data => ({ results: data.results }),
                cache: true,
            },
        });

        @if($selectedEmployeeId && $initialEmployee)
            // Auto-load if employee_id was preset in URL
            setTimeout(() => loadReport(), 200);
        @endif
    });

    // ── Load Report Data ─────────────────────────────────────────────────────
    function loadReport() {
        const managerId = $('#managerSelect').val();
        const yearId    = $('#yearSelect').val();
        const month     = $('#monthSelect').val();

        if (!managerId) {
            alert('Please select a Manager / Employee before searching.');
            return;
        }

        currentManagerId   = managerId;
        currentYearId      = yearId;
        currentMonthFilter = month;

        // UI: loading state
        $('#tableLoader').show();
        $('#emptyState').hide();
        $('#tableWrapper').hide();
        $('#tableFooterBar').hide();
        $('#kpiSection').hide();
        $('#searchBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Loading...');
        $('#downloadBtn').prop('disabled', true);

        let url = `${API_BASE}?employee_id=${managerId}&year_id=${yearId}&format=json`;
        if (month) url += `&month=${month}`;

        $.ajax({
            url: url,
            method: 'GET',
            success: function (res) {
                if (res.success) {
                    reportData = res.data || [];
                    renderTable(reportData);
                    renderKPIs(res);
                } else {
                    showError(res.message || 'Unknown error from API.');
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Server error. Please try again.';
                showError(msg);
            },
            complete: function () {
                $('#tableLoader').hide();
                $('#searchBtn').prop('disabled', false).html('<i class="ri-search-line me-1"></i>Search');
            }
        });
    }

    // ── Render Table ─────────────────────────────────────────────────────────
    function renderTable(data) {
        const tbody = $('#reportBody');
        const tfoot = $('#reportFoot');
        tbody.empty();
        tfoot.empty();

        if (!data || data.length === 0) {
            tbody.append('<tr><td colspan="17" class="text-center text-muted py-4">No records found for the selected criteria.</td></tr>');
            $('#tableWrapper').show();
            $('#emptyState').hide();
            $('#downloadBtn').prop('disabled', true);
            return;
        }

        let totalAmt = 0, totalClaims = 0;

        data.forEach((row, idx) => {
            const statusBadge = row.emp_status === 'A'
                ? '<span class="badge bg-success-subtle text-success">Active</span>'
                : '<span class="badge bg-danger-subtle text-danger">Inactive</span>';

            const companyBadgeColor = row.company === 'VNR' ? '#15648C'
                : row.company === 'VVNR' ? '#8e44ad'
                : '#16a085';

            const amt = parseFloat(row.total_finance_amt || 0);
            totalAmt    += amt;
            totalClaims += parseInt(row.claim_count || 0, 10);

            tbody.append(`
                <tr>
                    <td>${idx + 1}</td>
                    <td><span class="badge text-white" style="background:${companyBadgeColor};">${row.company}</span></td>
                    <td class="fw-semibold">${row.emp_code || ''}</td>
                    <td>${row.employee_name || ''}</td>
                    <td>${statusBadge}</td>
                    <td>${row.function || ''}</td>
                    <td>${row.vertical || ''}</td>
                    <td>${row.department || ''}</td>
                    <td>${row.designation || ''}</td>
                    <td>${row.grade || ''}</td>
                    <td>${row.zone || ''}</td>
                    <td>${row.region || ''}</td>
                    <td>${row.territory || ''}</td>
                    <td>${row.month || ''}</td>
                    <td>${row.claim_type || ''}</td>
                    <td class="text-end fw-semibold">₹ ${formatNum(amt)}</td>
                    <td class="text-center">${row.claim_count || 0}</td>
                </tr>
            `);
        });

        // Footer totals
        tfoot.append(`
            <tr>
                <td colspan="15" class="text-end fw-bold">Grand Total</td>
                <td class="text-end fw-bold text-primary">₹ ${formatNum(totalAmt)}</td>
                <td class="text-center fw-bold text-primary">${totalClaims}</td>
            </tr>
        `);

        $('#tableWrapper').show();
        $('#emptyState').hide();
        $('#tableFooterBar').show();
        $('#rowCount').text(`${data.length} record(s) | Total Finance: ₹ ${formatNum(totalAmt)}`);
        $('#downloadBtn').prop('disabled', false);
    }

    // ── Render KPI Cards ─────────────────────────────────────────────────────
    function renderKPIs(res) {
        const totalAmt    = (res.data || []).reduce((s, r) => s + parseFloat(r.total_finance_amt || 0), 0);
        const totalClaims = (res.data || []).reduce((s, r) => s + parseInt(r.claim_count || 0, 10), 0);

        $('#kpiReportees').text(res.reportee_count ?? '—');
        $('#kpiRows').text(res.total_rows ?? res.data?.length ?? '—');
        $('#kpiClaims').text(totalClaims.toLocaleString('en-IN'));
        $('#kpiAmount').text('₹ ' + formatNum(totalAmt));
        $('#kpiSection').css('display', 'flex').show();
    }

    // ── Download CSV ─────────────────────────────────────────────────────────
    function downloadCSV() {
        if (!currentManagerId) return;
        let url = `${API_BASE}?employee_id=${currentManagerId}&year_id=${currentYearId}&format=csv`;
        if (currentMonthFilter) url += `&month=${currentMonthFilter}`;
        window.open(url, '_blank');
    }

    // ── Table search filter ───────────────────────────────────────────────────
    function filterTable(q) {
        if (!q) {
            renderTable(reportData);
            return;
        }
        const ql = q.toLowerCase();
        const filtered = reportData.filter(row => {
            return Object.values(row).some(v => String(v || '').toLowerCase().includes(ql));
        });
        renderTable(filtered);
    }

    // ── Reset ─────────────────────────────────────────────────────────────────
    function resetFilters() {
        $('#managerSelect').val(null).trigger('change');
        $('#monthSelect').val('');
        reportData = [];
        currentManagerId = null;
        $('#tableWrapper').hide();
        $('#kpiSection').hide();
        $('#tableFooterBar').hide();
        $('#emptyState').show();
        $('#downloadBtn').prop('disabled', true);
        $('#tableSearch').val('');
    }

    // ── Show Error ────────────────────────────────────────────────────────────
    function showError(msg) {
        const tbody = $('#reportBody');
        tbody.empty().append(`<tr><td colspan="17" class="text-center text-danger py-4"><i class="ri-error-warning-line me-1"></i>${msg}</td></tr>`);
        $('#tableWrapper').show();
        $('#emptyState').hide();
    }

    // ── Format Numbers ────────────────────────────────────────────────────────
    function formatNum(n) {
        return parseFloat(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
</script>
@endpush
@endsection
