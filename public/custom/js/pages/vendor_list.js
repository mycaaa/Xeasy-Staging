$(document).ready(function () {
    $("#vendorTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });

    function loadVendorTypes(selectedType = "others") {
        $.ajax({
            url: "/vendor-types",

            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    const vendorTypeSelect = $("#vendor_type");
                    vendorTypeSelect.empty();
                    $.each(response.data, function (index, type) {
                        const isSelected = type.vendor_type_id === selectedType ? "selected" : "";
                        vendorTypeSelect.append(`<option value="${type.id}" ${isSelected}>${type.vendor_type}</option>`);
                    });
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "Failed to load vendor types.");
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON?.message || "Failed to load vendor types.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
        });
    }

    loadVendorTypes();

    $("#saveVendorBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            vendor_type: $("#vendor_type").val(),
            vendor_name: $("#vendor_name").val(),
            vendor_address: $("#vendor_address").val(),
            contact_person: $("#contact_person").val(),
            contact_number: $("#contact_number").val(),
            email: $("#email").val(),
            gst_no: $("#gst_no").val(),
            status: $("#is_active").is(":checked") ? "1" : "0",
            request_status: $("#request_status").val(),
            id: $(button).data("vendor-id") || null,
        };

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `vendors/${formData.id}` : "vendors";

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
                    showAlert("success", "ri-checkbox-circle-line", response.message || "Vendor saved successfully!");
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 2000);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while saving.");
                }
            },
            error: function (xhr) {
                let errorMsg = "Failed to save vendor.";
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

    $(document).on("click", ".delete-vendor", function (event) {
        event.preventDefault();
        const vendorId = $(this).data("id");
        const confirmation = confirm("Are you sure you want to delete this vendor?");
        if (confirmation) {
            $.ajax({
                url: "vendors/" + vendorId,
                type: "DELETE",
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    if (response.success) {
                        showAlert("success", "ri-checkbox-circle-line", response.message || "Vendor deleted successfully!");
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 5000);
                    } else {
                        showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while deleting.");
                    }
                },
                error: function (xhr) {
                    let errorMsg = "Failed to delete vendor.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $(document).on("click", ".edit-vendor", function (event) {
        event.preventDefault();
        const vendorId = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "vendors/" + vendorId + "/edit",
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
                    const vendor = response.data;
                    $("#vendorLabel").text("Edit Vendor - " + vendor.vendor_name);
                    $("#vendor_name").val(vendor.vendor_name);
                    loadVendorTypes(vendor.vendor_type);

                    $("#vendor_address").val(vendor.vendor_address);
                    $("#contact_person").val(vendor.contact_person);
                    $("#contact_number").val(vendor.contact_number);
                    $("#email").val(vendor.email);
                    $("#gst_no").val(vendor.gst_no);
                    $("#is_active").prop("checked", vendor.status == "1");
                    $("#request_status").val(vendor.request_status || "P");
                    $("#saveVendorBtn").attr("data-vendor-id", vendor.id);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "Error fetching vendor.");
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON?.message || "Failed to fetch vendor.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addVendorBtn", function (event) {
        event.preventDefault();
        $("#vendorLabel").text("Add New Vendor");
        $("#vendor_name").val("");
        loadVendorTypes("others");

        $("#vendor_address").val("");
        $("#contact_person").val("");
        $("#contact_number").val("");
        $("#email").val("");
        $("#gst_no").val("");
        $("#is_active").prop("checked", true);
        $("#request_status").val("P");
        $("#saveVendorBtn").removeAttr("data-vendor-id");
    });
});
