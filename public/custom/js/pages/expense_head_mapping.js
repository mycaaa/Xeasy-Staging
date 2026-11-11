$(document).ready(function () {
    $("#expenseHeadMappingTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });

    // Initialize Select2 for activity_id and expense_head_id
    $("#activity_id").select2({
        placeholder: "Select Activity",
        allowClear: true,
        width: '100%',
        dropdownParent: $('#expenseHeadMappingModal')
    });
    $("#expense_head_id").select2({
        placeholder: "Select Expense Head",
        allowClear: true,
        width: '100%',
        dropdownParent: $('#expenseHeadMappingModal')
    });

    function populateActivities(selectElement, selectedId = null) {
        $.ajax({
            url: "{{ route('expense-head-mappings.activities') }}",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append('<option value="">Select Activity</option>');
                    response.data.forEach(function (activity) {
                        const isSelected = selectedId == activity.id ? 'selected' : '';
                        selectElement.append(`<option value="${activity.id}">${activity.name}</option>`);
                    });
                    selectElement.val(selectedId || null).trigger('change');
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch activities:", xhr);
            },
        });
    }

    function populateExpenseHeads(selectElement, selectedId = null) {
        $.ajax({
            url: "{{ route('expense-head-mappings.expense-heads') }}",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append('<option value="">Select Expense Head</option>');
                    response.data.forEach(function (head) {
                        const isSelected = selectedId == head.id ? 'selected' : '';
                        selectElement.append(`<option value="${head.id}">${head.head_name}</option>`);
                    });
                    selectElement.val(selectedId || null).trigger('change');
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch expense heads:", xhr);
            },
        });
    }

    $(document).on("click", ".edit-expense-head-mapping", function (event) {
        event.preventDefault();
        const mappingId = $(this).data("id");
        const form = $('#expenseHeadMappingForm');
        const button = event.currentTarget;

        $.ajax({
            url: "{{ url('admin/expense-head-mappings') }}/" + mappingId + "/edit",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    const mapping = response.data;
                    $("#expenseHeadMappingLabel").text("Edit Expense Head Mapping - " + (mapping.activity?.claim_name || 'Mapping #' + mapping.id));
                    $("#activity_id").val(mapping.activity_id || '');
                    $("#expense_head_id").val(mapping.expense_head_id || '');
                    $("#checked").prop("checked", mapping.checked);
                    form.attr('action', "{{ url('admin/expense-head-mappings') }}/" + mapping.id);
                    form.append('<input type="hidden" name="_method" value="PUT">');

                    populateActivities($("#activity_id"), mapping.activity_id);
                    populateExpenseHeads($("#expense_head_id"), mapping.expense_head_id);
                } else {
                    alert("Error fetching expense head mapping.");
                }
            },
            error: function (xhr) {
                alert("Failed to fetch expense head mapping.");
            },
        });
    });

    $(document).on("click", "#addExpenseHeadMappingBtn", function (event) {
        event.preventDefault();
        $("#expenseHeadMappingLabel").text("Add New Expense Head Mapping");
        $("#activity_id").val(null).trigger('change');
        $("#expense_head_id").val(null).trigger('change');
        $("#checked").prop("checked", false);
        $('#expenseHeadMappingForm').attr('action', "{{ route('expense-head-mappings.store') }}");
        $('input[name="_method"]').remove();

        populateActivities($("#activity_id"));
        populateExpenseHeads($("#expense_head_id"));
    });

    $('#expenseHeadMappingModal').on('show.bs.modal', function (event) {
        populateActivities($("#activity_id"));
        populateExpenseHeads($("#expense_head_id"));
    });
});