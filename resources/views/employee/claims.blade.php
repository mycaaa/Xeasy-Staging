@extends('layouts.app')
@php
    $masterParticipantCategories = ['Retailer', 'Dealer', 'Distributor', 'Consultant', 'Management', 'Guest', 'Other Employee not available in the list'];
    try {
        $dbCats = \App\Models\OtherParticipantCategory::active()->get();
        if ($dbCats->count() > 0) {
            $masterParticipantCategories = $dbCats->pluck('name')->toArray();
        }
    } catch (\Exception $e) {
        // fallback
    }
@endphp
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="card mb-3 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-12 d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
                        <div class="mb-1">
                            <h5 class="mb-1 text-primary fw-semibold">
                                <i class="ri-file-upload-line"></i>
                                Claims Upload Summary
                            </h5>
                            <p class="text-muted mb-0" style="font-size: 13px;">
                                All claims of the selected month, newest first, with bill date, type and bills.
                            </p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <div class="month-filter"></div>{{-- UPLOAD-01: month filter was never placed --}}
                            <button type="button" class="btn btn-sm btn-soft-info waves-effect" id="openDailyAllowBtn">
                                <i class="ri-money-dollar-circle-line align-middle fs-16"></i>
                                DA - Daily Allow.
                            </button>
                            <button type="button" class="btn btn-sm btn-soft-secondary waves-effect" id="openClaimBtn">
                                <i class="ri-upload-cloud-2-line align-middle fs-16"></i>
                                Upload File
                            </button>
                            @if($hasActivity)
                            <button type="button" class="btn btn-sm btn-soft-warning waves-effect" id="openActivityClaimBtn">
                                <i class="ri-article-line align-middle fs-16"></i>
                                Activity
                            </button>
                            @endif
                            <button type="button" class="btn btn-sm btn-soft-success waves-effect" id="openOdometerBtn">
                                <i class="ri-riding-line align-middle fs-16"></i>
                                Odometer
                            </button>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <table id="claimsTable" class="table nowrap dt-responsive align-middle table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">Exp ID</th>
                                    <th class="text-center">Bill Type</th>
                                    <th class="text-center">Month</th>
                                    <th class="text-center">Bill Date</th>
                                    <th class="text-center">Upload Date</th>
                                    <th class="text-center">Amount</th>
                                    <th class="text-center">Bills / PDF</th>
                                    <th class="text-center">Remark</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Odometer Modal -->
<div class="modal fade" id="odometerModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="odometerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="progress-container" style="display: none">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header d-flex align-items-center justify-content-between">
                <h5 class="modal-title" id="odometerModalLabel">
                    <i class="ri-riding-line"></i> Odometer Entry
                </h5>
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <button type="button" class="btn btn-sm btn-success" id="submitOdometerBtn">
                        Submit
                        <span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true" style="display: none;"></span>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="odometerMessage" class="alert alert-danger" style="display: none;"></div>
                <form id="odometerForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="temp_punch_id" id="temp_punch_id" value="0" />
                    <input type="hidden" name="emp_id" id="emp_id" value="{{ session('employee_id') }}" />

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Vehicle Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicleType" id="twoWheeler" value="2" checked>
                                    <label class="form-check-label" for="twoWheeler">2 Wheeler</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicleType" id="fourWheeler" value="4">
                                    <label class="form-check-label" for="fourWheeler">4 Wheeler</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="billDate" class="form-label fw-semibold">Bill Date <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" value="{{ date('d-m-Y') }}" id="billDate" name="billDate" />
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card border mb-0">
                                <div class="card-body">
                                    <label class="form-label fw-semibold">Start Meter Photo</label>
                                    <div class="photo-upload-box border rounded text-center p-3 mb-2" style="min-height: 150px; background: #f8f9fa;">
                                        <img id="startPhotoPreview" src="#" alt="Start Photo" style="display:none; max-width: 100%; max-height: 150px;">
                                        <div id="startPhotoPlaceholder">
                                            <i class="ri-image-line" style="font-size: 48px; color: #ccc;"></i>
                                            <p class="text-muted mb-0">Click button below to upload</p>
                                        </div>
                                        <input type="file" id="startPhoto" name="startPhoto" accept="image/*" style="display: none;" />
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm w-50" onclick="triggerFileInput('startPhoto', true)">
                                            <i class="ri-camera-line"></i> Camera
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm w-50" onclick="triggerFileInput('startPhoto', false)">
                                            <i class="ri-folder-image-line"></i> Gallery
                                        </button>
                                    </div>
                                    <label for="openingReading" class="form-label fw-semibold mt-1">Opening Reading</label>
                                    <input type="number" class="form-control" id="openingReading" name="openingReading" placeholder="Enter opening reading" />
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border mb-0">
                                <div class="card-body">
                                    <label class="form-label fw-semibold">End Meter Photo</label>
                                    <div class="photo-upload-box border rounded text-center p-3 mb-2" style="min-height: 150px; background: #f8f9fa;">
                                        <img id="endPhotoPreview" src="#" alt="End Photo" style="display:none; max-width: 100%; max-height: 150px;">
                                        <div id="endPhotoPlaceholder">
                                            <i class="ri-image-line" style="font-size: 48px; color: #ccc;"></i>
                                            <p class="text-muted mb-0">Click button below to upload</p>
                                        </div>
                                        <input type="file" id="endPhoto" name="endPhoto" accept="image/*" style="display: none;" />
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm w-50" onclick="triggerFileInput('endPhoto', true)">
                                            <i class="ri-camera-line"></i> Camera
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm w-50" onclick="triggerFileInput('endPhoto', false)">
                                            <i class="ri-folder-image-line"></i> Gallery
                                        </button>
                                    </div>
                                    <label for="closingReading" class="form-label fw-semibold mt-2">Closing Reading</label>
                                    <input type="number" class="form-control" id="closingReading" name="closingReading" placeholder="Enter closing reading" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label for="totalKM" class="form-label fw-semibold">Total KM</label>
                        <input type="text" class="form-control" id="totalKM" name="totalKM" readonly />
                    </div>

                    <div class="mb-1">
                        <label for="remark" class="form-label fw-semibold">Remark <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="remark" name="remark" rows="1" placeholder="Enter your remark" maxlength="200"></textarea>
                        <small class="form-text text-muted float-end"><span id="remarkCount">0</span>/200</small>
                    </div>

                    <div id="dlMessageContainer" style="display: none;">
                        <div class="alert alert-danger" id="dlMessage"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- DA Claim Modal -->
<div class="modal fade" id="daClaimModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="daClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center justify-content-between">
                <h5 class="modal-title" id="daClaimModalLabel">
                    <i class="ri-money-dollar-circle-line"></i> Daily Allowance Claim
                </h5>
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <button type="button" class="btn btn-sm btn-success" id="submitDaBtn">
                        Submit
                        <span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"
                            style="display: none;"></span>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="daMessage" class="alert alert-danger" style="display: none;"></div>

                <form id="daClaimForm" enctype="multipart/form-data">
                    @csrf

                    <input type="hidden" name="EmployeeID" id="daEmployeeID" value="{{ session('employee_id') }}" />
                    <input type="hidden" id="HqIn" value="" />
                    <input type="hidden" id="HqOut" value="" />
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="claimdate" class="form-label fw-semibold">
                                Bill Date <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" value="{{ date('d-m-Y') }}" id="claimdate"
                                name="mclaim_date" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="claimtype" class="form-label fw-semibold">
                                Claim Type <span class="text-danger">*</span>
                            </label>
                            <select id="claimtype" name="mclaimtype" class="form-select" required>
                                <option value="">---- Select ----</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label fw-semibold">
                                Amount <span class="text-danger">*</span>
                            </label>
                            <input type="number" id="amount" name="mamount" class="form-control"
                                placeholder="Amount" readonly required />
                        </div>
                        <div class="col-md-6 mb-3" id="file-upload-row" style="display: none;">
                            <label for="uploadedFile" class="form-label fw-semibold">
                                Upload File <span class="text-danger">*</span>
                            </label>
                            <input type="file" id="uploadedFile" name="uploadedFile" class="form-control"
                                accept=".jpg,.jpeg,.png" />
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="remarks" class="form-label fw-semibold">
                                Remark <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="remarks" name="mremarks" rows="2"
                                placeholder="---- Type remark ----" required></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Upload Claim Modal -->
<div class="modal fade" id="uploadClaimModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="uploadClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center justify-content-between">
                <h5 class="modal-title" id="uploadClaimModalLabel">
                    <i class="ri-upload-cloud-2-line"></i> Upload Bill
                </h5>
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <button type="button" class="btn btn-sm btn-success" id="submitUploadClaimBtn">
                        Submit
                        <span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true" style="display: none;"></span>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="uploadClaimMessage" class="alert alert-danger" style="display: none;"></div>
                <form id="uploadClaimForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="temp_punch_id" id="upload_temp_punch_id" value="0" />
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="uploadClaimType" class="form-label fw-semibold">
                                Claim Type <span class="text-danger">*</span>
                            </label>
                            <select id="uploadClaimType" name="claimtype" class="form-select" required>
                                <option value="">--Select Claim Type--</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="uploadBillDate" class="form-label fw-semibold">
                                Bill Date <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" value="" id="uploadBillDate" name="uploadBillDate"
                                placeholder="dd/mm/yyyy" required />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3 upload-file-wrapper">
                            <label class="form-label fw-semibold">
                                Upload File <span class="text-danger">*</span>
                            </label>
                            <input type="file" id="uploadNewFile" name="NewFile[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf" multiple required />
                            <small class="text-muted">
                                Allowed: Max 3 Images + 2 PDF files (Total max 5 files)
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="uploadAmount" class="form-label fw-semibold">
                                Amount <span class="text-danger">*</span>
                            </label>
                            <input type="number" id="uploadAmount" name="amount" class="form-control"
                                placeholder="Bill Amount" required />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12" id="dynamicFields"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="uploadRemark" class="form-label fw-semibold">
                                Remark
                            </label>
                            <textarea id="uploadRemark" name="Rmk" class="form-control"
                                placeholder="Type Any Remark"></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Activity Claim Modal -->
<div class="modal fade" id="activityClaimModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="activityClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center justify-content-between">
                <h5 class="modal-title" id="activityClaimModalLabel">
                    <i class="ri-article-line"></i> Upload Activity Bills
                </h5>
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <button type="button" class="btn btn-sm btn-success" id="submitActivityClaimBtn">
                        Submit
                        <span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true" style="display: none;"></span>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="activityClaimMessage" class="alert alert-danger" style="display: none;"></div>
                <form id="activityClaimForm" enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="activityClaimSelect" class="form-label fw-semibold">
                                Activity <span class="text-danger">*</span>
                            </label>
                            <select id="activityClaimSelect" name="activity_claim" class="form-select" required>
                                <option value="">-----Select-----</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="activityTypeSelect" class="form-label fw-semibold">
                                Activity Type <span class="text-danger">*</span>
                            </label>
                            <select id="activityTypeSelect" name="activity_type" class="form-select" required>
                                <option value="">-----Select-----</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="activityBillDateInput" class="form-label fw-semibold">
                                Bill Date <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" value="{{ date('d-m-Y') }}"
                                id="activityBillDateInput" name="activity_bill_date" required />
                        </div>
                    </div>

                    @if($depId == 17)
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="trialNoInput" class="form-label fw-semibold">
                                Trial No. <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="trialNoInput" name="trial_no" class="form-control" required />
                        </div>
                    </div>
                    @endif

                    <div id="activityDynamicFields"></div>

                    {{-- REQ-003: Participant List (mandatory for Team Meetings, RCT, Retailer Meetings) --}}
                    <div id="activityParticipantSection" style="display:none;" class="mb-3">
                        <div class="card border-0" style="background:linear-gradient(135deg,rgba(var(--vz-primary-rgb),0.06),rgba(var(--vz-primary-rgb),0.02));border-radius:10px;padding:14px;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <strong style="font-size:13px;"><i class="ri-group-line me-1 text-primary"></i>Participant List <span class="text-danger pl-req">*</span><span class="text-muted fw-normal pl-opt" style="display:none;font-size:11px;">(optional)</span></strong>
                                <span class="badge bg-primary-subtle text-primary" style="font-size:10px;" id="activityParticipantTypeBadge"></span>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Department <span class="text-danger pl-req">*</span></label>
                                    <input type="text" class="form-control form-control-sm" name="participant_department" id="activityParticipantDept" placeholder="e.g. Sales, PD" aria-label="e.g. Sales, PD">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">No. of Participants <span class="text-danger pl-req">*</span></label>
                                    <input type="number" class="form-control form-control-sm" name="participant_count" id="activityParticipantCount" min="1" placeholder="Enter count" aria-label="Enter count">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Participant Names (comma-separated)</label>
                                <textarea class="form-control form-control-sm" name="participant_names" id="activityParticipantNames" rows="2" placeholder="e.g. Ramesh Kumar, Suresh Patel, ..." aria-label="e.g. Ramesh Kumar, Suresh Patel,"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Upload Participant List <span class="text-muted">(Excel / PDF / Photo)</span></label>
                                <input type="file" class="form-control form-control-sm" name="participant_list_file" id="activityParticipantFile"
                                    accept=".xlsx,.xls,.pdf,.jpg,.jpeg,.png,.webp">
                                <div class="form-text">Accepted: Excel (.xlsx/.xls), PDF, or Photo (JPG/PNG).
                                    <a href="{{ asset(config('claim_rules.participants.format_file', 'templates/participant_list_format.xlsx')) }}" download><i class="ri-download-2-line"></i> Download participant list format</a></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="activityRemarkInput" class="form-label fw-semibold">
                                Remark
                            </label>
                            <textarea id="activityRemarkInput" name="activity_remark" class="form-control"
                                rows="2" placeholder="Type Any Remark"></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@php
