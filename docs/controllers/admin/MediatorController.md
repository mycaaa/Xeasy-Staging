# MediatorController Documentation

## Overview
The `MediatorController` serves as a data mediation layer for expense claim processing, providing advanced filtering, data aggregation, and status-based claim management. This controller acts as an intermediary between the frontend interface and complex database operations, offering sophisticated data punch functionality for expense claim analysis.

## Class Information
- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `App\Http\Controllers\Controller`
- **Dependencies**: 
  - `App\Models\ClaimType`
  - `Yajra\DataTables\Facades\DataTables`
  - `Carbon\Carbon`
- **Location**: `app/Http/Controllers/admin/MediatorController.php`

## Methods

### dataPunch(Request $request, $status)
Provides comprehensive data analysis and filtering interface for expense claims by status.

**Route**: `GET /admin/data-punch/{status}`

**Purpose**: Displays filtered expense claim data with advanced analytics and filtering options.

**Parameters**:
- `$status` (string): Claim status filter ('hold', 'draft', 'filled', 'uploaded', 'denied')

**Returns**: 
- View: `admin.data_punch`
- Data: Status counts, available dates, employee list

**Response Data Structure**:
```php
[
    'status' => 'hold',
    'holdCount' => 25,
    'draftCount' => 18,
    'filledCount' => 42,
    'uploadedCount' => 33,
    'deniedCount' => 7,
    'availableDates' => ['2024-01-15', '2024-01-14', ...],
    'employees' => [
        ['EmployeeID' => 1, 'EmpName' => 'John Doe', 'EmpCode' => 'EMP001'],
        // ... more employees
    ]
]
```

**Business Logic**:

#### Status-Based Filtering
```php
// Hold Status: Submitted claims with remarks
if ($status === 'hold') {
    $statusQuery->where('ClaimStatus', 'Submitted')->where('Rmk', 1);
}

// Draft Status: Draft claims not denied
elseif ($status === 'draft') {
    $statusQuery->where('ClaimStatus', 'Draft')->where('Filledokay', '!=', 2);
}

// Filled Status: Filled claims from current month
elseif ($status === 'filled') {
    $statusQuery->where('ClaimStatus', 'Filled')
               ->where('Filledokay', '!=', 2)
               ->where('BillDate', '>=', $monthStart);
}
```

#### Base Conditions
```php
$baseCondition = function ($query) use ($today, $companyId) {
    $query->where(function ($q) use ($today, $companyId) {
        $q->where('CrDate', '<=', $today)
          ->orWhere('ClaimId', '!=', 7)
          ->orWhereRaw("? = 3", [$companyId]);
    });
};
```

### dataPunchData(Request $request, $status)
Provides DataTables-compatible JSON data for expense claims with advanced filtering.

**Route**: `POST /admin/data-punch/{status}/data`

**Purpose**: Returns paginated, searchable, and sortable expense claim data for DataTables.

**Parameters**:
- `$status` (string): Claim status filter
- `date` (optional): Filter by specific creation date
- `emp` (optional): Filter by specific employee ID

**Request Example**:
```json
{
    "draw": 1,
    "start": 0,
    "length": 10,
    "search": {"value": ""},
    "date": "2024-01-15",
    "emp": "123"
}
```

**Response Example**:
```json
{
    "draw": 1,
    "recordsTotal": 150,
    "recordsFiltered": 25,
    "data": [
        {
            "DT_RowIndex": 1,
            "ExpId": "EXP001",
            "ClaimId": 1,
            "CrBy": 123,
            "BillDate": "15-01-2024",
            "CrDate": "15-01-2024",
            "DateEntryRemark": "Travel expense",
            "EmpCode": "EMP001",
            "employee": "EMP001 - John Doe",
            "action": "<button class='btn btn-primary btn-sm view-claim' data-claim-id='1'>Edit</button>"
        }
    ]
}
```

**Business Logic**:

#### Dynamic Query Building
```php
$query = DB::table($table . ' as ec')
    ->join('hrims.hrm_employee as emp', 'ec.CrBy', '=', 'emp.EmployeeID')
    ->select([
        'ec.ExpId', 'ec.ClaimId', 'ec.CrBy', 'ec.BillDate', 
        'ec.CrDate', 'ec.DateEntryRemark', 'emp.EmpCode',
        DB::raw("CONCAT(emp.EmpCode, ' - ', emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname) AS EmpName")
    ])
    ->where('ec.ClaimStatus', '!=', 'Deactivate')
    ->where('ec.AttachTo', 0)
    ->where($baseCondition);
```

#### DataTables Integration
```php
return DataTables::of($query)
    ->addIndexColumn()
    ->editColumn('CrDate', function ($row) {
        return Carbon::parse($row->CrDate)->format('d-m-Y');
    })
    ->editColumn('BillDate', function ($row) {
        return Carbon::parse($row->BillDate)->format('d-m-Y');
    })
    ->addColumn('employee', function ($row) {
        return $row->EmpName;
    })
    ->addColumn('action', function ($row) {
        $cgId = ClaimType::getCgIdByClaimId($row->ClaimId);
        return '<button type="button" class="btn btn-primary btn-sm view-claim"
                        data-claim-id="' . $row->ClaimId . '"
                        data-empid="' . $row->CrBy . '"
                        data-cgid="' . $cgId . '"
                        data-expid="' . $row->ExpId . '">
                    <i class="ri-edit-2-fill"></i>
                </button>';
    })
    ->filterColumn('employee', function ($query, $keyword) {
        $query->whereRaw("CONCAT(emp.EmpCode, ' - ', emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname) LIKE ?", ["%{$keyword}%"]);
    })
    ->rawColumns(['action'])
    ->make(true);
```

### getFilteredOptions(Request $request, $status)
Provides dynamic filter options based on current status and applied filters.

**Route**: `GET /admin/data-punch/{status}/filters`

**Purpose**: Returns available filter options (dates and employees) based on current selection.

**Parameters**:
- `$status` (string): Current status filter
- `date` (optional): Currently selected date filter
- `emp` (optional): Currently selected employee filter

**Response Example**:
```json
{
    "dates": [
        {"value": "2024-01-15", "text": "15-01-2024"},
        {"value": "2024-01-14", "text": "14-01-2024"}
    ],
    "employees": [
        {"value": 123, "text": "EMP001 - John Doe"},
        {"value": 124, "text": "EMP002 - Jane Smith"}
    ]
}
```

**Business Logic**:

#### Cascading Filters
The method implements cascading filters where selecting one filter updates available options for other filters:

```php
// Apply existing filters to get relevant options
if ($request->has('date') && $request->date != '' && $request->date != '1010') {
    $statusQuery->whereDate('CrDate', $request->date);
}

if ($request->has('emp') && $request->emp != '' && $request->emp != '1010') {
    $statusQuery->where('CrBy', $request->emp);
}

// Get available dates based on current filters
$availableDates = (clone $statusQuery)
    ->select('CrDate')
    ->groupBy('CrDate')
    ->orderByDesc('CrDate')
    ->pluck('CrDate')
    ->map(function ($date) {
        return ['value' => $date, 'text' => Carbon::parse($date)->format('d-m-Y')];
    });
```

## Database Schema Integration

### Dynamic Table Selection
```php
$fYearId = session('year_id');
$table = "y{$fYearId}_expenseclaims";
```

### Multi-Database Queries
```php
// Main expense database
DB::table($table . ' as ec')

// HR database for employee information
->join('hrims.hrm_employee as emp', 'ec.CrBy', '=', 'emp.EmployeeID')
```

## Frontend Integration

