# 🏷️ Admin ActivityNameController Documentation

The Admin ActivityNameController manages activity names within the Xeasy Laravel expense management system, providing comprehensive CRUD operations for activity name management with department, vertical, and budget allocation features.

## 📋 Overview

**Location**: `app/Http/Controllers/admin/ActivityNameController.php`

**Purpose**: Provides advanced management functionality for activity names, including category association, department mapping, vertical assignment, budget limits, and time-based validity periods.

**Model**: `App\Models\ActivityName`
**Database**: `expense` connection
**Table**: `adv_activity_names`

**Middleware**: Inherits from base Controller (typically includes auth middleware)

**Key Features**:
- Full CRUD operations for activity names
- Category and claim type associations
- Department and vertical mapping
- Budget limit and amount management
- Time-based validity periods (month/year ranges)
- Status-based filtering and management
- Automatic permission generation
- Helper endpoints for dropdown data

## 📊 Public Methods

### 1. Activity Name Index View

#### `index(): View`

**Route**: `GET /admin/activity-names`
**Permission**: Admin access required

**Purpose**: Displays the activity name management interface with status-based filtering and relationship data.

**Query Parameters**:
- `status` (optional): Filter activity names by status
  - `active` (default): Shows only active activity names
  - `inactive`: Shows only inactive activity names  
  - `all`: Shows all activity names regardless of status

**Response**: Returns Blade view with activity names data including relationships

**View Data**:
```php
[
    'activityNames' => Collection, // Activity names with category and claimType relationships
    'status' => string            // Current filter status
]
```

**Eager Loading**:
```php
ActivityName::with(['category', 'claimType'])
```

**Business Logic**:
1. **Status Parameter**: Reads status from query parameter, defaults to 'active'
2. **Relationship Loading**: Eager loads category and claim type relationships
3. **Query Building**: Applies appropriate status filter to query
4. **Data Retrieval**: Fetches filtered activity names with relationships
5. **View Rendering**: Returns admin.activity_name view with data

---

### 2. Activity Name List API

#### `activityNameList(): JsonResponse`

**Route**: `GET /api/admin/activity-names`
**Permission**: Admin access required

**Purpose**: Returns all activity names as JSON for API consumption.

**Parameters**: None

**Response Structure**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "category_id": 2,
            "activity_name": 15,
            "description": "Business travel activities",
            "dept_id": "1,3,5",
            "vertical": "2,4",
            "from_month": "2025-01-01",
            "to_month": "2025-12-31",
            "from_year": 2025,
            "to_year": 2025,
            "approved_limit": 50000.00,
            "approved_amount": 45000,
            "status": true,
            "created_at": "2025-01-03T10:00:00.000000Z",
            "updated_at": "2025-01-03T10:00:00.000000Z"
        }
    ],
    "message": "Activity names fetched successfully."
}
```

---

### 3. Create Activity Name

#### `store(Request $request): JsonResponse`

**Route**: `POST /admin/activity-names`
**Permission**: Admin access required

**Purpose**: Creates a new activity name with comprehensive validation and automatic permission generation.

**Request Validation**:
```php
[
    'category_id' => 'nullable|integer|exists:adv_activity_categories,id',
    'activity_name' => 'required|integer|exists:claimtype,ClaimId,cgId,7',
    'dept_id' => 'nullable|array',
    'dept_id.*' => 'integer|exists:core_departments,id',
    'vertical' => 'nullable|array',
    'vertical.*' => 'string|max:255',
    'description' => 'nullable|string',
    'from_month' => 'nullable|date',
    'to_month' => 'nullable|date|after_or_equal:from_month',
    'from_year' => 'nullable|integer|digits:4',
    'to_year' => 'nullable|integer|digits:4|gte:from_year',
    'approved_limit' => 'required|numeric|min:0',
    'approved_amount' => 'required|integer|min:0',
    'status' => 'boolean'
]
```

**Request Body Example**:
```json
{
    "category_id": 2,
    "activity_name": 15,
    "description": "Business travel and accommodation",
    "dept_id": [1, 3, 5],
    "vertical": ["2", "4"],
    "from_month": "2025-01-01",
    "to_month": "2025-12-31",
    "from_year": 2025,
    "to_year": 2025,
    "approved_limit": 50000.00,
    "approved_amount": 45000,
    "status": true
}
```

**Response Structure**:
```json
{
    "success": true,
    "data": {
        "id": 10,
        "category_id": 2,
        "activity_name": 15,
        "description": "Business travel and accommodation",
        "dept_id": "1,3,5",
        "vertical": "2,4",
        "from_month": "2025-01-01",
        "to_month": "2025-12-31",
        "from_year": 2025,
        "to_year": 2025,
        "approved_limit": 50000.00,
        "approved_amount": 45000,
        "status": true,
        "created_at": "2025-01-03T10:00:00.000000Z",
        "updated_at": "2025-01-03T10:00:00.000000Z"
    },
    "message": "Activity name created successfully."
}
```

**Business Logic**:
1. **Input Validation**: Comprehensive validation including foreign key constraints
2. **Array Processing**: Converts department and vertical arrays to comma-separated strings
3. **Data Sanitization**: Filters and validates array inputs
4. **Record Creation**: Creates new ActivityName record
5. **Permission Generation**: Automatically creates three permissions per activity name
6. **Role Assignment**: Assigns view permission to Super Admin role
7. **Response**: Returns created activity name with success message

**Array Processing Logic**:
```php
$deptIds = $request->dept_id
    ? array_map('intval', array_filter($request->dept_id, 'is_numeric'))
    : null;
    
