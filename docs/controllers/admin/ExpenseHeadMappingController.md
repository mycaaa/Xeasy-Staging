# ExpenseHeadMappingController Documentation

## Overview
The `ExpenseHeadMappingController` manages the mapping relationships between activity types and expense head categories. This controller provides a matrix-based interface for configuring which expense heads are applicable to specific activities, enabling flexible expense categorization and validation rules.

## Class Information
- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `App\Http\Controllers\Controller`
- **Models**: 
  - `App\Models\ActivityName`
  - `App\Models\ExpenseHeadCategory`
  - `App\Models\ExpenseHeadMapping`
- **Dependencies**: `Spatie\Permission\Models\Permission`, `Spatie\Permission\Models\Role`
- **Location**: `app/Http/Controllers/admin/ExpenseHeadMappingController.php`

## Methods

### index()
Displays the expense head mapping matrix interface.

**Route**: `GET /admin/expense-head-mapping`

**Purpose**: Provides a comprehensive matrix view for managing activity-expense head relationships.

**Parameters**: None

**Returns**: 
- View: `admin.expense_head_mapping`
- Data: Expense heads, activities, existing mappings

**Response Data Structure**:
```php
[
    'expenseHeads' => [
        ['id' => 1, 'expense_head_name' => 'Travel', 'status' => 1],
        ['id' => 2, 'expense_head_name' => 'Accommodation', 'status' => 1],
        // ... more expense heads
    ],
    'activities' => [
        [
            'activity_id' => 1,
            'category_name' => 'Business Travel',
            'activity_name' => 'Local Travel'
        ],
        // ... more activities
    ],
    'mappings' => [
        '1-1' => true,  // activity_id-expense_head_id => checked
        '1-2' => false,
        // ... more mappings
    ]
]
```

**Business Logic**:

#### Expense Head Retrieval
```php
$expenseHeads = ExpenseHeadCategory::where('status', 1)->get();
```

#### Activity Data with Joins
```php
$activities = \DB::connection(session('expense_connection'))
    ->table('adv_activity_names as an')
    ->leftJoin('adv_activity_categories as ac', 'ac.id', '=', 'an.category_id')
    ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'an.activity_name')
    ->select(
        'an.id as activity_id',
        'ac.category_name',
        'ct.ClaimName as activity_name'
    )
    ->where('an.status', 1)
    ->get();
```

#### Mapping Matrix Generation
```php
$mappings = ExpenseHeadMapping::pluck('checked', \DB::raw("CONCAT(activity_id,'-',expense_head_id)"))->toArray();
```

### toggle(Request $request)
Toggles the mapping status between an activity and expense head.

**Route**: `POST /admin/expense-head-mapping/toggle`

**Purpose**: Creates or updates mapping relationships with permission management.

**Parameters**:
- `activity_id` (integer, required): ID of the activity
- `expense_head_id` (integer, required): ID of the expense head
- `checked` (boolean, required): Mapping status

**Request Example**:
```json
{
    "activity_id": 1,
    "expense_head_id": 2,
    "checked": true
}
```

**Response Example**:
```json
{
    "success": true,
    "message": "Mapping updated successfully.",
    "data": {
        "id": 15,
        "activity_id": 1,
        "expense_head_id": 2,
        "checked": true,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Business Logic**:

#### Validation Rules
```php
$request->validate([
    'activity_id' => 'required|integer|exists:adv_activity_names,id',
    'expense_head_id' => 'required|integer|exists:adv_expense_head_category,id',
    'checked' => 'required|boolean',
]);
```

#### Mapping Update/Create Logic
```php
$mapping = ExpenseHeadMapping::where([
    'activity_id' => $activityId,
    'expense_head_id' => $expenseHeadId,
])->first();