$employeeId = session('employee_id');
$yearId = session('year_id');
$companyId = session('company_id');
@endphp
@endsection
@push('scripts')f
<script>
    $(document).ready(function() {

        function showMessage(message, type, modalId) {
            let messageDiv;
            messageDiv = $(`${modalId}`);
            if (messageDiv.length) {
                messageDiv.removeClass("alert-danger alert-success")
                    .addClass("alert-" + type)
                    .text(message)
                    .show();

                setTimeout(function() {
                    messageDiv.fadeOut();
                }, 15000);
            }
        }

        function toggleLoading(btnId, show = true) {
            let btn = $(btnId);
            if (show) {
                btn.find(".spinner-border").show();
                btn.prop("disabled", true);
            } else {
                btn.find(".spinner-border").hide();
                btn.prop("disabled", false);
            }
        }

        function initClaimDatePicker(selector, opts) {
            if (document.querySelector(selector)._flatpickr) {
                document.querySelector(selector)._flatpickr.destroy();
            }
            opts = opts || {};
            const today = new Date();
            flatpickr(selector, {
                dateFormat: opts.format || "d-m-Y",
                enableYearSelection: true,
                minDate: opts.minDate || null,
                maxDate: today,
                defaultDate: opts.noDefault ? null : today,
                onReady() {
                    addCustomButtons(this);
                },
            });
        }

        let monthFilter = `
           <label class="d-flex align-items-center ms-2">
               <span class="me-2">Month:</span>
               <select id="filterMonth" class="form-select form-select-sm" style="width:140px;">
                   <option value="">Latest month</option>
                   <option value="all">All months</option>
                   <option value="4">April</option>
                   <option value="5">May</option>
                   <option value="6">June</option>
                   <option value="7">July</option>
                   <option value="8">August</option>
                   <option value="9">September</option>
                   <option value="10">October</option>
                   <option value="11">November</option>
                   <option value="12">December</option>
                   <option value="1">January</option>
                   <option value="2">February</option>
                   <option value="3">March</option>
               </select>
           </label>
       `;

        let table = $("#claimsTable").DataTable({
            processing: true,
            serverSide: false,
            searching: false,
            paging: true,
            pageLength: 10,
            info: true,
            ordering: false,
            dom: 'rtip',
            ajax: {
                url: "{{ route('claim.list.data') }}",
                data: function(d) {
                    d.month = $("#filterMonth").val();
                },
                // UPLOAD-01: show which month the server picked when none was chosen
                dataSrc: function(json) {
                    if (json.month && !$("#filterMonth").val()) {
                        $("#filterMonth").val(json.month);
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: "ExpId",           className: "text-center fw-semibold text-primary" },
                { data: "ClaimName",       className: "text-start" },
                { data: "ClaimMonth",      className: "text-center",
                  render: function(data) {
                    const m = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    return m[parseInt(data)] || data || '—';
                  }
                },
                { data: "BillDate",        className: "text-center" },
                { data: "CrDate",          className: "text-center" },
                { data: "FilledTAmt",      className: "text-end fw-semibold" },
                { data: "Images",          className: "text-center", orderable: false, searchable: false },
                { data: "DateEntryRemark", className: "text-start",
                  render: function(data) {
                    if (!data) return '<span class="text-muted">—</span>';
                    const safe = $('<div>').text(data).html();
                    return safe.length > 40
                        ? '<span title="' + safe + '">' + safe.substring(0, 40) + '…</span>'
                        : safe;
                  }
                },
                { data: "ClaimStatus",     className: "text-center", orderable: false, searchable: false }
            ]
        });

        $("div.month-filter").html(monthFilter);

        $(document).on("change", "#filterMonth", function() {
            table.ajax.reload();
        });

        $("#openOdometerBtn").on("click", function() {
            loadOdometerData("{{ date('Y-m-d') }}");
            // ODO-FY-01: back-dated odometer readings - any day of the current financial year up to today
            initClaimDatePicker("#billDate", { minDate: "{{ \App\Services\ClaimRules\ClaimSubmissionValidator::odometerMinDate() }}" });
            $("#odometerModal").modal('show');
        });
        // the "Odometer" button on My Claims opens this page with ?odometer=1
        if (new URLSearchParams(window.location.search).get("odometer") === "1") {
            setTimeout(function () { $("#openOdometerBtn").trigger("click"); }, 300);
        }

        $("#billDate").on("change", function() {
            const selectedDate = $(this).val();
            if (selectedDate) {
                loadOdometerData(selectedDate);
            }
        });

        function loadOdometerData(date) {
            $.ajax({
                url: "{{ route('odometer.data') }}",
                type: "GET",
                data: {
                    date: date
                },
                success: function(response) {
                    if (response.success) {
                        $("#billDate").val(date);
                        const apiData = response.apiData;
                        const existingData = response.existingData;
                        const currentDateOdoDetails = response.apiData.CurrentDate_Odo_Details;
                        $("#twoWheeler").prop("disabled", apiData.VehicleType2W == 0);
                        $("#fourWheeler").prop("disabled", apiData.VehicleType4W == 0);

                        if (apiData.VehicleType2W > 0) {
                            $("#twoWheeler").prop("checked", true);
                        } else if (apiData.VehicleType4W > 0) {
                            $("#fourWheeler").prop("checked", true);
                        }
                        if (apiData.DLExpired == 'Y') {
                            $("#dlMessage").text(apiData.DLMessage);
                            $("#dlMessageContainer").show();
                            $("#submitOdometerBtn").prop("disabled", true);
                        } else {
                            $("#dlMessageContainer").hide();
                            $("#submitOdometerBtn").prop("disabled", false);
                        }
                        if (currentDateOdoDetails && currentDateOdoDetails.length > 0) {
                            let odo = currentDateOdoDetails[0];
                            if (odo.opening_filepath) {
                                $("#startPhotoPreview").attr("src", odo.opening_filepath).show();
                                $("#startPhotoPlaceholder").hide();
                            } else {
                                $("#startPhotoPreview").hide();
                                $("#startPhotoPlaceholder").show();
                            }
                            if (odo.closing_filepath) {
                                $("#endPhotoPreview").attr("src", odo.closing_filepath).show();
                                $("#endPhotoPlaceholder").hide();
                            } else {
                                $("#endPhotoPreview").hide();
                                $("#endPhotoPlaceholder").show();
                            }
                        }
                        if (existingData) {
                            $("#temp_punch_id").val(existingData.temp_punch_id || 0);
                            $("#openingReading").val(existingData.odomtr_opening || '');
                            $("#closingReading").val(existingData.odomtr_closing || '');
                            $("#totalKM").val(existingData.TotKm || '');
                            $("#remark").val(existingData.DateEntryRemark || '');
                            $("#remarkCount").text(existingData.remark ? existingData.remark.length : 0);
                            if (existingData.odomtr_opening && existingData.odomtr_closing &&
                                existingData.opening_filepath && existingData.closing_filepath) {
                                $("#submitOdometerBtn").prop("disabled", true).text("Already Submitted");
                            }
                        } else {
                            $("#odometerForm")[0].reset();
                            $("#temp_punch_id").val(0);
                            $("#startPhotoPreview").hide();
                            $("#endPhotoPreview").hide();
                            $("#startPhotoPlaceholder").show();
                            $("#endPhotoPlaceholder").show();
                            $("#submitOdometerBtn").prop("disabled", false).text("Submit");
                        }
                    }
                },
                error: function() {
                    showMessage("Failed to load odometer data", "danger", "#odometerModal");
                }
            });
        }

        $("#closingReading").on("input", function() {
            const opening = parseFloat($("#openingReading").val()) || 0;
            const closing = parseFloat($(this).val()) || 0;
            const totalKM = closing - opening;
            $("#totalKM").val(totalKM >= 0 ? totalKM : "");
        });

        $("#remark").on("input", function() {
            const charCount = this.value.length;
            $("#remarkCount").text(charCount);
        });

        window.triggerFileInput = function(inputId, useCamera) {
            const input = document.getElementById(inputId);
            if (useCamera) {
                input.setAttribute("capture", "environment");
            } else {
                input.removeAttribute("capture");
            }
            input.click();
        }

        $("#startPhoto").on("change", function() {
            previewPhoto(this, "startPhotoPreview", "startPhotoPlaceholder");
            handleFileUpload(this, "opening_file", "openingReading");
        });

        $("#endPhoto").on("change", function() {
            previewPhoto(this, "endPhotoPreview", "endPhotoPlaceholder");
            handleFileUpload(this, "closing_file", "closingReading");
        });

        function previewPhoto(input, previewId, placeholderId) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $("#" + previewId).attr("src", e.target.result).show();
                    $("#" + placeholderId).hide();
                };
                reader.readAsDataURL(file);
            }
        }

        function handleFileUpload(fileInput, type, readingFieldId) {
            const file = fileInput.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append("photo", file);
            formData.append("type", type);
            formData.append("emp_id", $("#emp_id").val());
            formData.append("temp_punch_id", $("#temp_punch_id").val());
            $.ajax({
                url: "{{ route('odometer.upload') }}",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                beforeSend: function() {
                    toggleLoading("#submitOdometerBtn", true);
                },
                success: function(response) {
                    if (response.success && response.response && response.response.data) {
                        const data = response.response.data;
                        if (type === "opening_file") {
                            $("#" + readingFieldId).val(data.opening || "");
                        } else if (type === "closing_file") {
                            $("#" + readingFieldId).val(data.closeing || "");
                        }
                        $("#temp_punch_id").val(data.temp_punch_id || "0");
                        const opening = parseFloat($("#openingReading").val()) || 0;
                        const closing = parseFloat($("#closingReading").val()) || 0;
                        const totalKM = closing - opening;
                        $("#totalKM").val(totalKM >= 0 ? totalKM : "");
                    } else {
                        showMessage("Error: " + (response.message || "Unexpected response"), "danger", "#odometerModal");
                    }
                },
                error: function() {
                    showMessage("Error uploading file", "danger", "#odometerModal");
                },
                complete: function() {
                    toggleLoading("#submitOdometerBtn", false);
                }
            });
        }

        $("#submitOdometerBtn").on("click", function() {
            const formData = new FormData($("#odometerForm")[0]);
            const hasStartPhoto = document.getElementById("startPhoto").files.length > 0;
            const hasEndPhoto = document.getElementById("endPhoto").files.length > 0;
            let imageType = "";

            if (hasStartPhoto && !hasEndPhoto) {
                imageType = "opening";
            } else if (!hasStartPhoto && hasEndPhoto) {
                imageType = "closing";
            } else if (hasStartPhoto && hasEndPhoto) {
                imageType = "opening_closing";
            } else {
                showMessage("Please upload at least one photo (Start or End).", "danger", "#odometerModal");
                return;
            }

            formData.append("imageType", imageType);

            if (!$('input[name="vehicleType"]:checked').val()) {
                showMessage("Please select a Vehicle Type.", "danger", "#odometerModal");
                return;
            }

            if (!$("#billDate").val()) {
                showMessage("Please select a Bill Date.", "danger", "#odometerModal");
                return;
            }

            if (!$("#remark").val().trim()) {
                showMessage("Please enter a Remark.", "danger", "#odometerModal");
                return;
            }

            if (!$("#openingReading").val()) {
                showMessage("Please enter the Opening Reading.", "danger", "#odometerModal");
                return;
            }

            const opening = parseFloat($("#openingReading").val());
            const closing = parseFloat($("#closingReading").val());

            if (closing && closing <= opening) {
                showMessage("Closing Reading must be greater than Opening Reading.", "danger", "#odometerModal");
                return;
            }

            if (!confirm("Are you ready to submit your odometer reading?")) {
                return;
            }

            $.ajax({
                url: "{{ route('odometer.upload') }}",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                beforeSend: function() {
                    toggleLoading("#submitOdometerBtn", true);
                },
                success: function(response) {
                    if (response.success) {
                        showMessage("Odometer submitted successfully!", "success", "#odometerModal");
                        setTimeout(function() {
                            $("#odometerModal").modal('hide');
                            table.ajax.reload();
                        }, 2000);
                    } else {
                        showMessage(response.message || "Something went wrong.", "danger", "#odometerModal");
                    }
                },
                error: function() {
                    showMessage("Oops! Something went wrong while submitting. Please try again.", "danger", "#odometerModal");
                },
                complete: function() {
                    toggleLoading("#submitOdometerBtn", false);
                }
            });
        });

        $("#openDailyAllowBtn").on("click", function() {
            loadDaData();
            initClaimDatePicker("#claimdate");
            $("#daClaimModal").modal('show');
        });

        $('#daClaimModal').on('hidden.bs.modal', function() {
            $("#daClaimForm")[0].reset();
            $("#daMessage").hide();
            $("#file-upload-row").hide();
            $("#uploadedFile").removeAttr('required');
            $("#submitDaBtn").prop("disabled", false).html('Submit <span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true" style="display: none;"></span>');
        });

        function addCustomButtons(fp) {
            const container = document.createElement("div");
            container.className = "flatpickr-extra-buttons";
            const lastBtn = document.createElement("button");
            lastBtn.type = "button";
            lastBtn.className = "fp-btn fp-last-btn";
            lastBtn.innerHTML = '<i class="ri-calendar-check-line"></i>';
            lastBtn.onclick = () => {
                fp.setDate(today, true);
            };
            const clearBtn = document.createElement("button");
            clearBtn.type = "button";
            clearBtn.className = "fp-btn fp-clear-btn";
            clearBtn.innerHTML = '<i class="ri-close-circle-line"></i>';
            clearBtn.onclick = () => {
                fp.clear();
                fp.close();
            };
            container.appendChild(lastBtn);
            container.appendChild(clearBtn);
            fp.calendarContainer.appendChild(container);
        }

        function loadDaData() {
            $.ajax({
                url: "{{ route('da.data') }}",
                type: "GET",
                success: function(response) {
                    if (response.success) {
                        $("#HqIn").val(response.DAInside);
                        $("#HqOut").val(response.DAOutside);
                        $("#claimtype").empty().append('<option value="">---- Select ----</option>');
                        response.claimTypes.forEach(function(claim) {
                            $("#claimtype").append(`<option value="${claim.ClaimId}">${claim.ClaimName}</option>`);
                        });
                        if (response.fyearId < 6) {
                            $("#submitDaBtn").hide();
                        } else {
                            $("#submitDaBtn").show();
                        }
                    }
                },
                error: function() {
                    showMessage("Failed to load DA claim data", "danger", "#daClaimModal");
                }
            });
        }

        $("#claimtype").on("change", function() {
            const claimType = $(this).val();
            const fileUploadRow = $("#file-upload-row");
            const uploadedFileInput = $("#uploadedFile");
            const amount = $("#amount");
            if (claimType === '19') {
                amount.val($("#HqIn").val());
                fileUploadRow.hide();
                uploadedFileInput.removeAttr('required');
            } else if (claimType === '20') {
                amount.val($("#HqOut").val());
                fileUploadRow.show();
                uploadedFileInput.attr('required', 'required');
            } else {
                amount.val('');
                fileUploadRow.hide();
                uploadedFileInput.removeAttr('required');
            }
        });

        $("#claimdate").on("change", function() {
            const billDate = $(this).val();
            const employeeID = $("#daEmployeeID").val();
            if (!billDate) return;
            $.ajax({
                url: "{{ route('da.check.holiday') }}",
                type: "POST",
                data: {
                    billDate: billDate,
                    employeeID: employeeID,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status === 'sunday') {
                        if (!confirm('The selected bill date falls on a Sunday. Do you want to proceed?')) {
                            $("#claimdate").val("{{ date('d-m-Y') }}");
                        }
                    } else if (response.status === 'holiday') {
                        if (!confirm('The selected bill date falls on a holiday. Do you want to proceed?')) {
                            $("#claimdate").val("{{ date('d-m-Y') }}");
                        }
                    }
                }
            });
        });

        $("#submitDaBtn").on("click", function() {
            const claimType = $("#claimtype").val();
            const amount = parseFloat($("#amount").val()) || 0;
            const billDate = $("#claimdate").val();
            const maxAmt = claimType === '19' ? parseFloat($("#HqIn").val()) || 0 : parseFloat($("#HqOut").val()) || 0;

            if (!billDate) {
                showMessage("Please enter a bill date.", "danger", "#daMessage");
                return;
            }

            if (!claimType) {
                showMessage("Please select a claim type.", "danger", "#daMessage");
                return;
            }

            if (amount < 0) {
                showMessage("Amount cannot be less than 0.", "danger", "#daMessage");
                return;
            }

            if (maxAmt > 0 && amount > maxAmt) {
                showMessage("Amount cannot be more than " + maxAmt + ".", "danger", "#daMessage");
                return;
            }

            if (claimType === '20' && !$("#uploadedFile")[0].files.length) {
                // UAT E-12: same wording as the server-side tracker #17 rule.
                showMessage("DA Outstation (DOS) claim: please upload the night-halt proof (hotel bill or halt certificate) before submitting.", "danger", "#daMessage");
                return;
            }

            if (claimType === '20') {
                const fileInput = $("#uploadedFile")[0];
                const file = fileInput.files[0];
                if (file) {
                    const allowedTypes = ["image/jpeg", "image/jpg", "image/png"];
                    const fileType = file.type.toLowerCase();
                    if (!allowedTypes.includes(fileType)) {
                        showMessage("Invalid file type. Only JPG, JPEG, PNG are allowed.", "danger", "#daMessage");
                        return;
                    }
                    const maxSize = 2 * 1024 * 1024;
                    if (file.size > maxSize) {
                        showMessage("File size must be less than 2MB.", "danger", "#daMessage");
                        return;
                    }
                }
            }


            if (!$("#remarks").val().trim()) {
                showMessage("Please enter a remark.", "danger", "#daMessage");
                return;
            }

            const formData = new FormData($("#daClaimForm")[0]);
            $.ajax({
                url: "{{ route('da.submit') }}",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                beforeSend: function() {
                    toggleLoading("#submitDaBtn", true);
                },
                success: function(response) {
                    if (response.status === 'success') {
                        showMessage(response.msg, "success", "#daMessage");
                        setTimeout(function() {
                            $("#daClaimModal").modal('hide');
                            table.ajax.reload();
                        }, 2000);
                    } else {
                        showMessage(response.msg, "danger", "#daMessage");
                    }
                },
                error: function() {
                    showMessage("An error occurred while submitting the claim.", "danger", "#daMessage");
                },
                complete: function() {
                    toggleLoading("#submitDaBtn", false);
                }
            });
        });

        $("#openClaimBtn").on("click", function() {
            loadUploadClaimData();
            // BILLDATE-02: bill date is dd/mm/yyyy and starts empty, so the date read from the
            // bill fills it; a date the user picks is never overwritten.
            initClaimDatePicker("#uploadBillDate", { format: "d/m/Y", noDefault: true });
            $("#uploadClaimModal").modal("show");
        });

        $("#uploadClaimModal").on("show.bs.modal", function() {
            $("#uploadClaimForm")[0].reset();
            $("#uploadAmount").val("");
            $("#uploadRemark").val("");
            $("#upload_temp_punch_id").val(0);
            $("#uploadClaimMessage").hide();
            $(".upload-file-wrapper input[type='file']").val("");
            $("#dynamicFields").html("");
            $("#submitUploadClaimBtn").prop("disabled", false).html(
                'Submit <span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true" style="display: none;"></span>'
            );
        });

        // DATE-01: parse a bill date as DD-MM-YYYY. Accepts 05/08/2026, 05-08-26, 05.08.2026,
        // 2026-08-05 and "05 Aug 2026". Returns "DD-MM-YYYY", or "" if not a real date or in the future.
        function xeasyParseBillDate(raw) {
            raw = String(raw || "").trim();
            if (!raw) return "";
            const months = {jan:1,feb:2,mar:3,apr:4,may:5,jun:6,jul:7,aug:8,sep:9,sept:9,oct:10,nov:11,dec:12};
            let d, m, y, mt;
            if ((mt = raw.match(/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/))) { y = +mt[1]; m = +mt[2]; d = +mt[3]; }
            else if ((mt = raw.match(/^(\d{1,2})[-\/. ](\d{1,2})[-\/. ](\d{2,4})/))) { d = +mt[1]; m = +mt[2]; y = +mt[3]; }
            else if ((mt = raw.match(/^(\d{1,2})[-\/. ]?([A-Za-z]{3,9})[-\/., ]*(\d{2,4})/))) { d = +mt[1]; m = months[mt[2].toLowerCase().slice(0, 4)] || months[mt[2].toLowerCase().slice(0, 3)]; y = +mt[3]; }
            else return "";
            if (y < 100) y += 2000;
            const dt = new Date(y, (m || 0) - 1, d);
            if (!m || dt.getFullYear() !== y || dt.getMonth() !== m - 1 || dt.getDate() !== d) return "";
            const today = new Date(); today.setHours(23, 59, 59, 999);
            if (dt > today) return "";
            return String(d).padStart(2, "0") + "/" + String(m).padStart(2, "0") + "/" + y;
        }

        function loadUploadClaimData() {
            $.ajax({
                url: "{{ route('claim.upload.data') }}",
                type: "GET",
                success: function(response) {
                    if (response.success) {
                        $("#uploadClaimType")
                            .empty()
                            .append('<option value="">--Select Claim Type--</option>');
                        response.claimTypes.forEach(function(claim) {
                            $("#uploadClaimType").append(
                                `<option value="${claim.ClaimId}">${claim.ClaimName}</option>`
                            );
                        });
                    }
                },
                error: function() {
                    showMessage("Failed to load claim data", "danger");
                },
            });
        }

        const table1Html = `
        <div class="table-responsive mb-1">
        <table class="table table-bordered table-sm expense-table" id="table1">
            <thead class="table-light">
                <tr>
                    <th style="width:80px;">Sr No.</th>
                    <th>Head</th>
                    <th style="width:120px;">Amount</th>
                    <th style="width:200px;">Remark</th>
                    <th style="width:150px;">File</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>1</td>
                    <td>Toll Pass</td>
                    <td><input type="number" name="Toll_Pass" class="form-control form-control-sm amount-field" data-table="table1"></td>
                    <td><input type="text" name="Remark_Toll_Pass" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Toll_Pass" class="form-control form-control-sm"></td>
                </tr>

                <tr>
                    <td>2</td>
                    <td>Toll Tax</td>
                    <td><input type="number" name="Toll_Tax" class="form-control form-control-sm amount-field" data-table="table1"></td>
                    <td><input type="text" name="Remark_Toll_Tax" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Toll_Tax" class="form-control form-control-sm"></td>
                </tr>

                <tr>
                    <td>3</td>
                    <td>International Recharge</td>
                    <td><input type="number" name="International_Recharge" class="form-control form-control-sm amount-field" data-table="table1"></td>
                    <td><input type="text" name="Remark_International_Recharge" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_International_Recharge" class="form-control form-control-sm"></td>
                </tr>

                <tr>
                    <td>4</td>
                    <td>Printing / Stationery</td>
                    <td><input type="number" name="Printing_Stationery" class="form-control form-control-sm amount-field" data-table="table1"></td>
                    <td><input type="text" name="Remark_Printing_Stationery" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Printing_Stationery" class="form-control form-control-sm"></td>
                </tr>

                <tr>
                    <td>5</td>
                    <td>Others (Compulsory Remark)</td>
                    <td><input type="number" name="Others_t1" class="form-control form-control-sm amount-field" data-table="table1"></td>
                    <td><input type="text" name="Remark_Others_t1" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Others_t1" class="form-control form-control-sm"></td>
                </tr>
            </tbody>

            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">Total</th>
                    <th><input type="text" id="total_table1" class="form-control form-control-sm" readonly></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
        </div>
        `;


        const table2Html = `
        <div class="table-responsive mb-1">
        <table class="table table-bordered table-sm expense-table" id="table2">
            <thead class="table-light">
                <tr>
                    <th style="width:80px;">Sr No.</th>
                    <th>Head</th>
                    <th style="width:120px;">Amount</th>
                    <th style="width:200px;">Remark</th>
                    <th style="width:150px;">File</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>#</td>
                    <td>
                        Pax Details <span class="text-danger">*</span>
                        <small class="text-muted d-block" style="font-size: 11px;">(Enter the details excluding yourself)</small>
                    </td>
                    <td colspan="3">
                        <input type="text" name="PaxDetails" id="paxDetailsInput" class="form-control form-control-sm" placeholder="Enter the details excluding yourself" aria-label="Enter the details excluding yourself">
                    </td>
                </tr>

                <tr>
                    <td>1</td>
                    <td>Meeting Hall</td>
                    <td><input type="number" name="Meeting_Hall" class="form-control form-control-sm amount-field" data-table="table2"></td>
                    <td><input type="text" name="Remark_Meeting_Hall" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Meeting_Hall" class="form-control form-control-sm"></td>
                </tr>

                <tr>
                    <td>2</td>
                    <td>Lodging</td>
                    <td><input type="number" name="Lodging" class="form-control form-control-sm amount-field" data-table="table2"></td>
                    <td><input type="text" name="Remark_Lodging" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Lodging" class="form-control form-control-sm"></td>
                </tr>

                <tr>
                    <td>3</td>
                    <td>Meals</td>
                    <td><input type="number" name="Meals" class="form-control form-control-sm amount-field" data-table="table2"></td>
                    <td><input type="text" name="Remark_Meals" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Meals" class="form-control form-control-sm"></td>
                </tr>

                <tr>
                    <td>4</td>
                    <td>Business Entertainment</td>
                    <td><input type="number" name="Business_Entertainment" class="form-control form-control-sm amount-field" data-table="table2"></td>
                    <td><input type="text" name="Remark_Business_Entertainment" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Business_Entertainment" class="form-control form-control-sm"></td>
                </tr>

                <tr>
                    <td>5</td>
                    <td>Others (Remark Company)</td>
                    <td><input type="number" name="Others_t2" class="form-control form-control-sm amount-field" data-table="table2"></td>
                    <td><input type="text" name="Remark_Others_t2" class="form-control form-control-sm"></td>
                    <td><input type="file" name="File_Others_t2" class="form-control form-control-sm"></td>
                </tr>
            </tbody>

            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">Total</th>
                    <th><input type="text" id="total_table2" class="form-control form-control-sm" readonly></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
        </div>
        `;

        // Group Meal (Claim ID: 17) - Group checkbox first, then PAX and participants
        const groupMealHtml = `
        <div class="card border-success mb-3" id="groupMealSection">
            <div class="card-header bg-success-subtle text-success fw-bold d-flex justify-content-between align-items-center">
                <span><i class="ri-restaurant-line"></i> Group Meal Details</span>
                <span class="badge bg-success" id="mealParticipantCount" style="display:none;"></span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="groupMealCheckbox" name="group_meal" value="true">
                            <label class="form-check-label fw-semibold text-success ms-2" for="groupMealCheckbox">
                                Enable Group Meal Claim
                            </label>
                        </div>
                    </div>
                </div>
                <div id="groupMealFields" style="display:none;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">PAX No. <span class="text-danger meal-pax-asterisk" style="display:none;">*</span></label>
                            <input type="number" name="PaxDetails" id="mealPaxInput" class="form-control form-control-sm" min="1" placeholder="Enter PAX" aria-label="Enter PAX">
                            <small class="text-muted d-block mt-1" style="font-size: 11px;"><i class="ri-information-line text-info me-1"></i>Enter the details excluding yourself</small>
                        </div>
                    </div>

                    <!-- 1. Employee Search Section -->
                    <div id="groupMealParticipants" class="mb-3">
                        <label class="form-label fw-semibold small text-primary mb-1">
                            <i class="ri-user-search-line"></i> Select Employee Participants <span class="text-muted fw-normal ms-1" style="font-size: 11px;">(Enter the details excluding yourself)</span>
                        </label>
                        <div class="row mb-2">
                            <div class="col-md-8">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="mealEmployeeSearch" class="form-control" placeholder="Search by Employee Code, Name, or Grade" aria-label="Search by Employee Code, Name, or Grade">
                                    <button class="btn btn-outline-success" type="button" id="mealSearchBtn">
                                        <i class="ri-search-line"></i> Search
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="mealSearchResults" class="list-group mb-2" style="max-height:160px;overflow-y:auto;"></div>
                        <div id="mealSelectedList" class="mb-2"></div>
                        <input type="hidden" name="meal_participants" id="mealParticipantsInput">
                    </div>

                    <!-- 2. Other Participants (Category Lists -> No. of PAX on that Category -> Generate Rows) -->
                    <div class="card border border-dashed p-3 mb-2 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <label class="form-label fw-bold mb-0 text-dark small">
                                    <i class="ri-user-add-line text-success me-1"></i> Other Participants (Non-Employee / External Categories)
                                </label>
                                <div class="text-muted" style="font-size: 11px;">Enter No. of PAX for categories below, and click Generate for that category (excluding yourself).</div>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" id="mealCategoryPaxBadge">
                                Category PAX: <span id="mealCategoryPaxTotal">0</span>
                            </span>
                        </div>

                        <div id="otherMealLimitAlert" class="text-danger small fw-semibold mb-2" style="display:none;"></div>

                        <!-- Categories list container where each category has PAX input + Generate button + its own rows below -->
                        <div id="otherMealParticipantsContainer" class="d-flex flex-column gap-2 mb-2"></div>

                        <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border" style="font-size: 12px;">
                            <span class="fw-semibold text-secondary">Total Category PAX:</span>
                            <div class="d-flex align-items-center gap-2">
                                <span id="mealTotalCategoryPax" class="text-success fw-bold">0</span>
                                <small id="mealRemainingPaxHint" class="text-muted fw-normal" style="font-size: 11px;"></small>
                            </div>
                        </div>

                        <input type="hidden" name="meal_other_participants" id="mealOtherParticipants">
                    </div>

                </div>
            </div>
        </div>
        `;

        // Lodging (Claim ID: 1) - Group checkbox first, then PAX and participants
        const LODGING_RECEIPT_THRESHOLD = @json((float) config('claim_rules.lodging.receipt_threshold', 10000));
        const lodgingHtml = `
        <div class="card border-warning mb-3" id="lodgingReceiptSection">
            <div class="card-body py-2">
                <label class="form-label fw-semibold mb-1" for="lodgingReceiptFile">
                    Online Payment Receipt <span class="text-danger">*</span>
                    <small class="text-muted fw-normal">(required when the bill amount is above Rs ${LODGING_RECEIPT_THRESHOLD.toLocaleString('en-IN')})</small>
                </label>
                <input type="file" class="form-control form-control-sm" id="lodgingReceiptFile" name="File_Payment_Receipt[]" accept=".jpg,.jpeg,.png,.pdf">
            </div>
        </div>
        <div class="card border-primary mb-3" id="lodgingSection">
            <div class="card-header bg-primary-subtle text-primary fw-bold d-flex justify-content-between align-items-center">
                <span><i class="ri-hotel-line"></i> Group Lodging Details</span>
                <span class="badge bg-primary" id="lodgingParticipantCount">Participants: 0</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="lodgingGroupCheckbox" name="is_group_lodging" value="1">
                            <label class="form-check-label fw-bold text-dark" for="lodgingGroupCheckbox">
                                <i class="ri-hotel-bed-line text-primary"></i> Is Group Lodging?
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label fw-semibold mb-0" style="white-space: nowrap;">PAX No. <span class="text-danger lodging-pax-asterisk" style="display:none;">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="lodgingPaxInput" name="lodging_pax" min="1" max="100" placeholder="e.g. 3" aria-label="e.g. 3">
                        </div>
                    </div>
                </div>

                <!-- Hidden until Group Lodging is checked -->
                <div id="groupLodgingFields" style="display: none;">
                    <div class="alert alert-info py-2 px-3 mb-2" style="font-size: 12px;">
                        <i class="ri-information-line me-1"></i> You are automatically included as the claimant (PAX #1). Add other participants below.
                    </div>

                    <!-- 1. Search & Select Employees -->
                    <div class="card border border-dashed p-3 mb-2 bg-light">
                        <label class="form-label fw-bold mb-1 text-dark small">
                            <i class="ri-user-search-line text-primary me-1"></i> Add Employee Participants
                        </label>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" class="form-control" id="lodgingEmployeeSearch" placeholder="Search employee by name or employee code..." aria-label="Search employee by name or employee code">
                            <button class="btn btn-outline-primary" type="button" id="lodgingSearchBtn"><i class="ri-search-line"></i> Search</button>
                        </div>
                        <div id="lodgingSearchResults" class="list-group mb-2" style="max-height:160px;overflow-y:auto;"></div>
                        <div id="lodgingSelectedList" class="mb-2"></div>
                        <input type="hidden" name="lodging_participants" id="lodgingParticipantsInput">
                    </div>

                    <!-- 2. Other Participants (Category Lists -> No. of PAX on that Category -> Generate Rows) -->
                    <div class="card border border-dashed p-3 mb-2 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <label class="form-label fw-bold mb-0 text-dark small">
                                    <i class="ri-user-add-line text-primary me-1"></i> Other Participants (Non-Employee / External Categories)
                                </label>
                                <div class="text-muted" style="font-size: 11px;">Enter No. of PAX for categories below, and click Generate for that category (excluding yourself).</div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" id="lodgingCategoryPaxBadge">
                                Category PAX: <span id="lodgingCategoryPaxTotal">0</span>
                            </span>
                        </div>

                        <div id="otherLodgingLimitAlert" class="text-danger small fw-semibold mb-2" style="display:none;"></div>

                        <!-- Categories list container where each category has PAX input + Generate button + its own rows below -->
                        <div id="otherLodgingParticipantsContainer" class="d-flex flex-column gap-2 mb-2"></div>

                        <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border" style="font-size: 12px;">
                            <span class="fw-semibold text-secondary">Total Category PAX:</span>
                            <div class="d-flex align-items-center gap-2">
                                <span id="lodgingTotalCategoryPax" class="text-primary fw-bold">0</span>
                                <small id="lodgingRemainingPaxHint" class="text-muted fw-normal" style="font-size: 11px;"></small>
                            </div>
                        </div>

                        <input type="hidden" name="lodging_other_participants" id="lodgingOtherParticipants">
                    </div>

                </div>
            </div>
        </div>
        `;

        // Rail Fare (Claim ID: 14) - Ticket category dropdown
        const railFareHtml = `
        <div class="card border-info mb-3" id="railFareSection">
            <div class="card-header bg-info-subtle text-info fw-bold">
                <i class="ri-train-line"></i> Rail Fare Details
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Ticket Category <span class="text-danger">*</span></label>
                        <select name="ticket_category" id="ticketCategorySelect" class="form-select form-select-sm" required>
                            <option value="">--Select Category--</option>
                            <option value="General">General</option>
                            <option value="Premium Tatkal">Premium Tatkal</option>
                            <option value="Tatkal">Tatkal</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        `;

        $(document).on("change", "#uploadClaimType", function() {
            const claimType = $(this).val();
            $("#uploadBillDate").val("");
            $("#uploadAmount").val("");
            $("#uploadRemark").val("");
            $("#upload_temp_punch_id").val(0);
            $("#uploadClaimMessage").hide();
            $("#dynamicFields").html("");
            $("#uploadNewFile").val("");
            $("#uploadNewFile").prop("disabled", false).removeAttr("required");
            $("#uploadAmount").prop("disabled", false).removeAttr("required");
            // Reset participant arrays
            mealSelectedEmployees = [];
            lodgingSelectedEmployees = [];
            const fileField = $("#uploadNewFile").closest(".col-md-6");
            const amountField = $("#uploadAmount").closest(".col-md-6");
            if (claimType === "16" || claimType === "42") {
                $("#uploadNewFile").prop("disabled", true).removeAttr("required");
                fileField.hide();
                $("#uploadAmount").prop("disabled", true).removeAttr("required");
                amountField.hide();
                if (claimType === "16") {
                    $("#dynamicFields").html(table1Html);
                } else if (claimType === "42") {
                    $("#dynamicFields").html(table2Html);
                }
            } else if (claimType === "17") {
                // Group Meal - show PAX, group meal checkbox, participant fields
                fileField.show();
                amountField.show();
                $("#uploadNewFile").prop("disabled", false).attr("required", "required");
                $("#uploadAmount").prop("disabled", false).attr("required", "required");
                $("#dynamicFields").html(groupMealHtml);
                renderMealCategoryTable();
            } else if (claimType === "1") {
                // Lodging - show PAX, group lodging checkbox, participant fields
                fileField.show();
                amountField.show();
                $("#uploadNewFile").prop("disabled", false).attr("required", "required");
                $("#uploadAmount").prop("disabled", false).attr("required", "required");
                $("#dynamicFields").html(lodgingHtml);
                renderLodgingCategoryTable();
            } else if (claimType === "14") {
                // Rail Fare - show ticket category dropdown
                fileField.show();
                amountField.show();
                $("#uploadNewFile").prop("disabled", false).attr("required", "required");
                $("#uploadAmount").prop("disabled", false).attr("required", "required");
                $("#dynamicFields").html(railFareHtml);
            } else {
                $("#uploadNewFile").prop("disabled", false).attr("required", "required");
                fileField.show();
                $("#uploadAmount").prop("disabled", false).attr("required", "required");
                amountField.show();
            }
        });

        $(document).on("input", ".amount-field", function() {
            let tableId = $(this).data("table");
            let total = 0;

            $("#" + tableId + " .amount-field").each(function() {
                total += parseFloat($(this).val()) || 0;
            });

            $("#total_" + tableId).val(total.toFixed(0));
            $("#uploadAmount").val(total.toFixed(0));
        });

        // Claimant session variables for exclusion & validation
        const currentClaimantId = "{{ session('employee_id') }}";
        const currentClaimantCode = "{{ session('emp_code') }}";
        const currentClaimantName = "{{ session('emp_name') }}";

        // ─── Group Meal (Claim 17) Handlers ─────────────────────────────────────
        var mealSelectedEmployees = [];
        var otherMealRowCounter = 0;

        $(document).on("change", "#groupMealCheckbox", function() {
            if ($(this).is(":checked")) {
                $("#groupMealFields").show();
                $("#mealPaxInput").prop("required", true);
                $(".meal-pax-asterisk").show();
                renderMealCategoryTable();
            } else {
                $("#groupMealFields").hide();
                $("#mealPaxInput").prop("required", false).removeClass("is-invalid").val("");
                $(".meal-pax-asterisk").hide();
                mealSelectedEmployees = [];
                renderMealSelected();
                $("#otherMealParticipantsContainer").empty();
                updateMealParticipantCounts();
            }
        });

        $(document).on("click", "#mealSearchBtn", function() {
            searchEmployeesFor("meal");
        });

        $(document).on("keypress", "#mealEmployeeSearch", function(e) {
            if (e.which === 13) { e.preventDefault(); searchEmployeesFor("meal"); }
        });

        // Auto-search on input (debounced)
        let mealSearchTimer = null;
        $(document).on("input", "#mealEmployeeSearch", function() {
            clearTimeout(mealSearchTimer);
            const val = $(this).val().trim();
            if (!val) { $("#mealSearchResults").html(""); return; }
            mealSearchTimer = setTimeout(function() { searchEmployeesFor("meal"); }, 400);
        });

        // 1 & 2 & 4. Exclude Claimant, No Duplicate Employee, Exclude Across Group Companies
        $(document).on("click", ".meal-add-emp", function() {
            const code = String($(this).data("code") || "").trim();
            const newCode = String($(this).data("newcode") || "").trim();
            const empId = String($(this).data("id") || "").trim();
            const name = String($(this).data("name") || "").trim();
            const grade = String($(this).data("grade") || "").trim();
            const dept = String($(this).data("department") || "").trim();
            const company = String($(this).data("company") || "").trim();
            const isManagement = $(this).data("ismanagement") === true || $(this).data("ismanagement") === "true";

            // Exclude Claimant Validation (#1)
            if ((currentClaimantCode && (code === currentClaimantCode || newCode === currentClaimantCode)) ||
                (currentClaimantId && empId === currentClaimantId)) {
                showMessage("Claimant cannot be selected as a participant.", "danger", "#uploadClaimMessage");
                return;
            }

            const pax = parseInt($("#mealPaxInput").val()) || 0;
            const otherCount = $("#otherMealParticipantsContainer .other-participant-row").length;
            const currentTotal = mealSelectedEmployees.length + otherCount;
            if (pax > 0 && currentTotal >= pax) {
                showMessage(`Cannot add more participants than PAX limit (${pax}).`, "danger", "#uploadClaimMessage");
                return;
            }

            // No Duplicate Selection across Group Companies (#2 & #4)
            const isDuplicate = mealSelectedEmployees.some(e =>
                (e.code && (e.code === code || (newCode && e.code === newCode))) ||
                (e.new_code && (e.new_code === code || (newCode && e.new_code === newCode))) ||
                (e.id && empId && e.id === empId) ||
                (e.name.toLowerCase() === name.toLowerCase())
            );

            if (isDuplicate) {
                showMessage(`Employee "${name}" (${code}) is already added to the participant list.`, "danger", "#uploadClaimMessage");
                return;
            }

            // Unique participant cross-validation (#5)
            const otherParticipants = getOtherMealParticipantsList();
            const duplicateInOther = otherParticipants.find(o => o.name.toLowerCase() === name.toLowerCase());
            if (duplicateInOther) {
                showMessage(`"${name}" is already added under Other Participants as ${duplicateInOther.category}.`, "danger", "#uploadClaimMessage");
                return;
            }

            mealSelectedEmployees.push({ code, new_code: newCode, id: empId, name, grade, department: dept, company, is_management: isManagement });
            renderMealSelected();
            $("#mealSearchResults").html("");
            validateMealCategoryPax();
        });

        $(document).on("click", ".meal-remove-emp", function() {
            const code = String($(this).data("code") || "");
            mealSelectedEmployees = mealSelectedEmployees.filter(e => e.code !== code && e.new_code !== code);
            renderMealSelected();
            validateMealCategoryPax();
        });

        function renderMealSelected() {
            let html = '';
            mealSelectedEmployees.forEach(emp => {
                const mgmtBadge = emp.is_management ? '<span class="badge bg-warning text-dark ms-1">Management</span>' : '';
                const detailsArray = [];
                if (emp.department) detailsArray.push(`Dept: ${emp.department}`);
                if (emp.grade) detailsArray.push(`Grade: ${emp.grade}`);
                if (emp.company) detailsArray.push(emp.company);
                const detailsStr = detailsArray.length > 0 ? ` <small style="font-size:11px;opacity:0.85;">(${detailsArray.join(' - ')})</small>` : '';
                html += `<span class="badge bg-success me-1 mb-1 p-2">${emp.name} [${emp.code}]${detailsStr}${mgmtBadge} <i class="ri-close-line meal-remove-emp ms-1" data-code="${emp.code}" style="cursor:pointer;"></i></span>`;
            });
            $("#mealSelectedList").html(html);
            updateMealParticipantCounts();
        }

        // Dynamic Master Categories for Other Participants
        var masterParticipantCategories = {!! json_encode($masterParticipantCategories) !!};

        function fetchMasterParticipantCategories() {
            $.get("{{ url('other-participant-categories-list') }}", function(res) {
                if (res && res.status === 200 && res.data && res.data.length > 0) {
                    masterParticipantCategories = res.data.map(c => c.name);
                }
                renderMealCategoryTable();
                renderLodgingCategoryTable();
            });
        }
        fetchMasterParticipantCategories();

        function catToSlug(cat) {
            return String(cat || '').replace(/[^a-zA-Z0-9]/g, '_').toLowerCase();
        }

        function renderMealCategoryTable() {
            let html = '';
            masterParticipantCategories.forEach(cat => {
                const slug = catToSlug(cat);
                html += `
                <div class="border rounded p-2 bg-white meal-category-block" data-category="${cat}" data-slug="${slug}">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="fw-semibold text-dark" style="font-size: 12.5px;">
                            <i class="ri-user-follow-line text-success me-1"></i> ${cat}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">PAX:</span>
                            <input type="number" class="form-control form-control-sm text-center meal-cat-pax-input" 
                                   data-category="${cat}" data-slug="${slug}" min="0" value="0" placeholder="0" style="width: 70px;">
                            <button type="button" class="btn btn-sm btn-outline-success generate-cat-meal-rows-btn px-2 py-1" 
                                    data-category="${cat}" data-slug="${slug}" title="Generate rows for ${cat}">
                                <i class="ri-play-list-add-line me-1"></i> Generate
                            </button>
                        </div>
                    </div>
                    <!-- Category's own generated rows directly below -->
                    <div id="meal_rows_container_${slug}" class="meal-cat-rows-container mt-2 pt-2 border-top" style="display: none;">
                        <div class="row g-2 mb-1 text-muted small fw-semibold px-1" style="font-size: 11px;">
                            <div class="col-md-5">Participant Name <span class="text-danger">*</span></div>
                            <div class="col-md-4">Org / Mobile / Remarks (Optional)</div>
                            <div class="col-md-2">Category</div>
                            <div class="col-md-1 text-end">Action</div>
                        </div>
                        <div class="meal-cat-rows-list"></div>
                    </div>
                </div>
                `;
            });
            $("#otherMealParticipantsContainer").html(html);
            validateMealCategoryPax();
        }

        function getMealCategoryPaxTotal() {
            let total = 0;
            $(".meal-cat-pax-input").each(function() {
                total += parseInt($(this).val()) || 0;
            });
            return total;
        }

        function validateMealCategoryPax() {
            const totalPax = parseInt($("#mealPaxInput").val()) || 0;
            const empCount = mealSelectedEmployees.length;
            const catPax = getMealCategoryPaxTotal();
            const remainingForOther = Math.max(0, totalPax - empCount);

            $("#mealCategoryPaxTotal").text(catPax);
            $("#mealTotalCategoryPax").text(catPax);

            if (totalPax > 0) {
                $("#mealRemainingPaxHint").text(`(${catPax}/${remainingForOther} allocated)`);
            } else {
                $("#mealRemainingPaxHint").text('');
            }

            if (totalPax > 0 && (catPax + empCount) > totalPax) {
                const excess = (catPax + empCount) - totalPax;
                $("#otherMealLimitAlert").text(`Total Category PAX (${catPax}) + Employees (${empCount}) exceeds PAX No. (${totalPax}) by ${excess}. Please reduce category counts.`).show();
                $(".generate-cat-meal-rows-btn").prop("disabled", true).addClass("disabled");
            } else {
                $("#otherMealLimitAlert").hide().text('');
                $(".generate-cat-meal-rows-btn").prop("disabled", false).removeClass("disabled");
            }
        }

        $(document).on("input change", ".meal-cat-pax-input", function() {
            validateMealCategoryPax();
        });

        $(document).on("input change", "#mealPaxInput", function() {
            validateMealCategoryPax();
            updateMealParticipantCounts();
        });

        // Generate Rows for specific Meal Category
        $(document).on("click", ".generate-cat-meal-rows-btn", function() {
            const cat = $(this).data("category");
            const slug = $(this).data("slug") || catToSlug(cat);
            const totalPax = parseInt($("#mealPaxInput").val()) || 0;

            if (totalPax <= 0) {
                showMessage("Please enter PAX No. first.", "warning", "#uploadClaimMessage");
                $("#mealPaxInput").focus();
                return;
            }

            const catPax = parseInt($(`.meal-cat-pax-input[data-category="${cat}"]`).val()) || 0;
            if (catPax <= 0) {
                showMessage(`Please enter No. of PAX for "${cat}" before generating rows.`, "warning", "#uploadClaimMessage");
                $(`.meal-cat-pax-input[data-category="${cat}"]`).focus();
                return;
            }

            const allCatPax = getMealCategoryPaxTotal();
            const empCount = mealSelectedEmployees.length;
            if (allCatPax + empCount > totalPax) {
                showMessage(`Total Category PAX (${allCatPax}) + Employees (${empCount}) exceeds PAX No. (${totalPax}). Please adjust PAX counts.`, "danger", "#uploadClaimMessage");
                return;
            }

            // Preserve existing entered names & details for this category
            const existingList = [];
            $(`#meal_rows_container_${slug} .other-participant-row`).each(function() {
                const name = $(this).find(".other-meal-name").val().trim();
                const details = $(this).find(".other-meal-details").val().trim();
                existingList.push({ name, details });
            });

            $(`#meal_rows_container_${slug} .meal-cat-rows-list`).empty();

            for (let i = 0; i < catPax; i++) {
                const prev = existingList[i] || {};
                createOtherMealParticipantRow({
                    category: cat,
                    name: prev.name || '',
                    details: prev.details || '',
                    slug: slug
                });
            }

            validateMealCategoryPax();
            updateMealParticipantCounts();
            showMessage(`Generated ${catPax} participant row(s) for "${cat}". Please enter participant name(s).`, "success", "#uploadClaimMessage");
        });

        $(document).on("click", ".remove-other-meal-row", function() {
            const target = $(this).data("target");
            const cat = $(this).data("category") || $(target).find(".other-meal-category").val();
            const slug = $(this).data("slug") || catToSlug(cat);
            $(target).remove();

            // Decrement matching category input
            const remainingInCat = $(`#meal_rows_container_${slug} .other-participant-row`).length;
            $(`.meal-cat-pax-input[data-category="${cat}"]`).val(remainingInCat);
            if (remainingInCat === 0) {
                $(`#meal_rows_container_${slug}`).hide();
            }

            validateMealCategoryPax();
            updateMealParticipantCounts();
        });

        $(document).on("input change", ".other-meal-name, .other-meal-details", function() {
            updateMealParticipantCounts();
        });

        function createOtherMealParticipantRow(data = {}) {
            otherMealRowCounter++;
            const rowId = `other_meal_row_${otherMealRowCounter}`;
            const nameVal = data.name || '';
            const catVal = data.category || '';
            const detailVal = data.details || '';
            const slug = data.slug || catToSlug(catVal);

            const html = `
            <div class="row g-2 mb-2 align-items-center other-participant-row" id="${rowId}" data-category="${catVal}" data-slug="${slug}">
                <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm other-meal-name" placeholder="Participant Name *" aria-label="Participant Name *" value="${nameVal}">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm other-meal-details" placeholder="Org / Mobile / Remarks (Optional)" aria-label="Org / Mobile / Remarks (Optional)" value="${detailVal}">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm bg-light text-muted other-meal-category" value="${catVal}" readonly title="Category: ${catVal} (Non-editable)" style="cursor: not-allowed; font-weight: 500;">
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-other-meal-row" data-target="#${rowId}" data-category="${catVal}" data-slug="${slug}" title="Remove">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
            `;
            $(`#meal_rows_container_${slug} .meal-cat-rows-list`).append(html);
            $(`#meal_rows_container_${slug}`).show();
            updateMealParticipantCounts();
        }

        function getOtherMealParticipantsList() {
            const list = [];
            $("#otherMealParticipantsContainer .other-participant-row").each(function() {
                const name = $(this).find(".other-meal-name").val().trim();
                const category = $(this).find(".other-meal-category").val();
                const details = $(this).find(".other-meal-details").val().trim();
                if (name.length > 0) {
                    list.push({ name, category, details });
                }
            });
            return list;
        }

        function updateMealParticipantCounts() {
            const totalPax = parseInt($("#mealPaxInput").val()) || 0;
            const empCount = mealSelectedEmployees.length;
            const others = getOtherMealParticipantsList();
            const otherRowCount = $("#otherMealParticipantsContainer .other-participant-row").length;
            const totalSlots = empCount + otherRowCount;

            if (totalSlots > 0) {
                const othersLabel = otherRowCount > others.length ? `${otherRowCount} Others (${others.length} named)` : `${otherRowCount} Others`;
                $("#mealParticipantCount").show().text(`Participants: ${totalSlots} (${empCount} Employees, ${othersLabel})`);
            } else {
                $("#mealParticipantCount").hide().text('');
            }
            $("#mealParticipantsInput").val(JSON.stringify(mealSelectedEmployees.map(e => e.id || e.code)));
            $("#mealOtherParticipants").val(JSON.stringify(others));
        }

        // 5. Unique Participant Validation Function
        function validateUniqueMealParticipants() {
            const seenNames = new Set();

            // Check Employee Participants
            for (let emp of mealSelectedEmployees) {
                const nameKey = emp.name.toLowerCase().trim();
                if (seenNames.has(nameKey)) {
                    showMessage(`Duplicate participant "${emp.name}" found in employee list.`, "danger", "#uploadClaimMessage");
                    return false;
                }
                seenNames.add(nameKey);
            }

            // Check Other Participants
            const others = getOtherMealParticipantsList();
            for (let other of others) {
                const nameKey = other.name.toLowerCase().trim();
                if (seenNames.has(nameKey)) {
                    showMessage(`Participant "${other.name}" is entered multiple times across participant lists. Each participant must be unique.`, "danger", "#uploadClaimMessage");
                    return false;
                }
                seenNames.add(nameKey);
            }

            return true;
        }

        // ─── Lodging (Claim 1) Handlers ─────────────────────────────────────────
        var lodgingSelectedEmployees = [];
        var otherLodgingRowCounter = 0;

        $(document).on("change", "#lodgingGroupCheckbox", function() {
            if ($(this).is(":checked")) {
                $("#groupLodgingFields").show();
                $("#lodgingPaxInput").prop("required", true);
                $(".lodging-pax-asterisk").show();
                renderLodgingCategoryTable();
            } else {
                $("#groupLodgingFields").hide();
                $("#lodgingPaxInput").prop("required", false).removeClass("is-invalid").val("");
                $(".lodging-pax-asterisk").hide();
                lodgingSelectedEmployees = [];
                renderLodgingSelected();
                $("#otherLodgingParticipantsContainer").empty();
                updateLodgingParticipantCounts();
            }
        });

        $(document).on("click", "#lodgingSearchBtn", function() {
            searchEmployeesFor("lodging");
        });

        $(document).on("keypress", "#lodgingEmployeeSearch", function(e) {
            if (e.which === 13) { e.preventDefault(); searchEmployeesFor("lodging"); }
        });

        // Auto-search on input (debounced)
        let lodgingSearchTimer = null;
        $(document).on("input", "#lodgingEmployeeSearch", function() {
            clearTimeout(lodgingSearchTimer);
            const val = $(this).val().trim();
            if (!val) { $("#lodgingSearchResults").html(""); return; }
            lodgingSearchTimer = setTimeout(function() { searchEmployeesFor("lodging"); }, 400);
        });

        $(document).on("click", ".lodging-add-emp", function() {
            const code = String($(this).data("code") || "").trim();
            const newCode = String($(this).data("newcode") || "").trim();
            const empId = String($(this).data("id") || "").trim();
            const name = String($(this).data("name") || "").trim();
            const grade = String($(this).data("grade") || "").trim();
            const dept = String($(this).data("department") || "").trim();
            const company = String($(this).data("company") || "").trim();
            const isManagement = $(this).data("ismanagement") === true || $(this).data("ismanagement") === "true";

            // Exclude Claimant Validation (#1)
            if ((currentClaimantCode && (code === currentClaimantCode || newCode === currentClaimantCode)) ||
                (currentClaimantId && empId === currentClaimantId)) {
                showMessage("Claimant cannot be selected as a participant.", "danger", "#uploadClaimMessage");
                return;
            }

            const pax = parseInt($("#lodgingPaxInput").val()) || 0;
            const otherCount = $("#otherLodgingParticipantsContainer .other-lodging-participant-row").length;
            const currentTotal = lodgingSelectedEmployees.length + otherCount;
            if (pax > 0 && currentTotal >= pax) {
                showMessage(`Cannot add more participants than PAX limit (${pax}).`, "danger", "#uploadClaimMessage");
                return;
            }

            // No Duplicate Selection across Group Companies (#2 & #4)
            const isDuplicate = lodgingSelectedEmployees.some(e =>
                (e.code && (e.code === code || (newCode && e.code === newCode))) ||
                (e.new_code && (e.new_code === code || (newCode && e.new_code === newCode))) ||
                (e.id && empId && e.id === empId) ||
                (e.name.toLowerCase() === name.toLowerCase())
            );

            if (isDuplicate) {
                showMessage(`Employee "${name}" (${code}) is already added to the lodging participant list.`, "danger", "#uploadClaimMessage");
                return;
            }

            // Unique participant cross-validation (#5)
            const otherParticipants = getOtherLodgingParticipantsList();
            const duplicateInOther = otherParticipants.find(o => o.name.toLowerCase() === name.toLowerCase());
            if (duplicateInOther) {
                showMessage(`"${name}" is already added under Other Participants as ${duplicateInOther.category}.`, "danger", "#uploadClaimMessage");
                return;
            }

            lodgingSelectedEmployees.push({ code, new_code: newCode, id: empId, name, grade, department: dept, company, is_management: isManagement });
            renderLodgingSelected();
            $("#lodgingSearchResults").html("");
            validateLodgingCategoryPax();
        });

        $(document).on("click", ".lodging-remove-emp", function() {
            const code = String($(this).data("code") || "");
            lodgingSelectedEmployees = lodgingSelectedEmployees.filter(e => e.code !== code && e.new_code !== code);
            renderLodgingSelected();
            validateLodgingCategoryPax();
        });

        function renderLodgingSelected() {
            let html = '';
            lodgingSelectedEmployees.forEach(emp => {
                const mgmtBadge = emp.is_management ? '<span class="badge bg-warning text-dark ms-1">Management</span>' : '';
                const detailsArray = [];
                if (emp.department) detailsArray.push(`Dept: ${emp.department}`);
                if (emp.grade) detailsArray.push(`Grade: ${emp.grade}`);
                if (emp.company) detailsArray.push(emp.company);
                const detailsStr = detailsArray.length > 0 ? ` <small style="font-size:11px;opacity:0.85;">(${detailsArray.join(' - ')})</small>` : '';
                html += `<span class="badge bg-primary me-1 mb-1 p-2">${emp.name} [${emp.code}]${detailsStr}${mgmtBadge} <i class="ri-close-line lodging-remove-emp ms-1" data-code="${emp.code}" style="cursor:pointer;"></i></span>`;
            });
            $("#lodgingSelectedList").html(html);
            updateLodgingParticipantCounts();
        }

        function renderLodgingCategoryTable() {
            let html = '';
            masterParticipantCategories.forEach(cat => {
                const slug = catToSlug(cat);
                html += `
                <div class="border rounded p-2 bg-white lodging-category-block" data-category="${cat}" data-slug="${slug}">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="fw-semibold text-dark" style="font-size: 12.5px;">
                            <i class="ri-user-follow-line text-primary me-1"></i> ${cat}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">PAX:</span>
                            <input type="number" class="form-control form-control-sm text-center lodging-cat-pax-input" 
                                   data-category="${cat}" data-slug="${slug}" min="0" value="0" placeholder="0" style="width: 70px;">
                            <button type="button" class="btn btn-sm btn-outline-primary generate-cat-lodging-rows-btn px-2 py-1" 
                                    data-category="${cat}" data-slug="${slug}" title="Generate rows for ${cat}">
                                <i class="ri-play-list-add-line me-1"></i> Generate
                            </button>
                        </div>
                    </div>
                    <!-- Category's own generated rows directly below -->
                    <div id="lodging_rows_container_${slug}" class="lodging-cat-rows-container mt-2 pt-2 border-top" style="display: none;">
                        <div class="row g-2 mb-1 text-muted small fw-semibold px-1" style="font-size: 11px;">
                            <div class="col-md-5">Participant Name <span class="text-danger">*</span></div>
                            <div class="col-md-4">Org / Mobile / Remarks (Optional)</div>
                            <div class="col-md-2">Category</div>
                            <div class="col-md-1 text-end">Action</div>
                        </div>
                        <div class="lodging-cat-rows-list"></div>
                    </div>
                </div>
                `;
            });
            $("#otherLodgingParticipantsContainer").html(html);
            validateLodgingCategoryPax();
        }

        function getLodgingCategoryPaxTotal() {
            let total = 0;
            $(".lodging-cat-pax-input").each(function() {
                total += parseInt($(this).val()) || 0;
            });
            return total;
        }

        function validateLodgingCategoryPax() {
            const totalPax = parseInt($("#lodgingPaxInput").val()) || 0;
            const empCount = lodgingSelectedEmployees.length;
            const catPax = getLodgingCategoryPaxTotal();
            const remainingForOther = Math.max(0, totalPax - empCount);

            $("#lodgingCategoryPaxTotal").text(catPax);
            $("#lodgingTotalCategoryPax").text(catPax);

            if (totalPax > 0) {
                $("#lodgingRemainingPaxHint").text(`(${catPax}/${remainingForOther} allocated)`);
            } else {
                $("#lodgingRemainingPaxHint").text('');
            }

            if (totalPax > 0 && (catPax + empCount) > totalPax) {
                const excess = (catPax + empCount) - totalPax;
                $("#otherLodgingLimitAlert").text(`Total Category PAX (${catPax}) + Employees (${empCount}) exceeds PAX No. (${totalPax}) by ${excess}. Please reduce category counts.`).show();
                $(".generate-cat-lodging-rows-btn").prop("disabled", true).addClass("disabled");
            } else {
                $("#otherLodgingLimitAlert").hide().text('');
                $(".generate-cat-lodging-rows-btn").prop("disabled", false).removeClass("disabled");
            }
        }

        $(document).on("input change", ".lodging-cat-pax-input", function() {
            validateLodgingCategoryPax();
        });

        $(document).on("input change", "#lodgingPaxInput", function() {
            validateLodgingCategoryPax();
            updateLodgingParticipantCounts();
        });

        // Generate Rows for specific Lodging Category
        $(document).on("click", ".generate-cat-lodging-rows-btn", function() {
            const cat = $(this).data("category");
            const slug = $(this).data("slug") || catToSlug(cat);
            const totalPax = parseInt($("#lodgingPaxInput").val()) || 0;

            if (totalPax <= 0) {
                showMessage("Please enter PAX No. first.", "warning", "#uploadClaimMessage");
                $("#lodgingPaxInput").focus();
                return;
            }

            const catPax = parseInt($(`.lodging-cat-pax-input[data-category="${cat}"]`).val()) || 0;
            if (catPax <= 0) {
                showMessage(`Please enter No. of PAX for "${cat}" before generating rows.`, "warning", "#uploadClaimMessage");
                $(`.lodging-cat-pax-input[data-category="${cat}"]`).focus();
                return;
            }

            const allCatPax = getLodgingCategoryPaxTotal();
            const empCount = lodgingSelectedEmployees.length;
            if (allCatPax + empCount > totalPax) {
                showMessage(`Total Category PAX (${allCatPax}) + Employees (${empCount}) exceeds PAX No. (${totalPax}). Please adjust PAX counts.`, "danger", "#uploadClaimMessage");
                return;
            }

            // Preserve existing entered names & details for this category
            const existingList = [];
            $(`#lodging_rows_container_${slug} .other-lodging-participant-row`).each(function() {
                const name = $(this).find(".other-lodging-name").val().trim();
                const details = $(this).find(".other-lodging-details").val().trim();
                existingList.push({ name, details });
            });

            $(`#lodging_rows_container_${slug} .lodging-cat-rows-list`).empty();

            for (let i = 0; i < catPax; i++) {
                const prev = existingList[i] || {};
                createOtherLodgingParticipantRow({
                    category: cat,
                    name: prev.name || '',
                    details: prev.details || '',
                    slug: slug
                });
            }

            validateLodgingCategoryPax();
            updateLodgingParticipantCounts();
            showMessage(`Generated ${catPax} participant row(s) for "${cat}". Please enter participant name(s).`, "success", "#uploadClaimMessage");
        });

        $(document).on("click", ".remove-other-lodging-row", function() {
            const target = $(this).data("target");
            const cat = $(this).data("category") || $(target).find(".other-lodging-category").val();
            const slug = $(this).data("slug") || catToSlug(cat);
            $(target).remove();

            // Decrement matching category input
            const remainingInCat = $(`#lodging_rows_container_${slug} .other-lodging-participant-row`).length;
            $(`.lodging-cat-pax-input[data-category="${cat}"]`).val(remainingInCat);
            if (remainingInCat === 0) {
                $(`#lodging_rows_container_${slug}`).hide();
            }

            validateLodgingCategoryPax();
            updateLodgingParticipantCounts();
        });

        $(document).on("input change", ".other-lodging-name, .other-lodging-details", function() {
            updateLodgingParticipantCounts();
        });

        function createOtherLodgingParticipantRow(data = {}) {
            otherLodgingRowCounter++;
            const rowId = `other_lodging_row_${otherLodgingRowCounter}`;
            const nameVal = data.name || '';
            const catVal = data.category || '';
            const detailVal = data.details || '';
            const slug = data.slug || catToSlug(catVal);

            const html = `
            <div class="row g-2 mb-2 align-items-center other-lodging-participant-row" id="${rowId}" data-category="${catVal}" data-slug="${slug}">
                <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm other-lodging-name" placeholder="Participant Name *" aria-label="Participant Name *" value="${nameVal}">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm other-lodging-details" placeholder="Org / Mobile / Remarks (Optional)" aria-label="Org / Mobile / Remarks (Optional)" value="${detailVal}">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm bg-light text-muted other-lodging-category" value="${catVal}" readonly title="Category: ${catVal} (Non-editable)" style="cursor: not-allowed; font-weight: 500;">
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-other-lodging-row" data-target="#${rowId}" data-category="${catVal}" data-slug="${slug}" title="Remove">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
            `;
            $(`#lodging_rows_container_${slug} .lodging-cat-rows-list`).append(html);
            $(`#lodging_rows_container_${slug}`).show();
            updateLodgingParticipantCounts();
        }

        function getOtherLodgingParticipantsList() {
            const list = [];
            $("#otherLodgingParticipantsContainer .other-lodging-participant-row").each(function() {
                const name = $(this).find(".other-lodging-name").val().trim();
                const category = $(this).find(".other-lodging-category").val();
                const details = $(this).find(".other-lodging-details").val().trim();
                if (name.length > 0) {
                    list.push({ name, category, details });
                }
            });
            return list;
        }

        function updateLodgingParticipantCounts() {
            const totalPax = parseInt($("#lodgingPaxInput").val()) || 0;
            const empCount = lodgingSelectedEmployees.length;
            const others = getOtherLodgingParticipantsList();
            const otherRowCount = $("#otherLodgingParticipantsContainer .other-lodging-participant-row").length;
            const totalSlots = empCount + otherRowCount;

            if (totalSlots > 0) {
                const othersLabel = otherRowCount > others.length ? `${otherRowCount} Others (${others.length} named)` : `${otherRowCount} Others`;
                $("#lodgingParticipantCount").text(`Participants: ${totalSlots} (${empCount} Employees, ${othersLabel})`);
            } else {
                $("#lodgingParticipantCount").text('Participants: 0');
            }
            $("#lodgingParticipantsInput").val(JSON.stringify(lodgingSelectedEmployees.map(e => e.id || e.code)));
            $("#lodgingOtherParticipants").val(JSON.stringify(others));
        }

        function validateUniqueLodgingParticipants() {
            const seenNames = new Set();

            // Check Employee Participants
            for (let emp of lodgingSelectedEmployees) {
                const nameKey = emp.name.toLowerCase().trim();
                if (seenNames.has(nameKey)) {
                    showMessage(`Duplicate participant "${emp.name}" found in lodging employee list.`, "danger", "#uploadClaimMessage");
                    return false;
                }
                seenNames.add(nameKey);
            }

            // Check Other Participants
            const others = getOtherLodgingParticipantsList();
            for (let other of others) {
                const nameKey = other.name.toLowerCase().trim();
                if (seenNames.has(nameKey)) {
                    showMessage(`Participant "${other.name}" is entered multiple times across lodging participant lists. Each participant must be unique.`, "danger", "#uploadClaimMessage");
                    return false;
                }
                seenNames.add(nameKey);
            }

            return true;
        }

        // ─── Shared Employee Search ─────────────────────────────────────────────
        function searchEmployeesFor(type) {
            const searchInput = type === "meal" ? "#mealEmployeeSearch" : "#lodgingEmployeeSearch";
            const resultsContainer = type === "meal" ? "#mealSearchResults" : "#lodgingSearchResults";
            const searchText = $(searchInput).val().trim();
            if (!searchText) return;

            $(resultsContainer).html('<div class="text-center py-2"><span class="spinner-border spinner-border-sm"></span> Searching...</div>');

            const ajaxData = { search: searchText, status: 'A', _token: '{{ csrf_token() }}' };
            if (type === "meal" || type === "lodging") {
                ajaxData.exclude_claimant = 1;
                ajaxData.claimant_id = currentClaimantId;
                ajaxData.claimant_code = currentClaimantCode;
                ajaxData.type = type;
            }
            if (type === "lodging") {
                ajaxData.exclude_management = 1;
            }

            $.ajax({
                url: "{{ url('employees/search-all') }}",
                method: "POST",
                data: ajaxData,
                success: function(response) {
                    let html = '';
                    const employees = response.employees || response.data || [];
                    if (employees.length === 0) {
                        html = '<div class="list-group-item text-muted">No employees found</div>';
                    } else {
                        employees.forEach(emp => {
                            const code = emp.EmpCode_New || emp.EmpCode || emp.EmployeeID;
                            const newCode = emp.EmpCode_New || '';
                            const empId = emp.EmployeeID || '';
                            const name = emp.EmployeeName || `${emp.Fname || ''} ${emp.Lname || ''}`.trim();
                            const grade = emp.grade_name || '';
                            const dept = emp.department_name || '';
                            const company = emp.company_name || '';
                            const isMgmt = emp.is_management || false;
                            const addClass = type === "meal" ? "meal-add-emp" : "lodging-add-emp";

                            // Exclude claimant if present
                            if ((type === "meal" || type === "lodging") && ((currentClaimantCode && (code === currentClaimantCode || newCode === currentClaimantCode)) || (currentClaimantId && empId == currentClaimantId))) {
                                return;
                            }

                            const isAdded = type === "meal" 
                                ? mealSelectedEmployees.some(e => e.code === code || e.new_code === code || e.id == empId) 
                                : lodgingSelectedEmployees.some(e => e.code === code || e.new_code === code || e.id == empId);
                                
                            const mgmtTag = isMgmt ? '<span class="badge bg-warning text-dark ms-1">Management</span>' : '';
                            const gradeTag = grade ? `<span class="badge bg-info-subtle text-info ms-1">Grade: ${grade}</span>` : '';
                            const deptTag = dept ? `<span class="badge bg-primary-subtle text-primary ms-1">Dept: ${dept}</span>` : '';
                            const metaInfo = [dept ? `Dept: ${dept}` : '', grade ? `Grade: ${grade}` : '', company].filter(Boolean).join(' • ');
                            const metaSubtitle = metaInfo ? `<br><small class="text-muted"><i class="ri-building-line align-middle me-1"></i>${metaInfo}</small>` : '';

                            if (isAdded) {
                                html += `<div class="list-group-item d-flex justify-content-between align-items-center bg-light py-2">
                                    <div>
                                        <span><strong>${name}</strong> <small class="text-muted">(${code})</small> ${deptTag} ${gradeTag} ${mgmtTag}</span>
                                        ${metaSubtitle}
                                    </div>
                                    <span class="badge bg-secondary">Added</span>
                                </div>`;
                            } else {
                                html += `<a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 ${addClass}" data-code="${code}" data-newcode="${newCode}" data-id="${empId}" data-name="${name}" data-grade="${grade}" data-department="${dept}" data-company="${company}" data-ismanagement="${isMgmt}">
                                    <div>
                                        <span><strong>${name}</strong> <small class="text-muted">(${code})</small> ${deptTag} ${gradeTag} ${mgmtTag}</span>
                                        ${metaSubtitle}
                                    </div>
                                    <i class="ri-add-circle-line text-success fs-18"></i>
                                </a>`;
                            }
                        });
                    }
                    $(resultsContainer).html(html);
                },
                error: function() {
                    $(resultsContainer).html('<div class="list-group-item text-danger">Search failed</div>');
                }
            });
        }

        // Form submit validation for Lodging
        $(document).on("click", "#submitUploadClaimBtn", function() {
            // Updated lodging validation block handled in submit logic below
        });

        $(document).on("change", "#uploadNewFile", function() {
            const claimType = $("#uploadClaimType").val();
            if (!claimType) {
                showMessage("Please select a Claim Type before uploading files.", "danger", "#uploadClaimMessage");
                $(this).val('');
                return;
            }
            if (claimType === "16" || claimType === "42") {
                return;
            }
            const files = this.files;
            if (files.length === 0) return;
            let imgCount = 0,
                pdfCount = 0;
            for (let file of files) {
                let ext = file.name.split('.').pop().toLowerCase();
                if (["jpg", "jpeg", "png"].includes(ext)) {
                    imgCount++;
                } else if (ext === "pdf") {
                    pdfCount++;
                } else {
                    showMessage("Only JPG, PNG or PDF allowed.", "danger", "#uploadClaimMessage");
                    $(this).val('');
                    return;
                }
            }
            if (imgCount > 3) {
                showMessage("Maximum 3 images allowed.", "danger", "#uploadClaimMessage");
                $(this).val('');
                return;
            }
            if (pdfCount > 2) {
                showMessage("Maximum 2 PDF files allowed.", "danger", "#uploadClaimMessage");
                $(this).val('');
                return;
            }
            if (files.length > 5) {
                showMessage("Maximum 5 files allowed.", "danger", "#uploadClaimMessage");
                $(this).val('');
                return;
            }
            const firstFile = files[0];
            let formData = new FormData();
            formData.append("value", "extract_data");
            formData.append("YearId", "{{ $yearId }}");
            formData.append("tokenid", @json(\App\Support\LegacyApi::extractToken()));
            formData.append("ComId", "{{ $companyId }}");
            formData.append("empid", "{{ $employeeId }}");
            formData.append("file", firstFile);
            formData.append("claim_id", claimType);
            formData.append("form_type", "web");

            $.ajax({
                url: @json(\App\Support\LegacyApi::url('extract-data.php')),
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                beforeSend: function() {
                    $("#submitUploadClaimBtn").prop("disabled", true).text("Extracting...");
                },
                success: function(response) {
                    if (response.success === "true" && response.data) {
                        let rawAmount = response.data["Total Amount"] || "";
                        let cleanAmount = rawAmount.replace(/[₹,\/\-\s]/g, "").trim().split('.')[0];
                        // DATE-01: read the extracted date as day-month-year (Indian bills), never
                        // as the browser's month/day guess, and never overwrite a date the user entered.
                        let rawDate = response.data["Bill date"] || "";
                        let formattedDate = xeasyParseBillDate(rawDate);
                        let userDate = ($("#uploadBillDate").val() || "").trim();
                        if (formattedDate && !userDate) {
                            const fpu = document.querySelector("#uploadBillDate")._flatpickr;
                            if (fpu) { fpu.setDate(formattedDate, false, "d/m/Y"); } else { $("#uploadBillDate").val(formattedDate); }
                        } else if (formattedDate && userDate && userDate !== formattedDate) {
                            showMessage("Bill shows date " + formattedDate + "; kept the date you entered (" + userDate + ").", "warning", "#uploadClaimMessage");
                        }
                        if (cleanAmount && !($("#uploadAmount").val() || "").trim()) {
                            $("#uploadAmount").val(cleanAmount);
                        }
                        if (response.data["Remark"] && !($("#uploadRemark").val() || "").trim()) {
                            $("#uploadRemark").val(response.data["Remark"]);
                        }
                        $("#upload_temp_punch_id").val(response.data["temp_punch_id"] || "");
                        showMessage("Data extracted successfully.", "success", "#uploadClaimMessage");
                    } else {
                        showMessage("Extraction failed. Try another file.", "danger", "#uploadClaimMessage");
                    }
                    $("#submitUploadClaimBtn").prop("disabled", false).text("Submit");
                },
                error: function() {
                    showMessage("Error calling extract API.", "danger", "#uploadClaimMessage");
                    $("#submitUploadClaimBtn").prop("disabled", false).text("Submit");
                }
            });
        });

        $(document).on("click", "#submitUploadClaimBtn", function() {

            const claimType = $("#uploadClaimType").val();
            const billDate = $("#uploadBillDate").val();
            const amount = $("#uploadAmount").val()?.trim();
            const files = $("#uploadNewFile")[0].files;

            if (!claimType) {
                showMessage("Please select a claim type.", "danger", "#uploadClaimMessage");
                return;
            }

            if (!billDate) {
                showMessage("Bill date is required.", "danger", "#uploadClaimMessage");
                return;
            }

            const dateParts = billDate.split(/[-\/.]/);

            if (dateParts.length !== 3) {
                showMessage("Invalid date format. Use DD/MM/YYYY.", "danger", "#uploadClaimMessage");
                return;
            }

            const day = parseInt(dateParts[0]);
            const month = parseInt(dateParts[1]) - 1;
            const year = parseInt(dateParts[2]);

            const selectedDate = new Date(year, month, day);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (isNaN(selectedDate.getTime())) {
                showMessage("Invalid bill date.", "danger", "#uploadClaimMessage");
                return;
            }

            // BILLDATE-03: a bill cannot be dated in the future (also refused on the server)
            if (selectedDate > today) {
                showMessage("Bill date " + billDate + " is in the future. Enter the date printed on the bill (DD/MM/YYYY).", "danger", "#uploadClaimMessage");
                return;
            }

            if (claimType === "16") {
                let total = 0;
                $("#table1 .amount-field").each(function() {
                    total += parseFloat($(this).val()) || 0;
                });

                if (total <= 0) {
                    showMessage("Please enter at least one valid amount.", "danger", "#uploadClaimMessage");
                    return;
                }

                $("#uploadAmount").val(total);
            }

            //------------------------------------------
            // CLAIM TYPE 42 VALIDATION
            //------------------------------------------
            if (claimType === "42") {

                const pax = $("#paxDetailsInput").val()?.trim();
                if (!pax) {
                    showMessage("Pax Details is required.", "danger", "#uploadClaimMessage");
                    return;
                }

                let total = 0;
                $("#table2 .amount-field").each(function() {
                    total += parseFloat($(this).val()) || 0;
                });

                if (total <= 0) {
                    showMessage("Please enter at least one amount.", "danger", "#uploadClaimMessage");
                    return;
                }

                $("#uploadAmount").val(total);
            }

            //------------------------------------------
            // REGULAR CLAIM VALIDATION
            //------------------------------------------
            if (claimType !== "16" && claimType !== "42") {

                const parsedAmount = parseFloat(amount);

                if (claimType === "17") {
                    // Group Meal validation
                    if (isNaN(parsedAmount) || parsedAmount <= 0) {
                        showMessage("Valid amount is required for meal claims.", "danger", "#uploadClaimMessage");
                        return;
                    }
                    if ($("#groupMealCheckbox").is(":checked")) {
                        const mealPax = $("#mealPaxInput").val()?.trim();
                        if (mealPax === "" || mealPax === null) {
                            showMessage("PAX number is required when Group Meal is enabled.", "danger", "#uploadClaimMessage");
                            return;
                        }
                        const paxNum = parseInt(mealPax, 10);
                        if (isNaN(paxNum) || paxNum <= 0) {
                            showMessage("PAX number must be at least 1.", "danger", "#uploadClaimMessage");
                            return;
                        }

                        const empCount = mealSelectedEmployees.length;
                        const catPax = getMealCategoryPaxTotal();
                        const otherRowCount = $("#otherMealParticipantsContainer .other-participant-row").length;

                        // Check per-category row generation
                        let ungeneratedCategory = null;
                        masterParticipantCategories.forEach(cat => {
                            const expectedPax = parseInt($(`.meal-cat-pax-input[data-category="${cat}"]`).val()) || 0;
                            const slug = catToSlug(cat);
                            const actualRows = $(`#meal_rows_container_${slug} .other-participant-row`).length;
                            if (expectedPax !== actualRows && !ungeneratedCategory) {
                                ungeneratedCategory = { cat, expectedPax, actualRows };
                            }
                        });

                        if (ungeneratedCategory) {
                            showMessage(`Category "${ungeneratedCategory.cat}" has PAX ${ungeneratedCategory.expectedPax} but ${ungeneratedCategory.actualRows} row(s) generated. Please click 'Generate' for "${ungeneratedCategory.cat}" or adjust its PAX count.`, "danger", "#uploadClaimMessage");
                            return;
                        }

                        const totalCount = empCount + otherRowCount;
                        if (totalCount < paxNum) {
                            showMessage(`Total participants (${totalCount} = ${empCount} Employees + ${otherRowCount} Others) is less than PAX (${paxNum}). Please add ${paxNum - totalCount} more participant(s).`, "danger", "#uploadClaimMessage");
                            return;
                        }
                        if (totalCount > paxNum) {
                            showMessage(`Total participants (${totalCount} = ${empCount} Employees + ${otherRowCount} Others) exceeds PAX (${paxNum}). Please remove ${totalCount - paxNum} participant(s).`, "danger", "#uploadClaimMessage");
                            return;
                        }

                        // Validate uniqueness across participant selection options (#5)
                        if (!validateUniqueMealParticipants()) {
                            return;
                        }

                        // Check role/category & name for all Other Participants (#6)
                        let hasIncompleteOtherMeal = false;
                        $("#otherMealParticipantsContainer .other-participant-row").each(function() {
                            const n = $(this).find(".other-meal-name").val().trim();
                            const c = $(this).find(".other-meal-category").val();
                            if (!n) {
                                showMessage("Please enter a Participant Name for all added Other Participants, or remove unused rows.", "danger", "#uploadClaimMessage");
                                $(this).find(".other-meal-name").focus();
                                hasIncompleteOtherMeal = true;
                                return false;
                            }
                            if (!c) {
                                showMessage(`Category missing for participant "${n}".`, "danger", "#uploadClaimMessage");
                                hasIncompleteOtherMeal = true;
                                return false;
                            }
                        });
                        if (hasIncompleteOtherMeal) return;
                    }
                } else if (claimType === "1") {
                    // Lodging validation
                    if (isNaN(parsedAmount) || parsedAmount <= 0) {
                        showMessage("Valid amount is required for lodging claims.", "danger", "#uploadClaimMessage");
                        return;
                    }
                    // Tracker #16: online payment receipt above the threshold
                    if (parsedAmount > LODGING_RECEIPT_THRESHOLD && !($("#lodgingReceiptFile")[0]?.files?.length > 0)) {
                        showMessage(`Lodging bill above Rs ${LODGING_RECEIPT_THRESHOLD.toLocaleString('en-IN')}: please upload the online payment receipt before submitting.`, "danger", "#uploadClaimMessage");
                        return;
                    }
                    if ($("#lodgingGroupCheckbox").is(":checked")) {
                        const lodgingPax = $("#lodgingPaxInput").val()?.trim();
                        if (lodgingPax === "" || lodgingPax === null) {
                            showMessage("PAX number is required when Group Lodging is enabled.", "danger", "#uploadClaimMessage");
                            return;
                        }
                        const lodgingPaxNum = parseInt(lodgingPax, 10);
                        if (isNaN(lodgingPaxNum) || lodgingPaxNum <= 0) {
                            showMessage("PAX number must be at least 1.", "danger", "#uploadClaimMessage");
                            return;
                        }
                        if (lodgingPaxNum > 100) {
                            showMessage("PAX number cannot exceed 100.", "danger", "#uploadClaimMessage");
                            return;
                        }

                        const empCount = lodgingSelectedEmployees.length;
                        const catPax = getLodgingCategoryPaxTotal();
                        const otherRowCount = $("#otherLodgingParticipantsContainer .other-lodging-participant-row").length;

                        // Check per-category row generation
                        let ungeneratedLodgingCategory = null;
                        masterParticipantCategories.forEach(cat => {
                            const expectedPax = parseInt($(`.lodging-cat-pax-input[data-category="${cat}"]`).val()) || 0;
                            const slug = catToSlug(cat);
                            const actualRows = $(`#lodging_rows_container_${slug} .other-lodging-participant-row`).length;
                            if (expectedPax !== actualRows && !ungeneratedLodgingCategory) {
                                ungeneratedLodgingCategory = { cat, expectedPax, actualRows };
                            }
                        });

                        if (ungeneratedLodgingCategory) {
                            showMessage(`Category "${ungeneratedLodgingCategory.cat}" has PAX ${ungeneratedLodgingCategory.expectedPax} but ${ungeneratedLodgingCategory.actualRows} row(s) generated. Please click 'Generate' for "${ungeneratedLodgingCategory.cat}" or adjust its PAX count.`, "danger", "#uploadClaimMessage");
                            return;
                        }

                        const totalCount = empCount + otherRowCount;
                        if (totalCount < lodgingPaxNum) {
                            showMessage(`Total participants (${totalCount} = ${empCount} Employees + ${otherRowCount} Others) is less than PAX (${lodgingPaxNum}). Please add ${lodgingPaxNum - totalCount} more participant(s).`, "danger", "#uploadClaimMessage");
                            return;
                        }
                        if (totalCount > lodgingPaxNum) {
                            showMessage(`Total participants (${totalCount} = ${empCount} Employees + ${otherRowCount} Others) exceeds PAX (${lodgingPaxNum}). Please remove ${totalCount - lodgingPaxNum} participant(s).`, "danger", "#uploadClaimMessage");
                            return;
                        }

                        // Validate uniqueness across lodging participant selection options (#5)
                        if (!validateUniqueLodgingParticipants()) {
                            return;
                        }

                        // Check role/category & name for all Other Participants (#6)
                        let hasIncompleteOtherLodging = false;
                        $("#otherLodgingParticipantsContainer .other-lodging-participant-row").each(function() {
                            const n = $(this).find(".other-lodging-name").val().trim();
                            const c = $(this).find(".other-lodging-category").val();
                            if (!n) {
                                showMessage("Please enter a Participant Name for all added Other Participants, or remove unused rows.", "danger", "#uploadClaimMessage");
                                $(this).find(".other-lodging-name").focus();
                                hasIncompleteOtherLodging = true;
                                return false;
                            }
                            if (!c) {
                                showMessage(`Category missing for participant "${n}".`, "danger", "#uploadClaimMessage");
                                hasIncompleteOtherLodging = true;
                                return false;
                            }
                        });
                        if (hasIncompleteOtherLodging) return;
                    }
                } else if (claimType === "14") {
                    // Rail Fare validation
                    const ticketCategory = $("#ticketCategorySelect").val();
                    if (!ticketCategory) {
                        showMessage("Please select a ticket category for Rail Fare.", "danger", "#uploadClaimMessage");
                        return;
                    }
                    if (isNaN(parsedAmount) || parsedAmount <= 0) {
                        showMessage("Amount is required.", "danger", "#uploadClaimMessage");
                        return;
                    }
                }

                if (files.length === 0) {
                    showMessage("Please upload at least one file.", "danger", "#uploadClaimMessage");
                    return;
                }

                if (isNaN(parsedAmount) || parsedAmount <= 0) {
                    showMessage("Please enter a valid positive amount.", "danger", "#uploadClaimMessage");
                    return;
                }
            }

            if (claimType === "17") {
                updateMealParticipantCounts();
            } else if (claimType === "1") {
                updateLodgingParticipantCounts();
            }

            //------------------------------------------
            // CREATE FORM DATA
            //------------------------------------------
            const formData = new FormData();

            // CSRF
            formData.append("_token", $('input[name="_token"]').val());

            //------------------------------------------
            // ADD ALL NORMAL TEXT / NUMBER / HIDDEN / SELECT FIELDS
            //------------------------------------------
            $("#uploadClaimForm")
                .find("input[type='text'], input[type='number'], input[type='date'], input[type='hidden'], select, textarea")
                .each(function() {
                    let name = $(this).attr("name");
                    if (name && $(this).attr("type") !== "file") {
                        formData.append(name, $(this).val() || "");
                    }
                });

            // Include checked checkboxes
            $("#uploadClaimForm input[type='checkbox']:checked, #uploadClaimForm input[type='radio']:checked").each(function() {
                let name = $(this).attr("name");
                if (name) {
                    formData.append(name, $(this).val() || "true");
                }
            });

            //------------------------------------------
            // HANDLE REGULAR CLAIM FILES
            //------------------------------------------
            if (claimType !== "16" && claimType !== "42") {

                for (let i = 0; i < files.length; i++) {
                    formData.append("NewFile[]", files[i]);
                }
                if (claimType === "1") {
                    const receiptFiles = $("#lodgingReceiptFile")[0]?.files || [];
                    for (let i = 0; i < receiptFiles.length; i++) {
                        formData.append("File_Payment_Receipt[]", receiptFiles[i]);
                    }
                    formData.append("receipt_upload_supported", "1");
                }
            }

            //------------------------------------------
            // HANDLE DYNAMIC FILES (CLAIM 16 + 42)
            //------------------------------------------
            if (claimType === "16" || claimType === "42") {

                // ✓ Add all files under dynamicFields
                $("#dynamicFields input[type='file']").each(function() {

                    const fieldName = $(this).attr("name");
                    const file = this.files[0];

                    if (file && fieldName) {
                        formData.append(fieldName, file);
                    }
                });
            }

            //------------------------------------------
            // DEBUG OUTPUT
            //------------------------------------------
            console.log("Final FormData Entries:");
            for (let pair of formData.entries()) {
                console.log(pair[0], pair[1]);
            }

            //------------------------------------------
            // AJAX SUBMIT
            //------------------------------------------
            $.ajax({
                url: "{{ route('claim.upload.submit') }}",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                beforeSend: function() {
                    $("#submitUploadClaimBtn")
                        .prop("disabled", true)
                        .html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');
                },
                success: function(response) {
                    if (response.status === "success") {
                        showMessage(response.msg, "success", "#uploadClaimMessage");

                        setTimeout(() => {
                            $("#uploadClaimModal").modal("hide");
                            $("#uploadClaimForm")[0].reset();
                            $("#dynamicFields").html("");

                            if (typeof table !== "undefined") {
                                table.ajax.reload();
                            }
                        }, 1500);
                    } else {
                        showMessage(response.msg, "danger", "#uploadClaimMessage");
                    }
                },
                error: function(xhr) {
                    console.error("Server Error", xhr.responseText);
                    showMessage("Server error occurred while submitting claim.", "danger", "#uploadClaimMessage");
                },
                complete: function() {
                    $("#submitUploadClaimBtn").prop("disabled", false).text("Submit");
                }
            });

        });

        $("#openActivityClaimBtn").on("click", function() {
            fetchActivityTypes();
            fetchActivityNames();
            initClaimDatePicker("#activityBillDateInput");
            $("#activityClaimModal").modal('show');
        });

        $("#activityClaimModal").on("show.bs.modal", function() {
            $("#activityClaimForm")[0].reset();
            $("#activityDynamicFields").html("");
            $("#activityClaimMessage").hide();
            $("#activityParticipantSection").hide();  // REQ-003: reset participant section
            $("#submitActivityClaimBtn").prop("disabled", false).html(
                'Submit <span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true" style="display: none;"></span>'
            );
        });

        // REQ-003 / Tracker #21: participant list is required by ACTIVITY CLAIM (head), not by
        // activity type. The ids come from the server (claims with a "Participant list" head).
        const PARTICIPANT_REQUIRED_CLAIM_IDS = @json(array_map('strval', $participantActivityClaimIds ?? []));

        // PART-48: heads where the participant section is shown but optional (e.g. 48 Distributor Meeting)
        const PARTICIPANT_OPTIONAL_CLAIM_IDS = @json(array_map('strval', $participantOptionalClaimIds ?? []));

        $(document).on("change", "#activityClaimSelect", function() {
            const claimId = String($(this).val() || '');
            const optional = PARTICIPANT_OPTIONAL_CLAIM_IDS.includes(claimId);
            if (PARTICIPANT_REQUIRED_CLAIM_IDS.includes(claimId) || optional) {
                $('#activityParticipantSection').attr('data-optional', optional ? '1' : '0');
                $('#activityParticipantSection .pl-req').toggle(!optional);
                $('#activityParticipantSection .pl-opt').toggle(optional);
                $('#activityParticipantTypeBadge').text($(this).find("option:selected").text());
                $('#activityParticipantSection').slideDown(200);
            } else {
                $('#activityParticipantSection').slideUp(200);
                $('#activityParticipantDept, #activityParticipantNames, #activityParticipantFile').val('');
                $('#activityParticipantCount').val('');
            }
        });

        function fetchActivityTypes() {
            $.ajax({
                url: @json(\App\Support\LegacyApi::url('extract-data.php')),
                type: "GET",
                data: {
                    value: "activity_type",
                    YearId: "{{ $yearId }}",
                    tokenid: @json(\App\Support\LegacyApi::extractToken()),
                    ComId: "{{ $companyId }}",
                    empid: "{{ $employeeId }}"
                },
                dataType: "json",
                success: function(response) {
                    if (response.success === "true") {
                        let options = '<option value="">-----Select-----</option>';
                        $.each(response.data, function(index, item) {
                            options += `<option value="${item.id}">${item.type_name}</option>`;
                        });
                        $("#activityTypeSelect").html(options);
                    } else {
                        showMessage("Failed to fetch activity types.", "danger", "#activityClaimMessage");
                    }
                },
                error: function() {
                    showMessage("Error fetching activity types.", "danger", "#activityClaimMessage");
                }
            });
        }

        function fetchActivityNames() {
            $.ajax({
                url: @json(\App\Support\LegacyApi::url('extract-data.php')),
                type: "GET",
                data: {
                    value: "activity_claim_list",
                    YearId: "{{ $yearId }}",
                    tokenid: @json(\App\Support\LegacyApi::extractToken()),
                    ComId: "{{ $companyId }}",
                    empid: "{{ $employeeId }}"
                },
                dataType: "json",
                success: function(response) {
                    if (response.success === "true") {
                        let options = '<option value="">-----Select-----</option>';
                        $.each(response.data, function(index, item) {
                            options += `<option value="${item.ClaimId}">${item.ClaimName}</option>`;
                        });
                        $("#activityClaimSelect").html(options);
                    } else {
                        showMessage("Failed to fetch activity claims.", "danger", "#activityClaimMessage");
                    }
                },
                error: function() {
                    showMessage("Error fetching activity claims.", "danger", "#activityClaimMessage");
                }
            });
        }

        $(document).on("change", "#activityClaimSelect", function() {
            const claimId = $(this).val();

            if (!claimId) {
                $("#activityDynamicFields").empty();
                return;
            }

            $.ajax({
                url: @json(\App\Support\LegacyApi::url('extract-data.php')),
                type: "GET",
                data: {
                    value: "get_additional_fields",
                    YearId: "{{ $yearId }}",
                    tokenid: @json(\App\Support\LegacyApi::extractToken()),
                    ComId: "{{ $companyId }}",
                    empid: "{{ $employeeId }}",
                    ClaimId: claimId
                },
                dataType: "json",

                success: function(response) {
                    if (response.code === 200 && response.data.expenses.length > 0) {

                        let html = "";

                        /*--------------------------------------
                         | CROP & VARIETY SELECT SECTION
                         --------------------------------------*/
                        let cropOptions = "";
                        $.each(response.data.crops, function(i, crop) {
                            cropOptions += `<option value="${crop.id}">${crop.crop_name}</option>`;
                        });

                        html += `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Select Crops <span class="text-danger">*</span></label>
                        <select name="crop_list[]" id="activityCropSelect" class="form-select" multiple required>
                            ${cropOptions}
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Select Varieties</label>
                        <select name="variety_list[]" id="activityVarietySelect" class="form-select" multiple>
                            <option value="">Select crops first</option>
                        </select>
                    </div>
                </div>
                `;

                        /*--------------------------------------
                         | DYNAMIC TABLE
                         --------------------------------------*/
                        html += `
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th width="40%">Field</th>
                                <th width="30%">File</th>
                                <th width="30%">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                        $.each(response.data.expenses, function(i, item) {

                            const req = item.file_required == 1 ? "required" : "";
                            const mark = req ? `<span class="text-danger">*</span>` : "";

                            let fileInput = "";
                            if (item.has_file == 1) {
                                fileInput = `
                            <input type="file" 
                                   name="${item.file_input}[]" 
                                   class="form-control form-control-sm activity-file"
                                   multiple ${req}>
                        `;
                            }

                            let inputField = "";
                            let type = item.field_type;

                            if (["text", "number", "date"].includes(type)) {
                                inputField = `
                                    <input type="${type}" 
                                        name="${item.value}" 
                                        class="form-control form-control-sm activity-amount" 
                                        ${req}>
                                `;
                            } else {
                                inputField = `
                                    <input type="text" 
                                        name="${item.value}" 
                                        class="form-control form-control-sm activity-amount" 
                                        ${req}>
                                `;
                            }

                            html += `
                                <tr class="activity-row">
                                    <td><b>${item.label}${mark}</b></td>
                                    <td>${fileInput}</td>
                                    <td>${inputField}</td>
                                </tr>
                                `;
                        });

                        html += `
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="2" class="text-end">Total Amount:</th>
                                            <th><input type="text" id="activityTotalAmount" name="total_amount" class="form-control form-control-sm" readonly></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            `;

                        $("#activityDynamicFields").html(html);

                        /*--------------------------------------
                         | Select2 Initialization
                         --------------------------------------*/
                        $("#activityCropSelect, #activityVarietySelect").select2({
                            dropdownParent: $("#activityClaimModal")
                        });

                        /*--------------------------------------
                         | On Crop Change → Load Varieties
                         --------------------------------------*/
                        $("#activityCropSelect").on("change", function() {
                            const selected = $(this).val();
                            if (selected?.length) {
                                getActivityVarieties(selected);
                            } else {
                                $("#activityVarietySelect").html(`<option value="">Select crops first</option>`);
                            }
                        });

                        /*--------------------------------------
                         | Amount Auto-Total Calculation
                         --------------------------------------*/
                        $(document).on("input", ".activity-amount", function() {
                            let total = 0;
                            $(".activity-amount").each(function() {
                                total += parseFloat($(this).val()) || 0;
                            });
                            $("#activityTotalAmount").val(total.toFixed(0));
                        });

                    } else {
                        // Fallback Single Row
                        $("#activityDynamicFields").html(`
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Document <span class="text-danger">*</span></label>
                            <input type="file" name="activity_document[]" class="form-control" required multiple>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Total Amount</label>
                            <input type="text" id="activityTotalAmount" name="total_amount" class="form-control">
                        </div>
                    </div>
                `);
                    }
                },

                error: function() {
                    showMessage("Error fetching additional fields.", "danger", "#activityClaimMessage");
                }
            });
        });


        function getActivityVarieties(cropIds) {
            $.ajax({
                url: @json(\App\Support\LegacyApi::url('extract-data.php')),
                type: "GET",
                data: {
                    value: "get_varieties",
                    YearId: "{{ $yearId }}",
                    tokenid: @json(\App\Support\LegacyApi::extractToken()),
                    ComId: "{{ $companyId }}",
                    empid: "{{ $employeeId }}",
                    crop_ids: cropIds.join(",")
                },
                dataType: "json",
                success: function(response) {
                    if (response.code === 200 && response.data.varieties.length > 0) {
                        let varietyOptions = "";
                        $.each(response.data.varieties, function(index, variety) {
                            varietyOptions += `<option value="${variety.id}">${variety.variety_name}</option>`;
                        });
                        $("#activityVarietySelect").html(varietyOptions);
                    } else {
                        $("#activityVarietySelect").html('<option value="">No varieties found</option>');
                    }
                },
                error: function() {
                    showMessage("Error fetching varieties.", "danger", "#activityClaimMessage");
                }
            });
        }

        $(document).on("click", "#submitActivityClaimBtn", function() {

            const activityClaim = $("#activityClaimSelect").val();
            const activityType = $("#activityTypeSelect").val();
            const billDate = $("#activityBillDateInput").val();
            const crop = $("#activityCropSelect").val(); // returns array for multiple
            const variety = $("#activityVarietySelect").val(); // returns array for multiple
            const trialField = $("#trialNoInput");
            const trialNo = trialField.length ? trialField.val().trim() : null;

            /*--------------------------------------
             | BASIC VALIDATIONS
             --------------------------------------*/
            if (!activityClaim) {
                showMessage("Please select an Activity.", "danger", "#activityClaimMessage");
                return;
            }

            if (!activityType) {
                showMessage("Please select an Activity Type.", "danger", "#activityClaimMessage");
                return;
            }

            if (!billDate) {
                showMessage("Please select a Bill Date.", "danger", "#activityClaimMessage");
                return;
            }

            /*--------------------------------------
             | MULTIPLE SELECT VALIDATION
             --------------------------------------*/
            // crop_list[] must have at least 1 selected
            if (!crop || crop.length === 0) {
                showMessage("Please select at least one Crop.", "danger", "#activityClaimMessage");
                return;
            }

            // If variety field exists → must select at least 1
            const varietyElement = $("#activityVarietySelect");
            if (varietyElement.length && (!variety || variety.length === 0)) {
                showMessage("Please select at least one Variety.", "danger", "#activityClaimMessage");
                return;
            }

            if (trialField.length && (!trialNo || trialNo === "")) {
                showMessage("Trial Number is required.", "danger", "#activityClaimMessage");
                return;
            }

            /*--------------------------------------
             | ROW VALIDATION
             | Rules:
             |   - Amount > 0 → File REQUIRED
             |   - File selected → Amount > 0 REQUIRED
             --------------------------------------*/
            let rowError = false;

            $(".activity-row").each(function() {

                let amount = $(this).find(".activity-amount").val()?.trim();
                amount = amount ? parseFloat(amount) : 0;
                let fileInput = $(this).find(".activity-file")[0];
                let hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                // FILE required if amount > 0
                if (amount > 0 && !hasFile) {
                    rowError = true;
                    showMessage(
                        "Please upload a file for the row where amount is entered.",
                        "danger",
                        "#activityClaimMessage"
                    );
                    return false;
                }

                // AMOUNT required if file is selected
                if (hasFile && amount <= 0) {
                    rowError = true;
                    showMessage(
                        "Please enter an amount greater than 0 for the row where a file is selected.",
                        "danger",
                        "#activityClaimMessage"
                    );
                    return false;
                }
            });

            if (rowError) return;

            /*--------------------------------------
             | TOTAL AMOUNT VALIDATION
             --------------------------------------*/
            let totalAmount = parseFloat($("#activityTotalAmount").val()) || 0;

            if (totalAmount <= 0) {
                showMessage("Total Amount must be greater than 0.", "danger", "#activityClaimMessage");
                return;
            }

            /*--------------------------------------
             | REQ-003: Participant List Validation
             | Required for: Team Meetings (all depts), RCT/Retailer Meetings (Sales)
             --------------------------------------*/
            if ($('#activityParticipantSection').is(':visible') && $('#activityParticipantSection').attr('data-optional') !== '1') {
                const dept   = $('#activityParticipantDept').val().trim();
                const pCount = parseInt($('#activityParticipantCount').val()) || 0;
                const names  = $('#activityParticipantNames').val().trim();
                const file   = $('#activityParticipantFile')[0]?.files?.length > 0
                    || $("#activityDynamicFields input[type='file'][name^='file_participant_list']").toArray().some(el => el.files.length > 0);

                if (!dept) {
                    showMessage("Participant Department is required.", "danger", "#activityClaimMessage");
                    return;
                }
                if (pCount < 1) {
                    showMessage("Number of participants must be at least 1.", "danger", "#activityClaimMessage");
                    return;
                }
                if (!names && !file) {
                    showMessage("Please enter participant names or upload a participant list file.", "danger", "#activityClaimMessage");
                    return;
                }
            }

            /*--------------------------------------
             | FORM DATA BUILDING
             --------------------------------------*/
            const formData = new FormData($("#activityClaimForm")[0]);

            formData.set("activity_claim", activityClaim);
            formData.set("activity_type", activityType);
            formData.set("activity_bill_date", billDate);

            if (trialField.length) {
                formData.set("trial_no", trialNo);
            }

            // CROP multiple
            formData.delete("crop_list[]");
            crop.forEach(val => formData.append("crop_list[]", val));

            // VARIETY multiple
            formData.delete("variety_list[]");
            if (variety && variety.length) {
                variety.forEach(val => formData.append("variety_list[]", val));
            }

            /*--------------------------------------
             | AJAX SUBMISSION
             --------------------------------------*/
            $.ajax({
                url: "{{ route('activity.claim.submit') }}",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,

                beforeSend: function() {
                    $("#submitActivityClaimBtn")
                        .prop("disabled", true)
                        .html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');
                },

                success: function(response) {
                    if (response.status === "success") {
                        showMessage(response.msg, "success", "#activityClaimMessage");

                        setTimeout(() => {
                            $("#activityClaimModal").modal("hide");
                            $("#activityClaimForm")[0].reset();
                            $("#activityDynamicFields").html("");

                            if (typeof table !== "undefined") {
                                table.ajax.reload();
                            }
                        }, 1500);
                    } else {
                        showMessage(response.msg, "danger", "#activityClaimMessage");
                    }
                },

                error: function(xhr) {
                    console.error("Server Error", xhr.responseText);
                    showMessage("Server error occurred while submitting activity claim.", "danger", "#activityClaimMessage");
                },

                complete: function() {
                    $("#submitActivityClaimBtn").prop("disabled", false).text("Submit");
                }
            });

        });


    });
</script>
@endpush