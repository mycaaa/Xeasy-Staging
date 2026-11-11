# ExpenseHeadCategoryController Documentation

## Overview
The `ExpenseHeadCategoryController` manages expense head categories that define the types of expenses that can be claimed in the system. This controller provides comprehensive CRUD operations for expense head categories, including field type configuration, file attachment settings, and status management with integrated permission system.

## Class Information
- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `App\Http\Controllers\Controller`
- **Model**: `App\Models\ExpenseHeadCategory`
- **Dependencies**: 
  - `Spatie\Permission\Models\Permission`
  - `Spatie\Permission\Models\Role`
- **Location**: `app/Http/Controllers/admin/ExpenseHeadCategoryController.php`

## Methods

### index()
Displays a filtered listing of expense head categories.

**Route**: `GET /admin/expense-head-categories`

**Purpose**: Provides a comprehensive view of expense head categories with status-based filtering.

**Parameters**:
- `status` (query parameter): Filter by status ('active', 'inactive', 'all')

**Returns**: 
- View: `admin.expense_head_category`
- Data: Filtered expense head categories and current status

**Response Data Structure**:
```php
[
    'expenseHeads' => [
        [
            'id' => 1,
            'expense_head_name' => 'Travel Expenses',
            'short_code' => 'TRV',
       