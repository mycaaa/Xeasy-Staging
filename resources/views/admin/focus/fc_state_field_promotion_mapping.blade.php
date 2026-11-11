@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header align-items-center d-flex" style="justify-content: space-between;">
                        <x-configuration-tabs />
                        <div style="display: flex;gap:8px;align-items: baseline;">
                            <div class="dropdown card-header-dropdown">
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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
                                @can('new_state_field_promotion_mapping')
                                <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    data-bs-toggle="modal" data-bs-target="#stateFieldPromotionMappingModal" id="addStateFieldPromotionMappingBtn">
                                    <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                    Add New
                                </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="stateFieldPromotionMappingTable" class="table nowrap dt-responsive align-middle table-hover table-bordered" style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th>S No.</th>
                                    <th style="text-align: left">State Name</th>
                                    <th>Focus Name</th>
                                    <th>Focus Ledger Code</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @can('list_state_field_promotion_mapping')
                                @foreach ($mappings as $key => $mapping)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td style="text-align: left">{{ $mapping->state_name_display ?? ucwords($mapping->state_name) }}</td>
                                    <td>{{ $mapping->focus_name }}</td>
                                    <td><span class="badge bg-info-subtle text-info">{{ $mapping->focus_ledger_code }}</span></td>
                                    <td>
                                        @if ($mapping->is_active)
                                        <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                        @else
                                        <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @can("modify_state_field_promotion_mapping")
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#stateFieldPromotionMappingModal"
                                            class="btn btn-primary btn-sm edit-state-field-promotion-mapping" data-id="{{ $mapping->id }}">
                                            <i class="ri-edit-2-fill"></i>
                                        </button>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <span class="text-danger">You do not have permission to view the State Field Promotion Mapping List.</span>
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
<div class="modal fade" id="stateFieldPromotionMappingModal" tabindex="-1" aria-labelledby="stateFieldPromotionMappingLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 overflow-hidden">
            <div class="progress-container" style="display: none;">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header p-3">
                <h4 class="card-title mb-0" id="stateFieldPromotionMappingLabel">Add New State Field Promotion Mapping</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="stateFieldPromotionMappingForm">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label for="state_name" class="form-label">State Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="state_name" placeholder="e.g., Maharashtra" required>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label for="focus_name" class="form-label">Focus Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="focus_name" placeholder="e.g., INDIRECT-TRA-09" required>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label for="focus_ledger_code" class="form-label">Focus Ledger Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="focus_ledger_code" placeholder="e.g., SLC-0145" required>
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
                            <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill" id="saveStateFieldPromotionMappingBtn">
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
@push('scripts')
<script src="{{ asset('custom/js/pages/state_field_promotion_mapping.js') }}"></script>
@endpush