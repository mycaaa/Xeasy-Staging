$(document).ready(function () {
    $("#departmentVerticalMappingTable").DataTable({
        ordering: true,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, "asc"]],
    });

    $("#saveDepartmentVerticalMappingBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            base_department: $("#base_department").val().trim(),
            vertical_name: $("#vertical_name").val().trim(),
            mapped_department_name: $("#mapped_department_name").val().trim(),
            is_active: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("department-vertical-mapping-id") || null,
        };

        if (!formData.base_department || !formData.vertical_name || !formData.mapped_department_name) {
            showAlert("warning", "ri-error-warning-line", "Please fill all required fields.");
            return;
        }

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id
            ? `department-vertical-mappings/${formData.id}`
            : "department-vertical-mappings";

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
                    showAlert(
                        "success",
                        "ri-checkbox-circle-line",
                        response.message || "Department vertical mapping saved successfully!"
                    );
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 2000);
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "An error occurred while saving."
                    );
                }
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message || "Failed to save department vertical mapping."
                );
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", ".delete-department-vertical-mapping", function (event) {
        event.preventDefault();
        const id = $(this).data("id");
        if (confirm("Are you sure you want to delete this department vertical mapping?")) {
            $.ajax({
                url: "department-vertical-mappings/" + id,
                type: "DELETE",
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    if (response.success) {
                        showAlert(
                            "success",
                            "ri-checkbox-circle-line",
                            response.message || "Department vertical mapping deleted successfully!"
                        );
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 2000);
                    } else {
                        showAlert(
                            "danger",
                            "ri-error-warning-line",
                            response.message || "An error occurred while deleting."
                        );
                    }
                },
                error: function (xhr) {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        xhr.responseJSON?.message || "Failed to delete department vertical mapping."
                    );
                },
            });
        }
    });

    $(document).on("click", ".edit-department-vertical-mapping", function (event) {
        event.preventDefault();
        const id = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "department-vertical-mappings/" + id + "/edit",
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
                    const mapping = response.data;
                    $("#departmentVerticalMappingLabel").text(
                        "Edit Department Vertical Mapping - " + mapping.base_department
                    );
                    $("#base_department").val(mapping.base_department);
                    $("#vertical_name").val(mapping.vertical_name);
                    $("#mapped_department_name").val(mapping.mapped_department_name);
                    $("#is_active").prop("checked", mapping.is_active);
                    $("#saveDepartmentVerticalMappingBtn").attr(
                        "data-department-vertical-mapping-id",
                        mapping.id
                    );
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching department vertical mapping."
                    );
                }
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message || "Failed to fetch department vertical mapping."
                );
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addDepartmentVerticalMappingBtn", function (event) {
        event.preventDefault();
        $("#departmentVerticalMappingLabel").text("Add New Department Vertical Mapping");
        $("#base_department").val("");
        $("#vertical_name").val("");
        $("#mapped_department_name").val("");
        $("#is_active").prop("checked", true);
        $("#saveDepartmentVerticalMappingBtn").removeAttr("data-department-vertical-mapping-id");
    });
});

