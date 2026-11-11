@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Claim Type List</h4>
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
                                @can('create claimtype')
                                    <button type="button"
                                        class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                        data-bs-toggle="modal" data-bs-target="#claimTypeModal" id="addClaimTypeBtn">
                                        <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add
                                        New
                                    </button>
                                @endcan
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="claimTypeTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th style="text-align: left">Claim Name</th>
                                        <th>Focus Code</th>
                                        <th>Color Code</th>
                                        <th>Group</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @can('claim_type_list')
                                        @foreach ($claimTypes as $key => $claim)
                                            @can("view claimtype {$claim->ClaimId}")
                                                <tr>
                                                    <td>{{ $key + 1 }}</td>
                                                    <td style="text-align: left">{{ $claim->ClaimName }}</td>
                                                    <td>{{ $claim->focus_code ?? '-' }}</td>
                                                    <td>
                                                        @if($claim->color_code)
                                                            <span style="display: inline-block; width: 20px; height: 20px; background-color: {{ $claim->color_code }}; border: 1px solid #ccc; border-radius: 3px; vertical-align: middle;"></span>
                                                            <span style="margin-left: 5px;">{{ $claim->color_code }}</span>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>{{ $claim->group->cgName ?? '-' }}</td>
                                                    <td>
                                                        @if ($claim->ClaimStatus == 'A' || $claim->ClaimStatus == 'B')
                                                            <span
                                                                class="badge bg-success-subtle text-success badge-border">Active</span>
                                                        @else
                                                            <span
                                                                class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @can("update claimtype {$claim->ClaimId}")
                                                            <button type="button" data-bs-toggle="modal"
                                                                data-bs-target="#claimTypeModal"
                                                                class="btn btn-primary btn-sm edit-claim-type"
                                                                data-id="{{ $claim->ClaimId }}"><i class="ri-edit-2-fill"></i></button>
                                                        @endcan

                                                    </td>
                                                </tr>
                                            @endcan
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <span class="text-danger">You do not have permission to view the Claim Type
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
    <div class="modal fade" id="claimTypeModal" tabindex="-1" aria-labelledby="claimTypeLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 overflow-hidden">
                <div class="progress-container" style="display: none;">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header p-3">
                    <h4 class="card-title mb-0" id="claimTypeLabel">Add New Claim Type</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="claimTypeForm">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="claim_name" class="form-label">Claim Name</label>
                                    <input type="text" class="form-control" id="claim_name"
                                        placeholder="Enter claim name">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="claim_code" class="form-label">Claim Code</label>
                                    <input type="text" class="form-control" id="claim_code"
                                        placeholder="Enter claim code">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="focus_code" class="form-label">Focus Code</label>
                                    <input type="text" class="form-control" id="focus_code"
                                        placeholder="Enter focus code">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="color_code" class="form-label">Color Code</label>
                                    <input type="color" class="form-control form-control-color" id="color_code"
                                        title="Choose color">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="cg_id" class="form-label">Claim Group</label>
                                    <select class="form-select" id="cg_id">
                                        <option value="">Select Group</option>
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
                        </div>
                        <div class="col-lg-12">
                            <div class="hstack gap-2 justify-content-end">
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    id="saveClaimTypeBtn">
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
    <script src="{{ asset('custom/js/pages/claim_type.js') }}"></script>
@endpush
