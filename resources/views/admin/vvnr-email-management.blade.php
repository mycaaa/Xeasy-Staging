@extends('layouts.app')
@push('styles')
<style>
/* Rule modal select2 custom styling */
.select2-container--default .select2-selection--multiple {
    border: 1px solid #ced4da;
    border-radius: 4px;
    min-height: 38px;
    padding: 3px 6px;
    background-color: #fff;
    cursor: pointer;
}
.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #e7f1ff;
    border: 1px solid #b6d4fe;
    color: #0d6efd;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 500;
    margin: 3px 4px 3px 0;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: #0d6efd;
    margin-right: 6px;
    font-weight: bold;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #dc3545;
}
.grade-select2-wrapper .select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #fff8e6 !important;
    border: 1px solid #ffe8a1 !important;
    color: #b45309 !important;
}
.grade-select2-wrapper .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: #b45309 !important;
}
.grade-select2-wrapper .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #dc3545 !important;
}
.select2-dropdown {
    border: 1px solid #ced4da;
    border-radius: 4px;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    z-index: 99999 !important;
}
.select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 12px;
}
.select2-results__option {
    padding: 6px 12px;
    font-size: 12px;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #2b5c8f !important;
    color: #fff !important;
}
</style>
@endpush
@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 fw-bold text-primary">
                        <i class="ri-mail-settings-fill me-2"></i>VVNR Email Management & Automation Hub
                    </h4>
                    <p class="text-muted mb-0 fs-13">
                        Configure trigger schedules, manage custom templates, set recipient opt-in/opt-out permissions, and track live email logs for <strong>VVNR (Company 11)</strong>.
                    </p>
                </div>
                <div>
                    <span class="badge bg-warning-subtle text-warning fs-12 px-3 py-2 border border-warning">
                        <i class="ri-building-line me-1"></i>Company: VVNR (ID: 11)
                    </span>
                </div>
            </div>
        </div>

        {{-- Summary Stat Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-sm-6">
                <div class="card shadow-sm border-0 mb-0">
                    <div class="card-body p-3 text-center">
                        <i class="ri-time-line text-primary fs-24 mb-1 d-block"></i>
                        <h4 class="mb-0 fw-bold" id="statActiveRules">{{ $activeRulesCount }}</h4>
                        <span class="text-muted fs-12">Active Trigger Rules</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card shadow-sm border-0 mb-0">
                    <div class="card-body p-3 text-center">
                        <i class="ri-file-text-line text-info fs-24 mb-1 d-block"></i>
                        <h4 class="mb-0 fw-bold" id="statTemplates">{{ $templatesCount }}</h4>
                        <span class="text-muted fs-12">VVNR Templates</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 mb-0">
                    <div class="card-body p-3 text-center">
                        <i class="ri-team-line text-success fs-24 mb-1 d-block"></i>
                        <h4 class="mb-0 fw-bold" id="statVvnrEmp">{{ $totalVvnrEmployees }}</h4>
                        <span class="text-muted fs-12">Total Active VVNR Employees</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 mb-0">
                    <div class="card-body p-3 text-center">
                        <i class="ri-user-unfollow-line text-danger fs-24 mb-1 d-block"></i>
                        <h4 class="mb-0 fw-bold" id="statDisabled">{{ $disabledRecipientsCount }}</h4>
                        <span class="text-muted fs-12">Blocked / Opted-Out Recipients</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card shadow-sm border-0 mb-0">
                    <div class="card-body p-3 text-center">
                        <i class="ri-mail-send-line text-secondary fs-24 mb-1 d-block"></i>
                        <h4 class="mb-0 fw-bold" id="statTodaySent">{{ $todaySentCount }}</h4>
                        <span class="text-muted fs-12">Sent Today</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Navigation Tabs --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs nav-tabs-custom nav-success card-header-tabs border-bottom-0 mx-2" id="vvnrEmailTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" id="tab-triggers-link" data-bs-toggle="tab" href="#tab-triggers" role="tab">
                            <i class="ri-alarm-warning-line me-1"></i> Trigger Schedules & Automation
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" id="tab-templates-link" data-bs-toggle="tab" href="#tab-templates" role="tab">
                            <i class="ri-layout-masonry-line me-1"></i> Email Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" id="tab-recipients-link" data-bs-toggle="tab" href="#tab-recipients" role="tab">
                            <i class="ri-user-settings-line me-1"></i> Recipient Controls (Who Receives)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" id="tab-hierarchy-link" data-bs-toggle="tab" href="#tab-hierarchy" role="tab">
                            <i class="ri-node-tree me-1"></i> Monthly Hierarchy Summary (L2 / HOD)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" id="tab-logs-link" data-bs-toggle="tab" href="#tab-logs" role="tab">
                            <i class="ri-history-line me-1"></i> Email Send Logs & Audit Trail
                        </a>
                    </li>
                    <li class="nav-item ms-auto">
                        <button class="btn btn-sm btn-outline-primary my-2 me-2" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                            <i class="ri-mail-send-line me-1"></i> Send Test Email
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-3">
                <div class="tab-content">

                    {{-- TAB 1: TRIGGER SCHEDULES --}}
                    <div class="tab-pane fade show active" id="tab-triggers" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0 fw-bold">VVNR Automated Triggers</h5>
                                <span class="text-muted fs-12">Automated schedules controlling when emails trigger for VVNR expense claims.</span>
                            </div>
                            <button class="btn btn-primary btn-sm rounded-pill" onclick="openRuleModal()">
                                <i class="ri-add-line me-1"></i> Add Trigger Rule
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle mb-0" id="rulesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Notification Event</th>
                                        <th>Trigger Schedule</th>
                                        <th>Recipient Level</th>
                                        <th>Target Audience (Dept & Grade)</th>
                                        <th>Stage / Condition</th>
                                        <th>Linked Template</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="rulesTableBody">
                                    <tr><td colspan="10" class="text-center py-4 text-muted">Loading rules...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- TAB 2: EMAIL TEMPLATES --}}
                    <div class="tab-pane fade" id="tab-templates" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0 fw-bold">VVNR Email Templates</h5>
                                <span class="text-muted fs-12">Create and customize HTML templates with dynamic placeholders for VVNR notifications.</span>
                            </div>
                            <button class="btn btn-primary btn-sm rounded-pill" onclick="openTemplateModal()">
                                <i class="ri-add-line me-1"></i> Create New Template
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle mb-0" id="templatesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Template Name</th>
                                        <th>Subject Line</th>
                                        <th>Category</th>
                                        <th>Scope</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="templatesTableBody">
                                    <tr><td colspan="7" class="text-center py-4 text-muted">Loading templates...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- TAB 3: RECIPIENT CONTROLS (Employee / Department / Grade Wise) --}}
                    <div class="tab-pane fade" id="tab-recipients" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <ul class="nav nav-pills" id="recipSubTabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active py-1 px-3 fs-13" id="pill-emp-tab" data-bs-toggle="pill" data-bs-target="#pill-emp" type="button">
                                        <i class="ri-user-line me-1"></i> Employee-wise Control
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link py-1 px-3 fs-13" id="pill-dept-tab" data-bs-toggle="pill" data-bs-target="#pill-dept" type="button">
                                        <i class="ri-building-line me-1"></i> Department-wise Control
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link py-1 px-3 fs-13" id="pill-grade-tab" data-bs-toggle="pill" data-bs-target="#pill-grade" type="button">
                                        <i class="ri-medal-line me-1"></i> Grade-wise Control
                                    </button>
                                </li>
                            </ul>
                            <div class="d-flex gap-2">
                                <button class="btn btn-warning btn-sm" onclick="openBulkModal()">
                                    <i class="ri-shield-user-line me-1"></i> Bulk & Dept/Grade Control
                                </button>
                            </div>
                        </div>

                        <div class="tab-content" id="recipSubTabsContent">
                            {{-- SUB-TAB 1: EMPLOYEE LEVEL --}}
                            <div class="tab-pane fade show active" id="pill-emp" role="tabpanel">
                                {{-- Filters --}}
                                <div class="card bg-light border mb-3">
                                    <div class="card-body p-2">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-3">
                                                <label class="form-label fs-11 fw-bold mb-1">Search Employee (Name / Code / Email)</label>
                                                <input type="text" class="form-control form-control-sm" id="recipSearch" placeholder="Type name, code, or email..." aria-label="Type name, code, or email">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label fs-11 fw-bold mb-1">Department</label>
                                                <select class="form-select form-select-sm" id="recipDept">
                                                    <option value="">All Departments</option>
                                                    @foreach($departments as $d)
                                                        <option value="{{ $d->id }}">{{ $d->department_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label fs-11 fw-bold mb-1">Grade</label>
                                                <select class="form-select form-select-sm" id="recipGrade">
                                                    <option value="">All Grades</option>
                                                    @foreach($grades as $g)
                                                        <option value="{{ $g->id }}">{{ $g->grade_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label fs-11 fw-bold mb-1">Status Filter</label>
                                                <select class="form-select form-select-sm" id="recipShow">
                                                    <option value="">All Recipients</option>
                                                    <option value="enabled">Enabled Only</option>
                                                    <option value="disabled">Disabled Only</option>
                                                </select>
                                            </div>
                                            <div class="col-md-1">
                                                <button class="btn btn-primary btn-sm w-100" id="btnFilterRecipients">
                                                    <i class="ri-search-line"></i> Filter
                                                </button>
                                            </div>
                                            <div class="col-md-2">
                                                <button class="btn btn-outline-warning btn-sm w-100" id="btnBulkRecipients" onclick="openBulkModal('selected')">
                                                    <i class="ri-checkbox-multiple-line me-1"></i> Checked (<span id="bulkCount">0</span>)
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Recipients Table --}}
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle mb-0" id="recipientsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 30px;"><input type="checkbox" id="checkAllRecipients" class="form-check-input"></th>
                                                <th>Code</th>
                                                <th>Employee Name</th>
                                                <th>Department / Grade</th>
                                                <th>VNR / Official Email</th>
                                                <th class="text-center">Master Active</th>
                                                <th class="text-center">Submission Reminder</th>
                                                <th class="text-center">Courier Pending</th>
                                                <th class="text-center">Escalation</th>
                                                <th class="text-center">Reason / Note</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recipientsTableBody">
                                            <tr><td colspan="10" class="text-center py-4 text-muted">Loading recipients...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3" id="recipPaginationContainer"></div>
                            </div>

                            {{-- SUB-TAB 2: DEPARTMENT LEVEL --}}
                            <div class="tab-pane fade" id="pill-dept" role="tabpanel">
                                <div class="card bg-light border mb-3">
                                    <div class="card-body p-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <span class="fw-bold fs-13"><i class="ri-building-line me-1 text-primary"></i> VVNR Department-Wise Email Management</span>
                                            <p class="text-muted fs-11 mb-0">Enable or disable emails by department, filter by grade, or click <strong>Manage Grades</strong> on any department to control specific grades.</p>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="d-flex align-items-center bg-white px-2 py-1 border rounded">
                                                <label class="form-label fs-11 fw-bold mb-0 me-2 text-nowrap text-dark">
                                                    <i class="ri-medal-line me-1 text-warning"></i> Select Grade:
                                                </label>
                                                <select class="form-select form-select-sm border-0 py-0" id="deptFilterGrade" onchange="loadDepartmentSummary()" style="min-width: 170px; background-color: #f8f9fa;">
                                                    <option value="">All Grades (Combined)</option>
                                                    @foreach($grades as $g)
                                                        <option value="{{ $g->id }}">{{ $g->grade_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" onclick="loadDepartmentSummary()">
                                                <i class="ri-refresh-line me-1"></i> Refresh
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle mb-0" id="deptSummaryTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Department Name</th>
                                                <th class="text-center">Total Staff</th>
                                                <th class="text-center">Emails Active</th>
                                                <th class="text-center">Emails Blocked</th>
                                                <th class="text-center">Submission Reminder</th>
                                                <th class="text-center">Courier Pending</th>
                                                <th class="text-center">Hardcopy Escalation</th>
                                                <th class="text-center" style="min-width: 220px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="deptSummaryTableBody">
                                            <tr><td colspan="9" class="text-center py-4 text-muted"><i class="ri-loader-4-line ri-spin fs-20 me-1"></i> Loading VVNR departments...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- SUB-TAB 3: GRADE LEVEL --}}
                            <div class="tab-pane fade" id="pill-grade" role="tabpanel">
                                <div class="card bg-light border mb-3">
                                    <div class="card-body p-2 d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-bold fs-13"><i class="ri-medal-line me-1 text-primary"></i> VVNR Grade-Wise Email Management</span>
                                            <p class="text-muted fs-11 mb-0">Configure email delivery permissions across employee bands and hierarchy grades.</p>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="loadGradeSummary()">
                                            <i class="ri-refresh-line me-1"></i> Refresh Grades
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle mb-0" id="gradeSummaryTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Grade</th>
                                                <th class="text-center">Total Staff</th>
                                                <th class="text-center">Emails Active</th>
                                                <th class="text-center">Emails Blocked</th>
                                                <th class="text-center">Submission Reminder</th>
                                                <th class="text-center">Courier Pending</th>
                                                <th class="text-center">Hardcopy Escalation</th>
                                                <th class="text-center" style="min-width: 220px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="gradeSummaryTableBody">
                                            <tr><td colspan="9" class="text-center py-4 text-muted"><i class="ri-loader-4-line ri-spin fs-20 me-1"></i> Loading VVNR grades...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 4: EMAIL LOGS --}}
                    <div class="tab-pane fade" id="tab-logs" role="tabpanel">
                        <div class="card bg-light border mb-3">
                            <div class="card-body p-2">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label fs-11 fw-bold mb-1">Search Logs (Name, Email, Subject)</label>
                                        <input type="text" class="form-control form-control-sm" id="logSearch" placeholder="Search text..." aria-label="Search text">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fs-11 fw-bold mb-1">Notification Type</label>
                                        <select class="form-select form-select-sm" id="logType">
                                            <option value="">All Types</option>
                                            <option value="submission_reminder">Submission Reminder</option>
                                            <option value="pending_hardcopy">Courier / Pending Hardcopy</option>
                                            <option value="pending_hardcopy_escalation">Escalation</option>
                                            <option value="test_email">Test Email</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fs-11 fw-bold mb-1">Delivery Status</label>
                                        <select class="form-select form-select-sm" id="logStatus">
                                            <option value="">All</option>
                                            <option value="1">Sent Successfully</option>
                                            <option value="0">Failed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fs-11 fw-bold mb-1">From Date</label>
                                        <input type="date" class="form-control form-control-sm" id="logDateFrom">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fs-11 fw-bold mb-1">To Date</label>
                                        <input type="date" class="form-control form-control-sm" id="logDateTo">
                                    </div>
                                    <div class="col-md-1">
                                        <button class="btn btn-primary btn-sm w-100" id="btnFilterLogs">
                                            <i class="ri-search-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle mb-0" id="logsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Recipient</th>
                                        <th>Email Address</th>
                                        <th>Subject</th>
                                        <th>Event Type</th>
                                        <th>Delivery Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="logsTableBody">
                                    <tr><td colspan="7" class="text-center py-4 text-muted">Loading logs...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3" id="logPaginationContainer"></div>
                    </div>

                    {{-- TAB 5: MONTHLY HIERARCHY-WISE SUMMARY (L2 / HOD) --}}
                    <div class="tab-pane fade" id="tab-hierarchy" role="tabpanel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                            <div>
                                <h5 class="mb-0 fw-bold">Monthly Hierarchy-wise Summary (Reporting Manager’s Manager)</h5>
                                <span class="text-muted fs-12">Consolidated hierarchy oversight for L2 Reviewers / HODs with drill-down manager breakdown.</span>
                            </div>
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <div>
                                    <select class="form-select form-select-sm" id="hierarchyMonthSelect" style="min-width: 130px;">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div>
                                    <select class="form-select form-select-sm" id="hierarchyL2Filter" style="min-width: 220px;">
                                        <option value="">All Reviewers / HODs (L2)</option>
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" id="btnRefreshHierarchy" onclick="loadHierarchySummary()">
                                    <i class="ri-refresh-line me-1"></i> Refresh
                                </button>
                                <button class="btn btn-sm btn-success" id="btnDispatchHierarchyAll" onclick="dispatchHierarchySummary()">
                                    <i class="ri-mail-send-line me-1"></i> Dispatch Summary to L2 Managers
                                </button>
                            </div>
                        </div>

                        {{-- 6 Top Metric Cards --}}
                        <div class="row g-3 mb-4">
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card border-0 shadow-sm bg-primary-subtle text-primary mb-0 h-100">
                                    <div class="card-body p-3 text-center">
                                        <div class="fs-11 fw-bold text-uppercase">Total in Hierarchy</div>
                                        <div class="fs-22 fw-bold mt-1" id="metricTotalHierarchy">0</div>
                                        <small class="text-muted fs-11">Covered Employees</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card border-0 shadow-sm bg-danger-subtle text-danger mb-0 h-100">
                                    <div class="card-body p-3 text-center">
                                        <div class="fs-11 fw-bold text-uppercase">Not Submitted</div>
                                        <div class="fs-22 fw-bold mt-1" id="metricNotSubmitted">0</div>
                                        <small class="text-muted fs-11">Claim not filed</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card border-0 shadow-sm bg-warning-subtle text-warning mb-0 h-100">
                                    <div class="card-body p-3 text-center">
                                        <div class="fs-11 fw-bold text-uppercase">Approval Pending</div>
                                        <div class="fs-22 fw-bold mt-1" id="metricApprovalPending">0</div>
                                        <small class="text-muted fs-11">Awaiting Manager / HOD</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card border-0 shadow-sm bg-info-subtle text-info mb-0 h-100">
                                    <div class="card-body p-3 text-center">
                                        <div class="fs-11 fw-bold text-uppercase">Hard Copy Pending</div>
                                        <div class="fs-22 fw-bold mt-1" id="metricHardcopyPending">0</div>
                                        <small class="text-muted fs-11">Physical receipt</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card border-0 shadow-sm bg-secondary-subtle text-secondary mb-0 h-100">
                                    <div class="card-body p-3 text-center">
                                        <div class="fs-11 fw-bold text-uppercase">Other Pending</div>
                                        <div class="fs-22 fw-bold mt-1" id="metricOtherPending">0</div>
                                        <small class="text-muted fs-11">Hold / Verification</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="card border-0 shadow-sm bg-dark-subtle text-dark mb-0 h-100">
                                    <div class="card-body p-3 text-center">
                                        <div class="fs-11 fw-bold text-uppercase">Total Pending Cases</div>
                                        <div class="fs-22 fw-bold mt-1 text-danger" id="metricTotalPending">0</div>
                                        <small class="text-muted fs-11">Combined pending</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Hierarchy Managers View / Accordions / Cards --}}
                        <div id="hierarchyContainer">
                            <div class="text-center py-5 text-muted">
                                <i class="ri-node-tree fs-32 text-secondary mb-2 d-block"></i>
                                Click on the tab to view or refresh hierarchy summary.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

