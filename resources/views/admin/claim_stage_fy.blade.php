@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white"><i class="ri-file-list-3-line me-2"></i>Claim Stage Report (Full FY)</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#columnModal">
                        <i class="ri-settings-3-line me-1"></i>Select Columns
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                        <i class="ri-equalizer-line me-1"></i> More Filters
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="organogramFilterCountBadge" style="display:none;">0</span>
                    </button>
                    <button class="btn btn-sm btn-success" id="exportBtn" onclick="exportReport()">
                        <i class="ri-file-excel-line me-1"></i>Export
                    </button>
                </div>
            </div>
            @include('partials.organogram-filters-drawer')
            <div class="card-body">
                {{-- Filters --}}
                <div class="row g-2 mb-3">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Financial Year</label>
                        <select class="form-select form-select-sm" id="fySelect">
                            @foreach($financialYears as $fy)
                                <option value="{{ $fy->YearId }}" {{ $fy->YearId == session('year_id') ? 'selected' : '' }}>{{ $fy->Year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Function</label>
                        <select class="form-select form-select-sm" id="functionSelect">
                            <option value="">All</option>
                            @foreach($functions as $fn)
                                <option value="{{ $fn->id }}">{{ $fn->function_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Department</label>
                        <select class="form-select form-select-sm" id="departmentSelect">
                            <option value="">All</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Employee</label>
                        <select class="form-select form-select-sm" id="employeeSelect">
                            <option value="">All Employees</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->EmployeeID }}">{{ $emp->EmpCode_New }} - {{ $emp->Fname }} {{ $emp->Sname ?? '' }} {{ $emp->Lname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Month Status</label>
                        <select class="form-select form-select-sm" id="monthStatusSelect">
                            <option value="all" selected>All</option>
                            <option value="open">Open (Not Submitted)</option>
                            <option value="closed">Closed (Submitted)</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary btn-sm w-100" onclick="exportReport()">
                            <i class="ri-download-line"></i>
                        </button>
                    </div>
                </div>
                <div class="text-muted small mb-3">
                    <i class="ri-information-line me-1"></i>Select FY and filters, then click Export. The report will be shown below as an Excel preview.
                </div>

                {{-- Excel Preview (inline viewer like /claim-stage) --}}
                <div id="excelPreview" style="display:none;">
                    <iframe id="excelFrame" width="100%" height="500" frameborder="0" style="border:1px solid #dee2e6;border-radius:4px;"></iframe>
                </div>
                <div id="exportPlaceholder" class="text-center text-muted py-5">
                    <i class="ri-file-excel-line fs-1 d-block mb-2"></i>
                    Click <strong>Export</strong> to generate and preview the report
                </div>
                </div>

                {{-- Data Table --}}
                {{-- <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover" id="fyTable" style="font-size:10px;">
                        <thead style="background:#15648C;color:white;" id="fyTableHead">
                            <tr>
                                <th>#</th>
                                <th>Emp Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Month</th>
                                <th>Claim Amt</th>
                                <th>Submit Date</th>
                                <th>Verified Date</th>
                                <th>Verified Amt</th>
                                <th>Approved Date</th>
                                <th>Approved Amt</th>
                                <th>Finance Date</th>
                                <th>Finance Amt</th>
                                <th>Stage</th>
                            </tr>
                        </thead>
                        <tbody id="fyBody">
                            <tr><td colspan="15" class="text-center text-muted py-4">Click Export or Search to load data</td></tr>
                        </tbody>
                    </table>
                </div> --}}
            </div>
        </div>
    </div>
</div>

{{-- Column Selection Modal --}}
<div class="modal fade" id="columnModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-settings-3-line me-1"></i>Select Columns for Export</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-sm btn-outline-primary" onclick="selectAllCols()">Select All</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="deselectAllCols()">Deselect All</button>
                </div>
                <div class="row">
                    <div class="col-6">
                        <h6 class="fw-bold text-primary mb-2">Employee Info</h6>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="emp_code" id="col_emp_code" checked><label class="form-check-label" for="col_emp_code">Emp Code</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="name" id="col_name" checked><label class="form-check-label" for="col_name">Name</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="status" id="col_status" checked><label class="form-check-label" for="col_status">Status (A/D)</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="fy_year" id="col_fy_year" checked><label class="form-check-label" for="col_fy_year">FY Year</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="function" id="col_function"><label class="form-check-label" for="col_function">Function</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="vertical" id="col_vertical"><label class="form-check-label" for="col_vertical">Vertical</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="department" id="col_department" checked><label class="form-check-label" for="col_department">Department</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="sub_department" id="col_sub_department"><label class="form-check-label" for="col_sub_department">Sub Department</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="section" id="col_section"><label class="form-check-label" for="col_section">Section</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="designation" id="col_designation" checked><label class="form-check-label" for="col_designation">Designation</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="grade" id="col_grade"><label class="form-check-label" for="col_grade">Grade</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="cost_center" id="col_cost_center"><label class="form-check-label" for="col_cost_center">Cost Center/State</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="bu" id="col_bu"><label class="form-check-label" for="col_bu">Business Unit</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="zone" id="col_zone"><label class="form-check-label" for="col_zone">Zone</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="region" id="col_region"><label class="form-check-label" for="col_region">Region</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="territory" id="col_territory"><label class="form-check-label" for="col_territory">Territory</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="reporting" id="col_reporting"><label class="form-check-label" for="col_reporting">Reporting To</label></div>
                    </div>
                    <div class="col-6">
                        <h6 class="fw-bold text-primary mb-2">Claim Data</h6>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="month" id="col_month" checked><label class="form-check-label" for="col_month">Month</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="claim_amount" id="col_claim_amount" checked><label class="form-check-label" for="col_claim_amount">Claim Amount</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="submit_date" id="col_submit_date" checked><label class="form-check-label" for="col_submit_date">Submit Date</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="courier_date" id="col_courier_date"><label class="form-check-label" for="col_courier_date">Courier Received Date</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="verified_date" id="col_verified_date" checked><label class="form-check-label" for="col_verified_date">Verified Date</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="verified_amount" id="col_verified_amount" checked><label class="form-check-label" for="col_verified_amount">Verified Amount</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="approved_date" id="col_approved_date" checked><label class="form-check-label" for="col_approved_date">Approved Date</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="approved_amount" id="col_approved_amount" checked><label class="form-check-label" for="col_approved_amount">Approved Amount</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="finance_date" id="col_finance_date" checked><label class="form-check-label" for="col_finance_date">Finance Date</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="finance_amount" id="col_finance_amount" checked><label class="form-check-label" for="col_finance_amount">Finance Amount</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="pay_date" id="col_pay_date"><label class="form-check-label" for="col_pay_date">Payment Date</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="pay_amount" id="col_pay_amount"><label class="form-check-label" for="col_pay_amount">Payment Amount</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="total_claims" id="col_total_claims"><label class="form-check-label" for="col_total_claims">Total Claims Count</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="courier_send_date" id="col_courier_send_date"><label class="form-check-label" for="col_courier_send_date">Courier Send Date</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="last_mail_date" id="col_last_mail_date"><label class="form-check-label" for="col_last_mail_date">Last Mail Date</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="last_mail_subject" id="col_last_mail_subject"><label class="form-check-label" for="col_last_mail_subject">Last Mail Subject</label></div>
                        <div class="form-check mb-1"><input class="form-check-input col-check" type="checkbox" value="stage" id="col_stage"><label class="form-check-label" for="col_stage">Current Stage</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Apply</button>
            </div>
        </div>
    </div>
</div>

@endsection
@push('scripts')
@include('partials.organogram-filters-js')
{{-- 13 Nov 2026: moved here from the page body; it uses jQuery, which loads at the end of the page ("$ is not defined") --}}
<script>
function getSelectedColumns() {
    let cols = [];
    document.querySelectorAll('.col-check:checked').forEach(cb => cols.push(cb.value));
    return cols;
}

function selectAllCols() {
    document.querySelectorAll('.col-check').forEach(cb => cb.checked = true);
}

function deselectAllCols() {
    document.querySelectorAll('.col-check').forEach(cb => cb.checked = false);
}

function exportReport() {
    const btn = document.getElementById('exportBtn');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i>Generating...';
    btn.classList.add('disabled');

    const organogram = typeof window.getOrganogramFilterValues === 'function' ? window.getOrganogramFilterValues() : {};
    const postData = $.extend({
        _token: '{{ csrf_token() }}',
        year_id: document.getElementById('fySelect').value,
        function_id: document.getElementById('functionSelect').value,
        department_id: document.getElementById('departmentSelect').value,
        employee_id: document.getElementById('employeeSelect').value,
        month_status: document.getElementById('monthStatusSelect').value,
        columns: getSelectedColumns().join(','),
    }, organogram);

    $.ajax({
        url: '{{ route("reports.claim.dashboard.fy.report.export") }}',
        method: 'POST',
        data: postData,
        success: function(res) {
            if (res.status === 'success' && res.file_url) {
                // Show Excel inline using Microsoft Office Online Viewer
                const cleanUrl = res.file_url.replace(/([^:])\/\/+/g, '$1/');
                const embed = 'https://view.officeapps.live.com/op/embed.aspx?wdDownloadButton=True&src=' + encodeURIComponent(cleanUrl);
                $('#excelFrame').attr('src', embed);
                $('#excelPreview').show();
                $('#exportPlaceholder').hide();
            } else {
                alert('Export failed: ' + (res.message || 'Unknown error'));
            }
            btn.innerHTML = originalHtml;
            btn.classList.remove('disabled');
        },
        error: function(xhr) {
            alert('Export failed. Please try again.');
            btn.innerHTML = originalHtml;
            btn.classList.remove('disabled');
        }
    });
}

$(document).on('organogramFiltersApplied', function() {
    exportReport();
});
</script>
@endpush

