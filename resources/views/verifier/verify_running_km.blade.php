@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        {{-- Tab Navigation --}}
                        <div class="card-header p-0 border-bottom-0">
                            <ul class="nav nav-tabs card-header-tabs" id="runningKmTabs" role="tablist" style="padding: 0 1rem;">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="tab-verify-btn" data-bs-toggle="tab"
                                        data-bs-target="#tab-verify" type="button" role="tab">
                                        <i class="ri-shield-check-line me-1"></i> Verify Readings
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tab-duplicate-btn" data-bs-toggle="tab"
                                        data-bs-target="#tab-duplicate" type="button" role="tab">
                                        <i class="ri-error-warning-line me-1"></i> Duplicate Readings
                                        <span id="dupGroupsBadge" class="badge bg-danger ms-1" style="display:none;">0</span>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="tab-content">
                            {{-- ── Tab 1: Verify Readings (existing) ────────────────────── --}}
                            <div class="tab-pane fade show active" id="tab-verify" role="tabpanel">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2" id="dataPunchSection">
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <h4 class="card-title mb-0">Verify Running Km</h4>
                                        {{-- Status color legend --}}
                                        <div class="d-flex align-items-center gap-2" style="font-size:12px;">
                                            <span class="d-flex align-items-center gap-1">
                                                <span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:#FFF9D6;border:1.5px solid #e0c93a;"></span>
                                                Pending
                                            </span>
                                            <span class="d-flex align-items-center gap-1">
                                                <span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:#E8FFE8;border:1.5px solid #4caf50;"></span>
                                                Approved
                                            </span>
                                            <span class="d-flex align-items-center gap-1">
                                                <span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:#FFE5E9;border:1.5px solid #f44336;"></span>
                                                Rejected
                                            </span>
                                            <span class="d-flex align-items-center gap-1">
                                                <span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:#cce5ff;border:1.5px solid #3399ff;"></span>
                                                Verified (pending flush)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <div>
                                            <select class="select2" id="selMonth" name="month" style="width:130px"
                                                data-placeholder="Select Month">
                                                <option value=""></option>
                                            </select>
                                        </div>
                                        <div>
                                            <select class="select2" id="selEmployee" name="employee" style="width:250px"
                                                data-placeholder="Select Employee">
                                                <option value=""></option>
                                            </select>
                                        </div>
                                        <div>
                                            <select class="select2" id="selBillDate" name="billdate" style="width:250px"
                                                data-placeholder="Select Date">
                                                <option value=""></option>
                                            </select>
                                        </div>
                                        <div class="form-check d-flex align-items-center mb-0">
                                            <input class="form-check-input me-1" type="checkbox" id="chkOnlyEmployee"
                                                value="1">
                                            <label class="form-check-label" for="chkOnlyEmployee">Only Employee</label>
                                        </div>
                                        <div class="form-check d-flex align-items-center mb-0">
                                            <input class="form-check-input me-1" type="checkbox" id="chkDenied" name="denied"
                                                value="1">
                                            <label class="form-check-label" for="chkDenied">Only Denied</label>
                                        </div>
                                        <div class="form-check d-flex align-items-center mb-0">
                                            <input class="form-check-input me-1" type="checkbox" id="chkMissingImages"
                                                name="missing_images" value="1">
                                            <label class="form-check-label" for="chkMissingImages">Missing Images</label>
                                        </div>
                                        <div class="form-check d-flex align-items-center mb-0">
                                            <input class="form-check-input me-1" type="checkbox" id="chkBothImages"
                                                name="both_images" value="1">
                                            <label class="form-check-label" for="chkBothImages">Both Images</label>
                                        </div>
                                        @if(isSuperAdmin())
                                        <div class="form-check d-flex align-items-center mb-0">
                                            <input class="form-check-input me-1" type="checkbox" id="chkDeactivated"
                                                name="deactivated" value="1">
                                            <label class="form-check-label text-danger fw-semibold" for="chkDeactivated" title="View deactivated Running KM claims">Deactivated</label>
                                        </div>
                                        <button type="button" id="btnBulkDeactivate" class="btn btn-sm btn-danger d-none" title="Deactivate selected claims">
                                            <i class="ri-forbid-line me-1"></i> Deactivate Selected
                                            <span id="bulkDeactivateCount" class="badge bg-white text-danger ms-1">0</span>
                                        </button>
                                        @endif
                                        <button type="button" id="btnVerifyAll" class="btn btn-sm btn-success d-none">
                                            <i class="ri-check-double-line me-1"></i> Verify All
                                            <span id="verifyAllCount" class="badge bg-white text-success ms-1">0</span>
                                        </button>
                                        <button type="button" id="btnReloadRunningKm" class="btn btn-sm btn-outline-secondary" title="Reload the list (keeps the current page and filters)">
                                            <i class="ri-refresh-line me-1"></i> Refresh
                                        </button>
                                        <button type="button" id="btnExportExcel" class="btn btn-sm btn-success" title="Export filtered records to Excel">
                                            <i class="ri-file-excel-2-line me-1"></i> Export Excel
                                        </button>
                                        <i class="ri-eraser-fill text-danger" id="clearFilter" style="cursor:pointer;font-size:18px;" title="Clear Filters"></i>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="filterPrompt" class="text-center py-5 text-muted">
                                        <i class="ri-filter-3-line fs-2 d-block mb-2"></i>
                                        Please select a filter to load data.
                                    </div>
                                    <table id="runningKmTable"
                                        class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                        style="width:100%; display:none;">
                                        <thead class="table-light">
                                            <tr>
                                                <th rowspan="2" scope="col" style="align-content: center;">Sn</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Emp Code</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Emp Name</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">ExpId</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Upload Date
                                                </th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Bill Date</th>
                                                <th colspan="4" scope="col" style="align-content: center;">Odometer
                                                    Reading</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Remark</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Total KM</th>
                                                <th rowspan="2" id="cumKmHeader" scope="col" style="align-content:center;">
                                                    Cum. KM
                                                </th>
                                                <th rowspan="2" id="rateHeader" scope="col" style="align-content:center;">
                                                    Rate
                                                </th>
                                                <th rowspan="2" id="amountHeader" scope="col" style="align-content:center;">
                                                    Amount
                                                </th>
                                                <th rowspan="2" scope="col" style="align-content: center;">2W/4W</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Update</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Verify</th>
                                                <th rowspan="2" scope="col" style="align-content: center;">Details</th>
                                                @if(isSuperAdmin())
                                                <th rowspan="2" scope="col" style="align-content: center; min-width: 130px;">
                                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                                        <input type="checkbox" class="form-check-input m-0" id="chkSelectAllDeactivate" title="Select All for Bulk Deactivate">
                                                        <span>Deactivate</span>
                                                    </div>
                                                </th>
                                                @endif
                                            </tr>
                                            <tr>
                                                <th scope="col" style="align-content: center;">Opn. Img</th>
                                                <th scope="col" style="align-content: center;">Opn.</th>
                                                <th scope="col" style="align-content: center;">Cls. Img</th>
                                                <th scope="col" style="align-content: center;">Cls.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- ── Tab 2: Duplicate Readings ──────────────────────────────── --}}
                            <div class="tab-pane fade" id="tab-duplicate" role="tabpanel">
                                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-3">
                                        <h4 class="card-title mb-0">Duplicate Odometer Readings</h4>
                                        <div class="d-flex align-items-center gap-2" style="font-size:12px;">
                                            <span class="d-flex align-items-center gap-1">
                                                <span class="badge" style="background:#fff3cd;color:#856404;border:1px solid #ffc107;">Same Employee</span>
                                                Same person submitted identical readings twice
                                            </span>
                                            <span class="d-flex align-items-center gap-1">
                                                <span class="badge" style="background:#f8d7da;color:#842029;border:1px solid #f5c2c7;">Cross Employee</span>
                                                Different employees share the same reading pair
                                            </span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" id="btnExportDuplicates" class="btn btn-sm btn-success" title="Export duplicate readings to Excel">
                                            <i class="ri-file-excel-2-line me-1"></i> Export Excel
                                        </button>
                                        <button type="button" id="btnReloadDuplicates" class="btn btn-sm btn-outline-secondary">
                                            <i class="ri-refresh-line me-1"></i> Refresh
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="dupLoadingSpinner" class="text-center py-5 text-muted" style="display:none;">
                                        <div class="spinner-border text-primary mb-2" role="status"></div>
                                        <div>Scanning for duplicate readings&hellip;</div>
                                    </div>
                                    <div id="dupNoData" class="text-center py-5 text-success" style="display:none;">
                                        <i class="ri-shield-check-line fs-2 d-block mb-2"></i>
                                        No duplicate odometer readings found.
                                    </div>
                                    <table id="duplicateOdoTable"
                                        class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                        style="width:100%; display:none;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Sn</th>
                                                <th>Emp Code</th>
                                                <th>Emp Name</th>
                                                <th>Dept</th>
                                                <th>ExpId</th>
                                                <th>Bill Date</th>
                                                <th>Upload Date</th>
                                                <th>Opening</th>
                                                <th>Closing</th>
                                                <th>Total KM</th>
                                                <th>Dup. Type</th>
                                                <th>Verify Status</th>
                                                <th>Review</th>
                                                <th>View</th>
                                                @if(isSuperAdmin())
                                                <th>Deactivate</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>{{-- end tab-content --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal.view_claim_detail />

    @if(isSuperAdmin())
    {{-- Deactivate Running KM Claim Modal --}}
    <div class="modal fade" id="deactivateRunningKmModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="deactivateRunningKmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white py-2">
                    <h5 class="modal-title fs-6" id="deactivateRunningKmModalLabel">
                        <i class="ri-forbid-line me-1"></i> Deactivate Running KM Claim
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size:12px;">
                        <i class="ri-shield-user-line fs-5 text-warning flex-shrink-0"></i>
                        <div>
                            <strong>Super Admin Action:</strong> Deactivating will mark this claim as <strong>Deactivate</strong> and remove it from the verification queue.
                        </div>
                    </div>

                    <table class="table table-sm table-bordered mb-3" style="font-size:13px;">
                        <tbody>
                            <tr>
                                <th class="bg-light" style="width:35%;">Exp ID</th>
                                <td><strong id="deactivateRunningKmExpIdLabel" class="text-danger">-</strong></td>
                            </tr>
                            <tr id="deactivateRunningKmEmpRow" style="display:none;">
                                <th class="bg-light">Employee</th>
                                <td id="deactivateRunningKmEmpLabel">-</td>
                            </tr>
                            <tr id="deactivateRunningKmBillDateRow" style="display:none;">
                                <th class="bg-light">Bill Date</th>
                                <td id="deactivateRunningKmBillDateLabel">-</td>
                            </tr>
                            <tr id="deactivateRunningKmTotKmRow" style="display:none;">
                                <th class="bg-light">Total KM</th>
                                <td id="deactivateRunningKmTotKmLabel">-</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mb-2">
                        <label for="deactivateRunningKmReason" class="form-label fw-semibold" style="font-size:13px;">
                            Reason for Deactivation <span class="text-danger">*</span>
                        </label>
                        <textarea id="deactivateRunningKmReason" class="form-control" rows="3"
                            placeholder="Please enter the reason for deactivating this running KM claim..."
                            maxlength="500"></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <div class="invalid-feedback d-none" id="deactivateRunningKmReasonFeedback">Reason is required (min 3 characters).</div>
                            <small class="text-muted ms-auto" id="deactivateRunningKmCharCount">0/500</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="confirmDeactivateRunningKmBtn">
                        <i class="ri-forbid-line me-1"></i>Deactivate Claim
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk Deactivate Running KM Claims Modal --}}
    <div class="modal fade" id="bulkDeactivateRunningKmModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="bulkDeactivateRunningKmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white py-2">
                    <h5 class="modal-title fs-6" id="bulkDeactivateRunningKmModalLabel">
                        <i class="ri-forbid-line me-1"></i> Bulk Deactivate Running KM Claims
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size:12px;">
                        <i class="ri-shield-user-line fs-5 text-warning flex-shrink-0"></i>
                        <div>
                            <strong>Super Admin Bulk Action:</strong> All selected claims will be marked as <strong>Deactivate</strong> and removed from the active verification queue.
                        </div>
                    </div>

                    <div class="card border mb-3 bg-light">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold" style="font-size:13px;">Selected Claims:</span>
                                <span class="badge bg-danger fs-12"><span id="bulkDeactivateSelectedCount">0</span> Claims</span>
                            </div>
                            <div class="text-muted" style="font-size:11px;">Claim Exp IDs:</div>
                            <div id="bulkDeactivateExpIdsList" class="bg-white p-2 rounded border mt-1 font-monospace" style="max-height:80px; overflow-y:auto; font-size:11px; word-break:break-all;">-</div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="bulkDeactivateReason" class="form-label fw-semibold" style="font-size:13px;">
                            Reason for Bulk Deactivation <span class="text-danger">*</span>
                        </label>
                        <textarea id="bulkDeactivateReason" class="form-control" rows="3"
                            placeholder="Please enter a reason for deactivating these claims (applies to all selected claims)..."
                            maxlength="500"></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <div class="invalid-feedback d-none" id="bulkDeactivateReasonFeedback">Reason is required (min 3 characters).</div>
                            <small class="text-muted ms-auto" id="bulkDeactivateCharCount">0/500</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="confirmBulkDeactivateRunningKmBtn">
                        <i class="ri-forbid-line me-1"></i>Confirm Bulk Deactivate
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Revert Deactivated Running KM Claim Modal --}}
    <div class="modal fade" id="revertDeactivatedRunningKmModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="revertDeactivatedRunningKmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white py-2">
                    <h5 class="modal-title fs-6" id="revertDeactivatedRunningKmModalLabel">
                        <i class="ri-arrow-go-back-line me-1"></i> Revert Deactivated Claim
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="mb-2">Are you sure you want to revert Exp ID: <strong id="revertRunningKmExpIdLabel" class="text-success">-</strong> back to active (<strong class="text-primary">Submitted</strong>) status?</p>
                    <div id="revertRunningKmEmpLabel" class="text-muted small mb-0"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="confirmRevertRunningKmBtn">
                        <i class="ri-arrow-go-back-line me-1"></i>Revert to Active
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
@push('styles')
    <style>
        #runningKmTable thead th,
        #duplicateOdoTable thead th {
            text-align: center !important;
        }

        @keyframes rowFlashGreen {
            0%   { background-color: #d4edda !important; }
            60%  { background-color: #a8d5b5 !important; }
            100% { background-color: #d4edda !important; }
        }
        @keyframes rowFlashBlue {
            0%   { background-color: #cce5ff !important; }
            60%  { background-color: #99caff !important; }
            100% { background-color: #cce5ff !important; }
        }
        .row-updated {
            animation: rowFlashGreen 0.6s ease-in-out;
            background-color: #d4edda !important;
            transition: background-color 2s ease;
        }
        .row-verified-pending {
            background-color: #cce5ff !important;
            opacity: 0.85;
        }
        .row-verified-pending td {
            pointer-events: none;
            user-select: none;
        }
        .row-verified-pending .mark-verified {
            pointer-events: none;
        }

        #runningKmTable_processing {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 100 !important;
            background: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid #e2e5e8 !important;
            border-radius: 8px !important;
            padding: 15px 30px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
            font-size: 14px !important;
            color: #495057 !important;
            display: none;
            width: auto !important;
            margin: 0 !important;
        }

        /* Fix DataTables sort icons to show correctly */
        table.dataTable thead .sorting::after,
        table.dataTable thead .sorting_asc::after,
        table.dataTable thead .sorting_desc::after {
            opacity: 1 !important;
        }

        /* Duplicate tab row group colors */
        .dup-group-even td { background-color: #fff8e1 !important; }
        .dup-group-odd  td { background-color: #fce4ec !important; }

        /* Nav tabs inside card */
        #runningKmTabs .nav-link {
            font-size: 13px;
            padding: 8px 16px;
        }
        #runningKmTabs .nav-link.active {
            font-weight: 600;
        }
        .tab-content > .tab-pane {
            border-top: 1px solid #dee2e6;
        }
    </style>
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('custom/js/pages/eligibility_panel.js') }}"></script>
    <script>
        $(document).ready(function() {

            // Track verified ExpIds that haven't been flushed yet
            let pendingVerifiedIds = new Set();

            function updateVerifyAllBtn() {
                const count = pendingVerifiedIds.size;
                if (count > 0) {
                    $("#btnVerifyAll").removeClass("d-none");
                    $("#verifyAllCount").text(count);
                } else {
                    $("#btnVerifyAll").addClass("d-none");
                }
            }

            @if(isSuperAdmin())
            // Track selected ExpIds for Bulk Deactivation
            let selectedDeactivateIds = new Set();

            function updateBulkDeactivateBtn() {
                const count = selectedDeactivateIds.size;
                if (count > 0) {
                    $("#btnBulkDeactivate").removeClass("d-none");
                    $("#bulkDeactivateCount").text(count);
                } else {
                    $("#btnBulkDeactivate").addClass("d-none");
                }
            }

            function syncSelectAllDeactivateCheckbox() {
                let $boxes = $(".chk-deactivate-row");
                if ($boxes.length === 0) {
                    $("#chkSelectAllDeactivate").prop("checked", false).prop("indeterminate", false);
                    return;
                }
                let checkedCount = 0;
                $boxes.each(function() {
                    if ($(this).is(":checked")) checkedCount++;
                });

                if (checkedCount === 0) {
                    $("#chkSelectAllDeactivate").prop("checked", false).prop("indeterminate", false);
                } else if (checkedCount === $boxes.length) {
                    $("#chkSelectAllDeactivate").prop("checked", true).prop("indeterminate", false);
                } else {
                    $("#chkSelectAllDeactivate").prop("checked", false).prop("indeterminate", true);
                }
            }
            @endif

            $(".select2").select2({
                allowClear: true,
                placeholder: function() {
                    return $(this).data("placeholder");
                },
            });

            function initRunningKmTable() {

                let showCumulative = $("#chkOnlyEmployee").is(":checked");

                let hasFilter = $("#selMonth").val() ||
                    $("#selEmployee").val() ||
                    $("#selBillDate").val() ||
                    $("#chkOnlyEmployee").is(":checked") ||
                    $("#chkDenied").is(":checked") ||
                    $("#chkMissingImages").is(":checked") ||
                    $("#chkBothImages").is(":checked") ||
                    ($("#chkDeactivated").length && $("#chkDeactivated").is(":checked"));

                if (!hasFilter) {
                    if ($.fn.DataTable.isDataTable("#runningKmTable")) {
                        $("#runningKmTable").DataTable().clear().destroy();
                    }
                    $("#runningKmTable").hide();
                    $("#filterPrompt").show();
                    pendingVerifiedIds.clear();
                    updateVerifyAllBtn();
                    @if(isSuperAdmin())
                    selectedDeactivateIds.clear();
                    updateBulkDeactivateBtn();
                    $("#chkSelectAllDeactivate").prop("checked", false).prop("indeterminate", false);
                    @endif
                    return;
                }

                $("#filterPrompt").hide();
                $("#runningKmTable").show();

                if ($.fn.DataTable.isDataTable("#runningKmTable")) {
                    $("#runningKmTable").DataTable().clear().destroy();
                }

                pendingVerifiedIds.clear();
                updateVerifyAllBtn();
                @if(isSuperAdmin())
                selectedDeactivateIds.clear();
                updateBulkDeactivateBtn();
                $("#chkSelectAllDeactivate").prop("checked", false).prop("indeterminate", false);
                @endif

                let dtColumns = [{
                        data: "DT_RowIndex",
                        className: "text-center",
                        orderable: false,
                        searchable: false
                    },
                    { data: "EmpCode",        className: "text-center" },
                    { data: "employee_name",  className: "text-start employee-name-col" },
                    { data: "ExpId",          className: "text-center" },
                    { data: "CrDate",         className: "text-center" },
                    { data: "BillDate",       className: "text-center" },
                    { data: "opening_image",  className: "text-center", searchable: false, orderable: false },
                    { data: "odomtr_opening", className: "text-center", orderable: false },
                    { data: "closing_image",  className: "text-center", searchable: false, orderable: false },
                    { data: "odomtr_closing", className: "text-center", orderable: false },
                    { data: "verifyremark",   className: "text-center", orderable: false },
                    { data: "TotKm",          className: "text-center" },
                    {
                        data: "cumulative_km", className: "text-center",
                        searchable: false, orderable: false, defaultContent: "-",
                        render: function(data) { return data ? `<span class="fw-bold text-primary">${data}</span>` : "-"; }
                    },
                    { data: "rate_column",   className: "text-center", searchable: false, orderable: false },
                    { data: "amount_column", className: "text-center", searchable: false, orderable: false },
                    { data: "vehicle_type",  className: "text-center", searchable: false, orderable: false },
                    { data: "update_action", className: "text-center", searchable: false, orderable: false },
                    { data: "verify_action", className: "text-center", searchable: false, orderable: false },
                    { data: "details_action",className: "text-center", searchable: false, orderable: false }
                    @if(isSuperAdmin())
                    ,{ data: "deactivate_action", className: "text-center", searchable: false, orderable: false }
                    @endif
                ];

                let columnDefs = [];

                // Punching tracker #5: manual Refresh for the main list (no auto-refresh)
                $(document).on("click", "#btnReloadRunningKm", function() {
                    if ($.fn.DataTable.isDataTable("#runningKmTable")) {
                        $("#runningKmTable").DataTable().ajax.reload(null, false);
                    }
                });
                let table = $("#runningKmTable").DataTable({
                    processing: true,
                    serverSide: true,
                    ordering: true,
                    orderClasses: false,
                    order: [[5, 'asc']],
                    ajax: {
                        url: "{{ route('verify.running.km.data') }}",
                        type: "POST",
                        data: function(d) {
                            d._token = "{{ csrf_token() }}";
                            d.month    = $("#selMonth").val();
                            d.employee = $("#selEmployee").val();
                            d.denied        = $("#chkDenied").is(":checked") ? 1 : 0;
                            d.only_employee = $("#chkOnlyEmployee").is(":checked") ? 1 : 0;
                            d.missing_images = $("#chkMissingImages").is(":checked") ? 1 : 0;
                            d.both_images   = $("#chkBothImages").is(":checked") ? 1 : 0;
                            d.bill_date_range = $("#dateRangePicker").val();
                            d.bill_date = $("#selBillDate").val();
                            d.deactivated = ($("#chkDeactivated").length && $("#chkDeactivated").is(":checked")) ? 1 : 0;
                        },
                        dataSrc: function(json) {
                            updateFilterDropdowns(json.months, json.employees, json.bill_dates);
                            return json.data;
                        }
                    },

                    rowCallback: function(row, data) {
                        if (pendingVerifiedIds.has(data.ExpId)) {
                            $(row).addClass("row-verified-pending");
                            return;
                        }
                        let bgColor = "#FFFFFF";
                        if (data.Backdate_Odometer_Status === "P") bgColor = "#FFF9D6";
                        if (data.Backdate_Odometer_Status === "A") bgColor = "#E8FFE8";
                        if (data.Backdate_Odometer_Status === "R") bgColor = "#FFE5E9";
                        $(row).css("background-color", bgColor);
                    },

                    drawCallback: function() {
                        @if(isSuperAdmin())
                        $(".chk-deactivate-row").each(function() {
                            let expId = parseInt($(this).val(), 10);
                            if (selectedDeactivateIds.has(expId)) {
                                $(this).prop("checked", true);
                            }
                        });
                        syncSelectAllDeactivateCheckbox();
                        @endif
                    },

                    columns: dtColumns,
                    columnDefs: columnDefs,
                    pageLength: 100,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    responsive: true,
                    searching: true,
                    info: true,
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                    language: {
                        processing: '<div class="d-flex align-items-center gap-2"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading...</div>'
                    },
                });

                function recalcRow(expId) {
                    let opening = parseFloat($(`input[data-expid='${expId}'][data-type='opening']`).val());
                    let closing = parseFloat($(`input[data-expid='${expId}'][data-type='closing']`).val());
                    let rate = parseFloat($(`input.rate-input[data-expid='${expId}']`).val()) || 0;
                    if (!isNaN(opening) && !isNaN(closing) && closing >= opening && opening >= 0) {
                        let km = closing - opening;
                        let amount = km * rate;
                        $(`.row-amount-${expId}`).text(amount.toFixed(2));
                    }
                }

                $(document).off("input", ".rate-input, .odometer-input").on("input", ".rate-input, .odometer-input", function() {
                    let expId = $(this).data("expid");
                    recalcRow(expId);
                });

                $(document).off("change", ".vehicle-type-radio").on("change", ".vehicle-type-radio", function() {
                    let expId = $(this).data("expid");
                    let vehicleType = $(this).val();
                    let $rateInput = $(`input.rate-input[data-expid='${expId}']`);

                    $.ajax({
                        url: "{{ route('verify.running.km.calculate.rate') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            expId: expId,
                            vehicleType: vehicleType
                        },
                        success: function(res) {
                            if (res.success) {
                                $rateInput.val(parseFloat(res.rate).toFixed(2));
                                recalcRow(expId);
                            }
                        }
                    });
                });

                $(document).off("click", ".update-running-km").on("click", ".update-running-km", function() {
                    let expId = $(this).data("expid");
                    let opening = $(`input[data-expid='${expId}'][data-type='opening']`).val();
                    let closing = $(`input[data-expid='${expId}'][data-type='closing']`).val();
                    let verifyremark = $(`input[data-expid='${expId}'][data-type='verifyremark']`).val();
                    let vehicleType = $(`input[name='vehicle_type_${expId}']:checked`).val();
                    let rate = $(`input.rate-input[data-expid='${expId}']`).val();

                    if (opening === '' || closing === '' || isNaN(opening) || isNaN(closing)) {
                        showAlert("warning", "ri-error-warning-line",
                            "Opening and closing readings required.");
                        return;
                    }
                    if (parseFloat(opening) < 0 || parseFloat(closing) <= 0) {
                        showAlert("warning", "ri-error-warning-line",
                            "Readings must be valid numbers (opening >= 0, closing > 0).");
                        return;
                    }
                    if (parseFloat(closing) <= parseFloat(opening)) {
                        showAlert("warning", "ri-error-warning-line",
                            "Closing odometer must be greater than opening odometer.");
                        return;
                    }
                    if (!vehicleType) {
                        showAlert("warning", "ri-error-warning-line", "Please select 2W or 4W.");
                        return;
                    }

                    $.ajax({
                        url: "{{ route('verify.running.km.update') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            expId,
                            opening,
                            closing,
                            verifyremark,
                            vehicleType,
                            rate,
                        },
                        success: function(res) {
                            if (res.success) {
                                showAlert("success", "ri-checkbox-circle-line",
                                    "Updated successfully!");
                                table.ajax.reload(function() {
                                    // highlight the updated row after reload
                                    $("#runningKmTable tbody tr").each(function() {
                                        let rowExpId = $(this).find(".update-running-km").data("expid");
                                        if (rowExpId == expId) {
                                            $(this).addClass("row-updated");
                                            setTimeout(() => $(this).removeClass("row-updated"), 3000);
                                        }
                                    });
                                }, false);
                            } else {
                                showAlert("danger", "ri-close-circle-line", res.message);
                            }
                        }
                    });
                });


                $(document).off("click", ".mark-verified").on("click", ".mark-verified", function() {
                    let $btn  = $(this);
                    let expId = $btn.data("expid");
                    let $row  = $btn.closest("tr");
                    let rate  = $(`input.rate-input[data-expid='${expId}']`).val();
                    let vehicleType = $(`input[name='vehicle_type_${expId}']:checked`).val();

                    $btn.html('<div class="spinner-border spinner-border-sm"></div>').prop("disabled", true);

                    $.ajax({
                        url: "{{ route('verify.running.km.verify') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            expId: expId,
                            rate: rate,
                            vehicleType: vehicleType
                        },
                        success: function(res) {
                            if (res.success) {
                                showAlert("success", "ri-checkbox-circle-line",
                                    "Record verified successfully!");

                                // Track & style — do NOT reload table yet
                                pendingVerifiedIds.add(expId);
                                updateVerifyAllBtn();

                                $row.addClass("row-verified-pending");
                                $row.css("background-color", "");
                                $btn.closest("td").html(
                                    '<span class="badge bg-success"><i class="ri-check-double-line me-1"></i>Done</span>'
                                );
                            } else {
                                showAlert("danger", "ri-close-circle-line", res.message);
                                $btn.html('<i class="ri-check-double-line me-1"></i> Verify').prop("disabled", false);
                            }
                        },
                        error: function() {
                            showAlert("danger", "ri-close-circle-line", "Server error. Please try again.");
                            $btn.html('<i class="ri-check-double-line me-1"></i> Verify').prop("disabled", false);
                        }
                    });
                });

                // ── Verify All — flush verified rows from table ───────────────
                $("#btnVerifyAll").off("click").on("click", function() {
                    if (pendingVerifiedIds.size === 0) return;
                    pendingVerifiedIds.clear();
                    updateVerifyAllBtn();
                    table.ajax.reload(null, false);
                });


                $(document).off("click", ".zoom-verify-img").on("click", ".zoom-verify-img", function() {
                    $(".zoom-verify-img").not(this).removeClass("zoomed");
                    $(this).toggleClass("zoomed");
                });

                // View claim detail (like ehome) - opens inline detail below the row
                $(document).off("click", ".view-claim-detail").on("click", ".view-claim-detail", function(e) {
                    e.preventDefault();
                    const expId = $(this).data("expid");
                    const claimId = $(this).data("claim-id");
                    const $tr = $(this).closest("tr");
                    const rowId = "detail-row-" + expId;
                    const $existing = $("#" + rowId);

                    // Toggle if already exists
                    if ($existing.length) {
                        $existing.toggleClass("d-none");
                        return;
                    }

                    const colCount = $tr.find("td").length;

                    // Insert detail row below
                    const $row = $(`
                        <tr id="${rowId}" class="detail-viewer-row">
                            <td colspan="${colCount}" class="p-0" style="border-top:3px solid #15648C;">
                                <div style="background:#fff;">
                                    <div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:#e8f4fd;font-size:12px;">
                                        <span><i class="ri-file-text-line me-1"></i><strong>Claim Details - ExpId: ${expId}</strong></span>
                                        <div class="d-flex align-items-center gap-2">
                                            @if(isSuperAdmin())
                                            <button type="button" class="btn btn-xs btn-danger deactivate-running-km-btn py-0 px-2" data-expid="${expId}" data-claim-id="${claimId}" style="font-size:11px;" title="Deactivate Claim">
                                                <i class="ri-forbid-line me-1"></i>Deactivate
                                            </button>
                                            @endif
                                            <button type="button" class="btn-close btn-sm close-detail-row" data-row="${rowId}"></button>
                                        </div>
                                    </div>
                                    <div class="row g-0" style="min-height:350px;">
                                        <div class="col-md-5 border-end d-flex align-items-center justify-content-center" id="detail-viewer-${expId}" style="background:#f8f9fa;min-height:350px;">
                                            <div class="text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading files...</div>
                                        </div>
                                        <div class="col-md-7 p-3 overflow-auto" id="detail-form-${expId}" style="max-height:450px;">
                                            <div class="text-muted text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>Loading details...</div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `);
                    $tr.after($row);

                    // Fetch claim detail form
                    $.get("{{ url('claim-detail') }}", { claim_id: claimId, expid: expId }, function(data) {
                        // Render files on the left
                        let filesHtml = '';
                        if (data.uploaded_files && data.uploaded_files.length > 0) {
                            filesHtml = '<div class="p-2" style="overflow-y:auto;max-height:400px;">';
                            data.uploaded_files.forEach(function(file) {
                                if (file.file_url) {
                                    filesHtml += `<div class="mb-2 text-center">
                                        <img alt="Bill or document image" src="${file.file_url}" class="img-fluid border rounded" style="max-height:140px;object-fit:cover;cursor:pointer;" onclick="window.open('${file.file_url}','_blank')">
                                        <br><small class="text-muted">${file.file_name || ''}</small>
                                    </div>`;
                                }
                            });
                            filesHtml += '</div>';
                        } else {
                            filesHtml = '<div class="text-muted text-center py-4">No files uploaded</div>';
                        }
                        $(`#detail-viewer-${expId}`).html(filesHtml);

                        // Render eligibility panel + form on the right
                        let eligHtml = '';
                        if (typeof buildEligibilityPanel === 'function' && data.eligibility) {
                            eligHtml = buildEligibilityPanel(data.eligibility);
                        }
                        $(`#detail-form-${expId}`).html((data.html || '<div class="text-muted">No details available</div>') + eligHtml);
                    }).fail(function() {
                        $(`#detail-viewer-${expId}`).html('<div class="text-danger text-center py-4">Failed to load</div>');
                        $(`#detail-form-${expId}`).html('<div class="text-danger text-center py-4">Failed to load details</div>');
                    });
                });

                // Close detail row
                $(document).off("click", ".close-detail-row").on("click", ".close-detail-row", function() {
                    const rowId = $(this).data("row");
                    $("#" + rowId).remove();
                });
            }


            function updateFilterDropdowns(months, employees, billDates) {
                const currentMonth = $("#selMonth").val();
                const currentEmployee = $("#selEmployee").val();

                $("#selMonth").empty().append('<option value=""></option>');
                months?.forEach((month) => {
                    $("#selMonth").append(
                        `<option value="${month.value}" ${currentMonth == month.value ? "selected" : ""}>${month.text}</option>`
                    );
                });

                $("#selEmployee").empty().append('<option value=""></option>');
                employees?.forEach((emp) => {
                    $("#selEmployee").append(
                        `<option value="${emp.value}" ${currentEmployee == emp.value ? "selected" : ""}>${emp.text}</option>`
                    );
                });

                const currentBillDate = $("#selBillDate").val();
                $("#selBillDate").empty().append('<option value=""></option>');
                billDates?.forEach((d) => {
                    $("#selBillDate").append(
                        `<option value="${d.value}"
                    ${currentBillDate == d.value ? "selected" : ""}>
                    ${d.text} (${d.count})
                    </option>`
                    );
                });

                $("#selBillDate").trigger("change.select2");
                $("#selMonth, #selEmployee").trigger("change.select2");
            }

            function loadFilterDropdowns() {
                $.post("{{ route('verify.running.km.data') }}", {
                    _token: "{{ csrf_token() }}",
                    only_dropdowns: 1,
                    start: 0,
                    length: 0
                }, function(json) {
                    updateFilterDropdowns(json.months, json.employees, json.bill_dates);
                });
            }

            loadFilterDropdowns();

            $("#selMonth, #selEmployee").on("change", function() {
                initRunningKmTable();
            });

            $("#chkDenied").on("change", function() {
                initRunningKmTable();
            });

            $("#chkMissingImages").on("change", function() {
                initRunningKmTable();
            });

            $("#chkOnlyEmployee").on("change", function() {
                $("#selEmployee").val("").trigger("change.select2");
                initRunningKmTable();
            });

            $("#selBillDate").on("change", function() {
                initRunningKmTable();
            });

            $("#chkBothImages").on("change", function() {
                initRunningKmTable();
            });

            @if(isSuperAdmin())
            $("#chkDeactivated").on("change", function() {
                initRunningKmTable();
            });
            @endif

            $("#clearFilter").on("click", function() {
                $("#selMonth").val("").trigger("change.select2");
                $("#selEmployee").val("").trigger("change.select2");
                $("#selBillDate").val("").trigger("change.select2");
                $("#chkDenied").prop("checked", false);
                $("#chkMissingImages").prop("checked", false);
                $("#chkOnlyEmployee").prop("checked", false);
                $("#chkBothImages").prop("checked", false);
                if ($("#chkDeactivated").length) {
                    $("#chkDeactivated").prop("checked", false);
                }
                if ($.fn.DataTable.isDataTable("#runningKmTable")) {
                    $("#runningKmTable").DataTable().clear().destroy();
                }
                pendingVerifiedIds.clear();
                updateVerifyAllBtn();
                @if(isSuperAdmin())
                selectedDeactivateIds.clear();
                updateBulkDeactivateBtn();
                $("#chkSelectAllDeactivate").prop("checked", false).prop("indeterminate", false);
                @endif
                $("#runningKmTable").hide();
                $("#filterPrompt").show();
            });

            // ── Export to Excel (Verify Readings) ─────────────────────────
            $("#btnExportExcel").on("click", function() {
                let hasFilter = $("#selMonth").val() ||
                    $("#selEmployee").val() ||
                    $("#selBillDate").val() ||
                    $("#chkOnlyEmployee").is(":checked") ||
                    $("#chkDenied").is(":checked") ||
                    $("#chkMissingImages").is(":checked") ||
                    $("#chkBothImages").is(":checked") ||
                    ($("#chkDeactivated").length && $("#chkDeactivated").is(":checked"));

                if (!hasFilter) {
                    showAlert("warning", "ri-error-warning-line", "Please select at least one filter before exporting data.");
                    return;
                }

                let searchVal = "";
                if ($.fn.DataTable.isDataTable("#runningKmTable")) {
                    searchVal = $("#runningKmTable").DataTable().search() || "";
                }

                let params = {
                    month: $("#selMonth").val() || "",
                    employee: $("#selEmployee").val() || "",
                    bill_date: $("#selBillDate").val() || "",
                    only_employee: $("#chkOnlyEmployee").is(":checked") ? 1 : 0,
                    denied: $("#chkDenied").is(":checked") ? 1 : 0,
                    missing_images: $("#chkMissingImages").is(":checked") ? 1 : 0,
                    both_images: $("#chkBothImages").is(":checked") ? 1 : 0,
                    deactivated: ($("#chkDeactivated").length && $("#chkDeactivated").is(":checked")) ? 1 : 0,
                    search: searchVal,
                    bill_date_range: $("#dateRangePicker").val() || ""
                };

                let $btn = $(this);
                let originalHtml = $btn.html();
                $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Exporting...').prop("disabled", true);

                let queryString = $.param(params);
                window.location.href = "{{ route('verify.running.km.export') }}?" + queryString;

                setTimeout(function() {
                    $btn.html(originalHtml).prop("disabled", false);
                }, 2500);
            });

        // Duplicate Readings Export Handler
        $('#btnExportDuplicates').on('click', function() {
            let $btn = $(this);
            let originalHtml = $btn.html();
            $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Exporting...').prop("disabled", true);

            window.location.href = "{{ route('verify.running.km.duplicate.odometer.export') }}";

            setTimeout(function() {
                $btn.html(originalHtml).prop("disabled", false);
            }, 2500);
        });

        // ══════════════════════════════════════════════════════════════
        // Duplicate Readings Tab
        // ══════════════════════════════════════════════════════════════
        let dupTableInitialized = false;

        // Palette of subtle background colors for group rows
        const DUP_GROUP_COLORS = [
            '#fff3cd', '#fde8e8', '#e8f4fd', '#e8fdf0', '#f3e8fd',
            '#fdf5e8', '#e8fdfd', '#fde8f8', '#f0fde8', '#e8e8fd',
        ];

        function loadDuplicateTable() {
            $('#dupLoadingSpinner').show();
            $('#dupNoData').hide();
            $('#duplicateOdoTable').hide();

            if ($.fn.DataTable.isDataTable('#duplicateOdoTable')) {
                $('#duplicateOdoTable').DataTable().destroy();
            }

            $.ajax({
                url: '{{ route("verify.running.km.duplicate.odometer") }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', draw: 1, start: 0, length: -1 },
                success: function(json) {
                    $('#dupLoadingSpinner').hide();

                    const rows      = json.data || [];
                    const groupsCnt = json.groups_count || 0;

                    // Update badge on tab
                    // ODO-DUP-02: the badge counts groups still waiting for the verifier (they cannot be approved)
                    const openCnt = json.open_groups || 0, heldCnt = json.held_count || 0;
                    if (openCnt > 0 || groupsCnt > 0) {
                        $('#dupGroupsBadge').text((openCnt > 0 ? openCnt + ' to review' : groupsCnt + ' groups') + (heldCnt ? ' · ' + heldCnt + ' held' : '')).show();
                    } else if (heldCnt > 0) {
                        $('#dupGroupsBadge').text(heldCnt + ' held by system').show();
                    } else {
                        $('#dupGroupsBadge').hide();
                    }

                    if (rows.length === 0) {
                        $('#dupNoData').show();
                        return;
                    }

                    // Assign color index per unique group_key
                    const groupColorMap = {};
                    let colorIdx = 0;
                    rows.forEach(function(r) {
                        if (!(r.group_key in groupColorMap)) {
                            groupColorMap[r.group_key] = colorIdx % DUP_GROUP_COLORS.length;
                            colorIdx++;
                        }
                    });

                    $('#duplicateOdoTable').show().DataTable({
                        data: rows,
                        ordering: true,
                        order: [[7, 'asc']],
                        pageLength: 50,
                        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
                        responsive: true,
                        searching: true,
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                        columns: [
                            { data: null, render: function(d, t, r, m) { return m.row + 1; }, orderable: false, className: 'text-center' },
                            { data: 'EmpCode',          className: 'text-center' },
                            { data: 'employee_name',    className: 'text-start',
                              render: function(d, t, r) {
                                return '<div><span class="fw-semibold">' + (d || '') + '</span></div>';
                              }
                            },
                            { data: 'department_name',  className: 'text-start' },
                            { data: 'ExpId',            className: 'text-center' },
                            { data: 'BillDate',         className: 'text-center' },
                            { data: 'CrDate',           className: 'text-center' },
                            { data: 'odomtr_opening',   className: 'text-center fw-bold' },
                            { data: 'odomtr_closing',   className: 'text-center fw-bold' },
                            { data: 'TotKm',            className: 'text-center',
                              render: function(d) { return d ? parseInt(d).toLocaleString() : '0'; }
                            },
                            { data: 'duplicate_type',   className: 'text-center', orderable: false,
                              render: function(d, t, r) {
                                if (d === 'Held by system') {
                                    return '<span class="badge" style="background:#e2e3e5;color:#41464b;border:1px solid #c4c8cb;" title="' + (r.review_remark || '') + '">&#10006; Held by system (dup. of #' + (r.kept_exp_id || '') + ')</span>';
                                }
                                if (r.reviewed) {
                                    return '<span class="badge bg-success" title="' + $('<div>').text(r.review_remark || '').html() + '">&#10003; Reviewed: not a duplicate</span>';
                                }
                                if (d === 'Cross Employee') {
                                    return '<span class="badge" style="background:#f8d7da;color:#842029;border:1px solid #f5c2c7;">&#9888; Cross Employee</span>';
                                }
                                return '<span class="badge" style="background:#fff3cd;color:#856404;border:1px solid #ffc107;">&#8635; Same Employee</span>';
                              }
                            },
                            { data: 'v_verify',         className: 'text-center', orderable: false,
                              render: function(d) {
                                return d === 'Y'
                                    ? '<span class="badge bg-success">Verified</span>'
                                    : '<span class="badge bg-warning text-dark">Pending</span>';
                              }
                            },
                            { data: 'ExpId',            className: 'text-center', orderable: false,
                              render: function(d, t, r) {
                                if (r.duplicate_type === 'Same Employee' && !r.reviewed && r.ClaimStatus !== 'Deactivate') {
                                    return '<a href="javascript:void(0);" class="badge bg-warning text-dark dup-not-duplicate" data-expid="' + d + '" style="cursor:pointer;" title="Readings are genuine - let the claim go to approval">Not a duplicate</a>';
                                }
                                return '';
                              }
                            },
                            { data: 'ExpId',            className: 'text-center', orderable: false,
                              render: function(d, t, r) {
                                return '<a href="javascript:void(0);" class="badge bg-primary dup-view-detail" '
                                     + 'data-expid="' + d + '" data-claim-id="7" data-emp="' + r.CrBy + '" '
                                     + 'style="cursor:pointer;"><i class="ri-eye-line me-1"></i>View</a>';
                              }
                            },
                            @if(isSuperAdmin())
                            { data: 'ExpId',            className: 'text-center', orderable: false,
                              render: function(d, t, r) {
                                return '<a href="javascript:void(0);" class="badge bg-danger deactivate-running-km-btn" '
                                     + 'data-expid="' + d + '" data-claim-id="7" data-emp="' + (r.CrBy || '') + '" '
                                     + 'data-empcode="' + (r.EmpCode || '') + '" data-empname="' + (r.employee_name || '') + '" '
                                     + 'data-billdate="' + (r.BillDate || '') + '" data-totkm="' + (r.TotKm || '') + '" '
                                     + 'style="cursor:pointer;" title="Deactivate Claim"><i class="ri-forbid-line me-1"></i>Deactivate</a>';
                              }
                            },
                            @endif
                        ],
                        rowCallback: function(row, data) {
                            const ci    = groupColorMap[data.group_key] ?? 0;
                            const color = DUP_GROUP_COLORS[ci];
                            $(row).css('background-color', color);
                            $(row).find('td').css('background-color', color);
                        },
                    });

                    dupTableInitialized = true;
                },
                error: function() {
                    $('#dupLoadingSpinner').hide();
                    $('#dupNoData').show().html(
                        '<i class="ri-close-circle-line fs-2 text-danger d-block mb-2"></i>Failed to load duplicate data.'
                    );
                }
            });
        }

        // Load on first tab activation
        $('#tab-duplicate-btn').on('shown.bs.tab', function() {
            if (!dupTableInitialized) {
                loadDuplicateTable();
            }
        });

        // Reload button
        $('#btnReloadDuplicates').on('click', function() {
            dupTableInitialized = false;
            loadDuplicateTable();
        });

        // ODO-DUP-02: verifier marks the readings genuine so the claim can go to approval
        $(document).on('click', '.dup-not-duplicate', function(e) {
            e.preventDefault();
            const expId = $(this).data('expid');
            const $btn = $(this);
            const ask = function(text) {
                if (window.Swal) {
                    return Swal.fire({ title: 'Not a duplicate - ExpId ' + expId, input: 'textarea', inputLabel: 'Why are the same readings genuine?', inputValidator: function(v) { return (!v || v.trim().length < 5) ? 'Write the reason (at least 5 characters)' : undefined; }, showCancelButton: true }).then(function(r) { return r.isConfirmed ? r.value : null; });
                }
                return Promise.resolve(null);
            };
            ask().then(function(remark) {
                if (!remark) return;
                $.post('{{ route("verify.running.km.duplicate.odometer.review") }}', { _token: '{{ csrf_token() }}', expid: expId, remark: remark })
                    .done(function(res) { $btn.replaceWith('<span class="badge bg-success">Reviewed</span>'); if (window.toastr) toastr.success(res.message); })
                    .fail(function(x) { const m = (x.responseJSON && (x.responseJSON.message || x.responseJSON.msg)) || 'Could not save'; if (window.toastr) toastr.error(m); else if (window.Swal) Swal.fire('Error', m, 'error'); });
            });
        });

        // View claim detail from duplicate tab (reuses same detail-row mechanism)
        $(document).on('click', '.dup-view-detail', function(e) {
            e.preventDefault();
            const expId   = $(this).data('expid');
            const claimId = $(this).data('claim-id');
            const $tr     = $(this).closest('tr');
            const rowId   = 'detail-row-dup-' + expId;
            const $existing = $('#' + rowId);

            if ($existing.length) {
                $existing.toggleClass('d-none');
                return;
            }

            const colCount = $tr.find('td').length;
            const $row = $(`
                <tr id="${rowId}" class="detail-viewer-row">
                    <td colspan="${colCount}" class="p-0" style="border-top:3px solid #dc3545;">
                        <div style="background:#fff;">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:#fde8e8;font-size:12px;">
                                <span><i class="ri-file-text-line me-1"></i><strong>Claim Details - ExpId: ${expId}</strong></span>
                                <div class="d-flex align-items-center gap-2">
                                    @if(isSuperAdmin())
                                    <button type="button" class="btn btn-xs btn-danger deactivate-running-km-btn py-0 px-2" data-expid="${expId}" data-claim-id="${claimId}" style="font-size:11px;" title="Deactivate Claim">
                                        <i class="ri-forbid-line me-1"></i>Deactivate
                                    </button>
                                    @endif
                                    <button type="button" class="btn-close btn-sm close-detail-row" data-row="${rowId}"></button>
                                </div>
                            </div>
                            <div class="row g-0" style="min-height:350px;">
                                <div class="col-md-5 border-end d-flex align-items-center justify-content-center" id="dup-viewer-${expId}" style="background:#f8f9fa;min-height:350px;">
                                    <div class="text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading files...</div>
                                </div>
                                <div class="col-md-7 p-3 overflow-auto" id="dup-form-${expId}" style="max-height:450px;">
                                    <div class="text-muted text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>Loading details...</div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `);
            $tr.after($row);

            $.get('{{ url("claim-detail") }}', { claim_id: claimId, expid: expId }, function(data) {
                let filesHtml = '';
                if (data.uploaded_files && data.uploaded_files.length > 0) {
                    filesHtml = '<div class="p-2" style="overflow-y:auto;max-height:400px;">';
                    data.uploaded_files.forEach(function(file) {
                        if (file.file_url) {
                            filesHtml += `<div class="mb-2 text-center">
                                <img alt="Bill or document image" src="${file.file_url}" class="img-fluid border rounded" style="max-height:140px;object-fit:cover;cursor:pointer;" onclick="window.open('${file.file_url}','_blank')">
                                <br><small class="text-muted">${file.file_name || ''}</small>
                            </div>`;
                        }
                    });
                    filesHtml += '</div>';
                } else {
                    filesHtml = '<div class="text-muted text-center py-4">No files uploaded</div>';
                }
                $(`#dup-viewer-${expId}`).html(filesHtml);

                let eligHtml = '';
                if (typeof buildEligibilityPanel === 'function' && data.eligibility) {
                    eligHtml = buildEligibilityPanel(data.eligibility);
                }
                $(`#dup-form-${expId}`).html((data.html || '<div class="text-muted">No details available</div>') + eligHtml);
            }).fail(function() {
                $(`#dup-viewer-${expId}`).html('<div class="text-danger text-center py-4">Failed to load</div>');
                $(`#dup-form-${expId}`).html('<div class="text-danger text-center py-4">Failed to load details</div>');
            });
        });

        @if(isSuperAdmin())
        // ══════════════════════════════════════════════════════════════
        // Super Admin: Deactivate & Revert Running KM Claims
        // ══════════════════════════════════════════════════════════════
        var deactivateTargetExpId = null;

        $(document).on('click', '.deactivate-running-km-btn', function(e) {
            e.preventDefault();
            deactivateTargetExpId = $(this).data('expid');
            var empCode  = $(this).data('empcode') || '';
            var empName  = $(this).data('empname') || '';
            var billDate = $(this).data('billdate') || '';
            var totKm    = $(this).data('totkm') || '';

            $('#deactivateRunningKmExpIdLabel').text('#' + deactivateTargetExpId);
            if (empName || empCode) {
                $('#deactivateRunningKmEmpLabel').text((empCode ? empCode + ' - ' : '') + empName);
                $('#deactivateRunningKmEmpRow').show();
            } else {
                $('#deactivateRunningKmEmpRow').hide();
            }
            if (billDate) {
                $('#deactivateRunningKmBillDateLabel').text(billDate);
                $('#deactivateRunningKmBillDateRow').show();
            } else {
                $('#deactivateRunningKmBillDateRow').hide();
            }
            if (totKm) {
                $('#deactivateRunningKmTotKmLabel').text(totKm + ' KM');
                $('#deactivateRunningKmTotKmRow').show();
            } else {
                $('#deactivateRunningKmTotKmRow').hide();
            }

            $('#deactivateRunningKmReason').val('').removeClass('is-invalid');
            $('#deactivateRunningKmReasonFeedback').addClass('d-none');
            $('#deactivateRunningKmCharCount').text('0/500');

            new bootstrap.Modal(document.getElementById('deactivateRunningKmModal')).show();
        });

        $('#deactivateRunningKmReason').on('input', function() {
            var len = $(this).val().length;
            $('#deactivateRunningKmCharCount').text(len + '/500');
            if (len >= 3) {
                $(this).removeClass('is-invalid');
                $('#deactivateRunningKmReasonFeedback').addClass('d-none');
            }
        });

        $('#confirmDeactivateRunningKmBtn').on('click', function() {
            var reason = $('#deactivateRunningKmReason').val().trim();
            if (!reason || reason.length < 3) {
                $('#deactivateRunningKmReason').addClass('is-invalid');
                $('#deactivateRunningKmReasonFeedback').removeClass('d-none');
                return;
            }
            $('#deactivateRunningKmReason').removeClass('is-invalid');
            $('#deactivateRunningKmReasonFeedback').addClass('d-none');

            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Deactivating...');

            $.ajax({
                url: "{{ route('verify.running.km.deactivate') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    expId: deactivateTargetExpId,
                    reason: reason
                },
                success: function(res) {
                    if (res.success) {
                        var modalEl = document.getElementById('deactivateRunningKmModal');
                        var modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();

                        showAlert("success", "ri-checkbox-circle-line", res.message || "Running KM claim deactivated successfully.");

                        // Remove open detail row if open
                        $('#detail-row-' + deactivateTargetExpId).remove();
                        $('#detail-row-dup-' + deactivateTargetExpId).remove();

                        // Untrack pending verified if was present
                        if (pendingVerifiedIds.has(deactivateTargetExpId)) {
                            pendingVerifiedIds.delete(deactivateTargetExpId);
                            updateVerifyAllBtn();
                        }

                        // Reload main DataTable
                        if ($.fn.DataTable.isDataTable("#runningKmTable")) {
                            $("#runningKmTable").DataTable().ajax.reload(null, false);
                        }

                        // Reload duplicate table if active
                        if (dupTableInitialized) {
                            dupTableInitialized = false;
                            loadDuplicateTable();
                        }
                    } else {
                        showAlert("danger", "ri-error-warning-line", res.message || "Failed to deactivate claim.");
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Server error while deactivating claim.";
                    showAlert("danger", "ri-error-warning-line", msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    deactivateTargetExpId = null;
                }
            });
        });

        // ── Bulk Deactivate Event Listeners ─────────────────────────────────
        $(document).on('change', '#chkSelectAllDeactivate', function() {
            let isChecked = $(this).is(':checked');
            $('.chk-deactivate-row').each(function() {
                let expId = parseInt($(this).val(), 10);
                $(this).prop('checked', isChecked);
                if (isChecked) {
                    selectedDeactivateIds.add(expId);
                } else {
                    selectedDeactivateIds.delete(expId);
                }
            });
            updateBulkDeactivateBtn();
        });

        $(document).on('change', '.chk-deactivate-row', function() {
            let expId = parseInt($(this).val(), 10);
            if ($(this).is(':checked')) {
                selectedDeactivateIds.add(expId);
            } else {
                selectedDeactivateIds.delete(expId);
            }
            syncSelectAllDeactivateCheckbox();
            updateBulkDeactivateBtn();
        });

        $(document).on('click', '#btnBulkDeactivate', function() {
            let count = selectedDeactivateIds.size;
            if (count === 0) return;

            $('#bulkDeactivateSelectedCount').text(count);
            let idsList = Array.from(selectedDeactivateIds).map(id => '#' + id).join(', ');
            $('#bulkDeactivateExpIdsList').text(idsList);
            $('#bulkDeactivateReason').val('').removeClass('is-invalid');
            $('#bulkDeactivateReasonFeedback').addClass('d-none');
            $('#bulkDeactivateCharCount').text('0/500');

            new bootstrap.Modal(document.getElementById('bulkDeactivateRunningKmModal')).show();
        });

        $(document).on('input', '#bulkDeactivateReason', function() {
            let len = $(this).val().length;
            $('#bulkDeactivateCharCount').text(len + '/500');
            if (len >= 3) {
                $(this).removeClass('is-invalid');
                $('#bulkDeactivateReasonFeedback').addClass('d-none');
            }
        });

        $(document).on('click', '#confirmBulkDeactivateRunningKmBtn', function() {
            let count = selectedDeactivateIds.size;
            if (count === 0) {
                showAlert("warning", "ri-alert-line", "No claims selected for deactivation.");
                return;
            }

            let reason = $('#bulkDeactivateReason').val().trim();
            if (!reason || reason.length < 3) {
                $('#bulkDeactivateReason').addClass('is-invalid');
                $('#bulkDeactivateReasonFeedback').removeClass('d-none');
                return;
            }
            $('#bulkDeactivateReason').removeClass('is-invalid');
            $('#bulkDeactivateReasonFeedback').addClass('d-none');

            let $btn = $(this);
            let originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Deactivating ' + count + ' claims...');

            let expIdsArray = Array.from(selectedDeactivateIds);

            $.ajax({
                url: "{{ route('verify.running.km.bulk.deactivate') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    expIds: expIdsArray,
                    reason: reason
                },
                success: function(res) {
                    if (res.success) {
                        let modalEl = document.getElementById('bulkDeactivateRunningKmModal');
                        let modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();

                        showAlert("success", "ri-checkbox-circle-line", res.message || "Claims deactivated successfully.");

                        // Untrack from pendingVerifiedIds if present
                        expIdsArray.forEach(function(id) {
                            if (pendingVerifiedIds.has(id)) {
                                pendingVerifiedIds.delete(id);
                            }
                            $('#detail-row-' + id).remove();
                            $('#detail-row-dup-' + id).remove();
                        });
                        updateVerifyAllBtn();

                        // Clear selection
                        selectedDeactivateIds.clear();
                        updateBulkDeactivateBtn();
                        $('#chkSelectAllDeactivate').prop('checked', false).prop('indeterminate', false);

                        // Reload table
                        if ($.fn.DataTable.isDataTable("#runningKmTable")) {
                            $("#runningKmTable").DataTable().ajax.reload(null, false);
                        }
                        if (dupTableInitialized) {
                            dupTableInitialized = false;
                            loadDuplicateTable();
                        }
                    } else {
                        showAlert("danger", "ri-error-warning-line", res.message || "Failed to bulk deactivate claims.");
                    }
                },
                error: function(xhr) {
                    let msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Server error while bulk deactivating claims.";
                    showAlert("danger", "ri-error-warning-line", msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Revert Deactivated Claim
        var revertTargetExpId = null;
        $(document).on('click', '.revert-running-km-btn', function(e) {
            e.preventDefault();
            revertTargetExpId = $(this).data('expid');
            var empCode = $(this).data('empcode') || '';
            var empName = $(this).data('empname') || '';

            $('#revertRunningKmExpIdLabel').text('#' + revertTargetExpId);
            if (empName || empCode) {
                $('#revertRunningKmEmpLabel').text('Employee: ' + (empCode ? empCode + ' - ' : '') + empName);
            } else {
                $('#revertRunningKmEmpLabel').text('');
            }

            new bootstrap.Modal(document.getElementById('revertDeactivatedRunningKmModal')).show();
        });

        $('#confirmRevertRunningKmBtn').on('click', function() {
            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Reverting...');

            $.ajax({
                url: "{{ route('verify.running.km.revert.deactivated') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    expId: revertTargetExpId
                },
                success: function(res) {
                    if (res.success) {
                        var modalEl = document.getElementById('revertDeactivatedRunningKmModal');
                        var modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();

                        showAlert("success", "ri-checkbox-circle-line", res.message || "Claim reverted to active status successfully.");

                        if ($.fn.DataTable.isDataTable("#runningKmTable")) {
                            $("#runningKmTable").DataTable().ajax.reload(null, false);
                        }
                        if (dupTableInitialized) {
                            dupTableInitialized = false;
                            loadDuplicateTable();
                        }
                    } else {
                        showAlert("danger", "ri-error-warning-line", res.message || "Failed to revert claim.");
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Server error while reverting claim.";
                    showAlert("danger", "ri-error-warning-line", msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    revertTargetExpId = null;
                }
            });
        });
        @endif

    });
</script>
@endpush
