{{-- resources/views/advance/request/create.blade.php --}}
@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $sessiondata = session()->all();
    $yearId =session("year_id");
    $company_id =session("company_id");
@endphp

<div class="page-content">
    <div class="container-fluid">
    <!-- Authentication Data (Hidden) -->
    <div id="authData" 
        data-employee-id="{{ $user->employee_id ?? '' }}"
        data-employee-name="{{ $user->name ?? '' }}"
        data-employee-code="{{ $user->employee_code ?? '' }}"
        data-department="{{ $user->department ?? '' }}"
        data-company-id="{{ $company_id ?? 0 }}"
        data-fyear-id="{{ $yearId ?? 0 }}"
        data-user-id="{{ $user->id ?? 0 }}"
        style="display: none;">
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0">
                <!-- Page Header -->
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="history.back()" style="font-size: 12px; padding: 3px 10px;">
                            <i class="ri-arrow-left-line align-middle me-1" style="font-size: 12px;"></i> Back
                        </button>
                        
                        <h5 class="card-title mb-0" style="font-size: 14px; font-weight: 600;">
                            <i class="ri-add-line me-1" style="font-size: 16px;"></i>
                            New Advance Request
                        </h5>
                        
                        <span></span> <!-- Empty span for spacing -->
                    </div>
                </div>
                
                <div class="card-body p-2">
                    <!-- Alert Message -->
                    <div id="messageAlert" class="alert alert-danger mb-2 p-2" style="display: none; font-size: 12px;"></div>

                    <!-- Main Form Section -->
                    <div id="mainFormSection">
                        <div id="advanceRequestForm">
                            <!-- Request Type Selection -->
                            <div class="row g-3 my-3" id="requestTypeSection" style="display: none;">
                                <div class="col-md-12 mt-0">
                                    <label class="form-label">Request For</label>
                                    <div class="d-flex gap-4" id="requestTypeOptions">
                                        <div class="form-check" id="selfRequestOption" style="display: none;">
                                            <input class="form-check-input" type="radio" name="requestType" id="requestSelf" value="self">
                                            <label class="form-check-label" for="requestSelf">
                                                Self
                                            </label>
                                        </div>
                                        <div class="form-check" id="teamRequestOption" style="display: none;">
                                            <input class="form-check-input" type="radio" name="requestType" id="requestTeam" value="team">
                                            <label class="form-check-label" for="requestTeam">
                                                Team Members
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Common Fields Section -->
                            <div class="row g-3 mb-3" id="commonFieldsSection" style="display: none;">
                                <div class="col-md-3">
                                    <label for="advanceType" class="form-label">Advance Type</label>
                                    <select class="form-select form-select-sm" id="advanceType" name="advanceType" required>
                                        <option value="">Select request type first</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3" id="activityNameSection">
                                    <label for="activityName" class="form-label">Activity Name</label>
                                    <select class="form-select form-select-sm" id="activityName" name="activityName">
                                        <option value="">Select advance type first</option>
                                    </select>
                                </div>

                                <div class="col-md-3" id="monthSection">
                                    <label for="month" class="form-label">Month</label>
                                    <select class="form-select form-select-sm" id="month" name="month" required>
                                        <option value="">Select advance type first</option>
                                    </select>
                                </div>

                                @if (config('foreign_currency.enabled', true))
                                {{-- FX-53: currency picker for foreign-currency (overseas) advances --}}
                                <div class="col-md-3" id="currencySection">
                                    <label for="advCurrency" class="form-label">Currency</label>
                                    <select class="form-select form-select-sm" id="advCurrency">
                                        <option value="INR" selected>INR - Indian Rupee</option>
                                        @foreach ((array) config('foreign_currency.currencies', []) as $cur)
                                            <option value="{{ $cur }}">{{ $cur }}</option>
                                        @endforeach
                                    </select>
                                    <div id="fxRateBox" class="mt-1" style="display:none;">
                                        <small class="d-block" id="fxRateInfo"></small>
                                        <div class="input-group input-group-sm mt-1" id="fxManualRateGroup" style="display:none;">
                                            <span class="input-group-text">Rate ₹</span>
                                            <input type="number" class="form-control" id="fxManualRate" min="0.0001" step="0.0001" placeholder="INR per 1 unit" aria-label="INR per 1 unit">
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <div class="col-md-3" id="selfAmountSection" style="display: none;">
                                    <label for="selfAmount" class="form-label">Amount (₹)</label>
                                    <input type="number" class="form-control form-control-sm" id="selfAmount" name="selfAmount" 
                                           placeholder="0.00" min="0" max="999999" step="0.01" required
                                           oninput="validateAmountInput(this)">
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted">Enter the advance amount you need</small>
                                        <small class="text-muted" id="selfAmountCounter">0/6</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Activity Details Section (for Activity advance only) -->
                            <div class="row g-3 mb-3" id="activityDetailsSection" style="display: none;">
                                <div class="col-md-3">
                                    <label for="commonActivityType" class="form-label">Activity Type</label>
                                    <select class="form-select form-select-sm" id="commonActivityType" name="commonActivityType">
                                        <option value="">Select Activity Type</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="commonCrop" class="form-label">Crop</label> <br>
                                    <select class="form-control form-control-sm select2 w-100" id="commonCrop" name="commonCrop[]" multiple data-placeholder="Select crops" required>
                                        <option value="">Select activity first to load crops</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="commonVariety" class="form-label">Variety</label><br>
                                    <select class="form-control form-control-sm select2 w-100" id="commonVariety" name="commonVariety[]" multiple disabled data-placeholder="Select crops first" required>
                                        <option value="">Select crops first</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="commonPurpose" class="form-label">Purpose</label>
                                    <textarea class="form-control form-control-sm" id="commonPurpose" name="commonPurpose" rows="1" placeholder="Enter purpose" maxlength="500" required></textarea>
                                    <small class="char-counter text-muted float-end">0/500</small>
                                </div>
                            </div>

                            <!-- Simple Purpose Section (for Expense/Other advance) -->
                            <div class="row g-3 mb-3" id="simplePurposeSection" style="display: none;">
                                <div class="col-md-12 mt-1">
                                    <label for="simplePurpose" class="form-label">Purpose</label>
                                    <textarea class="form-control form-control-sm" id="simplePurpose" name="simplePurpose" rows="2" placeholder="Enter the purpose for this advance" maxlength="500" required></textarea>
                                    <small class="char-counter text-muted float-end">0/500</small>
                                </div>
                            </div>

                            <!-- Employee Selection Table -->
                            <div class="mb-3" id="employeeTableSection" style="display: none;">
                                <label class="form-label">Select Employees & Enter Amount</label>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" style="font-size: 11px;">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;padding: 8px 5px;" class="text-center">
                                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                                </th>
                                                <th style="width: 100px;padding: 8px 5px;">Emp Code</th>
                                                <th style="padding: 8px 5px;">Full Name</th>
                                                <th style="width: 150px;padding: 8px 5px;">Head Quarter</th>
                                                <th style="width: 150px;padding: 8px 5px;">Amount (₹)</th>
                                            </tr>
                                        </thead>
                                        <tbody id="employeeTableBody">
                                            <tr>
                                                <td colspan="5" class="text-center py-3">Select request type to load employees</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="mt-3" id="submitButtonSection" style="display: none;">
                                <button type="button" class="btn btn-primary btn-sm" id="submitBtn" style="font-size: 12px; padding: 4px 20px;">
                                    <i class="ri-send-plane-line me-1"></i> Submit Advance Request
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('styles')
<style>
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.loading-overlay .spinner-border {
    width: 2rem;
    height: 2rem;
}

