# 🏗️ Architecture Overview

Xeasy Laravel is built with a modern, scalable architecture designed to handle enterprise-level expense management across multiple companies and financial years.

## 🎯 System Architecture

### High-Level Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Backend       │    │   Database      │
│                 │    │                 │    │                 │
│ • Bootstrap 5   │◄──►│ • Laravel 12    │◄──►│ • MySQL/MariaDB │
│ • jQuery        │    │ • PHP 8.2+      │    │ • Multi-DB      │
│ • ApexCharts    │    │ • Sanctum       │    │ • Redis Cache   │
│ • DataTables    │    │ • Spatie Pkg    │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Core Principles

1. **Multi-Tenancy**: Company-based separation with dynamic database switching
2. **Multi-Year Support**: Financial year-based data isolation
3. **Role-Based Access Control**: Granular permissions and security
4. **Activity Logging**: Comprehensive audit trails
5. **API-First Design**: RESTful APIs for integration
6. **Modular Structure**: Feature-based organization

## 🗄️ Database Architecture

### Multi-Database Design

Xeasy Laravel uses a sophisticated multi-database architecture:

```
Company 1 (VNR Seeds)
├── hrims_db          # Employee data
└── expense_db        # Expense claims

Company 2 (VNR Group)
├── hrims_vnr_group   # Employee data
└── expense_vnr_group # Expense claims

Company 3 (Nursery)
├── hrims_db          # Shared employee data
└── expense_nr        # Expense claims

Company 4 (VVNR Seeds)
├── hrims_vvnr        # Employee data
└── expense_vvnr      # Expense claims
```

### Database Connections

**Primary Connections** (defined in `config/database.php`):
- `expense` - Main expense management database
- `hrims` - HR and employee management database
- `sqlite` - Default fallback database

**Dynamic Switching** (via `config/company_databases.php`):
- Runtime database switching based on company selection
- Session-based company context
- Automatic connection management

### Table Structure

**Year-Based Tables**:
```
y2024_expenseclaims  # 2024 expense claims
y2025_expenseclaims  # 2025 expense claims
y2026_expenseclaims  # 2026 expense claims
```

**Core Tables**:
- `users` - System users
- `roles` - User roles
- `permissions` - System permissions
- `financial_years` - Financial year definitions
- `claim_types` - Expense claim types
- `hrm_employee` - Employee master data

## 🔐 Security Architecture

### Authentication Flow

```
User Login Request
       ↓
┌─────────────────┐
│ AuthController  │
│                 │
│ 1. Validate     │
│ 2. Decrypt PWD  │
│ 3. Check User   │
│ 4. Set Session  │
└─────────────────┘
       ↓
┌─────────────────┐
│ Session Store   │
│                 │
│ • employee_id   │
│ • company_id    │
│ • year_id       │
│ • role_id       │
└─────────────────┘
```

### Authorization Layers

1. **Authentication Middleware**: `auth`
2. **Permission Middleware**: `permission:permission_name`
3. **Role-Based Access**: Spatie Permission package
4. **Activity Logging**: All actions tracked

### Security Features

- **Password Encryption**: Custom encryption + bcrypt hashing
- **JWT Token Support**: For API authentication
- **Session Management**: Database-stored sessions
- **CSRF Protection**: Laravel's built-in CSRF
- **Activity Logging**: Comprehensive audit trails
- **IP Tracking**: User action monitoring

## 🔄 Request Lifecycle

### Web Request Flow

```
HTTP Request
     ↓
┌─────────────────┐
│ Route Handler   │
│ (web.php)       │
└─────────────────┘
     ↓
┌─────────────────┐
│ Middleware      │
│ • Auth          │
│ • Permission    │
│ • CSRF          │
└─────────────────┘
     ↓
┌─────────────────┐
│ Controller      │
│ • Validate      │
│ • Process       │
│ • Response      │
└─────────────────┘
     ↓
┌─────────────────┐
│ Model/Service   │
│ • Business      │
│ • Database      │
│ • Logging       │
└─────────────────┘
```

### API Request Flow

```
API Request
     ↓
┌─────────────────┐
│ Route Handler   │
│ (api.php)       │
└─────────────────┘
     ↓
┌─────────────────┐
│ API Controller  │
│ • Validate      │
│ • Process       │
│ • JSON Response │
└─────────────────┘
```

## 📦 Package Architecture

### Core Laravel Packages

- **Laravel Framework 12**: Core framework
- **Laravel Sanctum**: API authentication
- **Laravel UI**: Authentication scaffolding

### Spatie Packages

- **Laravel Permission**: Role-based access control
- **Laravel Activity Log**: Activity tracking and audit trails

