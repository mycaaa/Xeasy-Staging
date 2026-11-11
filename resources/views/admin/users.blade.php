@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header align-items-center d-flex gap-2">
                        <h4 class="card-title mb-0 flex-grow-1">User List</h4>
                        <div class="dropdown card-header-dropdown">
                            <a class="text-reset dropdown-btn d-flex align-items-center" href="#"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="fw-semibold fs-12 me-1">Employee Status:</span>
                                <span class="text-muted">
                                    @if($status === 'active')
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
                                <a class="dropdown-item" href="?status=all">All</a>
                                <a class="dropdown-item" href="?status=active">Active</a>
                                <a class="dropdown-item" href="?status=inactive">Inactive</a>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <a data-bs-toggle="tooltip" data-bs-placement="bottom" title="User Login Activity" href="{{ url('user-activity') }}">
                                <i class="ri-bar-chart-fill me-1 align-middle"></i>
                            </a>
                        </div>
                        <div class="flex-shrink-0 d-flex gap-2">
                            @can('new_user')
                            <button type="button" class="btn btn-info btn-label waves-effect waves-light rounded-pill"
                                id="importEmployee">
                                <i class="ri-download-2-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                Import
                            </button>
                            <button type="button"
                                class="btn btn-success btn-label waves-effect waves-light rounded-pill"
                                data-bs-toggle="modal" data-bs-target="#userModal" id="addGuestUserBtn">
                                <i class="ri-user-add-line label-icon align-middle rounded-pill fs-16 me-2"></i> Add Guest User
                            </button>
                            <button type="button"
                                class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                data-bs-toggle="modal" data-bs-target="#userModal" id="addUserBtn">
                                <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add Employee
                            </button>
                            @endcan
                        </div>
                    </div>
                    <div class="card-body">
                        @can('user_list')
                        <table id="usersMasterTable"
                            class="table nowrap dt-responsive align-middle table-hover table-bordered"
                            style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th>S No.</th>
                                    <th>Employee ID</th>
                                    <th>Employee Code</th>
                                    <th>Employee Name</th>
                                    <th>Employee Status</th>
                                    <th>Company</th>
                                    <th>Roles</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                        @else
                        <div class="alert alert-danger text-center">
                            You do not have permission to view the user list.
                        </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<x-modal.users />
@endsection
@push('scripts')
<script src="{{ asset('custom/js/pages/users.js') }}"></script>
@endpush