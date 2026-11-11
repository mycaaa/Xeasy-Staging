@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Permissions List</h4>
                            <div class="dropdown card-header-dropdown">
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <span class="fw-semibold fs-12">Filter by Group: </span>
                                    <span class="text-muted">
                                        {{ request('group_id') ? $group->firstWhere('id', request('group_id'))->name ?? 'All Groups' : 'All Groups' }}
                                        <i class="mdi mdi-chevron-down"></i>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ url()->current() }}?group_id=">All Groups</a>
                                    @foreach ($group as $g)
                                        <a class="dropdown-item"
                                            href="{{ url()->current() }}?group_id={{ $g->id }}">{{ $g->name }}</a>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    data-bs-toggle="modal" data-bs-target="#permissionModal" id="addPermissionBtn">
                                    <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add
                                    New
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="permissionsMasterTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th style="text-align: left;">Permission</th>
                                        <th style="text-align: left;">Permission Key</th>
                                        <th>Group</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($permissions as $key => $permission)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td style="text-align:left">{{ $permission->permission_key }}</td>
                                            <td style="text-align:left">{{ $permission->name }}</td>
                                            <td><span
                                                    class="badge bg-dark-subtle text-body">{{ $permission->group_name ?? '-' }}</span>
                                            </td>
                                            <td>
                                                @if ($permission->status == 1)
                                                    <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button" data-bs-toggle="modal" data-bs-target="#permissionModal"
                                                    id="addPermissionBtn" class="btn btn-primary btn-sm edit-permission"
                                                    data-id="{{ $permission->id }}"><i class="ri-edit-2-fill"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal.permission />
@endsection
@push('scripts')
    <script src="{{ asset('custom/js/pages/permissions.js') }}"></script>
@endpush