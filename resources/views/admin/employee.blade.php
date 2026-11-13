@extends('layouts.app')
@section('content')
<style>
    .table-hover tbody tr:hover {
        background-color: #e9f3ff !important; /* light blue */
    }
    
    .mediator-display, .policy-display {
        transition: opacity 0.2s ease;
    }
    
    .mediator-edit, .policy-edit {
        animation: slideIn 0.2s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Scrollable Employee Claims Modal & Sticky Headers */
    #employeeClaimsModal .modal-dialog {
        max-width: 95vw !important;
        height: 90vh !important;
        margin: 1.75rem auto;
        transition: max-width 0.2s ease, width 0.2s ease, height 0.2s ease, margin 0.2s ease;
    }
    #employeeClaimsModal .modal-dialog.modal-fullscreen {
        width: 100vw !important;
        max-width: 100vw !important;
        height: 100vh !important;
        max-height: 100vh !important;
        margin: 0 !important;
    }
    #employeeClaimsModal .modal-content {
        height: 100% !important;
        max-height: 90vh !important;
        display: flex;
        flex-direction: column;
        border-radius: 8px;
    }
    #employeeClaimsModal .modal-dialog.modal-fullscreen .modal-content {
        max-height: 100vh !important;
        height: 100vh !important;
        border-radius: 0 !important;
    }
    #employeeClaimsModal .modal-body {
        flex: 1 1 0;
        min-height: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        padding: 8px 20px 14px 20px !important;
    }
    #employeeClaimsModal .employee-claims-scroll-container {
        flex: 1 1 0;
        min-height: 0;
        height: 100%;
        max-height: calc(90vh - 85px);
        overflow-y: auto !important;
        overflow-x: auto !important;
        scrollbar-width: thin;
        scrollbar-color: #15648c #f1f1f1;
    }
    #employeeClaimsModal .modal-dialog.modal-fullscreen .employee-claims-scroll-container {
        max-height: calc(100vh - 75px) !important;
    }
    #employeeClaimsModal .employee-claims-scroll-container::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    #employeeClaimsModal .employee-claims-scroll-container::-webkit-scrollbar-thumb {
        background: #dde6ff;
        border-radius: 4px; color: #1e3a8a;}
    #employeeClaimsModal .employee-claims-scroll-container::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    #employeeClaimsModal .claim-table thead tr:first-child th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #dde6ff !important;
        color: #1e3a8a !important;
    }
    #employeeClaimsModal .claim-table thead tr:nth-child(2) th {
        position: sticky;
        top: 26px;
        z-index: 9;
        background-color: #dde6ff !important;
        color: #1e3a8a !important;
    }
