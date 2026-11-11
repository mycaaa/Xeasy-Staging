// Management Selection Handler for Auto Advance
$(document).ready(function () {
    let currentAdvanceTypeId = null;
    let currentDepartmentId = null;
    let savedManagementData = [];

    // Handle management select button click
    $(document).on('click', '.management-select-btn', function (e) {
        e.preventDefault();

        currentAdvanceTypeId = $(this).data('advance-type-id');
        currentDepartmentId = $(this).data('department-id');
        const departmentName = $(this).data('department-name');

        $('#managementDepartmentName').text(departmentName);

        // Get currently saved management data
        const hiddenInput = $(`.selected-managers-data[data-advance-type-id="${currentAdvanceTypeId}"][data-department-id="${currentDepartmentId}"]`);
        const rawData = hiddenInput.val() || '[]';
        console.log('Raw saved data:', rawData);
        savedManagementData = JSON.parse(rawData);
        console.log('Parsed saved management data:', savedManagementData);

        // Fetch management team
        fetchManagementTeam();
    });

    // Handle select all checkbox
    $(document).on('change', '#selectAllManagers', function () {
        const isChecked = $(this).is(':checked');
        $('.manager-checkbox').prop('checked', isChecked);
    });

    // Handle individual checkbox change
    $(document).on('change', '.manager-checkbox', function () {
        updateSelectAllState();
    });

    // Handle confirm selection
    $('#confirmManagementSelection').on('click', function () {
        if (!currentAdvanceTypeId || !currentDepartmentId) return;

        // Collect selected managers with their verticals
        const managementData = [];
        let selectedCount = 0;

        $('.manager-checkbox:checked').each(function () {
            const managerId = parseInt($(this).val());

            // Get all selected verticals for this manager
            const selectedVerticals = [];
            $(`.vertical-checkbox[data-manager-id="${managerId}"]:checked`).each(function () {
                selectedVerticals.push(parseInt($(this).val()));
            });

            managementData.push({
                manager_id: managerId,
                vertical_ids: selectedVerticals
            });
            selectedCount++;
        });

        // Update hidden input with management data
        const hiddenInput = $(`.selected-managers-data[data-advance-type-id="${currentAdvanceTypeId}"][data-department-id="${currentDepartmentId}"]`);
        hiddenInput.val(JSON.stringify(managementData));

        // Update button display
        const btn = $(`.management-select-btn[data-advance-type-id="${currentAdvanceTypeId}"][data-department-id="${currentDepartmentId}"]`);
        const countDisplay = btn.next('.mt-1');

        if (selectedCount > 0) {
            if (countDisplay.length) {
                countDisplay.find('small').text(`${selectedCount} manager(s) selected`);
            } else {
                btn.after(`<div class="mt-1"><small class="text-muted">${selectedCount} manager(s) selected</small></div>`);
            }
        } else {
            countDisplay.remove();
        }

        // Close modal
        $('#managementSelectionModal').modal('hide');

        showAlert('Management selection updated. Click Save to apply changes.', 'info');
    });

    // Reset modal when closed
    $('#managementSelectionModal').on('hidden.bs.modal', function () {
        $('#managementTableBody').empty();
        currentAdvanceTypeId = null;
        currentDepartmentId = null;
    });

    function fetchManagementTeam() {
        showManagementModalLoading(true);

        $.ajax({
            url: '/advance-request-master/management-team',
            method: 'POST',
            data: {
                department_id: currentDepartmentId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                showManagementModalLoading(false);
                if (response.success) {
                    populateManagementModal(response.management);
                    $('#managementSelectionModal').modal('show');
                } else {
                    showAlert(response.message || 'Failed to fetch management team', 'error');
                }
            },
            error: function (xhr) {
                showManagementModalLoading(false);
                showAlert('Failed to fetch management team', 'error');
            }
        });
    }

    function populateManagementModal(management) {
        const tbody = $('#managementTableBody');
        tbody.empty();

        if (management.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center text-muted">No management team members found</td></tr>');
            return;
        }

        console.log('Populating modal with management:', management);
        console.log('Saved management data:', savedManagementData);

        management.forEach((manager, index) => {
            // Find saved data for this manager
            const savedData = savedManagementData.find(item => item.manager_id === manager.employee_id);
            const isManagerChecked = savedData !== undefined;
            const savedVerticalIds = savedData ? (savedData.vertical_ids || []) : [];
            
            console.log(`Manager ${manager.employee_id}:`, {
                savedData,
                isManagerChecked,
                savedVerticalIds
            });

            // Build verticals display with checkboxes
            let verticalsHtml = '';
            if (manager.verticals && manager.verticals.length > 0) {
                verticalsHtml = '<div class="verticals-checkbox-group">';
                manager.verticals.forEach((v, vIndex) => {
                    const checkboxId = `vertical_${manager.employee_id}_${v.id}`;
                    const isVerticalChecked = savedVerticalIds.includes(v.id);
                    console.log(`  Vertical ${v.id} (${v.name}): checked=${isVerticalChecked}`);
                    verticalsHtml += `
                        <div class="form-check form-check-inline">
                            <input class="form-check-input vertical-checkbox" 
                                   type="checkbox" 
                                   id="${checkboxId}" 
                                   value="${v.id}"
                                   data-manager-id="${manager.employee_id}"
                                   ${isVerticalChecked ? 'checked' : ''}>
                            <label class="form-check-label" for="${checkboxId}">
                                ${v.name}
                            </label>
                        </div>
                    `;
                });
                verticalsHtml += '</div>';
            } else {
                verticalsHtml = '<span class="text-muted">No verticals</span>';
            }

            const rowHtml = `
                <tr>
                    <td class="text-center">
                        <input class="form-check-input manager-checkbox" 
                               type="checkbox" 
                               value="${manager.employee_id}"
                               ${isManagerChecked ? 'checked' : ''}>
                    </td>
                    <td>${manager.employee_id}</td>
                    <td>${manager.full_name}</td>
                    <td>${verticalsHtml}</td>
                </tr>
            `;
            tbody.append(rowHtml);
        });

        updateSelectAllState();
    }

    function updateSelectAllState() {
        const totalCheckboxes = $('.manager-checkbox').length;
        const checkedCheckboxes = $('.manager-checkbox:checked').length;
        const selectAllCheckbox = $('#selectAllManagers');

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

    function showManagementModalLoading(show) {
        const tbody = $('#managementTableBody');
        if (show) {
            tbody.html('<tr><td colspan="4" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>');
        }
    }
});
