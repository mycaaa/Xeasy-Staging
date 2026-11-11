$(document).ready(function () {
    $("#stateFieldPromotionMappingTable").DataTable({
        ordering: true,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, "asc"]],
    });

    $("#saveStateFieldPromotionMappingBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            state_name: $("#state_name").val().trim(),
            focus_name: $("#focus_name").val().trim(),
            focus_ledger_code: $("#focus_ledger_code").val().trim(),
            is_active: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("state-field-promotion-mapping-id") || null,
        };

        if (!formData.state_name || !formData.focus_name || !formData.focus_ledger_code) {
            showAlert("warning", "ri-error-warning-line", "Please fill all required fields.");
            return;
        }

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id
            ? `state-field-promotion-mappings/${formData.id}`
            : "state-field-promotion-mappings";

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
                        response.message || "State field promotion mapping saved successfully!"
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
                    xhr.responseJSON?.message || "Failed to save state field promotion mapping."
                );
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", ".delete-state-field-promotion-mapping", function (event) {
        event.preventDefault();
        const id = $(this).data("id");
        if (confirm("Are you sure you want to delete this state field promotion mapping?")) {
            $.ajax({
                url: "state-field-promotion-mappings/" + id,
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
                            response.message || "State field promotion mapping deleted successfully!"
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
                        xhr.responseJSON?.message || "Failed to delete state field promotion mapping."
                    );
                },
            });
        }
    });

    $(document).on("click", ".edit-state-field-promotion-mapping", function (event) {
        event.preventDefault();
        const id = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "state-field-promotion-mappings/" + id + "/edit",
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
                    $("#stateFieldPromotionMappingLabel").text(
                        "Edit State Field Promotion Mapping - " + (mapping.state_name_display || mapping.state_name)
                    );
                    $("#state_name").val(mapping.state_name);
                    $("#focus_name").val(mapping.focus_name);
                    $("#focus_ledger_code").val(mapping.focus_ledger_code);
                    $("#is_active").prop("checked", mapping.is_active);
                    $("#saveStateFieldPromotionMappingBtn").attr(
                        "data-state-field-promotion-mapping-id",
                        mapping.id
                    );
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching state field promotion mapping."
                    );
                }
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message || "Failed to fetch state field promotion mapping."
                );
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addStateFieldPromotionMappingBtn", function (event) {
        event.preventDefault();
        $("#stateFieldPromotionMappingLabel").text("Add New State Field Promotion Mapping");
        $("#state_name").val("");
        $("#focus_name").val("");
        $("#focus_ledger_code").val("");
        $("#is_active").prop("checked", true);
        $("#saveStateFieldPromotionMappingBtn").removeAttr("data-state-field-promotion-mapping-id");
    });
});

