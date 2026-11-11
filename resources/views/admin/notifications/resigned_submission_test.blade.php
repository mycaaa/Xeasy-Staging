@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-white px-3 py-2 rounded shadow-sm">
                    <div>
                        <h4 class="mb-1 text-primary fw-semibold">
                            <i class="ri-mail-send-fill me-2"></i>Resigned Employees &mdash; Final Expense Submission Reminder
                        </h4>
                        <p class="text-muted mb-0 fs-13">
                            Multi-Company Support &bull; Live API Tester &bull; Template Editor &bull; Automation Settings &bull; Filterable Send Logs
                        </p>
                    </div>
                    <div class="page-title-right d-flex gap-2">
                        <button type="button" class="btn btn-soft-primary btn-sm" onclick="location.reload();">
                            <i class="ri-refresh-line align-middle me-1"></i> Refresh
                        </button>
                        <a href="{{ route('admin.resigned-submission.docs.download') }}" class="btn btn-outline-success btn-sm" title="Download API & Cron Documentation in Markdown">
                            <i class="ri-download-2-line align-middle me-1"></i> Download API Docs
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#viewApiDocsModal">
                            <i class="ri-code-s-slash-line align-middle me-1"></i> API Docs &amp; cURL
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate border-0 shadow-sm h-100" style="border-left: 4px solid #dc3545 !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">With Pending Claims</p>
                                <h3 class="mb-0 fw-bold text-danger">{{ $stats['pending_claims_count'] }}</h3>
                                <p class="text-muted fs-11 mt-1 mb-0">{{ $stats['all_resigned_count'] }} Total Resigned ({{ $stats['no_pending_count'] }} No Claims)</p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-3">
                                    <i class="ri-alert-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate border-0 shadow-sm h-100" style="border-left: 4px solid #198754 !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Cron Reminder Hits</p>
                                <h3 class="mb-0 fw-bold text-success">{{ $stats['cron_reminders'] }}</h3>
                                <p class="text-muted fs-11 mt-1 mb-0">Automated schedule executions</p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">
                                    <i class="ri-timer-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate border-0 shadow-sm h-100" style="border-left: 4px solid #0dcaf0 !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Total Dispatched</p>
                                <h3 class="mb-0 fw-bold text-info">{{ $stats['total_sent'] }}</h3>
                                <p class="text-muted fs-11 mt-1 mb-0">Total emails sent (API + Cron)</p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle text-info rounded-circle fs-3">
                                    <i class="ri-mail-check-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate border-0 shadow-sm h-100" style="border-left: 4px solid #ffc107 !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Awaiting 1st Reminder</p>
                                <h3 class="mb-0 fw-bold text-warning">{{ $stats['pending_reminders'] }}</h3>
                                <p class="text-muted fs-11 mt-1 mb-0">Reminder count = 0</p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3">
                                    <i class="ri-alarm-warning-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs nav-tabs-custom nav-success mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab-api-tester" role="tab">
                    <i class="ri-terminal-box-line me-1"></i> API Tester &amp; Live Preview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-employees" role="tab">
                    <i class="ri-team-line me-1"></i> Resigned Employees Directory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-template" role="tab">
                    <i class="ri-edit-2-line me-1"></i> Email Template Editor
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-settings" role="tab">
                    <i class="ri-settings-4-line me-1"></i> Reminder Schedule Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-logs" role="tab" id="tab-logs-link">
                    <i class="ri-file-list-3-line me-1"></i> Email Send Logs &amp; Filters
                </a>
            </li>
        </ul>

        <div class="tab-content text-muted">

            <!-- TAB 1: API Tester & Live Preview -->
            <div class="tab-pane active" id="tab-api-tester" role="tabpanel">
                <div class="row g-3 mb-4">
                    <!-- Left: Request Tester -->
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fs-14 fw-semibold text-dark">
                                    <i class="ri-send-plane-2-line me-1 text-primary"></i> Dispatch Email via API
                                </h5>
                                <span class="badge bg-success-subtle text-success fs-11">POST /api/notifications/resigned-submission/send</span>
                            </div>
                            <div class="card-body">
                                <form id="apiTestForm" onsubmit="return false;">
                                    <!-- Company Scope -->
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold fs-13">Company Scope</label>
                                        <select class="form-select" id="companyScopeSelect">
                                            <option value="all">-- All Companies (Not Bound to Specific Company) --</option>
                                            @foreach($companies as $cId => $cName)
                                                <option value="{{ $cId }}" {{ $selectedCompany == $cId ? 'selected' : '' }}>
                                                    {{ $cName }} (ID: {{ $cId }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Target Employee -->
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold fs-13">Target Employee (With Pending Claims)</label>
                                        <select class="form-select" id="targetEmployeeSelect">
                                            <option value="" disabled>-- Select Resigned Employee --</option>
                                            @foreach($employees as $emp)
                                                <option value="{{ $emp->EmployeeID }}"
                                                    data-company-id="{{ $emp->CompanyId }}"
                                                    data-company="{{ $emp->company_name }}"
                                                    data-name="{{ $emp->employee_name }}"
                                                    data-code="{{ $emp->EmpCode }}"
                                                    data-email="{{ $emp->email }}"
                                                    data-dept="{{ $emp->department_name }}"
                                                    data-dor="{{ $emp->DateOfResignation }}"
                                                    data-dos="{{ $emp->DateOfSepration }}"
                                                    data-count="{{ $emp->reminder_count }}"
                                                    data-pending-claims="{{ $emp->pending_claim_count ?? 0 }}"
                                                    data-pending-amount="{{ $emp->pending_claim_amount ?? 0 }}"
                                                    data-pending-months="{{ $emp->pending_months_text ?? '' }}"
                                                    {{ $loop->first ? 'selected' : '' }}>
                                                    [{{ $emp->company_name }}] {{ $emp->EmpCode }} &bull; {{ $emp->employee_name }} ({{ $emp->pending_claim_count ?? 0 }} Claims - ₹{{ number_format($emp->pending_claim_amount ?? 0) }} Pending | Reminders: {{ $emp->reminder_count }})
                                                </option>
                                            @endforeach
                                            <option value="all" class="fw-bold text-danger">-- [BATCH] All Eligible Resigned Employees with Pending Claims ({{ $stats['pending_claims_count'] }} Employees) --</option>
                                        </select>
                                        <div class="form-text fs-12 text-muted">
                                            Variable <code class="text-primary">&#123;&#123;employee_name&#125;&#125;</code> and company name will be automatically populated.
                                        </div>
                                    </div>

                                    <!-- Override Test Email -->
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold fs-13 d-flex justify-content-between">
                                            <span>Override / Test Recipient Email</span>
                                            <span class="badge bg-light text-muted fw-normal">Optional</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="ri-at-line"></i></span>
                                            <input type="email" class="form-control" id="overrideEmailInput" placeholder="e.g. query@vnrseeds.com" aria-label="e.g. query@vnrseeds.com">
                                        </div>
                                        <div class="form-text fs-12 text-info">
                                            <i class="ri-information-line me-1"></i>If entered, test email is routed to <strong>this address</strong> without notifying the employee.
                                        </div>
                                    </div>

                                    <!-- Subject -->
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold fs-13">Subject</label>
                                        <input type="text" class="form-control" id="subjectInput" value="{{ $template->subject ?: $defaultSubject }}">
                                    </div>

                                    <!-- Trigger Mode Switch -->
                                    <div class="mb-4 p-3 bg-light rounded border">
                                        <label class="form-label fw-semibold fs-13 mb-2">Trigger Mode</label>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="isCronSwitch">
                                            <label class="form-check-label fw-medium fs-13" for="isCronSwitch">
                                                Simulate Cron Job Hit (Increments Reminder Count)
                                            </label>
                                        </div>
                                        <div class="text-muted fs-12" id="modeDescription">
                                            <i class="ri-arrow-right-s-line text-primary"></i> <strong>Standard API mode:</strong> Dispatches email and records in <code>eml_send_log</code> without incrementing cron hit count.
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-primary flex-grow-1" id="btnSendApi">
                                            <i class="ri-send-plane-fill me-1"></i> Send via API
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="btnRefreshPreview">
                                            <i class="ri-refresh-line me-1"></i> Refresh Preview
                                        </button>
                                    </div>
                                </form>

                                <!-- API Response Box -->
                                <div class="mt-4" id="apiResponseWrapper" style="display: none;">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-semibold fs-12 text-uppercase text-muted">API Response:</span>
                                        <span class="badge" id="apiStatusBadge"></span>
                                    </div>
                                    <pre class="bg-dark text-success p-3 rounded fs-12 mb-0" id="apiResponseBox" style="max-height: 180px; overflow-y: auto;"></pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Live Preview -->
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title mb-0 fs-14 fw-semibold text-dark">
                                        <i class="ri-mail-open-line me-1 text-primary"></i> Live Email Template Preview
                                    </h5>
                                    <span class="fs-12 text-muted">Variables <code class="text-primary">&#123;&#123;employee_name&#125;&#125;</code> and <code class="text-primary">&#123;&#123;company_name&#125;&#125;</code> rendered live</span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary fs-12 px-2 py-1">
                                    <span id="previewRecipientBadge">To: {{ $firstEmployee ? ($firstEmployee->email ?: 'No email') : 'N/A' }}</span>
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between text-muted fs-12">
                                    <div>
                                        <strong>Subject:</strong> <span id="previewSubjectText" class="text-dark fw-medium">{{ $template->subject ?: $defaultSubject }}</span>
                                    </div>
                                    <div>
                                        <span class="badge bg-secondary-subtle text-secondary" id="previewEmployeeBadge">
                                            {{ $firstEmployee ? $firstEmployee->employee_name : 'Employee' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="preview-container" style="height: 540px; overflow-y: auto; background-color: #f4f6f8; padding: 15px;">
                                    <iframe id="emailPreviewFrame" style="width: 100%; height: 100%; border: none; background: transparent;"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Resigned Employees Directory -->
            <div class="tab-pane" id="tab-employees" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0 fw-bold text-dark">
                                <i class="ri-team-line text-primary me-1"></i> Resigned Employees &amp; Reminder Status
                            </h5>
                            <span class="badge bg-danger-subtle text-danger fs-12">{{ $employees->count() }} Listed</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <!-- Pending Claims Filter -->
                            <select class="form-select form-select-sm" id="employeeDirectoryPendingFilter" style="width: 220px;" onchange="window.location.href = '?company_id={{ $selectedCompany ?? '' }}&pending_only=' + (this.value === 'pending' ? '1' : '0');">
                                <option value="pending" {{ $pendingOnly ? 'selected' : '' }}>Pending Claims Only ({{ $stats['pending_claims_count'] }})</option>
                                <option value="all" {{ !$pendingOnly ? 'selected' : '' }}>All Resigned Employees ({{ $stats['all_resigned_count'] }})</option>
                            </select>

                            <!-- Company Filter -->
                            <select class="form-select form-select-sm" id="employeeDirectoryCompanyFilter" style="width: 200px;">
                                <option value="all">All Companies</option>
                                @foreach($companies as $cId => $cName)
                                    <option value="{{ $cId }}">{{ $cName }}</option>
                                @endforeach
                            </select>
                            <input type="text" class="form-control form-control-sm" id="employeeSearchInput" placeholder="Search employee..." aria-label="Search employee" style="width: 180px;">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnBatchCronSimulation">
                                <i class="ri-play-circle-line me-1"></i> Run Batch Cron Simulation
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="resignedEmployeesTable">
                                <thead class="table-light text-muted fs-12 text-uppercase">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Company</th>
                                        <th>Code</th>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Pending Claims for Submission</th>
                                        <th>Resignation Date</th>
                                        <th>Separation Date</th>
                                        <th>Email</th>
                                        <th class="text-center">Cron Hit Count</th>
                                        <th class="text-center">Total Sent</th>
                                        <th>Last Sent</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($employees as $index => $emp)
                                        <tr id="row-emp-{{ $emp->EmployeeID }}" data-company-id="{{ $emp->CompanyId }}" class="employee-dir-row">
                                            <td class="text-muted fw-semibold">{{ $index + 1 }}</td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">
                                                    {{ $emp->company_name }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border fw-medium px-2 py-1 fs-12">
                                                    {{ $emp->EmpCode }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark fs-13">{{ $emp->employee_name }}</div>
                                                <small class="text-muted">ID: {{ $emp->EmployeeID }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary fs-11">
                                                    {{ $emp->department_name ?: 'General' }}
                                                </span>
                                            </td>
                                            <td>
                                                @if(!empty($emp->has_pending_claims))
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-12 px-2 py-1">
                                                        <i class="ri-alert-line me-1"></i>{{ $emp->pending_claim_count }} Claims (₹{{ number_format($emp->pending_claim_amount) }})
                                                    </span>
                                                    <div class="small text-muted mt-1" style="font-size: 11px;">
                                                        {{ $emp->pending_months_text }}
                                                    </div>
                                                @else
                                                    <span class="badge bg-success-subtle text-success fs-11">
                                                        <i class="ri-check-line me-1"></i>0 Pending Claims
                                                    </span>
                                                    <div class="small text-muted mt-1" style="font-size: 11px;">All submitted</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($emp->DateOfResignation && \App\Support\ZeroDate::has($emp->DateOfResignation))
                                                    <span class="text-danger fw-medium fs-12">
                                                        <i class="ri-calendar-line me-1"></i>{{ \Carbon\Carbon::parse($emp->DateOfResignation)->format('d-M-Y') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted fs-12">&mdash;</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($emp->DateOfSepration && \App\Support\ZeroDate::has($emp->DateOfSepration))
                                                    <span class="text-dark fs-12">
                                                        {{ \Carbon\Carbon::parse($emp->DateOfSepration)->format('d-M-Y') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted fs-12">&mdash;</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($emp->email)
                                                    <a href="mailto:{{ $emp->email }}" class="text-primary fs-12 text-decoration-none">
                                                        <i class="ri-mail-line me-1"></i>{{ $emp->email }}
                                                    </a>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning fs-11">No Email</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge {{ $emp->reminder_count > 0 ? 'bg-success' : 'bg-light text-muted border' }} fs-12 px-2 py-1" id="count-badge-{{ $emp->EmployeeID }}">
                                                    {{ $emp->reminder_count }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info-subtle text-info fs-12 px-2 py-1" id="total-badge-{{ $emp->EmployeeID }}">
                                                    {{ $emp->total_sent_count }}
                                                </span>
                                            </td>
                                            <td class="fs-12 text-muted" id="last-sent-{{ $emp->EmployeeID }}">
                                                @if($emp->last_sent_at)
                                                    <div>{{ \Carbon\Carbon::parse($emp->last_sent_at)->format('d-M-Y H:i') }}</div>
                                                    <small class="badge bg-light text-muted border text-uppercase" style="font-size: 9px;">
                                                        via {{ $emp->last_trigger_type ?: 'cron' }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary"
                                                        onclick="selectAndPreview({{ $emp->EmployeeID }})" title="Preview in Console">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                    @if(!empty($emp->has_pending_claims))
                                                        <button type="button" class="btn btn-outline-success"
                                                            onclick="quickSend({{ $emp->EmployeeID }}, false)" title="Send Standard API">
                                                            <i class="ri-send-plane-line"></i> API
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning"
                                                            onclick="quickSend({{ $emp->EmployeeID }}, true)" title="Simulate Cron Hit (Increments Reminder Count)">
                                                            <i class="ri-timer-flash-line"></i> Cron
                                                        </button>
                                                    @else
                                                        <button type="button" class="btn btn-light text-muted border" disabled title="No pending claims - notification not required">
                                                            <i class="ri-check-line"></i> Done
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="13" class="text-center py-4 text-muted">
                                                <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                                No eligible resigned employees with pending claims found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Email Template Editor -->
            <div class="tab-pane" id="tab-template" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                        <div>
                            <h5 class="card-title mb-1 fw-bold text-dark">
                                <i class="ri-edit-2-line text-primary me-1"></i> Customize Email Template
                            </h5>
                            <p class="text-muted fs-12 mb-0">
                                Updates are saved to <code>eml_email_templates</code> and applied dynamically to all outgoing emails.
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetTemplateDefault">
                                <i class="ri-restart-line me-1"></i> Reset to Default
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="btnSaveTemplate">
                                <i class="ri-save-line me-1"></i> Save Template
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Available Variables Badges -->
                        <div class="mb-3 p-3 bg-light rounded border">
                            <div class="fw-semibold fs-12 text-uppercase text-muted mb-2">
                                <i class="ri-price-tag-3-line me-1"></i> Click to Insert Available Variables into Template:
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm var-pill" data-var="@{{employee_name}}">
                                    &#123;&#123;employee_name&#125;&#125;
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm var-pill" data-var="@{{emp_code}}">
                                    &#123;&#123;emp_code&#125;&#125;
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm var-pill" data-var="@{{company_name}}">
                                    &#123;&#123;company_name&#125;&#125;
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm var-pill" data-var="@{{department}}">
                                    &#123;&#123;department&#125;&#125;
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm var-pill" data-var="@{{resignation_date}}">
                                    &#123;&#123;resignation_date&#125;&#125;
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm var-pill" data-var="@{{separation_date}}">
                                    &#123;&#123;separation_date&#125;&#125;
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm var-pill" data-var="@{{query_email}}">
                                    &#123;&#123;query_email&#125;&#125;
                                </button>
                            </div>
                        </div>

                        <form id="templateEditorForm">
                            <div class="mb-3">
                                <label class="form-label fw-semibold fs-13">Subject Line <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="templateSubjectInput" value="{{ $template->subject ?: $defaultSubject }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold fs-13">Email Body HTML <span class="text-danger">*</span></label>
                                <textarea class="form-control font-monospace fs-12" id="templateHtmlTextarea" rows="16" style="tab-size: 4;">{{ $template->body_html ?: $samplePreview }}</textarea>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TAB 4: Reminder Schedule Settings -->
            <div class="tab-pane" id="tab-settings" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                        <div>
                            <h5 class="card-title mb-1 fw-bold text-dark">
                                <i class="ri-settings-4-line text-primary me-1"></i> When Reminder Will Hit &mdash; Automation Settings
                            </h5>
                            <p class="text-muted fs-12 mb-0">
                                Configure when and how the automated cron job triggers reminders for resigned employees.
                            </p>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" id="btnSaveSettings">
                            <i class="ri-save-line me-1"></i> Save Settings
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="settingsForm">
                            <div class="row g-4">
                                <!-- Automation Master Toggle -->
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label class="form-label fw-semibold fs-14 mb-0">Automated Cron Execution</label>
                                            <div class="form-check form-switch fs-18 mb-0">
                                                <input class="form-check-input" type="checkbox" id="settingsIsActive" {{ $settings->is_active ? 'checked' : '' }}>
                                            </div>
                                        </div>
                                        <p class="text-muted fs-12 mb-0">
                                            When enabled, the cron command <code>php artisan notifications:resigned-submission</code> scheduled in <code>bootstrap/app.php</code> will automatically evaluate eligible employees and dispatch reminder emails.
                                        </p>
                                    </div>
                                </div>

                                <!-- Cron Send Time & Frequency -->
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="form-label fw-semibold fs-13">Daily Send Time</label>
                                                <input type="time" class="form-control" id="settingsSendTime" value="{{ $settings->send_time ?: '09:00' }}">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label fw-semibold fs-13">Frequency</label>
                                                <select class="form-select" id="settingsFrequency">
                                                    <option value="daily" {{ $settings->frequency == 'daily' ? 'selected' : '' }}>Daily</option>
                                                    <option value="weekdays" {{ $settings->frequency == 'weekdays' ? 'selected' : '' }}>Weekdays Only (Mon-Fri)</option>
                                                    <option value="weekly" {{ $settings->frequency == 'weekly' ? 'selected' : '' }}>Weekly (Every Monday)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-text fs-12 text-muted mt-2">
                                            Current Laravel schedule: Daily at <strong>09:00 AM</strong>.
                                        </div>
                                    </div>
                                </div>

                                <!-- Min Interval & Max Reminders -->
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <label class="form-label fw-semibold fs-13">Minimum Interval Between Reminders (Days)</label>
                                        <div class="input-group mb-2">
                                            <input type="number" class="form-control" id="settingsMinDays" value="{{ $settings->min_days_between_reminders }}" min="1" max="30">
                                            <span class="input-group-text">days</span>
                                        </div>
                                        <p class="text-muted fs-12 mb-0">
                                            Prevents spamming the employee daily. If a reminder was sent within the last N days, the cron job will skip them.
                                        </p>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border h-100">
                                        <label class="form-label fw-semibold fs-13">Max Reminders Per Employee</label>
                                        <div class="input-group mb-2">
                                            <input type="number" class="form-control" id="settingsMaxReminders" value="{{ $settings->max_reminders_per_employee }}" min="0" max="50">
                                            <span class="input-group-text">hits</span>
                                        </div>
                                        <p class="text-muted fs-12 mb-0">
                                            Stop sending automatic reminders once this reminder count is reached (Enter <code>0</code> for unlimited).
                                        </p>
                                    </div>
                                </div>

                                <!-- Target Companies for Cron -->
                                <div class="col-md-12">
                                    <div class="p-3 bg-light rounded border">
                                        <label class="form-label fw-semibold fs-13 d-block mb-2">
                                            Target Companies for Automated Cron (Unchecked companies will be skipped during cron run)
                                        </label>
                                        <div class="d-flex flex-wrap gap-4">
                                            @php
                                                $allowedCos = is_array($settings->target_companies) ? $settings->target_companies : [1, 2, 3, 4, 11];
                                            @endphp
                                            @foreach($companies as $cId => $cName)
                                                <div class="form-check">
                                                    <input class="form-check-input settings-company-chk" type="checkbox" value="{{ $cId }}" id="chkCompany{{ $cId }}" {{ in_array($cId, $allowedCos) ? 'checked' : '' }}>
                                                    <label class="form-check-label fs-13" for="chkCompany{{ $cId }}">
                                                        {{ $cName }} (ID: {{ $cId }})
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <!-- Query Support Email -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Finance Support / Clarification Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-mail-line"></i></span>
                                        <input type="email" class="form-control" id="settingsQueryEmail" value="{{ $settings->query_email ?: 'query@vnrseeds.com' }}">
                                    </div>
                                    <div class="form-text fs-12 text-muted">
                                        Substituted into <code>&#123;&#123;query_email&#125;&#125;</code> in the email body.
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TAB 5: Email Send Logs & Advanced Filters -->
            <div class="tab-pane" id="tab-logs" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h5 class="card-title mb-1 fw-bold text-dark">
                                    <i class="ri-file-list-3-line text-primary me-1"></i> Email Send History &amp; Logs
                                </h4>
                                <p class="text-muted fs-12 mb-0">Records from <code>eml_send_log</code> with full filtering &amp; body inspection</p>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefreshLogs">
                                <i class="ri-refresh-line me-1"></i> Refresh Logs
                            </button>
                        </div>

                        <!-- Filter Controls Bar -->
                        <div class="p-3 bg-light rounded border">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label fs-12 fw-semibold text-muted mb-1">Company Filter</label>
                                    <select class="form-select form-select-sm" id="filterLogCompany">
                                        <option value="all">All Companies</option>
                                        @foreach($companies as $cId => $cName)
                                            <option value="{{ $cId }}">{{ $cName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fs-12 fw-semibold text-muted mb-1">Status</label>
                                    <select class="form-select form-select-sm" id="filterLogStatus">
                                        <option value="all">All Statuses</option>
                                        <option value="sent">Sent (Success)</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fs-12 fw-semibold text-muted mb-1">From Date</label>
                                    <input type="date" class="form-control form-control-sm" id="filterLogFromDate">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fs-12 fw-semibold text-muted mb-1">To Date</label>
                                    <input type="date" class="form-control form-control-sm" id="filterLogToDate">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-12 fw-semibold text-muted mb-1">Keyword Search</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" id="filterLogSearch" placeholder="Name, code, or email..." aria-label="Name, code, or email">
                                        <button class="btn btn-primary" type="button" id="btnApplyLogFilters">
                                            <i class="ri-filter-line me-1"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 fs-12" id="filteredLogsTable">
                                <thead class="table-light text-muted text-uppercase">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Company</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th class="text-center">Reminder Hit</th>
                                        <th>Status</th>
                                        <th>Date &amp; Time</th>
                                        <th>Error Message</th>
                                        <th class="text-end">View</th>
                                    </tr>
                                </thead>
                                <tbody id="logsTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">Loading logs...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Modal: View Email Body Content -->
<div class="modal fade" id="viewEmailBodyModal" tabindex="-1" aria-labelledby="viewEmailBodyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-semibold text-dark" id="viewEmailBodyModalLabel">
                        <i class="ri-mail-open-line me-1 text-primary"></i> Dispatched Email Content
                    </h5>
                    <small class="text-muted" id="modalEmailMeta"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background-color: #f4f6f8;">
                <iframe id="modalEmailBodyIframe" style="width: 100%; height: 480px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- API Documentation & Curl Modal -->
<div class="modal fade" id="viewApiDocsModal" tabindex="-1" aria-labelledby="viewApiDocsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="modal-title fw-semibold text-dark" id="viewApiDocsModalLabel">
                        <i class="ri-code-box-line me-1 text-primary"></i> Resigned Reminder API &amp; Server Cron Documentation
                    </h5>
                    <small class="text-muted">Complete guide for developers, API integrations, and server administrators</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('admin.resigned-submission.docs.download') }}" class="btn btn-success btn-sm">
                        <i class="ri-download-2-line me-1"></i> Download (.md)
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body" style="max-height: 540px; overflow-y: auto;">
                <!-- Section 1: Business Condition -->
                <div class="alert alert-info border-info d-flex align-items-center mb-3 py-2 px-3 fs-12">
                    <i class="ri-information-line fs-18 me-2 flex-shrink-0"></i>
                    <div>
                        <strong>Strict Filter Rule:</strong> Reminders are only dispatched to employees who have <strong>unsubmitted expense claims pending for final submission</strong> in active financial years. Employees with 0 pending claims are automatically skipped.
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2">Endpoint 1: Send Resigned Reminder Email</h6>
                <div class="p-2 bg-light border rounded mb-3">
                    <span class="badge bg-success me-2">POST</span>
                    <code>{{ url('/api/notifications/resigned-submission/send') }}</code>
                </div>

                <p class="fs-13 text-muted">Payload Parameters (JSON):</p>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered fs-12 mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Field</th>
                                <th>Type</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>employee_id</code></td>
                                <td>integer | null</td>
                                <td>Specific employee ID. If omitted or null, runs batch send for eligible employees with pending claims.</td>
                            </tr>
                            <tr>
                                <td><code>company_id</code></td>
                                <td>integer | null</td>
                                <td>Filter by specific company (1=VNR Seeds, 2=Group Concern, 3=Nursery, 4=Teamlease, 11=VVNR). If omitted, runs across all companies.</td>
                            </tr>
                            <tr>
                                <td><code>test_email</code></td>
                                <td>string | null</td>
                                <td>Override recipient email address (safely test delivery without emailing actual employee).</td>
                            </tr>
                            <tr>
                                <td><code>is_cron</code></td>
                                <td>boolean</td>
                                <td>Set <code>true</code> to increment the cron <code>reminder_count</code> in <code>eml_resigned_reminders</code>.</td>
                            </tr>
                            <tr>
                                <td><code>subject</code></td>
                                <td>string | null</td>
                                <td>Custom subject. Defaults to template subject.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-bold text-dark mb-2">Example cURL Request:</h6>
                <pre class="bg-dark text-light p-3 rounded fs-12 mb-3"><code>curl -X POST "{{ url('/api/notifications/resigned-submission/send') }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "employee_id": {{ $firstEmployee ? $firstEmployee->EmployeeID : 1644 }},
    "test_email": "query@vnrseeds.com",
    "is_cron": true
  }'</code></pre>

                <!-- Section: Server Cron Setup Guide -->
                <div class="card border border-warning-subtle bg-warning-subtle bg-opacity-10 mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-dark mb-1 d-flex align-items-center">
                            <i class="ri-timer-line text-warning me-2 fs-16"></i> Server Cron Setup Guide &mdash; What To Do On Your Server
                        </h6>
                        <p class="text-muted fs-12 mb-2">
                            To run reminder checks automatically every day at 09:00 AM, follow either of the methods below:
                        </p>

                        <div class="mb-3">
                            <span class="badge bg-primary mb-1">Option 1: Recommended (Laravel Scheduler)</span>
                            <p class="fs-12 text-muted mb-1">
                                If your server crontab already has Laravel's master scheduler running, <strong>NO extra cron is needed</strong>! The job is registered in <code>bootstrap/app.php</code>.
                            </p>
                            <p class="fs-12 text-muted mb-1">If not added yet, add this single entry to Linux crontab (<code>crontab -e</code>):</p>
                            <pre class="bg-dark text-light p-2 rounded fs-11 mb-0"><code>* * * * * cd /var/www/html/xeasy_laravel && php artisan schedule:run >> /dev/null 2>&1</code></pre>
                        </div>

                        <div>
                            <span class="badge bg-secondary mb-1">Option 2: Direct Dedicated Cron (Daily at 09:00 AM)</span>
                            <p class="fs-12 text-muted mb-1">
                                If you wish to trigger this specific reminder directly via system crontab without the master scheduler:
                            </p>
                            <pre class="bg-dark text-light p-2 rounded fs-11 mb-0"><code>0 9 * * * cd /var/www/html/xeasy_laravel && php artisan notifications:resigned-submission >> /var/www/html/xeasy_laravel/storage/logs/resigned_cron.log 2>&1</code></pre>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2">CLI Testing &amp; Dry Run:</h6>
                <p class="fs-13 text-muted mb-1">Can be simulated and verified from command line at any time:</p>
                <pre class="bg-dark text-light p-3 rounded fs-12 mb-0"><code>php artisan notifications:resigned-submission --dry-run</code></pre>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <a href="{{ route('admin.resigned-submission.docs.download') }}" class="btn btn-success btn-sm">
                    <i class="ri-download-2-line me-1"></i> Download Complete API Docs (.md)
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const targetSelect   = document.getElementById('targetEmployeeSelect');
    const companyScope   = document.getElementById('companyScopeSelect');
    const overrideEmail  = document.getElementById('overrideEmailInput');
    const subjectInput   = document.getElementById('subjectInput');
    const isCronSwitch   = document.getElementById('isCronSwitch');
    const modeDesc       = document.getElementById('modeDescription');
    const previewFrame   = document.getElementById('emailPreviewFrame');
    const btnSendApi     = document.getElementById('btnSendApi');
    const btnRefresh     = document.getElementById('btnRefreshPreview');
    const responseWrap   = document.getElementById('apiResponseWrapper');
    const responseBox    = document.getElementById('apiResponseBox');
    const statusBadge    = document.getElementById('apiStatusBadge');
    const previewSubject = document.getElementById('previewSubjectText');
    const previewBadge   = document.getElementById('previewRecipientBadge');
    const previewEmp     = document.getElementById('previewEmployeeBadge');

    // Execution mode toggle description
    isCronSwitch.addEventListener('change', function () {
        if (this.checked) {
            modeDesc.innerHTML = '<i class="ri-arrow-right-s-line text-warning"></i> <strong class="text-warning">Cron Job Simulation mode:</strong> Sends email and increments the <code>reminder_count</code> in <code>eml_resigned_reminders</code>.';
        } else {
            modeDesc.innerHTML = '<i class="ri-arrow-right-s-line text-primary"></i> <strong class="text-primary">Standard API mode:</strong> Sends email and records in <code>eml_send_log</code> without incrementing cron reminder count.';
        }
    });

    // Update preview when employee selected
    targetSelect.addEventListener('change', updatePreview);
    btnRefresh.addEventListener('click', updatePreview);

    // Company scope selector in API tester
    companyScope.addEventListener('change', function () {
        const selectedCo = this.value;
        const options = targetSelect.options;

        for (let i = 0; i < options.length; i++) {
            const opt = options[i];
            if (opt.value === 'all' || opt.value === '') continue;
            const coId = opt.dataset.companyId;
            if (selectedCo === 'all' || coId === selectedCo) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        }
    });

    function updatePreview() {
        const selectedOpt = targetSelect.options[targetSelect.selectedIndex];
        if (!selectedOpt) return;

        const empId   = selectedOpt.value;
        const empName = selectedOpt.dataset.name || 'Employee';
        const empMail = selectedOpt.dataset.email || 'No email';
        const currentSubject = subjectInput.value.trim() || 'Final Submission of Expense Claims in Xeasy Before Last Working Day';

        previewSubject.textContent = currentSubject;
        previewEmp.textContent = empName;
        previewBadge.textContent = 'To: ' + (overrideEmail.value.trim() || empMail);

        fetch(`{{ route('admin.resigned-submission.preview') }}?employee_id=${encodeURIComponent(empId)}&subject=${encodeURIComponent(currentSubject)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.html) {
                    const doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
                    doc.open();
                    doc.write(data.html);
                    doc.close();
                }
            })
            .catch(err => console.error('Preview error:', err));
    }

    // Initial Preview
    updatePreview();

    // Send API Button Action
    btnSendApi.addEventListener('click', function () {
        const empId = targetSelect.value;
        if (!empId) {
            alert('Please select a target employee or choose [BATCH] All.');
            return;
        }

        const payload = {
            employee_id: empId === 'all' ? null : parseInt(empId),
            company_id: companyScope.value === 'all' ? null : parseInt(companyScope.value),
            test_email: overrideEmail.value.trim() || null,
            is_cron: isCronSwitch.checked,
            subject: subjectInput.value.trim(),
            _token: '{{ csrf_token() }}'
        };

        btnSendApi.disabled = true;
        btnSendApi.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Calling API...';
        responseWrap.style.display = 'none';

        fetch('{{ route("admin.resigned-submission.send") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(async res => {
            const data = await res.json();
            return { ok: res.ok, status: res.status, data };
        })
        .then(({ ok, status, data }) => {
            btnSendApi.disabled = false;
            btnSendApi.innerHTML = '<i class="ri-send-plane-fill me-1"></i> Send via API';

            responseWrap.style.display = 'block';
            responseBox.textContent = JSON.stringify(data, null, 2);

            if (ok && data.success) {
                statusBadge.className = 'badge bg-success';
                statusBadge.textContent = `HTTP ${status} OK`;

                // Update row badges in UI if single send
                if (payload.employee_id && data.data) {
                    const empRes = data.data;
                    const badge = document.getElementById(`count-badge-${payload.employee_id}`);
                    const totalBadge = document.getElementById(`total-badge-${payload.employee_id}`);
                    const lastSent = document.getElementById(`last-sent-${payload.employee_id}`);

                    if (badge && empRes.reminder_count !== undefined) {
                        badge.textContent = empRes.reminder_count;
                        badge.className = 'badge bg-success fs-12 px-2 py-1';
                    }
                    if (totalBadge) {
                        const cur = parseInt(totalBadge.textContent) || 0;
                        totalBadge.textContent = cur + 1;
                    }
                    if (lastSent) {
                        lastSent.innerHTML = `<div>Just now</div><small class="badge bg-light text-muted border text-uppercase" style="font-size: 9px;">via ${payload.is_cron ? 'cron' : 'api'}</small>`;
                    }
                }
                loadLogs(); // Refresh logs tab
            } else {
                statusBadge.className = 'badge bg-danger';
                statusBadge.textContent = `HTTP ${status} ERROR`;
            }
        })
        .catch(err => {
            btnSendApi.disabled = false;
            btnSendApi.innerHTML = '<i class="ri-send-plane-fill me-1"></i> Send via API';
            responseWrap.style.display = 'block';
            statusBadge.className = 'badge bg-danger';
            statusBadge.textContent = 'FETCH ERROR';
            responseBox.textContent = err.toString();
        });
    });

    // Employee directory company & search filter
    const dirCompanyFilter = document.getElementById('employeeDirectoryCompanyFilter');
    const dirSearchInput   = document.getElementById('employeeSearchInput');

    function filterEmployeeDirectory() {
        const coVal = dirCompanyFilter.value;
        const kw    = dirSearchInput.value.toLowerCase().trim();
        const rows  = document.querySelectorAll('.employee-dir-row');

        rows.forEach(row => {
            const rowCo = row.dataset.companyId;
            const text  = row.textContent.toLowerCase();
            const coMatch = (coVal === 'all' || rowCo === coVal);
            const kwMatch = (!kw || text.includes(kw));

            if (coMatch && kwMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (dirCompanyFilter) dirCompanyFilter.addEventListener('change', filterEmployeeDirectory);
    if (dirSearchInput) dirSearchInput.addEventListener('input', filterEmployeeDirectory);

    // Batch Cron simulation button
    const btnBatch = document.getElementById('btnBatchCronSimulation');
    if (btnBatch) {
        btnBatch.addEventListener('click', function () {
            if (!confirm('Run Cron simulation for eligible resigned employees across all companies? This will increment reminder counts.')) {
                return;
            }

            btnBatch.disabled = true;
            btnBatch.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Running Cron Batch...';

            fetch('{{ route("admin.resigned-submission.send") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    employee_id: null,
                    is_cron: true,
                    company_id: dirCompanyFilter ? (dirCompanyFilter.value === 'all' ? null : parseInt(dirCompanyFilter.value)) : null,
                    test_email: overrideEmail.value.trim() || null
                })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message || 'Batch execution completed.');
                location.reload();
            })
            .catch(err => {
                alert('Batch execution failed: ' + err);
                btnBatch.disabled = false;
                btnBatch.innerHTML = '<i class="ri-play-circle-line me-1"></i> Run Batch Cron Simulation';
            });
        });
    }

    // Template Editor: Variable Insert Pills
    document.querySelectorAll('.var-pill').forEach(btn => {
        btn.addEventListener('click', function () {
            const varTag = this.dataset.var;
            const textarea = document.getElementById('templateHtmlTextarea');
            const start = textarea.selectionStart;
            const end   = textarea.selectionEnd;
            const text  = textarea.value;

            textarea.value = text.substring(0, start) + varTag + text.substring(end);
            textarea.focus();
            textarea.selectionEnd = start + varTag.length;
        });
    });

    // Save Template Action
    const btnSaveTemplate = document.getElementById('btnSaveTemplate');
    if (btnSaveTemplate) {
        btnSaveTemplate.addEventListener('click', function () {
            const subject  = document.getElementById('templateSubjectInput').value.trim();
            const bodyHtml = document.getElementById('templateHtmlTextarea').value;

            if (!subject || !bodyHtml) {
                alert('Please enter both subject line and body HTML.');
                return;
            }

            btnSaveTemplate.disabled = true;
            btnSaveTemplate.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

            fetch('{{ route("admin.resigned-submission.template.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ subject, body_html: bodyHtml })
            })
            .then(res => res.json())
            .then(data => {
                btnSaveTemplate.disabled = false;
                btnSaveTemplate.innerHTML = '<i class="ri-save-line me-1"></i> Save Template';
                alert(data.message || 'Template saved successfully.');
                updatePreview();
            })
            .catch(err => {
                btnSaveTemplate.disabled = false;
                btnSaveTemplate.innerHTML = '<i class="ri-save-line me-1"></i> Save Template';
                alert('Save failed: ' + err);
            });
        });
    }

    // Reset Template Action
    const btnResetTemplate = document.getElementById('btnResetTemplateDefault');
    if (btnResetTemplate) {
        btnResetTemplate.addEventListener('click', function () {
            if (!confirm('Reset template to standard VNR corporate default layout?')) return;
            fetch('{{ route("admin.resigned-submission.preview") }}?reset=1')
                .then(res => res.json())
                .then(data => {
                    if (data.html) {
                        document.getElementById('templateHtmlTextarea').value = data.html;
                        document.getElementById('templateSubjectInput').value = data.subject || 'Final Submission of Expense Claims in Xeasy Before Last Working Day';
                        btnSaveTemplate.click();
                    }
                });
        });
    }

    // Save Automation Settings Action
    const btnSaveSettings = document.getElementById('btnSaveSettings');
    if (btnSaveSettings) {
        btnSaveSettings.addEventListener('click', function () {
            const targetCos = [];
            document.querySelectorAll('.settings-company-chk:checked').forEach(chk => {
                targetCos.push(parseInt(chk.value));
            });

            const payload = {
                is_active: document.getElementById('settingsIsActive').checked,
                send_time: document.getElementById('settingsSendTime').value,
                frequency: document.getElementById('settingsFrequency').value,
                min_days_between_reminders: parseInt(document.getElementById('settingsMinDays').value) || 3,
                max_reminders_per_employee: parseInt(document.getElementById('settingsMaxReminders').value) || 5,
                target_companies: targetCos,
                query_email: document.getElementById('settingsQueryEmail').value.trim()
            };

            btnSaveSettings.disabled = true;
            btnSaveSettings.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

            fetch('{{ route("admin.resigned-submission.settings.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                btnSaveSettings.disabled = false;
                btnSaveSettings.innerHTML = '<i class="ri-save-line me-1"></i> Save Settings';
                alert(data.message || 'Settings saved successfully.');
            })
            .catch(err => {
                btnSaveSettings.disabled = false;
                btnSaveSettings.innerHTML = '<i class="ri-save-line me-1"></i> Save Settings';
                alert('Failed to save settings: ' + err);
            });
        });
    }

    // Logs Tab & Filtering
    const logCompanyFilter = document.getElementById('filterLogCompany');
    const logStatusFilter  = document.getElementById('filterLogStatus');
    const logFromDate      = document.getElementById('filterLogFromDate');
    const logToDate        = document.getElementById('filterLogToDate');
    const logSearch        = document.getElementById('filterLogSearch');
    const btnApplyFilters  = document.getElementById('btnApplyLogFilters');
    const btnRefreshLogs   = document.getElementById('btnRefreshLogs');
    const logsTbody        = document.getElementById('logsTableBody');

    function loadLogs() {
        if (!logsTbody) return;
        logsTbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Loading filtered logs...</td></tr>';

        const params = new URLSearchParams({
            company_id: logCompanyFilter.value,
            status: logStatusFilter.value,
            from_date: logFromDate.value,
            to_date: logToDate.value,
            search: logSearch.value.trim()
        });

        fetch(`{{ route('admin.resigned-submission.logs') }}?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                if (!data.logs || data.logs.length === 0) {
                    logsTbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="ri-inbox-line fs-24 d-block mb-1"></i> No matching email send logs found.</td></tr>';
                    return;
                }

                let html = '';
                data.logs.forEach((log, idx) => {
                    const statusBadge = log.is_sent
                        ? '<span class="badge bg-success"><i class="ri-check-line me-1"></i>Sent</span>'
                        : '<span class="badge bg-danger"><i class="ri-close-line me-1"></i>Failed</span>';

                    html += `<tr>
                        <td>${log.id}</td>
                        <td><span class="badge bg-primary-subtle text-primary fs-11">${log.company_name}</span></td>
                        <td>
                            <div class="fw-semibold">${log.recipient_name || '-'}</div>
                            <small class="text-muted">${log.recipient_email}</small>
                        </td>
                        <td class="text-truncate" style="max-width: 200px;">${log.subject}</td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary">Hit #${log.stage || 1}</span>
                        </td>
                        <td>${statusBadge}</td>
                        <td>${log.created_formatted}</td>
                        <td class="text-danger small text-truncate" style="max-width: 180px;">${log.error_message || '&mdash;'}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="viewLogBody(${log.id})">
                                <i class="ri-eye-line"></i> View
                            </button>
                        </td>
                    </tr>`;
                });
                logsTbody.innerHTML = html;
            })
            .catch(err => {
                logsTbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">Error loading logs: ${err}</td></tr>`;
            });
    }

    if (btnApplyFilters) btnApplyFilters.addEventListener('click', loadLogs);
    if (btnRefreshLogs) btnRefreshLogs.addEventListener('click', loadLogs);

    // Auto-load logs when clicking the tab
    const tabLogsLink = document.getElementById('tab-logs-link');
    if (tabLogsLink) {
        tabLogsLink.addEventListener('shown.bs.tab', loadLogs);
    }

    // Modal to view full sent HTML body
    window.viewLogBody = function (logId) {
        fetch(`{{ url('/resigned-submission-reminder/logs') }}/${logId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.log) {
                    const l = data.log;
                    document.getElementById('modalEmailMeta').textContent =
                        `To: ${l.recipient_name} (${l.recipient_email}) • Sent: ${l.created_at} • ${l.company_name}`;

                    const iframe = document.getElementById('modalEmailBodyIframe');
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(l.body_html || '<p class="p-3 text-muted">No HTML body recorded.</p>');
                    doc.close();

                    const modal = new bootstrap.Modal(document.getElementById('viewEmailBodyModal'));
                    modal.show();
                }
            })
            .catch(err => alert('Failed to load log detail: ' + err));
    };

    // Global quick actions attached to table
    window.selectAndPreview = function (empId) {
        targetSelect.value = empId;
        const triggerTab = document.querySelector('a[href="#tab-api-tester"]');
        if (triggerTab) bootstrap.Tab.getInstance(triggerTab)?.show() || new bootstrap.Tab(triggerTab).show();
        updatePreview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.quickSend = function (empId, isCron) {
        targetSelect.value = empId;
        isCronSwitch.checked = isCron;
        isCronSwitch.dispatchEvent(new Event('change'));
        const triggerTab = document.querySelector('a[href="#tab-api-tester"]');
        if (triggerTab) bootstrap.Tab.getInstance(triggerTab)?.show() || new bootstrap.Tab(triggerTab).show();
        updatePreview();
        btnSendApi.click();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
});
</script>
@endpush