{{-- MODAL: ADD / EDIT TRIGGER RULE --}}
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="ruleForm">
                @csrf
                <input type="hidden" name="id" id="ruleId">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="ruleModalTitle">Configure Trigger Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Notification Type / Trigger Event <span class="text-danger">*</span></label>
                            <select class="form-select" name="notification_type" id="ruleNotificationType" required>
                                <option value="submission_reminder">submission_reminder (Reminder for Non-submitted claims)</option>
                                <option value="pending_hardcopy">pending_hardcopy (Reminder for Courier / Physical Document receipt)</option>
                                <option value="pending_hardcopy_escalation">pending_hardcopy_escalation (Escalate overdue courier to Managers)</option>
                                <option value="approval_notification">approval_notification (Pending approval alert)</option>
                                <option value="hierarchy_monthly_summary">hierarchy_monthly_summary (Monthly Hierarchy Summary for L2 Reviewers / HODs)</option>
                                <option value="payment_notification">payment_notification (Claim payment processed notification)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Trigger Day of Month (1 - 31) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="send_day" id="ruleSendDay" min="1" max="31" required placeholder="e.g. 7 for 7th" aria-label="e.g. 7 for 7th">
                            <small class="text-muted">Day of month this trigger fires.</small>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mail Recipient Selection <span class="text-danger">*</span></label>
                            <select class="form-select" name="recipient_level" id="ruleRecipientLevel" required>
                                <option value="employee">Employee (Claimant)</option>
                                <option value="reporting_manager">Reporting Manager (L1 Manager)</option>
                                <option value="manager_manager">Reporting Manager’s Manager (L2 Reviewer / HOD)</option>
                                <option value="custom">Other / Custom Recipient</option>
                            </select>
                            <small class="text-muted fs-11">Official email is auto-fetched from reporting hierarchy based on selected level.</small>
                        </div>
                        <div class="col-md-6 d-none" id="ruleCustomRecipientContainer">
                            <label class="form-label fw-bold">Custom Recipient Email(s) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="custom_recipient_emails" id="ruleCustomRecipientEmails" placeholder="e.g. audit@vnrseeds.com, fin@vnrseeds.com" aria-label="e.g. audit@vnrseeds.com, fin@vnrseeds.com">
                            <small class="text-muted fs-11">Comma-separated email addresses.</small>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Stage Identifier</label>
                            <select class="form-select" name="stage" id="ruleStage">
                                <option value="non_submitted">non_submitted (Claim Not Yet Submitted)</option>
                                <option value="submitted_not_received">submitted_not_received (Courier / Hardcopy Pending)</option>
                                <option value="escalation_to_manager">escalation_to_manager (Overdue Courier Escalation to Manager)</option>
                                <option value="pending_approval">pending_approval (Pending Manager / HOD Approval)</option>
                                <option value="payment_processed">payment_processed (Settled & Payment Dispatched)</option>
                                <option value="">None / General</option>
                                <option value="__custom__">Custom Stage Identifier...</option>
                            </select>
                            <input type="text" class="form-control form-control-sm mt-1 d-none" id="ruleStageCustom" placeholder="Enter custom stage identifier..." aria-label="Enter custom stage identifier">
                            <small class="text-muted">Lifecycle stage associated with this trigger rule.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Linked Email Template <span class="text-danger">*</span></label>
                            <select class="form-select" name="email_template_id" id="ruleTemplateId" required>
                                <option value="">Select a template...</option>
                            </select>
                        </div>
                    </div>

                    <div class="card bg-light-subtle border p-3 mb-3 shadow-none rounded">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div>
                                <span class="fw-bold text-primary fs-13">
                                    <i class="ri-filter-3-line me-1"></i> Target Audience Filters (Department & Grade Specificity)
                                </span>
                                <div class="text-muted fs-11">Leave blank to target all departments & grades (company-wide).</div>
                            </div>
                            <span class="badge bg-light text-secondary border">Optional</span>
                        </div>
                        <div class="row g-3">
                            {{-- DEPARTMENTS --}}
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fs-12 fw-bold mb-0 text-dark">
                                        <i class="ri-building-line text-primary me-1"></i> Target Departments:
                                    </label>
                                    <div>
                                        <button type="button" class="btn btn-link btn-xs p-0 text-primary text-decoration-none me-2 fw-semibold" id="btnSelectAllDepts">Select All</button>
                                        <button type="button" class="btn btn-link btn-xs p-0 text-muted text-decoration-none" id="btnClearDepts">Clear</button>
                                    </div>
                                </div>
                                <select class="form-select select2-rule-dept" name="department_ids[]" id="ruleDepartmentIds" multiple="multiple" style="width: 100%;">
                                    @foreach($departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->department_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted fs-11 d-block mt-1">Select one or more departments, or leave empty for all.</small>
                            </div>

                            {{-- GRADES --}}
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fs-12 fw-bold mb-0 text-dark">
                                        <i class="ri-medal-line text-warning me-1"></i> Target Grades:
                                    </label>
                                    <div>
                                        <button type="button" class="btn btn-link btn-xs p-0 text-primary text-decoration-none me-2 fw-semibold" id="btnSelectAllGrades">Select All</button>
                                        <button type="button" class="btn btn-link btn-xs p-0 text-muted text-decoration-none" id="btnClearGrades">Clear</button>
                                    </div>
                                </div>
                                <div class="grade-select2-wrapper">
                                    <select class="form-select select2-rule-grade" name="grade_ids[]" id="ruleGradeIds" multiple="multiple" style="width: 100%;">
                                        @foreach($grades as $g)
                                            @php
                                                $gradeLabel = is_numeric($g->grade_name) ? 'Grade ' . $g->grade_name : $g->grade_name;
                                            @endphp
                                            <option value="{{ $g->id }}">{{ $gradeLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <small class="text-muted fs-11 d-block mt-1">Select one or more grades, or leave empty for all.</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description / Purpose</label>
                        <input type="text" class="form-control" name="description" id="ruleDescription" placeholder="e.g. Sales team L1-L3 monthly submission notice" aria-label="e.g. Sales team L1-L3 monthly submission notice">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="ruleIsActive" value="1" checked>
                        <label class="form-check-label fw-bold" for="ruleIsActive">Rule Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveRule">Save Trigger Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: CREATE / EDIT TEMPLATE --}}
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="templateForm">
                @csrf
                <input type="hidden" name="id" id="templateId">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="templateModalTitle">Email Template Editor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Template Unique Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="templateName" required placeholder="e.g. vvnr_submission_reminder" aria-label="e.g. vvnr_submission_reminder">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Category</label>
                            <select class="form-select" name="category" id="templateCategory">
                                <option value="expense">Expense</option>
                                <option value="advance">Advance</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="is_active" id="templateIsActive">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject" id="templateSubject" required placeholder="Subject with placeholders like @{{month}}">
                    </div>

                    {{-- Available Placeholders Helpers --}}
                    <div class="mb-3 p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fs-12 fw-bold text-dark"><i class="ri-code-line text-primary me-1"></i> Click to Insert Dynamic Variables:</span>
                            <span class="badge bg-primary-subtle text-primary fs-11">Auto-parsed on dispatch</span>
                        </div>
                        
                        <div class="mb-2">
                            <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Claimant & Company:</span>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-xs btn-outline-secondary placeholder-chip" data-tag="@{{employee_name}}">@{{employee_name}}</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary placeholder-chip" data-tag="@{{emp_code}}">@{{emp_code}}</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary placeholder-chip" data-tag="@{{month}}">@{{month}}</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary placeholder-chip" data-tag="@{{company_name}}">@{{company_name}}</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary placeholder-chip" data-tag="@{{login_url}}">@{{login_url}}</button>
                            </div>
                        </div>

                        <div class="mb-2">
                            <span class="fs-11 fw-bold text-danger text-uppercase d-block mb-1"><i class="ri-timer-line me-1"></i> Dynamic Pending & Due Date Variables (System-Calculated):</span>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-xs btn-outline-danger placeholder-chip" data-tag="@{{submission_date}}"><i class="ri-calendar-event-line me-1"></i>@{{submission_date}}</button>
                                <button type="button" class="btn btn-xs btn-outline-danger placeholder-chip" data-tag="@{{pending_since}}"><i class="ri-time-line me-1"></i>@{{pending_since}}</button>
                                <button type="button" class="btn btn-xs btn-danger placeholder-chip fw-bold" data-tag="@{{pending_days}}"><i class="ri-alarm-warning-line me-1"></i>@{{pending_days}} <span class="badge bg-white text-danger ms-1">Auto-Calc</span></button>
                                <button type="button" class="btn btn-xs btn-outline-danger placeholder-chip" data-tag="@{{due_date}}"><i class="ri-calendar-todo-line me-1"></i>@{{due_date}}</button>
                            </div>
                        </div>

                        <div>
                            <span class="fs-11 fw-bold text-success text-uppercase d-block mb-1"><i class="ri-node-tree me-1"></i> Hierarchy & Summaries:</span>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-xs btn-outline-success placeholder-chip" data-tag="@{{manager_name}}">@{{manager_name}} (L1)</button>
                                <button type="button" class="btn btn-xs btn-outline-success placeholder-chip" data-tag="@{{manager_manager_name}}">@{{manager_manager_name}} (L2 / HOD)</button>
                                <button type="button" class="btn btn-xs btn-outline-primary placeholder-chip" data-tag="@{{employee_table}}">@{{employee_table}}</button>
                                <button type="button" class="btn btn-xs btn-success placeholder-chip fw-bold" data-tag="@{{hierarchy_summary_table}}"><i class="ri-table-line me-1"></i>@{{hierarchy_summary_table}}</button>
                            </div>
                        </div>

                        <div class="mt-2 pt-2 border-top text-muted fs-11">
                            <i class="ri-information-line text-info me-1"></i> <strong>Dynamic Calculation:</strong> <code>@{{pending_days}}</code> is automatically calculated based on the pending date and current date (e.g. <em>"Approval has been pending for @{{pending_days}} days."</em>). Both <code>@{{var}}</code> and <code>{var}</code> are supported.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">HTML Email Body <span class="text-danger">*</span></label>
                        <textarea class="form-control font-monospace" name="body_html" id="templateBodyHtml" rows="12" required placeholder="Enter HTML content here..." aria-label="Enter HTML content here"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-info me-auto" id="btnPreviewInModal">
                        <i class="ri-eye-line me-1"></i> Preview Rendered
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveTemplate">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: PREVIEW TEMPLATE OR TRIGGER RECIPIENTS --}}
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="previewModalTitle">Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewModalBody">
                <!-- Content dynamically populated -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: BULK RECIPIENT CONTROL --}}
<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Bulk Recipient Permission Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Target Scope <span class="text-danger">*</span></label>
                    <select class="form-select" id="bulkScope">
                        <option value="selected" id="bulkScopeOptionSelected">Selected Employees (<span id="bulkSelectedCount">0</span> selected)</option>
                        <option value="department">By Department</option>
                        <option value="grade">By Grade</option>
                        <option value="department_and_grade">By Department & Grade (Combined)</option>
                        <option value="all">Entire Company (All Active VVNR Employees)</option>
                    </select>
                </div>
                <div class="mb-3 d-none" id="bulkDeptContainer">
                    <label class="form-label fw-bold">Select Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="bulkDeptSelect">
                        <option value="">Choose department...</option>
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->department_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3 d-none" id="bulkGradeContainer">
                    <label class="form-label fw-bold">Select Grade <span class="text-danger">*</span></label>
                    <select class="form-select" id="bulkGradeSelect">
                        <option value="">Choose grade...</option>
                        @foreach($grades as $g)
                            <option value="{{ $g->id }}">{{ $g->grade_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Setting to Update</label>
                    <select class="form-select" id="bulkField">
                        <option value="is_active">Master Switch (Allow / Block all emails)</option>
                        <option value="receive_submission_reminder">Submission Reminder</option>
                        <option value="receive_receiving_pending">Courier Pending Reminder</option>
                        <option value="receive_escalation">Hardcopy Escalation</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Value</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="bulkValue" id="bulkVal1" value="1" checked>
                            <label class="form-check-label text-success fw-bold" for="bulkVal1">Enable / Allow</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="bulkValue" id="bulkVal0" value="0">
                            <label class="form-check-label text-danger fw-bold" for="bulkVal0">Disable / Block</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Reason (Optional)</label>
                    <input type="text" class="form-control" id="bulkReason" placeholder="e.g. Requested exemption by HR" aria-label="e.g. Requested exemption by HR">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="btnApplyBulk">Apply Changes</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: TEST EMAIL --}}
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="testEmailForm">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="ri-mail-send-line me-1"></i> Send Instant Test Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Recipient Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="test_email" id="testEmailInput" required placeholder="Enter destination email..." aria-label="Enter destination email">
                        <small class="text-muted">A rendered email with mock VVNR data will be delivered here.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Choose Template <span class="text-danger">*</span></label>
                        <select class="form-select" name="template_id" id="testEmailTemplateSelect" required>
                            <option value="">Select a template...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSendTest">Send Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: LOG DETAILS --}}
<div class="modal fade" id="logDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="logDetailTitle">Email Delivery Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3 fs-13">
                    <div class="col-md-6"><strong>Recipient:</strong> <span id="logModalRecipient"></span></div>
                    <div class="col-md-6"><strong>Email:</strong> <span id="logModalEmail"></span></div>
                    <div class="col-md-6"><strong>Event Type:</strong> <span id="logModalType"></span></div>
                    <div class="col-md-6"><strong>Delivered At:</strong> <span id="logModalDate"></span></div>
                    <div class="col-md-12"><strong>Subject:</strong> <span id="logModalSubject"></span></div>
                    <div class="col-md-12" id="logModalErrorContainer" style="display:none;">
                        <div class="alert alert-danger p-2 mb-0 mt-1 fs-12">
                            <strong>Failure Error:</strong> <span id="logModalError"></span>
                        </div>
                    </div>
                </div>
                <hr>
                <h6 class="fw-bold mb-2">Sent Content:</h6>
                <div class="border rounded p-3 bg-white" id="logModalBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Toast notification bridge for SweetAlert2 & jquery-toast
const toastr = {
    success: function(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: msg, timer: 2500, showConfirmButton: false, toast: true, position: 'top-end' });
        } else if (typeof $.toast !== 'undefined') {
            $.toast({ text: msg, heading: 'Success', icon: 'success', position: 'top-right' });
        } else {
            alert(msg);
        }
    },
    error: function(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Error', text: msg, toast: true, position: 'top-end', timer: 4000, showConfirmButton: false });
        } else if (typeof $.toast !== 'undefined') {
            $.toast({ text: msg, heading: 'Error', icon: 'error', position: 'top-right' });
        } else {
            alert('Error: ' + msg);
        }
    },
    info: function(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'info', title: msg, timer: 2500, showConfirmButton: false, toast: true, position: 'top-end' });
        } else if (typeof $.toast !== 'undefined') {
            $.toast({ text: msg, heading: 'Info', icon: 'info', position: 'top-right' });
        }
    },
    warning: function(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'warning', title: msg, toast: true, position: 'top-end', timer: 3000, showConfirmButton: false });
        } else if (typeof $.toast !== 'undefined') {
            $.toast({ text: msg, heading: 'Warning', icon: 'warning', position: 'top-right' });
        } else {
            alert('Warning: ' + msg);
        }
    }
};
window.toastr = toastr;

