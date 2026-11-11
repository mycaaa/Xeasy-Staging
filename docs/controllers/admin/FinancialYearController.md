# 📅 Admin FinancialYearController Documentation

The Admin FinancialYearController manages financial year configurati
The `FinancialYearController` manages financial year configurations for the expense management system. This controller provides basic CRUD operations for financial year entities, allowing administrators to define and manage fiscal periods for expense tracking and reporting.

## Class Information
- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `App\Http\Controllers\Controller`
- **Model**: `App\Models\FinancialYear`
- **Location**: `app/Http/Controllers/admin/FinancialYearController.php`

## Methods

### index()
Displays a listing of all financial years.

**Route**: `GET /admin/financial-years`

**Purpose**: Retrieves and displays all financial year records for administrative management.

**Parameters**: None

**Returns**: 
- View: `admin.financial_years`
- Data: `$financialYears` - Collection of all FinancialYear records

**Response Example**:
```php
// View receives:
$financialYears = [
    [
        'id' => 1,
        'year_name' => '2024-2025',
        'start_date' => '2024-04-01',
        'end_date' => '2025-03-31',
        'status' => 'active',
        'created_at' => '2024-01-01 00:00:00'
    ],
    // ... more financial years
];
```

**Business Logic**:
- Fetches all financial year records without filtering
- Provides comprehensive view for administrators to manage fiscal periods
- Essential for expense claim categorization and reporting

**Security Considerations**:
- Requires admin authentication
- Should implement role-based access control
- Consider adding audit logging for financial year changes

### create()
Shows the form for creating a new financial year.

**Route**: `GET /admin/financial-years/create`

**Purpose**: Displays form interface for creating new financial year records.

**Implementation Status**: Method stub - requires implementation

**Recommended Implementation**:
```php
public function create()
{
    return view('admin.financial_years.create');
}
```

### store(Request $request)
Stores a newly created financial year in storage.

**Route**: `POST /admin/financial-years`

**Purpose**: Processes and saves new financial year data.

**Implementation Status**: Method stub - requires implementation

**Recommended Implementation**:
```php
public function store(Request $request)
{
    $request->validate([
        'year_name' => 'required|string|max:255|unique:financial_years',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'status' => 'required|in:active,inactive'
    ]);

    $financialYear = FinancialYear::create($request->all());
    
    return redirect()->route('admin.financial-years.index')
                   ->with('success', 'Financial year created successfully.');
}
```

### show(string $id)
Displays the specified financial year.

**Route**: `GET /admin/financial-years/{id}`

**Purpose**: Shows detailed view of a specific financial year.

**Implementation Status**: Method stub - requires implementation

### edit(string $id)
Shows the form for editing the specified financial year.

**Route**: `GET /admin/financial-years/{id}/edit`

**Purpose**: Displays edit form for existing financial year.

**Implementation Status**: Method stub - requires implementation

### update(Request $request, string $id)
Updates the specified financial year in storage.

**Route**: `PUT/PATCH /admin/financial-years/{id}`

**Purpose**: Processes updates to existing financial year records.

**Implementation Status**: Method stub - requires implementation

### destroy(string $id)
Removes the specified financial year from storage.

**Route**: `DELETE /admin/financial-years/{id}`

**Purpose**: Soft or hard deletes financial year records.

**Implementation Status**: Method stub - requires implementation

## Database Schema
Expected FinancialYear model structure:
```sql
CREATE TABLE financial_years (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    year_name VARCHAR(255) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

## Frontend Integration

### JavaScript Implementation
```javascript
class FinancialYearManager {
    constructor() {
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Form submission handling
        $('#financial-year-form').on('submit', this.handleFormSubmit.bind(this));
        
        // Status toggle
        $('.status-toggle').on('change', this.handleStatusToggle.bind(this));
        
        // Delete confirmation
        $('.delete-btn').on('click', this.handleDelete.bind(this));
    }

    handleFormSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        // Validate date range
        const startDate = new Date(formData.get('start_date'));
        const endDate = new Date(formData.get('end_date'));
        
        if (endDate <= startDate) {
            this.showError('End date must be after start date');
            return;
        }

