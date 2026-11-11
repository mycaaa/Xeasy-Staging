{{-- resources/views/reports/ledger-summary.blade.php --}}

@extends('layouts.app')

@section('styles')
    <style>
        .estable {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .estable th {
            background-color: #15648C;
            color: white;
            padding: 8px 6px;
            font-size: 11px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #ddd;
            white-space: nowrap;
        }
        .estable td {
            padding: 7px 6px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        .estable tbody tr:hover { background-color: #f5f5f5; }

        .snapshot-table th {
            background-color: #15648C;
            color: white;
            font-size: 11px;
            padding: 7px 6px;
            text-align: center;
            white-space: nowrap;
        }
        .snapshot-table td {
            font-size: 12px;
            padding: 6px 5px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        .snapshot-table tbody tr:hover { background-color: #f0f8ff; }

        #snapshotModal .modal-dialog { max-width: 1000px; }

        .snapshot-summary-bar {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .snap-stat { font-size: 12px; }
        .snap-stat span { font-weight: 700; color: #15648C; font-size: 14px; }

        /* date-filter pill in filter row */
        .date-filter-divider {
            width: 1px;
            background: #ddd;
            align-self: stretch;
            margin: 0 4px;
        }
        .as-of-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #eaf4fb;
            border: 1px solid #b8d9ef;
            border-radius: 4px;
            padding: 3px 8px;
            font-size: 12px;
            color: #15648C;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid mt-5">
        <div class="row mt-3">
            <div class="col-lg-12">
                <div class="card shadow">
                    <div class="card-header" style="background-color: #15648C; color: white; padding: 10px 15px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-white">Employee Ledger Summary</h5>
                            <span class="badge badge-light">Year: {{ session('year_id', date('Y')) }}</span>
                        </div>
                    </div>

                    <div class="card-body">

                        @if (!empty($errorMessage) || session('error'))
                            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                <i class="fa fa-exclamation-triangle me-1"></i> {{ $errorMessage ?? session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        {{-- Filter Section --}}
                        <div class="card mb-3">
                            <div class="card-body py-3">
                                <form method="GET" action="{{ route('expense-payments.ledger-summary') }}" id="mainFilterForm">
                                    @foreach(['function_ids', 'vertical_ids', 'department_ids', 'sub_department_ids', 'bu_ids', 'zone_ids', 'region_ids', 'territory_ids', 'hq_ids', 'policy_ids'] as $filterKey)
                                        @if(request()->filled($filterKey))
                                            @foreach((array)request($filterKey) as $fVal)
                                                <input type="hidden" name="{{ $filterKey }}[]" value="{{ $fVal }}">
                                            @endforeach
                                        @endif
                                    @endforeach
                                    <div class="row g-2 align-items-end">

                                        {{-- Search --}}
                                        <div class="col-md-3">
                                            <label class="form-label mb-1" style="font-size:12px;">Search Employee</label>
                                            <input type="text" name="search" id="searchInput" value="{{ request('search') }}"
                                                class="form-control form-control-sm" placeholder="Name or Employee Code" aria-label="Name or Employee Code">
                                        </div>

                                        {{-- As-of Date --}}
                                        <div class="col-md-3">
                                            <label class="form-label mb-1" style="font-size:12px;">
                                                <i class="fa fa-calendar-o text-primary"></i>
                                                Balance as of Date
                                                <span class="text-muted" style="font-size:10px;">(leave blank for current)</span>
                                            </label>
                                            <input type="text" id="snapshotDatePicker" class="form-control form-control-sm"
                                                placeholder="dd-mm-yyyy" aria-label="dd-mm-yyyy" autocomplete="off">
                                            {{-- hidden real value in Y-m-d --}}
                                            <input type="hidden" id="snapshotDate">
                                        </div>

                                        {{-- Buttons --}}
                                        <div class="col-md-6 d-flex gap-2 align-items-end flex-wrap">
                                            <button type="submit" class="btn btn-primary btn-sm" id="searchBtn">
                                                <i class="fa fa-search"></i> Search
                                            </button>
                                            <a href="{{ route('expense-payments.ledger-summary') }}"
                                                class="btn btn-secondary btn-sm">
                                                <i class="fa fa-times"></i> Reset
                                            </a>
                                            <button type="button" class="btn btn-info btn-sm" onclick="applyAsOfDate()" id="viewBalanceBtn">
                                                <i class="fa fa-calendar-check-o"></i> Apply Date Filter
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm d-none" onclick="clearAsOfDate()" id="clearDateBtn">
                                                <i class="fa fa-times-circle"></i> Clear Date Filter
                                            </button>
                                            <button type="button" class="btn btn-outline-primary btn-sm position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                                                <i class="fa fa-filter"></i> More Filters
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">0</span>
                                            </button>
                                            <button type="button" onclick="exportTable()" class="btn btn-success btn-sm" id="exportBtn">
                                                <i class="fa fa-download"></i> Export
                                            </button>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- As-of-date active banner --}}
                        <div id="asOfBanner" class="alert alert-info py-2 px-3 mb-2 d-none" style="font-size:12px;">
                            <i class="fa fa-calendar text-primary"></i>
                            Showing balances <strong>as of <span id="asOfBannerDate"></span></strong>
                            &nbsp;&mdash;&nbsp;
                            <a href="javascript:void(0)" onclick="clearAsOfDate()" class="text-danger fw-bold">
                                <i class="fa fa-times"></i> Clear &amp; show current balances
                            </a>
                        </div>

                        {{-- Table Section --}}
                        <div style="overflow-x: auto; position:relative;" id="tableWrapper">
                            {{-- Loading overlay — shown via .loader-visible class, not d-none --}}
                            <div id="tableLoader" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.82);z-index:10;align-items:center;justify-content:center;min-height:80px;">
                                <div class="text-center">
                                    <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                                    <div class="mt-2" style="font-size:13px;color:#15648C;">Loading as-of-date data…</div>
                                </div>
                            </div>
                            <table class="estable table table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width:30px;">S.No</th>
                                        <th style="width:160px;">Employee Details</th>
                                        <th style="width:80px;">Emp Code</th>
                                        <th style="width:120px;">Imprest Details</th>
                                        <th style="width:110px;">Advance Balance</th>
                                        <th style="width:110px;">Total Balance</th>
                                    </tr>
                                </thead>
                                <tbody id="mainTableBody">
                                    @php
                                        $page    = request('page', 1);
                                        $perPage = $ledgerRecords->perPage();
                                        $i       = ($page - 1) * $perPage + 1;
                                    @endphp

                                    @forelse($ledgerRecords as $record)
                                        @php
                                            $status     = $record->EmpStatus ?? 'A';
                                            $rowBgColor = $status != 'A' ? '#FFFFA6' : '';

                                            $ledgerSummary = $employeeBalances[$record->employee_id]
                                                ?? ['total_imprest' => 0, 'advance_balance' => 0, 'total_balance' => 0];
                                            $imprestTotal  = $ledgerSummary['total_imprest'];
                                            $advanceTotal  = $ledgerSummary['advance_balance'];
                                            $totalbalance  = $ledgerSummary['total_balance'];
                                        @endphp

                                        <tr style="background-color: {{ $rowBgColor }};">
                                            <td style="font-size:12px; text-align:center;">{{ $i }}</td>
                                            <td style="font-size:12px; text-align:center;">
                                                <a href="javascript:void(0)"
                                                    onclick="viewEmployeeDetails({{ $record->employee_id }})"
                                                    class="text-primary fw-bold"
                                                    style="text-decoration:underline;"
                                                    title="Click to view advances & adjustments">
                                                    {{ $record->employee_name }}
                                                </a>
                                                <div class="mt-1 d-flex justify-content-center gap-1 flex-wrap">
                                                    <a href="{{ url('/account-summary?e='.$record->employee_id.'&y=8') }}"
                                                        target="_blank"
                                                        class="badge bg-light text-primary border"
                                                        style="font-size:10px; text-decoration:none;"
                                                        title="Open Account Summary">
                                                        <i class="fa fa-book me-1"></i>Acct Summary
                                                    </a>
                                                    <a href="{{ url('/advance-adjustment-report?search='.$record->display_code) }}"
                                                        target="_blank"
                                                        class="badge bg-light text-info border"
                                                        style="font-size:10px; text-decoration:none;"
                                                        title="Open Advance Request & Adjustment Report">
                                                        <i class="fa fa-list-alt me-1"></i>Adv Report
                                                    </a>
                                                </div>
                                            </td>
                                            <td style="font-size:12px; text-align:center;">{{ $record->display_code }}</td>
                                            <td style="font-size:12px; text-align:center;">
                                                @if ($imprestTotal > 0)
                                                    <span>₹{{ number_format($imprestTotal) }}</span>
                                                    @if ($record->fixed_imprest_date_1)
                                                        <div class="text-muted" style="font-size:11px;">
                                                            {{ date('d-m-Y', strtotime($record->fixed_imprest_date_1)) }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td style="font-size:12px; text-align:center;">
                                                @if ($advanceTotal > 0)
                                                    <span>₹{{ number_format($advanceTotal) }}</span>
                                                @elseif ($advanceTotal < 0)
                                                    <span class="text-danger">₹{{ number_format($advanceTotal) }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td style="font-size:12px; text-align:center;">
                                                @if ($totalbalance > 0)
                                                    <strong class="text-success">₹{{ number_format($totalbalance) }}</strong>
                                                @elseif ($totalbalance < 0)
                                                    <strong class="text-danger">₹{{ number_format($totalbalance) }}</strong>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @php $i++; @endphp
                                    @empty
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:30px;">
                                                <i class="fa fa-info-circle"></i> No ledger records found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        <div id="paginationWrapper">
                        @if ($ledgerRecords->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $ledgerRecords->appends(request()->query())->links('pagination::bootstrap-4') }}
                            </div>
                        @endif

                        @if ($totalRecords > 0)
                            <div class="text-center mt-2" style="color:#ee4545; font-size:13px;">
                                Total Records: {{ number_format($totalRecords) }}
                            </div>
                        @endif
                        </div>

                        {{-- As-of-date pagination (shown when date filter is active) --}}
                        <div id="asOfPaginationWrapper" class="d-none">
                            <div class="d-flex justify-content-center mt-3" id="asOfPagination"></div>
                            <div class="text-center mt-2" style="color:#ee4545; font-size:13px;" id="asOfTotalInfo"></div>
                        </div>

                    </div>{{-- /card-body --}}
                </div>{{-- /card --}}
            </div>
        </div>
    </div>

    {{-- Include Modals --}}
    @include('reports.partials.ledger-modals')
    @include('partials.organogram-filters-drawer')
@endsection

@push('scripts')
<script src="{{ asset('vendor/cdn/xlsx@0.18.5/dist/xlsx.full.min.js') }}"></script>
@include('partials.organogram-filters-js')
<script>
/* ============================================================
   GLOBAL STATE
============================================================ */
let currentAsOfDate = null;
let currentEmployeeModalData = null;

/* ============================================================
   EMPLOYEE DETAILS MODAL (employee name click)
============================================================ */
function viewEmployeeDetails(employeeId) {
    const modalEl = document.getElementById('employeeDetailsModal');
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.querySelector('#employeeDetailsModal .modal-body').innerHTML =
        '<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><div class="mt-2 text-muted" style="font-size:13px;">Loading employee financial records...</div></div>';
    bsModal.show();

    $.ajax({
        url: "{{ url('get-employee-details') }}/" + employeeId,
        type: "GET",
        success: function (response) {
            const emp = response.employee || {};
            const sum = response.summary || {};
            const advances = response.advances || [];
            const ledger = response.ledger_entries || [];
            const urls = response.urls || {};

            // Cache for Excel exports
            currentEmployeeModalData = response;

            // Update modal title
            const headerNameEl = document.getElementById('empModalHeaderName');
            if (headerNameEl) {
                headerNameEl.textContent = (emp.name || 'Employee') + ' (Code: ' + (emp.code || emp.id) + ')';
            }

            const totalPaid = Number(sum.total_paid || 0);
            const totalAdj = Number(sum.total_adjusted || 0);
            const totalUnadj = Number(sum.total_unadjusted || 0);
            const totalImp = Number(sum.total_imprest || 0);
            const totalBal = Number(sum.total_balance || 0);

            let html = `
<div class="container-fluid px-1">
  <!-- Top Profile & Quick Redirect Bar -->
  <div class="card mb-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
    <div class="card-body py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <div class="fw-bold fs-6 text-dark">${escHtml(emp.name || 'Employee')}</div>
        <div class="text-muted small">Employee Code: <span class="badge bg-secondary">${escHtml(emp.code || emp.id)}</span></div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="${urls.account_summary || ('/account-summary?e=' + emp.id + '&y=8')}" 
           target="_blank" 
           class="btn btn-sm btn-primary shadow-sm"
           title="Open Full Account Summary in new tab">
          <i class="fa fa-book me-1"></i> Full Account Summary <i class="fa fa-external-link ms-1 small"></i>
        </a>
        <a href="${urls.advance_report || ('/advance-adjustment-report?search=' + (emp.code || ''))}" 
           target="_blank" 
           class="btn btn-sm btn-info text-white shadow-sm"
           title="Open Advance Request & Adjustment Report in new tab">
          <i class="fa fa-list-alt me-1"></i> Advance Report <i class="fa fa-external-link ms-1 small"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- KPI Summary Banner Cards -->
  <div class="row g-2 mb-3">
    <div class="col-6 col-md">
      <div class="p-2 border rounded text-center bg-white shadow-sm h-100">
        <div class="text-muted text-uppercase fw-semibold" style="font-size:10px;">Total Paid Advances</div>
        <div class="fw-bold fs-6 text-primary mt-1">₹${totalPaid.toLocaleString('en-IN')}</div>
      </div>
    </div>
    <div class="col-6 col-md">
      <div class="p-2 border rounded text-center bg-white shadow-sm h-100">
        <div class="text-muted text-uppercase fw-semibold" style="font-size:10px;">Total Adjusted</div>
        <div class="fw-bold fs-6 text-success mt-1">₹${totalAdj.toLocaleString('en-IN')}</div>
      </div>
    </div>
    <div class="col-6 col-md">
      <div class="p-2 border rounded text-center bg-white shadow-sm h-100">
        <div class="text-muted text-uppercase fw-semibold" style="font-size:10px;">Unadjusted Advance</div>
        <div class="fw-bold fs-6 ${totalUnadj > 0 ? 'text-danger' : 'text-success'} mt-1">₹${totalUnadj.toLocaleString('en-IN')}</div>
      </div>
    </div>
    <div class="col-6 col-md">
      <div class="p-2 border rounded text-center bg-white shadow-sm h-100">
        <div class="text-muted text-uppercase fw-semibold" style="font-size:10px;">Fixed Imprest</div>
        <div class="fw-bold fs-6 text-info mt-1">₹${totalImp.toLocaleString('en-IN')}</div>
      </div>
    </div>
    <div class="col-12 col-md">
      <div class="p-2 border rounded text-center bg-white shadow-sm h-100">
        <div class="text-muted text-uppercase fw-semibold" style="font-size:10px;">Total Outstanding</div>
        <div class="fw-bold fs-6 ${totalBal > 0 ? 'text-danger' : (totalBal < 0 ? 'text-warning' : 'text-success')} mt-1">₹${totalBal.toLocaleString('en-IN')}</div>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs & Excel Export Action Buttons -->
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <ul class="nav nav-tabs border-bottom-0 mb-0" id="empDetailTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="advances-tab" data-bs-toggle="tab" data-bs-target="#tab-advances" type="button" role="tab">
          <i class="fa fa-wallet me-1 text-primary"></i> Advances &amp; Adjustments Log (${advances.length})
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#tab-ledger" type="button" role="tab">
          <i class="fa fa-list-ol me-1 text-secondary"></i> Running Ledger Audit
        </button>
      </li>
    </ul>

    <div class="d-flex align-items-center gap-1">
      <button type="button" class="btn btn-sm btn-success shadow-sm" id="btnExportActiveTab" onclick="exportModalActiveTabExcel()" title="Export currently active tab to Excel">
        <i class="fa fa-file-excel-o me-1"></i> <span id="btnExportActiveTabLabel">Export Advances Log</span>
      </button>
      <button type="button" class="btn btn-sm btn-outline-success shadow-sm" onclick="exportModalBothTabsExcel()" title="Export both Advances Log and Running Ledger into one multi-sheet Excel file">
        <i class="fa fa-download me-1"></i> Export Both Tabs
      </button>
    </div>
  </div>

  <!-- Tab Content -->
  <div class="tab-content" id="empDetailTabsContent">
    <!-- TAB 1: ADVANCES & ADJUSTMENTS LOG -->
    <div class="tab-pane fade show active" id="tab-advances" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center py-1 px-2 mb-2 bg-light rounded border">
        <span class="text-muted" style="font-size:11px;"><i class="fa fa-info-circle text-primary me-1"></i> Complete record of advances disbursed and month-wise claim deductions</span>
        <button type="button" class="btn btn-sm btn-outline-success py-1 px-2" style="font-size:11px;" onclick="exportModalAdvancesExcel()" title="Export Advances & Adjustments Log to Excel">
          <i class="fa fa-file-excel-o me-1"></i> Export Advances Log (Excel)
        </button>
      </div>
      <div class="table-responsive border rounded bg-white" style="max-height: 450px; overflow-y: auto;">
        <table class="table table-bordered table-sm table-hover align-middle mb-0" style="font-size:11px;">
          <thead class="table-light text-center sticky-top" style="z-index: 1;">
            <tr>
              <th style="width:30px;">#</th>
              <th>Act ID</th>
              <th>Req ID</th>
              <th>Advance Type</th>
              <th>Payment Date</th>
              <th>Age</th>
              <th class="text-end">Paid Amount</th>
              <th class="text-end">Adjusted Amount</th>
              <th class="text-end">Unadjusted Balance</th>
              <th>Status</th>
              <th>Purpose / Remarks</th>
              <th>UTR Number</th>
            </tr>
          </thead>
          <tbody>`;

            if (!advances.length) {
                html += `<tr><td colspan="12" class="text-center py-4 text-muted"><i class="fa fa-info-circle me-1"></i> No advance records found for this employee.</td></tr>`;
            } else {
                let advIdx = 1;
                advances.forEach((adv) => {
                    const rowWarning = adv.is_over_2_months ? 'style="background-color:#fff3cd;"' : '';
                    let settlementsHtml = '';
                    if (adv.settlements && adv.settlements.length) {
                        settlementsHtml = '<div class="mt-1">';
                        adv.settlements.forEach((s) => {
                            settlementsHtml += `<span class="badge bg-success bg-opacity-10 text-success border border-success d-inline-block me-1" style="font-size:8px;">${escHtml(s.badge || ('Month: ' + s.month_name))}</span>`;
                        });
                        settlementsHtml += '</div>';
                    }

                    let remarkHtml = '';
                    if (adv.adjustment_remarks) {
                        remarkHtml = `<div class="text-success mt-1" style="font-size:9px;"><i class="fa fa-check me-1"></i>${escHtml(adv.adjustment_remarks)}</div>`;
                    }

                    html += `
            <tr ${rowWarning}>
              <td class="text-center">${advIdx++}</td>
              <td class="text-center fw-bold">${adv.activity_id}</td>
              <td class="text-center text-muted">${escHtml(adv.request_id)}</td>
              <td>
                <span class="badge bg-light text-dark border">${escHtml(adv.activity_type)}</span>
                ${adv.is_imprest ? '<span class="badge bg-info text-white ms-1" style="font-size:8px;">Imprest</span>' : ''}
              </td>
              <td class="text-center">${escHtml(adv.payment_date)}</td>
              <td class="text-center">
                ${adv.age_days}d
                ${adv.is_over_2_months ? '<span class="badge bg-danger ms-1" style="font-size:8px;">Over 2M</span>' : ''}
              </td>
              <td class="text-end fw-semibold text-primary">₹${Number(adv.paid_amount || 0).toLocaleString('en-IN')}</td>
              <td class="text-end text-success">
                <div class="fw-bold">₹${Number(adv.adjusted_amount || 0).toLocaleString('en-IN')}</div>
                ${settlementsHtml}
              </td>
              <td class="text-end fw-bold ${adv.unadjusted_balance > 0 ? 'text-danger' : 'text-success'}">
                ₹${Number(adv.unadjusted_balance || 0).toLocaleString('en-IN')}
              </td>
              <td class="text-center">
                <span class="badge ${adv.status_badge}" style="font-size:9px;">${escHtml(adv.status_label)}</span>
              </td>
              <td style="max-width:240px;" class="text-wrap">
                <div>${escHtml(adv.purpose)}</div>
                ${remarkHtml}
              </td>
              <td class="text-center text-muted" style="font-size:10px;">${escHtml(adv.utr_number)}</td>
            </tr>`;
                });
            }

            html += `
          </tbody>
          <tfoot class="table-light fw-bold" style="font-size:11px;">
            <tr>
              <td colspan="6" class="text-end">TOTAL:</td>
              <td class="text-end text-primary">₹${totalPaid.toLocaleString('en-IN')}</td>
              <td class="text-end text-success">₹${totalAdj.toLocaleString('en-IN')}</td>
              <td class="text-end ${totalUnadj > 0 ? 'text-danger' : 'text-success'}">₹${totalUnadj.toLocaleString('en-IN')}</td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- TAB 2: RUNNING LEDGER TRANSACTIONS -->
    <div class="tab-pane fade" id="tab-ledger" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center py-1 px-2 mb-2 bg-light rounded border">
        <span class="text-muted" style="font-size:11px;"><i class="fa fa-info-circle text-secondary me-1"></i> Chronological running ledger transactions (Credits, Debits & Fixed Imprest)</span>
        <button type="button" class="btn btn-sm btn-outline-success py-1 px-2" style="font-size:11px;" onclick="exportModalLedgerExcel()" title="Export Running Ledger Audit to Excel">
          <i class="fa fa-file-excel-o me-1"></i> Export Running Ledger (Excel)
        </button>
      </div>
      <div class="table-responsive border rounded bg-white" style="max-height: 450px; overflow-y: auto;">
        <table class="table table-bordered table-sm table-hover align-middle mb-0" style="font-size:11px;">
          <thead class="table-light text-center sticky-top" style="z-index: 1;">
            <tr>
              <th style="width:30px;">#</th>
              <th>Date</th>
              <th>Type / Particulars</th>
              <th class="text-end">Opening Bal</th>
              <th class="text-end text-success">Credit (+)</th>
              <th class="text-end text-danger">Debit (-)</th>
              <th class="text-end text-info" style="min-width:130px;">Financed Amount</th>
              <th class="text-end text-primary">Imprest</th>
              <th class="text-end fw-bold">Running Bal</th>
            </tr>
          </thead>
          <tbody>`;

            let runningBal = 0;
            let impSum = 0;
            let legSn = 1;

            ledger.forEach((row) => {
                if (row.source === "paid_advance" && (
                    (row.purpose && row.purpose.toLowerCase().includes('imprest')) ||
                    row.is_imprest === 1 || row.is_imprest === true
                )) {
                    return;
                }

                let credit = 0, debit = 0, rowImp = 0;
                let openBal = runningBal;
                if (row.source === "imprest") { 
                    rowImp = parseFloat(row.original_amount || 0); 
                    impSum += rowImp; 
                }
                if (row.source === "ledger_advance" || row.source === "paid_advance") { 
                    credit = parseFloat(row.original_amount || 0); 
                    runningBal += credit; 
                }
                if (row.source === "carried" || row.source === "adjustment") { 
                    debit = parseFloat(row.total_adjustment || 0); 
                    runningBal -= debit; 
                }
                let curFinal = impSum + runningBal;
                let dateStr = row.date ? new Date(row.date).toLocaleDateString('en-IN') : '-';

                const isAdjustment = (row.source === "carried" || row.source === "adjustment");
                let finAmtHtml = '<span class="text-muted">-</span>';

                if (isAdjustment) {
                    const fAmt = parseFloat(row.finance_amount || 0);
                    let fDateStr = '';
                    if (row.finance_date && row.finance_date !== '0000-00-00') {
                        try {
                            const dParts = String(row.finance_date).split(/[-T ]/);
                            if (dParts.length >= 3 && dParts[0].length === 4) {
                                fDateStr = dParts[2].padStart(2, '0') + '/' + dParts[1].padStart(2, '0') + '/' + dParts[0];
                            } else {
                                fDateStr = new Date(row.finance_date).toLocaleDateString('en-IN');
                            }
                        } catch(e) {
                            fDateStr = String(row.finance_date);
                        }
                    }

                    const fRemark = row.finance_remarks ? escHtml(row.finance_remarks) : '';

                    finAmtHtml = `<div class="d-flex flex-column text-end" style="line-height:1.25;">`;
                    if (fAmt > 0) {
                        finAmtHtml += `<div class="fw-bold text-info" style="font-size:11px;">₹${fAmt.toLocaleString('en-IN')}</div>`;
                    } else if (fDateStr || fRemark) {
                        finAmtHtml += `<div class="text-muted fw-semibold" style="font-size:11px;">-</div>`;
                    }
                    if (fDateStr) {
                        finAmtHtml += `<div class="text-muted" style="font-size:9px;">${fDateStr}</div>`;
                    }
                    if (fRemark) {
                        finAmtHtml += `<div class="text-success fst-italic mt-1" style="font-size:9px;line-height:1.2;"><i class="fa fa-check me-1"></i>${fRemark}</div>`;
                    }
                    if (!fAmt && !fDateStr && !fRemark) {
                        finAmtHtml = '<span class="text-muted">-</span>';
                    } else {
                        finAmtHtml += `</div>`;
                    }
                }

                html += `<tr>
                  <td class="text-center">${legSn++}</td>
                  <td class="text-center">${dateStr}</td>
                  <td>
                    <span class="text-capitalize fw-semibold">${escHtml((row.source || '').replace(/_/g, ' '))}</span>
                    ${row.purpose ? '<div class="text-muted small">' + escHtml(row.purpose) + '</div>' : ''}
                  </td>
                  <td class="text-end">₹${openBal.toLocaleString('en-IN')}</td>
                  <td class="text-end text-success">${credit > 0 ? '₹' + credit.toLocaleString('en-IN') : '-'}</td>
                  <td class="text-end text-danger">${debit > 0 ? '₹' + debit.toLocaleString('en-IN') : '-'}</td>
                  <td class="text-end">${finAmtHtml}</td>
                  <td class="text-end text-primary">${rowImp > 0 ? '₹' + rowImp.toLocaleString('en-IN') : '-'}</td>
                  <td class="text-end fw-bold ${curFinal > 0 ? 'text-danger' : (curFinal < 0 ? 'text-warning' : 'text-success')}">₹${curFinal.toLocaleString('en-IN')}</td>
                </tr>`;
            });

            if (!ledger.length) {
                html += `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa fa-info-circle me-1"></i> No transactions found.</td></tr>`;
            }

            html += `
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>`;

            document.querySelector('#employeeDetailsModal .modal-body').innerHTML = html;

            // Dynamically update export button labels on tab switch
            const tabAdv = document.getElementById('advances-tab');
            const tabLed = document.getElementById('ledger-tab');
            if (tabAdv && tabLed) {
                tabAdv.addEventListener('shown.bs.tab', function () {
                    const topBtn = document.getElementById('btnExportActiveTabLabel');
                    if (topBtn) topBtn.textContent = 'Export Advances Log';
                    const footBtn = document.getElementById('empModalFooterExportText');
                    if (footBtn) footBtn.textContent = 'Export Advances Log (Excel)';
                });
                tabLed.addEventListener('shown.bs.tab', function () {
                    const topBtn = document.getElementById('btnExportActiveTabLabel');
                    if (topBtn) topBtn.textContent = 'Export Running Ledger';
                    const footBtn = document.getElementById('empModalFooterExportText');
                    if (footBtn) footBtn.textContent = 'Export Running Ledger (Excel)';
                });
            }
        },
        error: function (xhr) {
            let msg = 'Error fetching employee details.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg += ' ' + xhr.responseJSON.message;
            }
            document.querySelector('#employeeDetailsModal .modal-body').innerHTML =
                '<div class="alert alert-danger py-3"><i class="fa fa-exclamation-triangle me-2"></i>' + escHtml(msg) + '</div>';
        }
    });
}

/* ============================================================
   EXCEL EXPORTS FOR EMPLOYEE FINANCIAL DETAILS MODAL
============================================================ */
function getAdvancesExportRows(data) {
    const emp = data.employee || {};
    const sum = data.summary || {};
    const advances = data.advances || [];

    const totalPaid = Number(sum.total_paid || 0);
    const totalAdj = Number(sum.total_adjusted || 0);
    const totalUnadj = Number(sum.total_unadjusted || 0);
    const totalImp = Number(sum.total_imprest || 0);
    const totalBal = Number(sum.total_balance || 0);

    const rows = [];

    // Title & Metadata
    rows.push(['EMPLOYEE ADVANCES & ADJUSTMENTS LOG']);
    rows.push(['Employee Name:', emp.name || '-', 'Employee Code:', emp.code || emp.id || '-', 'Export Date:', new Date().toLocaleDateString('en-IN')]);
    rows.push(['Total Paid Advances (₹):', totalPaid, 'Total Adjusted (₹):', totalAdj, 'Unadjusted Advance (₹):', totalUnadj]);
    rows.push(['Fixed Imprest (₹):', totalImp, 'Total Outstanding (₹):', totalBal]);
    rows.push([]);

    // Table Header
    rows.push([
        '#',
        'Act ID',
        'Req ID',
        'Advance Type',
        'Payment Date',
        'Age (Days)',
        'Over 2 Months',
        'Paid Amount (₹)',
        'Adjusted Amount (₹)',
        'Settlement Month(s)',
        'Unadjusted Balance (₹)',
        'Status',
        'Purpose',
        'Adjustment Remarks',
        'UTR Number'
    ]);

    let idx = 1;
    advances.forEach(function (adv) {
        let settlementsStr = '';
        if (adv.settlements && adv.settlements.length) {
            settlementsStr = adv.settlements.map(function (s) {
                let badge = s.badge || ('Month: ' + s.month_name);
                if (s.amount) {
                    badge += ' (₹' + Number(s.amount).toLocaleString('en-IN') + ')';
                }
                return badge;
            }).join(', ');
        }

        rows.push([
            idx++,
            adv.activity_id || '',
            adv.request_id || '-',
            adv.activity_type || 'Activity Advance',
            adv.payment_date || '-',
            adv.age_days || 0,
            adv.is_over_2_months ? 'Yes (Over 2M)' : 'No',
            Number(adv.paid_amount || 0),
            Number(adv.adjusted_amount || 0),
            settlementsStr || '-',
            Number(adv.unadjusted_balance || 0),
            adv.status_label || '-',
            adv.purpose || '-',
            adv.adjustment_remarks || '',
            adv.utr_number || '-'
        ]);
    });

    // Total Row
    rows.push([
        'TOTAL',
        '',
        '',
        '',
        '',
        '',
        '',
        totalPaid,
        totalAdj,
        '',
        totalUnadj,
        '',
        '',
        '',
        ''
    ]);

    return rows;
}

function getLedgerExportRows(data) {
    const emp = data.employee || {};
    const sum = data.summary || {};
    const ledger = data.ledger_entries || data.ledger || [];

    const totalImp = Number(sum.total_imprest || 0);
    const totalBal = Number(sum.total_balance || 0);

    const rows = [];

    // Title & Metadata
    rows.push(['RUNNING LEDGER AUDIT TRANSACTIONS']);
    rows.push(['Employee Name:', emp.name || '-', 'Employee Code:', emp.code || emp.id || '-', 'Export Date:', new Date().toLocaleDateString('en-IN')]);
    rows.push(['Fixed Imprest (₹):', totalImp, 'Total Outstanding Balance (₹):', totalBal]);
    rows.push([]);

    // Table Header
    rows.push([
        '#',
        'Date',
        'Type / Source',
        'Particulars / Purpose',
        'Opening Balance (₹)',
        'Credit (+) (₹)',
        'Debit (-) (₹)',
        'Financed Amount (₹)',
        'Financed Date',
        'Remarks',
        'Imprest (₹)',
        'Running Balance (₹)'
    ]);

    let runningBal = 0;
    let impSum = 0;
    let legSn = 1;
    let totalCredit = 0;
    let totalDebit = 0;
    let totalFinAmt = 0;

    ledger.forEach(function (row) {
        if (row.source === 'paid_advance' && (
            (row.purpose && row.purpose.toLowerCase().includes('imprest')) ||
            row.is_imprest === 1 || row.is_imprest === true
        )) {
            return;
        }

        let credit = 0, debit = 0, rowImp = 0;
        let openBal = runningBal;
        if (row.source === 'imprest') {
            rowImp = parseFloat(row.original_amount || 0);
            impSum += rowImp;
        }
        if (row.source === 'ledger_advance' || row.source === 'paid_advance') {
            credit = parseFloat(row.original_amount || 0);
            runningBal += credit;
            totalCredit += credit;
        }
        if (row.source === 'carried' || row.source === 'adjustment') {
            debit = parseFloat(row.total_adjustment || 0);
            runningBal -= debit;
            totalDebit += debit;
        }
        let curFinal = impSum + runningBal;
        let dateStr = row.date ? new Date(row.date).toLocaleDateString('en-IN') : '-';

        let sourceName = (row.source || '').replace(/_/g, ' ');
        sourceName = sourceName.charAt(0).toUpperCase() + sourceName.slice(1);

        const isAdj = (row.source === 'carried' || row.source === 'adjustment');
        let finAmt = isAdj ? parseFloat(row.finance_amount || 0) : 0;
        if (finAmt > 0) totalFinAmt += finAmt;

        let finDate = '';
        if (isAdj && row.finance_date && row.finance_date !== '0000-00-00') {
            try {
                const dParts = String(row.finance_date).split(/[-T ]/);
                if (dParts.length >= 3 && dParts[0].length === 4) {
                    finDate = dParts[2].padStart(2, '0') + '/' + dParts[1].padStart(2, '0') + '/' + dParts[0];
                } else {
                    finDate = new Date(row.finance_date).toLocaleDateString('en-IN');
                }
            } catch(e) {
                finDate = String(row.finance_date);
            }
        }
        let finRemark = isAdj ? (row.finance_remarks || '') : '';

        rows.push([
            legSn++,
            dateStr,
            sourceName,
            row.purpose || '-',
            openBal,
            credit > 0 ? credit : 0,
            debit > 0 ? debit : 0,
            finAmt > 0 ? finAmt : (isAdj ? 0 : ''),
            finDate || (isAdj ? '-' : ''),
            finRemark || (isAdj ? '-' : ''),
            rowImp > 0 ? rowImp : 0,
            curFinal
        ]);
    });

    // Total Row
    rows.push([
        'TOTAL / FINAL BALANCE',
        '',
        '',
        '',
        '',
        totalCredit,
        totalDebit,
        totalFinAmt > 0 ? totalFinAmt : '',
        '',
        '',
        impSum,
        impSum + runningBal
    ]);

    return rows;
}

function applyColWidths(ws, rows) {
    const colWidths = [];
    rows.forEach(function (r) {
        r.forEach(function (cell, colIdx) {
            const cellLen = (cell !== null && cell !== undefined) ? String(cell).length : 0;
            colWidths[colIdx] = Math.max(colWidths[colIdx] || 10, Math.min(cellLen + 3, 40));
        });
    });
    ws['!cols'] = colWidths.map(function (w) { return { wch: w }; });
}

function getSafeFileSlug(name) {
    return (name || 'Employee').replace(/[^a-zA-Z0-9_-]/g, '_');
}

function exportModalAdvancesExcel() {
    if (!currentEmployeeModalData) {
        alert('No employee financial data loaded to export.');
        return;
    }
    if (typeof XLSX === 'undefined') {
        alert('Excel library (SheetJS) is loading. Please try again in a few seconds.');
        return;
    }

    const emp = currentEmployeeModalData.employee || {};
    const rows = getAdvancesExportRows(currentEmployeeModalData);
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(rows);
    applyColWidths(ws, rows);
    XLSX.utils.book_append_sheet(wb, ws, 'Advances & Adjustments');

    const cleanName = getSafeFileSlug(emp.name);
    const empCode = emp.code || emp.id || 'code';
    const dateStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `Advances_Log_${empCode}_${cleanName}_${dateStr}.xlsx`);
}

function exportModalLedgerExcel() {
    if (!currentEmployeeModalData) {
        alert('No employee financial data loaded to export.');
        return;
    }
    if (typeof XLSX === 'undefined') {
        alert('Excel library (SheetJS) is loading. Please try again in a few seconds.');
        return;
    }

    const emp = currentEmployeeModalData.employee || {};
    const rows = getLedgerExportRows(currentEmployeeModalData);
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(rows);
    applyColWidths(ws, rows);
    XLSX.utils.book_append_sheet(wb, ws, 'Running Ledger Audit');

    const cleanName = getSafeFileSlug(emp.name);
    const empCode = emp.code || emp.id || 'code';
    const dateStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `Running_Ledger_Audit_${empCode}_${cleanName}_${dateStr}.xlsx`);
}

function exportModalBothTabsExcel() {
    if (!currentEmployeeModalData) {
        alert('No employee financial data loaded to export.');
        return;
    }
    if (typeof XLSX === 'undefined') {
        alert('Excel library (SheetJS) is loading. Please try again in a few seconds.');
        return;
    }

    const emp = currentEmployeeModalData.employee || {};
    const wb = XLSX.utils.book_new();

    // Sheet 1: Advances Log
    const advRows = getAdvancesExportRows(currentEmployeeModalData);
    const wsAdv = XLSX.utils.aoa_to_sheet(advRows);
    applyColWidths(wsAdv, advRows);
    XLSX.utils.book_append_sheet(wb, wsAdv, 'Advances & Adjustments');

    // Sheet 2: Running Ledger Audit
    const ledRows = getLedgerExportRows(currentEmployeeModalData);
    const wsLed = XLSX.utils.aoa_to_sheet(ledRows);
    applyColWidths(wsLed, ledRows);
    XLSX.utils.book_append_sheet(wb, wsLed, 'Running Ledger Audit');

    const cleanName = getSafeFileSlug(emp.name);
    const empCode = emp.code || emp.id || 'code';
    const dateStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `Financial_Details_${empCode}_${cleanName}_${dateStr}.xlsx`);
}

function exportModalActiveTabExcel() {
    const isLedgerTab = document.getElementById('ledger-tab') && document.getElementById('ledger-tab').classList.contains('active');
    if (isLedgerTab) {
        exportModalLedgerExcel();
    } else {
        exportModalAdvancesExcel();
    }
}


/* ============================================================
   AS-OF-DATE INLINE FILTER — replaces main table rows
============================================================ */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#snapshotDatePicker', {
            dateFormat: 'd-m-Y',
            maxDate: 'today',
            onChange: function(selectedDates) {
                if (selectedDates.length) {
                    const d = selectedDates[0];
                    document.getElementById('snapshotDate').value =
                        d.getFullYear() + '-' +
                        String(d.getMonth() + 1).padStart(2, '0') + '-' +
                        String(d.getDate()).padStart(2, '0');
                }
            }
        });
    }
});

