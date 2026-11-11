@extends('layouts.app') @section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Claim Stage Report</h4>
                        </div>
                        <div class="card-body pb-3 pt-0">
                            <div
                                class="bg-light-subtle border-top-dashed border border-start-0 border-end-0 border-bottom-dashed py-2">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div>
                                        <select class="form-select" id="reportType" name="reportType">
                                            <option value="" disabled selected>Select Report Option</option>
                                            <option value="delay_tracking_stage_wise"
                                                data-html='<i class="ri-file-text-fill"></i> Delay Tracking (Emp)'>
                                                Delay Tracking (Emp)
                                            </option>
                                            <option value="claim_stages_report"
                                                data-html='<i class="ri-file-text-fill"></i> Claim Stages'>
                                                Claim Stages
                                            </option>
                                            <option value="submission_wise"
                                                data-html='<i class="ri-file-text-fill"></i> Submission'>
                                                Submission
                                            </option>
                                            <option value="access_running_km"
                                                data-html='<i class="ri-road-map-line"></i> Excess Running KM'>
                                                Excess Running KM
                                            </option>
                                            <option value="focus_export"
                                                data-html='<i class="ri-focus-3-fill"></i> Focus Export'>
                                                Focus Export
                                            </option>
                                            <option value="month_wise_expense"
                                                data-html='<i class="ri-file-text-fill"></i> Monthly Exp (Single Sheet)'>
                                                Monthly Exp (Single Sheet)
                                            </option>
                                            <option value="month_wise_expense_multiple_sheet"
                                                data-html='<i class="ri-stack-fill"></i> Monthly Exp (Multiple Sheet)'>
                                                Monthly Exp (Multiple Sheet)
                                            </option>
                                            <option value="month_claim_type_wise_expense"
                                                data-html='<i class="ri-file-text-fill"></i> Claim Type (Single Sheet)'>
                                                Claim Type (Single Sheet)
                                            </option>
                                            <option value="month_claim_type_wise_expense_multiple_sheet"
                                                data-html='<i class="ri-stack-fill"></i> Claim Type (Multiple Sheet)'>
                                                Claim Type (Multiple Sheet)
                                            </option>
                                            <option value="employee_claim_type_wise_expense"
                                                data-html='<i class="ri-user-line"></i> Claim Type (Employee Wise)'>
                                                Claim Type (Employee Wise)
                                            </option>
                                            <option value="da_in_out_report"
                                                data-html='<i class="ri-file-list-3-line"></i> DA In/Out Report'>
                                                DA In/Out Report
                                            </option>
                                        </select>
                                    </div>
                                    <div id="columnContainer" style="display:none;">
                                        <select class="form-select" id="columnSelect" name="columnSelect"
                                            style="width:100%;">
                                            <option value="all" selected>All</option>
                                            <option value="Filled">Filled</option>
                                            <option value="Verified">Verified</option>
                                            <option value="Approved">Approved</option>
                                            <option value="Financed">Financed</option>
                                        </select>
                                    </div>
                                    <div id="stageContainer" style="display:none;">
                                        <select class="form-select" id="stageSelect" name="stageSelect" style="width:100%;">
                                            <option value="all" selected>All</option>
                                            @php
                                                $stages = session('company_id') == 11
                                                    ? \App\Exports\ClaimStagesExport::STAGES_VVNR
                                                    : \App\Exports\ClaimStagesExport::STAGES_VNR;
                                            @endphp
                                            @foreach($stages as $stage)
                                                <option value="{{ $stage }}">{{ $stage }}</option>
                                            @endforeach
                                            <option value="No Claim Employee List">No Claim Employee List</option>
                                        </select>
                                    </div>
                                    <div id="delayCategoryContainer" style="display:none;">
                                        <select class="form-select" id="delayCategorySelect" name="delayCategorySelect"
                                            style="width:180px;">
                                            <option value="all" selected>All Delays</option>
                                            <option value="same_day">Same Day (0 Days)</option>
                                            <option value="1_3_days">1–3 Days</option>
                                            <option value="4_7_days">4–7 Days</option>
                                            <option value="over_7_days">> 7 Days</option>
                                        </select>
                                    </div>
                                    <div id="dateRangeContainer" style="display:none;">
                                        <input type="text" class="form-control" id="dateRangePicker"
                                            placeholder="Select Date Range" aria-label="Select Date Range" style="width: 250px;" readonly>
                                    </div>
                                    @can('month_filter')
                                        <div id="monthContainer">
                                            <select class="form-select" id="monthSelect" style="width: 200px;" multiple>
                                                <option value="all">-- Select All --</option>
                                                <option value="1">January</option>
                                                <option value="2">February</option>
                                                <option value="3">March</option>
                                                <option value="4">April</option>
                                                <option value="5">May</option>
                                                <option value="6">June</option>
                                                <option value="7">July</option>
                                                <option value="8">August</option>
                                                <option value="9">September</option>
                                                <option value="10">October</option>
                                                <option value="11">November</option>
                                                <option value="12">December</option>
                                            </select>
                                        </div>
                                    @endcan
                                    @can('user_filter')
                                        <div>
                                            <select class="form-select" id="userSelect" multiple>
                                                <option value="" disabled>Select Employee</option>
                                                @foreach ($employees as $employee)
                                                    <option value="{{ $employee->EmployeeID }}"
                                                        data-status="{{ $employee->EmpStatus }}"
                                                        class="{{ $employee->EmpStatus === 'D' ? 'deactivated' : '' }}">
                                                        {{ $employee->EmpCode_New }} - {{ $employee->Fname }}
                                                        {{ $employee->Sname ?? '' }} {{ $employee->Lname }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endcan
                                    @can('claim_type_filter')
                                        @php $groupedClaimTypes = $claimTypes->groupBy('cgName'); @endphp
                                        <div id="claimTypeContainer" style="display: none;">
                                            <select class="form-select" id="claimTypeSelect" multiple>
                                                @foreach ($groupedClaimTypes as $groupName => $claims)
                                                    <optgroup label="{{ $groupName ?? 'Ungrouped' }}">
                                                        @foreach ($claims as $claim)
                                                            <option value="{{ $claim->ClaimId }}"
                                                                data-group="{{ $groupName ?? 'Ungrouped' }}">
                                                                {{ $claim->ClaimName }}
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endcan
                                    <a class="text-primary position-relative d-flex align-items-center ms-2"
                                        type="button" data-bs-toggle="offcanvas"
                                        data-bs-target="#additionalFiltersCanvas" aria-controls="additionalFiltersCanvas">
                                        <i class="ri-equalizer-line fs-16 me-1"></i>
                                        <span class="align-middle">More Filters</span>
                                        <span id="activeFilterCount"
                                            class="badge bg-danger-subtle text-danger ms-2">0</span>
                                    </a>
                                    <button type="button"
                                        class="btn btn-primary btn-label waves-effect waves-light rounded-pill ms-auto"
                                        id="searchButton">
                                        <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                                            <span class="loader" style="display: none;"></span>
                                        </i>
                                        Search Data
                                    </button>
                                </div>
                            </div>
                            @include('partials.organogram-filters-drawer', [
                                'drawerId' => 'additionalFiltersCanvas',
                                'applyBtnId' => 'applyFiltersBtn',
                                'applyBtnDismiss' => true,
                                'enforcePermissions' => true,
                            ])
                        </div>
                        <div id="excelPreview" class="mt-4" style="display:none;">
                            <div class="card border shadow-sm">
                                <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="ri-file-excel-2-line text-success fs-18"></i>
                                        <span class="fw-semibold text-dark" id="previewFileName">Generated Report</span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" id="sheetRowCountBadge">Ready</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <a id="downloadReportBtn" href="#" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1" download>
                                            <i class="ri-download-2-line"></i>
                                            <span>Download Excel</span>
                                        </a>
                                        <a id="openDirectBtn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                            <i class="ri-external-link-line"></i>
                                            <span>Open in New Tab</span>
                                        </a>
                                        <button type="button" id="toggleViewerBtn" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" title="Toggle between in-browser table and cloud iframe viewer">
                                            <i class="ri-external-link-line" id="toggleViewerIcon"></i>
                                            <span id="toggleViewerText">Switch to Cloud Viewer</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0 position-relative">
                                    <div id="viewerLoadingOverlay" class="position-absolute top-0 start-0 w-100 h-100 flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 20;">
                                        <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;">
                                            <span class="visually-hidden">Loading preview...</span>
                                        </div>
                                        <small class="text-muted mt-2 fw-medium" id="viewerLoadingText">Loading document preview...</small>
                                        <button type="button" id="dismissViewerLoaderBtn" class="btn btn-sm btn-outline-secondary mt-2 px-3 py-1 fs-12">
                                            <i class="ri-table-line me-1"></i>View Interactive Table
                                        </button>
                                    </div>

                                    {{-- Native In-Browser Interactive Sheet Viewer (Default, Fast & Reliable) --}}
                                    <div id="nativeSheetViewer">
                                        <div class="p-2 bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                                            <ul class="nav nav-pills nav-pills-sm flex-wrap gap-1" id="sheetTabsNav" role="tablist" style="margin-bottom:0;">
                                                <!-- Dynamic sheet tabs injected here -->
                                            </ul>
                                            <div class="input-group input-group-sm ms-auto" style="max-width: 260px;">
                                                <span class="input-group-text bg-white py-1"><i class="ri-search-line"></i></span>
                                                <input type="text" id="sheetSearchInput" class="form-control py-1" placeholder="Search in this sheet..." aria-label="Search in this sheet">
                                            </div>
                                        </div>
                                        <div id="sheetTableContainer" class="table-responsive" style="max-height: 600px; min-height: 250px; overflow: auto;">
                                            <!-- Dynamic Bootstrap table injected here -->
                                        </div>
                                    </div>

                                    {{-- Cloud Iframe Viewer (Fallback) --}}
                                    <div id="cloudIframeViewer" style="display: none;">
                                        <div class="alert alert-info border-0 rounded-0 mb-0 py-1 px-3 d-flex align-items-center justify-content-between small">
                                            <span>
                                                <i class="ri-information-line me-1"></i>
                                                Viewing via cloud document viewer. If preview does not load, please click <strong>Download Excel</strong> above.
                                            </span>
                                            <button type="button" id="toggleCloudProviderBtn" class="btn btn-xs btn-outline-info py-0 px-2" style="font-size:11px;">
                                                Switch Cloud Provider
                                            </button>
                                        </div>
                                        <iframe id="excelFrame" width="100%" height="600" frameborder="0" style="min-height: 600px; display: block;"></iframe>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endsection @push('styles')
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/classic.min.css" />
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/monolith.min.css" />
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/nano.min.css" />
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
    <style>
        #sheetTableContainer {
            background-color: #ffffff;
            padding: 8px;
        }
        #sheetTableContainer table {
            border-collapse: collapse;
            width: max-content;
            min-width: 100%;
            margin-bottom: 0;
        }
        #sheetTableContainer td, #sheetTableContainer th {
            white-space: nowrap;
        }
        #sheetTableContainer thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 2;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        #sheetTabsNav .nav-link {
            font-size: 11.5px;
            padding: 3px 10px;
            border-radius: 4px;
            color: #495057;
            background: #e9ecef;
        }
        #sheetTabsNav .nav-link.active {
            background-color: #00175f;
            color: #ffffff;
        }
        #sheetTabsNav .nav-link.active .badge {
            background-color: rgba(255,255,255,0.2) !important;
            color: #ffffff !important;
        }
        #viewerLoadingOverlay {
            display: none !important;
        }
        #viewerLoadingOverlay.active {
            display: flex !important;
        }
    </style>
    @endpush @push('scripts')
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('vendor/cdn/xlsx@0.18.5/dist/xlsx.full.min.js') }}"></script>
    <script src="{{ asset('custom/js/pages/claim_stage.js') }}?v={{ filemtime(public_path('custom/js/pages/claim_stage.js')) }}"></script>
@endpush