        this.submitForm(formData);
    }

    async submitForm(formData) {
        try {
            const response = await fetch('/admin/financial-years', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();
            
            if (response.ok) {
                this.showSuccess('Financial year saved successfully');
                window.location.reload();
            } else {
                this.showError(result.message || 'Error saving financial year');
            }
        } catch (error) {
            this.showError('Network error occurred');
        }
    }

    async handleStatusToggle(e) {
        const yearId = e.target.dataset.yearId;
        const status = e.target.checked ? 'active' : 'inactive';
        
        try {
            const response = await fetch(`/admin/financial-years/${yearId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ status })
            });

            if (!response.ok) {
                e.target.checked = !e.target.checked; // Revert toggle
                this.showError('Failed to update status');
            }
        } catch (error) {
            e.target.checked = !e.target.checked; // Revert toggle
            this.showError('Network error occurred');
        }
    }

    handleDelete(e) {
        const yearId = e.target.dataset.yearId;
        const yearName = e.target.dataset.yearName;
        
        if (confirm(`Are you sure you want to delete financial year "${yearName}"?`)) {
            this.deleteFinancialYear(yearId);
        }
    }

    async deleteFinancialYear(yearId) {
        try {
            const response = await fetch(`/admin/financial-years/${yearId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (response.ok) {
                this.showSuccess('Financial year deleted successfully');
                document.querySelector(`[data-year-id="${yearId}"]`).closest('tr').remove();
            } else {
                this.showError('Failed to delete financial year');
            }
        } catch (error) {
            this.showError('Network error occurred');
        }
    }

    showSuccess(message) {
        // Implement success notification
        console.log('Success:', message);
    }

    showError(message) {
        // Implement error notification
        console.error('Error:', message);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new FinancialYearManager();
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Financial Years Management</h4>
                    <a href="{{ route('admin.financial-years.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Add New Financial Year
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Year Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financialYears as $year)
                                <tr>
                                    <td>{{ $year->year_name }}</td>
                                    <td>{{ $year->start_date->format('d-m-Y') }}</td>
                                    <td>{{ $year->end_date->format('d-m-Y') }}</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input status-toggle" 
                                                   type="checkbox" 
                                                   data-year-id="{{ $year->id }}"
                                                   {{ $year->status === 'active' ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.financial-years.edit', $year->id) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger delete-btn"
                                                data-year-id="{{ $year->id }}"
                                                data-year-name="{{ $year->year_name }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
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
@endsection
```

## Performance Optimization

### Caching Strategy
```php
// In FinancialYear model
public static function getActiveYear()
{
    return Cache::remember('active_financial_year', 3600, function () {
        return self::where('status', 'active')
                  ->where('start_date', '<=', now())
                  ->where('end_date', '>=', now())
                  ->first();
    });
}
```

### Database Indexing
```sql
-- Recommended indexes
CREATE INDEX idx_financial_years_status ON financial_years(status);
CREATE INDEX idx_financial_years_dates ON financial_years(start_date, end_date);
CREATE INDEX idx_financial_years_active_period ON financial_years(status, start_date, end_date);
```

## Error Handling

### Validation Rules
```php
protected function getValidationRules($id = null)
{
    return [
        'year_name' => 'required|string|max:255|unique:financial_years,year_name,' . $id,
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'status' => 'required|in:active,inactive'
    ];
}

protected function getValidationMessages()
{
    return [
        'year_name.required' => 'Financial year name is required',
        'year_name.unique' => 'This financial year already exists',
        'end_date.after' => 'End date must be after start date',
        'status.in' => 'Status must be either active or inactive'
    ];
}
```

## Business Rules

### Financial Year Management
1. **Unique Periods**: No overlapping financial years allowed
2. **Active Year**: Only one financial year can be active at a time
3. **Date Validation**: End date must be after start date
4. **Expense Dependencies**: Cannot delete financial year with associated expenses
5. **Status Management**: Changing status affects expense claim processing

### Integration Points
- **Expense Claims**: All claims are categorized by financial year
- **Reporting**: Financial reports are generated per financial year
- **Budget Management**: Budgets are allocated per financial year
- **Audit Trail**: All financial transactions are tracked by year

## API Endpoints

### RESTful Routes
```php
Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'role:admin']], function () {
    Route::resource('financial-years', FinancialYearController::class);
    Route::patch('financial-years/{id}/status', [FinancialYearController::class, 'updateStatus']);
});
```

### API Response Format
```json
{
    "success": true,
    "message": "Financial year retrieved successfully",
    "data": {
        "id": 1,
        "year_name": "2024-2025",
        "start_date": "2024-04-01",
        "end_date": "2025-03-31",
        "status": "active",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

## Testing Considerations

### Unit Tests
```php
public function test_can_create_financial_year()
{
    $data = [
        'year_name' => '2025-2026',
        'start_date' => '2025-04-01',
        'end_date' => '2026-03-31',
        'status' => 'active'
    ];

    $response = $this->post('/admin/financial-years', $data);
    
    $response->assertRedirect();
    $this->assertDatabaseHas('financial_years', $data);
}
```

### Integration Tests
- Test financial year creation with expense claims
- Verify status changes affect system behavior
- Test date overlap validation
- Verify cascade operations on deletion

## Security Considerations

### Access Control
- Implement role-based permissions
- Audit all financial year changes
- Validate date ranges to prevent manipulation
- Secure deletion with dependency checks

### Data Integrity
- Prevent overlapping financial years
- Maintain referential integrity with expense claims
- Implement soft deletes for audit trail
- Validate business rules at database level

This controller serves as the foundation for financial year management in the expense system, requiring full implementation of CRUD operations and proper integration with the expense claim workflow.