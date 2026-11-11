$(document).ready(function () {

    // -------------------------------------------------------------------------
    // Select2 initialisation
    // -------------------------------------------------------------------------

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
        escapeMarkup: function (m) { return m; },
    });

    const selectConfigs = {
        "#functionSelect":       "Select Function",
        "#verticalSelect":       "Select Vertical",
        "#departmentSelect":     "Select Department",
        "#subDepartmentSelect":  "Select Sub-Department",
        "#buSelect":             "Select Business Unit",
        "#zoneSelect":           "Select Zone",
        "#regionSelect":         "Select Region",
        "#territorySelect":      "Select Territory",
        "#hqSelect":             "Select HQ",
        "#policySelect":         "Select Policy",
        "#userSelect":           "Select Employee",
        "#activityTypeSelect":   "Select Activity Type",
        "#activityNameSelect":   "Select Activity Name",
        "#monthSelect":          "Select Month",
    };

    Object.entries(selectConfigs).forEach(([selector, placeholder]) => {
        $(selector).select2({
            width: "100%",
            placeholder: placeholder,
            allowClear: true,
            multiple: true,
        });
    });

    // -------------------------------------------------------------------------
    // Month "Select All" sentinel
    // -------------------------------------------------------------------------

    $("#monthSelect").on("change", function () {
        const selected = $(this).val();
        if (selected && selected.includes("all")) {
            $("#monthSelect option").prop("selected", true);
        } else {
            $('#monthSelect option[value="all"]').prop("selected", false);
        }
    });

    // -------------------------------------------------------------------------
    // Cascading selects (function → vertical → department → sub-dept / employee)
    // -------------------------------------------------------------------------

    $(document).on("change", "#functionSelect", function () {
        const ids = $(this).val() || [];
        $.ajax({
            url: "verticals/by-function",
            type: "POST",
            data: JSON.stringify({ function_ids: ids }),
            contentType: "application/json",
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") },
            success: function (res) {
                let opts = '<option value="">Select options</option>';
                if (res.success) {
                    res.data.forEach(v => { opts += `<option value="${v.id}">${v.vertical_name}</option>`; });
                }
                $("#verticalSelect").html(opts).select2({ placeholder: "Select Vertical", allowClear: true, width: "100%" });
            },
        });
    });

    $(document).on("change", "#verticalSelect", function () {
        const ids = $(this).val() || [];
        $.ajax({
            url: "departments/by-vertical",
            type: "POST",
            data: JSON.stringify({ vertical_ids: ids }),
            contentType: "application/json",
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") },
            success: function (res) {
                let opts = '<option value="">Select options</option>';
                if (res.success) {
                    res.data.forEach(d => { opts += `<option value="${d.id}">${d.department_name}</option>`; });
                }
                $("#departmentSelect").html(opts).select2({ placeholder: "Select Department", allowClear: true, width: "100%" });
                $("#subDepartmentSelect, #userSelect")
                    .html('<option value="">Select options</option>')
                    .select2({ placeholder: "Select options", allowClear: true, width: "100%" });
            },
        });
    });

    $(document).on("change", "#departmentSelect", function () {
        const ids = $(this).val() || [];

        $.ajax({
            url: "sub-departments/by-department",
            type: "POST",
            data: JSON.stringify({ department_ids: ids }),
            contentType: "application/json",
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") },
            success: function (res) {
                let opts = '<option value="">Select options</option>';
                if (res.success) {
                    res.data.forEach(s => { opts += `<option value="${s.id}">${s.sub_department_name}</option>`; });
                }
                $("#subDepartmentSelect").html(opts).select2({ placeholder: "Select Sub-Department", allowClear: true, width: "100%" });
            },
        });

        $.ajax({
            url: "employees/by-department",
            type: "POST",
            data: JSON.stringify({ department_ids: ids }),
            contentType: "application/json",
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") },
            success: function (res) {
                let opts = '<option value="">Select options</option>';
                if (res.success) {
                    res.data.forEach(function (emp) {
                        const name = `${emp.Fname} ${emp.Sname || ""} ${emp.Lname}`.trim();
                        const cls  = emp.EmpStatus === "D" ? "deactivated" : "";
                        opts += `<option value="${emp.EmployeeID}" data-status="${emp.EmpStatus}" class="${cls}">${emp.EmpCode} - ${name}</option>`;
                    });
                }
                $("#userSelect").val(null).trigger("change").html(opts)
                    .select2({ placeholder: "Select Employee", allowClear: true, width: "100%" });
            },
        });
    });

    $(document).on("change", "#subDepartmentSelect", function () {
        const ids = $(this).val() || [];
        $.ajax({
            url: "employees/by-department",
            type: "POST",
            data: JSON.stringify({ sub_department_ids: ids }),
            contentType: "application/json",
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") },
            success: function (res) {
                let opts = '<option value="">Select options</option>';
                if (res.success) {
                    res.data.forEach(function (emp) {
                        const name = `${emp.Fname} ${emp.Sname || ""} ${emp.Lname}`.trim();
                        const cls  = emp.EmpStatus === "D" ? "deactivated" : "";
                        opts += `<option value="${emp.EmployeeID}" data-status="${emp.EmpStatus}" class="${cls}">${emp.EmpCode} - ${name}</option>`;
                    });
                }
                $("#userSelect").val(null).trigger("change").html(opts)
                    .select2({ placeholder: "Select Employee", allowClear: true, width: "100%" });
            },
        });
    });

    // -------------------------------------------------------------------------
    // Active filter count badge
    // -------------------------------------------------------------------------

    function updateActiveFilterCount() {
        const filterSelectors = [
            "#functionSelect", "#verticalSelect", "#departmentSelect",
            "#subDepartmentSelect", "#buSelect", "#zoneSelect", "#regionSelect",
            "#territorySelect", "#hqSelect", "#policySelect",
            "#userSelect", "#activityTypeSelect", "#activityNameSelect",
        ];
        let count = 0;
        filterSelectors.forEach(function (sel) {
            const val = $(sel).val();
            if (val && Array.isArray(val)) {
                count += val.filter(v => v !== "" && v !== "all").length;
            }
        });
        if ($('input[name="autoAdvanceFilter"]:checked').val() !== "all") {
            count += 1;
        }
        ["#submitDateFrom","#submitDateTo","#approveDateFrom","#approveDateTo"].forEach(function(sel){
            if ($(sel).val()) count += 1;
        });
        $("#activeFilterCount").text(count);
    }

    $(document).on(
        "change",
        "#functionSelect, #verticalSelect, #departmentSelect, #subDepartmentSelect, #buSelect, #zoneSelect, #regionSelect, #territorySelect, #hqSelect, #policySelect, #userSelect, #activityTypeSelect, #activityNameSelect",
        updateActiveFilterCount
    );

    $('input[name="autoAdvanceFilter"]').on("change", updateActiveFilterCount);
    $("#submitDateFrom, #submitDateTo, #approveDateFrom, #approveDateTo").on("change", updateActiveFilterCount);

    $("#resetAdvanceFiltersBtn").on("click", function () {
        [
            "#functionSelect", "#verticalSelect", "#departmentSelect",
            "#subDepartmentSelect", "#buSelect", "#zoneSelect", "#regionSelect",
            "#territorySelect", "#hqSelect", "#policySelect",
            "#userSelect", "#activityTypeSelect", "#activityNameSelect"
        ].forEach(function (sel) {
            $(sel).val(null).trigger("change");
        });
        $('input[name="autoAdvanceFilter"][value="all"]').prop("checked", true).trigger("change");
        $("#submitDateFrom, #submitDateTo, #approveDateFrom, #approveDateTo").val("").trigger("change");
        updateActiveFilterCount();
    });

    updateActiveFilterCount();

    // -------------------------------------------------------------------------
    // Export handler
    // -------------------------------------------------------------------------

    function handleReportRequest() {
        const clickedButton = this;
        const reportType    = $("#reportType").val();
        const month         = $("#monthSelect").val();

        if (!reportType) {
            showAlert("danger", "ri-error-warning-line", "Please select a report type");
            return;
        }

        const realMonths = Array.isArray(month)
            ? month.filter(v => v !== "all" && v !== "")
            : [];

        const submitDateFrom  = $("#submitDateFrom").val();
        const submitDateTo    = $("#submitDateTo").val();
        const approveDateFrom = $("#approveDateFrom").val();
        const approveDateTo   = $("#approveDateTo").val();
        const hasDateRange    = submitDateFrom || submitDateTo || approveDateFrom || approveDateTo;

        if (realMonths.length === 0 && !hasDateRange) {
            showAlert("danger", "ri-error-warning-line", "Please select a month or provide a date range filter");
            return;
        }

        const payload = {
            reportType:          reportType,
            monthSelect:         realMonths,
            functionSelect:      $("#functionSelect").val()      || [],
            verticalSelect:      $("#verticalSelect").val()      || [],
            departmentSelect:    $("#departmentSelect").val()    || [],
            subDepartmentSelect: $("#subDepartmentSelect").val() || [],
            buSelect:            $("#buSelect").val()            || [],
            zoneSelect:          $("#zoneSelect").val()          || [],
            regionSelect:        $("#regionSelect").val()        || [],
            territorySelect:     $("#territorySelect").val()     || [],
            hqSelect:            $("#hqSelect").val()            || [],
            policySelect:        $("#policySelect").val()        || [],
            userSelect:          $("#userSelect").val()          || [],
            activityTypeSelect:  $("#activityTypeSelect").val()  || [],
            activityNameSelect:  $("#activityNameSelect").val()  || [],
            autoAdvanceFilter:   $('input[name="autoAdvanceFilter"]:checked').val() || "all",
            submitDateFrom:      submitDateFrom  || "",
            submitDateTo:        submitDateTo    || "",
            approveDateFrom:     approveDateFrom || "",
            approveDateTo:       approveDateTo   || "",
        };

        $.ajax({
            url: "/advance-report/export",
            method: "POST",
            data: payload,
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") },

            beforeSend: function () {
                startSimpleLoader({ currentTarget: clickedButton });
            },

            success: function (res) {
                if (res.status !== "success") {
                    alert(res.message);
                    return;
                }
                const cleanUrl = (res.file_url || "").replace(/([^:])\/\/+/g, "$1/");
                const embed = `https://view.officeapps.live.com/op/embed.aspx?wdDownloadButton=True&src=${encodeURIComponent(cleanUrl)}`;
                $("#excelFrame").attr("src", embed);
                $("#excelPreview").show();
            },

            complete: function () {
                endSimpleLoader({ currentTarget: clickedButton });
            },
        });
    }

    $("#searchButton, #applyFiltersBtn").off("click").on("click", handleReportRequest);
});
