@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header align-items-center d-flex" style="justify-content: space-between;">
                        <h4 class="card-title mb-0 flex-grow-1">Vendor List</h4>
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
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=all&request_status={{ $requestStatus }}">All</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=active&request_status={{ $requestStatus }}">Active</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=inactive&request_status={{ $requestStatus }}">Inactive</a>
                                </div>
                            </div>
                            <div class="dropdown card-header-dropdown">
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <span class="fw-semibold fs-12">Vendor Type: </span>
                                    <span class="text-muted">
                                        @if ($vendorTypeId === 'all')
                                        All
                                        @else
                                        {{ $vendorTypes->find($vendorTypeId)->vendor_type ?? 'All' }}
                                        @endif
                                        <i class="mdi mdi-chevron-down"></i>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ url()->current() }}?status={{ $status }}&request_status={{ $requestStatus }}&vendor_type_id=all">
                                        <span>All</span>
                                        <span>({{ $vendors->count() }})</span>
                                    </a>
                                    @foreach ($vendorTypes as $type)
                                    <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ url()->current() }}?status={{ $status }}&request_status={{ $requestStatus }}&vendor_type_id={{ $type->id }}">
                                        <span>{{ $type->vendor_type }}</span>
                                        <span>({{ $type->vendor_count }})</span>
                                    </a>
                                    @endforeach
                                </div>
                            </div>
                            <div class="dropdown card-header-dropdown">
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <span class="fw-semibold fs-12">Request Status: </span>
                                    <span class="text-muted">
                                        @if ($requestStatus === 'P')
                                        Pending
                                        @elseif($requestStatus === 'A')
                                        Approved
                                        @elseif($requestStatus === 'R')
                                        Rejected
                                        @elseif($requestStatus === 'H')
                                        Hold
                                        @else
                                        All
                                        @endif
                                        <i class="mdi mdi-chevron-down"></i>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item d-flex justify-content-between align-items-center"
                                        href="{{ url()->current() }}?status={{ $status }}&request_status=all">
                                        All <span class="badge bg-secondary">{{ $requestCounts->total_count ?? 0 }}</span>
                                    </a>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center"
                                        href="{{ url()->current() }}?status={{ $status }}&request_status=P">
                                        Pending <span class="badge bg-warning text-dark">{{ $requestCounts->pending_count ?? 0 }}</span>
                                    </a>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center"
                                        href="{{ url()->current() }}?status={{ $status }}&request_status=A">
                                        Approved <span class="badge bg-success">{{ $requestCounts->approved_count ?? 0 }}</span>
                                    </a>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center"
                                        href="{{ url()->current() }}?status={{ $status }}&request_status=R">
                                        Rejected <span class="badge bg-danger">{{ $requestCounts->rejected_count ?? 0 }}</span>
                                    </a>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center"
                                        href="{{ url()->current() }}?status={{ $status }}&request_status=H">
                                        Hold <span class="badge bg-info text-dark">{{ $requestCounts->hold_count ?? 0 }}</span>
                                    </a>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                @can('create_vendor')
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    data-bs-toggle="modal" data-bs-target="#vendorModal"
                                    id="addVendorBtn">
                                    <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                    Add New
                                </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="vendorTable"
                            class="table nowrap dt-responsive align-middle table-hover table-bordered"
                            style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th>S No.</th>
                                    <th style="text-align: left">Vendor Name</th>
                                    <th>Vendor Type</th>
                                    <th>Contact Person</th>
                                    <th>Contact Number</th>
                                    <th>Email</th>
                                    <th>GST No</th>
                                    <th>Status</th>
                                    <th>Request Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @can('vendor_list')
                                @foreach ($vendors as $key => $vendor)
                                @can("view vendor {$vendor->id}")
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td style="text-align: left">{{ $vendor->vendor_name }}</td>
                                    <td>{{ ucfirst($vendor->vendor_type) }}</td>
                                    <td>{{ $vendor->contact_person ?? '-' }}</td>
                                    <td>{{ $vendor->contact_number ?? '-' }}</td>
                                    <td>{{ $vendor->email ?? '-' }}</td>
                                    <td>{{ $vendor->gst_no ?? '-' }}</td>
                                    <td>
                                        @if ($vendor->status == '1')
                                        <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                        @else
                                        <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($vendor->request_status == 'P')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                        @elseif ($vendor->request_status == 'A')
                                        <span class="badge bg-success">Approved</span>
                                        @elseif ($vendor->request_status == 'R')
                                        <span class="badge bg-danger">Rejected</span>
                                        @elseif ($vendor->request_status == 'H')
                                        <span class="badge bg-info text-dark">Hold</span>
                                        @else
                                        <span class="badge bg-secondary">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @can("update vendor {$vendor->id}")

                                        <button type="button" data-bs-toggle="modal"
                                            data-bs-target="#vendorModal"
                                            class="btn btn-primary btn-sm edit-vendor"
                                            data-id="{{ $vendor->id }}"><i class="ri-edit-2-fill"></i></button>

                                        @endcan
                                    </td>
                                </tr>
                                @endcan
                                @endforeach
                                @else
                                <tr>
                                    <td colspan="10" class="text-center">
                                        <span class="text-danger">You do not have permission to view the Vendor List.</span>
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
    <div class="modal fade" id="vendorModal" tabindex="-1" aria-labelledby="vendorLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 overflow-hidden">
                <div class="progress-container" style="display: none;">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header p-3">
                    <h4 class="card-title mb-0" id="vendorLabel">Add New Vendor</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="vendorForm">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="vendor_name" class="form-label">Vendor Name</label>
                                    <input type="text" class="form-control" id="vendor_name"
                                        placeholder="Enter vendor name">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="vendor_type" class="form-label">Vendor Type</label>
                                    <select class="form-select" id="vendor_type">
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="vendor_address" class="form-label">Address</label>
                                    <textarea class="form-control" id="vendor_address" rows="1" placeholder="Enter address"></textarea>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="contact_person"
                                        placeholder="Enter contact person">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" id="contact_number"
                                        placeholder="Enter contact number">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email"
                                        placeholder="Enter email">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="gst_no" class="form-label">GST No</label>
                                    <input type="text" class="form-control" id="gst_no"
                                        placeholder="Enter GST number">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="request_status" class="form-label">Request Status</label>
                                    <select class="form-select" id="request_status">
                                        <option value="P">Pending</option>
                                        <option value="A">Approved</option>
                                        <option value="R">Rejected</option>
                                        <option value="H">Hold</option>
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
                                    id="saveVendorBtn">
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
    <script src="{{ asset('custom/js/pages/vendor_list.js') }}"></script>
    @endpush