$(document).ready(function () {
    $("#menuMasterTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });
    $("#menuBtn").click(function () {
        $("#addMenuLabel").text("Add New Menu");
        selectMenuList("menuSelect", "menuModal");
        selectPermission("permissionSelect", "menuModal");
        $("#company_ids").val("");
        $('#selectAllCompanies').prop('checked', false);
        $('.company-checkbox').prop('checked', false);
        $("#menuForm")[0].reset();
        $("#saveMenuBtn").removeData("menu-id");
        $("#menuModal").modal("show");
    });
    $("#saveMenuBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            menu_name: document.getElementById("title").value,
            parent_id: document.getElementById("menuSelect").value || null,
            icon: document.getElementById("icon").value || null,
            order: document.getElementById("order").value || 0,
            url: document.getElementById("url").value,
            permission: document.getElementById("permissionSelect").value || null,
            is_active: document.getElementById("is_active").checked ? 1 : 0,
            id: $(button).data("menu-id") || null,
            company_access_id: getSelectedCompanies(),


        };
     console.log("Selected:", getSelectedCompanies());

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `menu/${formData.id}` : "menu";

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
                        response.message || "Menu saved successfully!"
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
                let errorMsg = "Failed to save menu.";
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        errorMsg = Object.values(errors).flat().join(" ");
                    } else if (xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
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
    $(document).on("click", ".edit-menu", function (event) {
        event.preventDefault();
        const menuId = $(this).data("id");
        const button = event.currentTarget;
        $.ajax({
            url: "menu/" + menuId + "/edit",
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
                    const menu = response.data;
                    $("#addMenuLabel").text("Edit Menu - " + menu.title);
                    $("#title").val(menu.title);
                    $("#icon").val(menu.icon || "");
                    $("#order").val(menu.order || 0);
                    $("#url").val(menu.url);
                    $("#is_active").prop("checked", menu.status == 1);
                    selectMenuList("menuSelect", "menuModal", menu.parent_id);
                    selectPermission(
                        "permissionSelect",
                        "menuModal",
                        menu.permission_name
                    );
                    $("#company_ids").val(menu.company_access_id);
                    let companyIds = menu.company_access_id
                        ? menu.company_access_id.split(',')
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

                    $("#saveMenuBtn").data("menu-id", menu.id);
                    $("#menuModal").modal("show");
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching menu."
                    );
                }
            },
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message || "Failed to fetch menu.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });
    $(document).on("click", ".delete-menu", function (event) {
        event.preventDefault();
        const menuId = $(this).data("id");
        const confirmation = confirm(
            "Are you sure you want to delete this menu?"
        );
        if (confirmation) {
            $.ajax({
                url: "menu/" + menuId,
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
                    let errorMsg = "Failed to delete menu.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });
    let logTable = null;
    $(document).on("click", ".view-logs", function (event) {
        event.preventDefault();
        var menuId = $(this).data("id");
        const button = event.currentTarget;

        if (logTable) {
            logTable.destroy();
            $("#logTable tbody").empty();
        }

        logTable = $("#logTable").DataTable({
            ordering: true,
            searching: true,
            paging: true,
            info: true,
            lengthChange: true,
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            ajax: {
                url: "/menu/log/" + menuId,
                type: "GET",
                beforeSend: function () {
                    startLoader({
                        currentTarget: button,
                    });
                },
                dataSrc: function (response) {
                    $("#logMenuTitle").text(response.title || "Untitled");
                    return response.data || [];
                },
                error: function (xhr, error, thrown) {
                    console.error("Error fetching logs:", thrown);
                    alert("Error fetching logs.");
                },
                complete: function () {
                    endLoader({
                        currentTarget: button,
                    });
                },
            },
            columns: [
                { data: "event" },
                { data: "description" },
                {
                    data: "properties",
                    render: function (data) {
                        return formatChanges(data);
                    },
                },
                {
                    data: "causer",
                    render: function (data) {
                        return data && data.name ? data.name : "System";
                    },
                },
                {
                    data: "created_at",
                    render: function (data) {
                        return data ? new Date(data).toLocaleString() : "-";
                    },
                },
            ],
            order: [[4, "desc"]],
        });

        $("#logModal").modal("show");
    });
    function formatChanges(properties) {
        if (!properties || (!properties.attributes && !properties.old)) {
            return "No changes recorded";
        }

        let changes = "<ul style='text-align: left;'>";
        const now = new Date();

        for (let key in properties.attributes) {
            if (properties.old && key in properties.old) {
                if (key === "updated_at") {
                    const oldDate = new Date(properties.old[key]);
                    const newDate = new Date(properties.attributes[key]);
                    const diffMs = newDate - oldDate;
                    const diffMins = Math.floor(diffMs / 60000);

                    if (diffMins < 60) {
                        changes += `<li>Updated <strong>${diffMins} minute${diffMins !== 1 ? "s" : ""
                            } ago</strong></li>`;
                    } else {
                        changes += `<li>Updated from <strong>${oldDate.toLocaleString(
                            "en-US",
                            { timeZone: "Asia/Kolkata" }
                        )}</strong> to <strong>${newDate.toLocaleString(
                            "en-US",
                            { timeZone: "Asia/Kolkata" }
                        )}</strong></li>`;
                    }
                } else {
                    changes += `<li>${key.charAt(0).toUpperCase() + key.slice(1)
                        } changed from <strong>${properties.old[key]
                        }</strong> to <strong>${properties.attributes[key]
                        }</strong></li>`;
                }
            } else {
                changes += `<li>${key.charAt(0).toUpperCase() + key.slice(1)
                    } set to <strong>${properties.attributes[key]
                    }</strong> (created)</li>`;
            }
        }
        changes += "</ul>";
        return changes;
    }
    function selectMenuList(feildId, modalFeildId, id = null) {
        $.ajax({
            url: "menu-list",
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
                const noParentOption = new Option(
                    "No Parent",
                    "0",
                    id === null || id === 0,
                    id === null || id === 0
                );
                const options = data.map(function (item) {
                    const isSelected = item.id === id;
                    return new Option(
                        item.title,
                        item.id,
                        isSelected,
                        isSelected
                    );
                });
                const selectElement = $(`#${feildId}`);
                selectElement
                    .empty()
                    .append(placeholderOption)
                    .append(noParentOption)
                    .append(options);
                if (id !== null) {
                    selectElement.val(id === 0 ? "0" : id);
                }
                selectElement.select2({
                    dropdownParent: $(`#${modalFeildId}`),
                });
            },
            error: function (xhr, status, error) {
                console.error("Error fetching menu list:", error);
            },
        });
    }
    function selectPermission(fieldId, modalFieldId, permissionName = null) {
        $.ajax({
            url: "permissions-list",
            type: "GET",
            dataType: "json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                const selectElement = $(`#${fieldId}`);
                if (
                    response.success &&
                    response.data &&
                    response.data.Navigation &&
                    response.data.Navigation["Navigation"]
                ) {
                    const permissionsArray =
                        response.data.Navigation["Navigation"];

                    const permissionData = permissionsArray.map((item) => ({
                        id: item.permission_key,
                        text: item.permission_key,
                        group: "Navigation",
                        name: item.name,
                    }));

                    selectElement.empty().select2({
                        dropdownParent: $(`#${modalFieldId}`),
                        placeholder: "Select a Navigation permission",
                        allowClear: true,
                        data: permissionData,
                        templateResult: function (data) {
                            if (!data.id) return data.text;
                            return $(`<span>${data.text}</span>`);
                        },
                        templateSelection: function (data) {
                            if (!data.id) return data.text;
                            return data.text;
                        },
                    });

                    if (permissionName !== null) {
                        const permissionExists = permissionData.some(
                            (item) => item.id === permissionName
                        );
                        selectElement
                            .val(permissionExists ? permissionName : null)
                            .trigger("change");
                    } else {
                        selectElement.val(null).trigger("change");
                    }
                } else {
                    console.error("No permissions found.");
                    selectElement
                        .empty()
                        .append(
                            new Option(
                                "No permissions available",
                                "",
                                true,
                                true
                            )
                        );
                }
            },
            error: function (xhr, status, error) {
                console.error("Error fetching permissions list:", error);
                $(`#${fieldId}`)
                    .empty()
                    .append(
                        new Option("Error loading permissions", "", true, true)
                    );
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

    function getSelectedCompanies() {
        let selected = [];

        document.querySelectorAll(".company-checkbox:checked").forEach(function (el) {
            selected.push(el.value);
        });

        return selected.join(",");
    }

});
