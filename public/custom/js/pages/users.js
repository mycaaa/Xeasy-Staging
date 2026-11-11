$(document).ready(function () {
    // Initialize DataTable with server-side processing
    const table = $("#usersMasterTable").DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "users/data",
            type: "POST",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            data: function (d) {
                d.status =
                    new URLSearchParams(window.location.search).get("status") ||
                    "active";
            },
            error: function (xhr, error, code) {
                console.error("DataTable AJAX error:", error);
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    "Failed to load data"
                );
            },
        },
        columns: [
            {
                data: null,
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                },
                orderable: false,
                searchable: false,
            },
            {
                data: "employee_id",
                name: "employee_id",
                render: function (data, type, row) {
                    return data || "-";
                },
            },
            {
                data: "emp_code",
                name: "emp_code",
                render: function (data, type, row) {
                    return data || "-";
                },
            },
            {
                data: "employee_name",
                name: "employee_name",
                render: function (data, type, row) {
                    return data || "-";
                },
            },
            {
                data: "emp_status",
                name: "emp_status",
                render: function (data, type, row) {
                    if (row.is_guest || data === 'Guest') {
                        return '<span class="badge bg-info-subtle text-info">Guest</span>';
                    }
                    if (data === 'A') {
                        return '<span class="badge bg-success-subtle text-success">Active</span>';
                    } else if (data === 'I' || data === 'D') {
                        return '<span class="badge bg-danger-subtle text-danger">Inactive</span>';
                    }
                    return '<span class="badge bg-secondary-subtle text-secondary">-</span>';
                },
            },
            {
                data: "company_name",
                name: "company_name",
                render: function (data, type, row) {
                    return data || "-";
                },
            },
            {
                data: "roles",
                name: "roles",
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    if (!data || data.length === 0) {
                        return '<span class="badge bg-secondary-subtle text-secondary">No Roles</span>';
                    }
                    return data
                        .map(
                            (role) =>
                                `<span class="badge bg-primary-subtle text-primary">${role.name}</span>`
                        )
                        .join(" ");
                },
            },
            {
                data: "status",
                name: "status",
                render: function (data, type, row) {
                    if (data == 1) {
                        return '<span class="badge bg-success-subtle text-success badge-border">Active</span>';
                    }
                    return '<span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>';
                },
            },
            {
                data: "actions",
                name: "actions",
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    let actions = "";

                    if (row.can_modify) {
                        actions += `<button type="button" data-bs-toggle="modal" data-bs-target="#userModal"
                            class="btn btn-primary btn-sm edit-user" data-id="${row.id}">
                            <i class="ri-edit-2-fill"></i>
                        </button> `;
                    }

                    if (row.can_manage_permissions) {
                        actions += `<button type="button" class="btn btn-info btn-sm permission-access-user"
                            data-bs-toggle="modal" data-bs-target="#permissionModal" data-id="${row.id}">
                            <i class="ri-lock-line"></i>
                        </button>`;
                    }

                    return actions || "-";
                },
            },
        ],
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        language: {
            emptyTable: "No users found",
            zeroRecords: "No matching users found",
        },
    });

    // Reload table when status filter changes
    $(".dropdown-item").on("click", function (e) {
        e.preventDefault();
        const url = $(this).attr("href");
        window.location.href = url;
    });

    // Rest of your existing code...
    $("#userModal").on("shown.bs.modal", function () {
        $("#role_id").select2({
            dropdownParent: $("#userModal"),
            width: "100%",
            placeholder: "Select a role",
        });
    });

    $("#userModal").on("hidden.bs.modal", function () {
        // Complete form and state reset when modal closes
        $("#user_id").val("");
        $("#is_guest").val(0);
        $("#guestFieldsWrap").hide();
        $("#employeeFieldsWrap").show();
        $("#employee_id").val("").removeAttr("readonly");
        $("#guest_name").val("");
        $("#guest_username").val("").removeAttr("readonly");
        $("#btnGenGuestUsername").show();
        $("#guest_email").val("");
        $("#guest_password").val("").attr("placeholder", "Min 6 characters");
        $("#guestPasswordLabel").html('Password <span class="text-danger">*</span>');
        $("#saveUserBtn").removeData("user-id").removeAttr("data-user-id");
        $("#is_active").prop("checked", true);
        $('.company-checkbox').prop('checked', false);
        $('#company_ids').val('');
        $('#selectAllCompanies').prop('checked', false);
        if ($("#role_id").hasClass("select2-hidden-accessible")) {
            $("#role_id").val(null).trigger("change");
        }
    });

    $("#addUserBtn").on("click", function () {
        $("#user_id").val("");
        $("#is_guest").val(0);
        $("#guestFieldsWrap").hide();
        $("#employeeFieldsWrap").show();
        $("#employee_id").val("").removeAttr("readonly");
        $("#saveUserBtn").removeData("user-id").removeAttr("data-user-id");
        $("#addUserModalTitle").text("Add New Employee User");
        $("#is_active").prop("checked", true);
        $('.company-checkbox').prop('checked', false);
        $('#company_ids').val('');
        $('#selectAllCompanies').prop('checked', false);
        selectRoleList("role_id");
    });

    $("#addGuestUserBtn").on("click", function () {
        $("#user_id").val("");
        $("#is_guest").val(1);
        $("#guestFieldsWrap").show();
        $("#employeeFieldsWrap").hide();
        $("#guest_name").val("");
        const rnd = Math.floor(1000 + Math.random() * 9000);
        $("#guest_username").val("guest_" + rnd).removeAttr("readonly");
        $("#btnGenGuestUsername").show();
        $("#guest_email").val("");
        $("#guest_password").val("").attr("placeholder", "Min 6 characters");
        $("#guestPasswordLabel").html('Password <span class="text-danger">*</span>');
        $("#saveUserBtn").removeData("user-id").removeAttr("data-user-id");
        $("#addUserModalTitle").text("Add New Guest User");
        $("#is_active").prop("checked", true);
        $('.company-checkbox').prop('checked', false);
        $('.company-checkbox[value="1"]').prop('checked', true);
        $('#company_ids').val('1');
        $('#selectAllCompanies').prop('checked', false);
        selectRoleList("role_id");
    });

    // Toggle guest password visibility
    $("#btnToggleGuestPass").on("click", function () {
        const input = $("#guest_password");
        const icon = $("#togglePassIcon");
        if (input.attr("type") === "password") {
            input.attr("type", "text");
            icon.removeClass("ri-eye-line").addClass("ri-eye-off-line");
        } else {
            input.attr("type", "password");
            icon.removeClass("ri-eye-off-line").addClass("ri-eye-line");
        }
    });

    // Auto generate random password
    $("#btnGenGuestPass").on("click", function () {
        const chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789@#$!";
        let pass = "";
        for (let i = 0; i < 10; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        $("#guest_password").attr("type", "text").val(pass);
        $("#togglePassIcon").removeClass("ri-eye-line").addClass("ri-eye-off-line");
    });

    // Auto generate guest username
    $("#btnGenGuestUsername").on("click", function () {
        const rnd = Math.floor(1000 + Math.random() * 9000);
        $("#guest_username").val("guest_" + rnd);
    });

    $("#saveUserBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;
        const isGuest = $("#is_guest").val() == "1";
        const userId = $("#user_id").val() || null;

        const formData = {
            is_guest: isGuest ? 1 : 0,
            role_id: $("#role_id").val(),
            is_active: $("#is_active").is(":checked") ? 1 : 0,
            id: userId,
            company_id: $('#company_ids').val()
        };

        if (isGuest) {
            formData.name = $.trim($("#guest_name").val());
            formData.email = $.trim($("#guest_email").val());
            const passVal = $("#guest_password").val();

            if (!formData.name) {
                showAlert("danger", "ri-error-warning-line", "Please enter guest user full name.");
                return;
            }
            // Only require and send username for new user creation
            if (!formData.id) {
                formData.username = $.trim($("#guest_username").val());
                if (!formData.username) {
                    showAlert("danger", "ri-error-warning-line", "Please enter guest username / ID.");
                    return;
                }
            }
            if (!formData.id && (!passVal || passVal.length < 6)) {
                showAlert("danger", "ri-error-warning-line", "Password is required and must be at least 6 characters.");
                return;
            }
            if (passVal && passVal.length > 0) {
                if (passVal.length < 6) {
                    showAlert("danger", "ri-error-warning-line", "Password must be at least 6 characters.");
                    return;
                }
                formData.password = passVal;
            }
            if (!formData.company_id) {
                showAlert("danger", "ri-error-warning-line", "Please select at least one company access.");
                return;
            }
        } else {
            formData.employee_id = $.trim($("#employee_id").val());
            if (!formData.employee_id) {
                showAlert("danger", "ri-error-warning-line", "Please enter Employee ID.");
                return;
            }
        }

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `users/${formData.id}` : "users";

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
                        response.message || "User saved successfully!"
                    );
                    $("#userModal").modal("hide");
                    table.ajax.reload(null, false); // Reload table without resetting pagination
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "An error occurred while saving."
                    );
                }
            },
            error: function (xhr, status, error) {
                let errorMsg = "Failed to save user.";
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

    $(document).on("click", ".edit-user", function (event) {
        event.preventDefault();
        const userId = $(this).attr("data-id") || $(this).data("id");
        $("#user_id").val(userId);
        const button = event.currentTarget;

        $.ajax({
            url: "users/" + userId + "/edit",
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
                    const user = response.data;
                    const isGuest = user.is_guest || false;

                    $("#user_id").val(userId);
                    $("#is_guest").val(isGuest ? 1 : 0);
                    $("#saveUserBtn").removeData("user-id").removeAttr("data-user-id");
                    $("#is_active").prop("checked", user.status == 1);

                    if (isGuest) {
                        $("#guestFieldsWrap").show();
                        $("#employeeFieldsWrap").hide();
                        $("#addUserModalTitle").text("Edit Guest User - " + (user.employee_id || user.name));
                        $("#guest_name").val(user.name || "");
                        $("#guest_username").val(user.employee_id || "").attr("readonly", true);
                        $("#btnGenGuestUsername").hide();
                        $("#guest_email").val(user.email || "");
                        $("#guest_password").val("").attr("placeholder", "Leave blank to keep current password");
                        $("#guestPasswordLabel").html('Password <small class="text-muted">(Optional for update)</small>');
                    } else {
                        $("#guestFieldsWrap").hide();
                        $("#employeeFieldsWrap").show();
                        $("#addUserModalTitle").text("Edit User - Employee ID: " + user.employee_id);
                        $("#employee_id").val(user.employee_id).attr("readonly", true);
                    }

                    // ✅ Set hidden input
                    $("#company_ids").val(user.company_access_id);

                    // ✅ Role select
                    const selectedRoles = user.role_ids || [];
                    selectRoleList("role_id", selectedRoles);

                    // ===============================
                    // ✅ CHECK COMPANY CHECKBOXES
                    // ===============================

                    let companyIds = user.company_access_id
                        ? user.company_access_id.split(',')
                        : [];

                    // Uncheck all first
                    $('.company-checkbox').prop('checked', false);

                    // Check based on data
                    companyIds.forEach(function (id) {
                        $('.company-checkbox[value="' + id + '"]').prop('checked', true);
                    });

                    // Handle Select All
                    $('#selectAllCompanies').prop(
                        'checked',
                        $('.company-checkbox:checked').length === $('.company-checkbox').length
                    );

                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching user."
                    );
                }
            },
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message || "Failed to fetch user.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });

    $(document).on("click", ".delete-user", function (event) {
        event.preventDefault();
        const userId = $(this).data("id");
        const confirmation = confirm(
            "Are you sure you want to delete this user?"
        );
        if (confirmation) {
            $.ajax({
                url: "users/" + userId,
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
                            response.message || "user deleted successfully!"
                        );
                        table.ajax.reload(null, false); // Reload table
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
                    let errorMsg = "Failed to delete user.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $("#importEmployee").click(function (event) {
        event.preventDefault();
        $("#importEmployeeModal").modal("show");
    });

    $("#confirmImportBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        $.ajax({
            url: "users/import",
            type: "POST",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            dataType: "json",
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            success: function (response) {
                if (response.success) {
                    $("#importEmployeeModal").modal("hide");
                    showAlert(
                        "success",
                        "ri-checkbox-circle-line",
                        response.message || "Employees imported successfully!"
                    );
                    table.ajax.reload(null, false); // Reload table
                } else {
                    $("#importEmployeeModal").modal("hide");
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message ||
                        "An error occurred while importing employees."
                    );
                }
            },
            error: function (xhr, status, error) {
                $("#importEmployeeModal").modal("hide");
                let errorMsg = "Failed to import employees.";
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

    $(document).on("click", ".permission-access-user", function (event) {
        const userId = $(this).data("id");
        const button = event.currentTarget;
        let userPermissions = [];

        $.ajax({
            url: `users/${userId}/permissions`,
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            success: function (response) {
                if (response.success) {
                    const groupedPermissions = response.data.permissions;
                    const assignedClaimTypes =
                        response.data.assigned_claim_types || [];
                    const userInfo = response.data.user;
                    $("#userNameTitle").text(` - ${userInfo.name}`);
                    userPermissions = [];
                    Object.keys(groupedPermissions).forEach((group) => {
                        groupedPermissions[group].forEach((permission) => {
                            userPermissions.push(permission.id);
                        });
                    });
                    $.ajax({
                        url: "permissions-list",
                        type: "GET",
                        dataType: "json",
                        headers: {
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                                "content"
                            ),
                        },
                        success: function (permResponse) {
                            if (permResponse.success) {
                                const categories = permResponse.data;
                                const container = $("#permissions-container");
                                container.empty();

                                if (Object.keys(categories).length === 0) {
                                    container.append(
                                        "<p>No permissions available.</p>"
                                    );
                                    return;
                                }

                                Object.keys(categories).forEach(
                                    (category, catIndex) => {
                                        const categoryId = `category-accor-${catIndex}`;
                                        let categoryHtml = `
                                    <div class="accordion-item material-shadow${catIndex > 0 ? " mt-2" : ""
                                            }">
                                        <h2 class="accordion-header" id="category-header-${catIndex}">
                                            <button class="accordion-button${catIndex === 0
                                                ? ""
                                                : " collapsed"
                                            }" type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#${categoryId}" 
                                                    aria-expanded="${catIndex === 0
                                                ? "true"
                                                : "false"
                                            }" 
                                                    aria-controls="${categoryId}">
                                                ${category}
                                            </button>
                                        </h2>
                                        <div id="${categoryId}" 
                                             class="accordion-collapse collapse${catIndex === 0 ? " show" : ""
                                            }" 
                                             aria-labelledby="category-header-${catIndex}" 
                                             data-bs-parent="#permissions-container">
                                            <div class="accordion-body">
                                                <div class="accordion nesting-accordion custom-accordionwithicon-plus">
                                `;

                                        Object.keys(
                                            categories[category]
                                        ).forEach((group, groupIndex) => {
                                            const permissions =
                                                categories[category][group];
                                            const accordionId = `role-accor-plus-${catIndex}-${groupIndex}`;
                                            let groupHtml = `
                                        <div class="accordion-item material-shadow${groupIndex > 0 ? " mt-2" : ""
                                                }">
                                            <h2 class="accordion-header" id="role-accordionwithplus-${catIndex}-${groupIndex}">
                                                <button class="accordion-button${groupIndex === 0
                                                    ? ""
                                                    : " collapsed"
                                                }" type="button" 
                                                        data-bs-toggle="collapse" 
                                                        data-bs-target="#${accordionId}" 
                                                        aria-expanded="${groupIndex === 0
                                                    ? "true"
                                                    : "false"
                                                }" 
                                                        aria-controls="${accordionId}">
                                                    ${group}
                                                </button>
                                            </h2>
                                            <div id="${accordionId}" 
                                                 class="accordion-collapse collapse${groupIndex === 0
                                                    ? " show"
                                                    : ""
                                                }" 
                                                 aria-labelledby="role-accordionwithplus-${catIndex}-${groupIndex}" 
                                                 data-bs-parent="#${categoryId}">
                                                <div class="accordion-body">
                                                    <div class="row">
                                    `;

                                            permissions.forEach(
                                                (permission) => {
                                                    const isChecked =
                                                        userPermissions.includes(
                                                            permission.id
                                                        );
                                                    groupHtml += `
                                            <div class="col-4 mb-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>${permission.permission_key
                                                        }</span>
                                                    <div class="form-check form-switch" style="margin-top: 0px;">
                                                        <input class="form-check-input permission-toggle" 
                                                               type="checkbox" 
                                                               role="switch" 
                                                               id="toggle-${permission.id
                                                        }" 
                                                               data-user-id="${userId}" 
                                                               data-permission-id="${permission.id
                                                        }" 
                                                               ${isChecked
                                                            ? "checked"
                                                            : ""
                                                        }>
                                                        <label class="form-check-label" for="toggle-${permission.id
                                                        }"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                                }
                                            );

                                            groupHtml += `
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                            categoryHtml += groupHtml;
                                        });

                                        categoryHtml += `
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                        container.append(categoryHtml);
                                    }
                                );
                            }
                        },
                        error: function (xhr) {
                            console.error("Failed to fetch permissions:", xhr);
                            showAlert(
                                "danger",
                                "ri-error-warning-line",
                                "Failed to fetch permissions."
                            );
                        },
                        complete: function () {
                            endLoader({
                                currentTarget: button,
                            });
                        },
                    });
                    $.ajax({
                        url: "get-claim-types",
                        method: "GET",
                        headers: {
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                                "content"
                            ),
                        },
                        success: function (response) {
                            if (response.success && response.data) {
                                const container = $("#claim-type-container");
                                container.empty();

                                response.data.forEach((category, catIndex) => {
                                    const categoryId = `claim-category-${catIndex}`;

                                    let categoryHtml = `
                                        <div class="accordion-item material-shadow${catIndex > 0 ? " mt-2" : ""
                                        }">
                                            <h2 class="accordion-header" id="claim-header-${catIndex}">
                                                <button class="accordion-button ${catIndex === 0
                                            ? ""
                                            : "collapsed"
                                        }" 
                                                    type="button" data-bs-toggle="collapse" data-bs-target="#${categoryId}">
                                                    ${category.text}
                                                </button>
                                            </h2>
                                            <div id="${categoryId}" class="accordion-collapse collapse ${catIndex === 0 ? "show" : ""
                                        }">
                                                <div class="accordion-body">
                                                    <div class="row">
                                    `;

                                    category.children.forEach((claim) => {
                                        const colClass =
                                            category.text
                                                .trim()
                                                .toLowerCase() === "activity"
                                                ? "col-6"
                                                : "col-4";
                                        const isChecked =
                                            assignedClaimTypes.includes(
                                                claim.id
                                            )
                                                ? "checked"
                                                : "";
                                        categoryHtml += `
                                              <div class="${colClass} mb-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>${claim.text}</span>
                                                    <div class="form-check form-switch" style="margin-top:0px;">
                                                        <input class="form-check-input claim-type-toggle"
                                                               type="checkbox"
                                                               data-user-id="${userId}"
                                                               data-claim-id="${claim.id}"
                                                               ${isChecked}>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                    });

                                    categoryHtml += `
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `;

                                    container.append(categoryHtml);
                                });
                            }
                        },
                    });
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching permissions."
                    );
                }
            },
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message || "Failed to fetch permissions.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });

    $(document).on("change", ".permission-toggle", function () {
        const userId = $(this).data("user-id");
        const permissionId = $(this).data("permission-id");
        const isChecked = $(this).is(":checked");
        const actionUrl = isChecked
            ? `users/${userId}/permissions/assign`
            : `users/${userId}/permissions/revoke`;

        $.ajax({
            url: actionUrl,
            type: "POST",
            data: { permission_id: permissionId },
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    showAlert(
                        "success",
                        "ri-check-line",
                        response.message ||
                        (isChecked
                            ? "Permission assigned successfully."
                            : "Permission revoked successfully.")
                    );
                    $(
                        '.permission-access-user[data-id="' + userId + '"]'
                    ).trigger("click");
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message ||
                        (isChecked
                            ? "Error assigning permission."
                            : "Error revoking permission.")
                    );
                    $(this).prop("checked", !isChecked);
                }
            }.bind(this),
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message ||
                    (isChecked
                        ? "Failed to assign permission."
                        : "Failed to revoke permission.");
                showAlert("danger", "ri-error-warning-line", errorMsg);
                $(this).prop("checked", !isChecked);
            }.bind(this),
        });
    });

    $(document).on("change", ".claim-type-toggle", function () {
        const userId = $(this).data("user-id");
        const claimTypeId = $(this).data("claim-id");
        const isChecked = $(this).is(":checked");

        const actionUrl = isChecked
            ? `users/${userId}/claim-types/assign`
            : `users/${userId}/claim-types/revoke`;

        $.ajax({
            url: actionUrl,
            type: "POST",
            data: { claim_type_id: claimTypeId },
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                showAlert(
                    "success",
                    "ri-check-line",
                    response.message ||
                    (isChecked
                        ? "Claim type assigned successfully."
                        : "Claim type revoked successfully.")
                );
            },
            error: function () {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    isChecked
                        ? "Failed to assign claim type."
                        : "Failed to revoke claim type."
                );
                $(this).prop("checked", !isChecked);
            }.bind(this),
        });
    });

    function selectRoleList(fieldId, ids = []) {
        $.ajax({
            url: "get-roles-list",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (data) {
                const options = data.data.map(function (item) {
                    const isSelected = ids.includes(item.id);
                    return new Option(
                        item.name,
                        item.id,
                        isSelected,
                        isSelected
                    );
                });
                $(`#${fieldId}`).empty().append(options);
                if (ids.length > 0) {
                    $(`#${fieldId}`).val(ids);
                }
                $(`#${fieldId}`).trigger("change");
            },
            error: function (xhr, status, error) {
                console.error("Failed to fetch groups:", error);
            },
        });
    }
    // Select All toggle
    $('#selectAllCompanies').on('change', function () {
        $('.company-checkbox').prop('checked', this.checked);
        updateCompanyIds();
    });

    // Individual checkbox change
    $('.company-checkbox').on('change', function () {

        // If any unchecked → uncheck select all
        if (!this.checked) {
            $('#selectAllCompanies').prop('checked', false);
        }

        // If all checked → check select all
        if ($('.company-checkbox:checked').length === $('.company-checkbox').length) {
            $('#selectAllCompanies').prop('checked', true);
        }

        updateCompanyIds();
    });

    // Update hidden input
    function updateCompanyIds() {
        let selected = [];

        $('.company-checkbox:checked').each(function () {
            selected.push($(this).val());
        });

        $('#company_ids').val(selected.join(','));
    }


});
