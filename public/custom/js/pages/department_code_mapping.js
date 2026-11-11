$(document).ready(function () {
    $("#deptCodeMappingTable").DataTable({
        ordering: true,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, "asc"]],
    });

    $("#saveDeptCodeMappingBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            original_department_name: $("#original_department_name")
                .val()
                .trim(),
            mapped_department_code: $("#mapped_department_code").val().trim(),
            mapped_department_name: $("#mapped_department_name").val().trim(),
            is_active: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("dept-code-mapping-id") || null,
        };

        if (
            !formData.original_department_name ||
            !formData.mapped_department_code ||
            !formData.mapped_department_name
        ) {
            showAlert(
                "warning",
                "ri-error-warning-line",
                "Please fill all required fields."
            );
            return;
        }

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id
            ? `department-code-mappings/${formData.id}`
            : "department-code-mappings";

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
                        response.message ||
                            "Department code mapping saved successfully!"
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
                let errorMsg = "Failed to save department code mapping.";
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

    $(document).on("click", ".delete-dept-code-mapping", function (event) {
        event.preventDefault();
        const id = $(this).data("id");
        if (
            confirm(
                "Are you sure you want to delete this department code mapping?"
            )
        ) {
            $.ajax({
                url: "department-code-mappings/" + id,
                type: "DELETE",
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                        "content"
                    ),
                },
                success: function (response) {
                    if (response.success) {
                        showAlert(
                            "success",
                            "ri-checkbox-circle-line",
                            response.message ||
                                "Department code mapping deleted successfully!"
                        );
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 2000);
                    } else {
                        showAlert(
                            "danger",
                            "ri-error-warning-line",
                            response.message ||
                                "An error occurred while deleting."
                        );
                    }
                },
                error: function (xhr) {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        xhr.responseJSON?.message ||
                            "Failed to delete department code mapping."
                    );
                },
            });
        }
    });

    $(document).on("click", ".edit-dept-code-mapping", function (event) {
        event.preventDefault();
        const id = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "department-code-mappings/" + id + "/edit",
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
                    $("#deptCodeMappingLabel").text(
                        "Edit Department Code Mapping - " +
                            mapping.original_department_name
                    );
                    $("#original_department_name").val(
                        mapping.original_department_name
                    );
                    $("#mapped_department_code").val(
                        mapping.mapped_department_code
                    );
                    $("#mapped_department_name").val(
                        mapping.mapped_department_name
                    );
                    $("#is_active").prop("checked", mapping.is_active);
                    $("#saveDeptCodeMappingBtn").attr(
                        "data-dept-code-mapping-id",
                        mapping.id
                    );
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message ||
                            "Error fetching department code mapping."
                    );
                }
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message ||
                        "Failed to fetch department code mapping."
                );
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addDeptCodeMappingBtn", function (event) {
        event.preventDefault();
        $("#deptCodeMappingLabel").text("Add New Department Code Mapping");
        $("#original_department_name").val("");
        $("#mapped_department_code").val("");
        $("#mapped_department_name").val("");
        $("#is_active").prop("checked", true);
        $("#saveDeptCodeMappingBtn").removeAttr("data-dept-code-mapping-id");
    });
});
