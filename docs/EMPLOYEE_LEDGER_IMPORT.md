# Employee Ledger Balance Import System

## Overview

The Employee Ledger Balance Import system allows administrators to import employee ledger balance data from Excel files. This system handles employee code validation, duplicate detection, and provides comprehensive import reporting.

## Features

- **Excel Import**: Support for .xlsx, .xls, and .csv files
- **Employee Code Validation**: Handles both E-prefixed codes and V-prefixed vendor codes
- **Duplicate Detection**: Optional skipping of duplicate entries
- **Comprehensive Reporting**: Detailed import results with error reporting
- **Template Download**: Provides a sample template for data preparation

## Database Schema

The system uses the `employee_ledger_balances` table with the following structure:

```sql
CREATE TABLE `employee_ledger_balances` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `year_id` INT(11) NOT NULL,
    `company_id` INT(11) NOT NULL,
    `dr` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
    `cr` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
    `fixed_imprest` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
    `fixed_imprest_date` DATE NULL DEFAULT NULL,
    `grade` VARCHAR(10) NULL DEFAULT NULL,
    `entry_date` DATE NOT NULL,
    `remark` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_employee_year` (`employee_id`, `year_id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_entry_date` (`entry_date`)
);
```

## Excel File Format

### Required Columns

| Column | Description | Format | Example |
|--------|-------------|--------|---------|
| Date | Entry date | DD-MM-YYYY | 05-01-2026 |
| EC | Employee Code | E#### or #### | E1734 |
| Grade | Employee Grade | Text | J1, J2, J3 |
| Amount | Fixed Imprest Amount | Number (commas allowed) | 20,000 |

### Sample Data

```
Date        EC      Grade   Amount
05-01-2026  E1734   J1      20,000
16-12-2025  E1728   J3      40,000
05-12-2025  E1710   J2      20,000
05-12-2025  E1714   J3      40,000
05-12-2025  E1715   J3      40,000
05-12-2025  E1718   J3      40,000
```

## Employee Code Handling

### Standard Employee Codes (E-prefixed)
- Format: E#### (e.g., E1734)
- System removes 'E' prefix and matches against `hrm_employees.emp_code`
- Falls back to exact match if needed

### Vendor Codes (V-prefixed)
- Format: V#### (e.g., V1001)
- System looks up in `vendor_list` table using `v_code`
- Maps to employee via `vendor_list.emp_code` → `hrm_employees.id`

### Numeric Codes
- Format: #### (e.g., 1734)
- Direct match against `hrm_employees.emp_code`

## API Endpoints

### Import Data
```
POST /api/employee-ledger/import
Content-Type: multipart/form-data

Parameters:
- excel_file: File (required)
- year_id: Integer (required)
- skip_duplicates: Boolean (optional, default: true)
- validate_employees: Boolean (optional, default: true)
```

### Get Financial Years
```
GET /api/financial-years

Response:
[
    {"id": 1, "name": "2025-26"},
    {"id": 2, "name": "2024-25"}
]
```

### Download Template
```
GET /api/employee-ledger/template

Response: CSV file download
```

## Import Process

1. **File Upload**: User selects Excel file and financial year
2. **File Parsing**: System reads Excel file using PhpSpreadsheet
3. **Data Validation**: Each row is validated for:
   - Required fields (Date, Employee Code, Amount)
   - Date format validation
   - Amount format validation
   - Employee code existence (if validation enabled)
4. **Duplicate Check**: Optional check for existing records
5. **Data Insertion**: Valid records are inserted into database
6. **Result Reporting**: Comprehensive report with success/error counts

## Error Handling

### Validation Errors
- Missing required fields
- Invalid date formats
- Invalid amount formats
- Employee not found
- Duplicate entries (if not skipped)

### Import Results
```json
{
    "success": true,
    "total_processed": 6,
    "successful_imports": 5,
    "skipped_duplicates": 1,
    "validation_errors": 0,
    "errors": []
}
```

## Usage Instructions

1. **Access Import Page**: Navigate to `/admin/employee-ledger/import`
2. **Download Template**: Click "Download Template" for sample format
3. **Prepare Data**: Format your Excel file according to the template
4. **Import Data**: 
   - Click "Import Data" button
   - Select your Excel file
   - Choose financial year
   - Configure import options
   - Click "Start Import"
5. **Review Results**: Check import summary and any validation errors

## File Structure

```
app/
├── Http/Controllers/
│   └── EmployeeLedgerController.php
├── Models/
│   └── EmployeeLedgerBalance.php
└── View/Components/modal/
    └── EmployeeLedgerImport.php

resources/views/
├── admin/employee-ledger/
│   └── import.blade.php
└── components/modal/
    └── employee-ledger-import.blade.php

routes/
└── employee-ledger.php

database/migrations/
└── 2026_02_02_150000_create_employee_ledger_balances_table.php
```

## Security Considerations

- File upload validation (type, size)
- Authentication required for all operations
- SQL injection protection via Eloquent/Query Builder
- Input sanitization for all user data
- Transaction rollback on errors

## Performance Considerations

- Database transactions for data integrity
- Batch processing for large files
- Indexed columns for faster queries
- Memory-efficient file processing
- Progress reporting for large imports

## Future Enhancements

- Import history tracking
- Scheduled imports
- Email notifications
- Advanced validation rules
- Bulk update capabilities
- Export functionality