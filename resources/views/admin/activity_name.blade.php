@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex" style="justify-content: space-between;">
                            <x-redirect-button />
                            <div style="display: flex;gap:8px;align-items: baseline;">
                                <div class="dropdown card-header-dropdown">
                                    <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <span class="fw-semibold fs-12">Status: </span>
                                        <span class="text-muted">
                                            @if ($status === 'active')
                                                Active
                                            @elseif($status === 'inactive')
                                                Inactive
                                            @else
                                                All
                                            @endif
                                            <i class="mdi mdi-chevron-down"></i>
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ url()->current() }}?status=all">All</a>
                                        <a class="dropdown-item" href="{{ url()->current() }}?status=active">Active</a>
                                        <a class="dropdown-item" href="{{ url()->current() }}?status=inactive">Inactive</a>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    @can('create activityname')
                                        <button type="button"
                                            class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                            data-bs-toggle="modal" data-bs-target="#activityNameModal" id="addActivityNameBtn">
                                            <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                            Add
                                            New
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="activityNameTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th style="text-align: left">Activity Name</th>
                                        <th>Category</th>
                                        <th>Departments</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @can('activity_name_list')
                                        @foreach ($activityNames as $key => $name)
                                            @can("view activityname {$name->id}")
                                                <tr>
                                                    <td>{{ $key + 1 }}</td>
                                                    <td style="text-align: left">{{ $name->claim_name }}</td>
                                                    <td>{{ $name->category->category_name ?? '-' }}</td>
                                                    <td>{{ $name->departments }}</td>
                                                    <td>
                                                        @if ($name->status)
                                                            <span
                                                                class="badge bg-success-subtle text-success badge-border">Active</span>
                                                        @else
                                                            <span
                                                                class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @can("update activityname {$name->id}")
                                                            <button type="button" data-bs-toggle="modal"
                                                                data-bs-target="#activityNameModal"
                                                                class="btn btn-primary btn-sm edit-activity-name"
                                                                data-id="{{ $name->id }}"><i class="ri-edit-2-fill"></i></button>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endcan
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="13" class="text-center">
                                                <span class="text-danger">You do not have permission to view the Activity Name
                                                    List.</span>
                                            </td>
                                        </tr>
                                    @endcan
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="activityNameModal" tabindex="-1" aria-labelledby="activityNameLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 overflow-hidden">
                <div class="progress-container" style="display: none;">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header p-3">
                    <h4 class="card-title mb-0" id="activityNameLabel">Add New Activity Name</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-0">
                    <form id="activityNameForm">
                        <ul class="nav nav-tabs nav-tabs-custom nav-primary mb-3" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#general" role="tab">
                                    <span>General</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#advance" role="tab">
                                    <span>Advance</span>
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="general" role="tabpanel">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="activity_name" class="form-label">Activity Name</label>
                                            <select class="form-select" id="activity_name">
                                                <option value="">Select Activity Name</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="category_id" class="form-label">Activity Category</label>
                                            <select class="form-select" id="category_id">
                                                <option value="">Select Category</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="dept_id" class="form-label">Departments</label>
                                            <select class="form-select" id="dept_id" name="dept_id[]" multiple>
                                                <option value="">Select Departments</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="is_active" checked>
                                                <label class="form-check-label" for="is_active">Active</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" rows="4" placeholder="Enter description"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="advance" role="tabpanel">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="vertical" class="form-label">Verticals</label>
                                            <select class="form-select" id="vertical" name="vertical[]" multiple>
                                                <option value="">Select Verticals</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="from_month" class="form-label">From Month</label>
                                            <input type="date" class="form-control" id="from_month">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="to_month" class="form-label">To Month</label>
                                            <input type="date" class="form-control" id="to_month">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="from_year" class="form-label">From Year</label>
                                            <input type="number" class="form-control" id="from_year"
                                                placeholder="Enter year">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="to_year" class="form-label">To Year</label>
                                            <input type="number" class="form-control" id="to_year"
                                                placeholder="Enter year">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="approved_limit" class="form-label">Approved Limit</label>
                                            <input type="number" class="form-control" id="approved_limit"
                                                placeholder="Enter limit">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="approved_amount" class="form-label">Approved Amount</label>
                                            <input type="number" class="form-control" id="approved_amount"
                                                placeholder="Enter amount">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="hstack gap-2 justify-content-end">
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    id="saveActivityNameBtn">
                                    <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                                        <span class="loader" style="display: none;"></span>
                                    </i>
                                    Submit
                                </button>
                            </div>
                        </div>
                    </form>
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
    <script src="{{ asset('custom/js/pages/activity_name.js') }}"></script>
@endpush
