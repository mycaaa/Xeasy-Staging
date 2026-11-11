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
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <span class="fw-semibold fs-12">Status: </span>
                                    <span class="text-muted">
                                        @if ($status === 'active') Active
                                        @elseif($status === 'inactive') Inactive
                                        @else All
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
                                @can('new_claim_focus_mapping')
                                <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    data-bs-toggle="modal" data-bs-target="#claimFocusMappingModal" id="addClaimFocusMappingBtn">
                                    <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                    Add New
                                </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="claimFocusMappingTable" class="table nowrap dt-responsive align-middle table-hover table-bordered" style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th>S No.</th>
                                    <th style="text-align: left">Claim Name</th>
                                    <th>Expense Head</th>
                                    <th>W Type</th>
                                    <th>Focus SubL</th>
                                    <th>Focus Name</th>
                                    <th>Ledger Code</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @can('list_claim_focus_mapping')
                                @foreach ($claimFocusMappings as $key => $mapping)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td style="text-align: left">{{ $mapping->claim_name }}</td>
                                    <td>
                                        @if($mapping->expense_head_name)
                                        <span class="badge bg-secondary-subtle text-secondary">{{ $mapping->expense_head_name }}</span>
                                        @else
                                        <span class="badge bg-info-subtle text-info">All</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($mapping->wtype)
                                        <span class="badge bg-info-subtle text-info">
                                            {{ $mapping->wtype == '2' ? '2-Wheeler' : '4-Wheeler' }}
                                        </span>
                                        @else
                                        <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-warning-subtle text-warning">{{ $mapping->focus_name_subl }}</span></td>
                                    <td>{{ $mapping->focus_name }}</td>
                                    <td><span class="badge bg-primary-subtle text-primary">{{ $mapping->focus_ledger_code }}</span></td>
                                    <td>
                                        @if ($mapping->is_active)
                                        <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                        @else
                                        <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @can("modify_claim_focus_mapping")
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#claimFocusMappingModal"
                                            class="btn btn-primary btn-sm edit-claim-focus-mapping" data-id="{{ $mapping->id }}">
                                            <i class="ri-edit-2-fill"></i>
                                        </button>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <span class="text-danger">You do not have permission to view the Claim Focus Mapping List.</span>
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

<!-- Modal -->
<div class="modal fade" id="claimFocusMappingModal" tabindex="-1" aria-labelledby="claimFocusMappingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 overflow-hidden">
            <div class="progress-container" style="display: none;">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header p-3">
                <h4 class="card-title mb-0" id="claimFocusMappingLabel">Add New Claim Focus Mapping</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="claimFocusMappingForm">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="claim_id" class="form-label">Claim Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="claim_id" required>
                                    <option value="">Select Claim Type</option>
                                    @foreach($claimTypes as $claimType)
                                    <option value="{{ $claimType->ClaimId }}">{{ $claimType->ClaimName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="expense_head_category_id" class="form-label">Expense Head Category</label>
                                <select class="form-select" id="expense_head_category_id">
                                    <option value="">All (Generic Mapping)</option>
                                    @foreach($expenseHeadCategories as $category)
                                    <option value="{{ $category->id }}">{{ $category->expense_head_name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Leave empty for generic mapping that applies to all expense heads</div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label for="wtype" class="form-label">W Type</label>
                                <select class="form-select" id="wtype">
                                    <option value="">N/A</option>
                                    <option value="2">2-Wheeler</option>
                                    <option value="4">4-Wheeler</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label for="focus_name_subl" class="form-label">Focus Name SubL <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="focus_name_subl" placeholder="e.g., SLC-0145" required>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label for="focus_ledger_code" class="form-label">Focus Ledger Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="focus_ledger_code" placeholder="e.g., INDIRECT-TRA-16" required>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="mb-3">
                                <label for="focus_name" class="form-label">Focus Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="focus_name" placeholder="e.g., INDIRECT-TRA-09" required>
                            </div>
                        </div>
                        <div class="col-lg-4">
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
                            <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill" id="saveClaimFocusMappingBtn">
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

<!-- Hidden data for JavaScript -->
<script type="application/json" id="claimTypesData">
    @json($claimTypes)
</script>
<script type="application/json" id="expenseHeadCategoriesData">
    @json($expenseHeadCategories)
</script>

@endsection

@push('scripts')
<script src="{{ asset('custom/js/pages/claim_focus_mapping.js') }}"></script>
@endpush