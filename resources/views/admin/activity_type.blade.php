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
                                    @can('create activitytype')
                                        <button type="button"
                                            class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                            data-bs-toggle="modal" data-bs-target="#activityTypeModal" id="addActivityTypeBtn">
                                            <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                            Add
                                            New
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="activityTypeTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th style="text-align: left">Type Name</th>
                                        <th>Focus Code</th>
                                        <th>Departments</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @can('activity_type_list')
                                        @foreach ($activityTypes as $key => $type)
                                            @can("view activitytype {$type->id}")
                                                <tr>
                                                    <td>{{ $key + 1 }}</td>
                                                    <td style="text-align: left">{{ $type->type_name }}</td>
                                                    <td>{{ $type->focus_code ?? '-' }}</td>
                                                    <td>
                                                        @php
                                                            $departmentIds = $type->department_id
                                                                ? explode(',', $type->department_id)
                                                                : [];
                                                            $departmentNames =
                                                                App\Models\CoreDepartments::whereIn(
                                                                    'id',
                                                                    $departmentIds,
                                                                )
                                                                    ->where('is_active', 1)
                                                                    ->pluck('department_name')
                                                                    ->implode(', ') ?:
                                                                '-';
                                                        @endphp
                                                        {{ $departmentNames }}
                                                    </td>
                                                    <td>{{ $type->description ?? '-' }}</td>
                                                    <td>
                                                        @if ($type->status)
                                                            <span
                                                                class="badge bg-success-subtle text-success badge-border">Active</span>
                                                        @else
                                                            <span
                                                                class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @can("update activitytype {$type->id}")
                                                            <button type="button" data-bs-toggle="modal"
                                                                data-bs-target="#activityTypeModal"
                                                                class="btn btn-primary btn-sm edit-activity-type"
                                                                data-id="{{ $type->id }}"><i class="ri-edit-2-fill"></i></button>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endcan
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="7" class="text-center">
                                                <span class="text-danger">You do not have permission to view the Activity
                                                    Type
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
    <div class="modal fade" id="activityTypeModal" tabindex="-1" aria-labelledby="activityTypeLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 overflow-hidden">
                <div class="progress-container" style="display: none;">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header p-3">
                    <h4 class="card-title mb-0" id="activityTypeLabel">Add New Activity Type</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="activityTypeForm">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="type_name" class="form-label">Type Name</label>
                                    <input type="text" class="form-control" id="type_name" placeholder="Enter type name">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="focus_code" class="form-label">Focus Code</label>
                                    <input type="text" class="form-control" id="focus_code" placeholder="Enter focus code">
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="department_id" class="form-label">Departments</label>
                                    <select class="form-select" id="department_id" name="department_id[]" multiple>
                                        <option value="">Select Departments</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" rows="2" placeholder="Enter description"></textarea>
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
                        </div>
                        <div class="col-lg-12">
                            <div class="hstack gap-2 justify-content-end">
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    id="saveActivityTypeBtn">
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
@endpush
@push('scripts')
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('custom/js/pages/activity_type.js') }}"></script>
@endpush
