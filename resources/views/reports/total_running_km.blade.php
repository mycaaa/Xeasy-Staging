@extends("layouts.app")

@push("styles")
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
<style>
.estable{width:100%;border-collapse:collapse;font-size:11px}
.estable th{background:#15648C;color:#fff;padding:6px 4px;font-size:10px;text-align:center;vertical-align:middle;border:1px solid #ccc;white-space:nowrap}
.estable th.sub-th{background:#1a7aab;font-size:9px}
.estable td{padding:5px 4px;border:1px solid #ddd;vertical-align:middle;font-size:11px}
.estable tbody tr:hover{background:#f0f7ff}
.estable .grand-total-row td{background:#15648C !important;color:#fff;font-weight:700;text-align:center;border:1px solid #0d4f70}
.estable .deactivated-row{background:#FFFFA6 !important}
.td-num{text-align:right !important}.td-ctr{text-align:center !important}
.summary-strip{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
.summary-strip .s-item{background:#f0f7fb;border:1px solid #b8d9f0;border-radius:4px;padding:4px 10px;font-size:11px;color:#333}
.summary-strip .s-item span{font-weight:700;color:#15648C}
.offcanvas-header.header-ligth{background:#15648C}
.offcanvas-header.header-ligth .offcanvas-title{color:#fff}
.offcanvas-header.header-ligth .btn-close{filter:invert(1)}
.select2-container{width:100% !important}
.select2-container--default .select2-selection--multiple{min-height:34px;border-color:#ced4da;font-size:12px}
.select2-container--default .select2-selection--multiple .select2-selection__choice{font-size:11px;background:#15648C;color:#fff;border:none;border-radius:2px;padding:1px 5px}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove{color:#fff}
</style>
@endpush

@section("content")
<div class="page-content"><div class="container-fluid"><div class="row"><div class="col-xl-12">
<div class="card card-height-100">

<div class="card-header align-items-center d-flex">
    <h4 class="card-title mb-0 flex-grow-1"><i class="ri-car-line me-1"></i> Employee-Wise Total Running KM <small class="text-muted fs-6">(2024-25 onwards)</small></h4>
    <div class="flex-shrink-0 ms-2">
        <button id="btnExport" class="btn btn-sm btn-success" disabled><i class="ri-file-excel-2-line me-1"></i> Export Excel</button>
    </div>
</div>

<div class="card-body pb-3 pt-0">

<div class="bg-light-subtle border-top-dashed border border-start-0 border-end-0 border-bottom-dashed py-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <input type="text" id="f_search" class="form-control w-auto" placeholder="Search name / code..." aria-label="Search name / code" style="min-width:200px;">
        <a class="text-primary position-relative d-flex align-items-center ms-2" role="button" data-bs-toggle="offcanvas" data-bs-target="#rkFiltersCanvas">
            <i class="ri-equalizer-line fs-16 me-1"></i><span>More Filters</span>
            <span id="activeFilterCount" class="badge bg-danger-subtle text-danger ms-2" style="display:none;">0</span>
        </a>
        <a class="text-primary d-flex align-items-center ms-2" role="button" id="btnClearFilters">
            <i class="ri-close-circle-line fs-16 me-1"></i><span>Clear</span>
        </a>
        <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill ms-auto" id="btnApplyFilters">
            <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2"></i>Search Data
        </button>
    </div>
</div>

{{-- Offcanvas --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="rkFiltersCanvas" aria-labelledby="rkFiltersCanvasLabel">
    <div class="offcanvas-header header-ligth">
        <h5 class="offcanvas-title" id="rkFiltersCanvasLabel">Additional Filters</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="row p-1">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Function</label>
                <select id="f_functions" class="form-select" multiple>
                    @foreach($functions as $fn)<option value="{{ $fn->id }}">{{ $fn->function_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Vertical</label>
                <select id="f_verticals" class="form-select" multiple>
                    @foreach($verticals as $vt)<option value="{{ $vt->id }}">{{ $vt->vertical_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Department</label>
                <select id="f_departments" class="form-select" multiple>
                    @foreach($departments ?? [] as $dep)<option value="{{ $dep->id }}">{{ $dep->department_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Sub-Department</label>
                <select id="f_sub_departments" class="form-select" multiple>
                    @foreach(($subDepartments ?? $sub_departments ?? []) as $sd)<option value="{{ $sd->id }}">{{ $sd->sub_department_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Business Unit (BU)</label>
                <select id="f_business_units" class="form-select" multiple>
                    @foreach($business_units ?? [] as $bu)<option value="{{ $bu->id }}">{{ $bu->business_unit_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Zone</label>
                <select id="f_zones" class="form-select" multiple>
                    @foreach($zones ?? [] as $zn)<option value="{{ $zn->id }}">{{ $zn->zone_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Region</label>
                <select id="f_regions" class="form-select" multiple>
                    @foreach($regions ?? [] as $rg)<option value="{{ $rg->id }}">{{ $rg->region_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Territory</label>
                <select id="f_territories" class="form-select" multiple>
                    @foreach($territories ?? [] as $tr)<option value="{{ $tr->id }}">{{ $tr->territory_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Loc / HQ</label>
                <select id="f_hqs" class="form-select" multiple>
                    @foreach($hqs ?? [] as $hq)<option value="{{ $hq->id }}">{{ $hq->city_village_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Grade</label>
                <select id="f_grades" class="form-select" multiple>
                    @foreach($grades as $gr)<option value="{{ $gr->id }}">{{ $gr->grade_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Vehicle Policy</label>
                <select id="f_policies" class="form-select" multiple>
                    @foreach($policies as $pol)<option value="{{ $pol->PolicyId }}">{{ $pol->PolicyName }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Employee Status</label>
                <select id="f_emp_status" class="form-select">
                    <option value="">All</option><option value="A">Active</option><option value="D">Deactivated</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Vehicle Type</label>
                <select id="f_vehicle_type" class="form-select">
                    <option value="">All (2W + 4W)</option><option value="2W">2-Wheeler Only</option><option value="4W">4-Wheeler Only</option>
                </select>
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label fw-semibold" style="font-size:12px;">Employee(s)</label>
                <select id="f_employees" class="form-select" multiple>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->EmployeeID }}" data-status="{{ $emp->EmpStatus }}">{{ $emp->EmpCode }} - {{ $emp->Fname }} {{ $emp->Sname ?? "" }} {{ $emp->Lname }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row p-1">
            <div class="col-md-6"><button type="button" class="btn btn-outline-primary w-100" id="btnClearCanvas">Clear Filters</button></div>
            <div class="col-md-6"><button type="button" class="btn btn-primary w-100" data-bs-dismiss="offcanvas" id="btnApplyCanvas">Apply Filters</button></div>
        </div>
    </div>
</div>

<div id="summaryStrip" class="summary-strip" style="display:none;"></div>

<div id="placeholder" class="text-center py-4 text-muted" style="display:none;">
    <div class="spinner-border text-secondary" style="width:2rem;height:2rem;"></div>
    <div class="mt-2" style="font-size:13px;">Loading data...</div>
</div>

<div id="noData" class="text-center py-5 text-muted" style="display:none;">
    <i class="ri-inbox-line" style="font-size:2.5rem;"></i>
    <div class="mt-2">No records found.</div>
</div>

<div id="tableWrap" style="overflow-x:auto;display:none;">
    <table class="estable table table-bordered"><thead id="rkThead"></thead><tbody id="rkTbody"></tbody></table>
    <div class="d-flex align-items-center justify-content-between mt-2 flex-wrap gap-2">
        <div id="totalRecordsBar" style="color:#ee4545;font-size:12px;"></div>
        <div class="d-flex align-items-center gap-2">
            <label style="font-size:12px;margin-bottom:0;">Rows per page:</label>
            <select id="perPageSelect" class="form-select form-select-sm" style="width:80px;font-size:12px;">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="0">All</option>
            </select>
        </div>
        <nav><ul class="pagination pagination-sm mb-0" id="rkPagination"></ul></nav>
    </div>
</div>

</div>{{-- /card-body --}}
</div>{{-- /card --}}
</div></div></div></div>

{{-- ── Employee KM Detail Modal ─────────────────────────────────────────── --}}
@include('components.modals.employee-running-km')

@endsection

@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<script>
$(function () {

    // ── Select2 IDs (no Financial Year — removed from filters) ───────────
    var S2 = ['#f_functions','#f_verticals','#f_departments',
              '#f_sub_departments','#f_business_units','#f_zones',
              '#f_regions','#f_territories','#f_hqs',
              '#f_grades','#f_policies','#f_employees'];

    function initSelect2() {
        S2.forEach(function(id) {
            if (!$(id).data('select2')) {
                $(id).select2({ width:'100%', placeholder:'— Select —', allowClear:true, dropdownParent:$('#rkFiltersCanvas') });
            }
        });
    }
    document.getElementById('rkFiltersCanvas').addEventListener('shown.bs.offcanvas', initSelect2);
    initSelect2();

    // ── State ─────────────────────────────────────────────────────────────
    var lastYears    = [];
    var lastRows     = [];
    var filteredRows = [];
    var currentPage  = 1;
    var perPage      = 10;
    var vehicleType  = '';   // '', '2W', '4W'

    // ── Loader ────────────────────────────────────────────────────────────
    function showLoader() { $('#placeholder').show(); $('#tableWrap,#noData').hide(); }
    function hideLoader() { $('#placeholder').hide(); }

    // ── Collect filters ───────────────────────────────────────────────────
    // Default years: only 2024-25 onwards (server handles this when year_ids is empty)
    function collectFilters() {
        return {
            function_ids:       $('#f_functions').val()       || [],
            vertical_ids:       $('#f_verticals').val()       || [],
            department_ids:     $('#f_departments').val()     || [],
            sub_department_ids: $('#f_sub_departments').val() || [],
            bu_ids:             $('#f_business_units').val()  || [],
            zone_ids:           $('#f_zones').val()           || [],
            region_ids:         $('#f_regions').val()         || [],
            territory_ids:      $('#f_territories').val()     || [],
            hq_ids:             $('#f_hqs').val()             || [],
            grade_ids:          $('#f_grades').val()          || [],
            policy_ids:         $('#f_policies').val()        || [],
            employee_ids:       $('#f_employees').val()       || [],
            emp_status:         $('#f_emp_status').val()      || '',
            vehicle_type:       $('#f_vehicle_type').val()    || '',
            _token:             '{{ csrf_token() }}',
        };
    }

    // ── Badge ─────────────────────────────────────────────────────────────
    function updateBadge() {
        var n = 0;
        S2.forEach(function(id) { var v=$(id).val(); if(v&&v.length) n++; });
        if ($('#f_emp_status').val()) n++;
        if ($('#f_vehicle_type').val()) n++;
        $('#activeFilterCount').text(n).toggle(n > 0);
    }

    // ── Format number ─────────────────────────────────────────────────────
    function fmt(n) {
        return (n && n > 0) ? Number(n).toLocaleString('en-IN') : '<span class="text-muted">-</span>';
    }

    // ── Build header ──────────────────────────────────────────────────────
    // Columns:
    //   Base (10): S.No | Code | Name | Status | Fn | Vt | Dept | SubDept | Grade | Policy
    //   2W pre-Apr'24 col : "Total RunKM Before Apr'24 (2W)"  — hidden when 4W-only
    //   4W pre-Apr'24 col : "Total RunKM Before Apr'24 (4W)"  — hidden when 2W-only
    //   Per year : 2W + 4W + Total  (or just 2W / just 4W when filtered)
    //   Grand    : 2W + 4W + Total  (or single col)
    function buildHeader(years) {
        vehicleType = $('#f_vehicle_type').val() || '';
        var show2wPcd = (vehicleType !== '4W');   // Before Apr'24 (2W) column
        var show4wPcd = (vehicleType !== '2W');   // Before Apr'24 (4W) column

        var base = ['S.No.','Emp Code','Employee Name','Status',
                    'Function','Vertical','Department','Sub-Dept','Grade','Policy'];
        if (show2wPcd) { base.push('Total RunKM<br>Before Apr\'24<br>(2W)'); }
        if (show4wPcd) { base.push('Total RunKM<br>Before Apr\'24<br>(4W)'); }

        var r1 = '', r2 = '';
        base.forEach(function(h) { r1 += '<th rowspan="2">' + h + '</th>'; });

        years.forEach(function(yr) {
            if (vehicleType === '2W') {
                r1 += '<th rowspan="2">' + yr + '<br><small>2W KM</small></th>';
            } else if (vehicleType === '4W') {
                r1 += '<th rowspan="2">' + yr + '<br><small>4W KM</small></th>';
            } else {
                r1 += '<th colspan="3">' + yr + '</th>';
                r2 += '<th class="sub-th">2W</th><th class="sub-th">4W</th><th class="sub-th">Total</th>';
            }
        });

        if (vehicleType === '2W') {
            r1 += '<th rowspan="2" style="background:#0d4f70;">Grand Total<br><small>2W</small></th>';
        } else if (vehicleType === '4W') {
            r1 += '<th rowspan="2" style="background:#0d4f70;">Grand Total<br><small>4W</small></th>';
        } else {
            r1 += '<th colspan="3" style="background:#0d4f70;">Grand Total</th>';
            r2 += '<th class="sub-th" style="background:#0d4f70;">2W</th>' +
                  '<th class="sub-th" style="background:#0d4f70;">4W</th>' +
                  '<th class="sub-th" style="background:#0d4f70;">Total</th>';
        }

        var thead = '<tr>' + r1 + '</tr>';
        if (r2) thead += '<tr>' + r2 + '</tr>';
        $('#rkThead').html(thead);
    }

    // ── Compute totals over ALL rows ──────────────────────────────────────
    function computeTotals(rows, years) {
        var g2 = 0, g4 = 0;
        var yt2 = new Array(years.length).fill(0);
        var yt4 = new Array(years.length).fill(0);
        rows.forEach(function(r) {
            // Pre-Apr'24 2W (from 2023-24 runningkm)
            if (vehicleType !== '4W') {
                g2 += parseFloat(r.km_2w_before_apr24 || 0);
            }
            // Pre-Apr'24 4W (from policy_change_details)
            if (vehicleType !== '2W') {
                g4 += (r.has_pcd && r.pcd_before_apr24) ? parseFloat(r.pcd_before_apr24) : 0;
            }
            years.forEach(function(yr, yi) {
                var k2 = parseFloat(r['km_2w_' + yi] || 0);
                var k4 = parseFloat(r['km_4w_' + yi] || 0);
                yt2[yi] += k2; yt4[yi] += k4;
                g2 += k2; g4 += k4;
            });
        });
        return { g2:g2, g4:g4, yt2:yt2, yt4:yt4 };
    }

    // ── Render one page ───────────────────────────────────────────────────
    function renderPage(rows, years, page) {
        var pp    = (perPage === 0) ? rows.length : perPage;
        var start = (page - 1) * pp;
        var slice = rows.slice(start, start + pp);
        var tot   = computeTotals(rows, years);
        var show2wPcd = (vehicleType !== '4W');
        var show4wPcd = (vehicleType !== '2W');
        var baseColspan = 10 + (show2wPcd ? 1 : 0) + (show4wPcd ? 1 : 0);

        var html = '';
        slice.forEach(function(r, idx) {
            var gi     = start + idx;
            var deact  = (r.EmpStatus === 'D');
            var rowCls = deact ? ' class="deactivated-row"' : (gi % 2 !== 0 ? ' style="background:#f9f9f9"' : '');
            var r2 = 0, r4 = 0, yrCells = '';

            years.forEach(function(yr, yi) {
                var k2 = parseFloat(r['km_2w_' + yi] || 0);
                var k4 = parseFloat(r['km_4w_' + yi] || 0);
                if (vehicleType === '2W') {
                    yrCells += '<td class="td-num">' + fmt(k2) + '</td>';
                    r2 += k2;
                } else if (vehicleType === '4W') {
                    yrCells += '<td class="td-num">' + fmt(k4) + '</td>';
                    r4 += k4;
                } else {
                    yrCells += '<td class="td-num">' + fmt(k2) + '</td>' +
                               '<td class="td-num">' + fmt(k4) + '</td>' +
                               '<td class="td-num">' + fmt(k2+k4) + '</td>';
                    r2 += k2; r4 += k4;
                }
            });

            // Add pre-Apr'24 KM to row totals
            var pre2W = (show2wPcd) ? parseFloat(r.km_2w_before_apr24 || 0) : 0;
            var pre4W = (show4wPcd && r.has_pcd && r.pcd_before_apr24) ? parseFloat(r.pcd_before_apr24) : 0;
            r2 += pre2W;
            r4 += pre4W;

            var badge = (r.EmpStatus === 'A')
                ? '<span class="badge bg-success" style="font-size:9px;">Active</span>'
                : '<span class="badge bg-warning text-dark" style="font-size:9px;">Deact.</span>';

            // Before Apr'24 (2W) cell — from 2023-24 runningkm
            var cell2wPcd = '';
            if (show2wPcd) {
                var v2w = parseFloat(r.km_2w_before_apr24 || 0);
                cell2wPcd = '<td class="td-num">' + (v2w > 0 ? Number(v2w).toLocaleString('en-IN') : '<span class="text-muted">-</span>') + '</td>';
            }

            // Before Apr'24 (4W) cell — from policy_change_details
            var cell4wPcd = '';
            if (show4wPcd) {
                var pcdVal = '<span class="text-muted">-</span>';
                if (r.has_pcd && r.pcd_before_apr24 && r.pcd_before_apr24 > 0) {
                    var tip = "4W KM before Apr'24: " + Number(r.pcd_before_apr24).toLocaleString('en-IN') +
                              " | After Apr'24: " + Number(r.pcd_after_apr24||0).toLocaleString('en-IN') +
                              " | Total: " + Number(r.pcd_total_run||0).toLocaleString('en-IN');
                    pcdVal = '<span title="' + tip + '" style="cursor:help;text-decoration:underline dotted;color:#15648C;font-weight:600;">' +
                             Number(r.pcd_before_apr24).toLocaleString('en-IN') + '</span>';
                }
                cell4wPcd = '<td class="td-num">' + pcdVal + '</td>';
            }

            var grandCells = '';
            if (vehicleType === '2W') {
                grandCells = '<td class="td-num" style="background:#fff8e1;font-weight:700;">' + fmt(r2) + '</td>';
            } else if (vehicleType === '4W') {
                grandCells = '<td class="td-num" style="background:#fff8e1;font-weight:700;">' + fmt(r4) + '</td>';
            } else {
                grandCells = '<td class="td-num" style="background:#fff8e1;font-weight:700;">' + fmt(r2) + '</td>' +
                             '<td class="td-num" style="background:#fff8e1;font-weight:700;">' + fmt(r4) + '</td>' +
                             '<td class="td-num" style="background:#fff8e1;font-weight:700;">' + fmt(r2+r4) + '</td>';
            }

            html += '<tr' + rowCls + '>' +
                '<td class="td-ctr">' + (gi+1) + '</td>' +
                '<td class="td-ctr" style="white-space:nowrap;">' + (r.employee_code||'') + '</td>' +
                '<td style="white-space:nowrap;"><a href="javascript:void(0)" class="text-primary emp-km-link" data-id="' + (r.EmployeeID||'') + '" style="text-decoration:underline;">' + (r.employee_name||'') + '</a></td>' +
                '<td class="td-ctr">' + badge + '</td>' +
                '<td>' + (r.function_name||'<span class="text-muted">-</span>') + '</td>' +
                '<td>' + (r.vertical_name||'<span class="text-muted">-</span>') + '</td>' +
                '<td>' + (r.department_name||'<span class="text-muted">-</span>') + '</td>' +
                '<td>' + (r.sub_department_name||'<span class="text-muted">-</span>') + '</td>' +
                '<td>' + (r.grade_name||'<span class="text-muted">-</span>') + '</td>' +
                '<td>' + (r.policy_name||'<span class="text-muted">-</span>') + '</td>' +
                cell2wPcd + cell4wPcd + yrCells + grandCells + '</tr>';
        });

        // Grand total row
        var gtCells = '';
        years.forEach(function(yr, yi) {
            if (vehicleType === '2W') {
                gtCells += '<td>' + Number(tot.yt2[yi]).toLocaleString('en-IN') + '</td>';
            } else if (vehicleType === '4W') {
                gtCells += '<td>' + Number(tot.yt4[yi]).toLocaleString('en-IN') + '</td>';
            } else {
                gtCells += '<td>' + Number(tot.yt2[yi]).toLocaleString('en-IN') + '</td>' +
                           '<td>' + Number(tot.yt4[yi]).toLocaleString('en-IN') + '</td>' +
                           '<td>' + Number(tot.yt2[yi]+tot.yt4[yi]).toLocaleString('en-IN') + '</td>';
            }
        });
        var gtGrand = '';
        if (vehicleType === '2W') {
            gtGrand = '<td>' + Number(tot.g2).toLocaleString('en-IN') + '</td>';
        } else if (vehicleType === '4W') {
            gtGrand = '<td>' + Number(tot.g4).toLocaleString('en-IN') + '</td>';
        } else {
            gtGrand = '<td>' + Number(tot.g2).toLocaleString('en-IN') + '</td>' +
                      '<td>' + Number(tot.g4).toLocaleString('en-IN') + '</td>' +
                      '<td>' + Number(tot.g2+tot.g4).toLocaleString('en-IN') + '</td>';
        }
        html += '<tr class="grand-total-row">' +
            '<td colspan="' + baseColspan + '" style="text-align:left;padding-left:10px;">Grand Total (' + rows.length + ' records)</td>' +
            gtCells + gtGrand + '</tr>';

        $('#rkTbody').html(html);
        renderPagination(rows.length, page);
    }

    // ── Pagination controls ───────────────────────────────────────────────
    function renderPagination(total, page) {
        var pp = (perPage === 0) ? total : perPage;
        var totalPages = (pp > 0) ? Math.ceil(total / pp) : 1;
        var from = (pp > 0) ? ((page-1)*pp+1) : 1;
        var to   = (pp > 0) ? Math.min(page*pp, total) : total;

        $('#totalRecordsBar').html('Showing <strong>' + from + '&ndash;' + to + '</strong> of <strong>' + total + '</strong> records');

        if (totalPages <= 1) { $('#rkPagination').html(''); return; }

        var html = '<li class="page-item ' + (page===1?'disabled':'') + '"><a class="page-link" href="#" data-p="' + (page-1) + '">&lsaquo;</a></li>';
        var delta = 2, prev = null;
        for (var i = 1; i <= totalPages; i++) {
            if (i===1 || i===totalPages || (i>=page-delta && i<=page+delta)) {
                if (prev !== null && i-prev > 1) html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                html += '<li class="page-item ' + (i===page?'active':'') + '"><a class="page-link" href="#" data-p="' + i + '">' + i + '</a></li>';
                prev = i;
            }
        }
        html += '<li class="page-item ' + (page===totalPages?'disabled':'') + '"><a class="page-link" href="#" data-p="' + (page+1) + '">&rsaquo;</a></li>';
        $('#rkPagination').html(html);
    }

    // ── Full render (header + body + summary) ─────────────────────────────
    function renderAll(rows, years, page) {
        currentPage = page || 1;
        if (!rows.length) {
            $('#tableWrap').hide(); $('#noData').show();
            $('#summaryStrip').hide(); $('#btnExport').prop('disabled', true);
            return;
        }
        buildHeader(years);
        renderPage(rows, years, currentPage);
        $('#tableWrap').show(); $('#noData').hide();
        $('#btnExport').prop('disabled', false);

        var tot    = computeTotals(rows, years);
        var active = rows.filter(function(r){ return r.EmpStatus==='A'; }).length;
        var deact  = rows.filter(function(r){ return r.EmpStatus==='D'; }).length;
        var pcdCnt = rows.filter(function(r){ return r.has_pcd; }).length;
        $('#summaryStrip').html(
            '<div class="s-item">Total: <span>' + rows.length + '</span></div>' +
            '<div class="s-item">Active: <span>' + active + '</span></div>' +
            '<div class="s-item">Deactivated: <span>' + deact + '</span></div>' +
            '<div class="s-item">With Pre-Apr\'24 Data: <span>' + pcdCnt + '</span></div>' +
            '<div class="s-item">Grand 2W KM: <span>' + Number(tot.g2).toLocaleString('en-IN') + '</span></div>' +
            '<div class="s-item">Grand 4W KM: <span>' + Number(tot.g4).toLocaleString('en-IN') + '</span></div>' +
            '<div class="s-item">Grand Total KM: <span>' + Number(tot.g2+tot.g4).toLocaleString('en-IN') + '</span></div>'
        ).show();
    }

    // ── Pagination click ──────────────────────────────────────────────────
    $(document).on('click', '#rkPagination .page-link', function(e) {
        e.preventDefault();
        var p = parseInt($(this).data('p'));
        if (!p || p < 1) return;
        var pp = (perPage===0) ? filteredRows.length : perPage;
        if (p > Math.ceil(filteredRows.length / pp)) return;
        currentPage = p;
        renderPage(filteredRows, lastYears, currentPage);
        $('html,body').animate({ scrollTop: $('#tableWrap').offset().top - 80 }, 150);
    });

    // ── Per-page change ───────────────────────────────────────────────────
    $('#perPageSelect').on('change', function() {
        perPage = parseInt($(this).val());
        currentPage = 1;
        renderPage(filteredRows, lastYears, 1);
    });

    // ── Fetch from server ─────────────────────────────────────────────────
    function fetchData() {
        showLoader();
        $.ajax({
            url:  '{{ route("reports.total-running-km.data") }}',
            type: 'POST',
            data: collectFilters(),
            success: function(res) {
                hideLoader();
                if (!res.success) { alert(res.message || 'Error'); return; }
                lastYears    = res.data.years || [];
                lastRows     = res.data.rows  || [];
                filteredRows = lastRows;
                currentPage  = 1;
                renderAll(filteredRows, lastYears, 1);
                updateBadge();
            },
            error: function(xhr) {
                hideLoader(); $('#noData').show();
                console.error(xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText);
            }
        });
    }

    // ── Auto-load ─────────────────────────────────────────────────────────
    fetchData();

    // ── Events ────────────────────────────────────────────────────────────
    $('#btnApplyFilters').on('click', fetchData);
    $('#btnApplyCanvas').on('click', function() { setTimeout(fetchData, 300); });

    function clearAll() {
        S2.forEach(function(id){ if($(id).data('select2')) $(id).val(null).trigger('change'); });
        $('#f_emp_status,#f_vehicle_type').val('');
        $('#f_search').val('');
        updateBadge();
    }
    $('#btnClearFilters').on('click', function(){ clearAll(); fetchData(); });
    $('#btnClearCanvas').on('click', clearAll);

    $(document).on('change', '#f_functions,#f_verticals,#f_departments,#f_sub_departments,' +
        '#f_grades,#f_policies,#f_employees,#f_emp_status,#f_vehicle_type', updateBadge);

    // Live search
    $('#f_search').on('input', function() {
        var q = $(this).val().trim().toLowerCase();
        filteredRows = q ? lastRows.filter(function(r) {
            return (r.employee_name||'').toLowerCase().indexOf(q) !== -1 ||
                   (r.employee_code||'').toLowerCase().indexOf(q) !== -1;
        }) : lastRows;
        currentPage = 1;
        renderAll(filteredRows, lastYears, 1);
    });

    // Export
    $('#btnExport').on('click', function() {
        var params = new URLSearchParams();
        var f = collectFilters();
        Object.keys(f).forEach(function(k) {
            var v = f[k];
            if (Array.isArray(v)) { v.forEach(function(val){ params.append(k+'[]', val); }); }
            else { params.append(k, v); }
        });
        window.location.href = '{{ route("reports.total-running-km.export") }}?' + params.toString();
    });

    // ── Employee KM Detail Modal (iframe) ────────────────────────────────
    $(document).on('click', '.emp-km-link', function() {
        var empId   = $(this).data('id');
        var empName = $(this).text();
        window.openEmpKmModal(empId, empName);
    });

    // Reset iframe when modal closes to stop any pending load
    document.getElementById('empKmModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('empKmModalFrame').src = 'about:blank';
    });

});
</script>
@endpush
