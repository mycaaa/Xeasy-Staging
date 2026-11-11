
$(document).ready(function () {
    $("#claimTypeTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });

    function populateGroups(selectElement) {
        $.ajax({
            url: "claim-type/groups", 
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    selectElement.empty();
                    selectElement.append('<option value="">Select Group</option>');
                    response.data.forEach(function (group) {
                        selectElement.append(`<option value="${group.cgId}">${group.cgName}</option>`);
                    });
                }
            },
            error: function (xhr) {
                console.error("Failed to fetch groups:", xhr);
            },
        });
    }

    $("#saveClaimTypeBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            claim_name: $("#claim_name").val(),
            claim_code: $("#claim_code").val(),
            focus_code: $("#focus_code").val(),
            color_code: $("#color_code").val(),
            cg_id: $("#cg_id").val(),
            is_active: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("claim-type-id") || null,
        };

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `claim-types/${formData.id}` : "claim-types";

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
                    showAlert("success", "ri-checkbox-circle-line", response.message || "Claim type saved successfully!");
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 2000);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while saving.");
                }
            },
            error: function (xhr, status, error) {
                let errorMsg = "Failed to save claim type.";
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

    $(document).on("click", ".delete-claim-type", function (event) {
        event.preventDefault();
        const claimTypeId = $(this).data("id");
        const confirmation = confirm("Are you sure you want to delete this claim type?");
        if (confirmation) {
            $.ajax({
                url: "claim-types/" + claimTypeId,
                type: "DELETE",
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    if (response.success) {
                        showAlert("success", "ri-checkbox-circle-line", response.message || "Claim type deleted successfully!");
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 5000);
                    } else {
                        showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while deleting.");
                    }
                },
                error: function (xhr, status, error) {
                    let errorMsg = "Failed to delete claim type.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $(document).on("click", ".edit-claim-type", function (event) {
        event.preventDefault();
        const claimTypeId = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "claim-types/" + claimTypeId + "/edit",
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
                    const claimType = response.data;
                    $("#claimTypeLabel").text("Edit Claim Type - " + claimType.ClaimName);
                    $("#claim_name").val(claimType.ClaimName);
                    $("#claim_code").val(claimType.ClaimCode);
                    $("#focus_code").val(claimType.focus_code);
                    $("#color_code").val(claimType.color_code || "#000000");
                    $("#cg_id").val(claimType.cgId);
                    $("#is_active").prop("checked", claimType.ClaimStatus === 'A' || claimType.ClaimStatus === 'B');
                    $("#saveClaimTypeBtn").attr("data-claim-type-id", claimType.ClaimId);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "Error fetching claim type.");
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON?.message || "Failed to fetch claim type.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addClaimTypeBtn", function (event) {
        event.preventDefault();
        $("#claimTypeLabel").text("Add New Claim Type");
        $("#claim_name").val("");
        $("#claim_code").val("");
        $("#focus_code").val("");
        $("#color_code").val("#000000");
        $("#cg_id").val("");
        $("#is_active").prop("checked", true);
        $("#saveClaimTypeBtn").removeAttr("data-claim-type-id");

        populateGroups($("#cg_id"));
    });

    $('#claimTypeModal').on('show.bs.modal', function (event) {
        populateGroups($("#cg_id"));
    });
});