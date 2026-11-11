# VVNRApproverController

## Overview
The VVNRApproverController handles team expense approval workflows for VVNR (VNR ESS) system. It provides similar functionality to VNRApproverController but connects to a different API endpoint (`vnress.in` instead of `vnrseeds.co.in`). This controller manages pending and approved claims, team member expenses, and approval workflows for team leaders.

## Namespace
`App\Http\Controllers\admin`

## Routes

### VVNR Approver Routes (Prefix: `/vvnr`, Name: `vvnr.`)
- **GET** `/vvnr/approver` - Main approver dashboard
- **GET** `/vvnr/approver/pending/list` - Get pending claims list (`vvnr.approver.pending.list`)
- **GET** `/vvnr/approver/pending/day` - Get pending claims by day (`vvnr.approver.pending.day`)
- **GET** `/vvnr/approver/approved/list` - Get approved claims list (`vvnr.approver.approved.list`)
- **GET** `/vvnr/approver/approved/day` - Get approved claims by day (`vvnr.approver.approved.day`)
- **POST** `/vvnr/approver/claim/approve` - Approve claim (`vvnr.approver.claim.approve`)
- **POST** `/vvnr/approver/claim/submit` - Submit claim (`vvnr.approver.claim.submit`)
- **GET** `/vvnr/team/members/list` - Get team members list (`vvnr.team.members.list`)
- **GET** `/vvnr/team/all/expenses` - Get all team expenses (`vvnr.team.all.expenses`)
- **GET** `/vvnr/team/member/expenses` - Get member expenses (`vvnr.team.member.expenses`)
- **GET** `/vvnr/team/member/expenses/by-claim` - Get member expenses by claim (`vvnr.team.member.expenses.by.claim`)

## Dependencies
- `Illuminate\Http\Request`
- `Illuminate\Support\Facades\Http`

## Methods

### getApproverPendingList()
**Purpose**: Retrieves the list of pending claims for the current approver.

**Parameters**: None (uses session data)

**Returns**: JSON response with pending claims list

**Session Variables Used**:
- `employee_id`: Current approver's employee ID
- `year_id`: Financial year ID
- `company_id`: Company identifier
- `token_id`: Authentication token

**API Endpoint**: `Team_Pending_Claimlist` (via `common_rep.php`)

### getApproverPendingDay(Request $request)
**Purpose**: Retrieves pending claims for a specific day/month.

**Parameters**:
- `empid`: Employee ID
- `month`: Month number
- `yearid`: Year ID
- `comid`: Company ID

**Returns**: JSON response with day-specific pending claims

**Features**:
- Extended execution time (300 seconds)
- Timeout handling (180s timeout, 30s connection timeout)

**API Endpoint**: `Team_Pending_Claimlist_Day` (via `common_rep.php`)

### getApproverApprovedList()
**Purpose**: Retrieves the list of approved claims for the current approver.

**Parameters**: None (uses session data)

**Returns**: JSON response with approved claims list

**API Endpoint**: `Team_Approved_Claimlist` (via `common_rep.php`)

### getApproverApprovedDay(Request $request)
**Purpose**: Retrieves approved claims for a specific day/month.

**Parameters**:
- `empid`: Employee ID
- `month`: Month number
- `yearid`: Year ID
- `comid`: Company ID

**Returns**: JSON response with day-specific approved claims

**API Endpoint**: `Team_Approved_Claimlist_Day` (via `common_rep.php`)

### approveClaimByApprover(Request $request)
**Purpose**: Approves a specific claim by an approver.

**Parameters**:
- `exp_id` (required): Expense ID
- `employee_id` (required): Employee ID
- `amount` (required, numeric, min:0): Claim amount
- `month` (required, integer): Month
- `remark` (optional): Approval remarks

**Returns**: JSON response with approval status

**Validation Rules**:
- `exp_id`: Required
- `employee_id`: Required
- `amount`: Required, numeric, minimum 0
- `month`: Required, integer

**API Endpoint**: `Claim_approved` (via `claim.php`)

### getTeamMembersList()
**Purpose**: Retrieves the list of team members under the current approver.

**Parameters**: None (uses session data)

**Returns**: JSON response with team members list

**API Endpoint**: `TeamMember` (via `common_rep.php`)

### getAllTeamExpenses()
**Purpose**: Retrieves all expenses for the entire team.

**Parameters**: None (uses session data)

**Returns**: JSON response with all team expenses

**API Endpoint**: `AllTeamExp` (via `common_rep.php`)

### getMemberExpenses(Request $request)
**Purpose**: Retrieves expenses for a specific team member and claim.

**Parameters**:
- `member_id`: Team member ID
- `claim_id`: Claim ID

**Returns**: JSON response with member-specific expenses

**API Endpoint**: `TeamMemberExpensesById` (via `common_rep.php`)

### getMemberExpensesByClaim(Request $request)
**Purpose**: Retrieves expenses for a specific member filtered by claim ID.

**Parameters**:
- `member_id`: Team member ID
- `claim_id`: Claim ID

**Returns**: JSON response with filtered member expenses

**API Endpoint**: `TeamExpensesByIdAndMember` (via `common_rep.php`)

### submitClaimByApprover(Request $request)
**Purpose**: Submits a claim on behalf of a team member.

**Parameters**:
- `emp_id` (required): Employee ID
- `month` (required, integer, 1-12): Month number

**Returns**: JSON response with submission status

**Validation Rules**:
- `emp_id`: Required
- `month`: Required, integer, between 1-12

**API Endpoint**: `Reporting_Claim_Submit` (via `claim.php`)

## Private Methods

### getApproverPendingCount($employeeId, $yearId, $companyId, $tokenId)
**Purpose**: Gets the count of pending claims for an approver.

**Parameters**:
- `$employeeId`: Employee ID
- `$yearId`: Year ID
- `$companyId`: Company ID
- `$tokenId`: Authentication token

**Returns**: Integer count of pending claims

### getApproverApprovedCount($employeeId, $yearId, $companyId, $tokenId)
**Purpose**: Gets the count of approved claims for an approver.

**Parameters**:
- `$employeeId`: Employee ID
- `$yearId`: Year ID
- `$companyId`: Company ID
- `$tokenId`: Authentication token

**Returns**: Integer count of approved claims

## Key Differences from VNRApproverController
- **API Base URL**: Uses `https://expense.vnress.in/` instead of `https://expense.vnrseeds.co.in/`
- **API Scripts**: Uses `common_rep.php` instead of `common.php` for most endpoints
- **Target System**: Designed for VVNR (VNR ESS) system rather than VNR Seeds

## Error Handling
- All API calls are wrapped in try-catch blocks
- Returns standardized error responses with appropriate HTTP status codes
- Validation errors return detailed error messages
- API timeouts and connection issues are handled gracefully

## Security Features
- SSL verification disabled for API calls (`verify => false`)
- Session-based authentication
- Input validation for all user inputs
- URL encoding for remarks to prevent injection

## API Integration
- Integrates with VNR ESS expense API (`https://expense.vnress.in/api/`)
- Uses HTTP facade for external API communication
- Handles API timeouts and connection issues
- Standardized error response format

## Session Dependencies
All methods rely on session variables:
- `employee_id`: Current user's employee ID
- `year_id`: Active financial year
- `company_id`: Current company context
- `token_id`: Authentication token (with fallback default)