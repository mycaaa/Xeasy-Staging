@extends('layouts.app')

@section('title', 'Employee Ledger Import')

@section('content')
<div class="page-content">

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="ri-file-list-3-line me-2"></i>Employee Ledger Balance Import
                    </h4>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success" onclick="openCreateModal()">
                            <i class="ri-add-line me-1"></i>Add New
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeLedgerImportModal">
                            <i class="ri-upload-2-line me-1"></i>Import Data
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Search Bar -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search by employee name, code, grade, remark..." aria-label="Search by employee name, code, grade, remark" onkeyup="handleSearch()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            
                        </div>
                    </div>
                    
                    <!-- Imported Data -->
                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="ledgerDataTable">
                                    <thead>
                                        <tr>
                                            <th>Employee Code</th>
                                            <th>Employee Name</th>
                                            <th>Month</th>
                                            <th>Dr (YTD)</th>
                                            <th>Cr (YTD)</th>
                                            <th>Balance</th>
                                            <th>Imprest 1</th>
                                            <th>Imprest 2</th>
                                            <th>Advances</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledgerDataBody">
                                        <tr>
                                            <td colspan="11" class="text-center text-muted">
                                                <i class="ri-loader-4-line ri-spin me-2"></i>Loading data...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="text-muted">
                                    <span id="recordsInfo">Showing 0 records</span>
                                </div>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0" id="pagination">
                                        <!-- Pagination will be loaded here -->
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Include the import modal component -->
<x-modal.employee-ledger-import />

<!-- Create/Edit Modal -->
<div class="modal fade" id="crudModal" tabindex="-1" aria-labelledby="crudModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="crudModalLabel">
                    <i class="ri-file-add-line me-2"></i><span id="modalTitle">Add New Record</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="crudForm">
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <input type="hidden" id="recordId" name="id">
                    
                    <!-- Employee -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Dr, Cr, Balance -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="dr" class="form-label">Dr (YTD)</label>
                            <input type="number" class="form-control" id="dr" name="dr" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="cr" class="form-label">Cr (YTD)</label>
                            <input type="number" class="form-control" id="cr" name="cr" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="balance" class="form-label">Balance <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="balance" name="balance" step="0.01" required>
                        </div>
                    </div>
                    
                    <!-- Fixed Imprest 1 -->
                    <h6 class="mt-3 mb-2 text-primary"><i class="ri-money-dollar-circle-line me-1"></i>Fixed Imprest 1</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fixed_imprest_amt_1" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="fixed_imprest_amt_1" name="fixed_imprest_amt_1" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="fixed_imprest_date_1" class="form-label">Date</label>
                            <input type="date" class="form-control" id="fixed_imprest_date_1" name="fixed_imprest_date_1">
                        </div>
                    </div>
                    
                    <!-- Fixed Imprest 2 -->
                    <h6 class="mt-3 mb-2 text-primary"><i class="ri-money-dollar-circle-line me-1"></i>Fixed Imprest 2</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fixed_imprest_amt_2" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="fixed_imprest_amt_2" name="fixed_imprest_amt_2" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="fixed_imprest_date_2" class="form-label">Date</label>
                            <input type="date" class="form-control" id="fixed_imprest_date_2" name="fixed_imprest_date_2">
                        </div>
                    </div>
                    
                    <!-- Advance 1 -->
                    <h6 class="mt-3 mb-2 text-success"><i class="ri-hand-coin-line me-1"></i>Advance 1</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="advance_amt_1" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="advance_amt_1" name="advance_amt_1" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_date_1" class="form-label">Date</label>
                            <input type="date" class="form-control" id="advance_date_1" name="advance_date_1">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_month_1" class="form-label">Month</label>
                            <input type="text" class="form-control" id="advance_month_1" name="advance_month_1" placeholder="e.g., January">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_purpose_1" class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="advance_purpose_1" name="advance_purpose_1">
                        </div>
                    </div>
                    
                    <!-- Advance 2 -->
                    <h6 class="mt-3 mb-2 text-success"><i class="ri-hand-coin-line me-1"></i>Advance 2</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="advance_amt_2" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="advance_amt_2" name="advance_amt_2" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_date_2" class="form-label">Date</label>
                            <input type="date" class="form-control" id="advance_date_2" name="advance_date_2">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_month_2" class="form-label">Month</label>
                            <input type="text" class="form-control" id="advance_month_2" name="advance_month_2" placeholder="e.g., February">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_purpose_2" class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="advance_purpose_2" name="advance_purpose_2">
                        </div>
                    </div>
                    
                    <!-- Advance 3 -->
                    <h6 class="mt-3 mb-2 text-success"><i class="ri-hand-coin-line me-1"></i>Advance 3</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="advance_amt_3" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="advance_amt_3" name="advance_amt_3" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_date_3" class="form-label">Date</label>
                            <input type="date" class="form-control" id="advance_date_3" name="advance_date_3">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_month_3" class="form-label">Month</label>
                            <input type="text" class="form-control" id="advance_month_3" name="advance_month_3" placeholder="e.g., March">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_purpose_3" class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="advance_purpose_3" name="advance_purpose_3">
                        </div>
                    </div>
                    
                    <!-- Advance 4 -->
                    <h6 class="mt-3 mb-2 text-success"><i class="ri-hand-coin-line me-1"></i>Advance 4</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="advance_amt_4" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="advance_amt_4" name="advance_amt_4" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_date_4" class="form-label">Date</label>
                            <input type="date" class="form-control" id="advance_date_4" name="advance_date_4">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_month_4" class="form-label">Month</label>
                            <input type="text" class="form-control" id="advance_month_4" name="advance_month_4" placeholder="e.g., April">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="advance_purpose_4" class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="advance_purpose_4" name="advance_purpose_4">
                        </div>
                    </div>
                    
                    <!-- Remark -->
                    <div class="mb-3">
                        <label for="remark" class="form-label">Remark</label>
                        <textarea class="form-control" id="remark" name="remark" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">
                    <i class="ri-eye-line me-2"></i>View Record Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ri-close-line me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/classic.min.css" />