$verticals = $request->vertical
    ? array_map('trim', array_filter($request->vertical))
    : null;
```

---

### 4. Edit Activity Name

#### `edit(string $id): JsonResponse`

**Route**: `GET /admin/activity-names/{id}/edit`
**Permission**: Admin access required

**Purpose**: Retrieves a specific activity name for editing.

**Parameters**:
- `id` (required): Activity name ID

**Response Structure**:
```json
{
    "success": true,
    "data": {
        "id": 1,
        "category_id": 2,
        "activity_name": 15,
        "description": "Business travel activities",
        "dept_id": "1,3,5",
        "vertical": "2,4",
        "from_month": "2025-01-01",
        "to_month": "2025-12-31",
        "from_year": 2025,
        "to_year": 2025,
        "approved_limit": 50000.00,
        "approved_amount": 45000,
        "status": true,
        "created_at": "2025-01-03T10:00:00.000000Z",
        "updated_at": "2025-01-03T10:00:00.000000Z"
    },
    "message": "Activity name retrieved successfully."
}
```

---

### 5. Update Activity Name

#### `update(Request $request, string $id): JsonResponse`

**Route**: `PUT /admin/activity-names/{id}`
**Permission**: Admin access required

**Purpose**: Updates an existing activity name with the same comprehensive validation as creation.

**Parameters**:
- `id` (required): Activity name ID

**Request Validation**: Same as `store()` method

**Request Body Example**:
```json
{
    "category_id": 3,
    "activity_name": 20,
    "description": "Updated business travel description",
    "dept_id": [1, 2, 4],
    "vertical": ["1", "3", "5"],
    "from_month": "2025-02-01",
    "to_month": "2025-11-30",
    "from_year": 2025,
    "to_year": 2025,
    "approved_limit": 60000.00,
    "approved_amount": 55000,
    "status": true
}
```

**Business Logic**: Same array processing and validation as creation, but updates existing record.

---

### 6. Delete Activity Name

#### `destroy(string $id): JsonResponse`

**Route**: `DELETE /admin/activity-names/{id}`
**Permission**: Admin access required

**Purpose**: Deletes an activity name record.

**Parameters**:
- `id` (required): Activity name ID

**Response Structure**:
```json
{
    "success": true,
    "data": null,
    "message": "Activity name deleted successfully."
}
```

---

### 7. Helper Endpoints

#### Get Categories

##### `getCategories(): JsonResponse`

**Route**: `GET /admin/activity-names/categories`
**Purpose**: Returns all activity categories for dropdown population.

**Response Structure**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "category_name": "Travel",
            "mapped_activity": "Y",
            "description": "Travel related activities",
            "status": true
        }
    ],
    "message": "Activity categories retrieved successfully."
}
```

#### Get Departments

##### `getDepartments(): JsonResponse`

