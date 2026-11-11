@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1">Submit Advance Request (Admin)</h4>
                    </div>
                    <div class="card-body">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#regularAdvance" role="tab">
                                    Regular Advance
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#autoAdvance" role="tab">
                                    Auto Advance (Manual)
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Regular Advance Tab -->
                            <div class="tab-pane active" id="regularAdvance" role="tabpanel">
                        <form id="adminAdvanceRequestForm">
                            @csrf
                            
                            <!-- Employee Selection -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="department_id" name="department_id">
                                        <option value="">Select Department</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Employee <span class="text-danger">*</span></label>
                                    <select class="form-select" id="employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                    </select>
                                    <div class="invalid-feedback">Please select an employee</div>
                                </div>
                            </div>

                            <!-- Request Details -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Advance Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="advance_type_id" name="advance_type_id" required>
                                        <option value="">Select Advance Type</option>
                                    </select>
                                    <div class="invalid-feedback">Please select advance type</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Activity Name <span class="text-danger">*</span></label>
                                    <select class="form-select" id="activity_name_id" name="activity_name_id" required>
                                        <option value="">Select Activity Name</option>
                                    </select>
                                    <div class="invalid-feedback">Please select activity name</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Month <span class="text-danger">*</span></label>
                                    <select class="form-select" id="month" name="month" required>
                                        <option value="">Select Month</option>
                                        <option value="1">January</option>
                                        <option value="2">February</option>
                                        <option value="3">March</option>
                                        <option value="4">April</option>
                                        <option value="5">May</option>
                                        <option value="6">June</option>
                                        <option value="7">July</option>
                                        <option value="8">August</option>
                                        <option value="9">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                    </select>
                                    <div class="invalid-feedback">Please select month</div>
                                </div>
                            </div>

                            <!-- Activities Section -->
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Activities</h5>
                                    <button type="button" class="btn btn-sm btn-primary" id="addActivityBtn">
                                        <i class="ri-add-line"></i> Add Activity
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="activitiesContainer">
                                        <!-- Activities will be added here dynamically -->
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="ri-check-line"></i> Submit Request
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                        <i class="ri-arrow-left-line"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                            </div>

                            <!-- Auto Advance Tab -->
                            <div class="tab-pane" id="autoAdvance" role="tabpanel">
                                <form id="autoAdvanceForm">
                                    @csrf
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Department <span class="text-danger">*</span></label>
                                            <select class="form-select" id="auto_department_id" name="department_id" required>
                                                <option value="">Select Department</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                                            <select class="form-select" id="auto_employee_id" name="employee_id" required>
                                                <option value="">Select Employee</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Month <span class="text-danger">*</span></label>
                                            <select class="form-select" id="auto_month" name="month" required>
                                                <option value="">Select Month</option>
                                                <option value="1">January</option>
                                                <option value="2">February</option>
                                                <option value="3">March</option>
                                                <option value="4">April</option>
                                                <option value="5">May</option>
                                                <option value="6">June</option>
                                                <option value="7">July</option>
                                                <option value="8">August</option>
                                                <option value="9">September</option>
                                                <option value="10">October</option>
                                                <option value="11">November</option>
                                                <option value="12">December</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="auto_amount" name="amount" 
                                                   min="1" step="1" required placeholder="Enter amount" aria-label="Enter amount">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-success" id="autoSubmitBtn">
                                                <i class="ri-check-line"></i> Submit Auto Advance
                                            </button>
                                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                                <i class="ri-arrow-left-line"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Template -->
<template id="activityTemplate">
    <div class="activity-item border rounded p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Activity <span class="activity-number"></span></h6>
            <button type="button" class="btn btn-sm btn-danger remove-activity">
                <i class="ri-delete-bin-line"></i> Remove
            </button>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Activity Type <span class="text-danger">*</span></label>
                <select class="form-select activity-type" name="activities[][activity_type_id]" required>
                    <option value="">Select Activity Type</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Amount <span class="text-danger">*</span></label>
                <input type="number" class="form-control activity-amount" name="activities[][amount]" 
                       min="0" step="0.01" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Crop</label>
                <select class="form-select activity-crop" name="activities[][activity_crops_id]" multiple>
                    <option value="">Select Crop(s)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Variety</label>
                <select class="form-select activity-variety" name="activities[][activity_variety_id]" multiple>
                    <option value="">Select Variety(ies)</option>
                </select>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Purpose <span class="text-danger">*</span></label>
                <textarea class="form-control activity-purpose" name="activities[][purpose]" 
                          rows="2" required></textarea>
            </div>
        </div>
    </div>