<link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/monolith.min.css" />
<link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/nano.min.css" />
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush 

@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>

<script>
let currentPage = 1;
const recordsPerPage = 10;
let searchTimeout = null;
let currentEditId = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeSelect2();
    loadLedgerData();
    
    // Form submission
    document.getElementById('crudForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveRecord();
    });
});

function initializeSelect2() {
    // Initialize employee select in CRUD form only
    $('#employee_id').select2({
        placeholder: 'Select Employee',
        allowClear: true,
          width: '100%',
        dropdownParent: $('#crudModal'),
        ajax: {
            url: '/api/employee-ledger/employees',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    search: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data.data.map(emp => ({
                        id: emp.id,
                        text: `${emp.EmpCode} - ${emp.name}`
                    }))
                };
            },
            cache: true
        }
    });
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    currentPage = 1;
    loadLedgerData();
}

function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage = 1;
        loadLedgerData();
    }, 500);
}

function loadLedgerData(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput')?.value || '';
    
    let url = `/api/employee-ledger/data?page=${page}&per_page=${recordsPerPage}&search=${encodeURIComponent(search)}`;
    
    fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        displayLedgerData(data.data || []);
        updatePagination(data);
        updateRecordsInfo(data);
    })
    .catch(error => {
        console.error('Error loading ledger data:', error);
        showErrorMessage('Failed to load ledger data');
    });
}

