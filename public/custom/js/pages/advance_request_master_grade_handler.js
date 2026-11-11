// Grade Selection Handler for Expense Advance Type
$(document).ready(function() {
    let currentGrades = [];
    let selectedGradeIds = [];

    // Handle grade input click to open modal (only for EXPENSE type)
    $(document).on('click', '.grade-input', function() {
        if ($(this).prop('disabled')) return;
        
        const departmentId = $(this).data('department-id');
        const advanceTypeId = $(this).data('advance-type-id');
        
        console.log('Grade input clicked - Department:', departmentId, 'AdvanceType:', advanceTypeId);
        
        if (!departmentId) {
            showAlert('Department ID is missing', 'error');
            return;
        }
        
        const currentSelectedIds = JSON.parse($(this).siblings('.grade-ids').val() || '[]');
        selectedGradeIds = currentSelectedIds.map(id => parseInt(id));
        
        // Store current context
        $('#gradeModal').data('advance-type-id', advanceTypeId);
        $('#gradeModal').data('department-id', departmentId);
        
        // Fetch all grades
        fetchGrades();
    });

    // Handle grade search
    $('#gradeSearch').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterGrades(searchTerm);
    });

    // Handle select all grades
    $('#selectAllGrades').on('click', function() {
        $('.grade-checkbox').prop('checked', true);
        $('.grade-item').addClass('table-primary');
        $('#selectAllGradesTable').prop('checked', true).prop('indeterminate', false);
        selectedGradeIds = currentGrades.map(g => parseInt(g.id));
        updateGradeSelectionCount();
    });

    // Handle clear all grades
    $('#clearAllGrades').on('click', function() {
        $('.grade-checkbox').prop('checked', false);
        $('.grade-item').removeClass('table-primary');
        $('#selectAllGradesTable').prop('checked', false).prop('indeterminate', false);
        selectedGradeIds = [];
        updateGradeSelectionCount();
    });

    // Handle individual grade checkbox change
    $(document).on('change', '.grade-checkbox', function() {
        const gradeId = parseInt($(this).val());
        const row = $(this).closest('tr');
        
        if ($(this).is(':checked')) {
            if (!selectedGradeIds.includes(gradeId)) {
                selectedGradeIds.push(gradeId);
            }
            row.addClass('table-primary');
        } else {
            selectedGradeIds = selectedGradeIds.filter(id => parseInt(id) !== gradeId);
            row.removeClass('table-primary');
        }
        
        updateSelectAllGradeCheckboxState();
        updateGradeSelectionCount();
    });

    // Handle confirm grade selection
    $('#confirmGradeSelection').on('click', function() {
        const advanceTypeId = $('#gradeModal').data('advance-type-id');
        const departmentId = $('#gradeModal').data('department-id');
        
        if (!advanceTypeId || !departmentId) return;
        
        // Update the input field and hidden field
        const selectedNames = currentGrades
            .filter(g => selectedGradeIds.includes(g.id))
            .map(g => g.grade_name);
        
        const inputField = $(`.grade-input[data-advance-type-id="${advanceTypeId}"][data-department-id="${departmentId}"]`);
        const hiddenField = $(`.grade-ids[data-advance-type-id="${advanceTypeId}"][data-department-id="${departmentId}"]`);
        
        inputField.val(selectedNames.join(', '));
        hiddenField.val(JSON.stringify(selectedGradeIds));
        
        // Close modal
        $('#gradeModal').modal('hide');
    });

    // Reset modal when closed
    $('#gradeModal').on('hidden.bs.modal', function() {
        $('#gradeSearch').val('');
        $('.grade-list').empty();
        $('#selectAllGradesTable').off('change');
        $(document).off('click', '.grade-item');
        $(this).removeData('advance-type-id');
        $(this).removeData('department-id');
        currentGrades = [];
        selectedGradeIds = [];
    });

    // Utility functions
    function fetchGrades() {
        showGradeModalLoading(true);
        
        // Get department ID from modal data
        const departmentId = $('#gradeModal').data('department-id');
        
        console.log('Fetching grades for department:', departmentId);
        
        if (!departmentId) {
            showGradeModalLoading(false);
            showAlert('Department ID is required', 'error');
            return;
        }
        
        $.ajax({
            url: '/advance-request-master/grades',
            method: 'GET',
            data: {
                department_id: departmentId
            },
            success: function(response) {
                showGradeModalLoading(false);
                if (response.success) {
                    currentGrades = response.grades;
                    populateGradeModal(response.grades);
                    $('#gradeModal').modal('show');
                } else {
                    showAlert(response.message || 'Failed to fetch grades', 'error');
                }
            },
            error: function(xhr) {
                showGradeModalLoading(false);
                const errorMsg = xhr.responseJSON?.message || 'Failed to fetch grades';
                showAlert(errorMsg, 'error');
                console.error('Grade fetch error:', xhr);
            }
        });
    }

    function populateGradeModal(grades) {
        const container = $('.grade-list');
        container.empty();
        
        if (grades.length === 0) {
            container.html('<p class="text-muted text-center">No grades found.</p>');
            return;
        }
        
        // Sort grades: selected ones first, then by name
        const sortedGrades = [...grades].sort((a, b) => {
            const aSelected = selectedGradeIds.includes(a.id);
            const bSelected = selectedGradeIds.includes(b.id);
            
            if (aSelected && !bSelected) return -1;
            if (!aSelected && bSelected) return 1;
            
            return a.grade_name.localeCompare(b.grade_name);
        });
        
        // Create table structure
        const tableHtml = `
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" class="form-check-input" id="selectAllGradesTable">
                            </th>
                            <th style="width: 60px;">ID</th>
                            <th>Grade Name</th>
                        </tr>
                    </thead>
                    <tbody id="gradeTableBody">
                    </tbody>
                </table>
            </div>
        `;
        
        container.html(tableHtml);
        
        const tbody = $('#gradeTableBody');
        
        sortedGrades.forEach((grade, index) => {
            const isChecked = selectedGradeIds.includes(parseInt(grade.id));
            const rowClass = isChecked ? 'table-primary' : '';
            
            const rowHtml = `
                <tr class="grade-item ${rowClass}" data-name="${grade.grade_name.toLowerCase()}" data-grade-id="${grade.id}">
                    <td>
                        <input class="form-check-input grade-checkbox" 
                               type="checkbox" 
                               value="${grade.id}" 
                               id="grade_${grade.id}"
                               ${isChecked ? 'checked' : ''}>
                    </td>
                    <td class="text-muted">${grade.id}</td>
                    <td>
                        <label class="form-check-label fw-medium" for="grade_${grade.id}" style="cursor: pointer;">
                            ${grade.grade_name}
                        </label>
                    </td>
                </tr>
            `;
            tbody.append(rowHtml);
        });
        
        updateSelectAllGradeCheckboxState();
        
        // Handle select all checkbox in table header
        $('#selectAllGradesTable').off('change').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.grade-checkbox').prop('checked', isChecked);
            
            if (isChecked) {
                selectedGradeIds = currentGrades.map(g => parseInt(g.id));
                $('.grade-item').addClass('table-primary');
            } else {
                selectedGradeIds = [];
                $('.grade-item').removeClass('table-primary');
            }
            updateGradeSelectionCount();
        });
        
        // Handle row click to toggle checkbox
        $(document).off('click', '.grade-item').on('click', '.grade-item', function(e) {
            if (e.target.type === 'checkbox' || e.target.tagName === 'LABEL') {
                return;
            }
            
            const checkbox = $(this).find('.grade-checkbox');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        });
        
        updateGradeSelectionCount();
    }

    function updateSelectAllGradeCheckboxState() {
        const totalCheckboxes = $('.grade-checkbox').length;
        const checkedCheckboxes = $('.grade-checkbox:checked').length;
        const selectAllCheckbox = $('#selectAllGradesTable');
        
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

    function filterGrades(searchTerm) {
        $('.grade-item').each(function() {
            const gradeName = $(this).data('name');
            const gradeId = $(this).data('grade-id').toString();
            
            const matchesName = gradeName.includes(searchTerm.toLowerCase());
            const matchesId = gradeId.includes(searchTerm);
            
            if (matchesName || matchesId) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    function updateGradeSelectionCount() {
        const count = selectedGradeIds.length;
        const total = currentGrades.length;
        $('#gradeModalLabel').text(`Select Grades (Not Allowed for Expense Advance) (${count}/${total} selected)`);
    }

    function showGradeModalLoading(show) {
        const container = '.grade-list';
        if (show) {
            $(container).html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        }
    }

    // Add CSS for grade table styling
    $('<style>').text(`
        .grade-item:hover {
            background-color: #f8f9fa !important;
        }
        .grade-item.table-primary:hover {
            background-color: #cce7ff !important;
        }
        .grade-item td {
            vertical-align: middle;
        }
        .grade-item label {
            margin-bottom: 0;
            width: 100%;
        }
        #gradeTableBody tr {
            cursor: pointer;
        }
    `).appendTo('head');

    // Handle grade input enable/disable based on is_active switch for EXPENSE type
    $('.is-active-switch').on('change', function() {
        const advanceTypeId = $(this).data('advance-type-id');
        const departmentId = $(this).data('department-id');
        const isChecked = $(this).is(':checked');

        if (departmentId) {
            const row = $(`tr[data-advance-type-id="${advanceTypeId}"][data-department-id="${departmentId}"]`);
            const gradeInput = row.find('.grade-input');
            
            if (gradeInput.length > 0) {
                // Don't clear values - just disable/enable the field
                gradeInput.prop('disabled', !isChecked);
            }
        }
    });

    // Initialize grade field states based on current switch values
    $('.is-active-switch').each(function() {
        const advanceTypeId = $(this).data('advance-type-id');
        const departmentId = $(this).data('department-id');
        const isChecked = $(this).is(':checked');

        if (departmentId) {
            const row = $(`tr[data-advance-type-id="${advanceTypeId}"][data-department-id="${departmentId}"]`);
            const gradeInput = row.find('.grade-input');
            
            if (gradeInput.length > 0 && !isChecked) {
                gradeInput.prop('disabled', true);
            }
        }
    });
});