</template>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let activityCount = 0;
    let departments = [];
    let employees = [];
    let advanceTypes = [];
    let activityNames = [];
    let activityTypes = [];
    let crops = [];
    let varieties = [];

    // Load only essential data on page load
    loadDepartments();
    loadAdvanceTypes();
    loadActivityNames();
    
    // Initialize Select2 for month dropdown
    $('#month').select2({
        placeholder: 'Select Month',
        allowClear: true,
        width: '100%'
    });

    // Initialize Auto Advance form
    initAutoAdvanceForm();

    // Load departments
    function loadDepartments() {
        return $.ajax({
            url: '{{ route("advance.admin.departments") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    departments = response.data;
                    const select = $('#department_id');
                    select.empty().append('<option value="">Select Department</option>');
                    response.data.forEach(dept => {
                        select.append(`<option value="${dept.id}">${dept.department_name}</option>`);
                    });
                    // Initialize Select2
                    select.select2({
                        placeholder: 'Select Department',
                        allowClear: true,
                        width: '100%'
                    });
                }
            },
            error: function(xhr) {
                showError('Failed to load departments: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    }

    // Load employees based on department
    $('#department_id').on('change', function() {
        const departmentId = $(this).val();
        const employeeSelect = $('#employee_id');
        
        // Destroy Select2 if already initialized
        if (employeeSelect.hasClass('select2-hidden-accessible')) {
            employeeSelect.select2('destroy');
        }
        
        employeeSelect.empty().append('<option value="">Loading...</option>');
        
        $.ajax({
            url: '{{ route("advance.admin.employees") }}',
            method: 'GET',
            data: { department_id: departmentId },
            success: function(response) {
                if (response.success) {
                    employees = response.data;
                    employeeSelect.empty().append('<option value="">Select Employee</option>');
                    response.data.forEach(emp => {
                        employeeSelect.append(
                            `<option value="${emp.EmployeeID}">
                                ${emp.EmployeeName} (${emp.EmpCode}) - ${emp.Designation}
                            </option>`
                        );
                    });
                    // Initialize Select2
                    employeeSelect.select2({
                        placeholder: 'Select Employee',
                        allowClear: true,
                        width: '100%'
                    });
                }
            },
            error: function(xhr) {
                employeeSelect.empty().append('<option value="">Select Employee</option>');
                showError('Failed to load employees');
            }
        });
    });

    // Load advance types
    function loadAdvanceTypes() {
        return $.ajax({
            url: '{{ route("advance.admin.advance-types") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    advanceTypes = response.data;
                    const select = $('#advance_type_id');
                    select.empty().append('<option value="">Select Advance Type</option>');
                    response.data.forEach(type => {
                        select.append(`<option value="${type.id}">${type.type_name}</option>`);
                    });
                    // Initialize Select2
                    select.select2({
                        placeholder: 'Select Advance Type',
                        allowClear: true,
                        width: '100%'
                    });
                }
            },
            error: function(xhr) {
                showError('Failed to load advance types: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    }

    // Load activity names
    function loadActivityNames() {
        return $.ajax({
            url: '{{ route("advance.admin.activity-names") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    activityNames = response.data;
                    const select = $('#activity_name_id');
                    select.empty().append('<option value="">Select Activity Name</option>');
                    response.data.forEach(activity => {
                        select.append(`<option value="${activity.id}">${activity.name}</option>`);
                    });
                    // Initialize Select2
                    select.select2({
                        placeholder: 'Select Activity Name',
                        allowClear: true,
                        width: '100%'
                    });
                }
            },
            error: function(xhr) {
                showError('Failed to load activity names: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    }

    // Load activity types
    function loadActivityTypes() {
        return $.ajax({
            url: '{{ route("advance.admin.activity-types") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    activityTypes = response.data;
                }
            },
            error: function(xhr) {
                showError('Failed to load activity types: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    }

    // Load crops
    function loadCrops() {
        return $.ajax({
            url: '{{ route("advance.admin.crops") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    crops = response.data;
                }
            },
            error: function(xhr) {
                showError('Failed to load crops: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    }

    // Load varieties based on selected crops
    function loadVarieties(cropIds, varietySelect) {
        return $.ajax({
            url: '{{ route("advance.admin.varieties") }}',
            method: 'GET',
            data: { crop_ids: cropIds },
            success: function(response) {
                if (response.success) {
                    varietySelect.empty().append('<option value="">Select Variety</option>');
                    response.data.forEach(variety => {
                        varietySelect.append(`<option value="${variety.id}">${variety.variety_name}</option>`);
                    });
                }
            }
        });
    }

    // Add activity
    $('#addActivityBtn').on('click', function() {
        // Load activity types and crops on first activity add
        if (activityCount === 0) {
            // Show loading state
            $(this).prop('disabled', true).html('<i class="ri-loader-4-line"></i> Loading...');
            
            // Load activity types and crops, then add activity
            $.when(loadActivityTypes(), loadCrops()).done(() => {
                $(this).prop('disabled', false).html('<i class="ri-add-line"></i> Add Activity');
                addActivity();
            }).fail(() => {
                $(this).prop('disabled', false).html('<i class="ri-add-line"></i> Add Activity');
                showError('Failed to load activity data');
            });
        } else {
            addActivity();
        }
    });

    function addActivity() {
        activityCount++;
        const template = document.getElementById('activityTemplate');
        const clone = template.content.cloneNode(true);
        
        // Update activity number
        clone.querySelector('.activity-number').textContent = activityCount;
        
        // Populate activity types
        const activityTypeSelect = clone.querySelector('.activity-type');
        if (activityTypes && activityTypes.length > 0) {
            activityTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.type_name;
                activityTypeSelect.appendChild(option);
            });
        }
        
        // Populate crops
        const cropSelect = clone.querySelector('.activity-crop');
        if (crops && crops.length > 0) {
            crops.forEach(crop => {
                const option = document.createElement('option');
                option.value = crop.id;
                option.textContent = crop.crop_name;
                cropSelect.appendChild(option);
            });
        }
        
        $('#activitiesContainer').append(clone);
        
        // Initialize Select2 on the newly added selects
        const $lastActivity = $('#activitiesContainer .activity-item:last');
        $lastActivity.find('.activity-type').select2({
            placeholder: 'Select Activity Type',
            allowClear: true,
            width: '100%'
        });
        $lastActivity.find('.activity-crop').select2({
            placeholder: 'Select Crop(s)',
            allowClear: true,
            width: '100%',
            multiple: true,
            closeOnSelect: false
        });
        $lastActivity.find('.activity-variety').select2({
            placeholder: 'Select Variety(ies)',
            allowClear: true,
            width: '100%',
            multiple: true,
            closeOnSelect: false
        });
        
        // Attach event handlers
        attachActivityHandlers();
    }

    // Attach event handlers to activity items
    function attachActivityHandlers() {
        // Remove activity
        $('.remove-activity').off('click').on('click', function() {
            const $activity = $(this).closest('.activity-item');
            // Destroy Select2 instances before removing
            $activity.find('select').each(function() {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
            });
            $activity.remove();
            updateActivityNumbers();
        });
        
        // Load varieties when crop changes (use select2:select and select2:unselect to avoid initialization triggers)
        $('.activity-crop').off('select2:select select2:unselect').on('select2:select select2:unselect', function(e) {
            const cropIds = $(this).val(); // This will be an array for multiple select
            const varietySelect = $(this).closest('.activity-item').find('.activity-variety');
            
            if (cropIds && cropIds.length > 0) {
                // Save current variety selection
                const currentVarieties = varietySelect.val();
                
                // Destroy Select2 before updating options
                if (varietySelect.hasClass('select2-hidden-accessible')) {
                    varietySelect.select2('destroy');
                }
                
                loadVarieties(cropIds, varietySelect).then(() => {
                    // Reinitialize Select2 after loading varieties
                    varietySelect.select2({
                        placeholder: 'Select Variety(ies)',
                        allowClear: true,
                        width: '100%',
                        multiple: true,
                        closeOnSelect: false
                    });
                    
                    // Restore previously selected varieties if they still exist
                    if (currentVarieties && currentVarieties.length > 0) {
                        const availableOptions = varietySelect.find('option').map(function() {
                            return $(this).val();
                        }).get();
                        const validSelections = currentVarieties.filter(v => availableOptions.includes(v));
                        if (validSelections.length > 0) {
                            varietySelect.val(validSelections).trigger('change');
                        }
                    }
                });
            } else {
                if (varietySelect.hasClass('select2-hidden-accessible')) {
                    varietySelect.select2('destroy');
                }
                varietySelect.empty().append('<option value="">Select Variety(ies)</option>');
                varietySelect.select2({
                    placeholder: 'Select Variety(ies)',
                    allowClear: true,
                    width: '100%',
                    multiple: true,
                    closeOnSelect: false
                });
            }
        });
    }

    // Update activity numbers
    function updateActivityNumbers() {
        $('.activity-item').each(function(index) {
            $(this).find('.activity-number').text(index + 1);
        });
        activityCount = $('.activity-item').length;
    }

    // Form submission
    $('#adminAdvanceRequestForm').on('submit', function(e) {
        e.preventDefault();
        
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }
        
        // Validate at least one activity
        if ($('.activity-item').length === 0) {
            showError('Please add at least one activity');
            return;
        }
        
        // Collect form data
        const formData = {
            employee_id: $('#employee_id').val(),
            advance_type_id: $('#advance_type_id').val(),
            activity_name_id: $('#activity_name_id').val(),
            month: $('#month').val(),
            activities: []
        };
        
        // Collect activities
        $('.activity-item').each(function() {
            const cropIds = $(this).find('.activity-crop').val(); // Array or null
            const varietyIds = $(this).find('.activity-variety').val(); // Array or null
            
            const activity = {
                activity_type_id: $(this).find('.activity-type').val(),
                amount: $(this).find('.activity-amount').val(),
                activity_crops_id: cropIds && cropIds.length > 0 ? cropIds : null,
                activity_variety_id: varietyIds && varietyIds.length > 0 ? varietyIds : null,
                purpose: $(this).find('.activity-purpose').val()
            };
            formData.activities.push(activity);
        });
        
        // Submit
        const submitBtn = $('#submitBtn');
        submitBtn.prop('disabled', true).html('<i class="ri-loader-4-line"></i> Submitting...');
        
        $.ajax({
            url: '{{ route("advance.admin.submit-request") }}',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.message);
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showError(response.message || 'Failed to submit request');
                    submitBtn.prop('disabled', false).html('<i class="ri-check-line"></i> Submit Request');
                }
            },
            error: function(xhr) {
                let message = 'Failed to submit request';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showError(message);
                submitBtn.prop('disabled', false).html('<i class="ri-check-line"></i> Submit Request');
            }
        });
    });

    // Helper functions
    function showSuccess(message) {
        $.toast({
            heading: 'Success',
            text: message,
            icon: 'success',
            position: 'top-right',
            hideAfter: 3000
        });
    }

    function showError(message) {
        $.toast({
            heading: 'Error',
            text: message,
            icon: 'error',
            position: 'top-right',
            hideAfter: 5000
        });
    }

    // Auto Advance Form Functions
    function initAutoAdvanceForm() {
        // Load departments for auto advance
        $.ajax({
            url: '{{ route("advance.admin.departments") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const select = $('#auto_department_id');
                    select.empty().append('<option value="">Select Department</option>');
                    response.data.forEach(dept => {
                        select.append(`<option value="${dept.id}">${dept.department_name}</option>`);
                    });
                    select.select2({
                        placeholder: 'Select Department',
                        allowClear: true,
                        width: '100%'
                    });
                }
            }
        });

        // Initialize month select2
        $('#auto_month').select2({
            placeholder: 'Select Month',
            allowClear: true,
            width: '100%'
        });

        // Load employees when department changes
        $('#auto_department_id').on('change', function() {
            const departmentId = $(this).val();
            const employeeSelect = $('#auto_employee_id');
            
            if (employeeSelect.hasClass('select2-hidden-accessible')) {
                employeeSelect.select2('destroy');
            }
            
            employeeSelect.empty().append('<option value="">Loading...</option>');
            
            $.ajax({
                url: '{{ route("advance.admin.employees") }}',
                method: 'GET',
                data: { department_id: departmentId },
                success: function(response) {
                    if (response.success) {
                        employeeSelect.empty().append('<option value="">Select Employee</option>');
                        response.data.forEach(emp => {
                            employeeSelect.append(
                                `<option value="${emp.EmployeeID}">
                                    ${emp.EmployeeName} (${emp.EmpCode}) - ${emp.Designation}
                                </option>`
                            );
                        });
                        employeeSelect.select2({
                            placeholder: 'Select Employee',
                            allowClear: true,
                            width: '100%'
                        });
                    }
                },
                error: function() {
                    employeeSelect.empty().append('<option value="">Select Employee</option>');
                    showError('Failed to load employees');
                }
            });
        });

        // Auto Advance form submission
        $('#autoAdvanceForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                e.stopPropagation();
                $(this).addClass('was-validated');
                return;
            }
            
            const formData = {
                department_id: $('#auto_department_id').val(),
                employee_id: $('#auto_employee_id').val(),
                month: $('#auto_month').val(),
                amount: $('#auto_amount').val()
            };
            
            const submitBtn = $('#autoSubmitBtn');
            submitBtn.prop('disabled', true).html('<i class="ri-loader-4-line"></i> Submitting...');
            
            $.ajax({
                url: '{{ route("advance.admin.submit-manual-advance") }}',
                method: 'POST',
                data: JSON.stringify(formData),
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.message);
                        $('#autoAdvanceForm')[0].reset();
                        $('#auto_department_id').val(null).trigger('change');
                        $('#auto_employee_id').val(null).trigger('change');
                        $('#auto_month').val(null).trigger('change');
                    } else {
                        showError(response.message || 'Failed to submit auto advance');
                    }
                    submitBtn.prop('disabled', false).html('<i class="ri-check-line"></i> Submit Auto Advance');
                },
                error: function(xhr) {
                    let message = 'Failed to submit auto advance';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showError(message);
                    submitBtn.prop('disabled', false).html('<i class="ri-check-line"></i> Submit Auto Advance');
                }
            });
        });
    }
});
</script>
@endpush