function applyAsOfDate(page) {
    page = page || 1;
    const date   = document.getElementById('snapshotDate').value;
    const search = document.getElementById('searchInput').value;

    if (!date) { alert('Please select a date first.'); return; }

    currentAsOfDate = date;

    // Show loader using style.display (avoids !important conflict with d-none)
    const loader = document.getElementById('tableLoader');
    loader.style.display = 'flex';

    const organogram = typeof window.getOrganogramFilterValues === 'function' ? window.getOrganogramFilterValues() : {};
    const reqData = $.extend({ as_of_date: date, search: search, per_page: 20, page: page }, organogram);

    $.ajax({
        url: "{{ route('expense-payments.ledger-summary-as-of-date') }}",
        type: 'GET',
        data: reqData,
        success: function (res) {
            loader.style.display = 'none';
            renderAsOfRows(res);
            document.getElementById('asOfBannerDate').textContent = formatDateDisplay(date);
            document.getElementById('asOfBanner').classList.remove('d-none');
            document.getElementById('clearDateBtn').classList.remove('d-none');
        },
        error: function (xhr) {
            loader.style.display = 'none';
            let msg = 'Failed to load as-of-date data.';
            try { const r = JSON.parse(xhr.responseText); msg = r.message || r.error || msg; } catch(e) {}
            // Show error inline in the table so it's visible
            document.getElementById('mainTableBody').innerHTML =
                '<tr><td colspan="6" class="text-center text-danger py-3"><i class="fa fa-exclamation-triangle"></i> ' + escHtml(msg) + '</td></tr>';
            document.getElementById('paginationWrapper').classList.add('d-none');
            document.getElementById('asOfPaginationWrapper').classList.remove('d-none');
            document.getElementById('asOfTotalInfo').textContent = '';
            document.getElementById('asOfPagination').innerHTML = '';
        }
    });
}