if ($mapping) {
    // Update existing mapping
    $mapping->update([
        'checked' => $checked,
        'updated_at' => now(),
    ]);
} else {
    // Create new mapping
    $mapping = ExpenseHeadMapping::create([
        'activity_id' => $activityId,
        'expense_head_id' => $expenseHeadId,
        'checked' => $checked,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Assign permissions
    $managerRole = Role::where('name', 'Super Admin')->first();
    if ($managerRole) {
        $managerRole->givePermissionTo("view expenseheadmapping {$mapping->id}");
    }
}
```

### getActivities()
Retrieves all active activities with their claim type information.

**Route**: `GET /admin/expense-head-mapping/activities`

**Purpose**: Provides API endpoint for dynamic activity loading.

**Parameters**: None

**Response Example**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Local Travel"
        },
        {
            "id": 2,
            "name": "International Travel"
        }
    ],
    "message": "Activities retrieved successfully."
}
```

**Business Logic**:
```php
$activities = ActivityName::with('claimType')
    ->get(['id', 'activity_name'])
    ->map(function ($activity) {
        return [
            'id' => $activity->id,
            'name' => $activity->claim_name,
        ];
    });
```

### getExpenseHeads()
Retrieves all expense head categories.

**Route**: `GET /admin/expense-head-mapping/expense-heads`

**Purpose**: Provides API endpoint for dynamic expense head loading.

**Parameters**: None

**Response Example**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "expense_head_name": "Travel Expenses"
        },
        {
            "id": 2,
            "expense_head_name": "Accommodation"
        }
    ],
    "message": "Expense heads retrieved successfully."
}
```

## Database Schema

### ExpenseHeadMapping Model
```sql
CREATE TABLE expense_head_mappings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    activity_id INT NOT NULL,
    expense_head_id INT NOT NULL,
    checked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_mapping (activity_id, expense_head_id),
    FOREIGN KEY (activity_id) REFERENCES adv_activity_names(id),
    FOREIGN KEY (expense_head_id) REFERENCES adv_expense_head_category(id)
);
```

## Frontend Integration

### JavaScript Implementation
```javascript
class ExpenseHeadMappingManager {
    constructor() {
        this.initializeEventListeners();
        this.loadMappingMatrix();
    }

    initializeEventListeners() {
        // Matrix checkbox changes
        $(document).on('change', '.mapping-checkbox', this.handleMappingToggle.bind(this));
        
        // Bulk operations
        $('#select-all-activities').on('change', this.handleSelectAllActivities.bind(this));
        $('#select-all-expense-heads').on('change', this.handleSelectAllExpenseHeads.bind(this));
        
        // Filter functionality
        $('#activity-filter').on('input', this.filterActivities.bind(this));
        $('#expense-head-filter').on('input', this.filterExpenseHeads.bind(this));
        
        // Save all changes
        $('#save-mappings').on('click', this.saveBulkMappings.bind(this));
    }

