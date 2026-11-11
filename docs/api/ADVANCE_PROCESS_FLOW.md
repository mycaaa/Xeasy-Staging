# Advance Request Process Flow

## Overview
This document outlines the advance request process and the database columns that are updated at each stage.

---

## Process Stages

### 1. Draft Creation
**Status**: `Draft`

#### advance_requests table
| Column | Value/Update |
|--------|--------------|
| `current_status` | `'Draft'` |
| `submission_no` | `1` |
| `advance_fill_date` | Current date |
| `created_at` | Timestamp |
| `updated_at` | Timestamp |

#### advance_request_employees table
| Column | Value/Update |
|--------|--------------|
| `advance_request_id` | Request ID |
| `employee_id` | Employee code |
| `created_at` | Timestamp |
| `updated_at` | Timestamp |

#### advance_request_activities table
| Column | Value/Update |
|--------|--------------|
| `advance_request_employee_id` | Employee record ID |
| `activity_type_id` | Activity type |
| `amount` | Requested amount |
| `approval_status` | `'Pending'` |
| `finance_status` | `'Pending'` |
| `payment_status` | `'Pending'` |
| `final_status` | `'Pending'` |
| `current_approval_level` | `1` |
| `created_at` | Timestamp |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `advance_request_id` | Request ID |
| `action_type` | `'Draft_Save'` |
| `action_by` | User ID |
| `action_at` | Timestamp |
| `submission_no` | `1` |

---

### 2. Submission
**Status**: `Submitted` → `In_Approval`

#### advance_requests table
| Column | Value/Update |
|--------|--------------|
| `current_status` | `'Submitted'` → `'In_Approval'` |
| `last_submitted_at` | Current timestamp |
| `updated_at` | Timestamp |

#### advance_activity_approvals table (Created for each approver)
| Column | Value/Update |
|--------|--------------|
| `advance_request_activity_id` | Activity ID |
| `approval_level` | Approver level (1, 2, 3...) |
| `approver_id` | Approver user ID |
| `approver_status` | `'Pending'` |
| `submission_no` | Current submission number |
| `created_at` | Timestamp |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Submit'` or `'Resubmit'` |
| `action_by` | User ID |
| `action_to` | First approver ID |
| `action_at` | Timestamp |
| `submission_no` | Current submission number |

---

### 3. Approval Process
**Status**: `In_Approval`

#### advance_activity_approvals table (Per approver action)
| Column | Value/Update |
|--------|--------------|
| `approver_status` | `'Approved'` / `'Rejected'` / `'Reverted'` |
| `approver_amount` | Approved amount (if different) |
| `approver_remark` | Approver comments |
| `approver_date` | Action date |
| `updated_at` | Timestamp |

#### advance_request_activities table (After all approvers at level approve)
| Column | Value/Update |
|--------|--------------|
| `approval_status` | `'Approved'` (when all levels approve) |
| `current_approval_level` | Incremented to next level |
| `updated_at` | Timestamp |

#### advance_requests table (After all activities approved)
| Column | Value/Update |
|--------|--------------|
| `current_status` | `'Approved'` → `'Finance'` |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Approve'` |
| `action_by` | Approver user ID |
| `action_to` | Next approver or finance user |
| `action_at` | Timestamp |
| `related_activity_id` | Activity ID (if specific) |

---

### 4. Rejection (During Approval)
**Status**: `In_Approval` → `Rejected`

#### advance_activity_approvals table
| Column | Value/Update |
|--------|--------------|
| `approver_status` | `'Rejected'` |
| `approver_remark` | Rejection reason |
| `approver_date` | Action date |
| `updated_at` | Timestamp |

#### advance_request_activities table
| Column | Value/Update |
|--------|--------------|
| `approval_status` | `'Rejected'` |
| `updated_at` | Timestamp |

#### advance_requests table
| Column | Value/Update |
|--------|--------------|
| `current_status` | `'Rejected'` |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Reject'` |
| `action_by` | Approver user ID |
| `action_at` | Timestamp |
| `remarks` | Rejection reason |


### 6. Finance Approval
**Status**: `Finance`