/* Form styling */
.form-label {
    font-weight: 600;
    color: #333;
    font-size: 11px;
    margin-bottom: 0.3rem;
}

.form-control-sm, .form-select-sm {
    font-size: 11px;
    padding: 0.3rem 0.5rem;
    height: auto;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa !important;
    font-size: 11px;
}

.table td {
    vertical-align: middle;
    font-size: 11px;
}

.btn-primary {
    background-color: #155997;
    border-color: #155997;
}

.btn-primary:hover {
    background-color: #0d3d66;
    border-color: #0d3d66;
}

/* Select2 customization */
.select2-container--default .select2-selection--multiple {
    min-height: 32px;
    font-size: 11px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #e3f2fd;
    border-color: #155997;
    color: #155997;
    font-size: 10px;
    padding: 2px 6px;
    margin: 2px;
}

/* Compact layout */
.card-body {
    padding: 12px;
}

.alert {
    font-size: 11px;
    padding: 6px 10px;
}

.char-counter {
    font-size: 10px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 8px;
    }
    
    .table-responsive {
        font-size: 10px !important;
    }
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    // ==========================================
    // GLOBAL VARIABLES
    // ==========================================
    const authData = document.getElementById('authData');
    const empId = authData ? authData.dataset.employeeId : '';
    const empName = authData ? authData.dataset.employeeName : '';
    const empCode = authData ? authData.dataset.employeeCode : '';
    const department = authData ? authData.dataset.department : '';
    const companyId = authData ? parseInt(authData.dataset.companyId) : 0;
    const fYearId = authData ? parseInt(authData.dataset.fyearId) : 0;
    const userId = authData ? parseInt(authData.dataset.userId) : 0;
    const tokenId = 'web-session'; // ADV-WEB-01: the web session is the login (the old hard-coded device token is removed); the endpoints still ask for a non-empty tokenid
    const API_BASE_URL = "{{ url('api') }}"; // runs in the browser: same host as the page (API_BASE_URL may be a server-internal address)
    const API_LOCAL_BASE_URL = "{{ url('api') }}";
    // API endpoints - all external APIs
 const apiEndpoints = {
        checkAccess: `${API_BASE_URL}/advance_request.php`,
        teamMembers: `${API_BASE_URL}/common.php`,
        activityList: `${API_BASE_URL}/extract-data.php`,
        submitDraft: `${API_BASE_URL}/advance_request.php`,
        activityTypes: `${API_BASE_URL}/extract-data.php`,
        cropList: `${API_BASE_URL}/extract-data.php`,
        varieties: `${API_BASE_URL}/extract-data.php`,
        submitRequest: `${API_LOCAL_BASE_URL}/advance/draft` // Laravel local API
    };

    let teamMembers = [];
    let activityTypes = [];
    let cropsList = [];
    let advanceAccess = {};
    let availableAdvanceTypes = [];
    let selectedAdvanceType = '';
    let selectedActivityId = '';
    let selectedMonth = '';

    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================
    function showLoading() {
        $('#loadingOverlay').show();
    }

    function hideLoading() {
        $('#loadingOverlay').hide();
    }

    function showMessage(message, type = 'danger') {
        const messageDiv = $('#messageAlert');
        messageDiv.removeClass('alert-success alert-danger alert-warning')
                  .addClass(`alert-${type}`)
                  .html(`<i class="ri-error-warning-line me-1"></i>${message}`)
                  .show();
        
        setTimeout(() => {
            messageDiv.fadeOut();
        }, 5000);
    }

    function hideMessage() {
        $('#messageAlert').hide();
    }

    function getMaxAmountForSelectedMonth() {
        if (selectedAdvanceType !== 'Expense' || !advanceAccess.ExpenseAdvanceMonths) {
            return 0;
        }
        
        console.log('Looking for month:', selectedMonth, 'in ExpenseAdvanceMonths:', advanceAccess.ExpenseAdvanceMonths);
        
        // Find the selected month in ExpenseAdvanceMonths
        const selectedMonthData = advanceAccess.ExpenseAdvanceMonths.find(month => {
            return month.month_number === parseInt(selectedMonth);
        });
        
        console.log('Found month data:', selectedMonthData);
        
        if (selectedMonthData && selectedMonthData.claim_amount) {
            return parseFloat(selectedMonthData.claim_amount);
        }
        
        return 0;
    }

    window.validateAmountInput = function (input) { return validateAmountInput(input); }; // ADV-WEB-01: used by oninput="" attributes
    function validateAmountInput(input) {
        const value = input.value.toString();
        const numericValue = parseFloat(value) || 0;
        const maxDigits = 6;
        const maxAmount = 999999;
        
        const digitsOnly = value.replace('.', '');
        const digitCount = digitsOnly.length;
        
        // Update counter if it exists
        const counterId = input.id + 'Counter';
        const counter = document.getElementById(counterId);
        if (counter) {
            counter.textContent = `${digitCount}/${maxDigits}`;
        }
        
        let hasError = false;
        let errorMessage = '';
        
        // Check digit limit
        if (digitCount > maxDigits) {
            hasError = true;
            errorMessage = 'Amount cannot exceed 6 digits';
            // Truncate to max digits
            const truncated = digitsOnly.substring(0, maxDigits);
            if (value.includes('.')) {
                const parts = value.split('.');
                const integerPart = parts[0];
                const decimalPart = parts[1] || '';
                if (integerPart.length > maxDigits) {
                    input.value = integerPart.substring(0, maxDigits);
                } else {
                    const remainingDigits = maxDigits - integerPart.length;
                    input.value = integerPart + '.' + decimalPart.substring(0, remainingDigits);
                }
            } else {
                input.value = truncated;
            }
        } else if (numericValue > maxAmount) {
            hasError = true;
            errorMessage = 'Amount cannot exceed ₹999,999';
            input.value = maxAmount;
        }
        
        // For Expense advance, check against claim_amount
        if (selectedAdvanceType === 'Expense') {
            const maxAllowedAmount = getMaxAmountForSelectedMonth();
            if (maxAllowedAmount > 0 && numericValue > maxAllowedAmount) {
                hasError = true;
                errorMessage = `Amount cannot exceed maximum limit of ₹${maxAllowedAmount.toLocaleString()} (based on claim amount)`;
                input.value = maxAllowedAmount.toFixed(2);
            }
        }
        
        // Update styling
        if (hasError) {
            input.style.borderColor = '#dc3545';
            if (counter) {
                counter.style.color = '#dc3545';
            }
        } else if (digitCount >= maxDigits * 0.8) {
            input.style.borderColor = '#ffc107';
            if (counter) {
                counter.style.color = '#ffc107';
            }
        } else {
            input.style.borderColor = '#ced4da';
            if (counter) {
                counter.style.color = '#6c757d';
            }
        }
        
        updateSubmitButtonVisibility();
    }

    function updateSubmitButtonVisibility() {
        const requestType = $('input[name="requestType"]:checked').val();
        const advanceType = $('#advanceType').val();
        const month = $('#month').val();
        
        let showSubmit = false;
        
        if (requestType && advanceType && month) {
            if (requestType === 'self') {
                const purpose = $('#simplePurpose').val();
                const amount = $('#selfAmount').val();
                showSubmit = purpose && purpose.trim() && amount && parseFloat(amount) > 0;
            } else if (requestType === 'team') {
                const checkedMembers = $('.member-checkbox:checked').length;
                showSubmit = checkedMembers > 0;
                
                if (advanceType === 'Activity') {
                    const selectedActivityId = $('#activityName').val();
                    const selectedCrops = $('#commonCrop').val() || [];
                    const selectedVarieties = $('#commonVariety').val() || [];
                    const purpose = $('#commonPurpose').val();
                    
                    showSubmit = showSubmit && selectedActivityId && selectedCrops.length > 0 && 
                               selectedVarieties.length > 0 && purpose && purpose.trim();
                }
            }
        }
        
        if (showSubmit) {
            $('#submitButtonSection').show();
        } else {
            $('#submitButtonSection').hide();
        }
    }

    // ==========================================
    // API CALLS
    // ==========================================
    function checkAdvanceAccess() {
        showLoading();
        $.ajax({
            url: apiEndpoints.checkAccess,
            method: 'GET',
            data: {
                value: 'show_request_tab',
                empid: empId,
                YearId: fYearId,
                ComId: companyId,
                tokenid: tokenId,
            },
            success: function(response) {
                hideLoading();
                console.log('Advance Access Response:', response);
                
                let parsedResponse = response;
                if (typeof response === 'string') {
                    try {
                        parsedResponse = JSON.parse(response);
                    } catch (e) {
                        showMessage('Error checking advance access permissions');
                        return;
                    }
                }
                
                if (parsedResponse.code === 200 && parsedResponse.data) {
                    advanceAccess = parsedResponse.data;
                    
                    // Check available advance types
                    availableAdvanceTypes = [];
                    if (advanceAccess.ActivityAdvance === 'Y') {
                        availableAdvanceTypes.push({
                            code: 'Activity',
                            name: 'Activity Advance'
                        });
                    }
                    if (advanceAccess.ExpenseAdvance === 'Y') {
                        availableAdvanceTypes.push({
                            code: 'Expense',
                            name: 'Expense Advance'
                        });
                    }
                    if (advanceAccess.OtherAdvance === 'Y') {
                        availableAdvanceTypes.push({
                            code: 'Other',
                            name: 'Other/Special Advance'
                        });
                    }
                    
                    // Determine which request types to show
                    const hasSelfAccess = (advanceAccess.ExpenseAdvance === 'Y' || advanceAccess.OtherAdvance === 'Y');
                    const hasTeamAccess = (advanceAccess.ActivityAdvance === 'Y');
                    
                    if (hasSelfAccess) {
                        $('#selfRequestOption').show();
                    }
                    if (hasTeamAccess) {
                        $('#teamRequestOption').show();
                    }
                    
                    if (hasSelfAccess || hasTeamAccess) {
                        $('#requestTypeSection').show();
                        
                        // Auto-select if only one option
                        if (hasSelfAccess && !hasTeamAccess) {
                            $('#requestSelf').prop('checked', true).trigger('change');
                        } else if (!hasSelfAccess && hasTeamAccess) {
                            $('#requestTeam').prop('checked', true).trigger('change');
                        }
                    } else {
                        showMessage('You do not have access to create advance requests.', 'warning');
                    }
                    
                } else {
                    showMessage('Unable to check advance access permissions', 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                showMessage('Error checking advance access permissions');
            }
        });
    }

    function fetchTeamMembers() {
        showLoading();
        $.ajax({
            url: apiEndpoints.teamMembers,
            method: 'GET',
            data: {
                value: 'TeamMemberAppraiser',
                empid: empId,
                YearId: fYearId,
                ComId: companyId,
                tokenid: tokenId,
            },
            success: function(response) {
                hideLoading();
                console.log('Team Members Response:', response);
                
                let parsedResponse = response;
                if (typeof response === 'string') {
                    try {
                        parsedResponse = JSON.parse(response);
                    } catch (e) {
                        showMessage('Error parsing team members data');
                        return;
                    }
                }
                
                if (parsedResponse.Code === "300" && parsedResponse.Team_list) {
                    teamMembers = parsedResponse.Team_list;
                    renderEmployeeTable();
                } else if (parsedResponse.code === 200 && parsedResponse.data) {
                    teamMembers = parsedResponse.data;
                    renderEmployeeTable();
                } else {
                    showMessage('No team members found', 'warning');
                    $('#employeeTableBody').html('<tr><td colspan="5" class="text-center">No team members found</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                showMessage('Error fetching team members');
                $('#employeeTableBody').html('<tr><td colspan="5" class="text-center">Error loading team members</td></tr>');
            }
        });
    }

    function populateAdvanceTypeDropdown(requestType) {
        let options = '<option value="">Select Advance Type</option>';
        
        let filteredTypes = [];
        if (requestType === 'self') {
            filteredTypes = availableAdvanceTypes.filter(type => type.code === 'Expense' || type.code === 'Other');
        } else if (requestType === 'team') {
            filteredTypes = availableAdvanceTypes.filter(type => type.code === 'Activity');
        }
        
        filteredTypes.forEach(type => {
            options += `<option value="${type.code}">${type.name}</option>`;
        });
        
        $('#advanceType').html(options);
        
        if (filteredTypes.length === 1) {
            const singleType = filteredTypes[0];
            $('#advanceType').val(singleType.code);
            $('#advanceType').closest('.col-md-3').hide();
            $('#advanceType').trigger('change');
        } else {
            $('#advanceType').closest('.col-md-3').show();
        }
    }

    function populateMonthDropdown(advanceType = null) {
        let monthOptions = '<option value="">Select Month</option>';
        
        if (advanceType && advanceAccess) {
            const monthsKey = `${advanceType}AdvanceMonths`;
            const months = advanceAccess[monthsKey] || [];
            
            if (months.length > 0) {
                months.forEach(month => {
                    let monthValue, displayText;
                    
                    if (month.month_year) {
                        monthValue = month.month_number.toString();
                        displayText = month.month_name;
                    } else {
                        monthValue = month.month_number.toString();
                        displayText = month.month_name;
                    }
                    
                    monthOptions += `<option value="${monthValue}">${displayText}</option>`;
                });
            }
        }
        
        $('#month').html(monthOptions);
    }

    function fetchActivityList() {
        $.ajax({
            url: apiEndpoints.activityList,
            method: 'GET',
            data: {
                value: 'activity_claim_list',
                YearId: fYearId,
                ComId: companyId,
                empid: empId,
                tokenid: tokenId,
            },
            success: function(response) {
                console.log('Activity List Response:', response);
                if (response.success === "true" && response.data) {
                    let options = '<option value="">Select Activity</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.ClaimId}">${item.ClaimName}</option>`;
                    });
                    $('#activityName').html(options).prop('disabled', false);
                }
            },
            error: function() {
                $('#activityName').html('<option value="">Error loading activities</option>').prop('disabled', true);
            }
        });
    }

    function fetchActivityTypes() {
        $.ajax({
            url: apiEndpoints.activityTypes,
            method: 'GET',
            data: {
                value: 'activity_type',
                YearId: fYearId,
                ComId: companyId,
                empid: empId,
                tokenid: tokenId,
            },
            success: function(response) {
                console.log('Activity Types Response:', response);
                if (response.success === "true" && response.data) {
                    activityTypes = response.data;
                    populateActivityTypesDropdown();
                }
            },
            error: function() {
                console.error('Error fetching activity types');
            }
        });
    }

    function fetchCropsList(claimId = null) {
        const targetClaimId = claimId || selectedActivityId;
        
        if (!targetClaimId) {
            console.log('No activity selected, skipping crop fetch');
            cropsList = [];
            return;
        }
        
        $.ajax({
            url: apiEndpoints.cropList,
            method: 'GET',
            data: {
                value: 'get_additional_fields',
                YearId: fYearId,
                ComId: companyId,
                empid: empId,
                ClaimId: targetClaimId,
                tokenid: tokenId,
            },
            success: function(response) {
                console.log('Crops List Response:', response);
                if (response.code === 200 && response.data && response.data.crops) {
                    cropsList = response.data.crops.map(crop => ({
                        id: crop.id,
                        name: crop.crop_name
                    }));
                    console.log('Crops loaded:', cropsList.length, 'crops');
                    updateCropSelect();
                } else {
                    console.error('No crops found in API response');
                    cropsList = [];
                    updateCropSelect();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching crops:', error);
                cropsList = [];
                updateCropSelect();
            }
        });
    }

    function fetchVarieties(cropIds, callback) {
        if (!cropIds || cropIds.length === 0) {
            callback([]);
            return;
        }

        $.ajax({
            url: apiEndpoints.varieties,
            method: 'GET',
            data: {
                value: 'get_varieties',
                YearId: fYearId,
                ComId: companyId,
                empid: empId,
                crop_ids: cropIds.join(','),
                tokenid: tokenId,
            },
            success: function(response) {
                if (response.code === 200 && response.data && response.data.varieties) {
                    callback(response.data.varieties);
                } else {
                    callback([]);
                }
            },
            error: function() {
                callback([]);
            }
        });
    }

    // ==========================================
    // RENDER FUNCTIONS
    // ==========================================
    function renderEmployeeTable() {
        let html = '';
        
        if (!teamMembers || teamMembers.length === 0) {
            html = '<tr><td colspan="5" class="text-center">No team members available</td></tr>';
        } else {
            teamMembers.forEach((member, index) => {
                const empId = member.EmployeeID || member.employee_id;
                const empCode = member.EmpCode || member.emp_code;
                const fullName = member.FullName || member.full_name;
                const headQuarter = member.HeadQuarterName || member.head_quarter_name || '-';
                
                if (empId && empCode && fullName) {
                    html += `
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input member-checkbox" 
                                       data-id="${empId}" 
                                       data-code="${empCode}" 
                                       data-name="${fullName}"
                                       data-hq="${headQuarter}">
                            </td>
                            <td><strong>${empCode}</strong></td>
                            <td>${fullName}</td>
                            <td>${headQuarter}</td>
                            <td>
                                <input type="number" class="form-control form-control-sm amount-input" 
                                       placeholder="0.00" aria-label="0.00" min="0" max="999999" step="0.01" 
                                       oninput="validateAmountInput(this)"
                                       data-emp-id="${empId}" disabled>
                            </td>
                        </tr>
                    `;
                }
            });
        }
        
        $('#employeeTableBody').html(html);
        $('#employeeTableSection').show();
    }

    function populateActivityTypesDropdown() {
        let options = '<option value="">Select Activity Type</option>';
        
        if (activityTypes && activityTypes.length > 0) {
            activityTypes.forEach(type => {
                options += `<option value="${type.id}">${type.type_name}</option>`;
            });
        } else {
            options += '<option value="">Loading activity types...</option>';
        }
        
        $('#commonActivityType').html(options);
    }

    function updateCropSelect() {
        const cropSelect = $('#commonCrop');
        const currentValues = cropSelect.val() || [];
        let cropOptions = '';
        
        if (cropsList.length > 0) {
            cropsList.forEach(crop => {
                cropOptions += `<option value="${crop.id}">${crop.name}</option>`;
            });
        } else {
            cropOptions = '<option value="">No crops available for selected activity</option>';
        }
        
        cropSelect.html(cropOptions);
        
        if (cropsList.length > 0) {
            cropSelect.val(currentValues);
        }
        
        // Reinitialize Select2
        if (cropSelect.hasClass('select2-hidden-accessible')) {
            cropSelect.select2('destroy');
        }
        cropSelect.select2({
            theme: 'bootstrap-5',
            placeholder: cropsList.length > 0 ? 'Select crops' : 'No crops available',
            allowClear: true,
            closeOnSelect: false,
            disabled: cropsList.length === 0
        });
    }

    // ==========================================
    // EVENT HANDLERS
    // ==========================================
    $(document).on('change', 'input[name="requestType"]', function() {
        const requestType = $(this).val();
        hideMessage();
        
        if (requestType) {
            let availableForRequestType = [];
            if (requestType === 'self') {
                availableForRequestType = availableAdvanceTypes.filter(type => type.code === 'Expense' || type.code === 'Other');
            } else if (requestType === 'team') {
                availableForRequestType = availableAdvanceTypes.filter(type => type.code === 'Activity');
            }
            
            if (availableForRequestType.length === 0) {
                let message = requestType === 'self' 
                    ? 'You do not have access to Expense or Other advance types for self requests.'
                    : 'You do not have access to Activity advance type for team requests.';
                showMessage(message, 'warning');
                return;
            }
            
            $('#commonFieldsSection').show();
            
            if (requestType === 'self') {
                $('#activityNameSection').hide();
                $('#activityDetailsSection').hide();
                $('#simplePurposeSection').show();
                $('#selfAmountSection').hide();
                $('#employeeTableSection').hide();
            } else if (requestType === 'team') {
                $('#activityNameSection').show();
                $('#activityDetailsSection').show();
                $('#simplePurposeSection').hide();
                $('#selfAmountSection').hide();
                $('#employeeTableSection').show();
                fetchTeamMembers();
                fetchActivityTypes();
            }
            
            populateAdvanceTypeDropdown(requestType);
            $('#activityName').val('');
            $('#month').val('');
            $('#selfAmount').val('');
            $('#submitButtonSection').hide();
            
        } else {
            $('#commonFieldsSection').hide();
            $('#employeeTableSection').hide();
            $('#selfAmountSection').hide();
            $('#submitButtonSection').hide();
            $('#advanceType').closest('.col-md-3').show();
        }
    });
    
    $(document).on('change', '#advanceType', function() {
        const selectedType = $(this).val();
        const requestType = $('input[name="requestType"]:checked').val();
        selectedAdvanceType = selectedType;
        
        if (selectedType) {
            if (selectedType === 'Activity' && requestType === 'team') {
                $('#activityNameSection').show();
                $('#activityDetailsSection').show();
                $('#simplePurposeSection').hide();
                $('#selfAmountSection').hide();
                fetchActivityList();
                
                // Initialize Select2
                setTimeout(function() {
                    $('#commonCrop').select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Select crops',
                        allowClear: true,
                        closeOnSelect: false
                    });

                    $('#commonVariety').select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Select varieties',
                        allowClear: true,
                        closeOnSelect: false
                    });
                }, 100);
            } else {
                $('#activityNameSection').hide();
                $('#activityDetailsSection').hide();
                $('#simplePurposeSection').show();
                $('#selfAmountSection').hide();
                $('#activityName').val('');
            }
            
            populateMonthDropdown(selectedType);
            
            if (requestType === 'team') {
                $('#employeeTableSection').show();
            } else if (requestType === 'self') {
                $('#employeeTableSection').hide();
            }
            
        } else {
            $('#activityNameSection').hide();
            $('#activityDetailsSection').hide();
            $('#simplePurposeSection').hide();
            $('#selfAmountSection').hide();
            $('#month').html('<option value="">Select advance type first</option>');
            $('#submitButtonSection').hide();
        }
        
        updateSubmitButtonVisibility();
    });

    $(document).on('change', '#month', function() {
        selectedMonth = $(this).val();
        
        console.log('Month changed to:', selectedMonth);
        
        const requestType = $('input[name="requestType"]:checked').val();
        
        // Show amount input section for self requests after month selection
        if (requestType === 'self' && selectedMonth) {
            $('#selfAmountSection').show();
            console.log('Self amount section shown after month selection');
        }
        
        // For Expense advance, auto-fill amount and set max limit
        if (selectedAdvanceType === 'Expense') {
            const maxAmount = getMaxAmountForSelectedMonth();
            console.log('Max amount for selected month:', maxAmount);
            
            if (maxAmount > 0) {
                // Auto-fill the amount input with claim_amount
                $('#selfAmount').val(maxAmount.toFixed(2));
                
                // Set max attribute on amount input
                $('#selfAmount').attr('max', maxAmount);
                
                console.log('Auto-filled amount input with:', maxAmount);
            } else {
                // Clear amount if no claim amount available
                $('#selfAmount').val('');
                $('#selfAmount').removeAttr('max');
            }
        }
        
        updateSubmitButtonVisibility();
    });

    $(document).on('change', '#activityName', function() {
        selectedActivityId = $(this).val();
        
        if (selectedActivityId) {
            // Reset crops and varieties
            cropsList = [];
            $('#commonCrop').html('<option value="">Loading crops...</option>').prop('disabled', true);
            $('#commonVariety').html('<option value="">Select crops first</option>').prop('disabled', true);
            
            // Fetch crops for selected activity
            fetchCropsList();
            
            // Populate activity types dropdown
            populateActivityTypesDropdown();
        } else {
            cropsList = [];
            $('#commonCrop').html('<option value="">Select activity first</option>').prop('disabled', true);
            $('#commonVariety').html('<option value="">Select crops first</option>').prop('disabled', true);
        }
        
        updateSubmitButtonVisibility();
    });

    $(document).on('change', '#commonCrop', function() {
        const selectedCrops = $(this).val() || [];
        
        if (selectedCrops.length > 0) {
            // Enable and fetch varieties for selected crops
            fetchVarieties(selectedCrops, function(varieties) {
                let varietyOptions = '<option value="">Select varieties</option>';
                
                if (varieties.length > 0) {
                    varieties.forEach(variety => {
                        varietyOptions += `<option value="${variety.id}">${variety.variety_name}</option>`;
                    });
                } else {
                    varietyOptions = '<option value="">No varieties found for selected crops</option>';
                }
                
                const varietySelect = $('#commonVariety');
                varietySelect.html(varietyOptions).prop('disabled', false);
                
                // Reinitialize Select2 for varieties
                if (varietySelect.hasClass('select2-hidden-accessible')) {
                    varietySelect.select2('destroy');
                }
                varietySelect.select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Select varieties',
                    allowClear: true,
                    closeOnSelect: false
                });
            });
        } else {
            $('#commonVariety').html('<option value="">Select crops first</option>').prop('disabled', true);
        }
        
        updateSubmitButtonVisibility();
    });

    // Character counter for textareas
    $(document).on('input', 'textarea[maxlength]', function() {
        const length = $(this).val().length;
        const maxLength = $(this).attr('maxlength');
        const counter = $(this).siblings('.char-counter');
        
        if (counter.length) {
            counter.text(`${length}/${maxLength}`);
            
            if (length > maxLength * 0.8) {
                counter.css('color', '#ffc107');
            } else {
                counter.css('color', '#6c757d');
            }
        }
    });

    // Select all checkbox
    $(document).on('change', '#selectAll', function() {
        const isChecked = $(this).prop('checked');
        
        $('.member-checkbox').prop('checked', isChecked).trigger('change');
    });

    // Individual member checkbox
    $(document).on('change', '.member-checkbox', function() {
        const isChecked = $(this).prop('checked');
        const amountInput = $(this).closest('tr').find('.amount-input');
        
        amountInput.prop('disabled', !isChecked);
        
        if (!isChecked) {
            amountInput.val('');
        } else {
            amountInput.focus();
        }
        
        // Update select all checkbox state
        const totalCheckboxes = $('.member-checkbox').length;
        const checkedCheckboxes = $('.member-checkbox:checked').length;
        
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
        
        updateSubmitButtonVisibility();
    });

    // Amount input changes for team members
    $(document).on('input', '.amount-input', function() {
        validateAmountInput(this);
        updateSubmitButtonVisibility();
    });

    // Self amount input
    $(document).on('input', '#selfAmount', function() {
        validateAmountInput(this);
        updateSubmitButtonVisibility();
    });

    // ==========================================
    // FORM SUBMISSION (Using External API)
    // ==========================================
    $(document).on('click', '#submitBtn', function() {
        const requestType = $('input[name="requestType"]:checked').val();
        const advanceType = $('#advanceType').val();
        const month = $('#month').val();
        
        if (!requestType || !advanceType || !month) {
            showMessage('Please complete all required fields', 'warning');
            return;
        }

        // Validate based on request type
        if (requestType === 'self') {
            const amount = $('#selfAmount').val();
            const purpose = $('#simplePurpose').val();
            
            if (!amount || parseFloat(amount) <= 0) {
                showMessage('Please enter a valid amount', 'warning');
                $('#selfAmount').focus();
                return;
            }
            
            if (!purpose || purpose.trim().length === 0) {
                showMessage('Please enter purpose for the advance', 'warning');
                $('#simplePurpose').focus();
                return;
            }
            
            submitSelfRequest();
            
        } else if (requestType === 'team') {
            const selectedMembers = $('.member-checkbox:checked');
            
            if (selectedMembers.length === 0) {
                showMessage('Please select at least one team member', 'warning');
                return;
            }

            // Validate all selected members have valid amounts
            let allAmountsValid = true;
            selectedMembers.each(function() {
                const amountInput = $(this).closest('tr').find('.amount-input');
                const amount = amountInput.val();
                
                if (!amount || parseFloat(amount) <= 0) {
                    allAmountsValid = false;
                    amountInput.addClass('is-invalid');
                } else {
                    amountInput.removeClass('is-invalid');
                }
            });
            
            if (!allAmountsValid) {
                showMessage('Please enter valid amounts for all selected team members', 'warning');
                return;
            }

            // Additional validation for Activity advance
            if (advanceType === 'Activity') {
                const activityId = $('#activityName').val();
                const selectedCrops = $('#commonCrop').val() || [];
                const selectedVarieties = $('#commonVariety').val() || [];
                const purpose = $('#commonPurpose').val();
                
                if (!activityId) {
                    showMessage('Please select an activity', 'warning');
                    $('#activityName').focus();
                    return;
                }
                
                if (selectedCrops.length === 0) {
                    showMessage('Please select at least one crop', 'warning');
                    $('#commonCrop').select2('open');
                    return;
                }
                
                if (selectedVarieties.length === 0) {
                    showMessage('Please select at least one variety', 'warning');
                    $('#commonVariety').select2('open');
                    return;
                }
                
                if (!purpose || purpose.trim().length === 0) {
                    showMessage('Please enter purpose for the advance', 'warning');
                    $('#commonPurpose').focus();
                    return;
                }
            }

            submitTeamRequest();
        }
    });

    // ADV-WEB-01 (13 Nov 2026): the self request posted the old mobile fields (value=submit_advance_request, ...)
    // to /api/advance/draft, which takes {request, employees[]}: every web self request failed with 422.
    // It now sends the same structure as the team request, for the logged-in employee.
    function submitSelfRequest() {
        const selectedAdvanceType = $('#advanceType').val();
        const amount = parseFloat($('#selfAmount').val());
        const purpose = ($('#simplePurpose').val() || '').trim();
        if (!$('#month').val()) { showMessage('Please select month', 'warning'); return; }
        if (!amount || amount <= 0) { showMessage('Please enter a valid amount', 'warning'); return; }
        if (!purpose) { showMessage('Please enter purpose', 'warning'); return; }
        const fx = xeasyFx.current();
        if (fx.currency !== 'INR' && !(fx.rate > 0)) { showMessage('No ' + fx.currency + ' rate available: enter the rate', 'warning'); return; }
        const inrAmount = fx.currency === 'INR' ? amount : Math.round(amount * fx.rate * 100) / 100;
        if (inrAmount > 999999) { showMessage('Amount cannot exceed ₹999,999', 'warning'); return; }

        const payload = {
            user_id: empId,
            request: {
                month: parseInt($('#month').val()),
                year_id: fYearId,
                req_app_submit_id: parseInt(empId),
                advance_type_id: selectedAdvanceType === 'Activity' ? 1 : selectedAdvanceType === 'Expense' ? 2 : selectedAdvanceType === 'Other' ? 3 : null
            },
            employees: [{
                employee_id: parseInt(empId),
                activities: [{
                    activity_type_id: null,
                    activity_crops_id: [],
                    activity_variety_id: [],
                    amount: inrAmount,
                    ...xeasyFx.fields(amount),
                    purpose: purpose,
                    advance_type: selectedAdvanceType
                }]
            }]
        };

        showLoading();
        $.ajax({
            url: apiEndpoints.submitRequest,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function(response) {
                hideLoading();
                if (response.code === 200 || response.success === true) {
                    showMessage('Advance request submitted successfully!', 'success');
                    setTimeout(() => { window.location.href = '/advance-requests'; }, 1500);
                } else {
                    showMessage(response.message || 'Failed to submit advance request', 'danger');
                }
            },
            error: function(xhr) {
                hideLoading();
                const r = xhr.responseJSON || {};
                showMessage(r.message || 'Error submitting advance request. Please try again.', 'danger');
            }
        });
    }

