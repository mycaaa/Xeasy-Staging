$(document).ready(function () {
    $("#permissionsMasterTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });
    $("#addPermissionBtn").click(function () {
        selectGroupList("groupDropdown");
    });
    $("#groupDropdown").change(function () {
        if ($(this).val() === "new") {
            $("#newGroupInput")
                .removeClass("d-none")
                .prop("required", true)
                .focus();
            $("#saveGroupBtn").removeClass("d-none");
        } else {
            $("#newGroupInput")
                .addClass("d-none")
                .prop("required", false)
                .val("");
            $("#saveGroupBtn").addClass("d-none");
        }
    });
    $("#saveGroupBtn").click(function (event) {
        const groupName = $("#newGroupInput").val().trim();
        if (groupName !== "") {
            $.ajax({
                url: "permission-groups",
                type: "POST",
                data: {
                    group_name: groupName,
                    _token: $('meta[name="csrf-token"]').attr("content"),
                },
                beforeSend: function () {
                    spinStartLoader(event);
                },
                success: function (response) {
                    if (response.success) {
                        showAlert(
                            "success",
                            "ri-checkbox-circle-line",
                            response.message || "Group saved successfully!"
                        );
                        $("#newGroupInput").val("");
                        selectGroupList("groupDropdown", response.data.id);
                    } else {
                        showAlert(
                            "danger",
                            "ri-error-warning-line",
                            response.message ||
                                "An error occurred while saving."
                        );
                    }
                },
                error: function (xhr) {
                    let errorMsg = "Failed to save group.";

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }

                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
                complete: function () {
                    spinEndLoader(event);
                },
            });
        } else {
            showAlert("warning", "ri-alert-line", "Please enter a group name.");
        }
    });
    $("#savePermissionBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;
        const formData = {
            permission_name: document.getElementById("permissionName").value,
            group_id: document.getElementById("groupDropdown").value,
            is_active: document.getElementById("is_active").checked ? 1 : 0,
            id: $(button).data("permission-id") || null,
        };
        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `permissions/${formData.id}` : "permissions";
        $.ajax({
            url: url,
            type: requestType,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            data: formData,
            dataType: "json",
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            success: function (response) {
                if (response.success) {
                    showAlert(
                        "success",
                        "ri-checkbox-circle-line",
                        response.message || "Permission saved successfully!"
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
            error: function (xhr, status, error) {
                let errorMsg = "Failed to save permission.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });
    $(document).on("click", ".delete-permission", function (event) {
        event.preventDefault();
        const permissionId = $(this).data("id");
        const confirmation = confirm(
            "Are you sure you want to delete this permission?"
        );
        if (confirmation) {
            $.ajax({
                url: "permissions/" + permissionId,
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
                                "Permission deleted successfully!"
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
                error: function (xhr, status, error) {
                    let errorMsg = "Failed to delete permission.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });
    $(document).on("click", ".edit-permission", function (event) {
        event.preventDefault();
        const permissionId = $(this).data("id");
        const button = event.currentTarget;
        $.ajax({
            url: "permissions/" + permissionId + "/edit",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            type: "GET",
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    const permission = response.data;
                    $("#addPermissionLabel").text(
                        "Edit Permission - " + permission.permission_key
                    );
                    $("#permissionName").val(permission.permission_key);
                    $("#groupDropdown").val(permission.permission_group_id);
                    $("#is_active").prop("checked", permission.status);
                    $("#savePermissionBtn").attr(
                        "data-permission-id",
                        permission.id
                    );
                    selectGroupList(
                        "groupDropdown",
                        permission.permission_group_id
                    );
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching permission."
                    );
                }
            },
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message || "Failed to fetch permission.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });
    $(document).on("change", ".permission-checkbox", function () {
        console.log("permission-checkbox");
        const role_id = $(this).data("role-id");
        const permission_id = $(this).data("permission-id");
        const isChecked = $(this).is(":checked");
        $.ajax({
            url: "/permissions/assign",
            type: "POST",
            contentType: "application/x-www-form-urlencoded; charset=UTF-8",
            data: {
                role_id: role_id,
                permission_id: permission_id,
                isChecked: isChecked ? "1" : "0",
                _token: $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    showAlert(
                        "success",
                        "ri-checkbox-circle-line",
                        response.message || "Permission updated successfully!"
                    );
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message ||
                            "An error occurred: " +
                                (response.message || "Unknown error")
                    );
                    $(this).prop("checked", !isChecked);
                }
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    "Failed to update permission. Please try again."
                );
                $(this).prop("checked", !isChecked);
            },
        });
    });
    function selectGroupList(feildId, id = null) {
        $.ajax({
            url: "permission-groups",
            type: "GET",
            dataType: "json",
            delay: 250,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (data) {
                const placeholderOption = new Option(
                    "Select a menu",
                    "",
                    false,
                    false
                );
                const addNewGroup = new Option(
                    "Add New Group",
                    "new",
                    false,
                    false
                );

                const options = data.data.map(function (item) {
                    const isSelected = item.id === id;
                    return new Option(
                        item.name,
                        item.id,
                        isSelected,
                        isSelected
                    );
                });

                $(`#${feildId}`)
                    .empty()
                    .append(placeholderOption)
                    .append(addNewGroup)
                    .append(options);

                if (id !== null) {
                    $(`#${feildId}`).val(id);
                }
            },
            error: function (xhr, status, error) {
                console.error("Failed to fetch groups:", error);
            },
        });
    }
});