**Route**: `GET /admin/activity-names/departments`
**Purpose**: Returns active departments for selection.

**Response Structure**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "department_name": "Human Resources",
            "is_active": 1
        }
    ],
    "message": "Departments retrieved successfully."
}
```

#### Get Claim Types

##### `getClaimTypes(): JsonResponse`

**Route**: `GET /admin/activity-names/claim-types`
**Purpose**: Returns claim types with cgId = 7 for activity name selection.

**Query Logic**:
```php
ClaimType::where('cgId', 7)->get(['ClaimId', 'ClaimName'])
```

**Response Structure**:
```json
{
    "success": true,
    "data": [
        {
            "ClaimId": 15,
            "ClaimName": "Business Travel"
        }
    ],
    "message": "Claim types retrieved successfully."
}
```

#### Get Verticals

##### `getVerticals(): JsonResponse`

**Route**: `GET /admin/activity-names/verticals`
**Purpose**: Returns active verticals for selection.

**Response Structure**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "vertical_name": "Technology",
            "is_active": 1
        }
    ],
    "message": "Verticals retrieved successfully."
}
```

---

## 🔧 Implementation Details

### Model Configuration

#### Database Connection
```php

protected $table = 'adv_activity_names';
```

#### Fillable Attributes
```php
protected $fillable = [
    'category_id', 'activity_name', 'description', 'dept_id', 
    'vertical', 'from_month', 'to_month', 'from_year', 
    'to_year', 'approved_limit', 'approved_amount', 'status'
];
```

#### Type Casting
```php
protected $casts = [
    'status' => 'boolean',
    'dept_id' => 'string',
    'vertical' => 'string',
    'from_month' => 'date',
    'to_month' => 'date',
    'approved_limit' => 'decimal:0',
    'approved_amount' => 'integer',
];
```

### Relationship Management

#### Category Relationship
```php
public function category()
{
    return $this->belongsTo(ActivityCategory::class, 'category_id', 'id');
}
```

#### Claim Type Relationship
```php
public function claimType()
{
    return $this->belongsTo(ClaimType::class, 'activity_name', 'ClaimId');
}
```

### Array Handling

#### Department IDs
```php
public function getDepartmentIdsAttribute()
{
    return $this->dept_id ? explode(',', $this->dept_id) : [];
}

public function setDepartmentIdsAttribute($value)
{
    $this->attributes['dept_id'] = is_array($value) ? implode(',', $value) : $value;
}
```

#### Verticals
```php
public function getVerticalsAttribute()
{
    return $this->vertical ? explode(',', $this->vertical) : [];
}

public function setVerticalsAttribute($value)
{
    $this->attributes['vertical'] = is_array($value) ? implode(',', $value) : $value;
}
```

### Computed Attributes

#### Claim Name
```php
public function getClaimNameAttribute()
{
    return $this->claimType ? $this->claimType->ClaimName : '-';
}
```

#### Department Names
```php
public function getDepartmentsAttribute()
{
    $deptIds = $this->getDepartmentIdsAttribute();
    if (empty($deptIds)) {
        return '-';
    }
    return CoreDepartments::whereIn('id', $deptIds)
        ->where('is_active', 1)
        ->pluck('department_name')
        ->implode(', ') ?: '-';
}
```

#### Vertical Names
```php
public function getVerticalNamesAttribute()
{
    $verticalIds = $this->getVerticalsAttribute();
    if (empty($verticalIds)) {
        return '-';
    }
    return CoreVertical::whereIn('id', $verticalIds)
        ->where('is_active', 1)
        ->pluck('vertical_name')
        ->implode(', ') ?: '-';
}
```

## 📊 Data Structure

### Database Schema

```sql
CREATE TABLE adv_activity_names (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    activity_name INT NOT NULL,
    description TEXT NULL,
    dept_id VARCHAR(255) NULL,
    vertical VARCHAR(255) NULL,
    from_month DATE NULL,
    to_month DATE NULL,
    from_year INT NULL,
    to_year INT NULL,
    approved_limit DECIMAL(15,2) NOT NULL,
    approved_amount INT NOT NULL,
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (category_id) REFERENCES adv_activity_categories(id),
    FOREIGN KEY (activity_name) REFERENCES claimtype(ClaimId)
);
```

