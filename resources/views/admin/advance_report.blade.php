@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-xl-12">
                <div class="card card-height-100">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1">Advance Report</h4>
                    </div>
                    <div class="card-body pb-3 pt-0">
                        <div class="bg-light-subtle border-top-dashed border border-start-0 border-end-0 border-bottom-dashed py-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">

                                {{-- Report Type --}}
                                <div>
                                    <select class="form-select" id="reportType" name="reportType">
                                        <option value="" disabled selected>Select Report Option</option>
                        <option value="monthly_advance"
                                            data-html='<i class="ri-stack-fill"></i> Monthly Advance (Multiple Sheet)'>
                                            Monthly Advance (Multiple Sheet)
                                        </option>
                                        <option value="employee_wise_advance"
                                            data-html='<i class="ri-user-line"></i> Employee Wise Advance'>
                                            Employee Wise Advance
                                        </option>
                                    </select>
                                </div>

                                {{-- Month Selector --}}
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

                                {{-- More Filters toggle --}}
                                <a class="text-primary position-relative d-flex align-items-center ms-2"
                                   type="button"
                                   data-bs-toggle="offcanvas"
                                   data-bs-target="#advanceFiltersCanvas"
                                   aria-controls="advanceFiltersCanvas">
                                    <i class="ri-equalizer-line fs-16 me-1"></i>
                                    <span class="align-middle">More Filters</span>
                                    <span id="activeFilterCount" class="badge bg-danger-subtle text-danger ms-2">0</span>
                                </a>

                                {{-- Search Button --}}
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

                        {{-- Off-canvas: Additional Filters (Server-Side Rendered) --}}
                        @include('partials.organogram-filters-drawer', [
                            'drawerId' => 'advanceFiltersCanvas',
                            'extraFieldsView' => 'admin.partials.advance_report_extra_filters',
                            'extraFieldsData' => compact('employees', 'activityTypes', 'activityNames'),
                            'resetBtnId' => 'resetAdvanceFiltersBtn',
                            'resetBtnLabel' => 'Reset Filters',
                            'applyBtnId' => 'applyFiltersBtn',
                            'applyBtnDismiss' => true,
                            'buttonLayout' => 'row',
                            'enforcePermissions' => true,
                        ])
                    </div>

                    {{-- Excel Preview --}}
                    <div id="excelPreview" style="display:none;">
                        <iframe id="excelFrame" width="100%" height="400" frameborder="0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush

@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<script src="{{ asset('custom/js/pages/advance_report.js') }}?v={{ filemtime(public_path('custom/js/pages/advance_report.js')) }}"></script>
@endpush
