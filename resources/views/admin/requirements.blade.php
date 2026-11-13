@extends('layouts.app')

@section('content')
<div class="page-content">
<div class="container-fluid">

    {{-- Page Title --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 fw-bold"><i class="ri-file-list-3-line me-2 text-primary"></i>System Requirements</h4>
                    <p class="text-muted mb-0" style="font-size:13px;">Pending feature requests and system improvement requirements · {{ now()->format('d M Y') }}</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-warning-subtle text-warning px-3 py-2"><i class="ri-time-line me-1"></i>6 Requirements Pending</span>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2"><i class="ri-calendar-line me-1"></i>Raised: {{ now()->format('d M Y') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <div class="fs-2 fw-bold text-warning">6</div>
                    <div class="text-muted" style="font-size:12px;">Total Requirements</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <div class="fs-2 fw-bold text-danger">6</div>
                    <div class="text-muted" style="font-size:12px;">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <div class="fs-2 fw-bold text-info">2</div>
                    <div class="text-muted" style="font-size:12px;">Automation</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <div class="fs-2 fw-bold text-success">2</div>
                    <div class="text-muted" style="font-size:12px;">Validation</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <div class="fs-2 fw-bold text-purple" style="color:#7c3aed;">1</div>
                    <div class="text-muted" style="font-size:12px;">Report Enhancement</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body py-2">
                    <div class="fs-2 fw-bold text-primary">1</div>
                    <div class="text-muted" style="font-size:12px;">Notification</div>
                </div>
            </div>
        </div>
    </div>

    {{-- REQ 1: Promotion / Imprest Automation --}}
    <div class="card border-0 shadow-sm mb-4" id="req-1">
        <div class="card-header border-0 pb-0" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:12px 12px 0 0;">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:#dde6ff;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0; color: #1e3a8a;">
                        <i class="ri-coins-line text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary" style="font-size:10px;">REQ-001</span>
                            <span class="badge bg-info-subtle text-info" style="font-size:10px;"><i class="ri-robot-line me-1"></i>Automation</span>
                            <span class="badge bg-danger" style="font-size:10px;">High Priority</span>
                        </div>
                        <h5 class="mb-0 mt-1 fw-bold">Promotion / Imprest Automation</h5>
                    </div>
                </div>
                <span class="badge bg-warning-subtle text-warning px-3 py-2" style="font-size:11px;"><i class="ri-time-line me-1"></i>Pending</span>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3" style="font-size:13px;">Implement an automated imprest management process based on employee grade and department.</p>

            <div class="row g-3">
                {{-- Sub-req A --}}
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100" style="border-color:#bfdbfe !important;background:#f8fbff;">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary">A</span>
                            <strong style="font-size:13px;">New Joiners</strong>
                        </div>
                        <p style="font-size:12px;color:#374151;margin-bottom:0;">Based on the employee's <strong>grade</strong> and <strong>department</strong> (Sales/PD), the system should automatically generate an imprest request to Finance.</p>
                        <div class="mt-2 pt-2 border-top">
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge bg-light text-dark border" style="font-size:10px;">Trigger: New Employee Onboarding</span>
                                <span class="badge bg-light text-dark border" style="font-size:10px;">Target: Finance Team</span>
                                <span class="badge bg-light text-dark border" style="font-size:10px;">Based on: Grade + Department</span>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Sub-req B --}}
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100" style="border-color:#bfdbfe !important;background:#f8fbff;">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary">B</span>
                            <strong style="font-size:13px;">Employee Promotion</strong>
                        </div>
                        <p style="font-size:12px;color:#374151;margin-bottom:8px;">If an employee is promoted from <strong>J1/J2 → J3/J4/M1</strong>, automatically trigger a request to release the additional eligible imprest amount.</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-2" style="font-size:11px;">
                                <thead class="table-primary">
                                    <tr><th>Grade</th><th>Eligible Imprest</th><th>On Promotion</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td><span class="badge bg-secondary">J1</span> <span class="badge bg-secondary">J2</span></td><td class="fw-bold">₹ 20,000</td><td>—</td></tr>
                                    <tr><td><span class="badge bg-primary">J3</span> <span class="badge bg-primary">J4</span> <span class="badge bg-success">M1</span></td><td class="fw-bold">₹ 40,000</td><td class="text-success fw-bold">+₹ 20,000 auto-request</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge bg-light text-dark border" style="font-size:10px;">Trigger: Grade Change in HRIMS</span>
                            <span class="badge bg-light text-dark border" style="font-size:10px;">Auto: Finance Request</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 p-2 rounded" style="background:#fef3c7;border:1px solid #fcd34d;">
                <strong style="font-size:12px;color:#92400e;"><i class="ri-alert-line me-1"></i>Implementation Note:</strong>
                <span style="font-size:12px;color:#92400e;"> Requires HRIMS webhook/event on employee grade change → Laravel job to create imprest request → notify Finance team via notification system.</span>
            </div>
        </div>
    </div>

    {{-- REQ 2: Courier Date Validation --}}
    <div class="card border-0 shadow-sm mb-4" id="req-2">
        <div class="card-header border-0 pb-0" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-radius:12px 12px 0 0;">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:#16a34a;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="ri-calendar-check-line text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary" style="font-size:10px;">REQ-002</span>
                            <span class="badge bg-success-subtle text-success" style="font-size:10px;"><i class="ri-checkbox-circle-line me-1"></i>Validation</span>
                            <span class="badge bg-warning text-dark" style="font-size:10px;">Medium Priority</span>
                        </div>
                        <h5 class="mb-0 mt-1 fw-bold">Courier Date Validation</h5>
                    </div>
                </div>
                <span class="badge bg-warning-subtle text-warning px-3 py-2" style="font-size:11px;"><i class="ri-time-line me-1"></i>Pending</span>
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#374151;">Add a system validation for the <strong>Courier Date</strong> to ensure that only valid dates are entered and captured correctly as per the actual dispatch date.</p>
            <div class="row g-2 mt-1">
                <div class="col-md-4"><div class="p-2 rounded border text-center" style="font-size:11px;background:#f0fdf4;">
                    <i class="ri-calendar-line text-success d-block fs-4 mb-1"></i><strong>Date Format</strong><br>Only valid date formats accepted
                </div></div>
                <div class="col-md-4"><div class="p-2 rounded border text-center" style="font-size:11px;background:#f0fdf4;">
                    <i class="ri-history-line text-success d-block fs-4 mb-1"></i><strong>No Future Dates</strong><br>Courier date cannot be future-dated
                </div></div>
                <div class="col-md-4"><div class="p-2 rounded border text-center" style="font-size:11px;background:#f0fdf4;">
                    <i class="ri-error-warning-line text-success d-block fs-4 mb-1"></i><strong>Mandatory Field</strong><br>Cannot proceed without valid date
                </div></div>
            </div>
        </div>
    </div>

    {{-- REQ 3: Participant List Validation --}}
    <div class="card border-0 shadow-sm mb-4" id="req-3">
        <div class="card-header border-0 pb-0" style="background:linear-gradient(135deg,#fdf4ff,#f3e8ff);border-radius:12px 12px 0 0;">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:#ede7fe;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0; color: #4c1d95;">
                        <i class="ri-group-line text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary" style="font-size:10px;">REQ-003</span>
                            <span class="badge" style="font-size:10px;background:#f3e8ff;color:#7c3aed;"><i class="ri-checkbox-circle-line me-1"></i>Validation</span>
                            <span class="badge bg-danger" style="font-size:10px;">High Priority</span>
                        </div>
                        <h5 class="mb-0 mt-1 fw-bold">Participant List Validation</h5>
                    </div>
                </div>
                <span class="badge bg-warning-subtle text-warning px-3 py-2" style="font-size:11px;"><i class="ri-time-line me-1"></i>Pending</span>
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#374151;">Participant details should be <strong>mandatory</strong> while submitting activity-related expense claims.</p>

            <div class="row g-3">
                <div class="col-md-5">
                    <h6 class="fw-bold mb-2" style="font-size:12px;">Required Fields to Capture:</h6>
                    <ul style="font-size:12px;padding-left:16px;">
                        <li class="mb-1"><strong>Department</strong></li>
                        <li class="mb-1"><strong>Activity Type</strong></li>
                        <li class="mb-1"><strong>Participant List</strong></li>
                    </ul>
                    <h6 class="fw-bold mb-2 mt-3" style="font-size:12px;">Upload Formats Accepted:</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge" style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-size:10px;"><i class="ri-file-excel-line me-1"></i>Excel (.xlsx)</span>
                        <span class="badge" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:10px;"><i class="ri-file-pdf-line me-1"></i>PDF</span>
                        <span class="badge" style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;font-size:10px;"><i class="ri-image-line me-1"></i>Photo</span>
                    </div>
                </div>
                <div class="col-md-7">
                    <h6 class="fw-bold mb-2" style="font-size:12px;">Applicability by Department & Claim Type:</h6>
                    <table class="table table-sm table-bordered" style="font-size:11px;">
                        <thead class="table-light">
                            <tr><th>Department</th><th>Claim Type</th><th>Participant Mandatory</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-secondary">All Departments</span></td>
                                <td>Team Meetings</td>
                                <td><span class="badge bg-success">Yes</span> <small class="text-muted">(like Group Meal)</small></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">Sales</span></td>
                                <td>RCT</td>
                                <td><span class="badge bg-success">Yes</span></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">Sales</span></td>
                                <td>Retailer Meetings</td>
                                <td><span class="badge bg-success">Yes</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- REQ 4: Report Enhancement --}}
    <div class="card border-0 shadow-sm mb-4" id="req-4">
        <div class="card-header border-0 pb-0" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border-radius:12px 12px 0 0;">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:#fdefd2;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0; color: #7a4500;">
                        <i class="ri-file-chart-line text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary" style="font-size:10px;">REQ-004</span>
                            <span class="badge bg-warning-subtle text-warning" style="font-size:10px;"><i class="ri-bar-chart-line me-1"></i>Report Enhancement</span>
                            <span class="badge bg-info text-white" style="font-size:10px;">Low Priority</span>
                        </div>
                        <h5 class="mb-0 mt-1 fw-bold">Report Enhancement — Generation Timestamp</h5>
                    </div>
                </div>
                <span class="badge bg-warning-subtle text-warning px-3 py-2" style="font-size:11px;"><i class="ri-time-line me-1"></i>Pending</span>
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#374151;">The <strong>date and time of report generation</strong> should be displayed on all reports generated from the system for better tracking and audit purposes.</p>
            <div class="row g-2 mt-1">
                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background:#fffbeb;font-size:12px;">
                        <strong><i class="ri-check-line text-success me-1"></i>Expected Behavior:</strong>
                        <ul class="mb-0 mt-1" style="padding-left:16px;">
                            <li>Every report (Excel/PDF/CSV) should include a generation timestamp</li>
                            <li>Format: <code>Generated on: DD-MM-YYYY HH:MM:SS</code></li>
                            <li>Applies to all report modules in the system</li>
                            <li>Should include the name of the user who generated the report</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background:#fffbeb;font-size:12px;">
                        <strong><i class="ri-file-list-line text-warning me-1"></i>Applicable Reports:</strong>
                        <ul class="mb-0 mt-1" style="padding-left:16px;">
                            <li>Claim Stage Summary</li>
                            <li>TAT Analysis Report</li>
                            <li>Running KM Report</li>
                            <li>Payment Export</li>
                            <li>All other system-generated exports</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- REQ 5: Transfer Notification VVNR → VNR --}}
    <div class="card border-0 shadow-sm mb-4" id="req-5">
        <div class="card-header border-0 pb-0" style="background:linear-gradient(135deg,#eff6ff,#e0f2fe);border-radius:12px 12px 0 0;">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:#0284c7;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="ri-notification-3-line text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary" style="font-size:10px;">REQ-005</span>
                            <span class="badge bg-primary-subtle text-primary" style="font-size:10px;"><i class="ri-mail-send-line me-1"></i>Notification</span>
                            <span class="badge bg-warning text-dark" style="font-size:10px;">Medium Priority</span>
                        </div>
                        <h5 class="mb-0 mt-1 fw-bold">Transfer Notification — VVNR to VNR</h5>
                    </div>
                </div>
                <span class="badge bg-warning-subtle text-warning px-3 py-2" style="font-size:11px;"><i class="ri-time-line me-1"></i>Pending</span>
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#374151;">The system should generate an <strong>automatic notification</strong> whenever an employee is transferred from <strong>VVNR → VNR</strong>.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background:#f0f9ff;font-size:12px;">
                        <strong><i class="ri-flow-chart text-info me-1"></i>Trigger Flow:</strong>
                        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-danger px-3 py-2">VVNR Employee</span>
                            <i class="ri-arrow-right-line"></i>
                            <span class="badge bg-secondary px-3 py-2">Transfer Event in HRIMS</span>
                            <i class="ri-arrow-right-line"></i>
                            <span class="badge bg-success px-3 py-2">VNR Employee</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background:#f0f9ff;font-size:12px;">
                        <strong><i class="ri-mail-send-line text-info me-1"></i>Notification Recipients:</strong>
                        <ul class="mb-0 mt-1" style="padding-left:16px;">
                            <li>Finance Team (imprest/account settlement)</li>
                            <li>Previous Reporting Manager (VVNR)</li>
                            <li>New Reporting Manager (VNR)</li>
                            <li>HR Department</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- REQ 6: Transfer / Deputation Notification --}}
    <div class="card border-0 shadow-sm mb-4" id="req-6">
        <div class="card-header border-0 pb-0" style="background:linear-gradient(135deg,#fdf2f8,#fce7f3);border-radius:12px 12px 0 0;">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:#db2777;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="ri-map-pin-user-line text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary" style="font-size:10px;">REQ-006</span>
                            <span class="badge" style="font-size:10px;background:#fce7f3;color:#be185d;"><i class="ri-mail-send-line me-1"></i>Notification</span>
                            <span class="badge bg-warning text-dark" style="font-size:10px;">Medium Priority</span>
                        </div>
                        <h5 class="mb-0 mt-1 fw-bold">Transfer / Deputation Notification</h5>
                    </div>
                </div>
                <span class="badge bg-warning-subtle text-warning px-3 py-2" style="font-size:11px;"><i class="ri-time-line me-1"></i>Pending</span>
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#374151;">The system should generate an <strong>automatic notification</strong> whenever an employee is transferred or deputed to another location/department.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background:#fdf2f8;font-size:12px;">
                        <strong><i class="ri-map-pin-2-line" style="color:#db2777;"></i> Applicable Scenarios:</strong>
                        <ul class="mb-0 mt-1" style="padding-left:16px;">
                            <li><strong>Location Transfer</strong> — Employee moves to a new HQ/territory</li>
                            <li><strong>Department Transfer</strong> — Employee changes department</li>
                            <li><strong>Deputation</strong> — Temporary assignment to another division</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background:#fdf2f8;font-size:12px;">
                        <strong><i class="ri-mail-send-line" style="color:#db2777;"></i> Notification Details:</strong>
                        <ul class="mb-0 mt-1" style="padding-left:16px;">
                            <li>Previous & New Reporting Manager notified</li>
                            <li>Finance Team notified for imprest/advance settlement</li>
                            <li>Employee notified of effective date</li>
                            <li>HR Department alerted</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Implementation Roadmap --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark text-white" style="border-radius:12px 12px 0 0;">
            <h5 class="mb-0 fw-bold"><i class="ri-road-map-line me-2"></i>Implementation Roadmap</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle" style="font-size:12px;">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:70px;">REQ #</th>
                            <th>Requirement</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Complexity</th>
                            <th>Modules Affected</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><a href="#req-1" class="fw-bold text-primary">REQ-001</a></td>
                            <td>Promotion / Imprest Automation</td>
                            <td><span class="badge bg-info-subtle text-info">Automation</span></td>
                            <td><span class="badge bg-danger">High</span></td>
                            <td><span class="badge bg-warning text-dark">High</span></td>
                            <td>HRIMS, Finance, Imprest Module</td>
                            <td><span class="badge bg-info text-white">In Progress</span></td>
                        </tr>
                        <tr>
                            <td><a href="#req-2" class="fw-bold text-primary">REQ-002</a></td>
                            <td>Courier Date Validation</td>
                            <td><span class="badge bg-success-subtle text-success">Validation</span></td>
                            <td><span class="badge bg-warning text-dark">Medium</span></td>
                            <td><span class="badge bg-success">Low</span></td>
                            <td>Verifier, Claim Submission</td>
                            <td><span class="badge bg-success">Done</span></td>
                        </tr>
                        <tr>
                            <td><a href="#req-3" class="fw-bold text-primary">REQ-003</a></td>
                            <td>Participant List Validation</td>
                            <td><span class="badge bg-success-subtle text-success">Validation</span></td>
                            <td><span class="badge bg-danger">High</span></td>
                            <td><span class="badge bg-warning text-dark">Medium</span></td>
                            <td>Upload Bill, Activity Claim</td>
                            <td><span class="badge bg-success">Done</span></td>
                        </tr>
                        <tr>
                            <td><a href="#req-4" class="fw-bold text-primary">REQ-004</a></td>
                            <td>Report Timestamp</td>
                            <td><span class="badge bg-warning-subtle text-warning">Report Enhancement</span></td>
                            <td><span class="badge bg-info text-white">Low</span></td>
                            <td><span class="badge bg-success">Low</span></td>
                            <td>All Report Exports</td>
                            <td><span class="badge bg-success">Done</span></td>
                        </tr>
                        <tr>
                            <td><a href="#req-5" class="fw-bold text-primary">REQ-005</a></td>
                            <td>Transfer Notification (VVNR→VNR)</td>
                            <td><span class="badge bg-primary-subtle text-primary">Notification</span></td>
                            <td><span class="badge bg-warning text-dark">Medium</span></td>
                            <td><span class="badge bg-warning text-dark">Medium</span></td>
                            <td>HRIMS, Notification System</td>
                            <td><span class="badge bg-info text-white">In Progress</span></td>
                        </tr>
                        <tr>
                            <td><a href="#req-6" class="fw-bold text-primary">REQ-006</a></td>
                            <td>Transfer / Deputation Notification</td>
                            <td><span class="badge bg-primary-subtle text-primary">Notification</span></td>
                            <td><span class="badge bg-warning text-dark">Medium</span></td>
                            <td><span class="badge bg-warning text-dark">Medium</span></td>
                            <td>HRIMS, Notification System</td>
                            <td><span class="badge bg-info text-white">In Progress</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>
@endsection