### Data Processing

- **Maatwebsite Excel**: Excel import/export
- **Laravel DomPDF**: PDF generation
- **Yajra DataTables**: Server-side table processing

### Frontend Integration

- **Laravel Mix/Vite**: Asset compilation
- **Bootstrap 5**: UI framework
- **ApexCharts**: Data visualization
- **DataTables**: Advanced table functionality

## 🏢 Multi-Company Architecture

### Company Isolation

Each company operates with:
- **Separate Databases**: HRIMS + Expense databases
- **Isolated Data**: No cross-company data access
- **Company Context**: Session-based company selection
- **Dynamic Switching**: Runtime database connection switching

### Configuration Management

**Company Database Config** (`config/company_databases.php`):
```php
'companies' => [
    1 => [
        'name' => 'VNR Seeds Pvt Ltd',
        'hrims' => ['database' => 'hrims', ...],
        'expense' => ['database' => 'expense', ...]
    ],
    // ... other companies
]
```

### Session Management

**Company Context Storage**:
- `company_id` - Selected company
- `year_id` - Selected financial year
- `employee_id` - Current user
- `role_id` - User role

## 📅 Multi-Year Architecture

### Year-Based Data Isolation

**Dynamic Table Naming**:
```php
// ExpenseClaim model
public static function tableName(int $yearId): string
{
    return 'y' . $yearId . '_expenseclaims';
}
```

**Financial Year Management**:
- Year activation/deactivation
- Year-over-year comparisons
- Historical data preservation
- Year-based reporting

## 🔄 Service Layer Architecture

### Controller → Service → Model Pattern

```
Controller Layer
├── Request validation
├── Authorization checks
└── Response formatting

Service Layer
├── Business logic
├── Data processing
└── External integrations

Model Layer
├── Database operations
├── Relationships
└── Activity logging
```

### Example Service Implementation

```php
class ExpenseClaimService
{
    public function createClaim(array $data): ExpenseClaim
    {
        // Business logic
        $this->validateClaimData($data);
        
        // Database operation
        $claim = ExpenseClaim::create($data);
        
        // Activity logging
        activity()->log('Expense claim created');
        
        return $claim;
    }
}
```

## 📊 Caching Architecture

### Cache Layers

1. **Application Cache**: Database-based caching
2. **Permission Cache**: Spatie permission caching (24 hours)
3. **Session Cache**: Database session storage
4. **Query Cache**: Eloquent query result caching

### Cache Configuration

```php
// config/cache.php
'default' => 'database',
'stores' => [
    'database' => [
        'driver' => 'database',
        'table' => 'cache',
    ],
]
```

## 🔍 Logging Architecture

### Activity Logging

**Spatie Activity Log Integration**:
- Model changes tracking
- User action logging
- IP address and user agent capture
- Custom log names and attributes

**Log Configuration**:
```php
// In models
use LogsActivity;

protected static $logAttributes = ['*'];
protected static $logName = 'expense_claim';
protected static $logOnlyDirty = true;
```

### System Logging

- **Laravel Log**: Application errors and debugging
- **User Logs**: Authentication and user actions
- **Activity Logs**: Model changes and business actions

## 🚀 Performance Architecture

### Optimization Strategies

1. **Database Indexing**: Optimized queries with proper indexes
2. **Eager Loading**: Prevent N+1 query problems
3. **Caching**: Multi-layer caching strategy
4. **Asset Optimization**: Minified CSS/JS with Vite
5. **Query Optimization**: Efficient database queries

### Scalability Considerations

- **Multi-Database**: Horizontal scaling per company
- **Queue System**: Background job processing
- **API Rate Limiting**: Prevent abuse
- **Session Management**: Database-based sessions for clustering

## 🔧 Development Architecture

### Environment Configuration

- **Local**: Development with debug enabled
- **Staging**: Pre-production testing
- **Production**: Optimized for performance

### Development Tools

- **Laravel Debugbar**: Development debugging
- **Laravel Pint**: Code style fixing
- **PHPUnit**: Unit testing
- **Laravel Sail**: Docker development environment

## 📈 Monitoring & Maintenance

### Health Monitoring

- **Application Logs**: Error tracking and debugging
- **Activity Logs**: User action monitoring
- **Performance Metrics**: Query performance tracking
- **Security Logs**: Authentication and authorization tracking

### Maintenance Tasks

- **Log Rotation**: Automatic log cleanup
- **Cache Clearing**: Scheduled cache maintenance
- **Database Optimization**: Query optimization and indexing
- **Security Updates**: Regular package updates

---

This architecture provides a robust, scalable foundation for enterprise expense management while maintaining flexibility for future enhancements and integrations.