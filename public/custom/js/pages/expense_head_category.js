$(document).ready(function () {
    $("#expenseHeadTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });

    $("#field_type").select2({
        dropdownParent: $("#expenseHeadModal"),
        width: "100%",
        allowClear: true,
    });

    $("#saveExpenseHeadBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            expense_head_name: $("#expense_head_name").val(),
            short_code: $("#short_code").val(),
            field_type: $("#field_type").val(),
            has_file: $("#has_file").is(":checked") ? 1 : 0,
            file_required: $("#file_required").is(":checked") ? 1 : 0,
            status: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("expense-head-id") || null,
        };

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `expense-heads/${formData.id}` : "expense-heads";

        $.ajax({
            url: url,
            type: requestType,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            data: formData,
            dataType: "json",
            beforeSend: function () {
                startLoader({ currentTarget: button });
            },
            success: function (response) {
                if (response.success) {
                    showAlert("success", "ri-checkbox-circle-line", response.message || "Expense head category saved successfully!");
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 2000);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while saving.");
                }
            },
            error: function (xhr) {
                let errorMsg = "Failed to save expense head category.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", ".delete-expense-head", function (event) {
        event.preventDefault();
        const expenseHeadId = $(this).data("id");
        const confirmation = confirm("Are you sure you want to delete this expense head category?");
        if (confirmation) {
            $.ajax({
                url: "expense-heads/" + expenseHeadId,
                type: "DELETE",
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    if (response.success) {
                        showAlert("success", "ri-checkbox-circle-line", response.message || "Expense head category deleted successfully!");
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 5000);
                    } else {
                        showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while deleting.");
                    }
                },
                error: function (xhr) {
                    let errorMsg = "Failed to delete expense head category.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $(document).on("click", ".edit-expense-head", function (event) {
        event.preventDefault();
        const expenseHeadId = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "expense-heads/" + expenseHeadId + "/edit",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            beforeSend: function () {
                startLoader({ currentTarget: button });
            },
            success: function (response) {
                if (response.success) {
                    const expenseHead = response.data;
                    $("#expenseHeadLabel").text("Edit Expense Head Category - " + expenseHead.expense_head_name);
                    $("#expense_head_name").val(expenseHead.expense_head_name);
                    $("#short_code").val(expenseHead.short_code);
                    $("#field_type").val(expenseHead.field_type);
                    $("#has_file").prop("checked", expenseHead.has_file);
                    $("#file_required").prop("checked", expenseHead.file_required);
                    $("#is_active").prop("checked", expenseHead.status);
                    $("#saveExpenseHeadBtn").attr("data-expense-head-id", expenseHead.id);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "Error fetching expense head category.");
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON?.message || "Failed to fetch expense head category.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addExpenseHeadBtn", function (event) {
        event.preventDefault();
        $("#expenseHeadLabel").text("Add New Expense Head Category");
        $("#expense_head_name").val("");
        $("#short_code").val("");
        $("#field_type").val("");
        $("#has_file").prop("checked", false);
        $("#file_required").prop("checked", false);
        $("#is_active").prop("checked", true);
        $("#saveExpenseHeadBtn").removeAttr("data-expense-head-id");
    });
});