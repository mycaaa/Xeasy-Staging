$(document).ready(function () {
    // Get data from hidden script tags
    const claimTypes = JSON.parse(document.getElementById('claimTypesData').textContent);
    const expenseHeadCategories = JSON.parse(document.getElementById('expenseHeadCategoriesData').textContent);

    $("#claimFocusMappingTable").DataTable({
        ordering: true,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, "asc"]],
    });

    $("#saveClaimFocusMappingBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            claim_id: $("#claim_id").val() || null,
            expense_head_category_id: $("#expense_head_category_id").val() || null,
            wtype: $("#wtype").val() || null,
            focus_name_subl: $("#focus_name_subl").val().trim(),
            focus_name: $("#focus_name").val().trim(),
            focus_ledger_code: $("#focus_ledger_code").val().trim(),
            is_active: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("claim-focus-mapping-id") || null,
        };

        if (
            !formData.claim_id ||
            !formData.focus_name_subl ||
            !formData.focus_name ||
            !formData.focus_ledger_code
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
            ? `claim-focus-mappings/${formData.id}`
            : "claim-focus-mappings";

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
                            "Claim focus mapping saved successfully!"
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
                let errorMessage = "Failed to save claim focus mapping.";
                if (xhr.responseJSON?.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON?.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join(', ');
                }
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    errorMessage
                );
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", ".delete-claim-focus-mapping", function (event) {
        event.preventDefault();
        const id = $(this).data("id");
        if (
            confirm("Are you sure you want to delete this claim focus mapping?")
        ) {
            $.ajax({
                url: "claim-focus-mappings/" + id,
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
                                "Claim focus mapping deleted successfully!"
                        );
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 2000);
                    }
                },
                error: function (xhr) {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        xhr.responseJSON?.message ||
                            "Failed to delete claim focus mapping."
                    );
                },
            });
        }
    });

    $(document).on("click", ".edit-claim-focus-mapping", function (event) {
        event.preventDefault();
        const id = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "claim-focus-mappings/" + id + "/edit",
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
                    const claimName = mapping.claim_type ? mapping.claim_type.ClaimName : 'Unknown';
                    
                    $("#claimFocusMappingLabel").text(
                        "Edit Claim Focus Mapping - " + claimName
                    );
                    $("#claim_id").val(mapping.claim_id);
                    $("#expense_head_category_id").val(mapping.expense_head_category_id || "");
                    $("#wtype").val(mapping.wtype || "");
                    $("#focus_name_subl").val(mapping.focus_name_subl);
                    $("#focus_name").val(mapping.focus_name);
                    $("#focus_ledger_code").val(mapping.focus_ledger_code);
                    $("#is_active").prop("checked", mapping.is_active);
                    $("#saveClaimFocusMappingBtn").attr(
                        "data-claim-focus-mapping-id",
                        mapping.id
                    );
                }
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message ||
                        "Failed to fetch claim focus mapping."
                );
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addClaimFocusMappingBtn", function (event) {
        event.preventDefault();
        $("#claimFocusMappingLabel").text("Add New Claim Focus Mapping");
        $("#claim_id").val("");
        $("#expense_head_category_id").val("");
        $("#wtype").val("");
        $("#focus_name_subl").val("");
        $("#focus_name").val("");
        $("#focus_ledger_code").val("");
        $("#is_active").prop("checked", true);
        $("#saveClaimFocusMappingBtn").removeAttr(
            "data-claim-focus-mapping-id"
        );
    });

    // Helper function to get claim name by ID
    function getClaimNameById(claimId) {
        const claim = claimTypes.find(c => c.ClaimId == claimId);
        return claim ? claim.ClaimName : 'Unknown';
    }

    // Helper function to get expense head name by ID
    function getExpenseHeadNameById(categoryId) {
        if (!categoryId) return 'All';
        const category = expenseHeadCategories.find(c => c.id == categoryId);
        return category ? category.expense_head_name : 'Unknown';
    }
});
