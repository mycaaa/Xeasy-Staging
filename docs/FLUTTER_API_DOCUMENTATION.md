# Xeasy Flutter App — Complete API Documentation

**Project:** Xeasy Expense Management System  
**Backend:** Laravel (PHP)  
**Frontend:** Flutter (Dart)  
**Last Updated:** June 2026

---

## Table of Contents

1. [Base URLs & Configuration](#1-base-urls--configuration)
2. [Authentication](#2-authentication)
3. [Session Storage (SharedPreferences)](#3-session-storage-sharedpreferences)
4. [API Environments](#4-api-environments)
5. [Login API](#5-login-api)
6. [Legacy PHP API — Common Endpoints](#6-legacy-php-api--common-endpoints)
7. [Legacy PHP API — Claim Endpoints](#7-legacy-php-api--claim-endpoints)
8. [Legacy PHP API — Approver Endpoints](#8-legacy-php-api--approver-endpoints)
9. [New Laravel REST API — Advance Request Module](#9-new-laravel-rest-api--advance-request-module)
   - [Advance Requests (CRUD)](#91-advance-requests-crud)
   - [Advance Approvals](#92-advance-approvals)
   - [Advance Finance](#93-advance-finance)
   - [HOD Advance Report](#94-hod-advance-report)
10. [New Laravel REST API — Odometer Backdate](#10-new-laravel-rest-api--odometer-backdate)
11. [New Laravel REST API — Payment Import](#11-new-laravel-rest-api--payment-import)
12. [New Laravel REST API — Employee Ledger](#12-new-laravel-rest-api--employee-ledger)
13. [New Laravel REST API — Hierarchy](#13-new-laravel-rest-api--hierarchy)
14. [Standard Response Formats](#14-standard-response-formats)
15. [Error Codes](#15-error-codes)
16. [Data Models](#16-data-models)
17. [Flutter Files & Their API Calls (Quick Reference)](#17-flutter-files--their-api-calls-quick-reference)

---

## 1. Base URLs & Configuration

**Flutter config file:** `lib/utils/Config.dart`

| Constant | Value | Used For |
|---|---|---|
| `MainUrl` | `https://expense.vnrseeds.co.in/api/` | VNR / Nursery legacy PHP API |
| `VVNRMainUrl` | `https://expense.vnrseeds.co.in/api/11/api/` | VVNR (Company 11) legacy PHP API |
| `NewXeasyMainUrl` | `https://expensenew.vnrseeds.co.in/api/` | New Laravel REST API |
| `CommonUrl` | `common.php` | Legacy PHP common script |
| `UploadClaim` | `claim.php` | Legacy bill/odometer upload script |
| `ExtractDataUploadImage` | `extract-data.php` | OCR / Image claim list |
| `AdvanceRequest` | `advance_request.php` | Legacy advance tab visibility check |
| `LoginUrl` | `MainUrl + 'login_new.php'` | Login endpoint |
| `VVNRCommonUrl` | `VVNRMainUrl + 'common_rep.php'` | VVNR approver common endpoint |
| `AppVersion` | `MainUrl + 'appversion.php'` | App version check |

**Legacy URL pattern:**
```
{MainUrl}{ComId}/{script}
e.g. https://expense.vnrseeds.co.in/api/1/common.php
```

**Company IDs:**

| ComId | Company |
|---|---|
| 1 | VNR Seeds |
| 3 | VNR Nursery |
| 11 | VVNR Seeds |

---

## 2. Authentication

### Legacy PHP API
- No `Authorization` header is used
- Authentication is done via a **device token** (`tokenid` = Firebase FCM token)
- `tokenid` is stored in `hrm_employee.xeasy_tokenid` (Android) or `hrm_employee.xeasy_tokenid_ios` (iOS)
- All calls pass these mandatory fields in body (POST) or query string (GET):
  - `empid` — Employee ID (numeric)
  - `YearId` — Financial year ID
  - `ComId` — Company ID
  - `tokenid` — FCM device token

### New Laravel REST API
- Uses **query parameter `user_id`** (EmployeeID) — no HTTP Authorization header
- Stateless — no session or cookie required
- Example: `GET https://expensenew.vnrseeds.co.in/api/advance/approvals/pending?user_id=1234`

### Web SSO (Laravel)
- Supports JWT token login via `firebase/php-jwt`
- Secret key: `v7n90l9uvy` (HS256)
- Token payload: `{ sub: "employeeId" }`
- Endpoint: `POST /login?token={jwt}`

---

## 3. Session Storage (SharedPreferences)

After login, the Flutter app stores these values in `SharedPreferences`:

| Key | Type | Description |
|---|---|---|
| `EmployeeID` | String | Numeric employee ID |
| `EmpCode` | String | Employee code (e.g. "EMP001") |
| `EmpName` | String | Full name |
| `ComId` | String | Company ID (1/3/11) |
| `YearId` | String | Financial year ID |
| `Role` | String | "A" (Approver) or "E" (Employee) |
| `TokenId` | String | FCM device token |
| `Reporting_VESS` | String | "Y" if manages VVNR employees |
| `Reporting_NURSURY` | String | "Y" if manages Nursery employees |
| `Grade` | String | Grade code |
| `GradeValue` | String | Grade display name |
| `HeadQuarter` | String | HQ city/territory name |
| `Designation` | String | Designation name |
| `Mobile` | String | Official mobile number |
| `Reporting` | String | Reporting manager name |
| `Department` | String | Department name |
| `FromDate` | String | Financial year start date |
| `ToDate` | String | Financial year end date |
| `SelectedYear` | String | Financial year label (e.g. "2024-25") |
| `UserImage` | String | Profile image URL |
| `isLoggedIn` | Bool | Login state flag |

---

## 4. API Environments

| Environment | Base URL | Notes |
|---|---|---|
| Legacy Production (VNR) | `https://expense.vnrseeds.co.in/api/` | PHP scripts |
| Legacy Production (VVNR) | `https://expense.vnrseeds.co.in/api/11/api/` | PHP scripts |
| New REST Production | `https://expensenew.vnrseeds.co.in/api/` | Laravel REST |
| Demo (commented out) | `https://expense.vnrseeds.co.in/api_demo/` | Testing only |

---

## 5. Login API

### 5.1 Login (Mobile App)

**Endpoint:** `POST {LoginUrl}` → `POST https://expense.vnrseeds.co.in/api/login_new.php`  
**Content-Type:** `multipart/form-data`

**Request Fields:**

| Field | Type | Required | Description |
|---|---|---|---|
| `value` | String | Yes | Fixed: `"login"` |
| `ComId` | String | Yes | Company ID (fixed `"1"` for mobile) |
| `empcode` | String | Yes | Employee code (leading zeros stripped, lowercase for VVNR) |
| `emppass` | String | Yes | Password (encrypted in DB, decrypted on server) |
| `platform` | String | Yes | `"android"` or `"ios"` |
| `tokenid` | String | Yes | FCM device token |
| `yearid` | String | Yes | Financial year ID |

**Success Response (Code "300"):**
```json
{
  "Code": "300",
  "EmployeeID": "1234",
  "EmpCode": "EMP001",
  "EmpName": "John Doe",
  "ComId": "1",
  "YearId": "7",
  "Role": "A",
  "Reporting_VESS": "Y",
  "Reporting_NURSURY": "N",
  "Status": "A",
  "Grade": "G3",
  "GradeValue": "M3",
  "HeadQuarterName": "Nagpur",
  "DesigName": "Territory Manager",
  "MobileNo": "9999999999",
  "ReportingName": "Jane Doe",
  "DepartmentName": "Sales",
  "ProfileImg": "https://vnrseeds.co.in/file-view/Employee_Image/1/EMP001.jpg",
  "FromDate": "2024-04-01",
  "ToDate": "2025-03-31"
}
```

**Error Response:**
```json
{
  "Code": "400",
  "Msg": "Invalid employee ID or password"
}
```

---

### 5.2 Get Year List (Login Screen Dropdown)

**Endpoint:** `POST {MainUrl}1/common.php`

**Request Fields:**

| Field | Value |
|---|---|
| `value` | `"yearlist"` |
| `ComId` | `"1"` |

**Response:**
```json
{
  "Code": "300",
  "year_list": [
    { "YearId": "7", "Year": "2024-25", "Status": "Active" },
    { "YearId": "6", "Year": "2023-24", "Status": "Inactive" }
  ]
}
```

---

### 5.3 App Version Check

**Endpoint:** `GET {MainUrl}{ComId}/appversion.php`

**Query Params:** `value=version`, `ComId`, `empid`

**Response:**
```json
{
  "Code": "300",
  "version": "2.5.1",
  "force_update": "Y",
  "message": "Please update to continue"
}
```

---

## 6. Legacy PHP API — Common Endpoints

All requests go to: `{MainUrl}{ComId}/common.php`  
Method: `POST multipart/form-data` or `GET` with query params

### 6.1 Open Claims List (Current Month)

**value:** `"Openlist"`  
**Method:** POST

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |

**Response:**
```json
{
  "Code": "300",
  "Open_list": [
    {
      "Month": "4",
      "Month_Name": "April",
      "TotClaims": 3,
      "TotAmt": 1500.00,
      "DayWiseDetails": [
        {
          "BillDate": "2024-04-10",
          "ExpId": "101",
          "ClaimType": "TA",
          "Amount": 500.00,
          "ClaimStatus": "Filled",
          "Remark": ""
        }
      ]
    }
  ]
}
```

---

### 6.2 Closed Claims List

**value:** `"Closelist"` / `"Closelist_All_Month"` / `"Closelist_Month"`  
**Method:** POST

| Field | Required | Description |
|---|---|---|
| `empid` | Yes | Employee ID |
| `YearId` | Yes | Year ID |
| `ComId` | Yes | Company ID |
| `tokenid` | Yes | FCM token |
| `Month` | Conditional | Required for `"Closelist_Month"` |

**Response:**
```json
{
  "Code": "300",
  "Close_list": [
    {
      "Month": "3",
      "Month_Name": "March",
      "Status": "Closed",
      "TotAmt": 12500.00,
      "FinanceAmt": 12000.00,
      "PaidDate": "2024-04-15"
    }
  ]
}
```

---

### 6.3 Draft Claims List

**value:** `"Draftlist"`  
**Method:** POST

Same auth fields. Returns claims in `"Draft_list"` key.

---

### 6.4 Day-wise Open List Details

**value:** `"Openlist_Day"`  
**Method:** GET

| Query Param | Description |
|---|---|
| `value` | `"Openlist_Day"` |
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `Month` | Month number (1-12) |

**Response:**
```json
{
  "Code": "300",
  "DayWiseDetails": [
    {
      "BillDate": "2024-04-05",
      "ExpId": "201",
      "ClaimId": "1",
      "ClaimName": "TA",
      "Amount": 350.00,
      "ClaimStatus": "Filled",
      "ImgPath": "https://..."
    }
  ]
}
```

---

### 6.5 Add Remark

**value:** `"AddRemark"`  
**Method:** POST

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `ExpId` | Expense claim ID |
| `remark` | Remark text |

**Response:** `{ "Code": "300", "Msg": "Remark added" }`

---

### 6.6 DA Claim Types List

**value:** `"DA_ClaimType"`  
**Method:** POST

Returns a list of claim types applicable to the employee for DA filing.

**Response:**
```json
{
  "Code": "300",
  "ClaimType_list": [
    { "ClaimId": "19", "ClaimName": "Daily Allowance", "MaxAmt": 500 },
    { "ClaimId": "20", "ClaimName": "Night Stay", "MaxAmt": 1000 }
  ]
}
```

---

### 6.7 Team Member List (Approver)

**value:** `"TeamMemberAppraiser"`  
**Method:** GET

Returns all team members for the logged-in approver.

**Response:**
```json
{
  "Code": "300",
  "Team_list": [
    { "EmployeeID": "456", "EmpCode": "EMP456", "FullName": "Jane Smith" },
    { "EmployeeID": "789", "EmpCode": "EMP789", "FullName": "Bob Jones" }
  ]
}
```

---

### 6.8 Show/Hide Odometer Approver Button

**value:** `"show_hide_pending"`  
**Method:** GET

**Response:**
```json
{
  "Code": "200",
  "data": "Y"
}
```
`data`: "Y" = show approver odometer tab, "N" = hide.

---

### 6.9 VNR Pending Claims List (Approver)

**value:** `"Team_Pending_Claimlist"`  
**Method:** POST  
**File:** `vnr_pending_claims_api_call.dart`

**Response:**
```json
{
  "Pending_list": [
    {
      "EmployeeID": "456",
      "EmpCode": "EMP456",
      "EmpName": "Jane Smith",
      "Month": "4",
      "TotAmt": 3200.00,
      "DayWiseDetails": [...]
    }
  ]
}
```

---

### 6.10 VNR Approved Claims List (Approver)

**value:** `"Team_Approved_Claimlist"` / `"ApprovedClaimsByMonth"` / `"ApprovedClaimsByMem"` / `"ApprovedClaimsByMonthMem"`  
**Method:** POST  
**File:** `vnr_approved_claims_api_call.dart`

| Field | Required For |
|---|---|
| `month` | `ApprovedClaimsByMonth`, `ApprovedClaimsByMonthMem` |
| `memid` | `ApprovedClaimsByMem`, `ApprovedClaimsByMonthMem` |

**Response:**
```json
{
  "Approved_list": [
    {
      "EmployeeID": "456",
      "Month": "4",
      "ApprovedAmt": 3000.00,
      "DayWiseDetails": [...]
    }
  ]
}
```

---

### 6.11 VNR Pending Claims — Day-wise Details

**value:** `"Team_Pending_Claimlist_Day"`  
**Method:** GET  

Query params: `empid`, `YearId`, `ComId`, `tokenid`, `Month`

**Response:**
```json
{
  "Code": "300",
  "DayWiseDetails": [
    {
      "BillDate": "2024-04-10",
      "ExpId": "101",
      "ClaimName": "TA",
      "Amount": 500.00
    }
  ]
}
```

---

### 6.12 VNR Approved Claims — Day-wise Details

**value:** `"Team_Approved_Claimlist_Day"`  
**Method:** GET  
Query params: `empid`, `YearId`, `ComId`, `tokenid`, `Month`

---

### 6.13 All Team Expense (Approver)

**value:** `"AllTeamExp"` / `"TeamMemberExpenses"` / `"TeamMemberExpensesById"` / `"TeamExpensesByIdAndMember"` / `"TeamRunningKilometers"`  
**Method:** POST  
**File:** `all_team_expense_screen.dart`

---

### 6.14 Show Activity Tab Visibility

**Endpoint:** `GET {MainUrl}{ComId}/extract-data.php`

| Query Param | Value |
|---|---|
| `value` | `"show_activity_tab"` |
| `YearId` | Year ID |
| `tokenid` | FCM token |
| `ComId` | Company ID |
| `empid` | Employee ID |

**Response:** `{ "Code": "300", "show_tab": "Y" }`

---

### 6.15 Show Approver Advance Tab

**Endpoint:** `GET {MainUrl}{ComId}/advance_request.php`

| Query Param | Value |
|---|---|
| `value` | `"show_approver_tab"` |
| `YearId` | Year ID |
| `tokenid` | FCM token |
| `ComId` | Company ID |
| `empid` | Employee ID |

**Response:** `{ "code": 200, "data": "Yes" }`

---

### 6.16 Vehicle Running KM

**value:** `"VehicleRunning"`  
**Method:** GET or POST  

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `month` | Month number |

---

## 7. Legacy PHP API — Claim Endpoints

All requests go to: `{MainUrl}{ComId}/claim.php`  
Method: `POST multipart/form-data`

### 7.1 Upload Bill / Expense Claim

**value:** `"upload_bill"`

| Field | Type | Description |
|---|---|---|
| `empid` | String | Employee ID |
| `YearId` | String | Year ID |
| `ComId` | String | Company ID |
| `tokenid` | String | FCM token |
| `claimId` | String | Claim type ID |
| `date` | String | Bill date (YYYY-MM-DD) |
| `amount` | String | Claim amount |
| `remark` | String | Optional remark |
| `image` | File | Bill image/PDF |

**Response:** `{ "Code": "300", "Msg": "Claim uploaded", "ExpId": "101" }`

---

### 7.2 Upload Odometer Opening

**value:** `"uploadimg_odo_opening"`

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `odomtr_opening` | Opening odometer reading |
| `remark` | Remark |
| `vehicleType` | Vehicle type |
| `image` | Odometer photo |

---

### 7.3 Upload Odometer Closing

**value:** `"uploadimg_odo_closing"`

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `odomtr_closing` | Closing odometer reading |
| `odomtr_opening` | Opening reading (for KM validation) |
| `vehicleType` | Vehicle type |
| `image` | Odometer photo |

---

### 7.4 Odometer Details for a Date

**value:** `"Current_date_odo_details"`

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `date` | Date (YYYY-MM-DD) |

---

### 7.5 Delete Claim

**value:** `"Claim_Deleted"`

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `ExpId` | Expense ID to delete |

**Response:** `{ "Code": "300", "Msg": "Claim deleted" }`

---

### 7.6 Mark Claim Okay (Self-Audit)

**value:** `"Claim_Okay"`

Same fields as Delete. Sets claim status to "Okay".

---

### 7.7 Deny Claim

**value:** `"Claim_Deny"`

Same fields as Delete + `remark` field.

---

### 7.8 Submit Claims for Month

**value:** `"Claim_Submit"`

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `Month` | Month number |

---

### 7.9 Courier Submit (Close Month)

**value:** `"Courier_Submit"`

Submits a closed month for courier/physical document submission.

---

### 7.10 DA Update

**value:** `"DA_Update"`

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `claimId` | DA claim type ID |
| `amount` | DA amount |
| `remark` | Remark |
| `date` | Date |
| `image` | Optional receipt image |

---

### 7.11 Upload Activity Bill (Seed Trial / Field Activity)

**value:** `"upload_activity_bill"`

| Field | Description |
|---|---|
| `empid` | Employee ID |
| `YearId` | Year ID |
| `ComId` | Company ID |
| `tokenid` | FCM token |
| `claimId` | Claim type ID |
| `activityType` | Activity type ID |
| `cropIds` | JSON array of crop IDs |
| `varietyIds` | JSON array of variety IDs |
| `amount` | Amount |
| `date` | Date |
| `images` | Bill images |

---

### 7.12 Image OCR / Extract Data

**Endpoint:** `GET {MainUrl}{ComId}/extract-data.php`  
**value:** `"claim_list"` — returns available claim types for OCR

**For extraction:**  
**value:** `"extract_data"`  
**Method:** POST

| Field | Description |
|---|---|
| `YearId` | Year ID |
| `tokenid` | FCM token |
| `ComId` | Company ID |
| `empid` | Employee ID |
| `claimId` | Claim type |
| `image` | Image file |

---

## 8. Legacy PHP API — Approver Endpoints

### 8.1 VVNR Pending Claims List

**Endpoint:** `POST {VVNRCommonUrl}` → `POST https://expense.vnrseeds.co.in/api/11/api/common_rep.php`  
**value:** `"Team_Pending_Claimlist"`  
**File:** `vvnr_pending_claims_api_call.dart`

Same request/response structure as VNR pending claims (section 6.9).

---

### 8.2 VVNR Approved Claims List

**value:** `"Team_Approved_Claimlist"` / `"ApprovedClaimsByMonth"` / `"ApprovedClaimsByMem"` / `"ApprovedClaimsByMonthMem"`  
**File:** `vvnr_approved_claims_api_call.dart`

Same structure as VNR approved claims (section 6.10).

---

### 8.3 Nursery Pending / Approved Claims

**Endpoint:** `{MainUrl}3/common.php`  
**ComId is hardcoded to `"3"` for Nursery**  
**Files:** `nursery_pending_claims_api_call.dart`, `nursery_approved_claims_api_call.dart`

Same request/response structure as VNR claims.

---

### 8.4 Last Odometer Reading (Approver Team View)

**value:** `"team_last_odo"`  
**Method:** GET  
Files: `vnr_last_odometer.dart`, `vvnr_last_odometer.dart`

---

## 9. New Laravel REST API — Advance Request Module

**Base URL:** `https://expensenew.vnrseeds.co.in/api/`  
**Route file:** `routes/advance.php`  
**Authentication:** Pass `user_id={employeeId}` as query parameter  

All responses follow this envelope:
```json
{
  "success": true | false,
  "message": "...",
  "data": { ... } | [ ... ] | null
}
```

---

### 9.1 Advance Requests (CRUD)

#### List My Activity Advance Requests

`GET /advance/?user_id={employeeId}`

Optional query params:
- `status` — filter by status (Draft, Submitted, In_Approval, Finance, Closed, etc.)

**Response data:** Array of employee objects, each containing `req_data` + `activities`.

---

#### List My Expense Advance Requests

`GET /advance/expense?user_id={employeeId}`

---

#### List My Other Advance Requests

`GET /advance/other?user_id={employeeId}`

---

#### Get Single Request Detail

`GET /advance/{requestId}?user_id={employeeId}`

---

#### Create Draft & Submit (Mobile main flow)

`POST /advance/draft?user_id={employeeId}`  
**Content-Type:** `application/json`  
**CORS origin:** `https://expense.vnrseeds.co.in`

**Request Body:**
```json
{
  "request": {
    "advance_type_id": 1,
    "activity_name_id": 5,
    "month": 4,
    "advance_fill_date": "2024-04-20",
    "req_app_submit_id": 1234
  },
  "employees": [
    {
      "employee_id": 1234,
      "activities": [
        {
          "activity_type_id": 2,
          "activity_crops_id": [1, 3],
          "activity_variety_id": [7, 12],
          "amount": 5000,
          "purpose": "Seed distribution in Nagpur",
          "is_tour_travel": "N"
        }
      ]
    }
  ]
}
```

**Advance Type IDs:**

| ID | Code | Name |
|---|---|---|
| 1 | Act | Activity Advance |
| 2 | Exp | Expense Advance |
| 3 | Oth | Other Advance |

**Response:**
```json
{
  "success": true,
  "message": "Draft advance request created and submitted successfully",
  "data": {
    "id": 42,
    "current_status": "Submitted",
    "approval_required": true,
    "employees": [...],
    "actions": [...]
  }
}
```

> **Note:** If `approval_required` is `false` (based on `advance_request_masters` config), the request is automatically moved to "Finance" status with activities auto-approved.

---

#### Update Draft

`PUT /advance/draft/{requestId}?user_id={employeeId}`

Same body structure as Create.

---

#### Submit Draft

`POST /advance/submit/{requestId}?user_id={employeeId}`

---

#### Resubmit (After Rejection/Revert)

`POST /advance/resubmit/{requestId}?user_id={employeeId}`

---

#### Delete Draft

`DELETE /advance/{requestId}?user_id={employeeId}`

Only Draft-status requests can be deleted.

---

#### Table View

`GET /advance/table-view?user_id={employeeId}&status=&month=&year=&employee_id=`

---

#### Employee Summary

`GET /advance/employee-summary?user_id={employeeId}&month=&year=`

---

### 9.2 Advance Approvals

#### Get Pending Approvals

`GET /advance/approvals/pending?user_id={employeeId}`

Returns all advance requests pending the calling user's approval.

**Response data (array of employee objects):**
```json
[
  {
    "id": 10,
    "advance_request_id": 5,
    "employee_id": 456,
    "employee_name": "Jane Smith",
    "employee_code": "EMP456",
    "department": "Sales",
    "designation": "Territory Manager",
    "total_pending_amount": 5000,
    "pending_activities_count": 1,
    "current_approval_level": 1,
    "approver_level_info": "Level 1 Approval",
    "req_data": {
      "id": 5,
      "advance_type_id": 1,
      "advance_type_name": "Activity",
      "advance_type_code": "Act",
      "activity_name_id": 3,
      "activity_name": "Field Demo",
      "month": 4,
      "submission_no": 1,
      "current_status": "Submitted",
      "last_submitted_at": "2024-04-20T10:00:00",
      "approval_summary": [
        {
          "level": 1,
          "total_approvals": 1,
          "approved_count": 0,
          "pending_count": 1,
          "status": "Pending"
        }
      ]
    },
    "activities": [
      {
        "id": 20,
        "activity_type_id": 2,
        "activity_type_name": "Field Visit",
        "crop_names": ["Maize"],
        "variety_names": ["VNR 3421"],
        "amount": 5000,
        "purpose": "Demo at Nagpur",
        "current_approval_level": 1,
        "approval_status": "Pending",
        "approvals": [
          {
            "id": 30,
            "approval_level": 1,
            "approver_id": 789,
            "approver_name": "Manager Name",
            "approver_status": "Pending",
            "approver_amount": null,
            "approver_date": null
          }
        ],
        "finance_status": "Pending",
        "payment_status": null
      }
    ]
  }
]
```

---

#### Get Approved (by me)

`GET /advance/approvals/approved?user_id={employeeId}`

Returns requests this user has approved. Each employee object includes:
- `approved_at_level` — approval level where action was taken
- `approved_date` — date of approval
- `total_approved_amount`

---

#### Get Rejected (by me)

`GET /advance/approvals/rejected?user_id={employeeId}`

Returns requests this user has rejected. Each activity includes `rejection_reason`.

---

#### Requests Submitted For Me (as employee)

`GET /advance/submitted-for-me?user_id={employeeId}`

Shows all requests where the caller is listed as an employee (not the creator). Includes status tracking: pending/approved/rejected.

---

#### Requests Rejected For Me (as employee)

`GET /advance/rejected-for-me?user_id={employeeId}`

---

#### Employee Approval Summary

`GET /advance/employee-approval-summary/{requestId}?user_id={employeeId}`

Returns per-employee approval level status for a specific request.

---

#### Approve Request

`POST /advance/approve?user_id={employeeId}`  
**Content-Type:** `application/json`

**Request Body:**
```json
{
  "employee_id": 456,
  "request_id": 5,
  "approver_amount": 4500,
  "approver_remark": "Approved with reduced amount"
}
```

> `approver_amount` cannot exceed the total requested amount. If multiple activities exist, amount is distributed proportionally.

**Response:** `{ "success": true, "message": "Employee activities approved successfully" }`

---

#### Reject Request

`POST /advance/reject?user_id={employeeId}`

**Request Body:**
```json
{
  "employee_id": 456,
  "request_id": 5,
  "remarks": "Activity not aligned with plan"
}
```

---

#### Revert Request (Send Back)

`POST /advance/revert?user_id={employeeId}`

**Request Body:**
```json
{
  "employee_id": 456,
  "request_id": 5,
  "remarks": "Please revise the amount"
}
```

---

### 9.3 Advance Finance

Finance team endpoints for processing approved requests.

#### Pending Finance Requests

`GET /advance/finance/pending?user_id={employeeId}&year_id={yearId}`

Returns requests with `approval_status=Approved` and `finance_status=Pending`.  
Each employee includes `ledger_balance` and `expense_submissions` arrays.

---

#### Finance Approved Requests

`GET /advance/finance/approved?user_id={employeeId}&year_id={yearId}`

---

#### Finance Rejected Requests

`GET /advance/finance/rejected?user_id={employeeId}&year_id={yearId}`

---

#### Approve by Finance

`POST /advance/finance/approve?user_id={employeeId}`

**Request Body:**
```json
{
  "employee_id": 456,
  "request_id": 5,
  "activity_id": 20,
  "finance_approved_amount": 4500,
  "finance_remark": "Approved"
}
```

`activity_id` is optional — if omitted, all activities for the employee are approved.

---

#### Reject by Finance

`POST /advance/finance/reject?user_id={employeeId}`

```json
{
  "employee_id": 456,
  "request_id": 5,
  "activity_id": 20,
  "remarks": "Duplicate request"
}
```

---

#### Revert by Finance

`POST /advance/finance/revert?user_id={employeeId}`

```json
{
  "employee_id": 456,
  "request_id": 5,
  "remarks": "Please provide invoice"
}
```

---

#### Release Payment

`POST /advance/finance/release-payment?user_id={employeeId}`

**Request Body:**
```json
{
  "selected_requests": [
    {
      "id": 10,
      "employee_id": 456,
      "advance_request_id": 5,
      "finance_approved_amount": 4500
    }
  ]
}
```

Creates a payment batch (`PAY_BATCH_YYYYMMDD_NNN`), marks activities as `payment_status = ReadyForPayment`.

**Response:**
```json
{
  "success": true,
  "data": {
    "released_count": 1,
    "total_amount": 4500,
    "payment_batch_id": "PAY_BATCH_20240501_001",
    "released_requests": [...]
  }
}
```

---

#### Get Employee Balance

`GET /advance/finance/employee-balance?employee_id={id}&company_id={comId}`

Returns current ledger balance with imprest slots, paid advances, and carried advances.

**Response:**
```json
{
  "success": true,
  "data": {
    "employee_id": 456,
    "total_imprest": 15000,
    "advance_balance": 5000,
    "total_balance": 20000,
    "summary": {
      "total_paid_advances": 8000,
      "total_carried": 3000,
      "total_ledger_advances": 5000
    },
    "imprests": [...],
    "ledger_advances": [...],
    "paid_advances": [...],
    "carried_advances": [...]
  }
}
```

---

#### Confirm Advance Payments (Match with Bank)

`POST /advance/finance/confirm-payments`

Matches `ReadyForPayment` activities against `digi_next_payment_file` records using account number + amount. Updates matched activities to `payment_status = Paid`.

**Body:** `{ "yearId": 7, "confirmed_by": 789 }`

---

#### Confirm Expense Payments

`POST /advance/finance/confirm-expense-payments`

Same logic for expense submissions in `y{yearId}_monthexpensefinal` table.

---

#### Released Payments List

`GET /advance/finance/released-payments?year_id=&batch_id=&employee_id=&status=ReadyForPayment&limit=50&page=1`

---

#### Confirmed Payments List

`GET /advance/finance/confirmed-payments?year_id=&batch_id=&employee_id=&limit=50&page=1`

---

#### Unsettled Payments

`GET /advance/finance/unsettled-payments?include_resolved=false`

---

#### Settle Unsettled Payment (Manual)

`POST /advance/finance/settle-unsettled-payment`

```json
{
  "activity_id": 20,
  "payment_amount": 4500,
  "payment_date": "2024-05-15",
  "utr_number": "UTR12345",
  "payment_remark": "Manually settled"
}
```

---

#### Resolve Discrepancy

`POST /advance/finance/resolve-discrepancy?user_id={employeeId}`

```json
{
  "activity_id": 20,
  "payment_amount": 4500,
  "payment_date": "2024-05-15",
  "utr_reference": "UTR12345",
  "bank_reference": "REF789"
}
```

---

#### Export Payments (Excel)

`GET /advance/finance/export-payments?user_id={id}&format=excel&year_id=&batch_id=&include_exported=false`

Returns Excel file download (`.xlsx`) when `format=excel`.  
Without `format=excel`, returns count preview:
```json
{ "success": true, "data": { "activity_count": 5, "total_amount": 22500 } }
```

---

#### Payment Batches

`GET /advance/finance/payment-batches?limit=20&page=1&status=`

---

#### Focus Export (ERP Integration)

`GET /advance/finance/focus-export-ready?year_id=`  
`POST /advance/finance/mark-exported` — `{ activity_ids: [], focus_batch_id: "FB001", remarks: "" }`  
`POST /advance/finance/mark-imported` — `{ focus_batch_id: "FB001", remarks: "" }`

---

#### Force Resync Payments

`POST /advance/finance/force-resync-payments?user_id={id}`

Resets all `PAID` activities back to `ReadyForPayment` and all `MATCHED` payment records back to `PENDING` for re-matching.

---

### 9.4 HOD Advance Report

Used by the `HODAdvanceReportScreen` in Flutter.

#### Get Employee Access Level

`GET /advance/hod/employee-level?employee_id={id}`

**Response:**
```json
{
  "success": true,
  "data": {
    "employee_id": 1225,
    "employee_name": "Rajesh Kumar",
    "employee_code": "EMP1225",
    "access_level": "zone",
    "level_description": "Zone Level"
  }
}
```

**Access levels:** `all`, `bu`, `zone`, `region`, `territory`, `none`

---

#### Get Team Members List

`GET /advance/hod/team?employee_id={id}`

Returns all employees recursively below the given manager who have advance requests.

**Response:**
```json
{
  "success": true,
  "data": {
    "access_level": "zone",
    "team_members": [
      {
        "EmployeeID": 456,
        "EmployeeName": "Jane Smith",
        "EmployeeCode": "EMP456",
        "DepartmentName": "Sales",
        "Designation": "TM",
        "BUId": 2, "BUName": "Seeds BU",
        "ZoneId": 5, "ZoneName": "Central Zone",
        "RegionId": 10, "RegionName": "Vidarbha",
        "TerrId": 20, "TerritoryName": "Nagpur"
      }
    ]
  }
}
```

---

#### Consolidated Report

`GET /advance/hod/consolidated-report?employee_id={id}&year_id=7&month=4&specific_employee_id=&bu_id=&zone_id=&region_id=&terr_id=`

Returns hierarchical paid advance data: Activity → Month → Submitted By → Employee → Requests → Activities.

**Response:**
```json
{
  "success": true,
  "access_level": "zone",
  "grand_total": 125000,
  "data": {
    "3": {
      "activity_name": "Field Demo",
      "grand_total": 125000,
      "months": {
        "Apr": {
          "month_total": 45000,
          "submitted_by": [
            {
              "submitted_by_id": 789,
              "submitted_by_name": "John Manager",
              "submitted_by_total": 45000,
              "employees": [
                {
                  "employee_id": 456,
                  "employee_name": "Jane Smith",
                  "employee_total": 45000,
                  "hierarchy": { "business_unit": {...}, "zone": {...} },
                  "advance_requests": [
                    {
                      "request_id": 5,
                      "status": "Closed",
                      "total_amount": 45000,
                      "activities": [
                        {
                          "activity_type": "Field Visit",
                          "crops": ["Maize"],
                          "amount": 45000,
                          "payment": { "amount": 45000, "status": "Paid", "date": "2024-05-10" },
                          "finance": { "approved_amount": 45000, "status": "Approved" },
                          "approvals": [{ "approval_level": 1, "approver_status": "Approved" }]
                        }
                      ]
                    }
                  ]
                }
              ]
            }
          ]
        }
      }
    }
  }
}
```

---

#### Hierarchy Filter APIs (HOD)

| Endpoint | Params | Description |
|---|---|---|
| `GET /advance/hod/business-units` | `employee_id`, `search`, `limit=10` | Business units for filter dropdown |
| `GET /advance/hod/zones` | `employee_id`, `business_unit_id`, `search`, `limit=10` | Zones under a BU |
| `GET /advance/hod/regions` | `employee_id`, `zone_id`, `business_unit_id`, `search`, `limit=10` | Regions under a zone |
| `GET /advance/hod/territories` | `employee_id`, `region_id`, `search`, `limit=10` | Territories under a region |

---

## 10. New Laravel REST API — Odometer Backdate

Base URL: `https://expensenew.vnrseeds.co.in/api/`  
Route file: `routes/api.php`

### 10.1 Check Button Visibility

`GET /odo-backdate-submission-show/{yearId}/{employeeId}`  
**Used by:** `vnr_approver_screen.dart` → `fetchPendingCount()`

**Response:**
```json
{
  "success": true,
  "data": {
    "pending_count": 3,
    "show_button": true
  }
}
```

---

### 10.2 Get Backdate Submission List

`GET /odo-backdate-submission/{yearId}/{employeeId}`

Returns all employees' backdated odometer entries grouped by employee with status counts (Pending/Approved/Rejected). Checks if caller is a BU GM or HOD.

**Response:**
```json
{
  "success": true,
  "data": {
    "456": {
      "approval_type": "hod_level",
      "employee_name": "Jane Smith",
      "EmpCode": "EMP456",
      "total_records": 2,
      "status_count": { "Pending": 1, "Approved": 1, "Rejected": 0 },
      "items": [
        {
          "ExpId": 101,
          "BillDate": "2024-04-10",
          "CrDate": "2024-04-15",
          "odomtr_opening": 12500,
          "odomtr_closing": 12750,
          "TotKm": 250,
          "RatePerKM": 7.5,
          "days_difference": 5,
          "Backdate_Odometer_Status": "Pending",
          "opening_filepath": "https://s3.../opening.jpg",
          "closing_filepath": "https://s3.../closing.jpg"
        }
      ]
    }
  }
}
```

---

### 10.3 Approve Single Record

`GET /odo-backdate-approval/{yearId}/{employeeId}/{expenseId}`

Sets `Backdate_Odometer_Status = A`, `ClaimStatus = Filled`, `ClaimAtStep = 1`.

---

### 10.4 Reject Single Record

`GET /odo-backdate-rejection/{yearId}/{employeeId}/{expenseId}`

Sets `Backdate_Odometer_Status = R`.

---

### 10.5 Bulk Approve All for Employee

`GET /odo-backdate-bulk-approval/{yearId}/{employeeId}/{empid}`

`employeeId` = approver, `empid` = employee whose records to approve.

**Response:** `{ "success": true, "data": { "updated_count": 3 } }`

---

### 10.6 Bulk Reject All for Employee

`GET /odo-backdate-bulk-rejection/{yearId}/{employeeId}/{empid}`

---

## 11. New Laravel REST API — Payment Import

Base URL: `https://expensenew.vnrseeds.co.in/api/`  
Route prefix: `/payments/`

### 11.1 Import Payment File

`POST /payments/import`  
**Content-Type:** `multipart/form-data`

| Field | Type | Required | Description |
|---|---|---|---|
| `payment_file` | File | Yes | XLSX/XLS/CSV, max 10MB |
| `yearId` | Integer | Yes | Financial year ID |
| `source_app` | String | No | Source application name |
| `confirmed_by` | Integer | No | User ID for auto-confirmation |

> File format: Rows 1–5 are skipped. Row 6 = header. Column A is skipped. Headers are fuzzy-matched against DB column names (≥95% similarity threshold).

**Response:**
```json
{
  "success": true,
  "data": {
    "total_rows": 50,
    "inserted_rows": 48,
    "skipped_rows": 2,
    "failed_rows": [],
    "import_batch_id": "uuid-...",
    "source_app": "API",
    "auto_confirm_result": {
      "success": true,
      "data": {
        "summary": {
          "total_processed": 45,
          "total_matched": 43,
          "success_rate": "95.56%"
        }
      }
    }
  }
}
```

---

### 11.2 List Payments

`GET /payments/list`

**Query Params:**

| Param | Description |
|---|---|
| `per_page` | Records per page (default: 25) |
| `page` | Page number |
| `search` | Search in transaction_refno, beneficiary_name, account_no |
| `date_from` / `date_to` | Value date range (YYYY-MM-DD) |
| `import_date_from` / `import_date_to` | Import date range |
| `match_status` | MATCHED / UNMATCHED / PENDING |
| `batch_id` | Import batch UUID |
| `payment_type` | Payment type filter |
| `bank_name` | Bank name filter |
| `min_amount` / `max_amount` | Amount range |
| `application` | Application name |
| `sort_by` | Column to sort by (default: created_at) |
| `sort_order` | asc / desc (default: desc) |

---

### 11.3 Payment Details

`GET /payments/details/{id}`

---

### 11.4 Import Status by Batch

`GET /payments/status/{batchId}`

Returns breakdown of a batch: total records, matched/unmatched/pending counts, success rates.

---

### 11.5 List Import Batches

`GET /payments/batches?per_page=25&page=1&sort_by=created_at&sort_order=desc`

---

### 11.6 Delete Payment Record

`DELETE /payments/delete/{id}`

---

### 11.7 Export Payments as CSV

`GET /payments/export?batch_id=&date_from=&date_to=&match_status=`

Returns streaming CSV file download.

---

### 11.8 List Uploaded Files

`GET /payments/uploaded-files?per_page=25&page=1&date_from=&date_to=&search=`

---

## 12. New Laravel REST API — Employee Ledger

`GET /employee-ledger/data`  
`DELETE /employee-ledger/{id}`

Used internally by the web admin for ledger balance management.

---

## 13. New Laravel REST API — Hierarchy

Base URL: `https://expensenew.vnrseeds.co.in/api/`  
**Controller:** `Api/HierarchyController.php`

| Method | Endpoint | Params | Description |
|---|---|---|---|
| GET | `/hierarchy/business-units` | — | All active BUs |
| GET | `/hierarchy/zones` | `bu_id` | Zones, optionally filtered by BU |
| GET | `/hierarchy/regions` | `zone_id` | Regions, optionally filtered by zone |
| GET | `/hierarchy/territories` | `region_id` | Territories, optionally filtered by region |
| GET | `/hierarchy/bu-zone-mappings` | `bu_id` | BU-Zone mapping table |
| GET | `/hierarchy/zone-region-mappings` | `zone_id` | Zone-Region mapping table |
| GET | `/hierarchy/territory-mappings` | `region_id` | Territory mapping table |
| GET | `/hierarchy/tree` | `bu_id`, `zone_id`, `region_id` | Full nested hierarchy tree |

---

## 14. Standard Response Formats

### Success (New REST API)
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Error (New REST API)
```json
{
  "success": false,
  "message": "Error description",
  "errors": { "field": ["validation message"] }
}
```

### Legacy PHP API Success
```json
{ "Code": "300", ... }
```

### Legacy PHP API Error
```json
{ "Code": "400", "Msg": "Error message" }
```

---

## 15. Error Codes

### HTTP Status Codes

| Code | Meaning |
|---|---|
| 200 | Success |
| 400 | Bad request / validation error |
| 404 | Resource not found |
| 422 | Validation failed (Laravel) |
| 500 | Server error |

### Legacy PHP Codes

| Code | Meaning |
|---|---|
| 300 | Success |
| 400 | Error / Invalid credentials |

### Advance Request Status Flow

```
Draft → Submitted → In_Approval → Finance → Closed
                 ↘ Rejected
                 ↘ Reverted → Submitted (resubmit)
                                         ↘ Finance (auto-approve)
```

### Advance Activity Status Values

| Status | Description |
|---|---|
| `Pending` | Awaiting approval/finance action |
| `Approved` | Approved at current level |
| `Rejected` | Rejected |
| `Reverted` | Sent back for correction |

### Payment Status Values

| Status | Description |
|---|---|
| `Pending` | Not yet released |
| `ReadyForPayment` | Released by finance, awaiting bank processing |
| `Paid` | Confirmed paid (matched with bank file) |
| `Unsettled` | Could not be matched with bank file |
| `Released` | Intermediate payment released state |

---

## 16. Data Models

### Advance Request Object
```json
{
  "id": 5,
  "advance_type_id": 1,
  "advance_type_name": "Activity",
  "advance_type_code": "Act",
  "activity_name_id": 3,
  "activity_name": "Field Demo",
  "month": 4,
  "req_app_submit_id": 1234,
  "submission_no": 1,
  "last_submitted_at": "2024-04-20T10:00:00Z",
  "advance_fill_date": "2024-04-19",
  "current_status": "In_Approval",
  "reverted_from_status": null,
  "revert_reason": null,
  "reverted_by": null,
  "reverted_at": null,
  "created_by": 1234,
  "created_at": "2024-04-19T08:00:00Z",
  "updated_at": "2024-04-20T10:00:00Z"
}
```

### Advance Activity Object
```json
{
  "id": 20,
  "advance_request_employee_id": 10,
  "activity_type_id": 2,
  "activity_type_name": "Field Visit",
  "activity_crops_id": [1, 3],
  "crop_names": ["Maize", "Wheat"],
  "activity_variety_id": [7],
  "variety_names": ["VNR 3421"],
  "amount": 5000,
  "purpose": "Seed distribution demo",
  "is_tour_travel": "N",
  "current_approval_level": 1,
  "approval_status": "Pending",
  "finance_id": null,
  "finance_approved_amount": null,
  "finance_status": "Pending",
  "finance_remark": null,
  "finance_date": null,
  "payment_id": null,
  "payment_amount": null,
  "payment_status": null,
  "payment_remark": null,
  "payment_date": null,
  "payment_batch_id": null,
  "approvals": [...],
  "created_at": "...",
  "updated_at": "..."
}
```

### Approval Object
```json
{
  "id": 30,
  "approval_level": 1,
  "approver_id": 789,
  "approver_name": "Manager Name",
  "approver_email": "manager@vnrseeds.com",
  "approver_designation": "RSM",
  "approver_status": "Approved",
  "approver_amount": 4500,
  "approver_remark": "OK",
  "approver_date": "2024-04-21",
  "reverted_by": null,
  "reverted_at": null,
  "revert_reason": null
}
```

### Payment File Object (DigiNext)
```json
{
  "id": 100,
  "record_no": "1",
  "beneficiary_account_number": "123456789012",
  "transaction_refno": "BT26013049926940",
  "bank_name": "HDFC Bank",
  "ifsc_code": "HDFC0001234",
  "debit_account_number": "999900001234",
  "value_date": "2024-05-10",
  "amount": 4500.00,
  "beneficiary_name": "Jane Smith",
  "payment_type": "NEFT",
  "rbi_utr_reference_number": "HDFC240510123456",
  "currency": "INR",
  "transaction_status": "SUCCESS",
  "import_date": "2024-05-11",
  "import_batch_id": "uuid-...",
  "is_processed": true,
  "match_status": "MATCHED",
  "matched_activity_id": 20,
  "processed_at": "2024-05-11T09:00:00Z",
  "processed_by": 789
}
```

---

## 17. Flutter Files & Their API Calls (Quick Reference)

| Flutter File | API Calls |
|---|---|
| `lib/utils/Config.dart` | Defines all base URLs |
| `lib/screens/login_screen.dart` | `login_new.php`, `common.php?yearlist` |
| `lib/screens/claimant_screen.dart` | `common.php?Openlist`, `Closelist`, `Draftlist`, `Openlist_Day`; `claim.php?Claim_Deleted`, `Claim_Submit`; `extract-data.php?show_activity_tab`; `appversion.php` |
| `lib/screens/upload_image_screen.dart` | `extract-data.php?claim_list`, `?extract_data`; `claim.php?upload_bill` |
| `lib/screens/odometer_screen.dart` | `claim.php?uploadimg_odo_opening`, `?uploadimg_odo_closing`, `?Current_date_odo_details` |
| `lib/screens/da_screen.dart` | `common.php?DA_ClaimType`; `claim.php?DA_Update` |
| `lib/screens/activity_screen.dart` | `claim.php?upload_activity_bill` |
| `lib/screens/running_km_screen.dart` | `common.php?VehicleRunning` |
| `lib/screens/vnr_approver_screen.dart` | `common.php?TeamMemberAppraiser`, `?show_hide_pending`; `advance_request.php?show_approver_tab`; `NewXeasyMainUrl/odo-backdate-submission-show` |
| `lib/screens/vvnr_approver_screen.dart` | `VVNRCommonUrl?TeamMemberAppraiser`, `?show_hide_pending` |
| `lib/screens/all_team_expense_screen.dart` | `common.php?AllTeamExp`, `?TeamMemberExpenses` etc. |
| `lib/screens/approval_advance_request_list.dart` | `NewXeasyMainUrl/advance/approvals/pending`, `/approved`, `/rejected`; `/advance/approve`, `/advance/reject` |
| `lib/screens/hod_advance_report_page.dart` | `NewXeasyMainUrl/advance/hod/employee-level`, `/team`, `/business-units`, `/zones`, `/regions`, `/territories`, `/consolidated-report` |
| `lib/components/vnr_pending_claims_api_call.dart` | `common.php?Team_Pending_Claimlist`, `?Team_Pending_Claimlist_Day` |
| `lib/components/vnr_approved_claims_api_call.dart` | `common.php?Team_Approved_Claimlist`, `?ApprovedClaimsByMonth/Mem`, `?Team_Approved_Claimlist_Day` |
| `lib/components/vvnr_pending_claims_api_call.dart` | `VVNRCommonUrl?Team_Pending_Claimlist`, `?Team_Pending_Claimlist_Day` |
| `lib/components/vvnr_approved_claims_api_call.dart` | `VVNRCommonUrl?Team_Approved_Claimlist`, `?ApprovedClaimsByMonth/Mem` |
| `lib/components/nursery_pending_claims_api_call.dart` | `MainUrl/3/common.php?Team_Pending_Claimlist` |
| `lib/components/nursery_approved_claims_api_call.dart` | `MainUrl/3/common.php?Team_Approved_Claimlist`, `?ApprovedClaimsByMonth/Mem` |

---

## Appendix A: Laravel Route File Locations

| File | Prefix | Description |
|---|---|---|
| `routes/web.php` | `/` | Web session-based routes |
| `routes/api.php` | `/api/` | Main API routes (odometer, payments, ledger) |
| `routes/advance.php` | `/advance/` | Advance module REST routes |
| `routes/backend.php` | `/` | Admin panel routes |

## Appendix B: Laravel API Controller Locations

| Controller | Namespace | Description |
|---|---|---|
| `AuthController` | `App\Http\Controllers` | Login, logout, decrypt password |
| `OdometerBackdateController` | `App\Http\Controllers\Api` | Backdate odometer approval |
| `PaymentApiController` | `App\Http\Controllers\Api` | DigiNext payment file management |
| `HierarchyController` | `App\Http\Controllers\Api` | BU/Zone/Region/Territory lookups |
| `AdvanceRequestController` | `App\Http\Controllers\Api\Advance` | Advance request CRUD |
| `AdvanceApprovalController` | `App\Http\Controllers\Api\Advance` | Advance approval workflow |
| `AdvanceFinanceController` | `App\Http\Controllers\Api\Advance` | Finance processing & payment |
| `HodAdvanceReportController` | `App\Http\Controllers\Api\Advance` | HOD team advance reports |

---

*Documentation generated from source code analysis — June 2026*
