$(document).ready(function () {
    $("#apiManagerTable").DataTable({
        ordering: false,
        searching: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });

    $("#saveApiBtn").click(function (event) {
        event.preventDefault();
        const button = event.currentTarget;

        const formData = {
            claim_id: document.getElementById("claim_id").value,
            api_name: document.getElementById("api_name").value,
            endpoint: document.getElementById("endpoint").value,
            status: document.getElementById("api_status").checked ? 1 : 0,
            id: $(button).data("api-id") || null,
        };

        const requestType = formData.id ? "PUT" : "POST";
        const url = formData.id ? `api-manager/${formData.id}` : "api-manager";

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
                        response.message || "API saved successfully!"
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
                let errorMsg = "Failed to save API.";
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

    $(document).on("click", ".delete-api", function (event) {
        event.preventDefault();
        const apiId = $(this).data("id");
        const confirmation = confirm(
            "Are you sure you want to delete this API?"
        );
        if (confirmation) {
            $.ajax({
                url: "api-manager/" + apiId,
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
                            response.message || "API deleted successfully!"
                        );
                        setTimeout(() => {
                            window.location.href = window.location.href;
                        }, 2000);
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
                    let errorMsg = "Failed to delete API.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
            });
        }
    });

    $(document).on("click", ".edit-api", function (event) {
        event.preventDefault();
        const apiId = $(this).data("id");
        const button = event.currentTarget;

        $.ajax({
            url: "api-manager/" + apiId + "/edit",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            type: "GET",
            dataType: "json",
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            success: function (response) {
                if (response.success) {
                    const api = response.data;
                    $("#addApiLabel").text("Edit API - " + api.name);
                    $("#claim_id").val(api.claim_id);
                    $("#api_name").val(api.name);
                    $("#endpoint").val(api.endpoint);
                    $("#api_status").prop("checked", api.status == 1);
                    $("#saveApiBtn").attr("data-api-id", api.id);
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "Error fetching API details."
                    );
                }
            },
            error: function (xhr) {
                const errorMsg =
                    xhr.responseJSON?.message || "Failed to fetch API details.";
                showAlert("danger", "ri-error-warning-line", errorMsg);
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });

    $("#apiFeildsMapping").on("show.bs.modal", function (event) {
        const button = $(event.relatedTarget);
        const claim_id = button.data("id");
        $.ajax({
            url: "get-columns",
            method: "GET",
            data: { claim_id: claim_id },
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            dataType: "json",
            success: function (response) {
                const columns = response.columns;
                const tbody = $("#mapping-rows");
                tbody.empty();

                columns.forEach((column) => {
                    const row = `
                        <tr>
                            <td>${column}</td>
                            <td><select class="form-control input-type" name="input_type[]">
                                <option value="Input">Input</option>
                                <option value="Select">Select</option>
                            </select></td>
                            <td><select class="form-control select-table" name="select_table[]">
                                <option value="">--Select Table--</option>
                            </select></td>
                            <td><select class="form-control search-column" name="search_column[]">
                                <option value="">--Select Column--</option>
                            </select></td>
                            <td><select class="form-control return-column" name="return_column[]">
                                <option value="">--Select Column--</option>
                            </select></td>
                            <td><select class="form-control punch-table" name="punch_table[]">
                                <option value="y1_punchdata_51">y1_punchdata_51</option>
                            </select></td>
                            <td><select class="form-control punch-column" name="punch_column[]">
                                <option value="${column}">${column}</option>
                            </select></td>
                            <td><input type="text" class="form-control" name="condition[]" value="${column}" /></td>
                        </tr>`;
                    tbody.append(row);
                });

                // Apply Select2 with dropdownParent set to the modal
                $("#mapping-rows select").select2({
                    dropdownParent: $("#apiFeildsMapping"),
                });

                // Populate select-table options
                $.ajax({
                    url: "get-tables",
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                            "content"
                        ),
                    },
                    method: "GET",
                    success: function (response) {
                        const tables = response.tables;
                        $(".select-table").each(function () {
                            const select = $(this);
                            tables.forEach((table) => {
                                select.append(
                                    `<option value="${table}">${table}</option>`
                                );
                            });
                            select.trigger("change");
                        });
                    },
                });
            },
        });
    });

    $(document).on("change", ".select-table", function () {
        const table = $(this).val();
        const row = $(this).closest("tr");
        const searchColumn = row.find(".search-column");
        const returnColumn = row.find(".return-column");

        if (table) {
            $.ajax({
                url: '{{ route("get.columns", ":table") }}'.replace(
                    ":table",
                    table
                ),
                method: "GET",
                success: function (response) {
                    const columns = response.columns;
                    searchColumn
                        .empty()
                        .append('<option value="">--Select Column--</option>');
                    returnColumn
                        .empty()
                        .append('<option value="">--Select Column--</option>');

                    columns.forEach((column) => {
                        searchColumn.append(
                            `<option value="${column}">${column}</option>`
                        );
                        returnColumn.append(
                            `<option value="${column}">${column}</option>`
                        );
                    });

                    searchColumn.trigger("change");
                    returnColumn.trigger("change");
                },
            });
        } else {
            searchColumn
                .empty()
                .append('<option value="">--Select Column--</option>');
            returnColumn
                .empty()
                .append('<option value="">--Select Column--</option>');
            searchColumn.trigger("change");
            returnColumn.trigger("change");
        }
    });

    $("#save-mapping").on("click", function () {
        const mappings = [];
        $("#mapping-rows tr").each(function () {
            const row = $(this);
            mappings.push({
                temp_column: row.find("td:first").text(),
                main_column: row.find(".punch-column").val(),
            });
        });

        $.ajax({
            url: '{{ route("map.fields") }}',
            method: "POST",
            data: {
                claim_id: $("#apiFeildsMapping")
                    .find(".feilds-mapping-api")
                    .data("id"),
                mappings: mappings,
                _token: "{{ csrf_token() }}",
            },
            success: function (response) {
                alert(response.success);
                $("#apiFeildsMapping").modal("hide");
            },
            error: function (xhr) {
                alert(xhr.responseJSON.error || "An error occurred");
            },
        });
    });
});