### JavaScript Implementation
```javascript
class DataPunchManager {
    constructor() {
        this.dataTable = null;
        this.currentStatus = null;
        this.initializeComponents();
    }

    initializeComponents() {
        this.initializeDataTable();
        this.initializeFilters();
        this.initializeStatusTabs();
    }

    initializeDataTable() {
        this.dataTable = $('#data-punch-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: `/admin/data-punch/${this.getCurrentStatus()}/data`,
                type: 'POST',
                data: (d) => {
                    d.date = $('#date-filter').val();
                    d.emp = $('#employee-filter').val();
                    d._token = $('meta[name="csrf-token"]').attr('content');
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'ExpId', name: 'ec.ExpId' },
                { data: 'employee', name: 'employee' },
                { data: 'BillDate', name: 'ec.BillDate' },
                { data: 'CrDate', name: 'ec.CrDate' },
                { data: 'DateEntryRemark', name: 'ec.DateEntryRemark' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            order: [[4, 'desc']], // Order by CrDate descending
            pageLength: 25,
            responsive: true,
            language: {
                processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
            }
        });
    }

    initializeFilters() {
        // Date filter change
        $('#date-filter').on('change', () => {
            this.updateFilters();
            this.dataTable.ajax.reload();
        });

        // Employee filter change
        $('#employee-filter').on('change', () => {
            this.updateFilters();
            this.dataTable.ajax.reload();
        });

        // Reset filters
        $('#reset-filters').on('click', () => {
            this.resetFilters();
        });
    }

    initializeStatusTabs() {
        $('.status-tab').on('click', (e) => {
            e.preventDefault();
            const status = $(e.target).data('status');
            this.switchStatus(status);
        });
    }

    async updateFilters() {
        const currentDate = $('#date-filter').val();
        const currentEmp = $('#employee-filter').val();
        
        try {
            const response = await fetch(`/admin/data-punch/${this.getCurrentStatus()}/filters?date=${currentDate}&emp=${currentEmp}`);
            const data = await response.json();
            
            this.updateDateOptions(data.dates, currentDate);
            this.updateEmployeeOptions(data.employees, currentEmp);
            
        } catch (error) {
            console.error('Error updating filters:', error);
        }
    }

    updateDateOptions(dates, selectedDate) {
        const dateSelect = $('#date-filter');
        const currentValue = selectedDate || dateSelect.val();
        
        dateSelect.empty();
        dateSelect.append('<option value="1010">All Dates</option>');
        
        dates.forEach(date => {
            const option = $('<option></option>')
                .attr('value', date.value)
                .text(date.text);
            
            if (date.value === currentValue) {
                option.prop('selected', true);
            }
            
            dateSelect.append(option);
        });
    }

    updateEmployeeOptions(employees, selectedEmp) {
        const empSelect = $('#employee-filter');
        const currentValue = selectedEmp || empSelect.val();
        
        empSelect.empty();
        empSelect.append('<option value="1010">All Employees</option>');
        
        employees.forEach(emp => {
            const option = $('<option></option>')
                .attr('value', emp.value)
                .text(emp.text);
            
            if (emp.value == currentValue) {
                option.prop('selected', true);
            }
            
            empSelect.append(option);
        });
    }

    switchStatus(status) {
        this.currentStatus = status;
        
        // Update active tab
        $('.status-tab').removeClass('active');
        $(`.status-tab[data-status="${status}"]`).addClass('active');
        
        // Update counts display
        this.updateStatusCounts();
        
        // Reset filters and reload data
        this.resetFilters();
        
        // Update DataTable URL
        this.dataTable.ajax.url(`/admin/data-punch/${status}/data`);
        this.dataTable.ajax.reload();
    }

    resetFilters() {
        $('#date-filter').val('1010');
        $('#employee-filter').val('1010');
        this.updateFilters();
    }

    getCurrentStatus() {
        return this.currentStatus || $('.status-tab.active').data('status') || 'hold';
    }

    updateStatusCounts() {
        // Update count badges based on current data
        const counts = {
            hold: parseInt($('#hold-count').text()) || 0,
            draft: parseInt($('#draft-count').text()) || 0,
            filled: parseInt($('#filled-count').text()) || 0,
            uploaded: parseInt($('#uploaded-count').text()) || 0,
            denied: parseInt($('#denied-count').text()) || 0
        };
        
        Object.keys(counts).forEach(status => {
            $(`.status-tab[data-status="${status}"] .badge`).text(counts[status]);
        });
    }
}

// Claim viewing functionality
$(document).on('click', '.view-claim', function() {
    const claimId = $(this).data('claim-id');
    const empId = $(this).data('empid');
    const cgId = $(this).data('cgid');
    const expId = $(this).data('expid');
    
    // Open claim in modal or navigate to edit page
    window.open(`/admin/claims/edit?claim_id=${claimId}&emp_id=${empId}&cg_id=${cgId}&exp_id=${expId}`, '_blank');
});

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new DataPunchManager();
});
```

