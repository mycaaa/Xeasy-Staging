$(document).ready(function () {
    $("#activityNameTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });
    $("#category_id").select2({
        placeholder: "Select Category",
        allowClear: true,
        width: "100%",
        dropdownParent: $("#activityNameModal"),
    });
    $("#dept_id").select2({
        placeholder: "Select Departments",
        allowClear: true,
        width: "100%",
        dropdownParent: $("#activityNameModal"),
    });
    $("#vertical").select2({
        placeholder: "Select Verticals",
        allowClear: true,
        width: "100%",
        dropdownParent: $("#activityNameModal"),
    });
    $("#activity_name").select2({
        placeholder: "Select Activity Name",
        allowClear: true,
        width: "100%",
        dropdownParent: $("#activityNameModal"),
    });

    function populateCategories(selectElement, selectedId = null) {
        $.ajax({
            url: "activity-name/categories",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append('<option value=""></option>'); // Select2 placeholder

                    response.data.forEach(function (category) {
                        selectElement.append(
                            `<option value="${category.id}">${category.category_name}</option>`
                        );
                    });

                    // ✅ Set selected value (if any) and refresh Select2
                    if (selectedId) {
                        selectElement.val(String(selectedId));
                    } else {
                        selectElement.val(null);
                    }

                    selectElement.trigger("change"); // IMPORTANT for Select2
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch categories:", xhr);
            },
        });
    }

    function populateDepartments(selectElement, selectedIds = []) {
        $.ajax({
            url: "activity-name/departments",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append('<option value=""></option>'); // Select2 placeholder

                    response.data.forEach(function (department) {
                        selectElement.append(
                            `<option value="${department.id}">${department.name}</option>`
                        );
                    });

                    const numericIds = selectedIds
                        .map((id) => parseInt(id, 10))
                        .filter((id) => !isNaN(id));

                    selectElement
                        .val(numericIds.length ? numericIds : null)
                        .trigger("change");
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch departments:", xhr);
            },
        });
    }

    function populateVerticals(selectElement, selectedIds = []) {
        $.ajax({
            url: "activity-name/verticals",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append(
                        '<option value="">Select Verticals</option>'
                    );
                    response.data.forEach(function (vertical) {
                        const isSelected = selectedIds.includes(
                            String(vertical.id)
                        )
                            ? "selected"
                            : "";
                        selectElement.append(
                            `<option value="${vertical.id}">${vertical.name}</option>`
                        );
                    });
                    selectElement
                        .val(selectedIds.length ? selectedIds : null)
                        .trigger("change");
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch verticals:", xhr);
            },
        });
    }

    function populateClaimTypes(selectElement, selectedId = null) {
        $.ajax({
            url: "activity-name/claim-types",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append(
                        '<option value="">Select Activity Name</option>'
                    );
                    response.data.forEach(function (claimType) {
                        const isSelected =
                            selectedId == claimType.ClaimId ? "selected" : "";
                        selectElement.append(
                            `<option value="${claimType.ClaimId}">${claimType.ClaimName}</option>`
                        );
                    });
                    selectElement.val(selectedId || null).trigger("change");
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch claim types:", xhr);
            },
        });
    }

    $("#saveActivityNameBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            category_id: $("#category_id").val() || null,
            activity_name: $("#activity_name").val() || null,
            dept_id: $("#dept_id").val() || null,
            vertical: $("#vertical").val() || null,
            description: $("#description").val(),
            from_month: $("#from_month").val(),
            to_month: $("#to_month").val(),
            from_year: $("#from_year").val(),
            to_year: $("#to_year").val(),
            approved_limit: $("#approved_limit").val(),
            approved_amount: $("#approved_amount").val(),
            status: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("activity-name-id") || null,
        };

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id
            ? `activity-names/${formData.id}`
            : "activity-names";

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
                        response.message || "Activity name saved successfully!"
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
                let errorMsg = "Failed to save activity name.";
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

    $(document).on("click", ".delete-activity-name", function (event) {
        event.preventDefault();
        const activityNameId = $(this).data("id");
        const confirmation = confirm(
            "Are you sure you want to delete this activity name?"
        );
        if (confirmation) {
            $.ajax({
                url: "activity-names/" + activityNameId,
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
                                "Activity name deleted successfully!"
                        );
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 5000);
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
                    let errorMsg = "Failed to delete activity name.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $(document).on("click", ".edit-activity-name", function (event) {
        event.preventDefault();
        const activityNameId = $(this).data("id");
        const button = event.currentTarget;
        $.ajax({
            url: "activity-names/" + activityNameId + "/edit",
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
                    const activityName = response.data;
                    $("#activityNameLabel").text(
                        "Edit Activity Name - " +
                            (activityName.claim_name ||
                                activityName.activity_name)
                    );
                    populateCategories(
                        $("#category_id"),
                        activityName.category_id
                    );
                    $("#activity_name").val(activityName.activity_name || "");
                    $("#description").val(activityName.description);
                    $("#from_month").val(
                        activityName.from_month
                            ? activityName.from_month.split(" ")[0]
                            : ""
                    );
                    $("#to_month").val(
                        activityName.to_month
                            ? activityName.to_month.split(" ")[0]
                            : ""
                    );
                    $("#from_year").val(activityName.from_year);
                    $("#to_year").val(activityName.to_year);
                    $("#approved_limit").val(activityName.approved_limit);
                    $("#approved_amount").val(activityName.approved_amount);
                    $("#is_active").prop("checked", activityName.status);
                    $("#saveActivityNameBtn").attr(
                        "data-activity-name-id",
                        activityName.id
                    );

                    const deptIds = activityName.dept_id
                        ? activityName.dept_id.split(",")
                        : [];
                    const verticalIds = activityName.vertical
                        ? activityName.vertical.split(",")
                        : [];
                    populateDepartments($("#dept_id"), deptIds);
                    populateVerticals($("#vertical"), verticalIds);
                    populateClaimTypes(
                        $("#activity_name"),
                        activityName.activity_name
                    );
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching activity name."
                    );
                }
            },
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message ||
                    "Failed to fetch activity name.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addActivityNameBtn", function (event) {
        event.preventDefault();
        $("#activityNameLabel").text("Add New Activity Name");
        $("#category_id").val("");
        $("#activity_name").val(null).trigger("change");
        $("#dept_id").val(null).trigger("change");
        $("#vertical").val(null).trigger("change");
        $("#description").val("");
        $("#from_month").val("");
        $("#to_month").val("");
        $("#from_year").val("");
        $("#to_year").val("");
        $("#approved_limit").val("");
        $("#approved_amount").val("");
        $("#is_active").prop("checked", true);
        $("#saveActivityNameBtn").removeAttr("data-activity-name-id");
        populateCategories($("#category_id"));
        populateDepartments($("#dept_id"));
        populateVerticals($("#vertical"));
        populateClaimTypes($("#activity_name"));
    });

    $("#activityNameModal").on("show.bs.modal", function (event) {
        populateCategories($("#category_id"));
        populateDepartments($("#dept_id"));
        populateVerticals($("#vertical"));
        populateClaimTypes($("#activity_name"));
    });
});