### Field Descriptions

| Field | Type | Description | Constraints |
|-------|------|-------------|-------------|
| `id` | BIGINT | Primary key | Auto-increment |
| `category_id` | INT | Activity category reference | FK to adv_activity_categories |
| `activity_name` | INT | Claim type reference | FK to claimtype (cgId=7) |
| `description` | TEXT | Activity description | Optional |
| `dept_id` | VARCHAR(255) | Comma-separated department IDs | Optional |
| `vertical` | VARCHAR(255) | Comma-separated vertical IDs | Optional |
| `from_month` | DATE | Validity start month | Optional |
| `to_month` | DATE | Validity end month | Must be >= from_month |
| `from_year` | INT | Validity start year | 4 digits |
| `to_year` | INT | Validity end year | >= from_year |
| `approved_limit` | DECIMAL(15,2) | Budget limit | Required, >= 0 |
| `approved_amount` | INT | Approved amount | Required, >= 0 |
| `status` | BOOLEAN | Active/inactive status | Default: 1 |

## 🚨 Business Rules

### Validation Rules

1. **Category Association**: Optional but must exist if provided
2. **Claim Type Constraint**: Must be from claimtype table with cgId = 7
3. **Department Validation**: All department IDs must exist in core_departments
4. **Date Logic**: to_month must be after or equal to from_month
5. **Year Logic**: to_year must be greater than or equal to from_year
6. **Budget Constraints**: Both limit and amount must be non-negative

### Data Integrity

1. **Foreign Key Constraints**: Enforced at database and application level
2. **Array Storage**: Comma-separated values for departments and verticals
3. **Status Management**: Inactive records hidden from user selections
4. **Time Validity**: Month and year ranges define activity validity periods

### Permission System

1. **Automatic Generation**: Three permissions per activity name
2. **Role Assignment**: Super Admin gets default view access
3. **Granular Control**: Individual permissions per activity name
4. **Permission Cleanup**: Consider cleanup on deletion

## 🔐 Security Considerations

### Input Validation

**Comprehensive Validation**:
- Foreign key existence validation
- Array input sanitization
- Date range validation
- Numeric constraint validation
- String length limits

**Security Features**:
- SQL injection prevention through Eloquent ORM
- Mass assignment protection via `$fillable`
- Input sanitization through validation rules
- Array input filtering and type casting

### Data Protection

**Sensitive Data Handling**:
- Budget information requires proper access control
- Department and vertical mappings need authorization
- Audit trail for budget changes recommended

## 🚀 Performance Considerations

### Query Optimization

**Current Queries**:
```php
// Index with relationships
ActivityName::with(['category', 'claimType'])->where('status', 1);

// Helper endpoints
ActivityCategory::all();
CoreDepartments::active();
ClaimType::where('cgId', 7)->get(['ClaimId', 'ClaimName']);
CoreVertical::active();
```

**Optimization Recommendations**:

1. **Database Indexes**:
   ```sql
   CREATE INDEX idx_activity_names_status ON adv_activity_names (status);
   CREATE INDEX idx_activity_names_category ON adv_activity_names (category_id);
   CREATE INDEX idx_activity_names_activity ON adv_activity_names (activity_name);
   CREATE INDEX idx_activity_names_dates ON adv_activity_names (from_month, to_month);
   ```

2. **Query Optimization**:
   ```php
   // Selective field loading
   ActivityName::select('id', 'category_id', 'activity_name', 'status')
       ->with(['category:id,category_name', 'claimType:ClaimId,ClaimName'])
       ->where('status', 1)
       ->get();
   ```

3. **Caching Strategy**:
   ```php
   // Cache helper data for 1 hour
   $categories = Cache::remember('activity_categories', 3600, function () {
       return ActivityCategory::all();
   });
   
   $departments = Cache::remember('active_departments', 3600, function () {
       return CoreDepartments::active();
   });
   ```

### Memory Management

**Large Dataset Handling**:
- Implement pagination for index views
- Use chunking for bulk operations
- Consider lazy loading for relationships
- Optimize array processing for large datasets

## 📈 Monitoring and Analytics

### Key Metrics to Track

