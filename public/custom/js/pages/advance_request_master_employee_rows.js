$(document).ready(function () {
    let currentAdvanceTypeId = null;
    let currentDepartmentId = null;
    let currentEmployeeId = null;
    let currentDesignations = [];
    let currentEmployees = [];
    let selectedDesignationIds = [];
    let selectedEmployeeId = null;
    let currentActivities = [];
    let activityLimitsData = {};

    // Initialize Flatpickr for date inputs
    $('.effective-from-input, .effective-to-input').flatpickr({
        dateFormat: 'Y-m-d',
        placeholder: 'Select Date',
        allowInput: true,
        clickOpens: true
    });

    // Bind save button events
    bindSaveButtonEvents();
    bindDeleteButtonEvents();
    bindAddEmployeeEvents();
    bindActivityLimitsEvents();

    function bindSaveButtonEvents() {
        // Remove existing handlers to prevent duplicates
        $(document).off('click', '.save-btn');

        // Handle save button click - using event delegation
        $(document).on('click', '.save-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            currentAdvanceTypeId = $(this).data('advance-type-id');
            currentDepartmentId = $(this).data('department-id') || null;
            currentEmployeeId = $(this).data('employee-id') || null;

            // Validate required fields
            let row;
            if (currentDepartmentId) {
                // Department-based validation
                row = $(`tr[data-advance-type-id="${currentAdvanceTypeId}"][data-department-id="${currentDepartmentId}"]`);
            } else if (currentEmployeeId) {
                // Employee-based validation (individual row)
                row = $(`tr[data-advance-type-id="${currentAdvanceTypeId}"][data-employee-id="${currentEmployeeId}"]`);
            }

            const isActive = row.find('.is-active-switch').is(':checked');
            const approverLevel = row.find('.approver-level-select').val();
            const effectiveFrom = row.find('.effective-from-input').val();

            if (isActive) {
                if (currentDepartmentId) {
                    // Department-based validation
                    // Only check designations for Activity Advance (type_code = 'ACTIVITY')
                    const typeCode = row.closest('.tab-pane').attr('id'); // This will be like 'tab-1', 'tab-2', etc.
                    const designationInput = row.find('.designation-input');

                    // Only validate designations if the designation input exists (Activity type)
                    if (designationInput.length > 0) {
                        const selectedDesignationData = JSON.parse(row.find('.designation-ids').val() || '{}');

                        // Check if it's an object with at least one vertical having designations
                        let hasDesignations = false;
                        if (typeof selectedDesignationData === 'object' && selectedDesignationData !== null) {
                            // Check if any vertical has designations
                            for (const verticalId in selectedDesignationData) {
                                if (Array.isArray(selectedDesignationData[verticalId]) && selectedDesignationData[verticalId].length > 0) {
                                    hasDesignations = true;
                                    break;
                                }
                            }
                        }

                        if (!hasDesignations) {
                            showAlert('Please select at least one designation', 'error');
                            return;
                        }
                    }
                } else if (currentEmployeeId) {
                    // Employee-based validation - check request_for is selected
                    const requestForChecked = row.find('.request-for-checkbox:checked');
                    if (requestForChecked.length === 0) {
                        showAlert('Please select at least one option (Act or Exp)', 'error');
                        return;
                    }
                }

                // Skip approver level validation for Auto Advance (type 4)
                if (currentAdvanceTypeId != 4 && !approverLevel) {
                    showAlert('Please select an approver level', 'error');
                    return;
                }
                if (!effectiveFrom) {
                    showAlert('Please select an effective from date', 'error');
                    return;
                }
            }

            // Show confirmation modal
            $('#confirmSaveModal').modal('show');
        });
    }

    function bindDeleteButtonEvents() {
        $(document).off('click', '.delete-btn');

        $(document).on('click', '.delete-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            currentAdvanceTypeId = $(this).data('advance-type-id');
            currentEmployeeId = $(this).data('employee-id');

            if (confirm('Are you sure you want to delete this employee setting?')) {
                deleteEmployeeSetting();
            }
        });
    }

    function bindAddEmployeeEvents() {
        $(document).off('click', '#addEmployeeRow');

        $(document).on('click', '#addEmployeeRow', function (e) {
            e.preventDefault();
            currentAdvanceTypeId = $(this).data('advance-type-id');

            // Fetch and show employees
            fetchEmployeesForAdd();
        });
    }

    function bindActivityLimitsEvents() {
        // Handle activity limits button click
        $(document).off('click', '.activity-limits-btn');
        $(document).on('click', '.activity-limits-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            currentAdvanceTypeId = $(this).data('advance-type-id');
            currentDepartmentId = $(this).data('department-id');
            const departmentName = $(this).data('department-name');

            $('#activityLimitsDepartmentName').text(departmentName);

            // Fetch activities and existing limits
            fetchActivitiesAndLimits();
        });

        // Handle individual activity limit save
        $(document).off('click', '.save-activity-limit-btn');
        $(document).on('click', '.save-activity-limit-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const activityId = $(this).data('activity-id');
            const row = $(this).closest('tr');
            const minAmount = row.find('.activity-min-amount').val();
            const maxAmount = row.find('.activity-max-amount').val();

            if (!minAmount || !maxAmount) {
                showAlert('Please enter both min and max amounts', 'error');
                return;
            }

            // Validate 6-digit limit
            if (parseFloat(minAmount) > 999999 || parseFloat(maxAmount) > 999999) {
                showAlert('Amount cannot exceed 999,999', 'error');
                return;
            }

            if (parseFloat(maxAmount) < parseFloat(minAmount)) {
                showAlert('Maximum amount must be greater than or equal to minimum amount', 'error');
                return;
            }

            saveActivityLimit(activityId, minAmount, maxAmount, true);
        });

        // Handle save all activity limits
        $(document).off('click', '#saveAllActivityLimits');
        $('#saveAllActivityLimits').on('click', function (e) {
            e.preventDefault();

            const limitsToSave = [];
            let hasError = false;

            $('#activityLimitsTableBody tr').each(function () {
                const activityId = $(this).data('activity-id');
                const minAmount = $(this).find('.activity-min-amount').val();
                const maxAmount = $(this).find('.activity-max-amount').val();

                // Only save if both amounts are provided
                if (minAmount && maxAmount) {
                    // Validate 6-digit limit
                    if (parseFloat(minAmount) > 999999 || parseFloat(maxAmount) > 999999) {
                        showAlert('Amount cannot exceed 999,999', 'error');
                        hasError = true;
                        return false;
                    }

                    if (parseFloat(maxAmount) < parseFloat(minAmount)) {
                        showAlert(`Maximum amount must be greater than or equal to minimum amount for all activities`, 'error');
                        hasError = true;
                        return false;
                    }

                    limitsToSave.push({
                        activity_id: activityId,
                        min_amount: minAmount,
                        max_amount: maxAmount,
                        is_active: true
                    });
                }
            });

            if (hasError) return;

            if (limitsToSave.length > 0) {
                saveAllActivityLimits(limitsToSave);
            } else {
                showAlert('Please enter amounts for at least one activity', 'warning');
            }
        });
    }

    // Handle confirm save
    $('#confirmSaveBtn').on('click', function () {
        if (!currentAdvanceTypeId) return;

        let row;
        if (currentDepartmentId) {
            row = $(`tr[data-advance-type-id="${currentAdvanceTypeId}"][data-department-id="${currentDepartmentId}"]`);
        } else if (currentEmployeeId) {
            row = $(`tr[data-advance-type-id="${currentAdvanceTypeId}"][data-employee-id="${currentEmployeeId}"]`);
        }

        const isActive = row.find('.is-active-switch').is(':checked');
        const approverLevel = row.find('.approver-level-select').val();
        const effectiveFrom = row.find('.effective-from-input').val();
        const effectiveTo = row.find('.effective-to-input').val();

        // Show loading state
        showLoading(true);

        const data = {
            advance_type_id: currentAdvanceTypeId,
            approver_level: isActive ? approverLevel : null,
            is_active: isActive ? 1 : 0,
            effective_from: isActive ? effectiveFrom : null,
            effective_to: isActive ? effectiveTo : null,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        // Declare limitsData outside the if block so it's accessible in the AJAX callback
        let limitsData = null;

        if (currentDepartmentId) {
            // Department-based data
            data.department_id = currentDepartmentId;

            // Only include designation_ids if the designation input exists (Activity type)
            const designationIdsInput = row.find('.designation-ids');
            if (designationIdsInput.length > 0) {
                // Always send current value, even when inactive (to preserve data)
                const selectedDesignationData = designationIdsInput.val() || '{}';
                data.designation_ids = selectedDesignationData;
            }

            // Only include grade_ids if the grade input exists (EXPENSE type)
            const gradeIdsInput = row.find('.grade-ids');
            if (gradeIdsInput.length > 0) {
                // Always send current value, even when inactive (to preserve data)
                const selectedGradeIds = JSON.parse(gradeIdsInput.val() || '[]');
                data.grade_ids = selectedGradeIds;
            } else {
                data.grade_ids = [];
            }

            // Only include is_tour_travel if the checkbox exists (EXPENSE type)
            const tourTravelCheckbox = row.find('.tour-travel-checkbox');
            if (tourTravelCheckbox.length > 0) {
                // Always send current value, even when inactive (to preserve data)
                data.is_tour_travel = tourTravelCheckbox.is(':checked') ? 1 : 0;
            }

            // Only include allow_expense if the checkbox exists (EXPENSE type)
            const allowExpenseCheckbox = row.find('.allow-expense-checkbox');
            if (allowExpenseCheckbox.length > 0) {
                // Always send current value, even when inactive (to preserve data)
                data.allow_expense = allowExpenseCheckbox.is(':checked') ? 1 : 0;
            }

            // Only include vertical_ids if the vertical checkboxes exist (EXPENSE type)
            const verticalCheckboxes = row.find('.vertical-checkbox');
            if (verticalCheckboxes.length > 0) {
                // Always send current value, even when inactive (to preserve data)
                const selectedVerticalIds = [];
                verticalCheckboxes.filter(':checked').each(function () {
                    selectedVerticalIds.push(parseInt($(this).val()));
                });
                data.vertical_ids = selectedVerticalIds;
            } else {
                data.vertical_ids = [];
            }

            // For Auto Advance type (id = 4), include expense_percentage and management_data
            if (currentAdvanceTypeId == 4) {
                // Always send current value, even when inactive (to preserve data)
                const expensePercentage = row.find('.expense-percentage-input').val();
                data.expense_percentage = expensePercentage || null;

                // Get selected managers with their verticals from hidden input
                const selectedManagersInput = row.find('.selected-managers-data');
                if (selectedManagersInput.length > 0) {
                    const managementData = selectedManagersInput.val() || '[]';
                    data.management_data = managementData;
                }
            }

            // Store min/max amounts separately - they will be saved via storeLimits endpoint
            const minAmountInput = row.find('.min-amount-input');
            const maxAmountInput = row.find('.max-amount-input');
            if (minAmountInput.length > 0 && maxAmountInput.length > 0) {
                const minAmount = minAmountInput.val()?.trim();
                const maxAmount = maxAmountInput.val()?.trim();

                console.log('Min Amount:', minAmount, 'Max Amount:', maxAmount, 'Is Active:', isActive);

                // Always save limits data (even if empty - will be set to null)
                limitsData = {
                    advance_type_id: currentAdvanceTypeId,
                    department_id: currentDepartmentId,
                    activity_id: null,
                    min_amount: (minAmount && minAmount !== '') ? minAmount : null,
                    max_amount: (maxAmount && maxAmount !== '') ? maxAmount : null,
                    is_active: isActive ? 1 : 0,
                    _token: $('meta[name="csrf-token"]').attr('content')
                };

                console.log('Limits Data:', limitsData);
            }
        } else if (currentEmployeeId) {
            // Employee-based data
            data.employee_id = currentEmployeeId;

            // Get all selected request_for values (Act and/or Exp)
            const requestForValues = [];
            row.find('.request-for-checkbox:checked').each(function () {
                requestForValues.push($(this).val());
            });

            if (isActive && requestForValues.length > 0) {
                data.request_for = requestForValues;
            }
        }

        $.ajax({
            url: '/advance-request-master',
            method: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    // If we have limits data to save, save it now
                    if (limitsData) {
                        $.ajax({
                            url: '/advance-request-master/limits',
                            method: 'POST',
                            data: limitsData,
                            success: function (limitsResponse) {
                                showLoading(false);
                                $('#confirmSaveModal').modal('hide');

                                if (limitsResponse.success) {
                                    showAlert('Settings and limits saved successfully', 'success');
                                } else {
                                    showAlert('Settings saved but limits failed: ' + (limitsResponse.message || 'Unknown error'), 'warning');
                                }

                                // Reload the page after 2 seconds to show updated data
                                setTimeout(function () {
                                    window.location.reload();
                                }, 2000);
                            },
                            error: function (xhr) {
                                showLoading(false);
                                $('#confirmSaveModal').modal('hide');

                                let errorMessage = 'Settings saved but limits failed';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMessage += ': ' + xhr.responseJSON.message;
                                }

                                showAlert(errorMessage, 'warning');

                                // Still reload to show the settings that were saved
                                setTimeout(function () {
                                    window.location.reload();
                                }, 2000);
                            }
                        });
                    } else {
                        // No limits to save, just show success and reload
                        showLoading(false);
                        $('#confirmSaveModal').modal('hide');
                        showAlert(response.message, 'success');

                        // Reload the page after 2 seconds to show updated data
                        setTimeout(function () {
                            window.location.reload();
                        }, 2000);
                    }
                } else {
                    showLoading(false);
                    $('#confirmSaveModal').modal('hide');
                    showAlert(response.message || 'Failed to save settings', 'error');
                }
            },
            error: function (xhr) {
                showLoading(false);
                $('#confirmSaveModal').modal('hide');

                let errorMessage = 'Failed to save settings';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join(', ');
                }

                showAlert(errorMessage, 'error');
            }
        });
    });

    // Handle enable/disable switch changes
    // No logic needed - fields remain enabled and preserve their values
    $('.is-active-switch').on('change', function () {
        // Just log for debugging if needed
        console.log('Switch toggled for advance type:', $(this).data('advance-type-id'));
    });
    // Handle designation input click to open modal (only for Activity type)
    $(document).on('click', '.designation-input', function () {
        if ($(this).prop('disabled')) return;

        // Only allow designation selection for Activity type (which has designation inputs)
        const departmentId = $(this).data('department-id');
        if (!departmentId) return; // Skip if no department ID

        currentAdvanceTypeId = $(this).data('advance-type-id');
        currentDepartmentId = departmentId;
        const currentSelectedData = JSON.parse($(this).siblings('.designation-ids').val() || '{}');

        // Handle both array format and object format (vertical_id => [designation_ids or designation objects])
        selectedDesignationIds = [];
        window.designationApprovalStatus = {}; // Store approval status globally

        if (Array.isArray(currentSelectedData)) {
            // Old format: simple array of IDs - no approval status stored
            selectedDesignationIds = currentSelectedData.map(id => parseInt(id));
            // Don't set default approval status for old format
        } else if (typeof currentSelectedData === 'object' && currentSelectedData !== null) {
            // New format: object with vertical_id => array of designation objects or IDs
            Object.values(currentSelectedData).forEach(items => {
                if (Array.isArray(items)) {
                    items.forEach(item => {
                        if (typeof item === 'object' && item.id) {
                            // New format: {id: 123, approval_required: 1}
                            const designationId = parseInt(item.id);
                            const approvalStatus = parseInt(item.approval_required);
                            selectedDesignationIds.push(designationId);
                            window.designationApprovalStatus[designationId] = approvalStatus;
                        } else {
                            // Old format: just ID numbers - no approval status
                            selectedDesignationIds.push(parseInt(item));
                            // Don't set default approval status
                        }
                    });
                }
            });
        }

        // Fetch designations for this department
        fetchDesignationsForDepartment(currentDepartmentId);
    });

    // Handle designation search
    $('#designationSearch').on('input', function () {
        const searchTerm = $(this).val().toLowerCase();
        filterDesignations(searchTerm);
    });

    // Handle add employee search
    $('#addEmployeeSearch').on('input', function () {
        const searchTerm = $(this).val().toLowerCase();
        filterAddEmployees(searchTerm);
    });

    // Handle select all designations
    $('#selectAllDesignations').on('click', function () {

        const activeTab = $('.tab-pane.active');

        activeTab.find('.designation-checkbox').each(function () {

            const id = parseInt($(this).val());

            $(this).prop('checked', true);
            $(this).closest('.designation-item').addClass('table-primary');

            if (!selectedDesignationIds.includes(id)) {
                selectedDesignationIds.push(id);
            }

        });

        updateDesignationSelectionCount();
    });

    // Handle clear all designations
    $('#clearAllDesignations').on('click', function () {

        const activeTab = $('.tab-pane.active');

        activeTab.find('.designation-checkbox').each(function () {

            const id = parseInt($(this).val());

            $(this).prop('checked', false);
            $(this).closest('.designation-item').removeClass('table-primary');

            selectedDesignationIds = selectedDesignationIds.filter(d => d !== id);

        });

        updateDesignationSelectionCount();
    });

    // Handle individual designation checkbox change
    $(document).on('change', '.designation-checkbox', function () {
        const designationId = parseInt($(this).val());
        const row = $(this).closest('tr');

        if ($(this).is(':checked')) {
            if (!selectedDesignationIds.includes(designationId)) {
                selectedDesignationIds.push(designationId);
            }
            row.addClass('table-primary');
        } else {
            selectedDesignationIds = selectedDesignationIds.filter(id => parseInt(id) !== designationId);
            row.removeClass('table-primary');
        }

        // Update the select all checkbox state
        updateSelectAllCheckboxState();
        updateDesignationSelectionCount();
    });

    function updateSelectAllCheckboxState() {
        const totalCheckboxes = $('.designation-checkbox').length;
        const checkedCheckboxes = $('.designation-checkbox:checked').length;
        const selectAllCheckbox = $('#selectAllDesignationsTable');

        if (checkedCheckboxes === 0) {
            selectAllCheckbox.prop('indeterminate', false);
            selectAllCheckbox.prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            selectAllCheckbox.prop('indeterminate', false);
            selectAllCheckbox.prop('checked', true);
        } else {
            selectAllCheckbox.prop('indeterminate', true);
            selectAllCheckbox.prop('checked', false);
        }
    }

    // Handle add employee selection
    $(document).on('click', '.add-employee-item', function () {
        selectedEmployeeId = parseInt($(this).data('employee-id'));
        $('.add-employee-item').removeClass('selected');
        $(this).addClass('selected');
    });

    $('#confirmDesignationSelection').on('click', function () {
        if (!currentAdvanceTypeId || !currentDepartmentId) return;
        let selectedNames = [];
        let verticalDesignationMap = {};

        currentDesignations.forEach(vertical => {
            const selectedInVertical = vertical.designations
                .filter(d => selectedDesignationIds.includes(parseInt(d.id)));
            if (selectedInVertical.length > 0) {
                // Store designation IDs with their approval status
                const designationsWithApproval = selectedInVertical.map(d => {
                    const approvalCheckbox = $(`.approval-required-checkbox[data-designation-id="${d.id}"]`);
                    return {
                        id: parseInt(d.id),
                        approval_required: approvalCheckbox.is(':checked') ? 1 : 0
                    };
                });

                verticalDesignationMap[vertical.vertical_id] = designationsWithApproval;
                selectedNames.push(
                    ...selectedInVertical.map(d => d.designation_name)
                );
            }
        });
        const inputField = $(`.designation-input[data-advance-type-id="${currentAdvanceTypeId}"][data-department-id="${currentDepartmentId}"]`);
        const hiddenField = $(`.designation-ids[data-advance-type-id="${currentAdvanceTypeId}"][data-department-id="${currentDepartmentId}"]`);
        inputField.val(selectedNames.join(', '));
        hiddenField.val(JSON.stringify(verticalDesignationMap));
        $('#designationModal').modal('hide');

    });

    // Handle confirm add employee
    $('#confirmAddEmployee').on('click', function () {
        if (!currentAdvanceTypeId || !selectedEmployeeId) {
            showAlert('Please select an employee', 'error');
            return;
        }

        // Check if employee already exists in the table
        const existingRow = $(`.employee-settings-table[data-advance-type-id="${currentAdvanceTypeId}"] tr[data-employee-id="${selectedEmployeeId}"]`);
        if (existingRow.length > 0) {
            showAlert('This employee is already added', 'error');
            return;
        }

        // Get all selected request_for values
        const requestForValues = [];
        $('input[name="add_request_for"]:checked').each(function () {
            requestForValues.push($(this).val());
        });

        if (requestForValues.length === 0) {
            showAlert('Please select at least one option (Act or Exp)', 'error');
            return;
        }

        // Create new employee setting
        const data = {
            advance_type_id: currentAdvanceTypeId,
            employee_id: selectedEmployeeId,
            request_for: requestForValues,
            approver_level: 1, // Default
            is_active: 1,
            effective_from: new Date().toISOString().split('T')[0], // Today
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        $.ajax({
            url: '/advance-request-master',
            method: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#addEmployeeModal').modal('hide');
                    // Reload the page to show the new row
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert(response.message || 'Failed to add employee', 'error');
                }
            },
            error: function (xhr) {
                let errorMessage = 'Failed to add employee';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showAlert(errorMessage, 'error');
            }
        });
    });

    // Reset modals when closed
    $('#designationModal').on('hidden.bs.modal', function () {
        $('#designationSearch').val('');
        $('.designation-list').empty();
        $('#selectAllDesignationsTable').off('change'); // Remove event handler
        $(document).off('click', '.designation-item'); // Remove row click handler
        currentAdvanceTypeId = null;
        currentDepartmentId = null;
        currentDesignations = [];
        selectedDesignationIds = [];
    });

    $('#addEmployeeModal').on('hidden.bs.modal', function () {
        $('#addEmployeeSearch').val('');
        $('.add-employee-list').empty();
        currentAdvanceTypeId = null;
        currentEmployees = [];
        selectedEmployeeId = null;
    });

    $('#activityLimitsModal').on('hidden.bs.modal', function () {
        $('#activityLimitsTableBody').empty();
        currentAdvanceTypeId = null;
        currentDepartmentId = null;
        currentActivities = [];
        activityLimitsData = {};
    });

    // Utility functions
    function fetchDesignationsForDepartment(departmentId) {
        showModalLoading('designation', true);

        $.ajax({
            url: `/advance-request-master/designations/${departmentId}`,
            method: 'GET',
            success: function (response) {
                showModalLoading('designation', false);

                if (response.success) {
                    currentDesignations = response.verticals;
                    populateDesignationModal(response.verticals);
                    $('#designationModal').modal('show');
                } else {
                    showAlert(response.message || 'Failed to fetch designations', 'error');
                }
            },
            error: function (xhr) {
                showModalLoading('designation', false);
                showAlert('Failed to fetch designations', 'error');
            }
        });
    }

    function fetchEmployeesForAdd() {
        showModalLoading('add-employee', true);

        $.ajax({
            url: '/advance-request-master/employees',
            method: 'GET',
            success: function (response) {
                showModalLoading('add-employee', false);
                if (response.success) {
                    currentEmployees = response.employees;
                    populateAddEmployeeModal(response.employees);
                    $('#addEmployeeModal').modal('show');
                } else {
                    showAlert(response.message || 'Failed to fetch employees', 'error');
                }
            },
            error: function (xhr) {
                showModalLoading('add-employee', false);
                showAlert('Failed to fetch employees', 'error');
            }
        });
    }

    function deleteEmployeeSetting() {
        if (!currentAdvanceTypeId || !currentEmployeeId) return;

        const data = {
            advance_type_id: currentAdvanceTypeId,
            employee_id: currentEmployeeId,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        $.ajax({
            url: '/advance-request-master/employee',
            method: 'DELETE',
            data: data,
            success: function (response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    // Remove the row from the table
                    $(`tr[data-advance-type-id="${currentAdvanceTypeId}"][data-employee-id="${currentEmployeeId}"]`).remove();

                    // Check if table is empty and show message
                    const table = $(`.employee-settings-table[data-advance-type-id="${currentAdvanceTypeId}"] tbody`);
                    if (table.find('tr').length === 0) {
                        table.html('<tr><td colspan="7" class="text-center text-muted">No employees configured. Click "Add Employee" to start.</td></tr>');
                    }
                } else {
                    showAlert(response.message || 'Failed to delete employee setting', 'error');
                }
            },
            error: function (xhr) {
                let errorMessage = 'Failed to delete employee setting';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showAlert(errorMessage, 'error');
            }
        });
    }

    function populateDesignationModal(verticals) {

        const container = $('.designation-list');
        container.empty();

        if (!verticals || verticals.length === 0) {
            container.html('<p class="text-muted text-center">No designations found.</p>');
            return;
        }

        let tabHeaders = `<ul class="nav nav-tabs mb-3" id="verticalTabs" role="tablist">`;
        let tabContent = `<div class="tab-content">`;

        verticals.forEach((vertical, vIndex) => {

            const activeClass = vIndex === 0 ? 'active' : '';
            const showClass = vIndex === 0 ? 'show active' : '';

            const tabId = `vertical_${vertical.vertical_id}`;

            // Tab header
            tabHeaders += `
            <li class="nav-item">
                <button class="nav-link ${activeClass}" 
                        data-bs-toggle="tab"
                        data-bs-target="#${tabId}"
                        type="button">
                    ${vertical.vertical_name}
                </button>
            </li>
        `;

            // Table for designations
            let tableRows = '';

            vertical.designations.forEach(designation => {

                const isChecked = selectedDesignationIds.includes(parseInt(designation.id));
                const rowClass = isChecked ? 'table-primary' : '';

                // Get saved approval status - no default value
                const savedApprovalStatus = window.designationApprovalStatus ? window.designationApprovalStatus[designation.id] : undefined;
                const approvalRequired = savedApprovalStatus !== undefined
                    ? (savedApprovalStatus === 1 || savedApprovalStatus === '1' || savedApprovalStatus === true)
                    : false; // No default - unchecked by default

                tableRows += `
                <tr class="designation-item ${rowClass}" data-designation-id="${designation.id}">
                    <td>
                        <input class="form-check-input designation-checkbox"
                               type="checkbox"
                               value="${designation.id}"
                               id="designation_${designation.id}"
                               ${isChecked ? 'checked' : ''}>
                    </td>
                    <td>${designation.id}</td>
                    <td>
                        <label class="form-check-label" for="designation_${designation.id}">
                            ${designation.designation_name}
                        </label>
                    </td>
                    <td>${designation.active_grades || '-'}</td>
                    <td>${designation.designation_code || '-'}</td>
                    <td class="text-center">
                        <input class="form-check-input approval-required-checkbox"
                               type="checkbox"
                               value="1"
                               data-designation-id="${designation.id}"
                               id="approval_${designation.id}"
                               ${approvalRequired ? 'checked' : ''}>
                    </td>
                </tr>
            `;
            });

            tabContent += `
            <div class="tab-pane fade ${showClass}" id="${tabId}">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px"></th>
                                <th style="width:60px">ID</th>
                                <th>Designation</th>
                                <th>Active Grades</th>
                                <th style="width:120px">Code</th>
                                <th style="width:120px" class="text-center">Approval Not Req.</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        });

        tabHeaders += `</ul>`;
        tabContent += `</div>`;

        container.html(tabHeaders + tabContent);

        bindDesignationEvents();
    }

    function bindDesignationEvents() {

        $(document).off('change', '.designation-checkbox')
            .on('change', '.designation-checkbox', function () {

                const id = parseInt($(this).val());
                const row = $(this).closest('tr');

                if ($(this).is(':checked')) {
                    if (!selectedDesignationIds.includes(id)) {
                        selectedDesignationIds.push(id);
                    }
                    row.addClass('table-primary');
                } else {
                    selectedDesignationIds = selectedDesignationIds.filter(d => d !== id);
                    row.removeClass('table-primary');
                }

                updateDesignationSelectionCount();
            });
    }

    function populateAddEmployeeModal(employees) {
        const container = $('.add-employee-list');
        container.empty();

        if (employees.length === 0) {
            container.html('<p class="text-muted text-center">No employees found.</p>');
            return;
        }

        employees.forEach(employee => {
            const itemHtml = `
                <div class="add-employee-item p-2 border rounded mb-2" 
                     data-employee-id="${employee.id}" 
                     data-name="${employee.name.toLowerCase()}"
                     style="cursor: pointer;">
                    <strong>${employee.name}</strong>
                    ${employee.emp_code ? `<small class="text-muted d-block">(${employee.emp_code})</small>` : ''}
                </div>
            `;
            container.append(itemHtml);
        });
    }

    function fetchActivitiesAndLimits() {
        showModalLoading('activity', true);

        // Initialize currentActivities as empty array
        currentActivities = [];

        // Fetch activities first with department_id parameter
        $.ajax({
            url: '/advance-request-master/activities',
            method: 'GET',
            data: {
                department_id: currentDepartmentId
            },
            success: function (response) {
                if (response.success && response.activities) {
                    currentActivities = response.activities;

                    // Now fetch existing limits for this department
                    fetchExistingActivityLimits();
                } else {
                    showModalLoading('activity', false);
                    console.error('Invalid activities response:', response);
                    showAlert(response.message || 'Failed to fetch activities', 'error');

                    // Show modal anyway with empty activities
                    currentActivities = [];
                    populateActivityLimitsModal();
                    $('#activityLimitsModal').modal('show');
                }
            },
            error: function (xhr) {
                showModalLoading('activity', false);
                console.error('Activities fetch error:', xhr);
                showAlert('Failed to fetch activities', 'error');

                // Show modal anyway with empty activities
                currentActivities = [];
                populateActivityLimitsModal();
                $('#activityLimitsModal').modal('show');
            }
        });
    }

    function fetchExistingActivityLimits() {
        $.ajax({
            url: `/advance-request-master/activity-limits/${currentAdvanceTypeId}/${currentDepartmentId}`,
            method: 'GET',
            success: function (response) {
                showModalLoading('activity', false);

                if (response.success && response.limits) {
                    activityLimitsData = {};
                    response.limits.forEach(limit => {
                        activityLimitsData[limit.activity_id] = limit;
                    });
                } else {
                    // No existing limits, just use empty object
                    activityLimitsData = {};
                }

                populateActivityLimitsModal();
                $('#activityLimitsModal').modal('show');
            },
            error: function (xhr) {
                showModalLoading('activity', false);

                // Even if fetching limits fails, show the modal with activities
                activityLimitsData = {};
                populateActivityLimitsModal();
                $('#activityLimitsModal').modal('show');
            }
        });
    }

    function populateActivityLimitsModal() {
        const tbody = $('#activityLimitsTableBody');
        tbody.empty();

        // Ensure currentActivities is an array
        if (!Array.isArray(currentActivities)) {
            currentActivities = [];
        }

        if (currentActivities.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted">No activities found for this department.</td></tr>');
            return;
        }

        currentActivities.forEach((activity, index) => {
            const existingLimit = activityLimitsData[activity.id] || {};
            const minAmount = existingLimit.min_amount || '';
            const maxAmount = existingLimit.max_amount || '';

            // Show approved limits if available
            const approvedLimitInfo = activity.approved_limit > 0 ?
                `<small class="text-muted d-block">Approved Limit: ${activity.approved_limit}</small>` : '';

            const row = `
                <tr data-activity-id="${activity.id}">
                    <td>${index + 1}</td>
                    <td>
                        ${activity.activity_name}
                        ${approvedLimitInfo}
                    </td>
                    <td>
                        <input type="number" class="form-control activity-min-amount" 
                               placeholder="0.00" step="0.01" min="0" max="999999"
                               value="${minAmount}">
                    </td>
                    <td>
                        <input type="number" class="form-control activity-max-amount" 
                               placeholder="0.00" step="0.01" min="0" max="999999"
                               value="${maxAmount}">
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm save-activity-limit-btn" data-activity-id="${activity.id}">
                            <i class="ri-save-3-line"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function saveActivityLimit(activityId, minAmount, maxAmount, isActive) {
        const data = {
            advance_type_id: currentAdvanceTypeId,
            department_id: currentDepartmentId,
            activity_id: activityId,
            min_amount: minAmount || null,
            max_amount: maxAmount || null,
            is_active: isActive ? 1 : 0,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        $.ajax({
            url: '/advance-request-master/limits',
            method: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    showAlert(response.message, 'success');

                    // Update local data
                    if (isActive) {
                        activityLimitsData[activityId] = {
                            activity_id: activityId,
                            min_amount: minAmount,
                            max_amount: maxAmount,
                            is_active: true
                        };
                    } else {
                        delete activityLimitsData[activityId];
                    }
                } else {
                    showAlert(response.message || 'Failed to save activity limit', 'error');
                }
            },
            error: function (xhr) {
                let errorMessage = 'Failed to save activity limit';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join(', ');
                }
                showAlert(errorMessage, 'error');
            }
        });
    }

    function saveAllActivityLimits(limitsToSave) {
        showLoading(true);

        const promises = limitsToSave.map(limit => {
            const data = {
                advance_type_id: currentAdvanceTypeId,
                department_id: currentDepartmentId,
                activity_id: limit.activity_id,
                min_amount: limit.min_amount,
                max_amount: limit.max_amount,
                is_active: limit.is_active ? 1 : 0,
                _token: $('meta[name="csrf-token"]').attr('content')
            };

            return $.ajax({
                url: '/advance-request-master/limits',
                method: 'POST',
                data: data
            });
        });

        Promise.all(promises)
            .then(responses => {
                showLoading(false);
                const successCount = responses.filter(r => r.success).length;
                showAlert(`Successfully saved ${successCount} activity limits`, 'success');
                $('#activityLimitsModal').modal('hide');
            })
            .catch(error => {
                showLoading(false);
                showAlert('Failed to save some activity limits', 'error');
            });
    }

    function filterDesignations(searchTerm) {
        $('.designation-item').each(function () {
            const designationName = $(this).data('name');
            const designationId = $(this).data('designation-id').toString();

            // Search in both name and ID
            const matchesName = designationName.includes(searchTerm.toLowerCase());
            const matchesId = designationId.includes(searchTerm);

            if (matchesName || matchesId) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    function filterAddEmployees(searchTerm) {
        $('.add-employee-item').each(function () {
            const employeeName = $(this).data('name');
            if (employeeName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    function updateDesignationSelectionCount() {
        const count = selectedDesignationIds.length;
        const total = currentDesignations.length;
        $('#designationModalLabel').text(`Select Designations (${count}/${total} selected)`);
    }

    function showModalLoading(type, show) {
        let container;
        if (type === 'designation') {
            container = '.designation-list';
        } else if (type === 'add-employee') {
            container = '.add-employee-list';
        } else if (type === 'activity') {
            container = '#activityLimitsTableBody';
        }

        if (show) {
            $(container).html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        }
    }

    function showLoading(show) {
        if (show) {
            $('.progress-container').show();
            $('#confirmSaveBtn').prop('disabled', true);
            $('#confirmSaveBtn .loader').show();
        } else {
            $('.progress-container').hide();
            $('#confirmSaveBtn').prop('disabled', false);
            $('#confirmSaveBtn .loader').hide();
        }
    }

    function showAlert(message, type = 'primary') {
        // Map our simple types to your advanced function's parameters
        const typeMap = {
            'success': { type: 'success', icon: 'ri-check-line' },
            'error': { type: 'danger', icon: 'ri-error-warning-line' },
            'warning': { type: 'warning', icon: 'ri-alert-line' },
            'info': { type: 'info', icon: 'ri-information-line' }
        };

        const alertConfig = typeMap[type] || { type: 'primary', icon: 'ri-information-line' };

        // Use your advanced showAlert function
        const timestamp = Date.now();
        const alertId = `alert-${timestamp}`;
        const progressId = `progress-${timestamp}`;
        const duration = 5000;

        const alertHTML = `<div id="${alertId}" class="alert alert-${alertConfig.type} alert-dismissible alert-label-icon rounded-label fade show material-shadow position-relative overflow-hidden mt-2" role="alert" style="min-width: 300px;">
            <div id="${progressId}" class="position-absolute top-0 start-0 bg-${alertConfig.type}" style="height: 4px; width: 0%; transition: width ${duration}ms linear;"></div>
            <i class="${alertConfig.icon} label-icon"></i>
            <strong>${alertConfig.type.charAt(0).toUpperCase() + alertConfig.type.slice(1)}</strong> - ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;

        let container = document.getElementById("alert-container");
        if (!container) {
            container = document.createElement("div");
            container.id = "alert-container";
            container.style.position = "fixed";
            container.style.top = "20px";
            container.style.right = "20px";
            container.style.zIndex = "1055";
            container.style.maxWidth = "350px";
            document.body.appendChild(container);
        }

        container.insertAdjacentHTML("beforeend", alertHTML);

        setTimeout(() => {
            const progressBar = document.getElementById(progressId);
            if (progressBar) {
                progressBar.style.width = "100%";
            }
        }, 50);

        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.classList.remove("show");
                alert.classList.add("fade");
                setTimeout(() => alert.remove(), 500);
            }
        }, duration);
    }

    // Add CSS for selected employee item and table styling
    $('<style>').text(`
        .add-employee-item:hover {
            background-color: #f8f9fa;
        }
        .add-employee-item.selected {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }
        .designation-item:hover {
            background-color: #f8f9fa !important;
        }
        .designation-item.table-primary:hover {
            background-color: #cce7ff !important;
        }
        .designation-item td {
            vertical-align: middle;
        }
        .designation-item label {
            margin-bottom: 0;
            width: 100%;
        }
        #designationTableBody tr {
            cursor: pointer;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
    `).appendTo('head');

    // Add input validation for amount fields (6 digit limit)
    $(document).on('input', '.min-amount-input, .max-amount-input, .activity-min-amount, .activity-max-amount', function () {
        let value = $(this).val();

        // Remove any non-numeric characters except decimal point
        value = value.replace(/[^0-9.]/g, '');

        // Ensure only one decimal point
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }

        // Limit to 6 digits before decimal point
        if (parts[0] && parts[0].length > 6) {
            parts[0] = parts[0].substring(0, 6);
            value = parts.join('.');
        }

        // Limit to 2 decimal places
        if (parts[1] && parts[1].length > 2) {
            parts[1] = parts[1].substring(0, 2);
            value = parts.join('.');
        }

        // Update the input value
        $(this).val(value);

        // Check if value exceeds 999999
        const numValue = parseFloat(value);
        if (numValue > 999999) {
            $(this).val('999999');
            showAlert('Maximum amount cannot exceed 999,999', 'warning');
        }
    });

    // Prevent pasting invalid values
    $(document).on('paste', '.min-amount-input, .max-amount-input, .activity-min-amount, .activity-max-amount', function (e) {
        const pastedData = (e.originalEvent || e).clipboardData.getData('text/plain');
        const numValue = parseFloat(pastedData);

        if (isNaN(numValue) || numValue > 999999) {
            e.preventDefault();
            showAlert('Invalid amount. Maximum allowed is 999,999', 'warning');
        }
    });
});