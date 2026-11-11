@extends('layouts.app') @section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Generate Claim Report</h4>
                            <div class="flex-shrink-0 ms-2">
                                <button class="btn btn-soft-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#exportModal">Get Report<i
                                        class="mdi mdi-chevron-down align-middle ms-1"></i></button>
                            </div>
                        </div>
                        <div class="card-body pb-3 pt-0">
                            <div
                                class="bg-light-subtle border-top-dashed border border-start-0 border-end-0 border-bottom-dashed py-2 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <input type="text" placeholder="Select From Date" aria-label="Select From Date" class="form-control flatpickr w-auto"
                                        id="fromDate" style="min-width: 170px;" />
                                    <input type="text" placeholder="Select To Date" aria-label="Select To Date" class="form-control flatpickr w-auto"
                                        id="toDate" style="min-width: 170px;" />
                                    <input type="number" placeholder="Exp: 12345" aria-label="Exp: 12345" class="form-control w-auto" id="expId"
                                        style="width: 90px !important;" />
                                    <div class="d-flex align-items-center flex-wrap gap-3">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="dateType" id="billDate"
                                                value="billDate" checked />
                                            <label class="form-check-label" for="billDate">Bill Date</label>
                                        </div>
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="dateType" id="uploadDate"
                                                value="uploadDate" />
                                            <label class="form-check-label" for="uploadDate">Upload Date</label>
                                        </div>
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="dateType" id="filledDate"
                                                value="filledDate" />
                                            <label class="form-check-label" for="filledDate">Filled Date</label>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center ms-2"
                                        id="openMoreFiltersBtn"
                                        data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas"
                                        aria-controls="additionalFiltersCanvas">
                                        <i class="ri-equalizer-line fs-14 me-1"></i>
                                        <span class="align-middle">More Filters</span>
                                        <span id="activeFilterCount"
                                            class="badge bg-danger-subtle text-danger ms-1">0</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center ms-1"
                                        id="clearAllFilters" title="Clear all filters">
                                        <i class="ri-close-circle-line fs-14 me-1"></i>
                                        <span class="align-middle">Clear</span>
                                    </button>
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

                            <table style="margin-top: 15px;" id="claimReportTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sn</th>
                                        <th>Exp Id</th>
                                        <th>Claim Type</th>
                                        <th>Emp Name</th>
                                        <th>Month</th>
                                        <th>Upload Date</th>
                                        <th>Bill Date</th>
                                        <th>Claimed Amt</th>
                                        <th>Claim Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('partials.organogram-filters-drawer', [
        'drawerId' => 'additionalFiltersCanvas',
        'extraFieldsView' => 'admin.partials.claim_report_extra_filters',
        'extraFieldsData' => compact('employees', 'guestUsers', 'claimTypes'),
        'resetBtnId' => 'clearFiltersBtn',
        'resetBtnLabel' => 'Clear Filters',
        'applyBtnId' => 'applyFiltersBtn',
        'buttonLayout' => 'row',
        'enforcePermissions' => true,
    ])

    <x-modal.claim_report />
    <x-modal.view_claim_detail />
@endsection @push('styles')
    <style>
        #additionalFiltersCanvas {
            width: 520px;
            max-width: 95vw;
        }
        #additionalFiltersCanvas .offcanvas-header {
            background: #00175f;
            color: #ffffff;
            padding: 1rem 1.25rem;
        }
        #additionalFiltersCanvas .offcanvas-header .offcanvas-title {
            color: #ffffff;
            font-weight: 700;
            font-size: 1.1rem;
        }
        #additionalFiltersCanvas .offcanvas-header .btn-close {
            filter: invert(1);
        }
        #additionalFiltersCanvas .select2-container {
            width: 100% !important;
        }
        #additionalFiltersCanvas .select2-container--default .select2-selection--multiple {
            border-color: #cbd5e1;
            border-radius: 6px;
            min-height: 38px;
        }
        #additionalFiltersCanvas .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #00175f;
        }
    </style>
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/classic.min.css" />
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/monolith.min.css" />
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/nano.min.css" />
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush @push('scripts')
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('custom/js/pages/claim_report.js') }}"></script>
    <script src="{{ asset('custom/js/pages/claim.js') }}"></script>
@endpush