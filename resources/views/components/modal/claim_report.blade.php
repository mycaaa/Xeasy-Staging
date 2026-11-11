<div class="modal fade" id="exportModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="exportModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="progress-container" style="display: none;">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Claim Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="mb-4">
                        <h6>Select Report Type</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <select class="form-select" id="reportType" name="reportType" style="width: 100%;">
                                    <option value="" disabled selected>Select Report Option</option>
                                    @can('general_single_sheet')
                                        <option value="general"
                                            data-html="<i class='ri-file-text-fill'></i> General (Single Sheet)">General
                                            (Single Sheet)
                                        </option>
                                    @endcan
                                    @can('head_wise_single_sheet')
                                        <option value="head_wise"
                                            data-html="<i class='ri-file-text-fill'></i> Head Wise (Single Sheet)">Head Wise
                                            (Single Sheet)
                                        </option>
                                    @endcan
                                    @can('month_filter')
                                        <option value="month_wise"
                                            data-html="<i class='ri-stack-fill'></i> Month Wise (Multiple Sheet)">Month Wise
                                            (Multiple Sheet)
                                        </option>
                                    @endcan
                                    @can('department_filter')
                                        <option value="department_wise"
                                            data-html="<i class='ri-stack-fill'></i> Department Wise (Multiple Sheet)">
                                            Department Wise (Multiple Sheet)</option>
                                    @endcan
                                    @can('claim_type_filter')
                                        <option value="claim_type_wise"
                                            data-html="<i class='ri-stack-fill'></i> Claim Type Wise (Multiple Sheet)">Claim
                                            Type Wise (Multiple Sheet)</option>
                                    @endcan
                                </select>
                            </div>
                            <div class="col-md-2" style="align-content: center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="protectSheets"
                                        name="protectSheets">
                                    <label class="form-check-label" for="protectSheets">Protect Sheets</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <h6>Select Columns to Export</h6>
                        <div class="row">
                            <div class="col-12 mb-3" id="generalDetails">
                                <h6 class="text-primary">General Details</h6>
                                <div class="row">
                                    @can('user_filter')
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="emp_name" id="colEmpName" checked>
                                                <label class="form-check-label" for="colEmpName">Emp Name</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="emp_code" id="colEmpCode" checked>
                                                <label class="form-check-label" for="colEmpCode">Emp Code</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="grade" id="colGrade" checked>
                                                <label class="form-check-label" for="colGrade">Grade</label>
                                            </div>
                                        </div>
                                    @endcan
                                    @can('function_filter')
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="function" id="colFunction">
                                                <label class="form-check-label" for="colFunction">Function</label>
                                            </div>
                                        </div>
                                    @endcan
                                    @can('vertical_filter')
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="vertical" id="colVertical">
                                                <label class="form-check-label" for="colVertical">Vertical</label>
                                            </div>
                                        </div>
                                    @endcan
                                    @can('department_filter')
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="department" id="colDepartment">
                                                <label class="form-check-label" for="colDepartment">Department</label>
                                            </div>
                                        </div>
                                    @endcan
                                    @can('sub_department_filter')
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="sub_department" id="colSubDepartment">
                                                <label class="form-check-label" for="colSubDepartment">Sub
                                                    Department</label>
                                            </div>
                                        </div>
                                    @endcan
                                    @can('policy_filter')
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="policy" id="colPolicy">
                                                <label class="form-check-label" for="colPolicy">Policy</label>
                                            </div>
                                        </div>
                                    @endcan
                                    @can('vehicle_type_filter')
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="vehicle_type" id="colVehicleType">
                                                <label class="form-check-label" for="colVehicleType">Vehicle Type</label>
                                            </div>
                                        </div>
                                    @endcan
                                </div>
                            </div>
                            <div class="col-12 mb-3" id="claimDetails">
                                <h6 class="text-primary">Claim Details</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="exp_id" id="colSn" checked>
                                            <label class="form-check-label" for="colSn">Exp Id</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="claim_id" id="colClaimId">
                                            <label class="form-check-label" for="colClaimId">Claim ID</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="claim_type" id="colClaimType" checked>
                                            <label class="form-check-label" for="colClaimType">Claim Type</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="claim_status" id="colClaimStatus" checked>
                                            <label class="form-check-label" for="colClaimStatus">Claim Status</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="month" id="colMonth" checked>
                                            <label class="form-check-label" for="colMonth">Month</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="upload_date" id="colUploadDate" checked>
                                            <label class="form-check-label" for="colUploadDate">Upload Date</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="bill_date" id="colBillDate" checked>
                                            <label class="form-check-label" for="colBillDate">Bill Date</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="odomtr_opening" id="colOdoOpening">
                                            <label class="form-check-label" for="colOdoOpening">Odomter
                                                Opening</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="odomtr_closing" id="colOdoClosing">
                                            <label class="form-check-label" for="colOdoClosing">Odomter
                                                Closing</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="TotKm" id="colTotKm">
                                            <label class="form-check-label" for="colTotKm">Total KM</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="cumulative_km" id="colCumulativeKm">
                                            <label class="form-check-label" for="colCumulativeKm">Cumulative
                                                KM</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="WType" id="colWType">
                                            <label class="form-check-label" for="colWType">Wheeler Type</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="RatePerKM" id="colRatePerKM">
                                            <label class="form-check-label" for="colRatePerKM">Rate Per KM</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="activity_category" id="colActivityCategory">
                                            <label class="form-check-label" for="colActivityCategory">Activity
                                                Category</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="activity_type" id="colActivityType">
                                            <label class="form-check-label" for="colActivityType">Activity
                                                Type</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="traill_no" id="colTrialNo">
                                            <label class="form-check-label" for="colTrialNo">Trial No.</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="crop" id="colCrop">
                                            <label class="form-check-label" for="colCrop">Crop</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="variety" id="colVariety">
                                            <label class="form-check-label" for="colVariety">Variety</label>
                                        </div>
                                    </div>
                                    {{-- Tracker #35: participant list on the summary sheet --}}
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="participant_pax" id="colParticipantPax">
                                            <label class="form-check-label" for="colParticipantPax">PAX</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="participants_employee" id="colParticipantsEmployee">
                                            <label class="form-check-label" for="colParticipantsEmployee">Participants (Employees / Details)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="participants_other" id="colParticipantsOther">
                                            <label class="form-check-label" for="colParticipantsOther">Other Participants</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="participants_activity" id="colParticipantsActivity">
                                            <label class="form-check-label" for="colParticipantsActivity">Activity Participants</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mb-3" id="filledDetails">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <h6 class="text-primary mb-0">Filled Details</h6>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="FilledAmt" id="colFilledAmt" checked>
                                            <label class="form-check-label" for="colFilledAmt">Filled Amt</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="FilledDate" id="colFilledDate">
                                            <label class="form-check-label" for="colFilledDate">Filled Date</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input column-checkbox" type="checkbox"
                                                value="FilledByName" id="colFilledByName">
                                            <label class="form-check-label" for="colFilledByName">Filled By</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @can('verification_details')
                                <div class="col-12 mb-3" id="verificationDetails">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <h6 class="text-primary mb-0">Verification Details</h6>
                                        <div class="d-flex gap-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="verifyAmtCurrent" value="current" checked>
                                                <label class="form-check-label" for="verifyAmtCurrent" style="font-size:11px;">Current</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="verifyAmtPrevious" value="previous">
                                                <label class="form-check-label" for="verifyAmtPrevious" style="font-size:11px;">Previous</label>
                                            </div>
                                        </div>
                                        <small class="text-muted" id="verifyAmtVersionHint" style="font-size:10px;">Current = Verified Amt &nbsp;|&nbsp; Previous = Filled Amt (before verification)</small>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="VerifyAmt" id="colVerifyAmt" checked>
                                                <label class="form-check-label" for="colVerifyAmt">Verified Amt</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="VerifyTRemark" id="colVerifyTRemark">
                                                <label class="form-check-label" for="colVerifyTRemark">Verify
                                                    Remark</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="VerifyDate" id="colVerifyDate">
                                                <label class="form-check-label" for="colVerifyDate">Verify Date</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="VerifyByName" id="colVerifyByName">
                                                <label class="form-check-label" for="colVerifyByName">Verified By</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endcan
                            @can('approval_details')
                                <div class="col-12 mb-3" id="approvalAndFinanceDetails">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <h6 class="text-primary mb-0">Approval and Finance Details</h6>
                                        <div class="d-flex gap-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="apprAmtCurrent" value="current" checked>
                                                <label class="form-check-label" for="apprAmtCurrent" style="font-size:11px;">Current</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="apprAmtPrevious" value="previous">
                                                <label class="form-check-label" for="apprAmtPrevious" style="font-size:11px;">Previous</label>
                                            </div>
                                        </div>
                                        <small class="text-muted" style="font-size:10px;">Current = Approved/Financed Amt &nbsp;|&nbsp; Previous = Verified/Approved Amt (before this stage)</small>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="ApprAmt" id="colApprAmt" checked>
                                                <label class="form-check-label" for="colApprAmt">Approved Amt</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="ApprTRemark" id="colApprTRemark">
                                                <label class="form-check-label" for="colApprTRemark">Approval
                                                    Remark</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="ApprDate" id="colApprDate">
                                                <label class="form-check-label" for="colApprDate">Approval Date</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="ApprByName" id="colApprByName">
                                                <label class="form-check-label" for="colApprByName">Approved By</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="FinancedTAmt" id="colFinancedAmt" checked>
                                                <label class="form-check-label" for="colFinancedAmt">Financed Amt</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="FinancedTRemark" id="colFinancedTRemark">
                                                <label class="form-check-label" for="colFinancedTRemark">Finance
                                                    Remark</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="FinancedDate" id="colFinancedDate">
                                                <label class="form-check-label" for="colFinancedDate">Finance Date</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    value="FinancedByName" id="colFinancedByName">
                                                <label class="form-check-label" for="colFinancedByName">Financed By</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endcan
                            @can('finance_details')
                                <div class="col-12 mb-3" id="odometerAccessDetails">
                                    <h6 class="text-primary">Odometer & Excess Details</h6>
                                    <div class="row">

                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    id="colBackdateOdometerStatus" name="columns[]"
                                                    value="backdate_odometer_status">
                                                <label class="form-check-label" for="colBackdateOdometerStatus">
                                                    Backdated Odometer Status
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    id="colBackdateOdometerBy" name="columns[]"
                                                    value="backdate_odometer_by">
                                                <label class="form-check-label" for="colBackdateOdometerBy">
                                                    Backdated Odometer Approved By
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    id="colBackdateOdometerDate" name="columns[]"
                                                    value="backdate_odometer_date">
                                                <label class="form-check-label" for="colBackdateOdometerDate">
                                                    Backdated Odometer Approved Date
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    id="colDelayedDays" name="columns[]" value="delayed_days">
                                                <label class="form-check-label" for="colDelayedDays">
                                                    Delayed Days
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    id="colAccessRunningStatus" name="columns[]"
                                                    value="access_running_status">
                                                <label class="form-check-label" for="colAccessRunningStatus">
                                                    Excess Running Status
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    id="colAccessRunningBy" name="columns[]" value="access_running_by">
                                                <label class="form-check-label" for="colAccessRunningBy">
                                                    Excess Running Approved By
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-checkbox" type="checkbox"
                                                    id="colAccessRunningDate" name="columns[]"
                                                    value="access_running_date">
                                                <label class="form-check-label" for="colAccessRunningDate">
                                                    Excess Running Approved Date
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endcan
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div class="hstack gap-2 justify-content-end">
                    <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                        id="exportExcelBtn">
                        <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                            <span class="loader" style="display: none;"></span>
                        </i>
                        Export to Excel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