function renderAsOfRows(res) {
    const data = res.data;
    let html = '';
    let sno  = (res.current_page - 1) * res.per_page + 1;

    if (!data.length) {
        html = '<tr><td colspan="6" class="text-center text-muted py-3">No records found for selected date.</td></tr>';
    } else {
        data.forEach(function (row) {
            const rowBg = row.emp_status !== 'A' ? 'background:#FFFFA6;' : '';
            let codes = (row.display_code || '').trim();
            if (/^\d+$/.test(codes)) codes = 'E' + codes.padStart(4, '0');
            const impH = row.imprest_total > 0 ? '&#8377;' + fmtNum(row.imprest_total) : '<span class="text-muted">-</span>';
            const advH = row.advance_total > 0 ? '&#8377;' + fmtNum(row.advance_total) : '<span class="text-muted">-</span>';
            const balH = row.total_balance > 0
                ? '<strong class="text-success">&#8377;' + fmtNum(row.total_balance) + '</strong>'
                : (row.total_balance < 0
                    ? '<strong class="text-danger">&#8377;' + fmtNum(row.total_balance) + '</strong>'
                    : '<span class="text-muted">-</span>');
            html += '<tr style="' + rowBg + '">'
                + '<td class="text-center" style="font-size:12px;">' + sno++ + '</td>'
                + '<td class="text-center" style="font-size:12px;">'
                +   '<a href="javascript:void(0)" onclick="viewEmployeeDetails(' + row.employee_id + ')" class="text-primary fw-bold" style="text-decoration:underline;">' + escHtml(row.employee_name) + '</a>'
                +   '<div class="mt-1 d-flex justify-content-center gap-1 flex-wrap">'
                +     '<a href="/account-summary?e=' + row.employee_id + '&y=8" target="_blank" class="badge bg-light text-primary border" style="font-size:10px;text-decoration:none;"><i class="fa fa-book me-1"></i>Acct Summary</a>'
                +     '<a href="/advance-adjustment-report?search=' + encodeURIComponent(row.display_code || '') + '" target="_blank" class="badge bg-light text-info border" style="font-size:10px;text-decoration:none;"><i class="fa fa-list-alt me-1"></i>Adv Report</a>'
                +   '</div>'
                + '</td>'
                + '<td class="text-center" style="font-size:12px;">' + escHtml(codes) + '</td>'
                + '<td class="text-center" style="font-size:12px;">' + impH + '</td>'
                + '<td class="text-center" style="font-size:12px;">' + advH + '</td>'
                + '<td class="text-center" style="font-size:12px;">' + balH + '</td>'
                + '</tr>';
        });
    }

    document.getElementById('mainTableBody').innerHTML = html;
    document.getElementById('paginationWrapper').classList.add('d-none');
    document.getElementById('asOfPaginationWrapper').classList.remove('d-none');
    document.getElementById('asOfTotalInfo').textContent = 'Total Records: ' + Number(res.total).toLocaleString('en-IN');

    // Render paginator
    const el = document.getElementById('asOfPagination');
    if (res.last_page <= 1) { el.innerHTML = ''; return; }
    let nav = '<nav><ul class="pagination mb-0">';
    nav += '<li class="page-item' + (res.current_page === 1 ? ' disabled' : '') + '"><a class="page-link" href="#" onclick="applyAsOfDate(' + (res.current_page - 1) + ');return false;">«</a></li>';
    for (let p = Math.max(1, res.current_page - 2); p <= Math.min(res.last_page, res.current_page + 2); p++) {
        nav += '<li class="page-item' + (p === res.current_page ? ' active' : '') + '"><a class="page-link" href="#" onclick="applyAsOfDate(' + p + ');return false;">' + p + '</a></li>';
    }
    nav += '<li class="page-item' + (res.current_page === res.last_page ? ' disabled' : '') + '"><a class="page-link" href="#" onclick="applyAsOfDate(' + (res.current_page + 1) + ');return false;">»</a></li>';
    nav += '</ul></nav>';
    el.innerHTML = nav;
}

