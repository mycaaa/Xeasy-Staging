$(document).ready(function () {
    // Initialize date range picker for DA In/Out report
    flatpickr("#dateRangePicker", {
        mode: "range",
        dateFormat: "Y-m-d",
        maxDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
            // Update active filter count when dates are selected
            updateActiveFilterCount();
        }
    });

    $("#claimTypeSelect").select2({
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
        $select.val(newValues).trigger("change.select2");
    });

    $("#claimTypeSelect").on("change", function () {
        let selected = $(this).val() || [];

        if (selected.includes("7")) {
            $("#backdate_odometer_submission").closest(".form-check").show();
        } else {
            $("#backdate_odometer_submission").closest(".form-check").hide();
            $('input[name="claim_filter_type"]').prop("checked", false);
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

        $select.val(allOptions).trigger("change.select2");
    });

    const selectConfigs = {
        "#stageSelect": "Select Stage",
        "#delayCategorySelect": "Select Delay Category",
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
        "#monthSelect": "Select Month",
        "#policySelect": "Select Policy",
        "#columnSelect": "Select Column",
    };

    Object.entries(selectConfigs).forEach(([selector, placeholder]) => {
        if (selector === "#stageSelect") {
            $(selector).select2({
                width: "180px",
                placeholder: placeholder,
                allowClear: false,
                multiple: false,
            });
        } else if (selector === "#delayCategorySelect") {
            $(selector).select2({
                width: "180px",
                placeholder: placeholder,
                allowClear: false,
                multiple: false,
            });
        } else {
            $(selector).select2({
                width: "100%",
                placeholder: placeholder,
                allowClear: true,
                multiple: true,
            });
        }
    });

    $("#reportType").on("change", function () {
        let type = $(this).val();

        $("#stageSelect, #monthSelect, #columnSelect")
            .val(null)
            .trigger("change");

        $("#stageContainer, #columnContainer, #claimTypeContainer, #delayCategoryContainer, #dateRangeContainer").hide();
        $("#monthContainer").show();
        $("#monthSelect option").show();
        $("#monthSelect").prop("disabled", false);

        if (type === "claim_stages_report") {
            $("#stageContainer").show();
            $("#stageSelect").val("all").trigger("change");
        } else if (type === "delay_tracking_stage_wise") {
            $("#delayCategoryContainer").show();
            $("#delayCategorySelect").val("all").trigger("change");
        } else if (type === "da_in_out_report") {
            // Show date range picker instead of month selector for DA In/Out report
            $("#monthContainer").hide();
            $("#dateRangeContainer").show();
        } else if (type === "submission_wise" || type === "running_km") {
            $("#monthSelect").val(["all"]).trigger("change");
            $("#monthSelect option").each(function () {
                if ($(this).val() !== "all") {
                    $(this).hide();
                }
            });
            $("#monthSelect").prop("disabled", true);
        }

        if (
            type === "month_wise_expense" ||
            type === "month_claim_type_wise_expense" ||
            type === "month_wise_expense_multiple_sheet" ||
            type === "month_claim_type_wise_expense_multiple_sheet" ||
            type === "employee_claim_type_wise_expense"
        ) {
            if (
                type === "month_claim_type_wise_expense_multiple_sheet" ||
                type === "month_claim_type_wise_expense" ||
                type === "employee_claim_type_wise_expense"
            ) {
                $("#claimTypeContainer").show();
            }

            $("#columnContainer").show();
            $("#columnSelect").val("all").trigger("change");
        }
    });

    $("#reportType").select2({
        width: "250px",
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

    $("#monthSelect").on("change", function () {
        let selectedValues = $(this).val();
        if (selectedValues && selectedValues.includes("all")) {
            $("#monthSelect option").prop("selected", true);
        } else {
            $('#monthSelect option[value="all"]').prop("selected", false);
        }
    });

    $(document).on("change", "#functionSelect", function () {
        const selectedFunctions = $(this).val() || [];
        $.ajax({
            url: "verticals/by-function",
            type: "POST",
            data: JSON.stringify({
                function_ids: selectedFunctions,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    let verticalOptions =
                        '<option value="">Select options</option>';
                    response.data.forEach((vertical) => {
                        verticalOptions += `<option value="${vertical.id}">${vertical.vertical_name}</option>`;
                    });
                    $("#verticalSelect").html(verticalOptions);
                    $("#verticalSelect").select2({
                        placeholder: "Select options",
                        allowClear: true,
                        width: "100%",
                    });
                } else {
                    console.error(
                        "Failed to fetch verticals:",
                        response.message
                    );
                    $("#verticalSelect")
                        .html('<option value="">Select options</option>')
                        .select2({
                            placeholder: "Select options",
                            allowClear: true,
                            width: "100%",
                        });
                }
            },
            error: function (xhr) {
                console.error("Error fetching verticals:", xhr.responseText);
                $("#verticalSelect")
                    .html('<option value="">Error loading verticals</option>')
                    .select2({
                        placeholder: "Select options",
                        allowClear: true,
                        width: "100%",
                    });
            },
        });
    });

    $(document).on("change", "#verticalSelect", function () {
        const selectedVerticals = $(this).val() || [];
        $.ajax({
            url: "departments/by-vertical",
            type: "POST",
            data: JSON.stringify({
                vertical_ids: selectedVerticals,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    let deptOptions =
                        '<option value="">Select options</option>';
                    response.data.forEach((dept) => {
                        deptOptions += `<option value="${dept.id}">${dept.department_name}</option>`;
                    });
                    $("#departmentSelect").html(deptOptions);
                    $("#departmentSelect").select2({
                        placeholder: "Select options",
                        allowClear: true,
                        width: "100%",
                    });

                    $("#subDepartmentSelect, #userSelect")
                        .html('<option value="">Select options</option>')
                        .select2({
                            placeholder: "Select options",
                            allowClear: true,
                            width: "100%",
                        });
                } else {
                    console.error(
                        "Failed to fetch departments:",
                        response.message
                    );
                    $("#departmentSelect")
                        .html('<option value="">Select options</option>')
                        .select2({
                            placeholder: "Select options",
                            allowClear: true,
                            width: "100%",
                        });
                }
            },
            error: function (xhr) {
                console.error("Error fetching departments:", xhr.responseText);
                $("#departmentSelect")
                    .html('<option value="">Error loading departments</option>')
                    .select2({
                        placeholder: "Select options",
                        allowClear: true,
                        width: "100%",
                    });
            },
        });
    });

    $(document).on("change", "#departmentSelect", function () {
        const selectedDepartments = $(this).val() || [];
        $.ajax({
            url: "sub-departments/by-department",
            type: "POST",
            data: JSON.stringify({
                department_ids: selectedDepartments,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    let subDeptOptions =
                        '<option value="">Select options</option>';
                    response.data.forEach((subDept) => {
                        subDeptOptions += `<option value="${subDept.id}">${subDept.sub_department_name}</option>`;
                    });
                    $("#subDepartmentSelect").html(subDeptOptions);
                    $("#subDepartmentSelect").select2({
                        placeholder: "Select options",
                        allowClear: true,
                        width: "100%",
                    });
                } else {
                    console.error(
                        "Failed to fetch sub-departments:",
                        response.message
                    );
                    $("#subDepartmentSelect")
                        .html('<option value="">Select options</option>')
                        .select2({
                            placeholder: "Select options",
                            allowClear: true,
                            width: "100%",
                        });
                }
            },
            error: function (xhr) {
                console.error(
                    "Error fetching sub-departments:",
                    xhr.responseText
                );
                $("#subDepartmentSelect")
                    .html(
                        '<option value="">Error loading sub-departments</option>'
                    )
                    .select2({
                        placeholder: "Select options",
                        allowClear: true,
                        width: "100%",
                    });
            },
        });

        $.ajax({
            url: "employees/by-department",
            type: "POST",
            data: JSON.stringify({
                department_ids: selectedDepartments,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    let userOptions =
                        '<option value="">Select options</option>';
                    response.data.forEach((employee) => {
                        const fullName = `${employee.Fname} ${
                            employee.Sname || ""
                        } ${employee.Lname}`.trim();
                        const optionText = `${employee.EmpCode} - ${fullName}`;
                        const statusClass =
                            employee.EmpStatus === "D" ? "deactivated" : "";
                        userOptions += `<option value="${employee.EmployeeID}" data-status="${employee.EmpStatus}" class="${statusClass}">${optionText}</option>`;
                    });
                    $("#userSelect")
                        .val(null)
                        .trigger("change")
                        .html(userOptions)
                        .select2({
                            placeholder: "Select options",
                            allowClear: true,
                            width: "100%",
                        });
                } else {
                    console.error("Failed to fetch users:", response.message);
                    $("#userSelect")
                        .html('<option value="">Select options</option>')
                        .select2({
                            placeholder: "Select options",
                            allowClear: true,
                            width: "100%",
                        });
                }
            },
            error: function (xhr) {
                console.error("Error fetching users:", xhr.responseText);
                $("#userSelect")
                    .html('<option value="">Error loading users</option>')
                    .select2({
                        placeholder: "Select options",
                        allowClear: true,
                        width: "100%",
                    });
            },
        });
    });

    $(document).on("change", "#subDepartmentSelect", function () {
        const selectedSubDepts = $(this).val() || [];
        $.ajax({
            url: "employees/by-department",
            type: "POST",
            data: JSON.stringify({
                sub_department_ids: selectedSubDepts,
            }),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    let userOptions =
                        '<option value="">Select options</option>';
                    response.data.forEach((employee) => {
                        const fullName = `${employee.Fname} ${
                            employee.Sname || ""
                        } ${employee.Lname}`.trim();
                        const optionText = `${employee.EmpCode} - ${fullName}`;
                        const statusClass =
                            employee.EmpStatus === "D" ? "deactivated" : "";
                        userOptions += `<option value="${employee.EmployeeID}" data-status="${employee.EmpStatus}" class="${statusClass}">${optionText}</option>`;
                    });
                    $("#userSelect")
                        .val(null)
                        .trigger("change")
                        .html(userOptions)
                        .select2({
                            placeholder: "Select options",
                            allowClear: true,
                            width: "100%",
                        });
                } else {
                    console.error("Failed to fetch users:", response.message);
                    $("#userSelect")
                        .html('<option value="">Select options</option>')
                        .select2({
                            placeholder: "Select options",
                            allowClear: true,
                            width: "100%",
                        });
                }
            },
            error: function (xhr) {
                console.error("Error fetching users:", xhr.responseText);
                $("#userSelect")
                    .html('<option value="">Error loading users</option>')
                    .select2({
                        placeholder: "Select options",
                        allowClear: true,
                        width: "100%",
                    });
            },
        });
    });

    let table = null;

    function updateActiveFilterCount() {
        let filterCount = 0;

        const filterSelectors = [
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
            "#monthSelect",
            "#claimTypeSelect",
            "#policySelect",
            "#stageSelect",
            "#delayCategorySelect",
            "#columnSelect"
        ];

        filterSelectors.forEach((selector) => {
            const $element = $(selector);
            if ($element.length === 0) return; // Skip if element doesn't exist
            
            const value = $element.val();
            
            if (value && value.length > 0 && value !== "" && value !== "all") {
                if (Array.isArray(value)) {
                    // For multi-select, exclude "all" option and count actual selections
                    const filteredValues = value.filter(v => v !== "all" && v !== "");
                    filterCount += filteredValues.length;
                } else {
                    // For single select, count if not default value
                    if (value !== "all" && value !== "") {
                        filterCount += 1;
                    }
                }
            }
        });

        // Check date range picker
        const dateRangeValue = $("#dateRangePicker").val();
        if (dateRangeValue && dateRangeValue.trim() !== "") {
            filterCount += 1;
        }

        $("#activeFilterCount").text(filterCount);
    }

    function handleReportRequest() {
        const clickedButton = this;

        const reportType = $("#reportType").val();
        const month = $("#monthSelect").val();

        if (!reportType) {
            showAlert(
                "danger",
                "ri-error-warning-line",
                "Please select a report type"
            );
            return;
        }

        // For DA In/Out report, validate date range instead of month
        if (reportType === "da_in_out_report") {
            const dateRange = $("#dateRangePicker").val();
            if (!dateRange || dateRange.trim() === "") {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    "Please select a date range"
                );
                return;
            }
        } else {
            // For other reports, validate month selection (exclude "all" sentinel)
            const realMonths = Array.isArray(month)
                ? month.filter((v) => v !== "all" && v !== "")
                : [];
            if (realMonths.length === 0) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    "Please select a month"
                );
                return;
            }
        }

        // Strip the "all" sentinel value before sending to backend
        const monthValues = Array.isArray(month)
            ? month.filter((v) => v !== "all" && v !== "")
            : [];

        const payload = {
            reportType: reportType,
            monthSelect: monthValues,
            dateRange: $("#dateRangePicker").val(),
            stage: $("#stageSelect").val(),
            delayCategory: $("#delayCategorySelect").val(),
            columnSelect: $("#columnSelect").val() || [],
            functionSelect: $("#functionSelect").val() || [],
            verticalSelect: $("#verticalSelect").val() || [],
            departmentSelect: $("#departmentSelect").val() || [],
            subDepartmentSelect: $("#subDepartmentSelect").val() || [],
            buSelect: $("#buSelect").val() || [],
            zoneSelect: $("#zoneSelect").val() || [],
            regionSelect: $("#regionSelect").val() || [],
            territorySelect: $("#territorySelect").val() || [],
            hqSelect: $("#hqSelect").val() || [],
            userSelect: $("#userSelect").val() || [],
            policySelect: $("#policySelect").val() || [],
            claimTypeSelect: $("#claimTypeSelect").val() || [],
        };

        $.ajax({
            url: "/export-claim-stage",
            method: "POST",
            data: payload,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },

            beforeSend: function () {
                startSimpleLoader({
                    currentTarget: clickedButton,
                });
            },

            success: function (res) {
                if (res.status !== "success") {
                    alert(res.message);
                    return;
                }

                const rawUrl = res.file_url || "";
                const cleanUrl = rawUrl.replace(/([^:])\/\/+/g, "$1/");

                // Extract and display file name
                let fileName = "Claim_Stages_Report.xlsx";
                try {
                    const urlObj = new URL(cleanUrl);
                    const segments = urlObj.pathname.split("/").filter(Boolean);
                    if (segments.length > 0) {
                        fileName = decodeURIComponent(segments[segments.length - 1]);
                    }
                } catch (e) {
                    const parts = cleanUrl.split("/").filter(Boolean);
                    if (parts.length > 0) {
                        fileName = decodeURIComponent(parts[parts.length - 1]);
                    }
                }

                $("#previewFileName").text(fileName);
                $("#downloadReportBtn").attr("href", cleanUrl).attr("download", fileName);
                $("#openDirectBtn").attr("href", cleanUrl);

                // Setup viewer URLs
                let currentCloudViewer = "office";
                let currentViewMode = "native";
                let loadedWorkbook = null;
                let cloudViewerTimer = null;
                const officeEmbed = `https://view.officeapps.live.com/op/embed.aspx?wdDownloadButton=True&src=${encodeURIComponent(cleanUrl)}`;
                const googleEmbed = `https://docs.google.com/viewer?url=${encodeURIComponent(cleanUrl)}&embedded=true`;

                // Safe URL for in-browser fetch (use relative pathname if same origin to avoid mixed-content / CORS)
                let fetchUrl = cleanUrl;
                try {
                    const parsedUrl = new URL(cleanUrl, window.location.origin);
                    if (parsedUrl.hostname === window.location.hostname) {
                        fetchUrl = parsedUrl.pathname + parsedUrl.search;
                    }
                } catch (e) {
                    fetchUrl = cleanUrl;
                }

                function showViewerLoader(text) {
                    if (text) $("#viewerLoadingText").text(text);
                    $("#viewerLoadingOverlay").addClass("active");
                }

                function hideViewerLoader() {
                    $("#viewerLoadingOverlay").removeClass("active");
                }

                function showInteractiveTable() {
                    currentViewMode = "native";
                    if (cloudViewerTimer) clearTimeout(cloudViewerTimer);
                    hideViewerLoader();
                    $("#cloudIframeViewer").hide();
                    $("#nativeSheetViewer").show();
                    $("#toggleViewerBtn").removeClass("btn-primary").addClass("btn-outline-secondary");
                    $("#toggleViewerIcon").attr("class", "ri-external-link-line");
                    $("#toggleViewerText").text("Switch to Cloud Viewer");
                }

                function loadCloudViewer(type) {
                    showViewerLoader("Connecting to cloud viewer...");
                    if (type === "office") {
                        $("#excelFrame").attr("src", officeEmbed);
                        $("#toggleCloudProviderBtn").text("Switch to Google Viewer");
                        currentCloudViewer = "office";
                    } else {
                        $("#excelFrame").attr("src", googleEmbed);
                        $("#toggleCloudProviderBtn").text("Switch to Office Viewer");
                        currentCloudViewer = "google";
                    }

                    // Safety timeout: never hang indefinitely on cloud iframe
                    if (cloudViewerTimer) clearTimeout(cloudViewerTimer);
                    cloudViewerTimer = setTimeout(function () {
                        hideViewerLoader();
                    }, 3000);
                }

                $("#excelFrame").off("load error").on("load error", function () {
                    if (cloudViewerTimer) clearTimeout(cloudViewerTimer);
                    hideViewerLoader();
                });

                $("#toggleCloudProviderBtn").off("click").on("click", function () {
                    loadCloudViewer(currentCloudViewer === "office" ? "google" : "office");
                });

                $("#dismissViewerLoaderBtn").off("click").on("click", function () {
                    showInteractiveTable();
                });

                $("#toggleViewerBtn").off("click").on("click", function () {
                    if (currentViewMode === "native") {
                        currentViewMode = "cloud";
                        $("#nativeSheetViewer").hide();
                        $("#cloudIframeViewer").show();
                        $("#toggleViewerBtn").removeClass("btn-outline-secondary").addClass("btn-primary");
                        $("#toggleViewerIcon").attr("class", "ri-table-line");
                        $("#toggleViewerText").text("Switch to Interactive Table");
                        loadCloudViewer(currentCloudViewer);
                    } else {
                        showInteractiveTable();
                    }
                });

                function escapeCell(val) {
                    if (val === null || val === undefined) return "";
                    return String(val)
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
                }

                function renderSheetData(sheetName) {
                    if (!loadedWorkbook || !loadedWorkbook.Sheets[sheetName]) return;
                    const sheet = loadedWorkbook.Sheets[sheetName];
                    const rows = (typeof XLSX !== 'undefined') ? XLSX.utils.sheet_to_json(sheet, { header: 1, defval: "" }) : [];

                    if (!rows || rows.length === 0) {
                        $("#sheetTableContainer").html('<div class="text-center text-muted py-5"><i class="ri-inbox-line fs-2 d-block mb-1"></i>No rows found in this sheet</div>');
                        $("#sheetRowCountBadge").text("0 rows");
                        return;
                    }

                    const headerRow = rows[0] || [];
                    const dataRows = rows.slice(1);

                    let html = '<table class="table table-sm table-bordered table-hover align-middle mb-0" id="sheetRenderedTable" style="font-size:11px;">';
                    html += '<thead class="table-light sticky-top" style="z-index:2; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">';
                    html += '<tr><th class="text-center bg-light" style="width:45px;">#</th>';
                    headerRow.forEach(function (col) {
                        html += `<th class="fw-semibold text-dark text-nowrap bg-light px-2 py-1">${escapeCell(col)}</th>`;
                    });
                    html += '</tr></thead>';

                    html += '<tbody>';
                    dataRows.forEach(function (row, rIdx) {
                        html += `<tr class="sheet-data-row"><td class="text-center text-muted fw-semibold py-1">${rIdx + 1}</td>`;
                        for (let c = 0; c < headerRow.length; c++) {
                            const val = row[c] !== undefined && row[c] !== null ? row[c] : "";
                            const isNum = typeof val === "number" || (!isNaN(val) && val !== "" && !isNaN(parseFloat(val)) && !String(val).startsWith("0"));
                            const alignClass = isNum ? "text-end" : "text-start";
                            const display = typeof val === "number" ? val.toLocaleString("en-IN") : String(val);
                            html += `<td class="${alignClass} text-nowrap px-2 py-1">${escapeCell(display)}</td>`;
                        }
                        html += "</tr>";
                    });
                    html += "</tbody></table>";

                    $("#sheetTableContainer").html(html);
                    $("#sheetRowCountBadge").text(dataRows.length + " rows");
                    $("#sheetSearchInput").val("");
                }

                function renderWorkbookTabs(wb) {
                    const nav = $("#sheetTabsNav");
                    nav.empty();

                    wb.SheetNames.forEach(function (name, idx) {
                        const sheet = wb.Sheets[name];
                        let rowCount = 0;
                        if (sheet && sheet["!ref"]) {
                            const range = XLSX.utils.decode_range(sheet["!ref"]);
                            rowCount = Math.max(0, range.e.r);
                        }

                        const tabItem = $(`
                            <li class="nav-item">
                                <button class="nav-link ${idx === 0 ? 'active' : ''} py-1 px-2.5 fs-12" type="button" data-sheet="${escapeCell(name)}">
                                    <i class="ri-table-line me-1"></i>${escapeCell(name)} <span class="badge bg-secondary-subtle text-secondary ms-1">${rowCount}</span>
                                </button>
                            </li>
                        `);

                        tabItem.find("button").on("click", function () {
                            nav.find(".nav-link").removeClass("active");
                            $(this).addClass("active");
                            renderSheetData(name);
                        });

                        nav.append(tabItem);
                    });
                }

                // In-sheet search
                $("#sheetSearchInput").off("input").on("input", function () {
                    const q = $(this).val().toLowerCase().trim();
                    if (!q) {
                        $("#sheetTableContainer table tr").show();
                        return;
                    }
                    $("#sheetTableContainer table tr").each(function (idx) {
                        if (idx === 0) {
                            $(this).show();
                            return;
                        }
                        const txt = $(this).text().toLowerCase();
                        $(this).toggle(txt.indexOf(q) > -1);
                    });
                });

                function renderLaravelExcelPreview(preview) {
                    if (!preview || !preview.sheets || preview.sheets.length === 0) return false;

                    // Remove previously injected styles
                    $("#excelPreviewCustomStyles").remove();
                    if (preview.styles) {
                        $("head").append(`<style id="excelPreviewCustomStyles">${preview.styles}</style>`);
                    }

                    const nav = $("#sheetTabsNav");
                    nav.empty();

                    function showSheet(sheetIndex) {
                        const sheet = preview.sheets[sheetIndex];
                        if (!sheet) return;

                        // Inject sheet HTML
                        $("#sheetTableContainer").html(sheet.html);

                        // Ensure clean table layout
                        const $table = $("#sheetTableContainer table");
                        $table.addClass("table table-sm table-hover align-middle mb-0");

                        // Count rows
                        const rowCount = $table.find("tr").length;
                        $("#sheetRowCountBadge").text(rowCount > 0 ? (rowCount - 1) + " rows" : "0 rows");
                        $("#sheetSearchInput").val("");
                    }

                    preview.sheets.forEach(function (sheet, idx) {
                        const tabItem = $(`
                            <li class="nav-item">
                                <button class="nav-link ${idx === 0 ? 'active' : ''} py-1 px-2.5 fs-12" type="button">
                                    <i class="ri-table-line me-1"></i>${escapeCell(sheet.name)}
                                </button>
                            </li>
                        `);

                        tabItem.find("button").on("click", function () {
                            nav.find(".nav-link").removeClass("active");
                            $(this).addClass("active");
                            showSheet(idx);
                        });

                        nav.append(tabItem);
                    });

                    showSheet(0);
                    showInteractiveTable();
                    return true;
                }

                // Load Excel Preview: Prefer Laravel Excel backend HTML preview, fallback to SheetJS / Cloud Viewer
                showViewerLoader("Loading document preview...");
                $("#excelPreview").show();

                if (res.preview && renderLaravelExcelPreview(res.preview)) {
                    // Rendered successfully via Laravel Excel / PhpSpreadsheet
                } else if (typeof XLSX !== "undefined") {
                    fetch(fetchUrl)
                        .then(function (response) {
                            if (!response.ok) throw new Error("Could not download file for preview: " + response.status);
                            return response.arrayBuffer();
                        })
                        .then(function (buffer) {
                            const data = new Uint8Array(buffer);
                            loadedWorkbook = XLSX.read(data, { type: "array" });

                            if (!loadedWorkbook || !loadedWorkbook.SheetNames || loadedWorkbook.SheetNames.length === 0) {
                                throw new Error("No sheets found in workbook");
                            }

                            renderWorkbookTabs(loadedWorkbook);
                            renderSheetData(loadedWorkbook.SheetNames[0]);
                            showInteractiveTable();
                        })
                        .catch(function (err) {
                            console.warn("Native SheetJS render failed:", err);
                            hideViewerLoader();
                            if (loadedWorkbook && loadedWorkbook.SheetNames && loadedWorkbook.SheetNames.length > 0) {
                                showInteractiveTable();
                            } else {
                                $("#sheetTableContainer").html(`
                                    <div class="text-center py-5">
                                        <i class="ri-file-excel-line text-success fs-1 mb-2 d-block"></i>
                                        <h6 class="fw-semibold text-dark">Excel File Ready</h6>
                                        <p class="text-muted small mb-3">In-browser interactive preview could not be loaded. Please click Download Excel above to view.</p>
                                        <a href="${cleanUrl}" download="${fileName}" class="btn btn-success btn-sm">
                                            <i class="ri-download-2-line me-1"></i>Download ${escapeCell(fileName)}
                                        </a>
                                    </div>
                                `);
                                showInteractiveTable();
                            }
                        });
                } else {
                    hideViewerLoader();
                    showInteractiveTable();
                }

                if ($("#excelPreview").length) {
                    $("html, body").animate({
                        scrollTop: $("#excelPreview").offset().top - 80,
                    }, 300);
                }
            },

            complete: function () {
                endSimpleLoader({
                    currentTarget: clickedButton,
                });
            },
        });
    }

    $("#searchButton, #applyFiltersBtn")
        .off("click")
        .on("click", handleReportRequest);

    $(document).on(
        "change",
        "#functionSelect, #verticalSelect, #departmentSelect, #subDepartmentSelect, #userSelect, #monthSelect, #claimTypeSelect, #policySelect, #stageSelect, #delayCategorySelect, #columnSelect",
        function () {
            updateActiveFilterCount();
        }
    );

    // Initialize filter count on page load
    updateActiveFilterCount();
});