    async handleMappingToggle(e) {
        const checkbox = e.target;
        const activityId = checkbox.dataset.activityId;
        const expenseHeadId = checkbox.dataset.expenseHeadId;
        const checked = checkbox.checked;
        
        // Show loading state
        this.setCheckboxLoading(checkbox, true);
        
        try {
            const response = await fetch('/admin/expense-head-mapping/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    activity_id: parseInt(activityId),
                    expense_head_id: parseInt(expenseHeadId),
                    checked: checked
                })
            });

            const result = await response.json();
            
            if (response.ok) {
                this.showSuccess('Mapping updated successfully');
                this.updateMappingStatus(activityId, expenseHeadId, checked);
            } else {
                // Revert checkbox state
                checkbox.checked = !checked;
                this.showError(result.message || 'Failed to update mapping');
            }
        } catch (error) {
            // Revert checkbox state
            checkbox.checked = !checked;
            this.showError('Network error occurred');
        } finally {
            this.setCheckboxLoading(checkbox, false);
        }
    }

    handleSelectAllActivities(e) {
        const checked = e.target.checked;
        const activityRow = $(e.target).closest('tr');
        const checkboxes = activityRow.find('.mapping-checkbox');
        
        checkboxes.each((index, checkbox) => {
            if (checkbox.checked !== checked) {
                $(checkbox).trigger('change');
            }
        });
    }

    handleSelectAllExpenseHeads(e) {
        const checked = e.target.checked;
        const expenseHeadId = e.target.dataset.expenseHeadId;
        const checkboxes = $(`.mapping-checkbox[data-expense-head-id="${expenseHeadId}"]`);
        
        checkboxes.each((index, checkbox) => {
            if (checkbox.checked !== checked) {
                $(checkbox).trigger('change');
            }
        });
    }

    filterActivities() {
        const filterText = $('#activity-filter').val().toLowerCase();
        
        $('.activity-row').each((index, row) => {
            const activityName = $(row).find('.activity-name').text().toLowerCase();
            const categoryName = $(row).find('.category-name').text().toLowerCase();
            
            if (activityName.includes(filterText) || categoryName.includes(filterText)) {
                $(row).show();
            } else {
                $(row).hide();
            }
        });
    }

    filterExpenseHeads() {
        const filterText = $('#expense-head-filter').val().toLowerCase();
        
        $('.expense-head-column').each((index, column) => {
            const expenseHeadName = $(column).find('.expense-head-name').text().toLowerCase();
            
            if (expenseHeadName.includes(filterText)) {
                $(column).show();
            } else {
                $(column).hide();
            }
        });
    }

    async loadMappingMatrix() {
        try {
            this.showLoading(true);
            
            const [activitiesResponse, expenseHeadsResponse] = await Promise.all([
                fetch('/admin/expense-head-mapping/activities'),
                fetch('/admin/expense-head-mapping/expense-heads')
            ]);
            
            const activities = await activitiesResponse.json();
            const expenseHeads = await expenseHeadsResponse.json();
            
            if (activities.success && expenseHeads.success) {
                this.renderMappingMatrix(activities.data, expenseHeads.data);
            } else {
                this.showError('Failed to load mapping data');
            }
        } catch (error) {
            this.showError('Error loading mapping matrix');
        } finally {
            this.showLoading(false);
        }
    }

    renderMappingMatrix(activities, expenseHeads) {
        const matrixContainer = $('#mapping-matrix');
        
        // Build matrix HTML
        let matrixHtml = `
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th rowspan="2">Activity</th>
                        <th rowspan="2">Category</th>
                        <th colspan="${expenseHeads.length}" class="text-center">Expense Heads</th>
                    </tr>
                    <tr>
        `;
        
        // Expense head headers
        expenseHeads.forEach(head => {
            matrixHtml += `
                <th class="expense-head-column text-center" style="min-width: 120px;">
                    <div class="expense-head-name">${head.expense_head_name}</div>
                    <div class="mt-1">
                        <input type="checkbox" class="form-check-input" 
                               id="select-all-${head.id}"
                               data-expense-head-id="${head.id}">
                        <label for="select-all-${head.id}" class="form-check-label small">All</label>
                    </div>
                </th>
            `;
        });
        
        matrixHtml += '</tr></thead><tbody>';
        
        // Activity rows
        activities.forEach(activity => {
            matrixHtml += `
                <tr class="activity-row">
                    <td class="activity-name">${activity.name}</td>
                    <td class="category-name">${activity.category_name || 'N/A'}</td>
            `;
            
            // Mapping checkboxes
            expenseHeads.forEach(head => {
                const mappingKey = `${activity.id}-${head.id}`;
                const isChecked = this.getMappingStatus(mappingKey);
                
                matrixHtml += `
                    <td class="text-center">
                        <input type="checkbox" 
                               class="form-check-input mapping-checkbox"
                               data-activity-id="${activity.id}"
                               data-expense-head-id="${head.id}"
                               ${isChecked ? 'checked' : ''}>
                    </td>
                `;
            });
            
            matrixHtml += '</tr>';
        });
        
        matrixHtml += '</tbody></table>';
        
        matrixContainer.html(matrixHtml);
    }

    getMappingStatus(mappingKey) {
        // Get from initial page data or cache
        return window.initialMappings && window.initialMappings[mappingKey] || false;
    }

    updateMappingStatus(activityId, expenseHeadId, checked) {
        const mappingKey = `${activityId}-${expenseHeadId}`;
        if (!window.initialMappings) {
            window.initialMappings = {};
        }
        window.initialMappings[mappingKey] = checked;
    }

    setCheckboxLoading(checkbox, loading) {
        if (loading) {
            $(checkbox).prop('disabled', true);
            $(checkbox).closest('td').addClass('loading');
        } else {
            $(checkbox).prop('disabled', false);
            $(checkbox).closest('td').removeClass('loading');
        }
    }

    showLoading(show) {
        if (show) {
            $('#loading-overlay').show();
        } else {
            $('#loading-overlay').hide();
        }
    }

    showSuccess(message) {
        // Implement success notification
        this.showNotification(message, 'success');
    }

    showError(message) {
        // Implement error notification
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        // Implement notification system
        console.log(`${type.toUpperCase()}: ${message}`);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new ExpenseHeadMappingManager();
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
                    <h4 class="card-title">Expense Head Mapping</h4>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success" id="save-mappings">
                            <i class="ri-save-line"></i> Save All Changes
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="activity-filter" class="form-label">Filter Activities</label>
                            <input type="text" class="form-control" id="activity-filter" 
                                   placeholder="Search activities...">
                        </div>
                        <div class="col-md-6">
                            <label for="expense-head-filter" class="form-label">Filter Expense Heads</label>
                            <input type="text" class="form-control" id="expense-head-filter" 
                                   placeholder="Search expense heads...">
                        </div>
                    </div>

                    <!-- Mapping Matrix -->
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-bordered table-hover table-sm">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th rowspan="2" style="vertical-align: middle;">Activity</th>
                                    <th rowspan="2" style="vertical-align: middle;">Category</th>
                                    <th colspan="{{ count($expenseHeads) }}" class="text-center">Expense Heads</th>
                                </tr>
                                <tr>
                                    @foreach($expenseHeads as $head)
                                    <th class="text-center" style="min-width: 120px;">
                                        <div class="mb-1">{{ $head->expense_head_name }}</div>
                                        <div>
                                            <input type="checkbox" class="form-check-input" 
                                                   id="select-all-{{ $head->id }}"
                                                   data-expense-head-id="{{ $head->id }}">
                                            <label for="select-all-{{ $head->id }}" class="form-check-label small">All</label>
                                        </div>
                                    </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activities as $activity)
                                <tr class="activity-row">
                                    <td>
                                        <strong>{{ $activity->activity_name }}</strong>
                                        <div class="text-end">
                                            <input type="checkbox" class="form-check-input" 
                                                   id="select-all-activity-{{ $activity->activity_id }}">
                                            <label for="select-all-activity-{{ $activity->activity_id }}" 
                                                   class="form-check-label small">All</label>
                                        </div>
                                    </td>
                                    <td class="text-muted">{{ $activity->category_name ?? 'N/A' }}</td>
                                    @foreach($expenseHeads as $head)
                                    <td class="text-center">
                                        @php
                                            $mappingKey = $activity->activity_id . '-' . $head->id;
                                            $isChecked = isset($mappings[$mappingKey]) && $mappings[$mappingKey];
                                        @endphp
                                        <input type="checkbox" 
                                               class="form-check-input mapping-checkbox"
                                               data-activity-id="{{ $activity->activity_id }}"
                                               data-expense-head-id="{{ $head->id }}"
                                               {{ $isChecked ? 'checked' : '' }}>
                                    </td>
                                    @endforeach
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