function displayLedgerData(records) {
    const tbody = document.getElementById('ledgerDataBody');
    
    if (records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-muted">
                    <i class="ri-information-line me-2"></i>No ledger data found
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = records.map(record => {
        const advanceCount = [
            record.advance_amt_1,
            record.advance_amt_2,
            record.advance_amt_3,
            record.advance_amt_4
        ].filter(amt => amt && amt > 0).length;
        
        return `
        <tr>
            <td>
                <span class="badge bg-primary">${record.EmpCode || '-'}</span>
            </td>
            <td>
                <strong>${record.employee_name || '-'}</strong>
            </td>
            <td>${record.month || '-'}</td>
            <td class="text-end">
                <span class="text-danger">₹${formatAmount(record.dr)}</span>
            </td>
            <td class="text-end">
                <span class="text-success">₹${formatAmount(record.cr)}</span>
            </td>
            <td class="text-end">
                <span class="fw-bold text-info">₹${formatAmount(record.balance)}</span>
            </td>
            <td class="text-end">
                <span class="text-muted">₹${formatAmount(record.fixed_imprest_amt_1)}</span>
                ${record.fixed_imprest_date_1 ? `<br><small>${formatDate(record.fixed_imprest_date_1)}</small>` : ''}
            </td>
            <td class="text-end">
                <span class="text-muted">₹${formatAmount(record.fixed_imprest_amt_2)}</span>
                ${record.fixed_imprest_date_2 ? `<br><small>${formatDate(record.fixed_imprest_date_2)}</small>` : ''}
            </td>
            <td class="text-center">
                <span class="badge bg-secondary">${advanceCount} Advance(s)</span>
            </td>
            <td>${formatDateTime(record.created_at)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-info" onclick="viewRecord(${record.id})" title="View Details">
                        <i class="ri-eye-line"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="editRecord(${record.id})" title="Edit">
                        <i class="ri-edit-line"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteRecord(${record.id})" title="Delete">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </td>
        </tr>
    `}).join('');
}

function openCreateModal() {
    currentEditId = null;
    document.getElementById('modalTitle').textContent = 'Add New Record';
    document.getElementById('crudForm').reset();
    document.getElementById('recordId').value = '';
    
    // Reset Select2
    $('#employee_id').val(null).trigger('change');
    
    const modal = new bootstrap.Modal(document.getElementById('crudModal'));
    modal.show();
}

function editRecord(id) {
    currentEditId = id;
    document.getElementById('modalTitle').textContent = 'Edit Record';
    
    fetch(`/api/employee-ledger/${id}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const record = data.data;
            document.getElementById('recordId').value = record.id;
            
            // Set Select2 value with employee data
            const employeeOption = new Option(
                `${record.EmpCode} - ${record.employee_name}`,
                record.employee_id,
                true,
                true
            );
            $('#employee_id').append(employeeOption).trigger('change');
            
            document.getElementById('dr').value = record.dr || '';
            document.getElementById('cr').value = record.cr || '';
            document.getElementById('balance').value = record.balance || '';
            
            document.getElementById('fixed_imprest_amt_1').value = record.fixed_imprest_amt_1 || '';
            document.getElementById('fixed_imprest_date_1').value = record.fixed_imprest_date_1 || '';
            document.getElementById('fixed_imprest_amt_2').value = record.fixed_imprest_amt_2 || '';
            document.getElementById('fixed_imprest_date_2').value = record.fixed_imprest_date_2 || '';
            
            document.getElementById('advance_amt_1').value = record.advance_amt_1 || '';
            document.getElementById('advance_date_1').value = record.advance_date_1 || '';
            document.getElementById('advance_month_1').value = record.advance_month_1 || '';
            document.getElementById('advance_purpose_1').value = record.advance_purpose_1 || '';
            
            document.getElementById('advance_amt_2').value = record.advance_amt_2 || '';
            document.getElementById('advance_date_2').value = record.advance_date_2 || '';
            document.getElementById('advance_month_2').value = record.advance_month_2 || '';
            document.getElementById('advance_purpose_2').value = record.advance_purpose_2 || '';
            
            document.getElementById('advance_amt_3').value = record.advance_amt_3 || '';
            document.getElementById('advance_date_3').value = record.advance_date_3 || '';
            document.getElementById('advance_month_3').value = record.advance_month_3 || '';
            document.getElementById('advance_purpose_3').value = record.advance_purpose_3 || '';
            
            document.getElementById('advance_amt_4').value = record.advance_amt_4 || '';
            document.getElementById('advance_date_4').value = record.advance_date_4 || '';
            document.getElementById('advance_month_4').value = record.advance_month_4 || '';
            document.getElementById('advance_purpose_4').value = record.advance_purpose_4 || '';
            
            document.getElementById('remark').value = record.remark || '';
            
            const modal = new bootstrap.Modal(document.getElementById('crudModal'));
            modal.show();
        }
    })
    .catch(error => {
        console.error('Error loading record:', error);
        showErrorMessage('Failed to load record');
    });
}

function saveRecord() {
    const formData = new FormData(document.getElementById('crudForm'));
    const data = Object.fromEntries(formData.entries());
    
    const url = currentEditId ? `/api/employee-ledger/${currentEditId}` : '/api/employee-ledger';
    const method = currentEditId ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('crudModal')).hide();
            loadLedgerData(currentPage);
            showSuccessMessage(data.message);
        } else {
            showErrorMessage(data.message || 'Failed to save record');
        }
    })
    .catch(error => {
        console.error('Error saving record:', error);
        showErrorMessage('Failed to save record');
    });
}

function viewRecord(id) {
    fetch(`/api/employee-ledger/${id}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const record = data.data;
            document.getElementById('viewModalBody').innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Employee Code</label>
                        <p class="fw-bold">${record.EmpCode || '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Employee Name</label>
                        <p class="fw-bold">${record.employee_name || '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Balance</label>
                        <p class="fw-bold text-info">₹${formatAmount(record.balance)}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Dr (YTD)</label>
                        <p class="fw-bold text-danger">₹${formatAmount(record.dr)}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Cr (YTD)</label>
                        <p class="fw-bold text-success">₹${formatAmount(record.cr)}</p>
                    </div>
                </div>
                
                <hr>
                <h6 class="text-primary mb-3">Fixed Imprest Details</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Imprest 1 Amount</label>
                        <p class="fw-bold">₹${formatAmount(record.fixed_imprest_amt_1)}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Imprest 1 Date</label>
                        <p class="fw-bold">${formatDate(record.fixed_imprest_date_1)}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Imprest 2 Amount</label>
                        <p class="fw-bold">₹${formatAmount(record.fixed_imprest_amt_2)}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Imprest 2 Date</label>
                        <p class="fw-bold">${formatDate(record.fixed_imprest_date_2)}</p>
                    </div>
                </div>
                
                <hr>
                <h6 class="text-success mb-3">Advance Details</h6>
                <div class="row">
                    ${record.advance_amt_1 ? `
                    <div class="col-md-12 mb-2">
                        <div class="border rounded p-2">
                            <strong>Advance 1:</strong> ₹${formatAmount(record.advance_amt_1)} 
                            ${record.advance_date_1 ? `| Date: ${formatDate(record.advance_date_1)}` : ''}
                            ${record.advance_month_1 ? `| Month: ${record.advance_month_1}` : ''}
                            ${record.advance_purpose_1 ? `<br><small class="text-muted">Purpose: ${record.advance_purpose_1}</small>` : ''}
                        </div>
                    </div>
                    ` : ''}
                    ${record.advance_amt_2 ? `
                    <div class="col-md-12 mb-2">
                        <div class="border rounded p-2">
                            <strong>Advance 2:</strong> ₹${formatAmount(record.advance_amt_2)} 
                            ${record.advance_date_2 ? `| Date: ${formatDate(record.advance_date_2)}` : ''}
                            ${record.advance_month_2 ? `| Month: ${record.advance_month_2}` : ''}
                            ${record.advance_purpose_2 ? `<br><small class="text-muted">Purpose: ${record.advance_purpose_2}</small>` : ''}
                        </div>
                    </div>
                    ` : ''}
                    ${record.advance_amt_3 ? `
                    <div class="col-md-12 mb-2">
                        <div class="border rounded p-2">
                            <strong>Advance 3:</strong> ₹${formatAmount(record.advance_amt_3)} 
                            ${record.advance_date_3 ? `| Date: ${formatDate(record.advance_date_3)}` : ''}
                            ${record.advance_month_3 ? `| Month: ${record.advance_month_3}` : ''}
                            ${record.advance_purpose_3 ? `<br><small class="text-muted">Purpose: ${record.advance_purpose_3}</small>` : ''}
                        </div>
                    </div>
                    ` : ''}
                    ${record.advance_amt_4 ? `
                    <div class="col-md-12 mb-2">
                        <div class="border rounded p-2">
                            <strong>Advance 4:</strong> ₹${formatAmount(record.advance_amt_4)} 
                            ${record.advance_date_4 ? `| Date: ${formatDate(record.advance_date_4)}` : ''}
                            ${record.advance_month_4 ? `| Month: ${record.advance_month_4}` : ''}
                            ${record.advance_purpose_4 ? `<br><small class="text-muted">Purpose: ${record.advance_purpose_4}</small>` : ''}
                        </div>
                    </div>
                    ` : ''}
                    ${!record.advance_amt_1 && !record.advance_amt_2 && !record.advance_amt_3 && !record.advance_amt_4 ? '<p class="text-muted">No advances recorded</p>' : ''}
                </div>
                
                ${record.remark ? `
                <hr>
                <div class="mb-3">
                    <label class="text-muted">Remark</label>
                    <p class="fw-bold">${record.remark}</p>
                </div>
                ` : ''}
                
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Created At</label>
                        <p class="fw-bold">${formatDateTime(record.created_at)}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Updated At</label>
                        <p class="fw-bold">${formatDateTime(record.updated_at)}</p>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
        }
    })
    .catch(error => {
        console.error('Error loading record:', error);
        showErrorMessage('Failed to load record');
    });
}

function deleteRecord(id) {
    if (confirm('Are you sure you want to delete this record?')) {
        fetch(`/api/employee-ledger/${id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                loadLedgerData(currentPage);
                showSuccessMessage('Record deleted successfully');
            } else {
                showErrorMessage(data.message || 'Failed to delete record');
            }
        })
        .catch(error => {
            console.error('Error deleting record:', error);
            showErrorMessage('Failed to delete record');
        });
    }
}

function updatePagination(data) {
    const pagination = document.getElementById('pagination');
    const totalPages = data.last_page || 1;
    const currentPage = data.current_page || 1;
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let paginationHTML = '';
    
    // Previous button
    if (currentPage > 1) {
        paginationHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadLedgerData(${currentPage - 1}); return false;">Previous</a>
            </li>
        `;
    }
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadLedgerData(${i}); return false;">${i}</a>
            </li>
        `;
    }
    
    // Next button
    if (currentPage < totalPages) {
        paginationHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadLedgerData(${currentPage + 1}); return false;">Next</a>
            </li>
        `;
    }
    
    pagination.innerHTML = paginationHTML;
}

function updateRecordsInfo(data) {
    const recordsInfo = document.getElementById('recordsInfo');
    const from = data.from || 0;
    const to = data.to || 0;
    const total = data.total || 0;
    
    recordsInfo.textContent = `Showing ${from} to ${to} of ${total} records`;
}

function refreshData() {
    loadLedgerData(currentPage);
}

function formatAmount(amount) {
    return new Intl.NumberFormat('en-IN').format(amount || 0);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-IN');
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('en-IN');
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function showSuccessMessage(message) {
    // You can implement a toast notification here
    alert(message);
}

function showErrorMessage(message) {
    // You can implement a toast notification here
    alert(message);
}
</script>
@endpush

