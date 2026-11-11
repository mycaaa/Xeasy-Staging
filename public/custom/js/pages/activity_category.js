$(document).ready(function () {
    $("#activityCategoryTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });


    $("#saveActivityCategoryBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            category_name: $("#category_name").val(),
            mapped_activity: $("#mapped_activity").val(),
            description: $("#description").val(),
            status: $("#is_active").is(":checked") ? 1 : 0,
            id: $(button).data("activity-category-id") || null,
        };

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `activity-categories/${formData.id}` : "activity-categories";

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
                    showAlert("success", "ri-checkbox-circle-line", response.message || "Activity category saved successfully!");
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 2000);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while saving.");
                }
            },
            error: function (xhr) {
                let errorMsg = "Failed to save activity category.";
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

    $(document).on("click", ".delete-activity-category", function (event) {
        event.preventDefault();
        const activityCategoryId = $(this).data("id");
        const confirmation = confirm("Are you sure you want to delete this activity category?");
        if (confirmation) {
            $.ajax({
                url: "activity-categories/" + activityCategoryId,
                type: "DELETE",
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    if (response.success) {
                        showAlert("success", "ri-checkbox-circle-line", response.message || "Activity category deleted successfully!");
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 5000);
                    } else {
                        showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while deleting.");
                    }
                },
                error: function (xhr) {
                    let errorMsg = "Failed to delete activity category.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $(document).on("click", ".edit-activity-category", function (event) {
        event.preventDefault();
        const activityCategoryId = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "activity-categories/" + activityCategoryId + "/edit",
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
                    const activityCategory = response.data;
                    $("#activityCategoryLabel").text("Edit Activity Category - " + activityCategory.category_name);
                    $("#category_name").val(activityCategory.category_name);
                    $("#mapped_activity").val(activityCategory.mapped_activity);
                    $("#description").val(activityCategory.description);
                    $("#is_active").prop("checked", activityCategory.status);
                    $("#saveActivityCategoryBtn").attr("data-activity-category-id", activityCategory.id);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "Error fetching activity category.");
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON?.message || "Failed to fetch activity category.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $(document).on("click", "#addActivityCategoryBtn", function (event) {
        event.preventDefault();
        $("#activityCategoryLabel").text("Add New Activity Category");
        $("#category_name").val("");
        $("#mapped_activity").val("N");
        $("#description").val("");
        $("#is_active").prop("checked", true);
        $("#saveActivityCategoryBtn").removeAttr("data-activity-category-id");
    });
});