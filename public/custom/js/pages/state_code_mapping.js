$(document).ready(function () {
    $("#stateCodeMappingTable").DataTable({
        ordering: true,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, "asc"]], // Sort by state name
    });

    $("#saveStateCodeMappingBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            state_name: $("#state_name").val().trim(),
            state_code: $("#state_code").val().trim(),
            is_active: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("state-code-mapping-id") || null,
        };

        // Validation
        if (!formData.state_name) {
            showAlert(
                "warning",
                "ri-error-warning-line",
                "Please enter state name."
            );
            return;
        }
        if (!formData.state_code) {
            showAlert(
                "warning",
                "ri-error-warning-line",
                "Please enter state code."
            );
            return;
        }
        if (formData.state_code.length !== 2) {
            showAlert(
                "warning",
                "ri-error-warning-line",
                "State code must be exactly 2 characters."
            );
            return;
        }

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id
            ? `state-code-mappings/${formData.id}`
            : "state-code-mappings";

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
                            "State code mapping saved successfully!"
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
                let errorMsg = "Failed to save state code mapping.";
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

    $(document).on("click", ".delete-state-code-mapping", function (event) {
        event.preventDefault();
        const stateCodeMappingId = $(this).data("id");
        const confirmation = confirm(
            "Are you sure you want to delete this state code mapping?"
        );

        if (confirmation) {
            $.ajax({
                url: "state-code-mappings/" + stateCodeMappingId,
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
                                "State code mapping deleted successfully!"
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
                    let errorMsg = "Failed to delete state code mapping.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $(document).on("click", ".edit-state-code-mapping", function (event) {
        event.preventDefault();
        const stateCodeMappingId = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "state-code-mappings/" + stateCodeMappingId + "/edit",
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
                    $("#stateCodeMappingLabel").text(
                        "Edit State Code Mapping - " + mapping.state_name
                    );
                    $("#state_name").val(mapping.state_name);
                    $("#state_code").val(mapping.state_code);
                    $("#is_active").prop("checked", mapping.is_active);
                    $("#saveStateCodeMappingBtn").attr(
                        "data-state-code-mapping-id",
                        mapping.id
                    );
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching state code mapping."
                    );
                }
            },
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message ||
                    "Failed to fetch state code mapping.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addStateCodeMappingBtn", function (event) {
        event.preventDefault();
        $("#stateCodeMappingLabel").text("Add New State Code Mapping");
        $("#state_name").val("");
        $("#state_code").val("");
        $("#is_active").prop("checked", true);
        $("#saveStateCodeMappingBtn").removeAttr("data-state-code-mapping-id");
    });
});