<!-- Loading Overlay -->
<div id="loading-overlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" 
     style="background: rgba(0,0,0,0.5); z-index: 9999;">
    <div class="d-flex justify-content-center align-items-center h-100">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Pass initial mappings to JavaScript
window.initialMappings = @json($mappings);
</script>
@endpush

@push('styles')
<style>
.loading {
    position: relative;
    opacity: 0.6;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 16px;
    height: 16px;
    margin: -8px 0 0 -8px;
    border: 2px solid #007bff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.table th {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
}
</style>
@endpush
```

## Performance Optimization

### Database Indexing
```sql
-- Optimize mapping queries
CREATE INDEX idx_expense_head_mappings_activity ON expense_head_mappings(activity_id);
CREATE INDEX idx_expense_head_mappings_expense_head ON expense_head_mappings(expense_head_id);
CREATE INDEX idx_expense_head_mappings_checked ON expense_head_mappings(checked);

-- Optimize activity queries
CREATE INDEX idx_activity_names_status ON adv_activity_names(status);
CREATE INDEX idx_expense_head_category_status ON adv_expense_head_category(status);
```

### Caching Strategy
```php
public function getCachedMappings()
{
    return Cache::remember('expense_head_mappings', 3600, function () {
        return ExpenseHeadMapping::pluck('checked', DB::raw("CONCAT(activity_id,'-',expense_head_id)"))->toArray();
    });
}
```

## Security Considerations

### Permission Management
- Automatic permission creation for new mappings
- Role-based access control integration
- Audit trail for mapping changes

### Input Validation
```php
protected function validateMappingRequest(Request $request)
{
    return $request->validate([
        'activity_id' => 'required|integer|exists:adv_activity_names,id',
        'expense_head_id' => 'required|integer|exists:adv_expense_head_category,id',
        'checked' => 'required|boolean'
    ], [
        'activity_id.exists' => 'Selected activity does not exist',
        'expense_head_id.exists' => 'Selected expense head does not exist'
    ]);
}
```

## Business Rules

### Mapping Logic
1. **Unique Constraints**: One mapping per activity-expense head pair
2. **Status Dependencies**: Only active activities and expense heads are mappable
3. **Permission Integration**: Mappings create corresponding permissions
4. **Cascade Updates**: Changes affect expense claim validation rules

### Integration Points
- **Expense Claims**: Mappings determine valid expense heads for activities
- **Validation Rules**: Form validation uses mapping data
- **Reporting**: Analytics consider mapping relationships
- **Audit Trail**: All mapping changes are logged

This controller provides essential configuration management for expense categorization, enabling flexible and maintainable expense head assignment rules.