function clearAsOfDate() {
    currentAsOfDate = null;
    const fp = document.getElementById('snapshotDatePicker')._flatpickr;
    if (fp) fp.clear();
    document.getElementById('snapshotDate').value = '';
    document.getElementById('asOfBanner').classList.add('d-none');
    document.getElementById('clearDateBtn').classList.add('d-none');
    const search = document.getElementById('searchInput').value;
    window.location.href = "{{ route('expense-payments.ledger-summary') }}" +
        (search ? '?search=' + encodeURIComponent(search) : '');
}

function exportTable() {
    const date   = document.getElementById('snapshotDate').value;
    const search = document.getElementById('searchInput').value;
    const organogram = typeof window.getOrganogramFilterValues === 'function' ? window.getOrganogramFilterValues() : {};
    let queryParams = new URLSearchParams();
    if (search) queryParams.set('search', search);
    $.each(organogram, function(k, v) {
        if (Array.isArray(v)) {
            v.forEach(val => queryParams.append(k + '[]', val));
        }
    });

    if (date) {
        queryParams.set('as_of_date', date);
        window.location.href = "{{ route('expense-payments.export-ledger-summary-as-of-date') }}?" + queryParams.toString();
    } else {
        window.location.href = "{{ route('expense-payments.export-ledger-summary') }}?" + queryParams.toString();
    }
}

