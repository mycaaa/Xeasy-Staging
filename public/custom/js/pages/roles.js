$(document).ready(function () {
    $("#rolesMasterTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });
    $("#saveRoleBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const permissionIds = [];
        $(".permission-toggle:checked").each(function () {
            permissionIds.push($(this).data("permission-id"));
        });
        const formData = {
            role_name: document.getElementById("role_name").value,
            is_active: document.getElementById("is_active").checked ? 1 : 0,
            id: $(button).data("role-id") || null,
            permissions: permissionIds,
        };

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `roles/${formData.id}` : "roles";

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
                        response.message || "Role saved successfully!"
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
                let errorMsg = "Failed to save role.";
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
    $(".delete-role").click(function (event) {
        event.preventDefault();
        const roleId = $(this).data("id");
        const confirmation = confirm(
            "Are you sure you want to delete this role?"
        );
        if (confirmation) {
            $.ajax({
                url: "roles/" + roleId,
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
                            response.message || "Role deleted successfully!"
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
                    let errorMsg = "Failed to delete role.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });
    $(".edit-role").click(function (event) {
        event.preventDefault();
        const roleId = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "roles/" + roleId + "/edit",
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
                    const role = response.data;
                    $("#addRoleLabel").text("Edit Role - " + role.name);
                    $("#role_name").val(role.name);
                    $("#is_active").prop("checked", role.status);
                    $("#saveRoleBtn").attr("data-role-id", role.id);

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
                                const tableContainer = $(
                                    "#allPermissionsContainer"
                                );
                                tableContainer.empty();

                                if (Object.keys(categories).length === 0) {
                                    tableContainer.append(
                                        "<p>No permissions available.</p>"
                                    );
                                    return;
                                }

                                Object.keys(categories).forEach(
                                    (category, catIndex) => {
                                        const categoryId = `category-accor-${catIndex}`;
                                        let categoryHtml = `
                                    <div class="accordion-item material-shadow">
                                        <h2 class="accordion-header" id="category-header-${catIndex}">
                                            <button class="accordion-button ${
                                                catIndex === 0
                                                    ? ""
                                                    : "collapsed"
                                            }" type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#${categoryId}" 
                                                    aria-expanded="${
                                                        catIndex === 0
                                                            ? "true"
                                                            : "false"
                                                    }" 
                                                    aria-controls="${categoryId}">
                                                ${category}
                                            </button>
                                        </h2>
                                        <div id="${categoryId}" 
                                             class="accordion-collapse collapse ${
                                                 catIndex === 0 ? "show" : ""
                                             }" 
                                             aria-labelledby="category-header-${catIndex}" 
                                             data-bs-parent="#allPermissionsContainer">
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
                                            <div class="accordion-item material-shadow">
                                                <h2 class="accordion-header" id="role-accordionwithplus-${catIndex}-${groupIndex}">
                                                    <button class="accordion-button ${
                                                        groupIndex === 0
                                                            ? ""
                                                            : "collapsed"
                                                    }" type="button" 
                                                            data-bs-toggle="collapse" 
                                                            data-bs-target="#${accordionId}" 
                                                            aria-expanded="${
                                                                groupIndex === 0
                                                                    ? "true"
                                                                    : "false"
                                                            }" 
                                                            aria-controls="${accordionId}">
                                                        ${group}
                                                    </button>
                                                </h2>
                                                <div id="${accordionId}" 
                                                     class="accordion-collapse collapse ${
                                                         groupIndex === 0
                                                             ? "show"
                                                             : ""
                                                     }" 
                                                     aria-labelledby="role-accordionwithplus-${catIndex}-${groupIndex}" 
                                                     data-bs-parent="#category-accor-${catIndex}">
                                                    <div class="accordion-body">
                                                        <div class="row">
                                        `;

                                            permissions.forEach(
                                                (permission) => {
                                                    const isChecked =
                                                        role.permissions.includes(
                                                            permission.id
                                                        );
                                                    groupHtml += `
                                                <div class="col-4 mb-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span>${
                                                            permission.permission_key
                                                        }</span>
                                                        <div class="form-check form-switch" style="margin-top: 0px;">
                                                            <input class="form-check-input permission-toggle" 
                                                                   type="checkbox" 
                                                                   role="switch" 
                                                                   id="role-toggle-${
                                                                       permission.id
                                                                   }" 
                                                                   data-permission-id="${
                                                                       permission.id
                                                                   }" 
                                                                   ${
                                                                       isChecked
                                                                           ? "checked"
                                                                           : ""
                                                                   }>
                                                            <label class="form-check-label" for="role-toggle-${
                                                                permission.id
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
                                    </div>
                                `;
                                        tableContainer.append(categoryHtml);
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
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching role."
                    );
                }
            },
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message || "Failed to fetch role.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });
    $(document).on("click", "#addRoleBtn", function (event) {
        event.preventDefault();
        $("#addRoleLabel").text("Add New Role");
        $("#role_name").val("");
        $("#is_active").prop("checked", true);
        $("#saveRoleBtn").removeAttr("data-role-id");
        const button = event.currentTarget;

        $.ajax({
            url: "permissions-list",
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
                    const categories = response.data;
                    const tableContainer = $("#allPermissionsContainer");
                    tableContainer.empty();

                    if (Object.keys(categories).length === 0) {
                        tableContainer.append(
                            "<p>No permissions available.</p>"
                        );
                        return;
                    }

                    Object.keys(categories).forEach((category, catIndex) => {
                        const categoryId = `category-accor-${catIndex}`;
                        let categoryHtml = `
                    <div class="accordion-item material-shadow">
                        <h2 class="accordion-header" id="category-header-${catIndex}">
                            <button class="accordion-button ${
                                catIndex === 0 ? "" : "collapsed"
                            }" type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#${categoryId}" 
                                    aria-expanded="${
                                        catIndex === 0 ? "true" : "false"
                                    }" 
                                    aria-controls="${categoryId}">
                                ${category}
                            </button>
                        </h2>
                        <div id="${categoryId}" 
                             class="accordion-collapse collapse ${
                                 catIndex === 0 ? "show" : ""
                             }" 
                             aria-labelledby="category-header-${catIndex}" 
                             data-bs-parent="#allPermissionsContainer">
                            <div class="accordion-body">
                                <div class="    ">
                    `;

                        Object.keys(categories[category]).forEach(
                            (group, groupIndex) => {
                                const permissions = categories[category][group];
                                const accordionId = `role-accor-plus-${catIndex}-${groupIndex}`;
                                let groupHtml = `
                        <div class="accordion-item material-shadow">
                            <h2 class="accordion-header" id="role-accordionwithplus-${catIndex}-${groupIndex}">
                                <button class="accordion-button ${
                                    groupIndex === 0 ? "" : "collapsed"
                                }" type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#${accordionId}" 
                                        aria-expanded="${
                                            groupIndex === 0 ? "true" : "false"
                                        }" 
                                        aria-controls="${accordionId}">
                                    ${group}
                                </button>
                            </h2>
                            <div id="${accordionId}" 
                                 class="accordion-collapse collapse ${
                                     groupIndex === 0 ? "show" : ""
                                 }" 
                                 aria-labelledby="role-accordionwithplus-${catIndex}-${groupIndex}" 
                                 data-bs-parent="#category-accor-${catIndex}">
                                <div class="accordion-body">
                                    <div class="row">
                    `;

                                permissions.forEach((permission) => {
                                    groupHtml += `
                            <div class="col-4 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>${permission.permission_key}</span>
                                    <div class="form-check form-switch" style="margin-top: 0px;">
                                        <input class="form-check-input permission-toggle" 
                                               type="checkbox" 
                                               role="switch" 
                                               id="role-toggle-${permission.id}" 
                                               data-permission-id="${permission.id}">
                                        <label class="form-check-label" for="role-toggle-${permission.id}"></label>
                                    </div>
                                </div>
                            </div>
                        `;
                                });

                                groupHtml += `
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                                categoryHtml += groupHtml;
                            }
                        );

                        categoryHtml += `
                            </div>
                        </div>
                    </div>
                `;
                        tableContainer.append(categoryHtml);
                    });
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
    });
});