### Blade Template Example
```blade
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Data Punch - {{ ucfirst($status) }} Claims</h4>
                </div>
                <div class="card-body">
                    <!-- Status Tabs -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link status-tab {{ $status === 'hold' ? 'active' : '' }}" 
                               data-status="hold" href="#">
                                Hold Claims 
                                <span class="badge bg-warning ms-1" id="hold-count">{{ $holdCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link status-tab {{ $status === 'draft' ? 'active' : '' }}" 
                               data-status="draft" href="#">
                                Draft Claims 
                                <span class="badge bg-secondary ms-1" id="draft-count">{{ $draftCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link status-tab {{ $status === 'filled' ? 'active' : '' }}" 
                               data-status="filled" href="#">
                                Filled Claims 
                                <span class="badge bg-info ms-1" id="filled-count">{{ $filledCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link status-tab {{ $status === 'uploaded' ? 'active' : '' }}" 
                               data-status="uploaded" href="#">
                                Uploaded Claims 
                                <span class="badge bg-success ms-1" id="uploaded-count">{{ $uploadedCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link status-tab {{ $status === 'denied' ? 'active' : '' }}" 
                               data-status="denied" href="#">
                                Denied Claims 
                                <span class="badge bg-danger ms-1" id="denied-count">{{ $deniedCount }}</span>
                            </a>
                        </li>
                    </ul>

                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="date-filter" class="form-label">Filter by Date</label>
                            <select class="form-select" id="date-filter">
                                <option value="1010">All Dates</option>
                                @foreach($availableDates as $date)
                                    <option value="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="employee-filter" class="form-label">Filter by Employee</label>
                            <select class="form-select" id="employee-filter">
                                <option value="1010">All Employees</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->EmployeeID }}">{{ $employee->EmpCode }} - {{ $employee->EmpName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-secondary" id="reset-filters">
                                <i class="ri-refresh-line"></i> Reset Filters
                            </button>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="data-punch-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Expense ID</th>
                                    <th>Employee</th>
                                    <th>Bill Date</th>
                                    <th>Created Date</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
@endpush
```

## Performance Optimization

### Query Optimization
```php
// Use indexes for better performance
DB::statement('CREATE INDEX IF NOT EXISTS idx_expense_claims_status_date ON ' . $table . ' (ClaimStatus, CrDate)');
DB::statement('CREATE INDEX IF NOT EXISTS idx_expense_claims_employee ON ' . $table . ' (CrBy)');
DB::statement('CREATE INDEX IF NOT EXISTS idx_expense_claims_bill_date ON ' . $table . ' (BillDate)');
```

### Caching Strategy
```php
public function getStatusCounts($status)
{
    $cacheKey = "data_punch_counts_{$status}_" . session('year_id') . "_" . session('company_id');
    
    return Cache::remember($cacheKey, 300, function () use ($status) {
        // Calculate counts
        return $this->calculateStatusCounts($status);
    });
}
```

## Security Considerations

### Data Access Control
- Session-based company and year filtering
- Employee data access through HR database join
- Status-based access restrictions

### Input Validation
```php
protected function validateDataPunchRequest(Request $request)
{
    return $request->validate([
        'date' => 'nullable|date',
        'emp' => 'nullable|integer|exists:hrims.hrm_employee,EmployeeID',
        'status' => 'required|in:hold,draft,filled,uploaded,denied'
    ]);
}
```

## Business Rules

### Status Definitions
1. **Hold**: Submitted claims with remarks requiring attention
2. **Draft**: Claims in draft status not yet denied
3. **Filled**: Completed claims from current month
4. **Uploaded**: Submitted claims without remarks
5. **Denied**: Claims that have been rejected

### Filtering Logic
- Date filters apply to creation date (CrDate)
- Employee filters show only relevant employees for selected status
- Cascading filters update available options dynamically
- Base conditions ensure data security and relevance

This controller provides sophisticated data mediation capabilities for expense claim management, enabling advanced filtering, analysis, and processing workflows.