// Handle organogram filters event listeners
$(document).on('organogramFiltersApplied', function(e, filters) {
    if (currentAsOfDate) {
        applyAsOfDate(1);
    } else {
        var form = $('#mainFilterForm');
        form.find('input[name^="function_ids"], input[name^="vertical_ids"], input[name^="department_ids"], input[name^="sub_department_ids"], input[name^="bu_ids"], input[name^="zone_ids"], input[name^="region_ids"], input[name^="territory_ids"], input[name^="hq_ids"], input[name^="policy_ids"]').remove();
        $.each(filters, function(k, v) {
            if (Array.isArray(v)) {
                $.each(v, function(i, val) {
                    form.append($('<input>', { type: 'hidden', name: k + '[]', value: val }));
                });
            }
        });
        form.submit();
    }
});

$(document).on('organogramFiltersReset', function() {
    if (currentAsOfDate) {
        applyAsOfDate(1);
    } else {
        var form = $('#mainFilterForm');
        form.find('input[name^="function_ids"], input[name^="vertical_ids"], input[name^="department_ids"], input[name^="sub_department_ids"], input[name^="bu_ids"], input[name^="zone_ids"], input[name^="region_ids"], input[name^="territory_ids"], input[name^="hq_ids"], input[name^="policy_ids"]').remove();
        form.submit();
    }
});

/* ============================================================
   BALANCE ADJUSTMENT FORM
============================================================ */
function adjustBalance(employeeId, currentBalance) {
    document.getElementById('adjustment_employee_id').value = employeeId;
    document.getElementById('current_balance').value = '₹ ' + parseFloat(currentBalance).toFixed(2);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('balanceAdjustmentModal')).show();
}

$('#balanceAdjustmentForm').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
        url: "{{ route('update.ledger-balance') }}",
        type: 'POST',
        data: $(this).serialize(),
        success: function (response) {
            if (response.success) {
                alert('Balance updated successfully!');
                bootstrap.Modal.getInstance(document.getElementById('balanceAdjustmentModal')).hide();
                location.reload();
            }
        },
        error: function (xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Something went wrong'));
        }
    });
});

/* ============================================================
   HELPERS
============================================================ */
function fmtNum(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}
function escHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
}
</script>
@endpush
