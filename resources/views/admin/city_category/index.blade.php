@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <!-- Page Header & Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between" style="margin-top: 2px !important;">
                    <h4 class="mb-sm-0 font-size-18">
                        <i class="ri-map-pin-2-line align-middle me-1 text-primary"></i> City Category Master
                    </h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0);">Masters</a></li>
                            <li class="breadcrumb-item active">City Categories</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Metrics & Summary Cards -->
        <div class="row mb-3">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-semibold text-muted text-truncate mb-0 fs-13">Total Cities</p>
                                <h3 class="fs-22 fw-bold ff-secondary mb-0 mt-2 text-dark">{{ number_format($totalCities) }}</h3>
                            </div>
                            <div class="flex-shrink-0 avatar-sm">
                                <span class="avatar-title bg-primary-subtle rounded fs-3 text-primary">
                                    <i class="ri-building-2-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-md-6">
                <div class="card card-animate shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-1">
                            <p class="text-uppercase fw-semibold text-muted mb-0 fs-13">Category Breakdown</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @forelse ($categoryBreakdown as $cat)
                                @php
                                    $badgeClass = match(strtoupper(trim($cat->city_category))) {
                                        'METRO', 'A'    => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'TIER-1', 'B'  => 'bg-success-subtle text-success border border-success-subtle',
                                        'TIER-2', 'C'  => 'bg-warning-subtle text-warning border border-warning-subtle',
                                        'TIER-3', 'D'  => 'bg-info-subtle text-info border border-info-subtle',
                                        default        => 'bg-secondary-subtle text-secondary border'
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} fs-12 px-2 py-1">
                                    {{ $cat->city_category }}: <strong class="ms-1">{{ $cat->total }}</strong>
                                </span>
                            @empty
                                <span class="text-muted fs-12">No categories defined yet.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-semibold text-muted text-truncate mb-0 fs-13">Audit Logs Recorded</p>
                                <h3 class="fs-22 fw-bold ff-secondary mb-0 mt-2 text-info">{{ number_format($totalLogs) }}</h3>
                            </div>
                            <div class="flex-shrink-0 avatar-sm">
                                <span class="avatar-title bg-info-subtle rounded fs-3 text-info">
                                    <i class="ri-history-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="card-title mb-0">City Category List</h5>
                    <span class="badge bg-light text-muted border">{{ $cities->total() }} records</span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <!-- Export to CSV / Excel Button -->
                    <a href="{{ route('city-categories.export', request()->query()) }}" 
                       class="btn btn-success btn-label waves-effect waves-light rounded-pill" 
                       title="Export filtered cities to CSV/Excel">
                        <i class="ri-file-excel-2-line label-icon align-middle rounded-pill fs-16 me-2"></i> Export CSV
                    </a>

                    <!-- View All Logs Button -->
                    <button type="button" class="btn btn-soft-info btn-label waves-effect waves-light rounded-pill" id="viewAllLogsBtn">
                        <i class="ri-history-line label-icon align-middle rounded-pill fs-16 me-2"></i> Audit Logs
                    </button>

                    <!-- Add New City Button -->
                    <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill" id="addNewCityBtn">
                        <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add City
                    </button>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="card-body border-bottom bg-light py-3">
                <form method="GET" action="{{ route('city-categories.index') }}" class="row g-2 align-items-center">
                    <div class="col-md-4 col-sm-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search by city name, category or CCID..." aria-label="Search by city name, category or CCID" value="{{ $search }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <select name="category" class="form-select bg-white">
                            <option value="">-- All Categories --</option>
                            @foreach ($distinctCategories as $cat)
                                <option value="{{ $cat }}" {{ $selectedCategory == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-3">
                            <i class="ri-filter-2-line me-1"></i> Apply Filter
                        </button>
                        @if(!empty($search) || !empty($selectedCategory))
                            <a href="{{ route('city-categories.index') }}" class="btn btn-outline-secondary" title="Clear Filters">
                                <i class="ri-refresh-line"></i> Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Table Body -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0" id="cityCategoryTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;" class="text-center">#</th>
                                <th style="width: 90px;" class="text-center">CCID</th>
                                <th>City Name</th>
                                <th style="width: 170px;" class="text-center">City Category</th>
                                <th>Created By</th>
                                <th style="width: 140px;" class="text-center">Created Date</th>
                                <th style="width: 140px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cities as $index => $city)
                                @php
                                    $catKey = strtoupper(trim($city->city_category));
                                    $badgeStyle = match($catKey) {
                                        'METRO', 'A'    => 'badge bg-primary-subtle text-primary border border-primary-subtle',
                                        'TIER-1', 'B'  => 'badge bg-success-subtle text-success border border-success-subtle',
                                        'TIER-2', 'C'  => 'badge bg-warning-subtle text-warning border border-warning-subtle',
                                        'TIER-3', 'D'  => 'badge bg-info-subtle text-info border border-info-subtle',
                                        default        => 'badge bg-secondary-subtle text-secondary border'
                                    };
                                    $creatorText = isset($creatorsMap[$city->crby]) ? e($creatorsMap[$city->crby]) : ($city->crby ? 'Emp ID: '.(int) $city->crby : '<span class="text-muted">System</span>'); // S-09: names encoded
                                @endphp
                                <tr>
                                    <td class="text-center text-muted">{{ $cities->firstItem() + $index }}</td>
                                    <td class="text-center"><span class="badge bg-light text-dark border">#{{ $city->ccid }}</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ri-map-pin-line text-muted me-2 fs-15"></i>
                                            <span class="fw-semibold text-dark">{{ $city->city_name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="{{ $badgeStyle }} px-3 py-1 fs-12">{{ $city->city_category }}</span>
                                    </td>
                                    <td>
                                        <small class="d-block">{!! $creatorText !!}</small>
                                    </td>
                                    <td class="text-center text-muted">
                                        <small>
                                            <i class="ri-calendar-line me-1"></i>
                                            {{ $city->crdate ? \Carbon\Carbon::parse($city->crdate)->format('d M Y') : '-' }}
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Edit Button -->
                                            <button type="button" 
                                                    class="btn btn-primary edit-city-btn" 
                                                    data-id="{{ $city->ccid }}"
                                                    data-name="{{ $city->city_name }}"
                                                    data-category="{{ $city->city_category }}"
                                                    title="Edit City">
                                                <i class="ri-edit-2-line"></i>
                                            </button>

                                            <!-- History / Log Button -->
                                            <button type="button" 
                                                    class="btn btn-soft-info view-city-logs-btn" 
                                                    data-id="{{ $city->ccid }}"
                                                    data-name="{{ $city->city_name }}"
                                                    title="View History">
                                                <i class="ri-history-line"></i>
                                            </button>

                                            <!-- Delete Button -->
                                            <button type="button" 
                                                    class="btn btn-danger delete-city-btn" 
                                                    data-id="{{ $city->ccid }}"
                                                    data-name="{{ $city->city_name }}"
                                                    title="Delete City">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-center text-muted">
                                            <i class="ri-map-pin-range-line display-5 text-muted mb-2"></i>
                                            <h5>No City Categories Found</h5>
                                            <p class="mb-0">No records match your search criteria. Click "Add City" to create one.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if ($cities->hasPages())
                    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                        <div class="text-muted fs-13">
                            Showing <strong>{{ $cities->firstItem() }}</strong> to <strong>{{ $cities->lastItem() }}</strong> of <strong>{{ $cities->total() }}</strong> records
                        </div>
                        <div>
                            {{ $cities->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: ADD / EDIT CITY CATEGORY                                -->
<!-- ============================================================== -->
<div class="modal fade" id="cityModal" tabindex="-1" aria-labelledby="cityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white p-3">
                <h5 class="modal-title text-white" id="cityModalLabel">
                    <i class="ri-map-pin-add-line me-1"></i> Add City Category
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="cityForm">
                @csrf
                <input type="hidden" id="cityId" name="id" value="">
                <input type="hidden" id="formMethod" name="_method" value="POST">

                <div class="modal-body p-4">
                    <div id="modalAlertContainer"></div>

                    <!-- City Name -->
                    <div class="mb-3">
                        <label for="modalCityName" class="form-label fw-semibold">City Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalCityName" name="city_name" placeholder="Enter city name (e.g. Mumbai, Pune, Raipur)" required>
                        <div class="invalid-feedback" id="cityNameFeedback"></div>
                    </div>

                    <!-- City Category -->
                    <div class="mb-3">
                        <label for="modalCityCategorySelect" class="form-label fw-semibold">City Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="modalCityCategorySelect">
                            <option value="">-- Select Category --</option>
                            <option value="Metro">Metro</option>
                            <option value="Tier-1">Tier-1</option>
                            <option value="Tier-2">Tier-2</option>
                            <option value="Tier-3">Tier-3</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                            <option value="__custom__">+ Enter Custom Category...</option>
                        </select>
                    </div>

                    <!-- Custom Category Input (revealed when selected) -->
                    <div class="mb-3" id="customCategoryGroup" style="display: none;">
                        <label for="modalCityCategoryCustom" class="form-label fw-semibold">Custom Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalCityCategoryCustom" placeholder="e.g. Remote, Rural, Special Zone">
                    </div>

                    <input type="hidden" id="modalCityCategory" name="city_category">
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" id="saveCityBtn">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="saveBtnSpinner" role="status" aria-hidden="true"></span>
                        <i class="ri-check-line me-1" id="saveBtnIcon"></i> Save City
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: AUDIT TRAIL / LOGS                                      -->
<!-- ============================================================== -->
<div class="modal fade" id="cityLogsModal" tabindex="-1" aria-labelledby="cityLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom p-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-title bg-info-subtle text-info rounded p-2 fs-18">
                        <i class="ri-history-line"></i>
                    </span>
                    <div>
                        <h5 class="modal-title mb-0" id="cityLogsModalLabel">Audit Trail & Action Logs</h5>
                        <small class="text-muted" id="cityLogsSubtitle">Showing history for all city categories</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('city-categories.logs.export') }}" id="exportLogsBtn" class="btn btn-sm btn-outline-success">
                        <i class="ri-download-2-line me-1"></i> Export Logs
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Modal Filter -->
            <div class="p-3 bg-light border-bottom">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" id="logFilterSearch" class="form-control form-control-sm" placeholder="Search logs by keyword, city, or user..." aria-label="Search logs by keyword, city, or user">
                    </div>
                    <div class="col-md-3">
                        <select id="logFilterAction" class="form-select form-select-sm">
                            <option value="">-- All Actions --</option>
                            <option value="Created">Created</option>
                            <option value="Updated">Updated</option>
                            <option value="Deleted">Deleted</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-secondary w-100" id="reloadLogsBtn">
                            <i class="ri-refresh-line me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th style="width: 90px;" class="text-center">Action</th>
                                <th>City</th>
                                <th style="width: 100px;">Category</th>
                                <th>Changes Summary</th>
                                <th>Action By</th>
                                <th style="width: 160px;" class="text-center">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-2 mb-0">Loading logs...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let currentLogCcid = null;

    // -------------------------------------------------------------
    // Category Select Handler
    // -------------------------------------------------------------
    function handleCategoryChange() {
        const val = $('#modalCityCategorySelect').val();
        if (val === '__custom__') {
            $('#customCategoryGroup').slideDown(150);
            $('#modalCityCategory').val($('#modalCityCategoryCustom').val().trim());
        } else {
            $('#customCategoryGroup').slideUp(150);
            $('#modalCityCategory').val(val);
        }
    }

    $('#modalCityCategorySelect').on('change', handleCategoryChange);
    $('#modalCityCategoryCustom').on('input', function() {
        if ($('#modalCityCategorySelect').val() === '__custom__') {
            $('#modalCityCategory').val($(this).val().trim());
        }
    });

    // -------------------------------------------------------------
    // Open Add Modal
    // -------------------------------------------------------------
    $('#addNewCityBtn').on('click', function() {
        $('#cityForm')[0].reset();
        $('#cityId').val('');
        $('#formMethod').val('POST');
        $('#cityModalLabel').html('<i class="ri-map-pin-add-line me-1"></i> Add City Category');
        $('#modalAlertContainer').html('');
        $('#customCategoryGroup').hide();
        $('#modalCityCategory').val('');
        $('#modalCityCategorySelect').val('');
        $('#cityModal').modal('show');
    });

    // -------------------------------------------------------------
    // Open Edit Modal
    // -------------------------------------------------------------
    $(document).on('click', '.edit-city-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const category = $(this).data('category');

        $('#cityForm')[0].reset();
        $('#modalAlertContainer').html('');
        $('#cityId').val(id);
        $('#formMethod').val('PUT');
        $('#modalCityName').val(name);
        $('#cityModalLabel').html('<i class="ri-edit-2-line me-1"></i> Edit City Category: ' + name);

        // Select category in dropdown or custom
        const existingOption = $('#modalCityCategorySelect option[value="' + category + '"]');
        if (existingOption.length > 0) {
            $('#modalCityCategorySelect').val(category);
            $('#customCategoryGroup').hide();
            $('#modalCityCategory').val(category);
        } else {
            $('#modalCityCategorySelect').val('__custom__');
            $('#modalCityCategoryCustom').val(category);
            $('#customCategoryGroup').show();
            $('#modalCityCategory').val(category);
        }

        $('#cityModal').modal('show');
    });

    // -------------------------------------------------------------
    // Submit Add / Edit Form
    // -------------------------------------------------------------
    $('#cityForm').on('submit', function(e) {
        e.preventDefault();

        // Sync category value
        if ($('#modalCityCategorySelect').val() === '__custom__') {
            $('#modalCityCategory').val($('#modalCityCategoryCustom').val().trim());
        } else {
            $('#modalCityCategory').val($('#modalCityCategorySelect').val());
        }

        const categoryVal = $('#modalCityCategory').val();
        if (!categoryVal) {
            $('#modalAlertContainer').html('<div class="alert alert-warning py-2">Please select or enter a City Category.</div>');
            return;
        }

        const id = $('#cityId').val();
        const method = $('#formMethod').val();
        const url = (method === 'PUT') 
            ? "{{ url('city-categories') }}/" + id
            : "{{ route('city-categories.store') }}";

        const formData = {
            _token: "{{ csrf_token() }}",
            _method: method,
            city_name: $('#modalCityName').val().trim(),
            city_category: categoryVal
        };

        $('#saveCityBtn').prop('disabled', true);
        $('#saveBtnSpinner').removeClass('d-none');
        $('#saveBtnIcon').addClass('d-none');

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(res) {
                $('#saveCityBtn').prop('disabled', false);
                $('#saveBtnSpinner').addClass('d-none');
                $('#saveBtnIcon').removeClass('d-none');

                if (res.success) {
                    $('#cityModal').modal('hide');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: res.message,
                            timer: 1400,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        alert(res.message);
                        location.reload();
                    }
                } else {
                    $('#modalAlertContainer').html('<div class="alert alert-danger py-2">' + (res.message || 'An error occurred.') + '</div>');
                }
            },
            error: function(xhr) {
                $('#saveCityBtn').prop('disabled', false);
                $('#saveBtnSpinner').addClass('d-none');
                $('#saveBtnIcon').removeClass('d-none');

                let errMsg = 'Failed to save city category.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errMsg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errMsg = xhr.responseJSON.message;
                }
                $('#modalAlertContainer').html('<div class="alert alert-danger py-2">' + errMsg + '</div>');
            }
        });
    });

    // -------------------------------------------------------------
    // Delete City
    // -------------------------------------------------------------
    $(document).on('click', '.delete-city-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        const doDelete = () => {
            $.ajax({
                url: "{{ url('city-categories') }}/" + id,
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    _method: 'DELETE'
                },
                success: function(res) {
                    if (res.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: res.message,
                                timer: 1400,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            alert(res.message);
                            location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', res.message || 'Could not delete city.', 'error');
                        } else {
                            alert(res.message || 'Could not delete city.');
                        }
                    }
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Server error while deleting.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg, 'error');
                    } else {
                        alert(msg);
                    }
                }
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete City?',
                html: `Are you sure you want to delete <strong>${name}</strong>?<br><small class="text-danger">This action will be logged in the audit trail.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="ri-delete-bin-line me-1"></i> Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    doDelete();
                }
            });
        } else {
            if (confirm(`Are you sure you want to delete city "${name}"?`)) {
                doDelete();
            }
        }
    });

    // -------------------------------------------------------------
    // Audit Logs Modal & AJAX Fetcher
    // -------------------------------------------------------------
    function loadLogs(ccid = null) {
        currentLogCcid = ccid;
        const action = $('#logFilterAction').val();
        const search = $('#logFilterSearch').val();

        let exportUrl = "{{ route('city-categories.logs.export') }}?";
        if (ccid) exportUrl += 'ccid=' + ccid + '&';
        if (action) exportUrl += 'action=' + action + '&';
        if (search) exportUrl += 'search=' + encodeURIComponent(search);
        $('#exportLogsBtn').attr('href', exportUrl);

        $('#logsTableBody').html(`
            <tr>
                <td colspan="7" class="text-center py-4">
                    <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                    <span class="text-muted ms-2">Loading audit logs...</span>
                </td>
            </tr>
        `);

        $.ajax({
            url: "{{ route('city-categories.logs') }}",
            type: 'GET',
            data: {
                ccid: ccid,
                action: action,
                search: search
            },
            success: function(res) {
                if (res.success && res.data && res.data.length > 0) {
                    let html = '';
                    res.data.forEach((log, i) => {
                        let actionBadge = '';
                        if (log.action === 'Created') {
                            actionBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle">Created</span>';
                        } else if (log.action === 'Updated') {
                            actionBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Updated</span>';
                        } else if (log.action === 'Deleted') {
                            actionBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Deleted</span>';
                        } else {
                            actionBadge = `<span class="badge bg-secondary-subtle text-secondary">${log.action}</span>`;
                        }

                        html += `
                            <tr>
                                <td class="text-center text-muted">${i + 1}</td>
                                <td class="text-center">${actionBadge}</td>
                                <td><strong>${log.city_name || '-'}</strong></td>
                                <td><span class="badge bg-light text-dark border">${log.city_category || '-'}</span></td>
                                <td><small class="text-dark">${log.changes_summary || '-'}</small></td>
                                <td>
                                    <small class="d-block fw-semibold text-dark">${log.action_by_name || 'System'}</small>
                                    ${log.ip_address ? `<small class="text-muted">IP: ${log.ip_address}</small>` : ''}
                                </td>
                                <td class="text-center text-muted"><small>${log.created_at}</small></td>
                            </tr>
                        `;
                    });
                    $('#logsTableBody').html(html);
                } else {
                    $('#logsTableBody').html(`
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="ri-history-line display-6 d-block mb-1 text-muted"></i>
                                No audit log records found.
                            </td>
                        </tr>
                    `);
                }
            },
            error: function() {
                $('#logsTableBody').html(`
                    <tr>
                        <td colspan="7" class="text-center py-3 text-danger">Failed to load logs. Please try again.</td>
                    </tr>
                `);
            }
        });
    }

    // Open logs for all cities
    $('#viewAllLogsBtn').on('click', function() {
        $('#cityLogsSubtitle').text('Showing full audit trail across all city categories');
        $('#cityLogsModal').modal('show');
        loadLogs(null);
    });

    // Open logs for single city
    $(document).on('click', '.view-city-logs-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#cityLogsSubtitle').html(`Showing history for city: <strong>${name}</strong> (CCID: ${id})`);
        $('#cityLogsModal').modal('show');
        loadLogs(id);
    });

    // Reload / Filter logs inside modal
    $('#reloadLogsBtn').on('click', function() {
        loadLogs(currentLogCcid);
    });

    $('#logFilterAction').on('change', function() {
        loadLogs(currentLogCcid);
    });

    let searchTimer;
    $('#logFilterSearch').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            loadLogs(currentLogCcid);
        }, 350);
    });
});
</script>
@endpush
