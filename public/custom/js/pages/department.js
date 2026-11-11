document.addEventListener("DOMContentLoaded", function () {
    const selectAllCheckbox = document.getElementById("selectAllDepartments");
    const departmentCheckboxes = document.querySelectorAll(
        ".department-checkbox"
    );
    const selectedDepartmentsSpan = document.getElementById(
        "selectedDepartments"
    );

    function updateSelectedText() {
        const checked = Array.from(departmentCheckboxes)
            .filter((cb) => cb.checked)
            .map((cb) => {
                const label = cb.nextElementSibling.textContent.trim();
                return label.length > 20
                    ? label.substring(0, 17) + "..."
                    : label;
            });

        selectedDepartmentsSpan.textContent =
            checked.length === 0
                ? "All Departments"
                : checked.length > 2
                ? `${checked.length} selected`
                : checked.join(", ");
    }

    selectAllCheckbox.addEventListener("change", function () {
        departmentCheckboxes.forEach((cb) => {
            cb.checked = selectAllCheckbox.checked;
        });
        updateSelectedText();
    });

    departmentCheckboxes.forEach((cb) => {
        cb.addEventListener("change", function () {
            selectAllCheckbox.checked = false;

            const allChecked = Array.from(departmentCheckboxes).every(
                (cb) => cb.checked
            );
            if (allChecked && departmentCheckboxes.length > 0) {
                selectAllCheckbox.checked = true;
            }

            updateSelectedText();
        });
    });
});
$(function () {
    let barChartInstance, pieChartInstance, lineChartInstance;

    const today = new Date();

    const dateInput = document.getElementById("dateRange");

    const datePicker = flatpickr(dateInput, {
        mode: "range",
        dateFormat: "d M, Y",
        maxDate: new Date(today.setHours(23, 59, 59, 999)),
        defaultDate: [new Date(today.getFullYear(), 3, 1)],
        onClose: function (selectedDates) {
            if (selectedDates.length === 2) {
                const [start, end] = selectedDates;
                const billDateFrom = formatDate(start);
                const billDateTo = formatDate(end);
                fetchDashboardData(billDateFrom, billDateTo);
            }
        },
    });

    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };

    const formatCurrency = (v) =>
        new Intl.NumberFormat("en-IN", {
            style: "currency",
            currency: "INR",
            maximumFractionDigits: 0,
        }).format(v);

    const fetchDashboardData = (
        billDateFrom,
        billDateTo,
        filterType = "all",
        sortBy = "variation",
        department = ""
    ) => {
        $.ajax({
            url: "/analytics-dashboard-data",
            method: "get",
            beforeSend: function () {
                startPageLoader();
            },
            data: {
                bill_date_from: billDateFrom,
                bill_date_to: billDateTo,
                filter_type: filterType,
                sort_by: sortBy,
                department: department,
                _token: "{{ csrf_token() }}",
            },
            success: function (response) {
                updateDashboard(response.data.departments, filterType, sortBy);
                renderEmployeeTable(
                    response.data.topEmployees,
                    "tblTopEmployees"
                );
                renderEmployeeTable(
                    response.data.topEmployeesSameDay,
                    "tblTopEmployeesSameDay"
                );
                renderEmployeeTable(
                    response.data.topEmployeesRevert,
                    "tblTopEmployeesRevert"
                );
                renderDepartmentMonthlyChart(
                    response.data.departmentMonthlyTotals
                );
                renderClaimTypeWiseTable(
                    response.data.departmentTotalsClaimTypeWise,
                    "#claimTypeWiseContainer"
                );

                endPageLoader();
            },
            error: function (xhr) {
                alert(
                    "Error fetching data: " +
                        (xhr.responseJSON?.message || "Unknown error")
                );
                endPageLoader();
            },
        });
    };

    const updateDashboard = (expenseData, filterType, sortBy) => {
        const filterData = (data, filterType) => {
            if (filterType === "increased") {
                return data.filter((d) => d.variation > 0);
            } else if (filterType === "decreased") {
                return data.filter((d) => d.variation < 0);
            } else if (filterType === "critical") {
                return data.filter((d) => Math.abs(d.variation) > 50);
            }
            return data;
        };

        const sortData = (data, sortBy) => {
            if (sortBy === "variation") {
                return data.sort((a, b) => b.variation - a.variation);
            } else if (sortBy === "current") {
                return data.sort((a, b) => b.currentYear - a.currentYear);
            } else if (sortBy === "previous") {
                return data.sort((a, b) => b.previousYear - a.previousYear);
            }
            return data;
        };

        const calculateMetrics = (data) => {
            let totalCurrent = 0;
            let totalPrev = 0;

            let critical = 0;
            let improving = 0;
            let declining = 0;
            let stable = 0;

            data.forEach((d) => {
                const variation =
                    d.previousYear > 0
                        ? ((d.currentYear - d.previousYear) / d.previousYear) *
                          100
                        : 0;

                d.variation = parseFloat(variation.toFixed(2));

                totalCurrent += d.currentYear;
                totalPrev += d.previousYear;

                if (Math.abs(d.variation) > 50) {
                    critical++;
                } else if (d.variation > 0) {
                    improving++;
                } else if (d.variation < 0) {
                    declining++;
                } else {
                    stable++;
                }
            });

            const overallVariation =
                totalPrev > 0
                    ? parseFloat(
                          (
                              ((totalCurrent - totalPrev) / totalPrev) *
                              100
                          ).toFixed(2)
                      )
                    : 0;

            return {
                totalCurrent,
                totalPrev,
                variation: overallVariation,
                critical,
                improving,
                declining,
                stable,
            };
        };

        const updateMetricsDisplay = ({
            totalCurrent,
            totalPrev,
            variation,
            critical,
            improving,
        }) => {
            $("#totalCurrent").text(formatCurrency(totalCurrent));
            $("#totalPrevious").text(formatCurrency(totalPrev));
            $("#overallVariation").text(
                (variation > 0 ? "+" : "") + variation + "%"
            );
        };

        const getVariationBadge = (variation) => {
            if (Math.abs(variation) > 50) {
                return `<span class="badge bg-warning-subtle text-warning">Critical</span>`;
            }
            if (variation > 0) {
                return `<span class="badge bg-success-subtle text-success">Increased</span>`;
            }
            if (variation < 0) {
                return `<span class="badge bg-danger-subtle text-danger">Decreased</span>`;
            }
            return `<span class="badge bg-secondary-subtle text-secondary">Stable</span>`;
        };

        const destroyCharts = () => {
            if (barChartInstance) barChartInstance.destroy();
            if (pieChartInstance) pieChartInstance.destroy();
            if (lineChartInstance) lineChartInstance.destroy();
        };

        const renderBarChart = (data) => {
            barChartInstance = new Chart($("#barChart"), {
                type: "bar",
                data: {
                    labels: data.map((d) => d.code),
                    datasets: [
                        {
                            label: "Previous Year",
                            data: data.map((d) => d.previousYear),
                            backgroundColor: "#94a3b8",
                        },
                        {
                            label: "Current Year",
                            data: data.map((d) => d.currentYear),
                            backgroundColor: "#3b82f6",
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (ctx) => formatCurrency(ctx.raw),
                            },
                        },
                    },
                },
            });
        };

        const renderPieChart = (data) => {
            pieChartInstance = new Chart($("#pieChart"), {
                type: "pie",
                data: {
                    labels: data.map((d) => d.department),
                    datasets: [
                        {
                            data: data.map((d) => d.currentYear),
                            backgroundColor: [
                                "#8884d8",
                                "#82ca9d",
                                "#ffc658",
                                "#ff7c7c",
                                "#8dd1e1",
                                "#d084d0",
                                "#87ceeb",
                                "#dda0dd",
                                "#a0522d",
                                "#ffb347",
                                "#3cb371",
                                "#20b2aa",
                                "#9370db",
                                "#4682b4",
                                "#ff69b4",
                                "#cd5c5c",
                                "#40e0d0",
                                "#9acd32",
                                "#ff6347",
                            ],
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: "right",
                            labels: {
                                generateLabels: (chart) => {
                                    const data = chart.data;
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        return {
                                            text: `${label} – ${value.toLocaleString(
                                                "en-IN"
                                            )}`,
                                            fillStyle:
                                                data.datasets[0]
                                                    .backgroundColor[i],
                                            strokeStyle:
                                                data.datasets[0]
                                                    .backgroundColor[i],
                                            fontColor: "#666666",
                                            hidden:
                                                isNaN(value) || value === null,
                                            index: i,
                                        };
                                    });
                                },
                                color: "#666666",
                            },
                        },
                    },
                },
            });
        };

        const getStatusAndClass = (variation) => {
            let status = "";
            let rowClass = "";
            let tooltipText = "";

            if (variation > 100) {
                status = `<span class="text-success" data-bs-toggle="tooltip" 
                                                                                                                                                  title="Expense increased drastically compared to last year.">
                                                                                                                                                  <i class="ri-rocket-2-line me-1"></i> Highly Increased
                                                                                                                                                </span>`;
                rowClass = "table-success";
            } else if (variation > 20) {
                status = `<span class="text-success" data-bs-toggle="tooltip" 
                                                                                                                                                  title="Expenses are higher than last year, monitor the trend.">
                                                                                                                                                  <i class="ri-line-chart-line me-1"></i> Increased
                                                                                                                                                </span>`;
                rowClass = "table-light";
            } else if (variation < -50) {
                status = `<span class="text-danger" data-bs-toggle="tooltip" 
                                                                                                                                                  title="Expense dropped heavily compared to last year. Needs urgent attention.">
                                                                                                                                                  <i class="ri-error-warning-fill me-1"></i> Critical
                                                                                                                                                </span>`;
                rowClass = "table-danger";
            } else if (variation < -20) {
                status = `<span class="text-primary" data-bs-toggle="tooltip" 
                                                                                                                                                  title="Expenses are lower than last year. Consider validating the reason.">
                                                                                                                                                  <i class="ri-bar-chart-2-line me-1"></i> Decreased
                                                                                                                                                </span>`;
                rowClass = "table-info";
            } else if (variation < 0) {
                status = `<span class="text-warning" data-bs-toggle="tooltip" 
                                                                                                                                                  title="Small decrease compared to last year. Not a major concern.">
                                                                                                                                                  <i class="ri-arrow-down-line me-1"></i> Slightly Decreased
                                                                                                                                                </span>`;
                rowClass = "table-warning";
            } else {
                status = `<span class="text-muted" data-bs-toggle="tooltip" 
                                                                                                                                                  title="No significant change compared to last year.">
                                                                                                                                                  <i class="ri-subtract-line me-1"></i> Stable
                                                                                                                                                </span>`;
                rowClass = "";
            }

            return {
                status,
                rowClass,
            };
        };

        const renderDepartmentTable = (data) => {
            let rows = "";
            let overallPrevTotal = 0;
            let overallCurrTotal = 0;
            const metrics = calculateMetrics(data);

            data.forEach((d, idx) => {
                overallPrevTotal += d.previousYear;
                overallCurrTotal += d.currentYear;

                const { status, rowClass } = getStatusAndClass(d.variation);

                rows += `
                        <tr class="department-row" data-dept="${
                            d.department_id
                        }">
                          <td class="toggle-icon text-center" title="View"><i class="ri-add-circle-fill"></i></td>
                            <td class="text-center">${idx + 1}</td>
                            <td class="text-start">${d.department ?? "-"}</td>
                            <td class="text-end">${formatCurrency(
                                d.previousYear
                            )}</td>
                            <td class="text-end">${formatCurrency(
                                d.currentYear
                            )}</td>
                            <td class="text-center ${getVariationClass(
                                d.variation
                            )}">
                                ${d.variation > 0 ? "+" : ""}${d.variation}%
                            </td>
                        </tr>
                        <tr class="dept-detail-row" id="dept-detail-${
                            d.department_id
                        }" style="display:none;">
                            <td colspan="6" style="background:#f8f9fa;">
                                <div class="dept-detail-content text-center">Loading...</div>
                            </td>
                        </tr>`;
            });

            const overallVariationPercent =
                overallPrevTotal === 0
                    ? 0
                    : (
                          ((overallCurrTotal - overallPrevTotal) /
                              overallPrevTotal) *
                          100
                      ).toFixed(2);

            const { status: overallStatus, rowClass: overallRowClass } =
                getStatusAndClass(overallVariationPercent);

            rows += `<tr class="fw-bold">
                                                                                                                                     <td class="text-center" colspan='2'>#</td>
                                                                                                                                            <td class="text-start">Overall Total</td>
                                                                                                                                            <td class="text-end">${formatCurrency(
                                                                                                                                                overallPrevTotal
                                                                                                                                            )}</td>
                                                                                                                                            <td class="text-end">${formatCurrency(
                                                                                                                                                overallCurrTotal
                                                                                                                                            )}</td>
                                                                                                                                            <td class="text-center ${
                                                                                                                                                overallVariationPercent >
                                                                                                                                                0
                                                                                                                                                    ? "text-success"
                                                                                                                                                    : overallVariationPercent <
                                                                                                                                                      0
                                                                                                                                                    ? "text-danger"
                                                                                                                                                    : "text-muted"
                                                                                                                                            }">
                                                                                                                                              ${
                                                                                                                                                  overallVariationPercent >
                                                                                                                                                  0
                                                                                                                                                      ? "+"
                                                                                                                                                      : ""
                                                                                                                                              }${overallVariationPercent}%
                                                                                                                                            </td>

                                                                                                                                          </tr>`;

            $("#deptTable tbody").html(rows);

            $("#increasedCount").text(metrics.improving);
            $("#decreasedCount").text(metrics.declining);
            $("#criticalCount").text(metrics.critical);
        };

        let filteredData = filterData(expenseData, filterType);
        filteredData = sortData(filteredData, sortBy);
        const metrics = calculateMetrics(filteredData);
        updateMetricsDisplay(metrics);
        destroyCharts();
        renderBarChart(filteredData);
        renderPieChart(filteredData);
        renderDepartmentTable(filteredData);
    };

    const getVariationClass = (variation) => {
        if (Math.abs(variation) > 50) return "text-warning";
        if (variation > 0) return "text-success";
        if (variation < 0) return "text-danger";
        return "text-muted";
    };

    const defaultStart = new Date(today.getFullYear(), 3, 1);
    const defaultEnd = today;

    datePicker.setDate([defaultStart, defaultEnd]);

    function formatDateForAPI(date) {
        return date.toISOString().split("T")[0];
    }

    fetchDashboardData(formatDate(defaultStart), formatDate(defaultEnd));

    $("#filterType, #sortBy").on("change", function () {
        const selectedDates = datePicker.selectedDates;
        if (selectedDates.length === 2) {
            const billDateFrom = formatDate(selectedDates[0]);
            const billDateTo = formatDate(selectedDates[1]);
            const filterType = $("#filterType").val();
            const sortBy = $("#sortBy").val();
            const departments = $('input[name="departments[]"]:checked')
                .map(function () {
                    return this.value;
                })
                .get();
            fetchDashboardData(
                billDateFrom,
                billDateTo,
                filterType,
                sortBy,
                departments.length > 0 ? departments : null
            );
        }
    });

    $('input[name="departments[]"]').on("change", function () {
        const selectedDates = datePicker.selectedDates;
        if (selectedDates.length === 2) {
            const billDateFrom = formatDate(selectedDates[0]);
            const billDateTo = formatDate(selectedDates[1]);
            const filterType = $("#filterType").val();
            const sortBy = $("#sortBy").val();
            const departments = $('input[name="departments[]"]:checked')
                .map(function () {
                    return this.value;
                })
                .get();
            fetchDashboardData(
                billDateFrom,
                billDateTo,
                filterType,
                sortBy,
                departments.length > 0 ? departments : null
            );
        }
    });

    $("#selectAllDepartments").on("change", function () {
        const selectedDates = datePicker.selectedDates;
        if (selectedDates.length === 2) {
            const billDateFrom = formatDate(selectedDates[0]);
            const billDateTo = formatDate(selectedDates[1]);
            const filterType = $("#filterType").val();
            const sortBy = $("#sortBy").val();
            const departments = $('input[name="departments[]"]:checked')
                .map(function () {
                    return this.value;
                })
                .get();
            fetchDashboardData(
                billDateFrom,
                billDateTo,
                filterType,
                sortBy,
                departments.length > 0 ? departments : null
            );
        }
    });

    $(document).on("click", ".department-row", function () {
        const selectedDates = datePicker.selectedDates;
        if (!selectedDates || selectedDates.length < 2) {
            alert("Please select a valid date range first.");
            return;
        }

        const billDateFrom = formatDateForAPI(selectedDates[0]);
        const billDateTo = formatDateForAPI(selectedDates[1]);

        const $row = $(this);
        const deptId = $row.data("dept");
        const $detailRow = $("#dept-detail-" + deptId);
        const $content = $detailRow.find(".dept-detail-content");

        $(".dept-detail-row").not($detailRow).hide();
        $(".department-row").not($row).removeClass("active-row");

        if ($detailRow.is(":visible")) {
            $detailRow.hide();
            $row.removeClass("active-row");
            return;
        }

        $row.addClass("active-row");
        $detailRow.show();
        $content.html("<p>Loading...</p>");

        $.ajax({
            url: "/get-sub-departments",
            method: "POST",
            data: {
                department_id: deptId,
                bill_date_from: billDateFrom,
                bill_date_to: billDateTo,
            },
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (res) {
                const subData = Array.isArray(res) ? res : res.data;

                if (subData && subData.length) {
                    let prevTotal = 0,
                        currTotal = 0;

                    subData.forEach((sd) => {
                        prevTotal += parseFloat(sd.TotalFinancedTAmt_Y6) || 0;
                        currTotal += parseFloat(sd.TotalFinancedTAmt_Y7) || 0;
                    });

                    let variation =
                        prevTotal === 0
                            ? 0
                            : (
                                  ((currTotal - prevTotal) / prevTotal) *
                                  100
                              ).toFixed(2);

                    let html = `
                <canvas id="dept-chart-${deptId}" style="width: 100%; height: 200px; display: block;" class="mb-3"></canvas>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th class="text-start">Sub Department</th>
                            <th class="text-end">Prev Year</th>
                            <th class="text-end">Curr Year</th>
                            <th class="text-center">Variation</th>
                        </tr>
                    </thead>
                    <tbody>`;

                    subData.forEach((sd, i) => {
                        html += `
                    <tr>
                   
                        <td>${i + 1}</td>
                        <td class="text-start">${
                            sd.sub_department_name || "-"
                        }</td>
                        <td class="text-end">${formatCurrency(
                            sd.TotalFinancedTAmt_Y6
                        )}</td>
                        <td class="text-end">${formatCurrency(
                            sd.TotalFinancedTAmt_Y7
                        )}</td>
                        <td class="text-center ${getVariationClass(
                            sd.VariationPercentage
                        )}">
                            ${sd.VariationPercentage > 0 ? "+" : ""}${
                            sd.VariationPercentage
                        }%
                        </td>
                    </tr>`;
                    });

                    html += `
                <tr class="fw-bold table-light">
                    <td colspan="2" class="text-start">Grand Total</td>
                    <td class="text-end">${formatCurrency(prevTotal)}</td>
                    <td class="text-end">${formatCurrency(currTotal)}</td>
                    <td class="text-center ${getVariationClass(variation)}">
                        ${variation > 0 ? "+" : ""}${variation}%
                    </td>
                </tr>`;

                    html += `</tbody></table>`;

                    $content.html(html);

                    const ctx = document
                        .getElementById(`dept-chart-${deptId}`)
                        .getContext("2d");

                    new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: subData.map(
                                (sd) => sd.sub_department_name || "-"
                            ),
                            datasets: [
                                {
                                    label: "Prev Year",
                                    data: subData.map(
                                        (sd) =>
                                            parseFloat(
                                                sd.TotalFinancedTAmt_Y6
                                            ) || 0
                                    ),
                                    backgroundColor: "rgba(75, 192, 192, 0.6)",
                                },
                                {
                                    label: "Curr Year",
                                    data: subData.map(
                                        (sd) =>
                                            parseFloat(
                                                sd.TotalFinancedTAmt_Y7
                                            ) || 0
                                    ),
                                    backgroundColor: "rgba(54, 162, 235, 0.6)",
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: "top" },
                                title: {
                                    display: false,
                                    text: "Sub-Department Analysis",
                                },
                            },
                            scales: {
                                y: { beginAtZero: true },
                            },
                        },
                    });
                } else {
                    $content.html("<p>No sub-department data found.</p>");
                }
            },
            error: function () {
                $content.html(
                    "<p class='text-danger'>Error loading sub-departments</p>"
                );
            },
        });
    });

    $("#checkAll").on("change", function () {
        $(".report-option").prop("checked", $(this).is(":checked"));
    });

    $(".report-option").on("change", function () {
        if (!$(this).is(":checked")) {
            $("#checkAll").prop("checked", false);
        } else if (
            $(".report-option:checked").length === $(".report-option").length
        ) {
            $("#checkAll").prop("checked", true);
        }
    });

    $("#exportReports").on("click", function () {
        updateModalDateRange();
        const button = this;
        const selected = $(".report-option:checked")
            .map(function () {
                return $(this).val();
            })
            .get();

        if (selected.length === 0) {
            showAlert(
                "danger",
                "ri-error-warning-line",
                "Please select at least one report to export"
            );
            return;
        }

        const selectedDates = datePicker.selectedDates;
        if (!selectedDates || selectedDates.length < 2) {
            showAlert(
                "danger",
                "ri-error-warning-line",
                "Please select a valid date range first"
            );
            return;
        }

        const billDateFrom = formatDateForAPI(selectedDates[0]);
        const billDateTo = formatDateForAPI(selectedDates[1]);

        console.log(
            "Exporting reports:",
            selected,
            "From:",
            billDateFrom,
            "To:",
            billDateTo
        );

        $.ajax({
            url: "/export-reports",
            method: "POST",
            data: {
                reports: selected,
                bill_date_from: billDateFrom,
                bill_date_to: billDateTo,
            },
            xhrFields: {
                responseType: "blob",
            },
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            beforeSend: function () {
                startLoader({ currentTarget: button });
            },
            success: function (data, textStatus, xhr) {
                const contentType = xhr.getResponseHeader("content-type") || "";
                if (contentType.includes("application/json")) {
                    const reader = new FileReader();
                    reader.onload = function () {
                        const response = JSON.parse(reader.result);
                        showAlert(
                            "danger",
                            "ri-error-warning-line",
                            response.error || "Export failed."
                        );
                    };
                    reader.readAsText(data);
                    return;
                }

                const startDate = billDateFrom.replace(/-/g, "");
                const endDate = billDateTo.replace(/-/g, "");
                const reportNames = {
                    department_expense_comparison:
                        "Department_Expense_Comparison",
                    expense_month_wise: "Expense_Month_Wise",
                    current_year_expense_distribution:
                        "Current_Year_Expense_Distribution",
                    monthly_trend_analysis: "Monthly_Trend_Analysis",
                    department_claim_type_totals:
                        "Department_Claim_Type_Totals",
                };

                const blob = new Blob([data], { type: contentType });
                const url = window.URL.createObjectURL(blob);
                const a = $("<a>", {
                    href: url,
                    download: `reports_${startDate}_to_${endDate}.zip`,
                }).appendTo("body");
                a[0].click();
                a.remove();
                window.URL.revokeObjectURL(url);
                showAlert("success", "ri-check-line", "Export completed!");
                $("#exportModal").modal("hide");
            },
            error: function () {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    "Error while exporting reports"
                );
            },
            complete: function () {
                endLoader({ currentTarget: button });
            },
        });
    });

    $("#exportModal").on("show.bs.modal", function () {
        updateModalDateRange();
    });

    function updateModalDateRange() {
        const selectedDates = datePicker.selectedDates;
        const displaySpan = $("#selectedDateRange");

        if (selectedDates && selectedDates.length >= 2) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const fromDate = new Date(selectedDates[0]);
            if (fromDate.getTime() !== today.getTime()) {
                fromDate.setDate(fromDate.getDate() + 1);
            }
            const from = formatDateForAPI(fromDate);

            const toDate = new Date(selectedDates[1]);
            if (toDate.getTime() !== today.getTime()) {
                toDate.setDate(toDate.getDate() + 1);
            }
            const to = formatDateForAPI(toDate);

            displaySpan.text(`${from} to ${to}`);
        } else {
            displaySpan.text("None");
        }
    }

    function renderEmployeeTable(data, tableId) {
        let rows = "";
        data.forEach((emp, idx) => {
            rows += `<tr><td>${idx + 1}</td><td class="text-start">${
                emp.employee_name
            } - ${emp.EmpCode}</td><td>${
                emp.department_code ?? "-"
            }</td><td class="text-end">${emp.claim_count}</td></tr>`;
        });
        $(`#${tableId} tbody`).html(rows);
    }

    function renderClaimTypeWiseTable(data, containerId = "") {
        const container = containerId
            ? document.querySelector(
                  containerId.startsWith("#") || containerId.startsWith(".")
                      ? containerId
                      : `#${containerId}`
              )
            : document.querySelector(".card-body");

        if (!container) {
            console.warn(
                `Container "${containerId || ".card-body"}" not found.`
            );
            return;
        }

        const departments = data.departments || [];
        const claimTypeTotals = data.claimTypeTotals || [];
        const grandTotals = data.grandTotals || [];

        if (!departments.length || !claimTypeTotals.length) {
            console.warn("No departments or claim types available:", {
                departments,
                claimTypeTotals,
            });
            container.innerHTML =
                '<p class="text-muted">No data available.</p>';
            return;
        }

        function escapeHtml(str) {
            if (str == null) return "";
            const div = document.createElement("div");
            div.textContent = str;
            return div.innerHTML;
        }

        function formatNumber(value) {
            if (value == null || isNaN(value) || value === 0) return "-";
            return Number(value).toLocaleString("en-IN", {
                style: "currency",
                currency: "INR",
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            });
        }

        function formatPct(value) {
            if (value == null || isNaN(value)) return "-";
            return Number(value).toFixed(2) + "%";
        }

        const departmentsList = departments.map((d) => d.department_name);
        const colors = [
            "#8884d8",
            "#82ca9d",
            "#ffc658",
            "#ff7c7c",
            "#8dd1e1",
            "#d084d0",
            "#87ceeb",
            "#dda0dd",
            "#a0522d",
            "#ffb347",
            "#3cb371",
            "#20b2aa",
            "#9370db",
            "#4682b4",
            "#ff69b4",
            "#cd5c5c",
            "#40e0d0",
            "#9acd32",
            "#ff6347",
        ];
        const totalColor = "#f0f0f0";

        if (!document.getElementById("claimTypeWiseContainer")) {
            const style = document.createElement("style");
            style.id = "claimTypeWiseContainer";
            style.textContent = `
            #claimTypeWiseContainer .table-responsive {
                overflow-x: auto;
            }
            #claimTypeWiseContainer .table {
                width: 100%;
                border-collapse: collapse;
            }
            #claimTypeWiseContainer th {
                text-align: center;
                vertical-align: middle;
            }
            #claimTypeWiseContainer .claim-name {
                width: 200px;
                text-align: left;
                vertical-align: middle;
            }
            #claimTypeWiseContainer .dept-header {
                background-color: transparent;
            }
            #claimTypeWiseContainer .text-end {
                text-align: right;
            }
            #claimTypeWiseContainer .text-success {
                color: #28a745;
            }
            #claimTypeWiseContainer .text-danger {
                color: #dc3545;
            }
            #claimTypeWiseContainer .fw-bold {
                font-weight: bold;
            }
            ${colors
                .map(
                    (color, i) => `
                #claimTypeWiseContainer .dept-${i} {
                    background-color: ${color}40;
                }
                #claimTypeWiseContainer .dept-cell-${i} {
                    background-color: ${color}15;
                }
            `
                )
                .join("")}
            #claimTypeWiseContainer .total {
                background-color: ${totalColor};
            }
        `;
            document.head.appendChild(style);
        }

        let html = `<div id="claimTypeWiseContainer" class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead>
                            <tr>
                                <th rowspan="2" class="claim-name">Claim Name</th>
                                ${departmentsList
                                    .map(
                                        (d, i) => `
                                    <th colspan="3" class="dept-header dept-${i}">
                                        ${escapeHtml(d)}
                                    </th>
                                  `
                                    )
                                    .join("")}
                                <th colspan="3" class="total">Total</th>
                            </tr>
                            <tr>
                                ${departmentsList
                                    .map(
                                        (_, i) => `
                                    <th class="dept-header dept-${i}">Prev</th>
                                    <th class="dept-header dept-${i}">Curr</th>
                                    <th class="dept-header dept-${i}">Var</th>
                                  `
                                    )
                                    .join("")}
                                <th class="total">Prev</th>
                                <th class="total">Curr</th>
                                <th class="total">Var</th>
                            </tr>
                        </thead>
                        <tbody>`;

        claimTypeTotals.forEach((claim) => {
            html += `<tr>
                    <td class="claim-name"><b>${escapeHtml(
                        claim.ClaimName
                    )}</b></td>
                    ${departmentsList
                        .map((d, i) => {
                            const dept = departments.find(
                                (dep) => dep.department_name === d
                            );
                            const val = (dept?.claims || []).find(
                                (c) => c.ClaimCode === claim.ClaimCode
                            ) || {
                                TotalFinancedTAmt_Y6: 0,
                                TotalFinancedTAmt_Y7: 0,
                                VariationPercentage: null,
                            };
                            return `
                        <td class="text-end dept-cell-${i}">${formatNumber(
                                val.TotalFinancedTAmt_Y6
                            )}</td>
                        <td class="text-end dept-cell-${i}">${formatNumber(
                                val.TotalFinancedTAmt_Y7
                            )}</td>
                        <td class="text-end dept-cell-${i} ${
                                val.VariationPercentage > 0
                                    ? "text-success"
                                    : val.VariationPercentage < 0
                                    ? "text-danger"
                                    : ""
                            }">${formatPct(val.VariationPercentage)}</td>`;
                        })
                        .join("")}
                    <td class="text-end total"><b>${formatNumber(
                        claim.TotalFinancedTAmt_Y6
                    )}</b></td>
                    <td class="text-end total"><b>${formatNumber(
                        claim.TotalFinancedTAmt_Y7
                    )}</b></td>
                    <td class="text-end total ${
                        claim.VariationPercentage > 0
                            ? "text-success"
                            : claim.VariationPercentage < 0
                            ? "text-danger"
                            : ""
                    }"><b>${formatPct(claim.VariationPercentage)}</b></td>
                </tr>`;
        });

        html += `<tr class="fw-bold">
                <td class="claim-name">Grand Total</td>
                ${departmentsList
                    .map((d, i) => {
                        const dept = departments.find(
                            (dep) => dep.department_name === d
                        );
                        const totals = dept?.totals || {
                            TotalFinancedTAmt_Y6: 0,
                            TotalFinancedTAmt_Y7: 0,
                            VariationPercentage: null,
                        };
                        return `
                    <td class="text-end dept-${i}">${formatNumber(
                            totals.TotalFinancedTAmt_Y6
                        )}</td>
                    <td class="text-end dept-${i}">${formatNumber(
                            totals.TotalFinancedTAmt_Y7
                        )}</td>
                    <td class="text-end dept-${i} ${
                            totals.VariationPercentage > 0
                                ? "text-success"
                                : totals.VariationPercentage < 0
                                ? "text-danger"
                                : ""
                        }">${formatPct(totals.VariationPercentage)}</td>`;
                    })
                    .join("")}
                <td class="text-end total"><b>${formatNumber(
                    grandTotals[1].TotalFinancedTAmt_Y6
                )}</b></td>
                <td class="text-end total"><b>${formatNumber(
                    grandTotals[0].TotalFinancedTAmt_Y7
                )}</b></td>
                <td class="text-end total ${
                    grandTotals[2].VariationPercentage > 0
                        ? "text-success"
                        : grandTotals[2].VariationPercentage < 0
                        ? "text-danger"
                        : ""
                }"><b>${formatPct(grandTotals[2].VariationPercentage)}</b></td>
            </tr>`;

        html += `</tbody></table></div>`;
        container.innerHTML = html;
    }

    function renderDepartmentMonthlyChart(departmentMonthlyTotals) {
        const months = [
            ...new Set(departmentMonthlyTotals.map((d) => d.MonthName)),
        ];
        const departmentMap = {};
        departmentMonthlyTotals.forEach((d) => {
            if (!departmentMap[d.department_name]) {
                departmentMap[d.department_name] = {};
            }
            departmentMap[d.department_name][d.MonthName] = parseFloat(
                d.FinancedTotal
            );
        });

        const datasets = Object.keys(departmentMap).map((dept, i) => {
            const data = months.map((m) => departmentMap[dept][m] || 0);
            return {
                label: dept,
                data: data,
                borderColor: [
                    "#8884d8",
                    "#82ca9d",
                    "#ffc658",
                    "#ff7c7c",
                    "#8dd1e1",
                    "#d084d0",
                    "#87ceeb",
                    "#dda0dd",
                    "#a0522d",
                    "#ffb347",
                    "#3cb371",
                    "#20b2aa",
                    "#9370db",
                    "#4682b4",
                    "#ff69b4",
                    "#cd5c5c",
                    "#40e0d0",
                    "#9acd32",
                    "#ff6347",
                ][i % 20],
                backgroundColor: [
                    "#8884d8",
                    "#82ca9d",
                    "#ffc658",
                    "#ff7c7c",
                    "#8dd1e1",
                    "#d084d0",
                    "#87ceeb",
                    "#dda0dd",
                    "#a0522d",
                    "#ffb347",
                    "#3cb371",
                    "#20b2aa",
                    "#9370db",
                    "#4682b4",
                    "#ff69b4",
                    "#cd5c5c",
                    "#40e0d0",
                    "#9acd32",
                    "#ff6347",
                ][i % 20],
                fill: false,
            };
        });

        const ctx = document.getElementById("lineChart").getContext("2d");
        if (window.lineChartInstance) {
            window.lineChartInstance.destroy();
        }

        window.lineChartInstance = new Chart(ctx, {
            type: "line",
            data: {
                labels: months,
                datasets: datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top",
                    },
                    tooltip: {
                        mode: "index",
                        intersect: false,
                    },
                },
                interaction: {
                    mode: "nearest",
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: "Financed Total",
                        },
                    },
                    x: {
                        title: {
                            display: true,
                            text: "Month",
                        },
                    },
                },
            },
        });
    }
});
