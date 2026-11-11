$(document).ready(function () {
    $("#reportType").select2({
        dropdownParent: $("#exportModal"),
        width: "100%",
        templateResult: function (data) {
            if (!data.id) return data.text;
            return $(data.element).data("html");
        },
        templateSelection: function (data) {
            if (!data.id) return data.text;
            return $(data.element).data("html");
        },
        escapeMarkup: function (m) {
            return m;
        },
    });
    $("#reportType").change(function () {
        var reportType = $(this).val();

        startLoader({
            currentTarget: this,
        });

        var generalDetails = $("#generalDetails");
        var claimDetails = $("#claimDetails");
        var filledDetails = $("#filledDetails");
        var verificationDetails = $("#verificationDetails");
        var approvalAndFinanceDetails = $("#approvalAndFinanceDetails");
        var odometerAccessDetails = $("#odometerAccessDetails");
        var delayTrackingStageWise = $("#delayTrackingStageWise");

        $(".column-checkbox").prop("checked", false).prop("disabled", false);

        generalDetails.hide();
        claimDetails.hide();
        filledDetails.hide();
        verificationDetails.hide();
        approvalAndFinanceDetails.hide();
        odometerAccessDetails.hide();
        delayTrackingStageWise.hide();

        if (
            reportType == "general" ||
            reportType == "head_wise" ||
            reportType == "month_wise" ||
            reportType == "department_wise" ||
            reportType == "claim_type_wise"
        ) {
            generalDetails.show();
            claimDetails.show();
            filledDetails.show();
            verificationDetails.show();
            approvalAndFinanceDetails.show();
            odometerAccessDetails.show();

            $(
                "#colEmpName, #colEmpCode, #colGrade, #colClaimType, #colClaimStatus, #colMonth, #colUploadDate, #colBillDate, #colFilledAmt, #colVerifyAmt, #colApprAmt, #colFinancedAmt"
            ).prop("checked", true);

            if (reportType === "head_wise") {
                $("#colClaimId").prop("checked", true).prop("disabled", true);
            }
        } else if (reportType == "delay_tracking_stage_wise") {
            generalDetails.show();
            delayTrackingStageWise.show();
            $("#colEmpName, #colEmpCode, #colGrade").prop("checked", true);

            $("#delayTrackingStageWise .column-checkbox").prop("checked", true);

            claimDetails.hide();
            filledDetails.hide();
            verificationDetails.hide();
            approvalAndFinanceDetails.hide();
            odometerAccessDetails.hide();
        }

        endLoader({
            currentTarget: this,
        });
    });

    const today = new Date().toISOString().split("T")[0];

    // Default fromDate to 1st of current month so the range spans the whole
    // month — otherwise both dates default to today, all BillDates are the
    // same, and sorting by Bill Date produces no visible change.
    const firstOfMonth = new Date();
    firstOfMonth.setDate(1);
    const defaultFromDate = firstOfMonth.toISOString().split("T")[0];

    function addCustomButtons(fp) {
        const container = document.createElement("div");
        container.className = "flatpickr-extra-buttons";

        // LAST DATE ICON BUTTON
        const lastBtn = document.createElement("button");
        lastBtn.type = "button";
        lastBtn.className = "fp-btn fp-last-btn";
        lastBtn.innerHTML = '<i class="ri-calendar-check-line"></i>';
        lastBtn.onclick = () => {
            fp.setDate(today, true);
        };

        // CLEAR ICON BUTTON
        const clearBtn = document.createElement("button");
        clearBtn.type = "button";
        clearBtn.className = "fp-btn fp-clear-btn";
        clearBtn.innerHTML = '<i class="ri-close-circle-line"></i>';
        clearBtn.onclick = () => {
            fp.clear();
            fp.close();
        };

        container.appendChild(lastBtn);
        container.appendChild(clearBtn);

        fp.calendarContainer.appendChild(container);
    }

    // From Date — defaults to 1st of current month
    flatpickr("#fromDate", {
        dateFormat: "Y-m-d",
        enableYearSelection: true,
        maxDate: "today",
        defaultDate: defaultFromDate,
        onReady() {
            addCustomButtons(this);
        },
    });

    // To Date — defaults to today
    flatpickr("#toDate", {
        dateFormat: "Y-m-d",
        enableYearSelection: true,
        maxDate: "today",
        defaultDate: today,
        onReady() {
            addCustomButtons(this);
        },
    });


    $("#claimTypeSelect").select2({
        dropdownParent: $("#additionalFiltersCanvas"),
        width: "100%",
        placeholder: "Select Claim Type",
        allowClear: true,
        multiple: true,
        templateResult: function (data) {
            if (data.children && data.text) {
                let groupName = data.text;
                return $(
                    `<div class="d-flex justify-content-between align-items-center">
                     <span>${groupName}</span>
                     <a href="#" class="select-group-all ms-2 text-primary" data-group="${groupName}" style="font-size: 12px;">All</a>
                 </div>`
                );
            }
            return data.text;
        },
    });

    $(document).on("click", ".select-group-all", function (e) {
        e.preventDefault();
        const groupName = $(this).data("group");
        const $select = $("#claimTypeSelect");

        let groupOptions = [];
        $select.find(`option[data-group='${groupName}']`).each(function () {
            groupOptions.push($(this).val());
        });

        let newValues = [
            ...new Set([...($select.val() || []), ...groupOptions]),
        ];
        $select.val(newValues).trigger("change");
    });

    $("#claimTypeSelect").on("change", function () {
        let selected = $(this).val() || [];

        if (selected.includes("7")) {
            $("#backdate_odometer_submission").closest(".form-check").show();
        } else {
            $("#backdate_odometer_submission").closest(".form-check").hide();
            if ($("#backdate_odometer_submission").is(":checked")) {
                $("#backdate_odometer_submission").prop("checked", false).trigger("change");
            }
        }
    });

    $('input[name="claim_filter_type"]').on("change", function () {
        let filterType = $(this).val();

        if (filterType === "deactivated_claims") {
            $("#claimStatusSelect").val(null).trigger("change");
            $("#claimStatusSelect")
                .prop("disabled", true)
                .trigger("change.select2");
        } else {
            $("#claimStatusSelect")
                .prop("disabled", false)
                .trigger("change.select2");
        }

        if (filterType === "bill_date_exception") {
            $("#fromDate, #toDate, #expId")
                .val("")
                .prop("disabled", true);
        } else {
            $("#fromDate, #toDate, #expId").prop("disabled", false);
        }

        // Auto-check TotKm and cumulative_km for access_running filter
        if (filterType === "access_running") {
            $("#colTotKm").prop("checked", true);
            $("#colCumulativeKm").prop("checked", true);
        }
    });

    $("#claimTypeSelect").on("select2:open", function () {
        if (!$(".select2-global-all").length) {
            $(".select2-dropdown").prepend(
                `<div class="px-2 py-1 border-bottom">
                 <a href="#" class="select2-global-all text-primary">Select All</a>
             </div>`
            );
        }
    });

    $("#claimTypeSelect").trigger("change");

    $(document).on("click", ".select2-global-all", function (e) {
        e.preventDefault();
        const $select = $("#claimTypeSelect");

        let allOptions = [];
        $select.find("option").each(function () {
            allOptions.push($(this).val());
        });

        $select.val(allOptions).trigger("change");
    });

    const selectConfigs = {
        "#functionSelect": "Select Function",
        "#verticalSelect": "Select Vertical",
        "#departmentSelect": "Select Department",
        "#subDepartmentSelect": "Select Sub-Department",
        "#buSelect": "Select BU",
        "#zoneSelect": "Select Zone",
        "#regionSelect": "Select Region",
        "#territorySelect": "Select Territory",
        "#hqSelect": "Select HQ",
        "#userSelect": "Select Employee",
        "#filledBySelect": "Select Filed By",
        "#monthSelect": "Select Month",
        "#claimStatusSelect": "Select Claim Status",
        "#policySelect": "Select Policy",
        "#wheelerTypeSelect": "Select Wheeler Type",
        "#vehicleTypeSelect": "Select Vehicle Type",
    };

    Object.entries(selectConfigs).forEach(([selector, placeholder]) => {
        if ($(selector).length) {
            $(selector).select2({
                dropdownParent: $("#additionalFiltersCanvas"),
                width: "100%",
                placeholder: placeholder,
                allowClear: true,
                multiple: true,
            });
        }
    });

    $(document).on("change", "#functionSelect", function () {
        const selectedFunctions = $(this).val() || [];
        $.ajax({
            url: "/verticals/by-function",
            type: "POST",
            data: JSON.stringify({
                function_ids: selectedFunctions,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response && response.success && response.data) {
                    let verticalOptions = '';
                    response.data.forEach((vertical) => {
                        verticalOptions += `<option value="${vertical.id}">${vertical.vertical_name}</option>`;
                    });
                    $("#verticalSelect").html(verticalOptions).val(null).trigger("change.select2");
                    $("#departmentSelect, #subDepartmentSelect, #userSelect").html("").val(null).trigger("change.select2");
                } else {
                    $("#verticalSelect, #departmentSelect, #subDepartmentSelect, #userSelect").html("").val(null).trigger("change.select2");
                }
                updateActiveFilterCount();
            },
            error: function (xhr) {
                console.error("Error fetching verticals:", xhr.responseText);
            },
        });
    });

    $(document).on("change", "#verticalSelect", function () {
        const selectedVerticals = $(this).val() || [];
        $.ajax({
            url: "/departments/by-vertical",
            type: "POST",
            data: JSON.stringify({
                vertical_ids: selectedVerticals,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response && response.success && response.data) {
                    let deptOptions = '';
                    response.data.forEach((dept) => {
                        deptOptions += `<option value="${dept.id}">${dept.department_name}</option>`;
                    });
                    $("#departmentSelect").html(deptOptions).val(null).trigger("change.select2");
                    $("#subDepartmentSelect, #userSelect").html("").val(null).trigger("change.select2");
                } else {
                    $("#departmentSelect, #subDepartmentSelect, #userSelect").html("").val(null).trigger("change.select2");
                }
                updateActiveFilterCount();
            },
            error: function (xhr) {
                console.error("Error fetching departments:", xhr.responseText);
            },
        });
    });

    $(document).on("change", "#departmentSelect", function () {
        const selectedDepartments = $(this).val() || [];
        $.ajax({
            url: "/sub-departments/by-department",
            type: "POST",
            data: JSON.stringify({
                department_ids: selectedDepartments,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response && response.success && response.data) {
                    let subDeptOptions = '';
                    response.data.forEach((subDept) => {
                        subDeptOptions += `<option value="${subDept.id}">${subDept.sub_department_name}</option>`;
                    });
                    $("#subDepartmentSelect").html(subDeptOptions).val(null).trigger("change.select2");
                } else {
                    $("#subDepartmentSelect").html("").val(null).trigger("change.select2");
                }
                updateActiveFilterCount();
            },
            error: function (xhr) {
                console.error("Error fetching sub-departments:", xhr.responseText);
            },
        });

        $.ajax({
            url: "/employees/by-department",
            type: "POST",
            data: JSON.stringify({
                department_ids: selectedDepartments,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response && response.success && response.data) {
                    let userOptions = '';
                    response.data.forEach((employee) => {
                        const fullName = `${employee.Fname} ${employee.Sname || ""} ${employee.Lname}`.trim();
                        const optionText = `${employee.EmpCode} - ${fullName}`;
                        const statusClass = employee.EmpStatus === "D" ? "deactivated" : "";
                        userOptions += `<option value="${employee.EmployeeID}" data-status="${employee.EmpStatus}" class="${statusClass}">${optionText}</option>`;
                    });
                    $("#userSelect").html(userOptions).val(null).trigger("change.select2");
                } else {
                    $("#userSelect").html("").val(null).trigger("change.select2");
                }
                updateActiveFilterCount();
            },
            error: function (xhr) {
                console.error("Error fetching users:", xhr.responseText);
            },
        });
    });

    $(document).on("change", "#subDepartmentSelect", function () {
        const selectedSubDepts = $(this).val() || [];
        $.ajax({
            url: "/employees/by-department",
            type: "POST",
            data: JSON.stringify({
                sub_department_ids: selectedSubDepts,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response && response.success && response.data) {
                    let userOptions = '';
                    response.data.forEach((employee) => {
                        const fullName = `${employee.Fname} ${employee.Sname || ""} ${employee.Lname}`.trim();
                        const optionText = `${employee.EmpCode} - ${fullName}`;
                        const statusClass = employee.EmpStatus === "D" ? "deactivated" : "";
                        userOptions += `<option value="${employee.EmployeeID}" data-status="${employee.EmpStatus}" class="${statusClass}">${optionText}</option>`;
                    });
                    $("#userSelect").html(userOptions).val(null).trigger("change.select2");
                } else {
                    $("#userSelect").html("").val(null).trigger("change.select2");
                }
                updateActiveFilterCount();
            },
            error: function (xhr) {
                console.error("Error fetching users:", xhr.responseText);
            },
        });
    });

    let table = null;

    function initializeDataTable() {
        table = $("#claimReportTable").DataTable({
            ordering: true,
            searching: false,
            paging: true,
            serverSide: true,
            processing: true,
            ajax: {
                url: "filter-claims",
                type: "POST",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                data: function (d) {
                    d.claim_filter_type = $("input[name='claim_filter_type']:checked").val();
                    d.function_ids = $("#functionSelect").val() || [];
                    d.vertical_ids = $("#verticalSelect").val() || [];
                    d.department_ids = $("#departmentSelect").val() || [];
                    d.sub_department_ids = $("#subDepartmentSelect").val() || [];
                    d.bu_ids = $("#buSelect").val() || [];
                    d.zone_ids = $("#zoneSelect").val() || [];
                    d.region_ids = $("#regionSelect").val() || [];
                    d.territory_ids = $("#territorySelect").val() || [];
                    d.hq_ids = $("#hqSelect").val() || [];
                    d.user_ids = $("#userSelect").val() || [];
                    d.filed_by_ids = $("#filledBySelect").val() || [];
                    d.months = $("#monthSelect").val() || [];
                    d.claim_type_ids = $("#claimTypeSelect").val() || [];
                    d.claim_statuses = $("#claimStatusSelect").val() || [];
                    d.from_date = $("#fromDate").val();
                    d.to_date = $("#toDate").val();
                    d.expId = $("#expId").val();
                    d.date_type = $('input[name="dateType"]:checked').val();
                    d.policy_ids = $("#policySelect").val() || [];
                    d.wheeler_type = $("#wheelerTypeSelect").val() || [];
                    d.vehicle_types = $("#vehicleTypeSelect").val() || [];
                },
            },
            columns: [
                { data: "DT_RowIndex",     name: "DT_RowIndex",     orderable: false, searchable: false },
                { data: "ExpId",           name: "ExpId",           orderable: true },
                { data: "claim_type_name", name: "claim_type_name", orderable: true,  className: "text-start" },
                { data: "employee_name",   name: "employee_name",   orderable: true,  className: "text-start" },
                { data: "ClaimMonth",      name: "ClaimMonth",      orderable: true },
                { data: "CrDate",          name: "CrDate",          orderable: true },
                { data: "BillDate",        name: "BillDate",        orderable: true },
                { data: "FilledTAmt",      name: "FilledTAmt",      orderable: true },
                { data: "ClaimStatus",     name: "ClaimStatus",     orderable: true },
                { data: "action",          name: "action",          orderable: false, searchable: false },
            ],
            destroy: true,
        });
    }

    function updateActiveFilterCount() {
        let filterCount = 0;

        // Multi-select dropdowns (count only if they have actual selections)
        const multiSelectFilters = [
            "#functionSelect",
            "#verticalSelect",
            "#departmentSelect",
            "#subDepartmentSelect",
            "#buSelect",
            "#zoneSelect",
            "#regionSelect",
            "#territorySelect",
            "#hqSelect",
            "#userSelect",
            "#filledBySelect",
            "#monthSelect",
            "#claimTypeSelect",
            "#claimStatusSelect",
            "#policySelect",
            "#wheelerTypeSelect",
            "#vehicleTypeSelect"
        ];

        multiSelectFilters.forEach((selector) => {
            const value = $(selector).val();
            if (value && Array.isArray(value) && value.length > 0) {
                // Filter out empty strings from the array
                const nonEmptyValues = value.filter(v => v !== "" && v !== null && v !== undefined);
                if (nonEmptyValues.length > 0) {
                    filterCount += 1; // Count as 1 active filter regardless of how many items selected
                }
            }
        });

        // Date inputs
        const fromDate = $("#fromDate").val();
        const toDate = $("#toDate").val();
        if (fromDate && fromDate.trim() !== "") {
            filterCount += 1;
        }
        if (toDate && toDate.trim() !== "") {
            filterCount += 1;
        }

        // Expense ID input
        const expId = $("#expId").val();
        if (expId && expId.trim() !== "") {
            filterCount += 1;
        }

        // Special filter types (radio buttons)
        const claimFilterType = $('input[name="claim_filter_type"]:checked').val();
        if (claimFilterType) {
            filterCount += 1;
        }

        // Date type filter (only count if not default "billDate")
        const dateType = $('input[name="dateType"]:checked').val();
        if (dateType && dateType !== "billDate") {
            filterCount += 1;
        }

        $("#activeFilterCount").text(filterCount);
    }

    if ($("#searchButton").length) {
        initializeDataTable();

        $("#searchButton").on("click", function () {
            if (table) {
                startSimpleLoader({
                    currentTarget: this,
                });
                table.ajax.reload(function () {
                    endSimpleLoader({
                        currentTarget: $("#searchButton")[0],
                    });
                });
            } else {
                endSimpleLoader({
                    currentTarget: $("#searchButton")[0],
                });
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    "DataTable not initialized"
                );
            }
        });
    } else {
        showAlert(
            "danger",
            "ri-error-warning-line",
            "Search button not found. DataTable not initialized"
        );
    }

    $("#exportModal").on("show.bs.modal", function () {
        const modalFilters = $("#modalFilters");
    });

    $("#exportExcelBtn").on("click", function () {
        const button = this;
        const reportType = $("#reportType").val();
        if (!reportType || reportType === "") {
            showAlert(
                "danger",
                "ri-error-warning-line",
                "Please select Report Type first."
            );
            $("#reportType").focus();
            return;
        }

        const columns = $(".column-checkbox:checked")
            .map(function () {
                return this.value;
            })
            .get();

        if (columns.length === 0) {
            showAlert(
                "danger",
                "ri-error-warning-line",
                "Please select at least one column to export"
            );
            return;
        }
        const filters = {
            function_ids: $("#functionSelect").val() || [],
            vertical_ids: $("#verticalSelect").val() || [],
            department_ids: $("#departmentSelect").val() || [],
            sub_department_ids: $("#subDepartmentSelect").val() || [],
            bu_ids: $("#buSelect").val() || [],
            zone_ids: $("#zoneSelect").val() || [],
            region_ids: $("#regionSelect").val() || [],
            territory_ids: $("#territorySelect").val() || [],
            hq_ids: $("#hqSelect").val() || [],
            user_ids: $("#userSelect").val() || [],
            filed_by_ids: $("#filledBySelect").val() || [],
            months: $("#monthSelect").val() || [],
            claim_type_ids: $("#claimTypeSelect").val() || [],
            claim_statuses: $("#claimStatusSelect").val() || [],
            from_date: $("#fromDate").val(),
            to_date: $("#toDate").val(),
            date_type: $('input[name="dateType"]:checked').val() || "billDate",
            policy_ids: $("#policySelect").val() || [],
            wheeler_type: $("#wheelerTypeSelect").val() || [],
            vehicle_types: $("#vehicleTypeSelect").val() || [],
            claim_filter_type: $(
                "input[name='claim_filter_type']:checked"
            ).val(),
        };
        // Collect verify amount versions
        const verifyAmtVersions = [];
        if ($('#verifyAmtCurrent').is(':checked')) verifyAmtVersions.push('current');
        if ($('#verifyAmtPrevious').is(':checked')) verifyAmtVersions.push('previous');
        
        // Collect approval amount versions
        const apprAmtVersions = [];
        if ($('#apprAmtCurrent').is(':checked')) apprAmtVersions.push('current');
        if ($('#apprAmtPrevious').is(':checked')) apprAmtVersions.push('previous');

        $.ajax({
            url: "/expense-claims/export",
            method: "POST",
            data: JSON.stringify({
                columns,
                reportType: $("#reportType").val(),
                protectSheets: $("#protectSheets").is(":checked"),
                verify_amt_versions: verifyAmtVersions.length > 0 ? verifyAmtVersions : ['current'],
                appr_amt_versions: apprAmtVersions.length > 0 ? apprAmtVersions : ['current'],
                ...filters,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            xhrFields: {
                responseType: "blob",
            },
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            success: function (data, status, xhr) {
                const contentType = xhr.getResponseHeader("content-type") || "";
                if (contentType.includes("application/json")) {
                    data.text().then((text) => {
                        const response = JSON.parse(text);
                        showAlert(
                            "danger",
                            "ri-error-warning-line",
                            response.message || "Export failed."
                        );
                    });
                    return;
                }

                const contentDisposition = xhr.getResponseHeader(
                    "content-disposition"
                );
                let fileName = "download";
                if (contentDisposition) {
                    const fileNameRegex =
                        /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                    const matches = fileNameRegex.exec(contentDisposition);
                    if (matches != null && matches[1]) {
                        fileName = matches[1].replace(/['"]/g, "");
                    }
                }

                const url = window.URL.createObjectURL(data);
                const a = $("<a>", {
                    href: url,
                    download: fileName,
                }).appendTo("body");
                a[0].click();
                a.remove();
                window.URL.revokeObjectURL(url);
                $("#exportModal").modal("hide");
            },

            error: function (xhr, status, error) {
                console.error("Export error:", {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    response: xhr.response,
                });

                const displayError = function (msg) {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        "Failed to export data: " + (msg || error || xhr.statusText || "Server error")
                    );
                };

                const rawResponse = xhr.response || xhr.responseText;
                if (rawResponse instanceof Blob) {
                    rawResponse.text().then(function (text) {
                        try {
                            const errJson = JSON.parse(text);
                            displayError(errJson.details || errJson.message || text);
                        } catch (e) {
                            // If response is HTML error page (e.g. 504 Gateway Time-out or 500)
                            const doc = new DOMParser().parseFromString(text, "text/html");
                            const title = doc.querySelector("title") ? doc.querySelector("title").innerText : "";
                            displayError(title || (text.length > 200 ? text.substring(0, 200) + "..." : text));
                        }
                    }).catch(function () {
                        displayError(error);
                    });
                } else if (typeof rawResponse === "string") {
                    try {
                        const errJson = JSON.parse(rawResponse);
                        displayError(errJson.details || errJson.message || rawResponse);
                    } catch (e) {
                        displayError(rawResponse);
                    }
                } else {
                    displayError(error);
                }
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });

    $(document).on("click", "#applyFiltersBtn", function () {
        const canvasEl = document.getElementById("additionalFiltersCanvas");
        if (canvasEl && typeof bootstrap !== "undefined") {
            const bsOffcanvas = bootstrap.Offcanvas.getInstance(canvasEl);
            if (bsOffcanvas) bsOffcanvas.hide();
        }
        updateActiveFilterCount();
        if (table) {
            startSimpleLoader({
                currentTarget: $("#searchButton")[0],
            });
            table.ajax.reload(function () {
                endSimpleLoader({
                    currentTarget: $("#searchButton")[0],
                });
            }, false);
        }
    });

    $(document).on("click", ".return-claim", function (e) {
        e.preventDefault();

        var expId = $(this).data("expid");
        var claimId = $(this).data("claim-id");

        Swal.fire({
            title: "Return Claim?",
            text: "Please provide a reason to return this claim to the employee:",
            input: "textarea",
            inputPlaceholder: "Enter reason for return...",
            inputAttributes: {
                rows: 3,
                maxlength: 500
            },
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Yes, Return",
            cancelButtonText: "Cancel",
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return "Reason is required to return the claim!";
                }
            }
        }).then((result) => {
            if (!result.isConfirmed) return;

            const reason = (result.value || "").trim();

            $.ajax({
                url: "/claims/return",
                method: "POST",
                data: {
                    expid: expId,
                    claim_id: claimId,
                    reason: reason,
                    _token: $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    showAlert(
                        "success",
                        "ri-checkbox-circle-line",
                        response.message || "Claim returned successfully."
                    );
                    $("#claimReportTable").DataTable().ajax.reload(null, false);
                },
                error: function (xhr) {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        "An error occurred: " +
                            (xhr.responseJSON?.message || "Please try again.")
                    );
                },
            });
        });
    });

    $(document).on(
        "change select2:select select2:unselect select2:clear",
        "#functionSelect, #verticalSelect, #departmentSelect, #subDepartmentSelect, #buSelect, #zoneSelect, #regionSelect, #territorySelect, #hqSelect, #userSelect, #filledBySelect, #monthSelect, #claimTypeSelect, #claimStatusSelect, #policySelect, #wheelerTypeSelect, #vehicleTypeSelect",
        function () {
            updateActiveFilterCount();
        }
    );

    $("#fromDate, #toDate, #expId").on("change keyup", function () {
        updateActiveFilterCount();
    });

    // Update filter count when special filter types change
    $('input[name="claim_filter_type"], input[name="dateType"]').on("change", function () {
        updateActiveFilterCount();
    });

    // Clear Filters functionality
    function performClearFilters() {
        // Clear all select2 dropdowns
        const selectElements = [
            "#functionSelect",
            "#verticalSelect",
            "#departmentSelect",
            "#subDepartmentSelect",
            "#buSelect",
            "#zoneSelect",
            "#regionSelect",
            "#territorySelect",
            "#hqSelect",
            "#userSelect",
            "#filledBySelect",
            "#monthSelect",
            "#claimTypeSelect",
            "#claimStatusSelect",
            "#policySelect",
            "#wheelerTypeSelect",
            "#vehicleTypeSelect"
        ];

        selectElements.forEach(selector => {
            $(selector).val(null).trigger('change.select2');
        });

        // Clear date inputs
        $("#fromDate").val('').trigger('change');
        $("#toDate").val('').trigger('change');
        $("#expId").val('');

        // Reset radio buttons to default
        $('input[name="claim_filter_type"]').prop('checked', false);
        $("#billDate").prop('checked', true); // Reset to default date type

        // Re-enable disabled elements
        $("#claimStatusSelect").prop("disabled", false).trigger("change.select2");
        $("#fromDate, #toDate, #expId").prop("disabled", false);

        // Update filter count
        updateActiveFilterCount();

        // reload data so cleared filters take effect immediately
        if (typeof table !== 'undefined' && table) {
            table.ajax.reload();
        }

        // hide offcanvas if it's currently open
        const offcanvasEl = document.getElementById('additionalFiltersCanvas');
        if (offcanvasEl && typeof bootstrap !== 'undefined') {
            const bsOff = bootstrap.Offcanvas.getInstance(offcanvasEl);
            if (bsOff) bsOff.hide();
        }
    }

    $("#clearFiltersBtn").on("click", performClearFilters);

    // also respond to the new header clear button
    $("#clearAllFilters").on("click", function() {
        performClearFilters();
    });
});