#### advance_request_activities table
| Column | Value/Update |
|--------|--------------|
| `finance_status` | `'Approved'` / `'Rejected'` |
| `finance_approved_amount` | Final approved amount |
| `finance_remark` | Finance comments |
| `finance_id` | Finance user ID |
| `finance_date` | Action date |
| `updated_at` | Timestamp |

#### advance_requests table (After all activities processed)
| Column | Value/Update |
|--------|--------------|
| `current_status` | `'Finance_Approved'` / `'Finance_Rejected'` / `'Closed'` |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Finance_Approve'` / `'Finance_Reject'` |
| `action_by` | Finance user ID |
| `action_at` | Timestamp |
| `related_employee_id` | Employee ID |
| `related_activity_id` | Activity ID (if specific) |

---

### 8. Payment Release (Export)
**Status**: `Finance_Approved` → `Payment`

#### advance_request_activities table
| Column | Value/Update |
|--------|--------------|
| `is_exported` | `true` |
| `first_export_date` | Date (if first time) |
| `last_export_date` | Current date |
| `export_count` | Incremented |
| `exported_by` | User ID |
| `payment_status` | `'Ready for Payment'` |
| `updated_at` | Timestamp |

#### advance_export_logs table (Created for each export)
| Column | Value/Update |
|--------|--------------|
| `activity_id` | Activity ID |
| `advance_request_id` | Request ID |
| `employee_id` | Employee code |
| `exported_amount` | Finance approved amount |
| `exported_by` | User ID |
| `exported_at` | Timestamp |
| `export_type` | Export type |

#### advance_requests table
| Column | Value/Update |
|--------|--------------|
| `current_status` | `'Payment'` |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Payment_Release'` |
| `action_by` | User ID |
| `action_at` | Timestamp |

---

### 9. Payment Confirmation
**Status**: `Payment`

#### advance_request_activities table
| Column | Value/Update |
|--------|--------------|
| `payment_status` | `'Released'` → `'Paid'` |
| `payment_amount` | Actual paid amount |
| `payment_id` | Payment processor ID |
| `payment_date` | Payment date |
| `payment_reference` | Payment reference number |
| `payment_remark` | Payment comments |
| `updated_at` | Timestamp |

#### advance_requests table (After all activities paid)
| Column | Value/Update |
|--------|--------------|
| `current_status` | `'Closed'` (if all paid) |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Payment_Released'` |
| `action_by` | Payment processor ID |
| `action_at` | Timestamp |

---

### 10. Focus Export
**Status**: `Closed`

#### advance_request_activities table
| Column | Value/Update |
|--------|--------------|
| `final_status` | `'Exported to Focus'` |
| `focus_exported_at` | Timestamp |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Focus_Export'` |
| `action_by` | User ID |
| `action_at` | Timestamp |

---

### 11. Focus Import
**Status**: `Closed`

#### advance_request_activities table
| Column | Value/Update |
|--------|--------------|
| `final_status` | `'Imported from Focus'` |
| `focus_imported_at` | Timestamp |
| `focus_reference` | Focus reference number |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Focus_Import'` |
| `action_by` | User ID |
| `action_at` | Timestamp |

---

### 12. Finalization
**Status**: `Closed` → `Finalized`

#### advance_request_activities table
| Column | Value/Update |
|--------|--------------|
| `final_status` | `'Finalized'` |
| `final_amount` | Final settled amount |
| `finalized_at` | Timestamp |
| `updated_at` | Timestamp |

#### advance_requests table
| Column | Value/Update |
|--------|--------------|
| `current_status` | `'Finalized'` |
| `updated_at` | Timestamp |

#### advance_request_actions table
| Column | Value/Update |
|--------|--------------|
| `action_type` | `'Finalize_Amount'` |
| `action_by` | User ID |
| `action_at` | Timestamp |

---

### Primary Tables
1. **advance_requests** - Main request record
2. **advance_request_employees** - Employees in request
3. **advance_request_activities** - Individual activities/line items
4. **advance_activity_approvals** - Approval chain records
5. **advance_request_actions** - Audit trail of all actions
6. **advance_export_logs** - Payment export history