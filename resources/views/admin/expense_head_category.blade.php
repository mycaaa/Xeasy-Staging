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
                                    @can('create expensehead')
                                        <button type="button"
                                            class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                            data-bs-toggle="modal" data-bs-target="#expenseHeadModal" id="addExpenseHeadBtn">
                                            <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                            Add New
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="expenseHeadTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th style="text-align: left">Expense Head Name</th>
                                        <th>Short Code</th>
                                        <th>Field Type</th>
                                        <th>Has File</th>
                                        <th>File Required</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @can('expense_head_list')
                                        @foreach ($expenseHeads as $key => $expenseHead)
                                            @can("view expensehead {$expenseHead->id}")
                                                <tr>
                                                    <td>{{ $key + 1 }}</td>
                                                    <td style="text-align: left">{{ $expenseHead->expense_head_name }}</td>
                                                    <td>{{ $expenseHead->short_code ?? '-' }}</td>
                                                    <td>{{ ucfirst($expenseHead->field_type) }}</td>
                                                    <td>
                                                        @if ($expenseHead->has_file)
                                                            <span
                                                                class="badge bg-primary-subtle text-primary">Yes</span>
                                                        @else
                                                            <span class="badge bg-danger-subtle text-danger">No</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($expenseHead->file_required)
                                                            <span
                                                                class="badge bg-primary-subtle text-primary">Yes</span>
                                                        @else
                                                            <span class="badge bg-danger-subtle text-danger">No</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($expenseHead->status)
                                                            <span
                                                                class="badge bg-success-subtle text-success badge-border">Active</span>
                                                        @else
                                                            <span
                                                                class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @can("update expensehead {$expenseHead->id}")
                                                            <button type="button" data-bs-toggle="modal"
                                                                data-bs-target="#expenseHeadModal"
                                                                class="btn btn-primary btn-sm edit-expense-head"
                                                                data-id="{{ $expenseHead->id }}"><i class="ri-edit-2-fill"></i></button>
                                                        @endcan

                                                    </td>
                                                </tr>
                                            @endcan
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="8" class="text-center">
                                                <span class="text-danger">You do not have permission to view the Expense
                                                    Head
                                                    Category List.</span>
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
    <div class="modal fade" id="expenseHeadModal" tabindex="-1" aria-labelledby="expenseHeadLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 overflow-hidden">
                <div class="progress-container" style="display: none;">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header p-3">
                    <h4 class="card-title mb-0" id="expenseHeadLabel">Add New Expense Head Category</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="expenseHeadForm">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="expense_head_name" class="form-label">Expense Head Name</label>
                                    <input type="text" class="form-control" id="expense_head_name"
                                        placeholder="Enter expense head name">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="short_code" class="form-label">Short Code</label>
                                    <input type="text" class="form-control" id="short_code"
                                        placeholder="Enter short code">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="field_type" class="form-label">Field Type</label>
                                    <select class="form-select" id="field_type">
                                        <option value="number">Number</option>
                                        <option value="text">Text</option>
                                        <option value="textarea">Textarea</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2">
                                <div class="mb-3">
                                    <label class="form-label">Has File</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="has_file">
                                        <label class="form-check-label" for="has_file">Yes</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-2">
                                <div class="mb-3">
                                    <label class="form-label">File Required</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="file_required">
                                        <label class="form-check-label" for="file_required">Yes</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-2">
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
                                    id="saveExpenseHeadBtn">
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
    <script src="{{ asset('custom/js/pages/expense_head_category.js') }}"></script>
@endpush
