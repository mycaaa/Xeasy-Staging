# 📚 Xeasy Laravel Documentation

Welcome to the comprehensive documentation for **Xeasy Laravel** - an enterprise-grade expense management system built with Laravel 12.

## 🎯 What is Xeasy Laravel?

Xeasy Laravel is a sophisticated expense management platform that automates and simplifies the entire expense claim lifecycle. From initial submission to final approval and reimbursement, the system provides a seamless experience for employees, managers, and finance teams across multiple companies and financial years.

## 🏗️ Architecture Overview

- **Framework**: Laravel 12 with PHP 8.2+
- **Database**: Multi-database architecture (MySQL/MariaDB)
- **Frontend**: Bootstrap 5, jQuery, ApexCharts, DataTables
- **Authentication**: Session-based + JWT token support
- **Authorization**: Role-based access control (Spatie Permission)
- **Multi-tenancy**: Company-based with dynamic database switching

## 📋 Table of Contents

### Getting Started
- [Getting Started Guide](getting-started.md) - Installation, setup, and first steps
- [Architecture Overview](architecture.md) - System design and technical architecture
- [Authentication System](authentication.md) - Login, permissions, and security

### Routing & Navigation
- [Routing Overview](routing/overview.md) - Route structure and organization
- [Web Routes](routing/web-routes.md) - Frontend application routes
- [API Routes](routing/api-routes.md) - RESTful API endpoints

### Controllers & Business Logic
- [HomeController](controllers/HomeController.md) - Dashboard and analytics
- [AuthController](controllers/AuthController.md) - Authentication and authorization
- [DashboardController](controllers/DashboardController.md) - Main dashboard functionality
- [ActivityController](controllers/ActivityController.md) - Activity management
- [ClaimantController](controllers/ClaimantController.md) - Expense claim submission
- [ClaimTypeController](controllers/ClaimTypeController.md) - Claim type configuration
- [EmployeeController](controllers/EmployeeController.md) - Employee management
- [PermissionController](controllers/PermissionController.md) - Permission management
- [ReportController](controllers/ReportController.md) - Report generation
- [SettingController](controllers/SettingController.md) - System settings
- [UsersController](controllers/UsersController.md) - User management
- [VerifierController](controllers/VerifierController.md) - Claim verification
- [VNRApproverController](controllers/VNRApproverController.md) - VNR approval workflow
- [VVNRApproverController](controllers/VVNRApproverController.md) - VVNR approval workflow

### API Documentation
- [Expense Claims API](api/expense-claims.md) - Expense claim endpoints
- [Users API](api/users.md) - User management endpoints
- [Odometer API](api/odometer.md) - Odometer backdate management
- [Advance Finance API](api/ADVANCE_FINANCE_API.md) - Advance payment finance operations
- [Advance Payment Confirmation API](api/ADVANCE_PAYMENT_CONFIRMATION_API.md) - Payment confirmation endpoints
- [Payment API Documentation](api/PAYMENT_API_DOCUMENTATION.md) - Payment processing APIs

### Advance Request System
- [Advance Request System Overview](ADVANCE_REQUEST_SYSTEM.md) - Complete system documentation
- [Advance Payment Export Tracking](ADVANCE_PAYMENT_EXPORT_TRACKING.md) - Export tracking feature
- [Export Excluded List Feature](EXPORT_EXCLUDED_LIST_FEATURE.md) - Excluded activities list
- [Export Excluded List Quick Reference](EXPORT_EXCLUDED_LIST_QUICK_REFERENCE.md) - Quick reference guide
- [Excluded Export Logging](EXCLUDED_EXPORT_LOGGING.md) - Logging excluded export attempts
- [Excluded Export Logging Summary](EXCLUDED_EXPORT_LOGGING_SUMMARY.md) - Implementation summary
- [Advance Request Limits](ADVANCE_REQUEST_LIMITS_FEATURE.md) - Activity-wise limits
- [Employee Ledger Balance](EMPLOYEE_LEDGER_BALANCE.md) - Ledger balance management

### Database & Models
- [Database Schema](database/schema.md) - Database structure and tables
- [Model Relationships](database/relationships.md) - Eloquent relationships and associations

### Reference
- [Glossary](glossary.md) - Terms, definitions, and concepts

## 🚀 Quick Start

1. **Installation**: Follow the [Getting Started Guide](getting-started.md)
2. **Authentication**: Learn about [Authentication System](authentication.md)
3. **Dashboard**: Explore [HomeController](controllers/HomeController.md) for dashboard features
4. **API Usage**: Check [API Documentation](api/expense-claims.md) for integration

## 🔧 Key Features

### 💼 Expense Management
- Multi-type expense claims (DA, Vehicle, Activity, etc.)
- Document upload and attachment support
- Odometer tracking for vehicle expenses
- Multi-stage approval workflows
- Real-time claim status tracking

### 📊 Analytics & Reporting
- Interactive dashboards with ApexCharts
- Department-wise expense analysis
- Year-over-year comparisons
- Custom date range filtering
- Excel and PDF export capabilities

### 👥 User & Permission Management
- Role-based access control (RBAC)
- Granular permission assignment
- User activity tracking and audit trails
- Permission groups for organization

### 🏢 Multi-Company Support
- Support for multiple companies
- Separate databases per company
- Dynamic database switching
- Company-specific configurations

### 🔐 Security Features
- Session-based authentication
- JWT token support for API access
- Activity logging and audit trails
- CSRF protection and secure sessions

## 📖 Documentation Standards

This documentation follows industry best practices:

- **Clear Structure**: Organized by feature and functionality
- **Cross-References**: Linked sections for easy navigation
- **Code Examples**: Practical examples with request/response formats
- **Search-Friendly**: Structured headings and consistent formatting
- **Developer-First**: Technical explanations with implementation details

## 🤝 Contributing to Documentation

To improve this documentation:

1. Follow the existing structure and formatting
2. Include practical examples and code snippets
3. Cross-reference related sections
4. Keep explanations clear and concise
5. Update the table of contents when adding new sections

## 📞 Support

- **Technical Issues**: Check controller-specific documentation
- **API Questions**: Refer to API documentation sections
- **Setup Problems**: Follow the Getting Started guide
- **Feature Requests**: Review architecture documentation

---

**Built with ❤️ using Laravel 12 and modern web technologies.**

*Last updated: January 2026*