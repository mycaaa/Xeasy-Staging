@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white"><i class="ri-bar-chart-2-line me-2"></i>Expense Type Wise Report</h5>
                    <div>
                        <a href="{{ route('expense-tree-report') }}" class="btn btn-sm btn-info text-white me-1">
                            <i class="ri-node-tree me-1"></i>Expense Tree Report
                        </a>
                        <a href="{{ route('expense-type-report.manager-grouped') }}" class="btn btn-sm btn-warning text-dark me-1">
                            <i class="ri-group-line me-1"></i>Manager Grouped Report
                        </a>
                        <a href="{{ route('company-multi-year-expense-report') }}" class="btn btn-sm btn-primary text-white me-1">
                            <i class="ri-calendar-line me-1"></i>Multi-Year Report
                        </a>
                        <a href="{{ route('expense-type-report.export') }}" id="exportBtn" class="btn btn-sm btn-success"
                            onclick="startExport(event)">
                            <i class="ri-file-excel-line me-1"></i>Export Detail
                        </a>
                        <a href="{{ route('expense-type-report.export-grouped') }}" id="exportGroupedBtn"
                            class="btn btn-sm btn-info" onclick="startGroupedExport(event)">
                            <i class="ri-pie-chart-line me-1"></i>Export Grouped
                        </a>
                    </div>

                </div>

                {{-- Tabs --}}
                <ul class="nav nav-tabs" role="tablist" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tabDetailReport" role="tab">
                            <i class="ri-list-check-2 me-1"></i>Detail Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabContributionReport" role="tab">
                            <i class="ri-pie-chart-2-line me-1"></i>Top Contribution
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    {{-- Tab 1: Detail Report --}}
                    <div class="tab-pane fade show active" id="tabDetailReport" role="tabpanel">
                        <div class="card-body">
                            {{-- Filters --}}
                            <div class="row g-2 mb-3 align-items-end">
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Financial Year</label>
                                    <select id="yearFilter" class="form-select form-select-sm">
                                        @foreach($years as $year)
                                            <option value="{{ $year->YearId }}" {{ $year->YearId == session('year_id') ? 'selected' : '' }}>{{ $year->Year }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Department</label>
                                    <select id="deptFilter" class="form-select form-select-sm">
                                        <option value="">-- Select All --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Vertical</label>
                                    <select id="verticalFilter" class="form-select form-select-sm">
                                        <option value="">-- Select All --</option>
                                        @foreach($verticals as $v)
                                            <option value="{{ $v->id }}">{{ $v->vertical_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">BU</label>
                                    <select id="buFilter" class="form-select form-select-sm">
                                        <option value="">-- Select All --</option>
                                        @foreach($businessUnits as $bu)
                                            <option value="{{ $bu->id }}">{{ $bu->business_unit_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Claim Type</label>
                                    <select id="claimTypeFilter" class="form-select form-select-sm">
                                        <option value="">-- Select All --</option>
                                        @foreach($claimTypes as $ct)
                                            <option value="{{ $ct->ClaimId }}">{{ $ct->ClaimName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Month</label>
                                    <select id="monthFilter" class="form-select form-select-sm">
                                        <option value="">-- Select All --</option>
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
                                <div class="col-xl-auto col-md-4 col-sm-6 d-flex align-items-end gap-2">
                                    <button class="btn btn-outline-secondary btn-sm px-2 text-nowrap d-flex align-items-center"
                                            type="button"
                                            data-bs-toggle="offcanvas"
                                            data-bs-target="#additionalFiltersCanvas"
                                            aria-controls="additionalFiltersCanvas">
                                        <i class="ri-equalizer-line me-1"></i>
                                        <span>More Filters</span>
                                        <span id="activeFilterCount" class="badge bg-danger-subtle text-danger ms-1">0</span>
                                    </button>
                                    <button class="btn btn-primary btn-sm px-3" onclick="loadData()">
                                        <i class="ri-search-line me-1"></i>Search
                                    </button>
                                </div>
                            </div>

                            {{-- Summary --}}
                            <div id="summary" class="mb-2 text-muted small"></div>

                            {{-- Pagination --}}
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div id="pageInfo" class="small text-muted"></div>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" id="prevBtn" onclick="changePage(-1)"
                                        disabled>← Prev</button>
                                    <span id="pageNum" class="mx-2 small fw-bold"></span>
                                    <button class="btn btn-sm btn-outline-primary" id="nextBtn" onclick="changePage(1)">Next
                                        →</button>
                                </div>
                            </div>

                            {{-- Table --}}
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover" id="reportTable">
                                    <thead class="bg-primary text-white" style="font-size:10px;">
                                        <tr>
                                            <th>#</th>
                                            <th>Emp Code</th>
                                            <th>Name</th>
                                            <th>Status</th>
                                            <th>Function</th>
                                            <th>Vertical</th>
                                            <th>Department</th>
                                            <th>Designation</th>
                                            <th>Grade</th>
                                            <th>BU</th>
                                            <th>Zone</th>
                                            <th>Region</th>
                                            <th>Territory</th>
                                            <th>Month</th>
                                            <th>Claim Code</th>
                                            <th>Bill Date</th>
                                            <th>Filled Date</th>
                                            <th class="text-end">Employee Amt</th>
                                            <th>Verify Date</th>
                                            <th class="text-end">Verify Amt</th>
                                            <th>Appr Date</th>
                                            <th class="text-end">Approver Amt</th>
                                            <th>Finance Date</th>
                                            <th class="text-end">Finance Amt</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reportBody" style="font-size:10px;">
                                        <tr>
                                            <td colspan="24" class="text-center text-muted py-4">Click "Search" to load data
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div> {{-- End of Detail Report Tab --}}

                    {{-- Tab 2: Top Contribution Report --}}
                    <div class="tab-pane fade show" id="tabContributionReport" role="tabpanel">
                        <div class="card-body">
                            {{-- Filters and Export --}}
                            <div class="row g-2 mb-3 align-items-end">
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Financial Year</label>
                                    <select id="contYearFilter" class="form-select form-select-sm">
                                        @foreach($years as $year)
                                            <option value="{{ $year->YearId }}" {{ $year->YearId == session('year_id') ? 'selected' : '' }}>{{ $year->Year }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Department</label>
                                    <select id="contDeptFilter" class="form-select form-select-sm">
                                        <option value="">-- Select All --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Vertical</label>
                                    <select id="contVerticalFilter" class="form-select form-select-sm">
                                        <option value="">-- Select All --</option>
                                        @foreach($verticals as $v)
                                            <option value="{{ $v->id }}">{{ $v->vertical_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">BU</label>
                                    <select id="contBuFilter" class="form-select form-select-sm">
                                        <option value="">-- Select All --</option>
                                        @foreach($businessUnits as $bu)
                                            <option value="{{ $bu->id }}">{{ $bu->business_unit_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">View By</label>
                                    <select id="contViewBy" class="form-select form-select-sm">
                                        <option value="claim_type">Claim Type</option>
                                        <option value="bu">Business Unit (BU)</option>
                                        <option value="region">Region</option>
                                        <option value="department">Department</option>
                                        <option value="grade">Grade</option>
                                        <option value="vertical">Vertical</option>
                                    </select>
                                </div>
                                <div class="col-xl col-md-4 col-sm-6">
                                    <label class="form-label small fw-bold">Top Records</label>
                                    <select id="contTopLimit" class="form-select form-select-sm">
                                        <option value="10">Top 10</option>
                                        <option value="15">Top 15</option>
                                        <option value="20">Top 20</option>
                                        <option value="50">Top 50</option>
                                        <option value="0">All</option>
                                    </select>
                                </div>
                                <div class="col-xl-auto col-md-4 col-sm-6 d-flex align-items-end gap-2">
                                    <button class="btn btn-primary btn-sm flex-grow-1" onclick="loadContributionData()">
                                        <i class="ri-search-line me-1"></i>Search
                                    </button>
                                    <button class="btn btn-success btn-sm" id="contExportBtn"
                                        onclick="exportContributionData()" title="Export to Excel">
                                        <i class="ri-file-excel-line"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Summary --}}
                            <div id="contSummary" class="mb-2 text-muted small"></div>

                            {{-- Table --}}
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover" id="contributionTable">
                                    <thead class="bg-primary text-white" style="font-size:11px;">
                                        <tr>
                                            <th>#</th>
                                            <th id="contCol1">Category</th>
                                            <th>Claim Count</th>
                                            <th>Claimed</th>
                                            <th>Verified</th>
                                            <th>Approved</th>
                                            <th>Financed</th>
                                            <th>% of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="contributionBody" style="font-size:11px;">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Click "Search" to load data
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div> {{-- End of Contribution Report Tab --}}
                </div>
            </div>
        </div>
    </div>

    @include('partials.organogram-filters-drawer')

@endsection
@push('scripts')
{{-- 13 Nov 2026: moved here from the page body; it uses jQuery, which loads at the end of the page ("$ is not defined") --}}
    <script>
        const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        let currentPage = 1;
        let lastPage = 1;

        function buildFilterQueryParams(filters) {
            const params = new URLSearchParams();
            Object.keys(filters).forEach(key => {
                const val = filters[key];
                if (Array.isArray(val)) {
                    val.forEach(item => {
                        if (item !== null && item !== undefined && item !== '') {
                            params.append(key + '[]', item);
                        }
                    });
                } else if (val !== null && val !== undefined && val !== '') {
                    params.append(key, val);
                }
            });
            return params.toString();
        }

        function startExport(e) {
            e.preventDefault();
            const btn = document.getElementById('exportBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i>Exporting...';
            btn.classList.add('disabled');

            const filters = getFilters();
            const qs = buildFilterQueryParams(filters);
            window.location.href = '{{ route("expense-type-report.export") }}?' + qs;

            setTimeout(function () {
                btn.innerHTML = originalHtml;
                btn.classList.remove('disabled');
            }, 3000);
        }

        function startGroupedExport(e) {
            e.preventDefault();
            const btn = document.getElementById('exportGroupedBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i>Exporting...';
            btn.classList.add('disabled');

            const filters = getFilters();
            const qs = buildFilterQueryParams(filters);
            window.location.href = '{{ route("expense-type-report.export-grouped") }}?' + qs;

            setTimeout(function () {
                btn.innerHTML = originalHtml;
                btn.classList.remove('disabled');
            }, 3000);
        }

        function getFilters() {
            const organogram = (typeof window.getOrganogramFilterValues === 'function')
                ? window.getOrganogramFilterValues()
                : {};

            const filters = {
                year_id: $('#yearFilter').val(),
                claim_type_id: $('#claimTypeFilter').val(),
                month: $('#monthFilter').val(),
                ...organogram
            };

            if ((!filters.department_ids || filters.department_ids.length === 0) && $('#deptFilter').val()) {
                filters.department_id = $('#deptFilter').val();
            }
            if ((!filters.vertical_ids || filters.vertical_ids.length === 0) && $('#verticalFilter').val()) {
                filters.vertical_id = $('#verticalFilter').val();
            }
            if ((!filters.bu_ids || filters.bu_ids.length === 0) && $('#buFilter').val()) {
                filters.bu_id = $('#buFilter').val();
            }

            return filters;
        }

        function changePage(delta) {
            currentPage += delta;
            if (currentPage < 1) currentPage = 1;
            if (currentPage > lastPage) currentPage = lastPage;
            loadData(false);
        }

        function loadData(resetPage) {
            if (resetPage !== false) currentPage = 1;
            const filters = getFilters();
            $('#reportBody').html('<tr><td colspan="24" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

            $.ajax({
                url: '{{ route("expense-type-report.data") }}',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', ...filters, page: currentPage, per_page: 100 },
                success: function (res) {
                    if (res.success && res.data.length > 0) {
                        lastPage = res.last_page;
                        let html = '';
                        let totalEmpAmt = 0, totalVerifyAmt = 0, totalApprAmt = 0, totalFinAmt = 0;
                        const startIdx = (res.page - 1) * res.per_page;

                        function fmtDate(d) {
                            if (!d || d === '0000-00-00' || d === '1970-01-01') return '-';
                            return d;
                        }

                        res.data.forEach((row, i) => {
                            totalEmpAmt += parseFloat(row.employee_amt) || 0;
                            totalVerifyAmt += parseFloat(row.verify_amt) || 0;
                            totalApprAmt += parseFloat(row.approver_amt) || 0;
                            totalFinAmt += parseFloat(row.finance_amt) || 0;
                            html += `<tr>
                            <td>${startIdx + i + 1}</td>
                            <td>${row.emp_code || ''}</td>
                            <td>${row.employee_name || ''}</td>
                            <td>${row.status || ''}</td>
                            <td>${row.function_name || '-'}</td>
                            <td>${row.vertical_name || '-'}</td>
                            <td>${row.department_name || '-'}</td>
                            <td>${row.designation_name || '-'}</td>
                            <td>${row.grade_name || '-'}</td>
                            <td>${row.business_unit_name || '-'}</td>
                            <td>${row.zone_name || '-'}</td>
                            <td>${row.region_name || '-'}</td>
                            <td>${row.territory_name || '-'}</td>
                            <td>${months[parseInt(row.month)] || row.month}</td>
                            <td>${row.claim_code || row.expense_type || ''}</td>
                            <td>${fmtDate(row.bill_date)}</td>
                            <td>${fmtDate(row.filled_date)}</td>
                            <td class="text-end">${Number(row.employee_amt || 0).toLocaleString('en-IN')}</td>
                            <td>${fmtDate(row.verify_date)}</td>
                            <td class="text-end">${Number(row.verify_amt || 0).toLocaleString('en-IN')}</td>
                            <td>${fmtDate(row.appr_date)}</td>
                            <td class="text-end">${Number(row.approver_amt || 0).toLocaleString('en-IN')}</td>
                            <td>${fmtDate(row.financed_date)}</td>
                            <td class="text-end">${Number(row.finance_amt || 0).toLocaleString('en-IN')}</td>
                        </tr>`;
                        });
                        html += `<tr class="table-warning fw-bold">
                        <td colspan="17" class="text-end">PAGE TOTAL</td>
                        <td class="text-end">${totalEmpAmt.toLocaleString('en-IN')}</td>
                        <td></td>
                        <td class="text-end">${totalVerifyAmt.toLocaleString('en-IN')}</td>
                        <td></td>
                        <td class="text-end">${totalApprAmt.toLocaleString('en-IN')}</td>
                        <td></td>
                        <td class="text-end">${totalFinAmt.toLocaleString('en-IN')}</td>
                    </tr>`;
                        $('#reportBody').html(html);
                        $('#summary').text(`Total ${res.total} records`);
                        $('#pageInfo').text(`Showing ${startIdx + 1}-${startIdx + res.data.length} of ${res.total}`);
                        $('#pageNum').text(`Page ${res.page} / ${res.last_page}`);
                        $('#prevBtn').prop('disabled', res.page <= 1);
                        $('#nextBtn').prop('disabled', res.page >= res.last_page);
                    } else {
                        $('#reportBody').html('<tr><td colspan="24" class="text-center text-muted py-4">No data found</td></tr>');
                        $('#summary').text('');
                        $('#pageInfo').text('');
                        $('#pageNum').text('');
                        $('#prevBtn').prop('disabled', true);
                        $('#nextBtn').prop('disabled', true);
                    }
                },
                error: function () {
                    $('#reportBody').html('<tr><td colspan="24" class="text-center text-danger py-4">Error loading data</td></tr>');
                }
            });

            const qs = buildFilterQueryParams(filters);
            $('#exportBtn').attr('href', '{{ route("expense-type-report.export") }}?' + qs);
            $('#exportGroupedBtn').attr('href', '{{ route("expense-type-report.export-grouped") }}?' + qs);
        }

        // Contribution Report Functions
        function getContributionFilters() {
            const organogram = (typeof window.getOrganogramFilterValues === 'function')
                ? window.getOrganogramFilterValues()
                : {};

            const filters = {
                year_id: $('#contYearFilter').val(),
                department_id: $('#contDeptFilter').val(),
                vertical_id: $('#contVerticalFilter').val(),
                bu_id: $('#contBuFilter').val(),
                view_by: $('#contViewBy').val(),
                top_limit: $('#contTopLimit').val(),
                ...organogram
            };
            return filters;
        }

        function loadContributionData() {
            const filters = getContributionFilters();
            console.log('Contribution filters:', filters);
            $('#contributionBody').html('<tr><td colspan="7" class="text-center py-4"><i class="ri-loader-4-line ri-spin"></i> Loading...</td></tr>');

            $.ajax({
                url: '{{ route("expense-type-report.contribution") }}',
                method: 'GET',
                data: filters,
                success: function (res) {
                    console.log('Response:', res);
                    if (res.success && res.data && res.data.length > 0) {
                        const viewBy = filters.view_by;
                        const labels = {
                            'claim_type': 'Claim Type',
                            'bu': 'Business Unit (BU)',
                            'business_unit': 'Business Unit (BU)',
                            'region': 'Region',
                            'department': 'Department',
                            'grade': 'Grade',
                            'vertical': 'Vertical'
                        };
                        $('#contCol1').text(labels[viewBy] || 'Category');

                        let html = '';
                        let grandTotalClaimed = 0, grandTotalVerified = 0, grandTotalApproved = 0, grandTotalFinanced = 0;

                        res.data.forEach(row => {
                            grandTotalClaimed += parseFloat(row.total_claimed) || 0;
                            grandTotalVerified += parseFloat(row.total_verified) || 0;
                            grandTotalApproved += parseFloat(row.total_approved) || 0;
                            grandTotalFinanced += parseFloat(row.total_financed) || 0;
                        });

                        res.data.forEach((row, i) => {
                            const pct = grandTotalFinanced > 0 ? ((parseFloat(row.total_financed) || 0) / grandTotalFinanced * 100).toFixed(2) : 0;
                            html += `<tr>
                            <td>${i + 1}</td>
                            <td class="fw-bold">${row.category_name || '-'}</td>
                            <td class="text-center">${row.claim_count || 0}</td>
                            <td class="text-end">${Number(row.total_claimed).toLocaleString('en-IN')}</td>
                            <td class="text-end">${Number(row.total_verified).toLocaleString('en-IN')}</td>
                            <td class="text-end">${Number(row.total_approved).toLocaleString('en-IN')}</td>
                            <td class="text-end">${Number(row.total_financed).toLocaleString('en-IN')}</td>
                            <td class="text-end fw-bold text-primary">${pct}%</td>
                        </tr>`;
                        });

                        let totalClaimCount = res.data.reduce((sum, row) => sum + (parseInt(row.claim_count) || 0), 0);

                        html += `<tr class="table-warning fw-bold">
                        <td colspan="2" class="text-end">GRAND TOTAL</td>
                        <td class="text-center">${totalClaimCount}</td>
                        <td class="text-end">${grandTotalClaimed.toLocaleString('en-IN')}</td>
                        <td class="text-end">${grandTotalVerified.toLocaleString('en-IN')}</td>
                        <td class="text-end">${grandTotalApproved.toLocaleString('en-IN')}</td>
                        <td class="text-end">${grandTotalFinanced.toLocaleString('en-IN')}</td>
                        <td class="text-end">100%</td>
                    </tr>`;

                        $('#contributionBody').html(html);
                        $('#contSummary').text(`Total records: ${res.data.length}`);
                    } else {
                        $('#contributionBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No data found</td></tr>');
                        $('#contSummary').text('');
                    }
                },
                error: function (xhr, status, error) {
                    console.log('Error:', error, xhr.responseJSON);
                    $('#contributionBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Error loading data. Check browser console.</td></tr>');
                }
            });
        }

        function exportContributionData() {
            const filters = getContributionFilters();
            const qs = buildFilterQueryParams(filters);
            window.location.href = '{{ route("expense-type-report.export-contribution") }}?' + qs;
        }

        // Auto load detail report on page load and on organogram changes
        $(document).ready(function () {
            loadData();

            $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
                loadData(true);
            });
        });
    </script>
@endpush


@push('scripts')
    @include('partials.organogram-filters-js')
@endpush