</style>
    {{-- Sticky Eligibility List button --}}
    <a href="https://ess.vnrseeds.co.in/Employee/EmpEligDetails.php?act=true&cid=1&mm=sas&ask=false&ww=rightProtect&we=12345&mm=er4e&r=t5tt5s&yy=eloi&gosto=false&rigt=checkessue&mailto=promt&rdd=012&value=0&page=1"
       target="_blank"
       style="position:fixed;right:0;top:50%;transform:translateY(-50%);z-index:1050;
              writing-mode:vertical-rl;text-orientation:mixed;
              background:#dde6ff;color:#1e3a8a;padding:12px 8px;
              border-radius:6px 0 0 6px;font-size:12px;font-weight:600;
              text-decoration:none;letter-spacing:.5px;box-shadow:-2px 0 8px rgba(0,0,0,.15);">
        Eligibility List
    </a>
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card mb-3">
                        @if(!empty($empInfo))
                        @php
                            $ec = $empInfo->EmpCode_New;
                            // Check deactive: separation date first, then status
                            $sepDate = $empInfo->DateOfSepration ?? null;
                            $isSepPast = (!empty($sepDate) && \App\Support\ZeroDate::has($sepDate) && $sepDate != '1970-01-01' && $sepDate != '1971-01-01' && strtotime($sepDate) > strtotime('2000-01-01') && strtotime($sepDate) < strtotime(date('Y-m-d')));
                            $isEmpDeactive = $isSepPast || in_array($empInfo->EmpStatus ?? '', ['D', 'De']);
                            $statusColor = $isEmpDeactive ? '#dc3545' : '#198754';
                            $statusLabel = $isEmpDeactive ? 'Deactive' : 'Active';
                        @endphp
                        <div class="px-3 py-2" style="background:#f0f4ff;border-bottom:1px solid #c7d4f0;">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:34px;height:34px;border-radius:50%;background:#dde6ff;color:#1e3a8a;
                                                display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">
                                        {{ strtoupper(substr($empInfo->FullName ?? 'E', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold" style="font-size:13px;color:#1e3a5f;line-height:1.3;">
                                            {{ $ec }} — {{ $empInfo->FullName ?? '' }}
                                            <span class="ms-1 badge" style="background:{{ $statusColor }};font-size:9px;vertical-align:middle;">{{ $statusLabel }}</span>
                                        </div>
                                        <div class="text-muted" style="font-size:11px;line-height:1.3;">
                                            {{ $empInfo->designation_name ?? '' }}
                                            @if(!empty($empInfo->department_name)) · {{ $empInfo->department_name }}@endif
                                            @if(!empty($empInfo->grade_name)) · {{ $empInfo->grade_name }}@endif
                                            @php
                                                $sepDate = $empInfo->DateOfSepration ?? null;
                                                $resDate = $empInfo->DateOfResignation ?? null;
                                                $validSep = $sepDate && \App\Support\ZeroDate::has($sepDate) && $sepDate !== '';
                                                $validRes = $resDate && \App\Support\ZeroDate::has($resDate) && $resDate !== '';
                                            @endphp
                                            @if($validRes)
                                                · <span class="text-warning fw-semibold">Resignation: {{ \Carbon\Carbon::parse($resDate)->format('d M Y') }}</span>
                                            @endif
                                            @if($validSep)
                                                · <span class="text-danger fw-semibold">Separation: {{ \Carbon\Carbon::parse($sepDate)->format('d M Y') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end" style="font-size:11px;color:#444;line-height:1.5;">
                                    @if(!empty($empInfo->MobileNo_Vnr))
                                        <div><i class="ri-phone-line me-1 text-primary"></i>{{ $empInfo->MobileNo_Vnr }}</div>
                                    @endif
                                    @if(!empty($empInfo->EmailId_Vnr))
                                        <div><i class="ri-mail-line me-1 text-primary"></i>{{ $empInfo->EmailId_Vnr }}</div>
                                    @endif
                                    @if(!empty($empInfo->ReportingName))
                                        <div><i class="ri-user-line me-1 text-primary"></i><strong>Reports to:</strong>
                                            @if(!empty($empInfo->ReportingEmpCode)){{ $empInfo->ReportingEmpCode }} — @endif{{ $empInfo->ReportingName }}
                                            @if(!empty($empInfo->ReportingContactNo)) ({{ $empInfo->ReportingContactNo }})@endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        {{-- MEDIATOR & ELIGIBILITY PANEL --}}
                        {{-- <div class="px-3 py-3" style="background:#f8f9fa;border-bottom:1px solid #dee2e6;">
                            <div class="row g-3">
                                <!-- Mediator Assignment -->
                                <div class="col-md-6">
                                    <div style="background:#fff;border:1px solid #dee2e6;border-radius:6px;padding:12px;">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label style="font-size:12px;font-weight:600;color:#1e3a5f;margin:0;">
                                                <i class="ri-keyboard-line text-primary me-1"></i>Data Punch Mediator
                                            </label>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    id="mediator-edit-btn"
                                                    style="font-size:10px;padding:4px 8px;">
                                                <i class="ri-edit-line"></i> Edit
                                            </button>
                                        </div>
                                        <div id="mediator-view" class="mediator-display">
                                            <div class="text-muted" style="font-size:11px;">Loading...</div>
                                        </div>
                                        <div id="mediator-edit" class="mediator-edit d-none">
                                            <select id="mediator-select" class="form-select form-select-sm" style="font-size:11px;">
                                                <option value="">-- Select Mediator --</option>
                                            </select>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-primary" id="mediator-save-btn">
                                                    <i class="ri-check-line"></i> Save
                                                </button>
                                                <button type="button" class="btn btn-sm btn-secondary" id="mediator-cancel-btn">
                                                    <i class="ri-close-line"></i> Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Eligibility Policy -->
                                <div class="col-md-6">
                                    <div style="background:#fff;border:1px solid #dee2e6;border-radius:6px;padding:12px;">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label style="font-size:12px;font-weight:600;color:#1e3a5f;margin:0;">
                                                <i class="ri-shield-check-line text-success me-1"></i>Claim Eligibility
                                            </label>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    id="policy-edit-btn"
                                                    style="font-size:10px;padding:4px 8px;">
                                                <i class="ri-edit-line"></i> Edit
                                            </button>
                                        </div>
                                        <div id="policy-view" class="policy-display">
                                            <div class="text-muted" style="font-size:11px;">Loading...</div>
                                        </div>
                                        <div id="policy-edit" class="policy-edit d-none">
                                            <select id="policy-select" class="form-select form-select-sm" style="font-size:11px;">
                                                <option value="">-- Select Policy --</option>
                                            </select>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-success" id="policy-save-btn">
                                                    <i class="ri-check-line"></i> Save
                                                </button>
                                                <button type="button" class="btn btn-sm btn-secondary" id="policy-cancel-btn">
                                                    <i class="ri-close-line"></i> Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                         --}}
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table nowrap dt-responsive table-hover table-bordered align-middle"
                                    style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2">Claim Month</th>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2" class="text-center">Total Claims</th>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2" class="text-center">Filling Status</th>
                                            <th colspan="4" class="text-center">Pending Approval</th>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2" class="text-center">View Home</th>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2" class="text-center">Return Claim</th>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2" class="text-center">View Details</th>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2" class="text-center">Allow Submit</th>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2" class="text-center">Backend<br>Submission</th>
                                            <th style="vertical-align: middle;text-align: center;text-align: left;font-size: 12px;"
                                                rowspan="2" class="text-center">Month Status</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center">Claimant</th>
                                            <th class="text-center">Verify</th>
                                            <th class="text-center">Reporting</th>
                                            <th class="text-center">Finance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if ($data->isNotEmpty())
                                            @foreach ($data as $row)
                                                <tr>
                                                    <td style="text-align: left;">
                                                        <strong>{{ \Carbon\Carbon::createFromFormat('!m', $row->ClaimMonth)->format('F') }}</strong>
                                                    </td>
                                                    <td class="text-center">{{ $row->TotalClaims }}</td>
                                                    <td class="text-center">
                                                        {{ $row->FilledCount > 0 ? $row->FilledCount : '' }}</td>
                                                    <td class="text-center">
                                                        {!! $row->ClaimantApprovalCount
                                                            ? "<a href='#' class='text-primary'><b>{$row->ClaimantApprovalCount}</b></a>"
                                                            : '' !!}
                                                    </td>
                                                    <td class="text-center">
                                                        {!! $row->VerifyCount ? "<a href='#' class='text-primary'><b>{$row->VerifyCount}</b></a>" : '' !!}
                                                    </td>
                                                    <td class="text-center">
                                                        {!! $row->ApprCount ? "<a href='#' class='text-primary'><b>{$row->ApprCount}</b></a>" : '' !!}
                                                    </td>
                                                    <td class="text-center">
                                                        {!! $row->FinanceCount ? "<a href='#' class='text-primary'><b>{$row->FinanceCount}</b></a>" : '' !!}
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="#"
                                                            class="badge bg-primary-subtle text-primary text-decoration-none ehome-btn"
                                                            data-employee-id="{{ $row->EmployeeID }}"
                                                            data-month="{{ $row->ClaimMonth }}"
                                                            data-year-id="{{ $year_id ?? session('year_id', '') }}"
                                                            data-company-id="{{ session('company_id') }}">
                                                            <i class="ri-home-4-line"></i> E-Home
                                                        </a>
                                                    </td>
                                                    <td class="text-center">
                                                        @php
                                                            $returnDisabled =
                                                                $row->ApprCount > 0 || $row->FinanceCount > 0;
                                                        @endphp
                                                        <button type="button"
                                                            class="badge border-0 return-claim-btn {{ $returnDisabled ? 'bg-secondary-subtle text-secondary' : 'bg-warning-subtle text-warning' }}"
                                                            data-employee-id="{{ $row->EmployeeID }}"
                                                            data-month="{{ $row->ClaimMonth }}"
                                                            {{ $returnDisabled ? 'disabled' : '' }}
                                                            style="{{ $returnDisabled ? 'opacity:0.5; cursor:not-allowed;' : 'cursor:pointer;' }}">
                                                            <i class="ri-arrow-go-back-line"></i> {{ $returnDisabled ? 'Locked' : 'Return' }}
                                                        </button>
                                                    </td>
                                                    <td class="text-center">
                                                        @php
                                                            $claimMonthName = \Carbon\Carbon::createFromFormat(
                                                                '!m',
                                                                $row->ClaimMonth,
                                                            )->format('F');
                                                        @endphp
                                                        <button type="button"
                                                            class="badge bg-info-subtle text-info border-0 view-employee-claims"
                                                            data-employee-id="{{ $row->EmployeeID }}"
                                                            data-month="{{ $row->ClaimMonth }}"
                                                            data-month-name="{{ $claimMonthName }}"
                                                            data-fetch-url="{{ url('employee-all-claims') }}">
                                                            <i class="ri-eye-line"></i> View
                                                        </button>
                                                    </td>
                                                    <td class="text-center">
                                                        @php
                                                            $submitAllowed = $row->SubPermission === 'Y';
                                                            $submitDisabled = $returnDisabled || $submitAllowed;
                                                        @endphp
                                                        <div class="form-check form-switch d-flex justify-content-center"
                                                            title="{{ $submitAllowed ? 'Already allowed — cannot be reverted' : ($returnDisabled ? 'Cannot allow — approvals already done' : 'Click to allow submission') }}">
                                                            <input class="form-check-input allow-submit-toggle"
                                                                type="checkbox" id="Chk_{{ $row->ClaimMonth }}"
                                                                data-employee-id="{{ $row->EmployeeID }}"
                                                                data-month="{{ $row->ClaimMonth }}"
                                                                {{ $submitAllowed ? 'checked' : '' }}
                                                                {{ $submitDisabled ? 'disabled' : '' }}
                                                                style="{{ $submitDisabled ? 'opacity:0.6; cursor:not-allowed;' . ($submitAllowed ? ' accent-color:#0ab39c;' : '') : 'cursor:pointer;' }}">
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        @if(($isDeactivated ?? false) && ($row->Status === 'Open'))
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary backend-submit-btn"
                                                                data-employee-id="{{ $row->EmployeeID }}"
                                                                data-month="{{ $row->ClaimMonth }}"
                                                                title="Backend Submit for Month {{ $row->ClaimMonth }}">
                                                                <i class="ri-send-plane-line"></i> Submit
                                                            </button>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if ($row->Status === 'Closed')
                                                            <span
                                                                class="badge bg-success-subtle text-success badge-border">Closed</span>
                                                        @else
                                                            <span
                                                                class="badge bg-info-subtle text-info badge-border">Open</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="13" class="text-center text-muted fst-italic">No records
                                                    found.</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TEAM MEMBERS --}}
            <div class="row">
                <div class="col-lg-12">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3" style="color:#1e3a5f;">Team Members:</h5>
                            <div class="d-flex flex-wrap gap-3">
                                @if ($employees->isNotEmpty())
                                    @foreach ($employees as $member)
                                        @php
                                            $emp = $member->employee;
                                            if ($emp) {
                                                $fullName = trim($emp->Fname . ' ' . $emp->Sname . ' ' . $emp->Lname);
                                                $empCode = $emp->EmpCode_New;
                                                $initials = strtoupper(
                                                    substr($emp->Fname, 0, 1) .
                                                        substr($emp->Sname, 0, 1) .
                                                        substr($emp->Lname, 0, 1),
                                                );
                                            }
                                        @endphp

                                        @if ($emp)
                                            <div class="d-flex align-items-center mb-2" data-bs-toggle="tooltip"
                                                title="{{ $fullName }}">
                                                <div
                                                    class="avatar-xs rounded-circle me-2 d-flex align-items-center justify-content-center bg-primary text-white fw-bold">
                                                    {{ $initials }}
                                                </div>
                                                <strong>{{ $empCode }} - {{ $fullName }}</strong>
                                            </div>
                                        @endif
                                    @endforeach
                                @else
                                    <div class="text-muted fst-italic">No team members found.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>


    <div class="modal fade" id="employeeClaimsModal" data-bs-backdrop="static" tabindex="-1"
        aria-labelledby="employeeClaimsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 overflow-hidden">
                <div class="progress-container" style="display: none">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0" id="employeeClaimsModalLabel">Employee Claims</h4>
                    <div class="d-flex align-items-center" style="gap: 8px;">
                        <div>
                            <span id="employeeClaimsMeta" class="text-muted small"></span>
                        </div>
                        <select id="employeeClaimsStatusFilter" class="form-select form-select-sm" style="width: auto; min-width: 80px;">
                            <option value="">All</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-light border text-secondary" id="btnToggleClaimsFullscreen" title="Toggle Fullscreen" style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; padding: 0;">
                            <i class="ri-fullscreen-line fs-16" id="claimsFullscreenIcon"></i>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="table-responsive employee-claims-scroll-container">
                        <table class="table table-striped table-bordered align-middle claim-table table-hover mb-0">
                          <thead>
    <tr>
        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">#</th>
        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Exp ID</th>
        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Claim</th>
        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Bill Dt</th>
        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Upload Dt</th>

        <th colspan="4" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;text-align:center;">Claim</th>
        <th colspan="4" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;text-align:center;">Verify</th>
        <th colspan="3" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;text-align:center;">Approve</th>
        <th colspan="3" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;text-align:center;">Finance</th>

        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Status</th>
        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Odometer</th>
        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Backdate</th>
        <th rowspan="2" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Action</th>
    </tr>
    <tr>
        {{-- Claim (4) --}}
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Amt</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Remark</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">CI Verify</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Filled OK</th>

        {{-- Verify (4) --}}
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Flag</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Amt</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Dt</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Remark</th>

        {{-- Approve (3) --}}
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Amt</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Dt</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Remark</th>

        {{-- Finance (3) --}}
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Amt</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Dt</th>
        <th style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Remark</th>
    </tr>
</thead>
                            <tbody id="employeeClaimsTableBody">
                                <tr>
                                    <td colspan="23" class="text-center text-muted py-4">Select a record to load claims.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="employeeClaimsEmptyState" class="text-center text-muted py-4 d-none">
                        No claims found for the selected criteria.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal.view_claim_detail />
@endsection
@push('scripts')
    <script src="{{ asset('js/ehome-dashboard.js') }}"></script>
    <script src="{{ asset('custom/js/pages/employee.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('custom/js/pages/claim.js') }}"></script>
@endpush
