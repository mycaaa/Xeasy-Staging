@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Menu List</h4>
                            <div class="dropdown card-header-dropdown">
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <span class="fw-semibold fs-12">Status: </span>
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
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=all">All</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=active">Active</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=inactive">Inactive</a>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                @can('new_menu')
                                    <button type="button"
                                        class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                        data-bs-toggle="modal" data-bs-target="#menuModal" id="menuBtn">
                                        <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add
                                        New
                                    </button>
                                @endcan
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="menuMasterTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th style="text-align: left">Menu</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @can('menu_list')
                                        @foreach ($menus as $key => $menu)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td style="text-align: left">{{ $menu->title }}</td>
                                                <td>
                                                    @if ($menu->status == 1)
                                                        <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @can('modify_menu')
                                                        <button type="button" data-bs-toggle="modal" data-bs-target="#menuModal"
                                                            id="addMenuBtn" class="btn btn-primary btn-sm edit-menu"
                                                            data-id="{{ $menu->id }}"><i class="ri-edit-2-fill"></i></button>
                                                    @endcan
                                                    @can('menu_activity_logs')
                                                        <button type="button" class="btn btn-info btn-sm view-logs"
                                                            data-id="{{ $menu->id }}" data-bs-toggle="modal"
                                                            data-bs-target="#logModal"><i class="ri-file-list-3-fill"></i></button>
                                                    @endcan

                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <span class="text-danger">You do not have permission to view the menu
                                                    list.</span>
                                            </td>
                                        </tr>
                                    @endcan
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal.menu />

    <!-- Log Modal -->
    <div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="progress-container" style="display: none">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header">
                    <h5 class="modal-title" id="logModalLabel">Activity Logs for Menu - <span id="logMenuTitle"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-striped" id="logTable">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Description</th>
                                <th>Changes</th>
                                <th>User</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('custom/js/pages/menu.js') }}"></script>
@endpush