1. **Usage Statistics**:
   - Most frequently used activity names
   - Department and vertical distribution
   - Budget utilization rates
   - Activity name creation/update frequency

2. **Performance Metrics**:
   - Response times for CRUD operations
   - Database query performance
   - Helper endpoint usage patterns
   - Array processing performance

3. **Business Intelligence**:
   - Budget allocation patterns
   - Department-wise activity preferences
   - Seasonal activity trends
   - Approval limit vs actual amount analysis

### Logging Recommendations

```php
Log::info('Activity name created', [
    'activity_name_id' => $activityName->id,
    'category_id' => $activityName->category_id,
    'approved_limit' => $activityName->approved_limit,
    'departments' => $deptIds,
    'verticals' => $verticals,
    'user_id' => Auth::id(),
    'timestamp' => now()
]);
```

## 🔄 Future Enhancements

### Suggested Improvements

1. **Enhanced Validation**:
   ```php
   // Add budget validation rules
   'approved_amount' => 'required|integer|min:0|lte:approved_limit',
   
   // Add date overlap validation
   'from_month' => 'required_with:to_month|date',
   'to_month' => 'required_with:from_month|date|after_or_equal:from_month',
   ```

2. **Soft Delete Implementation**:
   ```php
   use Illuminate\Database\Eloquent\SoftDeletes;
   
   class ActivityName extends Model
   {
       use SoftDeletes;
       protected $dates = ['deleted_at'];
   }
   ```

3. **Advanced Filtering**:
   ```php
   public function index(Request $request)
   {
       $query = ActivityName::with(['category', 'claimType']);
       
       // Category filter
       if ($request->has('category_id')) {
           $query->where('category_id', $request->category_id);
       }
       
       // Budget range filter
       if ($request->has('min_limit')) {
           $query->where('approved_limit', '>=', $request->min_limit);
       }
       
       // Date range filter
       if ($request->has('valid_date')) {
           $query->where('from_month', '<=', $request->valid_date)
                 ->where('to_month', '>=', $request->valid_date);
       }
       
       return $query->paginate(20);
   }
   ```

4. **Budget Tracking**:
   ```php
   public function getBudgetUtilizationAttribute()
   {
       return $this->approved_amount > 0 
           ? ($this->approved_amount / $this->approved_limit) * 100 
           : 0;
   }
   ```

### Feature Additions

1. **Bulk Operations**:
   - Bulk status updates
   - Bulk budget adjustments
   - Import/export functionality
   - Template-based creation

2. **Advanced Budget Management**:
   - Budget approval workflows
   - Multi-level budget limits
   - Budget utilization tracking
   - Automated budget alerts

3. **Enhanced Relationships**:
   - Many-to-many department relationships
   - Hierarchical vertical structures
   - Activity name templates
   - Approval chain management

4. **Reporting Features**:
   - Budget utilization reports
   - Department-wise activity analysis
   - Trend analysis and forecasting
   - Compliance reporting

## 🔗 Related Components

### Database Tables
- `adv_activity_names` - Main activity name storage
- `adv_activity_categories` - Category relationships
- `claimtype` - Claim type relationships (cgId = 7)
- `core_departments` - Department mappings
- `core_verticals` - Vertical mappings
- `permissions` - Generated permissions
- `roles` - Role-based access control

### Related Controllers
- `ActivityCategoryController` - Category management
- `ActivityController` - Individual activity management
- `ActivityTypeController` - Activity type management
- `ClaimTypeController` - Claim type management
- `PermissionController` - Permission management

### Frontend Integration
- Activity name selection dropdowns
- Budget management interfaces
- Department and vertical selectors
- Admin management dashboards
- Real-time budget tracking

### Models and Relationships
```php
// Enhanced relationships to consider
public function activities()
{
    return $this->hasMany(Activity::class, 'activity_name_id');
}

public function budgetAllocations()
{
    return $this->hasMany(BudgetAllocation::class, 'activity_name_id');
}

public function approvals()
{
    return $this->hasMany(ActivityApproval::class, 'activity_name_id');
}
```

---

The Admin ActivityNameController provides comprehensive activity name management functionality with advanced features for budget control, department mapping, and time-based validity, serving as a critical component in the expense management system's activity organization structure.