function submitTeamRequest() {

    hideMessage();

    const selectedAdvanceType = $('#advanceType').val();
    const selectedMonth = $('#month').val();
    const selectedActivityId = $('#activityName').val();

    if (!selectedAdvanceType) {
        showMessage('Please select advance type', 'warning');
        return;
    }

    if (!selectedMonth) {
        showMessage('Please select month', 'warning');
        return;
    }

    const checkedMembers = $('.member-checkbox:checked');

    if (checkedMembers.length === 0) {
        showMessage('Please select at least one team member', 'warning');
        return;
    }

    // Get purpose
    let commonPurpose = '';
    if (selectedAdvanceType === 'Activity') {
        commonPurpose = $('#commonPurpose').val();
    } else {
        commonPurpose = $('#simplePurpose').val();
    }

    if (!commonPurpose || !commonPurpose.trim()) {
        showMessage('Please enter purpose', 'warning');
        return;
    }

    // Build base payload
    const payload = {
        user_id: empId,
        request: {
            month: selectedMonth,
            year_id: fYearId, // ADV-WEB-01: required by /advance/draft; was missing, so team requests failed with 422
            req_app_submit_id: empId
        },
        employees: []
    };

    // Set advance_type_id
    if (selectedAdvanceType === 'Activity') {
        payload.request.advance_type_id = 1;
    } else if (selectedAdvanceType === 'Expense') {
        payload.request.advance_type_id = 2;
    } else if (selectedAdvanceType === 'Other') {
        payload.request.advance_type_id = 3;
    }

    // Add activity_name_id only if Activity
    if (selectedAdvanceType === 'Activity' && selectedActivityId) {
        payload.request.activity_name_id = parseInt(selectedActivityId);
    }

    let isValid = true;
    let errorMessage = '';

    // Activity common data
    const activityType = $('#commonActivityType').val();
    const crops = $('#commonCrop').val() || [];
    const varieties = $('#commonVariety').val() || [];

    checkedMembers.each(function () {

        const memberId = $(this).data('id');
        const row = $(this).closest('tr');
        const amount = parseFloat(row.find('.amount-input').val());

        if (!amount || amount <= 0) {
            isValid = false;
            errorMessage = 'Please enter valid amount for all selected members';
            return false;
        }

        // FX-53: with a foreign currency the entered amount is in that currency; INR = amount x rate
        const fx = xeasyFx.current();
        if (fx.currency !== 'INR' && !(fx.rate > 0)) {
            isValid = false;
            errorMessage = 'No ' + fx.currency + ' rate available: enter the rate';
            return false;
        }
        const inrAmount = fx.currency === 'INR' ? amount : Math.round(amount * fx.rate * 100) / 100;
        if (inrAmount > 999999) {
            isValid = false;
            errorMessage = 'Amount cannot exceed ₹999,999' + (fx.currency !== 'INR' ? ' (₹' + inrAmount.toLocaleString() + ' at ' + fx.rate + ')' : '');
            return false;
        }

        const employeeData = {
            employee_id: memberId,
            activities: []
        };

        let activity = {};

        if (selectedAdvanceType === 'Activity') {

            if (!activityType) {
                isValid = false;
                errorMessage = 'Please select activity type';
                return false;
            }

            if (crops.length === 0) {
                isValid = false;
                errorMessage = 'Crops are required';
                return false;
            }

            if (varieties.length === 0) {
                isValid = false;
                errorMessage = 'Varieties are required';
                return false;
            }

            activity = {
                activity_type_id: parseInt(activityType),
                activity_crops_id: crops.map(c => parseInt(c)),
                activity_variety_id: varieties.map(v => parseInt(v)),
                amount: inrAmount,
                ...xeasyFx.fields(amount),
                purpose: commonPurpose.trim()
            };

        } else {

            activity = {
                activity_type_id: null,
                activity_crops_id: [],
                activity_variety_id: [],
                amount: inrAmount,
                ...xeasyFx.fields(amount),
                purpose: commonPurpose.trim(),
                advance_type: selectedAdvanceType
            };
        }

        employeeData.activities.push(activity);
        payload.employees.push(employeeData);

    });

    if (!isValid) {
        showMessage(errorMessage, 'warning');
        return;
    }

    if (payload.employees.length === 0) {
        showMessage('No valid data to submit', 'warning');
        return;
    }

    if (!confirm(`Are you ready to submit your ${selectedAdvanceType.toLowerCase()} advance request?`)) {
        return;
    }

    console.log('Final Payload:', JSON.stringify(payload, null, 2));

    showLoading();

    $.ajax({
        url: apiEndpoints.submitRequest,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function (response) {
            hideLoading();

            if (response.code === 200 || response.success === true) {
                showMessage('Advance request submitted successfully!', 'success');
                setTimeout(() => {
                    window.location.href = '/advance-requests';
                }, 1500);
            } else {
                showMessage(response.message || 'Failed to submit advance request', 'danger');
            }
        },
        error: function (xhr) {
            hideLoading();
            console.error(xhr.responseText);
            showMessage('Error submitting advance request', 'danger');
        }
    });
}

    // ==========================================
    // FX-53: currency picker
    // ==========================================
    const xeasyFx = (function () {
        let rate = 0, rateDate = '', manual = false;
        const sel = () => ($('#advCurrency').val() || 'INR');
        function label(cur) {
            $('label[for="selfAmount"]').text('Amount (' + (cur === 'INR' ? '₹' : cur) + ')');
            $('th').filter(function () { return /^Amount \(/.test($(this).text().trim()); }).text('Amount (' + (cur === 'INR' ? '₹' : cur) + ')');
        }
        function refresh() {
            const cur = sel();
            label(cur);
            rate = 0; rateDate = ''; manual = false;
            if (cur === 'INR') { $('#fxRateBox').hide(); return; }
            $('#fxRateBox').show(); $('#fxRateInfo').removeClass('text-danger').text('Getting the ' + cur + ' rate...'); $('#fxManualRateGroup').hide();
            $.get(API_LOCAL_BASE_URL + '/advance/fx/quote', { currency: cur, fx_amount: 1 })
                .done(function (r) {
                    if (r && r.success) {
                        rate = parseFloat(r.data.fx_rate) || 0; rateDate = r.data.fx_rate_date || '';
                        $('#fxRateInfo').text('1 ' + cur + ' = ₹' + rate + ' (Finance rate of ' + rateDate + '). Enter amounts in ' + cur + '.');
                    }
                })
                .fail(function (x) {
                    manual = true;
                    $('#fxRateInfo').addClass('text-danger').text(((x.responseJSON || {}).message) || ('No ' + cur + ' rate set by Finance; enter the rate.'));
                    $('#fxManualRateGroup').show();
                });
        }
        $(document).on('change', '#advCurrency', refresh);
        $(document).on('input', '#fxManualRate', function () { rate = parseFloat($(this).val()) || 0; });
        return {
            current: function () { return { currency: sel(), rate: sel() === 'INR' ? 1 : rate }; },
            fields: function (fxAmount) {
                const cur = sel();
                if (cur === 'INR') { return {}; }
                const f = { currency: cur, fx_amount: fxAmount };
                if (manual && rate > 0) { f.fx_rate = rate; }
                return f;
            }
        };
    })();

    // ==========================================
    // INITIALIZATION
    // ==========================================
    function initializePage() {
        console.log('Initializing advance request page...');
        console.log('Auth Data:', { empId, empName, empCode, department, companyId, fYearId, userId });
        
        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            placeholder: $(this).data('placeholder'),
            allowClear: true,
            closeOnSelect: false
        });
        
        // Check advance access permissions
        checkAdvanceAccess();
        
        // Set up global error handling
        $(document).ajaxError(function(event, xhr, settings, error) {
            console.error('Ajax Error:', settings.url, error);
            hideLoading();
            
            if (xhr.status === 0) {
                showMessage('Network error. Please check your internet connection.', 'danger');
            } else if (xhr.status === 401) {
                showMessage('Session expired. Please login again.', 'danger');
            } else if (xhr.status === 403) {
                showMessage('Access denied. Please contact administrator.', 'danger');
            } else if (xhr.status === 500) {
                showMessage('Server error. Please try again later.', 'danger');
            }
        });
    }

    // Start initialization
    initializePage();
});
</script>
@endpush