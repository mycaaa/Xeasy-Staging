$(document).ready(function () {
    $("#activityTypeTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });

    // Initialize Select2 for department_id
    $("#department_id").select2({
        placeholder: "Select Departments",
        allowClear: true,
        width: '100%',
        dropdownParent: $('#activityTypeModal') // Ensure dropdown works within modal
    });

    function populateCategories(selectElement) {
        $.ajax({
            url: "activity-type/categories",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append('<option value="">Select Category</option>');
                    response.data.forEach(function (category) {
                        selectElement.append(`<option value="${category.id}">${category.category_name}</option>`);
                    });
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch categories:", xhr);
            },
        });
    }

    function populateDepartments(selectElement, selectedIds = []) {
        $.ajax({
            url: "activity-type/departments",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append('<option value="">Select Departments</option>');
                    response.data.forEach(function (department) {
                        const isSelected = selectedIds.includes(String(department.id)) ? 'selected' : '';
                        selectElement.append(`<option value="${department.id}" ${isSelected}>${department.department_name}</option>`);
                    });
                    selectElement.trigger('change'); // Trigger Select2 to update
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch departments:", xhr);
            },
        });
    }

    $("#saveActivityTypeBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            department_id: $("#department_id").val() || null, // Select2 returns an array
            type_name: $("#type_name").val(),
            focus_code: $("#focus_code").val(),
            description: $("#description").val(),
            status: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("activity-type-id") || null,
        };

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `activity-types/${formData.id}` : "activity-types";

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
                    showAlert("success", "ri-checkbox-circle-line", response.message || "Activity type saved successfully!");
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 2000);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while saving.");
                }
            },
            error: function (xhr) {
                let errorMsg = "Failed to save activity type.";
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

    $(document).on("click", ".delete-activity-type", function (event) {
        event.preventDefault();
        const activityTypeId = $(this).data("id");
        const confirmation = confirm("Are you sure you want to delete this activity type?");
        if (confirmation) {
            $.ajax({
                url: "activity-types/" + activityTypeId,
                type: "DELETE",
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    if (response.success) {
                        showAlert("success", "ri-checkbox-circle-line", response.message || "Activity type deleted successfully!");
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 5000);
                    } else {
                        showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while deleting.");
                    }
                },
                error: function (xhr) {
                    let errorMsg = "Failed to delete activity type.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $(document).on("click", ".edit-activity-type", function (event) {
        event.preventDefault();
        const activityTypeId = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "activity-types/" + activityTypeId + "/edit",
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
                    const activityType = response.data;
                    $("#activityTypeLabel").text("Edit Activity Type - " + activityType.type_name);
                    $("#type_name").val(activityType.type_name);
                    $("#focus_code").val(activityType.focus_code);
                    $("#description").val(activityType.description);
                    $("#is_active").prop("checked", activityType.status);
                    $("#saveActivityTypeBtn").attr("data-activity-type-id", activityType.id);

                    // Populate departments with selected IDs
                    populateDepartments($("#department_id"), activityType.department_id ? activityType.department_id.split(',') : []);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "Error fetching activity type.");
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON?.message || "Failed to fetch activity type.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addActivityTypeBtn", function (event) {
        event.preventDefault();
        $("#activityTypeLabel").text("Add New Activity Type");
        $("#department_id").val(null).trigger('change'); // Clear Select2
        $("#type_name").val("");
        $("#focus_code").val("");
        $("#description").val("");
        $("#is_active").prop("checked", true);
        $("#saveActivityTypeBtn").removeAttr("data-activity-type-id");
        populateDepartments($("#department_id"));
    });

    $('#activityTypeModal').on('show.bs.modal', function (event) {
        populateDepartments($("#department_id"));
    });
});