$(document).ready(function() {
    let globalTemplates = [];
    let selectedRecipientIds = [];
    let rulesCache = {};

    // Accessibility fix: blur active element on hide to prevent "Blocked aria-hidden on an element because its descendant retained focus"
    $(document).on('click', '[data-bs-dismiss="modal"]', function () {
        $(this).blur();
    });
    $(document).on('hide.bs.modal', '.modal', function () {
        if (document.activeElement && this.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });

    // Initialize data
    loadRules();
    loadTemplates();
    loadRecipients(1);
    loadLogs(1);

    // =========================================================================
    // 1. RULES LOGIC
    // =========================================================================
    function loadRules() {
        $.get('{{ route("vvnr.email.rules") }}', function(res) {
            if (!res.success) return;
            const rules = res.data;
            rulesCache = {};
            let rows = '';

            if (rules.length === 0) {
                rows = '<tr><td colspan="10" class="text-center py-4 text-muted">No trigger rules configured for VVNR. Click "Add Trigger Rule" above.</td></tr>';
            } else {
                rules.forEach((r, idx) => {
                    rulesCache[r.id] = r;
                    const statusBadge = r.is_active 
                        ? '<span class="badge bg-success">Active</span>' 
                        : '<span class="badge bg-secondary">Paused</span>';

                    const depts = (r.department_names && r.department_names.length > 0) ? r.department_names : [];
                    const grades = (r.grade_names && r.grade_names.length > 0) ? r.grade_names : [];

                    let targetBadges = '';
                    if (depts.length === 0 && grades.length === 0) {
                        targetBadges = '<span class="badge bg-light text-muted border">All Depts & Grades</span>';
                    } else {
                        if (depts.length > 0) {
                            targetBadges += `<div class="mb-1"><span class="badge bg-info-subtle text-info border border-info-subtle text-wrap" style="max-width:200px;"><i class="ri-building-line me-1"></i>${depts.join(', ')}</span></div>`;
                        }
                        if (grades.length > 0) {
                            const formattedGrades = grades.map(g => !isNaN(g) ? 'Grade ' + g : g);
                            targetBadges += `<div><span class="badge bg-warning-subtle text-dark border border-warning-subtle text-wrap" style="max-width:200px;"><i class="ri-medal-line me-1"></i>${formattedGrades.join(', ')}</span></div>`;
                        }
                    }

                    const recipientLevel = r.recipient_level || 'employee';
                    let recipBadge = '';
                    if (recipientLevel === 'employee') {
                        recipBadge = '<span class="badge bg-secondary-subtle text-secondary border"><i class="ri-user-line me-1"></i>Employee</span>';
                    } else if (recipientLevel === 'reporting_manager') {
                        recipBadge = '<span class="badge bg-primary-subtle text-primary border"><i class="ri-user-star-line me-1"></i>Reporting Manager (L1)</span>';
                    } else if (recipientLevel === 'manager_manager') {
                        recipBadge = '<span class="badge bg-warning-subtle text-dark border"><i class="ri-parent-line me-1"></i>Manager\'s Manager (L2)</span>';
                    } else if (recipientLevel === 'custom') {
                        recipBadge = `<span class="badge bg-dark text-white border text-wrap" style="max-width:180px;"><i class="ri-mail-line me-1"></i>Custom: ${r.custom_recipient_emails || '-'}</span>`;
                    } else {
                        recipBadge = `<span class="badge bg-light text-muted border">${recipientLevel}</span>`;
                    }
                    
                    rows += `<tr>
                        <td>${idx + 1}</td>
                        <td><span class="badge bg-primary-subtle text-primary fs-12">${r.notification_type}</span></td>
                        <td><span class="fw-bold text-dark">Day ${r.send_day}</span> of every month</td>
                        <td>${recipBadge}</td>
                        <td>${targetBadges}</td>
                        <td>${r.stage || '-'}</td>
                        <td>
                            <strong>${r.template_name || 'N/A'}</strong><br>
                            <small class="text-muted">${r.template_subject || ''}</small>
                        </td>
                        <td><small class="text-muted">${r.description || '-'}</small></td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input rule-toggle" type="checkbox" data-id="${r.id}" ${r.is_active ? 'checked' : ''}>
                                ${statusBadge}
                            </div>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-info me-1" onclick="previewRuleExecution('${r.notification_type}', ${r.id})" title="Preview who qualifies today">
                                <i class="ri-eye-line"></i> Preview
                            </button>
                            <button class="btn btn-xs btn-outline-success me-1" onclick="executeRuleNow('${r.notification_type}', ${r.id})" title="Run trigger now">
                                <i class="ri-play-fill"></i> Run Now
                            </button>
                            <button class="btn btn-xs btn-outline-primary me-1" onclick="editRule(${r.id})" title="Edit rule">
                                <i class="ri-pencil-line"></i>
                            </button>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteRule(${r.id})" title="Delete rule">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </td>
                    </tr>`;
                });
            }
            $('#rulesTableBody').html(rows);
            $('#statActiveRules').text(rules.filter(r => r.is_active).length);
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || xhr.statusText || 'Failed to load trigger schedules';
            toastr.error('Trigger Schedules: ' + msg);
        });
    }

    function initRuleSelect2() {
        if ($.fn.select2) {
            $('#ruleDepartmentIds').select2({
                dropdownParent: $('#ruleModal'),
                placeholder: "All Departments (or choose specific...)",
                allowClear: true,
                width: '100%',
                closeOnSelect: false
            });

            $('#ruleGradeIds').select2({
                dropdownParent: $('#ruleModal'),
                placeholder: "All Grades (or choose specific...)",
                allowClear: true,
                width: '100%',
                closeOnSelect: false
            });
        }
    }

    $(document).on('click', '#btnSelectAllDepts', function() {
        $('#ruleDepartmentIds > option').prop('selected', true);
        $('#ruleDepartmentIds').trigger('change');
    });
    $(document).on('click', '#btnClearDepts', function() {
        $('#ruleDepartmentIds').val(null).trigger('change');
    });

    $(document).on('click', '#btnSelectAllGrades', function() {
        $('#ruleGradeIds > option').prop('selected', true);
        $('#ruleGradeIds').trigger('change');
    });
    $(document).on('click', '#btnClearGrades', function() {
        $('#ruleGradeIds').val(null).trigger('change');
    });

    $('#ruleModal').on('shown.bs.modal', function () {
        initRuleSelect2();
    });

    const defaultStageMapping = {
        'submission_reminder': 'non_submitted',
        'pending_hardcopy': 'submitted_not_received',
        'pending_hardcopy_escalation': 'escalation_to_manager',
        'approval_notification': 'pending_approval',
        'payment_notification': 'payment_processed'
    };

    $(document).on('change', '#ruleNotificationType', function() {
        const type = $(this).val();
        if (defaultStageMapping[type]) {
            $('#ruleStage').val(defaultStageMapping[type]).trigger('change');
        }
    });

    $(document).on('change', '#ruleStage', function() {
        if ($(this).val() === '__custom__') {
            $('#ruleStageCustom').removeClass('d-none').attr('name', 'stage').focus();
            $(this).removeAttr('name');
        } else {
            $('#ruleStageCustom').addClass('d-none').removeAttr('name').val('');
            $(this).attr('name', 'stage');
        }
    });

    $(document).on('change', '#ruleRecipientLevel', function() {
        if ($(this).val() === 'custom') {
            $('#ruleCustomRecipientContainer').removeClass('d-none');
            $('#ruleCustomRecipientEmails').prop('required', true).focus();
        } else {
            $('#ruleCustomRecipientContainer').addClass('d-none');
            $('#ruleCustomRecipientEmails').prop('required', false).val('');
        }
    });

    window.openRuleModal = function() {
        $('#ruleForm')[0].reset();
        $('#ruleId').val('');
        $('#ruleNotificationType').val('submission_reminder');
        $('#ruleRecipientLevel').val('employee').trigger('change');
        $('#ruleCustomRecipientEmails').val('');
        $('#ruleCustomRecipientContainer').addClass('d-none');
        $('#ruleStage').attr('name', 'stage').val('non_submitted');
        $('#ruleStageCustom').addClass('d-none').removeAttr('name').val('');
        initRuleSelect2();
        $('#ruleDepartmentIds').val(null).trigger('change');
        $('#ruleGradeIds').val(null).trigger('change');
        $('#ruleModalTitle').text('Add VVNR Trigger Rule');
        $('#ruleModal').modal('show');
    };

    window.editRule = function(ruleOrId) {
        const rule = (typeof ruleOrId === 'object') ? ruleOrId : (rulesCache[ruleOrId] || {});
        $('#ruleId').val(rule.id || '');
        $('#ruleNotificationType').val(rule.notification_type || 'submission_reminder');
        $('#ruleSendDay').val(rule.send_day || 1);

        const recipLevel = rule.recipient_level || 'employee';
        $('#ruleRecipientLevel').val(recipLevel);
        $('#ruleCustomRecipientEmails').val(rule.custom_recipient_emails || '');
        if (recipLevel === 'custom') {
            $('#ruleCustomRecipientContainer').removeClass('d-none');
            $('#ruleCustomRecipientEmails').prop('required', true);
        } else {
            $('#ruleCustomRecipientContainer').addClass('d-none');
            $('#ruleCustomRecipientEmails').prop('required', false);
        }

        const stageVal = rule.stage || '';
        const stageExists = $(`#ruleStage option[value="${stageVal}"]`).length > 0;
        if (stageExists && stageVal !== '__custom__') {
            $('#ruleStage').attr('name', 'stage').val(stageVal);
            $('#ruleStageCustom').addClass('d-none').removeAttr('name').val('');
        } else if (stageVal) {
            $('#ruleStage').removeAttr('name').val('__custom__');
            $('#ruleStageCustom').removeClass('d-none').attr('name', 'stage').val(stageVal);
        } else {
            $('#ruleStage').attr('name', 'stage').val('');
            $('#ruleStageCustom').addClass('d-none').removeAttr('name').val('');
        }

        $('#ruleTemplateId').val(rule.email_template_id || '');
        $('#ruleDescription').val(rule.description || '');
        $('#ruleIsActive').prop('checked', rule.is_active == 1);

        initRuleSelect2();
        const deptVals = (rule.target_dept_ids || []).map(String);
        $('#ruleDepartmentIds').val(deptVals).trigger('change');

        const gradeVals = (rule.target_grade_ids || []).map(String);
        $('#ruleGradeIds').val(gradeVals).trigger('change');

        $('#ruleModalTitle').text('Edit VVNR Trigger Rule');
        $('#ruleModal').modal('show');
    };

    $('#ruleForm').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serialize();
        $.post('{{ route("vvnr.email.rules.save") }}', data, function(res) {
            if (res.success) {
                $('#ruleModal').modal('hide');
                loadRules();
                toastr.success(res.message);
            } else {
                toastr.error(res.message);
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error saving rule');
        });
    });

    $(document).on('change', '.rule-toggle', function() {
        const id = $(this).data('id');
        $.post(`{{ url("vvnr/email-management/rules") }}/${id}/toggle`, { _token: '{{ csrf_token() }}' }, function(res) {
            if (res.success) {
                loadRules();
                toastr.success(res.message);
            } else {
                toastr.error(res.message || 'Failed to toggle rule');
            }
        }).fail(function() {
            toastr.error('Network error updating rule status');
        });
    });

    window.deleteRule = function(id) {
        if (!confirm('Are you sure you want to delete this trigger schedule?')) return;
        $.ajax({
            url: `{{ url("vvnr/email-management/rules") }}/${id}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) {
                    loadRules();
                    toastr.success(res.message);
                } else {
                    toastr.error(res.message);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error deleting trigger rule');
            }
        });
    };

    window.previewRuleExecution = function(type, ruleId) {
        $('#previewModalTitle').text(`Preview Recipients for [${type}]`);
        $('#previewModalBody').html('<div class="text-center py-4"><i class="ri-loader-4-line ri-spin fs-24"></i> Checking VVNR claim database...</div>');
        $('#previewModal').modal('show');

        let url = `{{ url("vvnr/email-management/preview-trigger") }}/${type}`;
        if (ruleId) {
            url += `?rule_id=${ruleId}`;
        }

        $.get(url, function(res) {
            if (!res.success) {
                $('#previewModalBody').html(`<div class="alert alert-danger">${res.message}</div>`);
                return;
            }

            const data = res.data;
            let html = `<div class="mb-3">
                <span class="badge bg-primary fs-13">Total Qualifying Recipients: ${data.count}</span>
            </div>`;

            if (data.count === 0) {
                html += '<div class="alert alert-info">No VVNR employees currently meet the pending criteria for this notification.</div>';
            } else {
                html += `<div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr><th>Code</th><th>Name</th><th>Email</th><th>Month</th><th>Detail</th></tr>
                        </thead>
                        <tbody>`;
                data.recipients.forEach(r => {
                    html += `<tr>
                        <td><strong>${r.emp_code}</strong></td>
                        <td>${r.employee_name}</td>
                        <td>${r.email || '<span class="text-danger">Missing</span>'}</td>
                        <td>${r.month_name || '-'}</td>
                        <td>${r.submission_date || (r.employee_table ? 'Team Summary' : '-')}</td>
                    </tr>`;
                });
                html += `</tbody></table></div>`;
            }

            $('#previewModalBody').html(html);
        }).fail(function(xhr) {
            $('#previewModalBody').html(`<div class="alert alert-danger">${xhr.responseJSON?.message || 'Failed to check recipient eligibility.'}</div>`);
        });
    };

    window.executeRuleNow = function(type, ruleId) {
        if (!confirm(`Do you want to run the [${type}] email dispatch now for VVNR? This will send emails to all eligible opted-in recipients.`)) {
            return;
        }

        toastr.info('Executing trigger dispatch for VVNR...');
        $.post(`{{ url("vvnr/email-management/dispatch-trigger") }}/${type}`, {
            _token: '{{ csrf_token() }}',
            ignore_day: true,
            rule_id: ruleId || ''
        }, function(res) {
            if (res.success) {
                toastr.success(res.message);
                loadLogs(1);
            } else {
                toastr.error(res.message);
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Dispatch failed');
        });
    };

    // =========================================================================
    // 2. TEMPLATES LOGIC
    // =========================================================================
    function loadTemplates() {
        $.get('{{ route("vvnr.email.templates") }}', function(res) {
            if (!res.success) return;
            globalTemplates = res.data;
            let rows = '';
            let options = '<option value="">Select a template...</option>';

            if (globalTemplates.length === 0) {
                rows = '<tr><td colspan="7" class="text-center py-4 text-muted">No templates found. Click "Create New Template" above.</td></tr>';
            } else {
                globalTemplates.forEach((t, idx) => {
                    const statusBadge = t.is_active 
                        ? '<span class="badge bg-success">Active</span>' 
                        : '<span class="badge bg-secondary">Inactive</span>';

                    const scopeBadge = '<span class="badge bg-primary-subtle text-primary">VVNR</span>';

                    rows += `<tr>
                        <td>${idx + 1}</td>
                        <td><strong>${t.name}</strong></td>
                        <td>${t.subject}</td>
                        <td><span class="badge bg-light text-secondary">${t.category || 'expense'}</span></td>
                        <td>${scopeBadge}</td>
                        <td>${statusBadge}</td>
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-info me-1" onclick="previewTemplate(${t.id})" title="Preview rendered email">
                                <i class="ri-eye-line"></i>
                            </button>
                            <button class="btn btn-xs btn-outline-primary me-1" onclick="editTemplate(${t.id})" title="Edit template">
                                <i class="ri-pencil-line"></i>
                            </button>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteTemplate(${t.id})" title="Delete template">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </td>
                    </tr>`;

                    options += `<option value="${t.id}">${t.name} — (${t.subject})</option>`;
                });
            }

            $('#templatesTableBody').html(rows);
            $('#ruleTemplateId').html(options);
            $('#testEmailTemplateSelect').html(options);
            $('#statTemplates').text(globalTemplates.length);
        }).fail(function() {
            toastr.error('Failed to load VVNR email templates');
        });
    }

    window.openTemplateModal = function() {
        $('#templateForm')[0].reset();
        $('#templateId').val('');
        $('#templateModalTitle').text('Create VVNR Email Template');
        $('#templateModal').modal('show');
    };

    window.editTemplate = function(id) {
        $.get(`{{ url("vvnr/email-management/templates") }}/${id}`, function(res) {
            if (!res.success) {
                toastr.error(res.message || 'Template not found');
                return;
            }
            const t = res.data;
            $('#templateId').val(t.id);
            $('#templateName').val(t.name);
            $('#templateSubject').val(t.subject);
            $('#templateCategory').val(t.category || 'expense');
            $('#templateIsActive').val(t.is_active ? '1' : '0');
            $('#templateBodyHtml').val(t.body_html);
            $('#templateModalTitle').text('Edit VVNR Email Template');
            $('#templateModal').modal('show');
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error loading template details');
        });
    };

    $('#templateForm').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serialize();
        $.post('{{ route("vvnr.email.templates.save") }}', data, function(res) {
            if (res.success) {
                $('#templateModal').modal('hide');
                loadTemplates();
                toastr.success(res.message);
            } else {
                toastr.error(res.message);
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error saving template');
        });
    });

    window.previewTemplate = function(id) {
        $('#previewModalTitle').text('Rendered Template Preview');
        $('#previewModalBody').html('<div class="text-center py-4"><i class="ri-loader-4-line ri-spin fs-24"></i> Rendering sample email...</div>');
        $('#previewModal').modal('show');

        $.get(`{{ url("vvnr/email-management/templates") }}/${id}/preview`, function(res) {
            if (!res.success) {
                $('#previewModalBody').html(`<div class="alert alert-danger">${res.message || 'Error generating preview'}</div>`);
                return;
            }
            const t = res.data;
            const html = `
                <div class="mb-3 p-2 bg-light border rounded">
                    <strong>Subject:</strong> ${t.subject}
                </div>
                <div class="border rounded p-3 bg-white">
                    ${t.body_html}
                </div>
            `;
            $('#previewModalBody').html(html);
        }).fail(function(xhr) {
            $('#previewModalBody').html(`<div class="alert alert-danger">${xhr.responseJSON?.message || 'Failed to preview template'}</div>`);
        });
    };

    $('#btnPreviewInModal').on('click', function() {
        const subject = $('#templateSubject').val();
        const body    = $('#templateBodyHtml').val();
        $('#previewModalTitle').text('Template Preview');
        $('#previewModalBody').html(`
            <div class="mb-3 p-2 bg-light border rounded"><strong>Subject:</strong> ${subject}</div>
            <div class="border rounded p-3 bg-white">${body}</div>
        `);
        $('#previewModal').modal('show');
    });

    // Helper placeholder click
    $('.placeholder-chip').on('click', function() {
        const tag = $(this).data('tag');
        const textarea = document.getElementById('templateBodyHtml');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + tag + text.substring(end);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
        toastr.info(`Inserted ${tag}`);
    });

    window.deleteTemplate = function(id) {
        if (!confirm('Are you sure you want to delete this template?')) return;
        $.ajax({
            url: `{{ url("vvnr/email-management/templates") }}/${id}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) {
                    loadTemplates();
                    toastr.success(res.message);
                } else {
                    toastr.error(res.message);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error deleting template');
            }
        });
    };

    // =========================================================================
    // 3. RECIPIENT CONTROLS LOGIC
    // =========================================================================
    function loadRecipients(page = 1) {
        const params = {
            page: page,
            search: $('#recipSearch').val(),
            department_id: $('#recipDept').val(),
            grade_id: $('#recipGrade').val(),
            show: $('#recipShow').val(),
        };

        $('#recipientsTableBody').html('<tr><td colspan="10" class="text-center py-4 text-muted"><i class="ri-loader-4-line ri-spin fs-20 me-1"></i> Loading VVNR recipients...</td></tr>');

        $.get('{{ route("vvnr.email.recipients") }}', params, function(res) {
            if (!res.success) return;
            const data = res.data;
            const employees = data.employees;
            let rows = '';

            if (employees.length === 0) {
                rows = '<tr><td colspan="10" class="text-center py-4 text-muted">No recipients found matching your filter criteria.</td></tr>';
            } else {
                employees.forEach(emp => {
                    const isChecked = selectedRecipientIds.includes(emp.employee_id) ? 'checked' : '';
                    rows += `<tr>
                        <td><input type="checkbox" class="form-check-input recip-check" value="${emp.employee_id}" ${isChecked}></td>
                        <td><strong>${emp.emp_code}</strong></td>
                        <td>
                            <div class="fw-bold">${emp.employee_name}</div>
                            <small class="text-muted">${emp.designation || ''}</small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">${emp.department || '-'}</span><br>
                            <small class="text-muted">${emp.grade || ''}</small>
                        </td>
                        <td>
                            ${emp.email ? `<span class="text-dark">${emp.email}</span>` : '<span class="text-danger fw-bold">Missing Email</span>'}
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input recip-toggle" data-emp="${emp.employee_id}" data-field="is_active" ${emp.is_active ? 'checked' : ''}>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input recip-toggle" data-emp="${emp.employee_id}" data-field="receive_submission_reminder" ${emp.receive_submission_reminder ? 'checked' : ''}>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input recip-toggle" data-emp="${emp.employee_id}" data-field="receive_receiving_pending" ${emp.receive_receiving_pending ? 'checked' : ''}>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input recip-toggle" data-emp="${emp.employee_id}" data-field="receive_escalation" ${emp.receive_escalation ? 'checked' : ''}>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-secondary" onclick="promptReason(${emp.employee_id}, '${emp.reason || ''}')" title="Set exemption reason">
                                <i class="ri-chat-1-line"></i> ${emp.reason ? '<span class="text-primary font-monospace">Note</span>' : 'Note'}
                            </button>
                        </td>
                    </tr>`;
                });
            }

            $('#recipientsTableBody').html(rows);
            renderPagination('#recipPaginationContainer', data.pagination, loadRecipients);
        });
    }

    $('#btnFilterRecipients').on('click', () => loadRecipients(1));
    $('#recipSearch').on('keyup', function(e) { if (e.key === 'Enter') loadRecipients(1); });
    $('#recipDept, #recipGrade, #recipShow').on('change', () => loadRecipients(1));

    $(document).on('change', '.recip-toggle', function() {
        const empId = $(this).data('emp');
        const field = $(this).data('field');
        const val   = $(this).is(':checked') ? 1 : 0;

        $.post('{{ route("vvnr.email.recipients.update") }}', {
            _token: '{{ csrf_token() }}',
            employee_id: empId,
            field: field,
            value: val
        }, function(res) {
            if (res.success) {
                toastr.success(res.message);
            } else {
                toastr.error(res.message || 'Failed to update recipient setting');
            }
        }).fail(function() {
            toastr.error('Network error updating recipient setting');
        });
    });

    window.promptReason = function(empId, currentReason) {
        const reason = prompt('Enter exemption or blocking reason for this employee:', currentReason);
        if (reason === null) return;

        $.post('{{ route("vvnr.email.recipients.update") }}', {
            _token: '{{ csrf_token() }}',
            employee_id: empId,
            field: 'is_active',
            value: 0,
            reason: reason
        }, function(res) {
            toastr.success('Reason saved and employee blocked');
            loadRecipients();
        }).fail(function() {
            toastr.error('Failed to save reason');
        });
    };

    // Checkbox selections for bulk
    $('#checkAllRecipients').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.recip-check').prop('checked', isChecked);
        updateSelectedRecipients();
    });

    $(document).on('change', '.recip-check', function() {
        updateSelectedRecipients();
    });

    function updateSelectedRecipients() {
        selectedRecipientIds = [];
        $('.recip-check:checked').each(function() {
            selectedRecipientIds.push($(this).val());
        });
        $('#bulkCount').text(selectedRecipientIds.length);
        $('#bulkSelectedCount').text(selectedRecipientIds.length);
        $('#bulkScopeOptionSelected').text(`Selected Employees (${selectedRecipientIds.length} selected)`);
    }

    // Open Bulk Modal with optional scope and target
    window.openBulkModal = function(scope = null, targetId = null, targetGradeId = null) {
        if (scope) {
            $('#bulkScope').val(scope);
        } else if (selectedRecipientIds.length > 0) {
            $('#bulkScope').val('selected');
        } else {
            $('#bulkScope').val('department');
        }

        $('#bulkScope').trigger('change');

        if ((scope === 'department' || scope === 'department_and_grade') && targetId) {
            $('#bulkDeptSelect').val(targetId);
        }
        if (scope === 'grade' && targetId) {
            $('#bulkGradeSelect').val(targetId);
        }
        if (scope === 'department_and_grade' && targetGradeId) {
            $('#bulkGradeSelect').val(targetGradeId);
        }

        $('#bulkModal').modal('show');
    };

    $('#bulkScope').on('change', function() {
        const val = $(this).val();
        if (val === 'department') {
            $('#bulkDeptContainer').removeClass('d-none');
            $('#bulkGradeContainer').addClass('d-none');
        } else if (val === 'grade') {
            $('#bulkGradeContainer').removeClass('d-none');
            $('#bulkDeptContainer').addClass('d-none');
        } else if (val === 'department_and_grade') {
            $('#bulkDeptContainer').removeClass('d-none');
            $('#bulkGradeContainer').removeClass('d-none');
        } else {
            $('#bulkDeptContainer').addClass('d-none');
            $('#bulkGradeContainer').addClass('d-none');
        }
    });

    // Department Summary Loader
    window.loadDepartmentSummary = function() {
        const selectedGrade = $('#deptFilterGrade').val();
        const selectedGradeName = $('#deptFilterGrade option:selected').text();

        $('#deptSummaryTableBody').html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="ri-loader-4-line ri-spin fs-20 me-1"></i> Loading VVNR departments...</td></tr>');
        
        $.get('{{ route("vvnr.email.recipients.dept_summary") }}', { grade_id: selectedGrade }, function(res) {
            if (!res.success) return;
            const depts = res.data;
            let rows = '';

            if (depts.length === 0) {
                const emptyMsg = selectedGrade 
                    ? `No employees found for Grade [${selectedGradeName}] in any department.` 
                    : 'No department records found for VVNR.';
                rows = `<tr><td colspan="9" class="text-center py-4 text-muted">${emptyMsg}</td></tr>`;
            } else {
                depts.forEach((d, idx) => {
                    const activeRatio = `<span class="badge bg-success">${d.active_count} Enabled</span>`;
                    const disabledBadge = d.disabled_count > 0 
                        ? `<span class="badge bg-danger">${d.disabled_count} Blocked</span>` 
                        : '<span class="badge bg-light text-muted">0 Blocked</span>';

                    const gradesCount = (d.grades && d.grades.length) ? d.grades.length : 0;
                    let gradeBadgeBtn = '';
                    if (gradesCount > 0) {
                        gradeBadgeBtn = `<button class="btn btn-xs btn-outline-info py-0 px-2 ms-2 fs-11" onclick="toggleDeptGradeRow(${d.department_id})" title="Click to view and select grades in ${d.department_name}">
                            <i class="ri-medal-line me-1"></i>${gradesCount} Grades <i class="ri-arrow-down-s-line" id="arrow-dept-${d.department_id}"></i>
                        </button>`;
                    }

                    // Main row action buttons
                    let actionButtons = '';
                    if (selectedGrade) {
                        actionButtons = `
                            <button class="btn btn-xs btn-outline-success me-1" onclick="quickToggleDeptGrade(${d.department_id}, ${selectedGrade}, '${d.department_name.replace(/'/g, "\\'")}', '${selectedGradeName.replace(/'/g, "\\'")}', 1)" title="Enable emails for ${selectedGradeName} in ${d.department_name}">
                                <i class="ri-check-line"></i> Enable ${selectedGradeName}
                            </button>
                            <button class="btn btn-xs btn-outline-danger me-1" onclick="quickToggleDeptGrade(${d.department_id}, ${selectedGrade}, '${d.department_name.replace(/'/g, "\\'")}', '${selectedGradeName.replace(/'/g, "\\'")}', 0)" title="Block emails for ${selectedGradeName} in ${d.department_name}">
                                <i class="ri-close-line"></i> Block ${selectedGradeName}
                            </button>
                            <button class="btn btn-xs btn-outline-primary" onclick="openBulkModal('department_and_grade', ${d.department_id}, ${selectedGrade})" title="Configure settings for this department & grade">
                                <i class="ri-settings-3-line"></i>
                            </button>
                        `;
                    } else {
                        actionButtons = `
                            <button class="btn btn-xs btn-outline-success me-1" onclick="quickToggleDept(${d.department_id}, '${d.department_name.replace(/'/g, "\\'")}', 1)" title="Enable all staff in this department">
                                <i class="ri-check-line"></i> Enable All
                            </button>
                            <button class="btn btn-xs btn-outline-danger me-1" onclick="quickToggleDept(${d.department_id}, '${d.department_name.replace(/'/g, "\\'")}', 0)" title="Block all staff in this department">
                                <i class="ri-close-line"></i> Block All
                            </button>
                            <button class="btn btn-xs btn-outline-info me-1" onclick="toggleDeptGradeRow(${d.department_id})" title="Manage specific grades in this department">
                                <i class="ri-medal-line"></i> Manage Grades
                            </button>
                            <button class="btn btn-xs btn-outline-primary" onclick="openBulkModal('department', ${d.department_id})" title="Configure department settings">
                                <i class="ri-settings-3-line"></i>
                            </button>
                        `;
                    }

                    rows += `<tr>
                        <td>${idx + 1}</td>
                        <td>
                            <span class="fw-bold">${d.department_name}</span>
                            ${gradeBadgeBtn}
                        </td>
                        <td class="text-center"><span class="badge bg-light text-dark fs-12">${d.total_employees}</span></td>
                        <td class="text-center">${activeRatio}</td>
                        <td class="text-center">${disabledBadge}</td>
                        <td class="text-center"><small class="text-muted">${d.sub_count}/${d.total_employees}</small></td>
                        <td class="text-center"><small class="text-muted">${d.recv_count}/${d.total_employees}</small></td>
                        <td class="text-center"><small class="text-muted">${d.esc_count}/${d.total_employees}</small></td>
                        <td class="text-center">${actionButtons}</td>
                    </tr>`;

                    // Expandable sub-table for grades inside this department
                    if (d.grades && d.grades.length > 0) {
                        let gRows = '';
                        d.grades.forEach(g => {
                            const gActiveBadge = `<span class="badge bg-success">${g.active_count} Enabled</span>`;
                            const gBlockedBadge = g.disabled_count > 0 
                                ? `<span class="badge bg-danger">${g.disabled_count} Blocked</span>` 
                                : '<span class="badge bg-light text-muted">0 Blocked</span>';

                            gRows += `<tr>
                                <td class="ps-3"><i class="ri-medal-fill text-warning me-1"></i><strong>${g.grade_name}</strong></td>
                                <td class="text-center"><span class="badge bg-light text-dark">${g.total_employees} staff</span></td>
                                <td class="text-center">${gActiveBadge}</td>
                                <td class="text-center">${gBlockedBadge}</td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-xs btn-outline-success me-1" onclick="quickToggleDeptGrade(${d.department_id}, ${g.grade_id}, '${d.department_name.replace(/'/g, "\\'")}', '${g.grade_name.replace(/'/g, "\\'")}', 1)" title="Enable emails for ${g.grade_name} in ${d.department_name}">
                                        <i class="ri-check-line"></i> Enable Grade
                                    </button>
                                    <button class="btn btn-xs btn-outline-danger me-1" onclick="quickToggleDeptGrade(${d.department_id}, ${g.grade_id}, '${d.department_name.replace(/'/g, "\\'")}', '${g.grade_name.replace(/'/g, "\\'")}', 0)" title="Block emails for ${g.grade_name} in ${d.department_name}">
                                        <i class="ri-close-line"></i> Block Grade
                                    </button>
                                    <button class="btn btn-xs btn-outline-primary" onclick="openBulkModal('department_and_grade', ${d.department_id}, ${g.grade_id})" title="Advanced Settings for ${g.grade_name}">
                                        <i class="ri-settings-3-line"></i>
                                    </button>
                                </td>
                            </tr>`;
                        });

                        rows += `<tr id="dept-grades-row-${d.department_id}" class="d-none bg-light border-start border-3 border-info">
                            <td colspan="9" class="p-2 ps-4">
                                <div class="card border mb-0 shadow-none">
                                    <div class="card-header bg-white py-1 px-3 d-flex justify-content-between align-items-center">
                                        <span class="fs-12 fw-bold text-dark"><i class="ri-medal-line text-warning me-1"></i> Select which grades in [${d.department_name}] will receive emails:</span>
                                        <button class="btn btn-link btn-xs p-0 text-muted" onclick="toggleDeptGradeRow(${d.department_id})"><i class="ri-close-line"></i> Close</button>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-3">Grade</th>
                                                    <th class="text-center">Total Staff</th>
                                                    <th class="text-center">Emails Active</th>
                                                    <th class="text-center">Emails Blocked</th>
                                                    <th class="text-end pe-3">Select / Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>${gRows}</tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>`;
                    }
                });
            }
            $('#deptSummaryTableBody').html(rows);
        }).fail(function() {
            toastr.error('Failed to load department summary');
        });
    };

    window.toggleDeptGradeRow = function(deptId) {
        const row = $(`#dept-grades-row-${deptId}`);
        const arrow = $(`#arrow-dept-${deptId}`);
        if (row.hasClass('d-none')) {
            row.removeClass('d-none');
            arrow.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        } else {
            row.addClass('d-none');
            arrow.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
        }
    };

    window.quickToggleDeptGrade = function(deptId, gradeId, deptName, gradeName, val) {
        const actionText = val === 1 ? 'ENABLE all emails' : 'BLOCK all emails';
        if (!confirm(`Are you sure you want to ${actionText} for employees in [${deptName}] with grade [${gradeName}]?`)) return;

        $.post('{{ route("vvnr.email.recipients.bulk") }}', {
            _token: '{{ csrf_token() }}',
            scope: 'department_and_grade',
            department_id: deptId,
            grade_id: gradeId,
            field: 'is_active',
            value: val,
            reason: `Batch update for ${deptName} - Grade ${gradeName}`
        }, function(res) {
            if (res.success) {
                toastr.success(res.message);
                loadDepartmentSummary();
                loadRecipients(1);
            } else {
                toastr.error(res.message);
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Department grade update failed');
        });
    };

    // Grade Summary Loader
    window.loadGradeSummary = function() {
        $('#gradeSummaryTableBody').html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="ri-loader-4-line ri-spin fs-20 me-1"></i> Loading VVNR grades...</td></tr>');
        $.get('{{ route("vvnr.email.recipients.grade_summary") }}', function(res) {
            if (!res.success) return;
            const grades = res.data;
            let rows = '';

            if (grades.length === 0) {
                rows = '<tr><td colspan="9" class="text-center py-4 text-muted">No grade records found for VVNR.</td></tr>';
            } else {
                grades.forEach((g, idx) => {
                    const activeRatio = `<span class="badge bg-success">${g.active_count} Enabled</span>`;
                    const disabledBadge = g.disabled_count > 0 
                        ? `<span class="badge bg-danger">${g.disabled_count} Blocked</span>` 
                        : '<span class="badge bg-light text-muted">0 Blocked</span>';

                    rows += `<tr>
                        <td>${idx + 1}</td>
                        <td><strong>${g.grade_name}</strong></td>
                        <td class="text-center"><span class="badge bg-light text-dark fs-12">${g.total_employees}</span></td>
                        <td class="text-center">${activeRatio}</td>
                        <td class="text-center">${disabledBadge}</td>
                        <td class="text-center"><small class="text-muted">${g.sub_count}/${g.total_employees}</small></td>
                        <td class="text-center"><small class="text-muted">${g.recv_count}/${g.total_employees}</small></td>
                        <td class="text-center"><small class="text-muted">${g.esc_count}/${g.total_employees}</small></td>
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-success me-1" onclick="quickToggleGrade(${g.grade_id}, '${g.grade_name.replace(/'/g, "\\'")}', 1)" title="Enable all in this grade">
                                <i class="ri-check-line"></i> Enable All
                            </button>
                            <button class="btn btn-xs btn-outline-danger me-1" onclick="quickToggleGrade(${g.grade_id}, '${g.grade_name.replace(/'/g, "\\'")}', 0)" title="Block all in this grade">
                                <i class="ri-close-line"></i> Block All
                            </button>
                            <button class="btn btn-xs btn-outline-primary" onclick="openBulkModal('grade', ${g.grade_id})" title="Configure grade settings">
                                <i class="ri-settings-3-line"></i>
                            </button>
                        </td>
                    </tr>`;
                });
            }
            $('#gradeSummaryTableBody').html(rows);
        }).fail(function() {
            toastr.error('Failed to load grade summary');
        });
    };

    // Quick toggles for department and grade
    window.quickToggleDept = function(deptId, deptName, val) {
        const actionText = val === 1 ? 'ENABLE all emails' : 'BLOCK all emails';
        if (!confirm(`Are you sure you want to ${actionText} for all employees in department [${deptName}]?`)) return;

        $.post('{{ route("vvnr.email.recipients.bulk") }}', {
            _token: '{{ csrf_token() }}',
            scope: 'department',
            department_id: deptId,
            field: 'is_active',
            value: val,
            reason: `Department-level batch update for ${deptName}`
        }, function(res) {
            if (res.success) {
                toastr.success(res.message);
                loadDepartmentSummary();
                loadRecipients(1);
            } else {
                toastr.error(res.message);
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Department update failed');
        });
    };

    window.quickToggleGrade = function(gradeId, gradeName, val) {
        const actionText = val === 1 ? 'ENABLE all emails' : 'BLOCK all emails';
        if (!confirm(`Are you sure you want to ${actionText} for all employees in grade [${gradeName}]?`)) return;

        $.post('{{ route("vvnr.email.recipients.bulk") }}', {
            _token: '{{ csrf_token() }}',
            scope: 'grade',
            grade_id: gradeId,
            field: 'is_active',
            value: val,
            reason: `Grade-level batch update for ${gradeName}`
        }, function(res) {
            if (res.success) {
                toastr.success(res.message);
                loadGradeSummary();
                loadRecipients(1);
            } else {
                toastr.error(res.message);
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Grade update failed');
        });
    };

    // Sub-tab switch listeners
    $('#pill-dept-tab').on('shown.bs.tab', function() { loadDepartmentSummary(); });
    $('#pill-grade-tab').on('shown.bs.tab', function() { loadGradeSummary(); });

    $('#btnApplyBulk').on('click', function() {
        const scope = $('#bulkScope').val();
        const payload = {
            _token: '{{ csrf_token() }}',
            scope: scope,
            field: $('#bulkField').val(),
            value: $('input[name=bulkValue]:checked').val(),
            reason: $('#bulkReason').val()
        };

        if (scope === 'selected') {
            if (selectedRecipientIds.length === 0) {
                toastr.warning('Please select at least one employee from the list or choose "By Department / Grade".');
                return;
            }
            payload.employee_ids = selectedRecipientIds;
        } else if (scope === 'department') {
            const deptId = $('#bulkDeptSelect').val();
            if (!deptId) {
                toastr.warning('Please choose a department to update.');
                return;
            }
            payload.department_id = deptId;
        } else if (scope === 'grade') {
            const gradeId = $('#bulkGradeSelect').val();
            if (!gradeId) {
                toastr.warning('Please choose a grade to update.');
                return;
            }
            payload.grade_id = gradeId;
        } else if (scope === 'department_and_grade') {
            const deptId = $('#bulkDeptSelect').val();
            const gradeId = $('#bulkGradeSelect').val();
            if (!deptId || !gradeId) {
                toastr.warning('Please select both a department and a grade.');
                return;
            }
            payload.department_id = deptId;
            payload.grade_id = gradeId;
        }

        $.post('{{ route("vvnr.email.recipients.bulk") }}', payload, function(res) {
            if (res.success) {
                $('#bulkModal').modal('hide');
                toastr.success(res.message);
                selectedRecipientIds = [];
                $('#checkAllRecipients').prop('checked', false);
                updateSelectedRecipients();
                loadRecipients();
                if ($('#pill-dept').hasClass('active')) loadDepartmentSummary();
                if ($('#pill-grade').hasClass('active')) loadGradeSummary();
            } else {
                toastr.error(res.message);
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Bulk update failed');
        });
    });

    // =========================================================================
    // 4. EMAIL LOGS LOGIC
    // =========================================================================
    function loadLogs(page = 1) {
        const params = {
            page: page,
            search: $('#logSearch').val(),
            notification_type: $('#logType').val(),
            is_sent: $('#logStatus').val(),
            date_from: $('#logDateFrom').val(),
            date_to: $('#logDateTo').val(),
        };

        $('#logsTableBody').html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="ri-loader-4-line ri-spin fs-20 me-1"></i> Loading VVNR email logs...</td></tr>');

        $.get('{{ route("vvnr.email.logs") }}', params, function(res) {
            if (!res.success) return;
            const data = res.data;
            const logs = data.logs;
            let rows = '';

            if (logs.length === 0) {
                rows = '<tr><td colspan="7" class="text-center py-4 text-muted">No email logs found for VVNR matching the criteria.</td></tr>';
            } else {
                logs.forEach(l => {
                    const statusBadge = l.is_sent 
                        ? '<span class="badge bg-success-subtle text-success fs-12"><i class="ri-check-line me-1"></i>Sent</span>' 
                        : '<span class="badge bg-danger-subtle text-danger fs-12"><i class="ri-error-warning-line me-1"></i>Failed</span>';

                    rows += `<tr>
                        <td><small class="font-monospace text-muted">${l.created_at}</small></td>
                        <td>
                            <strong>${l.recipient_name || 'N/A'}</strong><br>
                            <small class="text-muted">${l.recipient_emp_code || ''}</small>
                        </td>
                        <td>${l.recipient_email}</td>
                        <td>${l.subject}</td>
                        <td><span class="badge bg-light text-secondary">${l.notification_type}</span></td>
                        <td>${statusBadge}</td>
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-primary" onclick="viewLogDetail(${l.id})" title="View email content">
                                <i class="ri-file-search-line"></i> View
                            </button>
                        </td>
                    </tr>`;
                });
            }

            $('#logsTableBody').html(rows);
            renderPagination('#logPaginationContainer', data.pagination, loadLogs);
        });
    }

    $('#btnFilterLogs').on('click', () => loadLogs(1));
    $('#logSearch').on('keyup', function(e) { if (e.key === 'Enter') loadLogs(1); });
    $('#logType, #logStatus, #logDateFrom, #logDateTo').on('change', () => loadLogs(1));

    window.viewLogDetail = function(id) {
        $('#logModalRecipient').text('Loading...');
        $('#logModalEmail').text('');
        $('#logModalType').text('');
        $('#logModalDate').text('');
        $('#logModalSubject').text('');
        $('#logModalBody').html('<div class="text-center py-4"><i class="ri-loader-4-line ri-spin fs-24"></i> Loading email content...</div>');
        $('#logModalErrorContainer').hide();
        $('#logDetailModal').modal('show');

        $.get(`{{ url("vvnr/email-management/logs") }}/${id}`, function(res) {
            if (!res.success) {
                toastr.error(res.message || 'Log details not found');
                return;
            }
            const l = res.data;
            $('#logModalRecipient').text((l.recipient_name || 'N/A') + ' (' + (l.recipient_emp_code || '-') + ')');
            $('#logModalEmail').text(l.recipient_email || '-');
            $('#logModalType').text(l.notification_type || '-');
            $('#logModalDate').text(l.created_at || '-');
            $('#logModalSubject').text(l.subject || '-');
            $('#logModalBody').html(l.body_html || '<p class="text-muted">No HTML content logged</p>');

            if (l.error_message) {
                $('#logModalError').text(l.error_message);
                $('#logModalErrorContainer').show();
            } else {
                $('#logModalErrorContainer').hide();
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to load log detail');
            $('#logModalBody').html('<div class="alert alert-danger">Failed to load log details.</div>');
        });
    };

    // =========================================================================
    // 5. TEST EMAIL
    // =========================================================================
    $('#testEmailForm').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serialize();
        toastr.info('Sending test email...');
        $.post('{{ route("vvnr.email.test") }}', data, function(res) {
            if (res.success) {
                $('#testEmailModal').modal('hide');
                toastr.success(res.message);
                loadLogs(1);
            } else {
                toastr.error(res.message);
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to dispatch test email');
        });
    });

    // =========================================================================
    // 6. MONTHLY HIERARCHY SUMMARY LOGIC
    // =========================================================================
    let hierarchyDataCache = null;

    $('#tab-hierarchy-link').on('shown.bs.tab', function() {
        if (!hierarchyDataCache) {
            loadHierarchySummary();
        }
    });

    $('#hierarchyMonthSelect').on('change', function() {
        loadHierarchySummary();
    });

    $('#hierarchyL2Filter').on('change', function() {
        renderHierarchySummary(hierarchyDataCache);
    });

    window.loadHierarchySummary = function() {
        const month = $('#hierarchyMonthSelect').val();
        $('#hierarchyContainer').html('<div class="text-center py-5 text-muted"><i class="ri-loader-4-line ri-spin fs-24 mb-2 d-block"></i> Fetching VVNR hierarchy summary for month ' + month + '...</div>');

        $.get('{{ route("vvnr.email.hierarchy.summary") }}', { month: month }, function(res) {
            if (!res.success) {
                $('#hierarchyContainer').html(`<div class="alert alert-danger">${res.message || 'Failed to load summary'}</div>`);
                return;
            }

            hierarchyDataCache = res.data;

            // Populate L2 Manager Filter Options
            const currentFilterVal = $('#hierarchyL2Filter').val();
            let optHtml = '<option value="">All Reviewers / HODs (L2)</option>';
            if (hierarchyDataCache.l2_summaries) {
                hierarchyDataCache.l2_summaries.forEach(m => {
                    optHtml += `<option value="${m.manager_id}">${m.manager_name} (${m.emp_code})</option>`;
                });
            }
            $('#hierarchyL2Filter').html(optHtml);
            if (currentFilterVal) {
                $('#hierarchyL2Filter').val(currentFilterVal);
            }

            renderHierarchySummary(hierarchyDataCache);
        }).fail(function(xhr) {
            $('#hierarchyContainer').html(`<div class="alert alert-danger">${xhr.responseJSON?.message || 'Error communicating with server'}</div>`);
        });
    };

    function renderHierarchySummary(data) {
        if (!data) return;

        const totals = data.company_totals || {};
        $('#metricTotalHierarchy').text(totals.total_employees || 0);
        $('#metricNotSubmitted').text(totals.submission_pending || 0);
        $('#metricApprovalPending').text(totals.approval_pending || 0);
        $('#metricHardcopyPending').text(totals.hardcopy_pending || 0);
        $('#metricOtherPending').text(totals.other_pending || 0);
        $('#metricTotalPending').text(totals.total_pending || 0);

        const filterL2Id = $('#hierarchyL2Filter').val();
        let summaries = data.l2_summaries || [];
        if (filterL2Id) {
            summaries = summaries.filter(s => String(s.manager_id) === String(filterL2Id));
        }

        if (summaries.length === 0) {
            $('#hierarchyContainer').html('<div class="alert alert-info">No reporting hierarchy records found matching criteria.</div>');
            return;
        }

        let containerHtml = '';

        summaries.forEach(m => {
            let breakdownRows = '';
            (m.manager_breakdown || []).forEach(b => {
                breakdownRows += `
                    <tr>
                        <td class="fw-semibold">
                            ${b.manager_name}
                            ${b.emp_code ? `<span class="badge bg-light text-muted border ms-1">${b.emp_code}</span>` : ''}
                        </td>
                        <td class="text-center fw-bold">${b.total_employees}</td>
                        <td class="text-center ${b.submission_pending > 0 ? 'text-danger fw-bold' : 'text-muted'}">${b.submission_pending}</td>
                        <td class="text-center ${b.approval_pending > 0 ? 'text-warning fw-bold' : 'text-muted'}">${b.approval_pending}</td>
                        <td class="text-center ${b.hardcopy_pending > 0 ? 'text-info fw-bold' : 'text-muted'}">${b.hardcopy_pending}</td>
                        <td class="text-center ${b.other_pending > 0 ? 'text-secondary fw-bold' : 'text-muted'}">${b.other_pending}</td>
                        <td class="text-center fw-bold ${b.total_pending > 0 ? 'bg-danger-subtle text-danger' : 'text-success'}">${b.total_pending}</td>
                    </tr>
                `;
            });

            containerHtml += `
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-2 px-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary fs-12 px-2 py-1"><i class="ri-user-star-line me-1"></i>L2 Manager / HOD</span>
                            <span class="fw-bold fs-15 text-dark">${m.manager_name}</span>
                            <span class="badge bg-light text-muted border">${m.emp_code || 'No Code'}</span>
                            <span class="text-muted fs-12 ms-2"><i class="ri-mail-line me-1"></i>${m.email || '<span class="text-danger">No Email</span>'}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-12">
                                Total Pending: ${m.total_pending}
                            </span>
                            <button class="btn btn-xs btn-outline-success" onclick="dispatchHierarchySummary(${m.manager_id})">
                                <i class="ri-mail-send-line me-1"></i> Send Summary Email
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2 mb-3">
                            <div class="col-md-2 col-4">
                                <div class="p-2 border rounded text-center bg-white">
                                    <small class="text-muted fs-11 d-block">Hierarchy Staff</small>
                                    <span class="fw-bold fs-14">${m.total_employees}</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="p-2 border rounded text-center bg-white">
                                    <small class="text-muted fs-11 d-block">Not Submitted</small>
                                    <span class="fw-bold fs-14 text-danger">${m.submission_pending}</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="p-2 border rounded text-center bg-white">
                                    <small class="text-muted fs-11 d-block">Approval Pending</small>
                                    <span class="fw-bold fs-14 text-warning">${m.approval_pending}</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="p-2 border rounded text-center bg-white">
                                    <small class="text-muted fs-11 d-block">Hard Copy Pending</small>
                                    <span class="fw-bold fs-14 text-info">${m.hardcopy_pending}</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="p-2 border rounded text-center bg-white">
                                    <small class="text-muted fs-11 d-block">Other Pending</small>
                                    <span class="fw-bold fs-14 text-secondary">${m.other_pending}</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="p-2 border rounded text-center bg-danger-subtle">
                                    <small class="text-danger fw-bold fs-11 d-block">Total Pending</small>
                                    <span class="fw-bold fs-14 text-danger">${m.total_pending}</span>
                                </div>
                            </div>
                        </div>

                        <h6 class="fs-12 fw-bold text-uppercase text-muted mb-2">Manager-wise Breakdown (Reporting to ${m.manager_name}):</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover align-middle mb-0 fs-12">
                                <thead class="table-light">
                                    <tr>
                                        <th>Manager Name (L1)</th>
                                        <th class="text-center" style="width: 130px;">Total Employees</th>
                                        <th class="text-center" style="width: 150px;">Submission Pending</th>
                                        <th class="text-center" style="width: 140px;">Approval Pending</th>
                                        <th class="text-center" style="width: 150px;">Hard Copy Pending</th>
                                        <th class="text-center" style="width: 130px;">Other Pending</th>
                                        <th class="text-center" style="width: 120px;">Total Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${breakdownRows || '<tr><td colspan="7" class="text-center py-2 text-muted">No reporting managers found</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#hierarchyContainer').html(containerHtml);
    }

    window.dispatchHierarchySummary = function(targetManagerId = null) {
        const month = $('#hierarchyMonthSelect').val();
        const confirmMsg = targetManagerId 
            ? 'Dispatch consolidated monthly summary email to this Reviewer / HOD?'
            : 'Dispatch consolidated monthly summary emails to ALL Reviewers / HODs in VVNR?';

        if (!confirm(confirmMsg)) return;

        toastr.info('Dispatching hierarchy summary email(s)...');

        $.post('{{ route("vvnr.email.hierarchy.dispatch") }}', {
            _token: '{{ csrf_token() }}',
            month: month,
            target_l2_manager_id: targetManagerId || ''
        }, function(res) {
            if (res.success) {
                toastr.success(res.message);
                loadLogs(1);
            } else {
                toastr.error(res.message || 'Dispatch failed');
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Server error dispatching summary emails');
        });
    };

    // Helper Pagination Renderer
    function renderPagination(container, meta, callback) {
        if (!meta || meta.last_page <= 1) {
            $(container).html('');
            return;
        }

        let html = `<div><small class="text-muted">Showing page ${meta.current_page} of ${meta.last_page} (${meta.total} records)</small></div>`;
        html += '<ul class="pagination pagination-sm mb-0">';

        if (meta.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-p="${meta.current_page - 1}">Previous</a></li>`;
        }

        for (let i = 1; i <= meta.last_page; i++) {
            if (i === 1 || i === meta.last_page || (i >= meta.current_page - 2 && i <= meta.current_page + 2)) {
                html += `<li class="page-item ${i === meta.current_page ? 'active' : ''}"><a class="page-link" href="#" data-p="${i}">${i}</a></li>`;
            } else if (i === meta.current_page - 3 || i === meta.current_page + 3) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        if (meta.current_page < meta.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" data-p="${meta.current_page + 1}">Next</a></li>`;
        }

        html += '</ul>';
        $(container).html(html);

        $(container).find('.page-link').on('click', function(e) {
            e.preventDefault();
            const p = $(this).data('p');
            if (p) callback(p);
        });
    }
});
</script>
@endpush
