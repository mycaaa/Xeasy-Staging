$(document).ready(function () {
    const financialQuarters = [
        {
            label: "Q1 (Apr - Jun)",
            startMonth: 3,
            startDay: 1,
            endMonth: 5,
            endDay: 30,
        },
        {
            label: "Q2 (Jul - Sep)",
            startMonth: 6,
            startDay: 1,
            endMonth: 8,
            endDay: 30,
        },
        {
            label: "Q3 (Oct - Dec)",
            startMonth: 9,
            startDay: 1,
            endMonth: 11,
            endDay: 31,
        },
        {
            label: "Q4 (Jan - Mar)",
            startMonth: 0,
            startDay: 1,
            endMonth: 2,
            endDay: 31,
        },
    ];

    const today = new Date();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();
    const dateInput = document.getElementById("dateRange");
    let currentAllowedRange = null;
    let claimTypeChart;
    let expenseMonthlyChart;
    let departmentChart;

    const datePicker = flatpickr(dateInput, {
        mode: "range",
        dateFormat: "d M, Y",
        maxDate: new Date(today.setHours(23, 59, 59, 999)),
        onClose: function (selectedDates) {
            if (selectedDates.length === 2 && currentAllowedRange) {
                const [start, end] = selectedDates;

                if (
                    start < currentAllowedRange[0] ||
                    end > currentAllowedRange[1]
                ) {
                    alert(
                        "Selected dates must be within the allowed quarter or month range."
                    );
                    datePicker.clear();
                } else {
                    const billDateFrom = formatDateForAPI(start);
                    const billDateTo = formatDateForAPI(end);
                    fetchDashboardData(billDateFrom, billDateTo);
                }
            }
        },
    });

    const container = document.getElementById("quarter-buttons");
    const datePickerDiv = document.getElementById("date-picker-wrapper");

    const monthOptions = [
        { label: "April", month: 3 },
        { label: "May", month: 4 },
        { label: "June", month: 5 },
        { label: "July", month: 6 },
        { label: "August", month: 7 },
        { label: "September", month: 8 },
        { label: "October", month: 9 },
        { label: "November", month: 10 },
        { label: "December", month: 11 },
        { label: "January", month: 0 },
        { label: "February", month: 1 },
        { label: "March", month: 2 },
    ];

    function getDateRange(startMonth, startDay, endMonth, endDay, year) {
        const crossYear = startMonth > endMonth;
        const startYear = year;
        const endYear = crossYear ? year + 1 : year;

        const start = new Date(startYear, startMonth, startDay);
        const end = new Date(endYear, endMonth, endDay);

        if (end.getDate() !== endDay) {
            end.setDate(0);
        }

        return [start, end];
    }

    function formatDateForAPI(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    }

    function createButton(
        label,
        className,
        dateRange,
        isDefault = false,
        isHidden = false
    ) {
        const div = document.createElement("div");
        div.className = "col-auto";
        div.innerHTML = `<button type="button" class="btn ${className} material-shadow-none btn-sm">${label}</button>`;
        const button = div.querySelector("button");

        if (isHidden) {
            div.style.display = "none";
        }

        button.addEventListener("click", () => {
            document
                .querySelectorAll("#quarter-buttons button")
                .forEach((btn) => {
                    btn.classList.remove("btn-primary", "text-white");
                    btn.classList.add("btn-soft-secondary");
                });

            button.classList.remove("btn-soft-secondary");
            button.classList.add("btn-primary", "text-white");

            currentAllowedRange = dateRange.length ? dateRange : null;

            if (currentAllowedRange) {
                const maxEnd = new Date(today.setHours(23, 59, 59, 999));
                const rangeEnd =
                    currentAllowedRange[1] > maxEnd
                        ? maxEnd
                        : currentAllowedRange[1];

                datePicker.set("minDate", currentAllowedRange[0]);
                datePicker.set("maxDate", maxEnd);
                datePicker.setDate([currentAllowedRange[0], rangeEnd], true);

                const billDateFrom = formatDateForAPI(currentAllowedRange[0]);
                const billDateTo = formatDateForAPI(rangeEnd);
                fetchDashboardData(billDateFrom, billDateTo);
            } else {
                datePicker.set("minDate", null);
                datePicker.set(
                    "maxDate",
                    new Date(today.setHours(23, 59, 59, 999))
                );
                datePicker.clear();

                const fyStart = new Date(currentYear, 3, 1);
                const fyEnd = new Date(currentYear + 1, 2, 31);

                const billDateFrom = formatDateForAPI(fyStart);
                const billDateTo = formatDateForAPI(
                    fyEnd > today
                        ? new Date(today.setHours(23, 59, 59, 999))
                        : fyEnd
                );
                fetchDashboardData(billDateFrom, billDateTo);
            }

            const monthDropdown = document.getElementById("month-dropdown");
            if (monthDropdown) {
                monthDropdown.style.display = "none";
            }
        });

        container.insertBefore(div, datePickerDiv);

        if (isDefault) button.click();
    }

    function createMonthToggleWithDropdown() {
        const div = document.createElement("div");
        div.className = "col-auto d-flex align-items-center gap-2";

        div.innerHTML = `
        <button id="month-toggle-button" type="button" class="btn btn-primary text-white material-shadow-none btn-sm">Month</button>
        <select id="month-dropdown" class="form-select form-select-sm" style="width: 140px;">
            ${monthOptions
                .map(
                    (month) =>
                        `<option value="${month.month}" ${
                            month.month === currentMonth ? "selected" : ""
                        }>${month.label}</option>`
                )
                .join("")}
        </select>`;

        const button = div.querySelector("#month-toggle-button");
        const dropdown = div.querySelector("#month-dropdown");

        function setMonthRange(month) {
            const year = month >= 3 ? currentYear : currentYear + 1;
            const startDate = new Date(year, month, 1);
            const endDate = new Date(year, month + 1, 0);
            const maxEnd = new Date(today.setHours(23, 59, 59, 999));
            const rangeEnd = endDate > maxEnd ? maxEnd : endDate;

            currentAllowedRange = [startDate, rangeEnd];
            datePicker.set("minDate", startDate);
            datePicker.set("maxDate", maxEnd);
            datePicker.setDate([startDate, rangeEnd], true);

            const billDateFrom = formatDateForAPI(startDate);
            const billDateTo = formatDateForAPI(rangeEnd);
            fetchDashboardData(billDateFrom, billDateTo);
        }

        setMonthRange(parseInt(dropdown.value));

        button.addEventListener("click", () => {
            document
                .querySelectorAll("#quarter-buttons button")
                .forEach((btn) => {
                    btn.classList.remove("btn-primary", "text-white");
                    btn.classList.add("btn-soft-secondary");
                });

            button.classList.remove("btn-soft-secondary");
            button.classList.add("btn-primary", "text-white");

            dropdown.style.display = "block";

            setMonthRange(parseInt(dropdown.value));
        });

        dropdown.addEventListener("change", (e) => {
            setMonthRange(parseInt(e.target.value));
        });

        container.insertBefore(div, datePickerDiv);
    }

    const fyStart = new Date(currentYear, 3, 1);
    const fyEnd = new Date(currentYear + 1, 2, 31);
    createButton("FY Year", "btn-soft-secondary", [fyStart, fyEnd]);

    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    createButton(
        "Current Month",
        "btn-soft-secondary",
        [firstDay, lastDay],
        true,
        true
    );

    let currentQuarterIndex = -1;
    financialQuarters.forEach((q, index) => {
        if (q.startMonth <= q.endMonth) {
            if (currentMonth >= q.startMonth && currentMonth <= q.endMonth) {
                currentQuarterIndex = index;
            }
        } else {
            if (currentMonth >= q.startMonth || currentMonth <= q.endMonth) {
                currentQuarterIndex = index;
            }
        }
    });

    financialQuarters.forEach((q, index) => {
        const year = q.startMonth > q.endMonth ? currentYear - 1 : currentYear;
        const range = getDateRange(
            q.startMonth,
            q.startDay,
            q.endMonth,
            q.endDay,
            year
        );
        const adjustedRange = [
            range[0],
            range[1] > today
                ? new Date(today.setHours(23, 59, 59, 999))
                : range[1],
        ];

        if (index <= currentQuarterIndex) {
            createButton(q.label, "btn-soft-secondary", adjustedRange);
        }
    });

    const monthDropdown = document.getElementById("month-dropdown");
    if (monthDropdown) {
        monthDropdown.style.display = "none";

        const monthToggle = document.getElementById("month-toggle-button");
        if (monthToggle) {
            monthToggle.classList.add("btn-soft-secondary");
            monthToggle.classList.remove("btn-primary", "text-white");
        }
    }

    createMonthToggleWithDropdown();

    function formatCurrency(value) {
        const numValue = Number(value || 0);
        return numValue === 0
            ? "₹0"
            : numValue.toLocaleString("en-IN", {
                  style: "currency",
                  currency: "INR",
                  minimumFractionDigits: 0,
                  maximumFractionDigits: 0,
              });
    }

    function getChartColorsArray(e) {
        if ($("#" + e).length) {
            var theme =
                document.documentElement.getAttribute("data-theme") || "";
            var attr = "data-colors" + (theme ? "-" + theme : "");
            var colors =
                $("#" + e).attr(attr) || $("#" + e).attr("data-colors");
            if (colors) {
                return JSON.parse(colors).map(function (color) {
                    color = color.replace(" ", "");
                    return color.indexOf(",") === -1
                        ? getComputedStyle(
                              document.documentElement
                          ).getPropertyValue(color) || color
                        : "rgba(" +
                              getComputedStyle(
                                  document.documentElement
                              ).getPropertyValue(color.split(",")[0]) +
                              "," +
                              color.split(",")[1] +
                              ")";
                });
            }
            return ["#5156be", "#ffbf53", "#2ab57d", "#fd625e"];
        }
    }

    function fetchDashboardData(billDateFrom, billDateTo) {
        $.ajax({
            url: "home-data",
            type: "GET",
            data: {
                bill_date_from: billDateFrom,
                bill_date_to: billDateTo,
            },
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            beforeSend: function () {
                startPageLoader();
            },
            success: function (data) {
                if (data.error) {
                    console.error(data.error);
                    return;
                }

                const cardData = data.cardData || {};
                const monthlyTotals = data.monthlyTotals || [];
                const departmentTotals = data.departmentTotals || [];
                const totalAllMonths = data.totalAllMonths || {};
                const yearId = data.yearId;
                const previousYearId = yearId - 1;
                const claimTypeTotals = data.claimTypeTotals || [];
                const yearlyComparison = data.yearlyComparison;
                const topTravelersByWType = data.topTravelersByWType;
                const topEmployees = data.topEmployees || [];
                tableUpdateYearlyComparison(yearlyComparison);
                tableUpdateCards(cardData);
                tableUpdateMonthlyTotals(monthlyTotals);
                tableUpdateDepartmentTotals(
                    departmentTotals,
                    yearId,
                    previousYearId
                );
                tableUpdateClaimTypeTotals(claimTypeTotals);
                tableTopEmployee(topEmployees);
                chartRenderMonthlyExpense(monthlyTotals);
                chartRenderDepartmentComparison(
                    departmentTotals,
                    yearId,
                    previousYearId
                );
                chartRenderClaimType(claimTypeTotals);
            },
            complete: function () {
                endPageLoader();
            },
            error: function (xhr, status, error) {
                console.error("Error fetching dashboard data:", error);
            },
        });
    }

    function tableUpdateYearlyComparison(yearlyComparison) {
        const variance = parseFloat(yearlyComparison.Variance_Percentage);
        $("#cyExpense").text(formatCurrency(yearlyComparison.CY_Expense));
        $("#pyExpense").text(formatCurrency(yearlyComparison.PY_Expense));
        let cssClass = "text-muted";

        if (variance > 0) {
            cssClass = "text-success";
        } else if (variance < 0) {
            cssClass = "text-danger";
        } else {
            cssClass = "text-secondary";
        }

        $("#variancePercent")
            .removeClass("text-success text-danger text-secondary text-muted")
            .addClass(cssClass)
            .html(`${variance.toFixed(2)}%`);
    }

    function tableUpdateCards(cardData) {
        const defaultCardData = {
            "Total Expense": 0,
            Deactivate: 0,
            Draft: 0,
            Submitted: 0,
            Filled: 0,
            Verified: 0,
            Approved: 0,
            Financed: 0,
        };
        const mergedCardData = {
            ...defaultCardData,
            ...cardData,
        };
        const cards = [
            {
                label: "Total Expense",
                icon: "ri-wallet-3-fill",
                color: "text-primary",
                value: mergedCardData["Total Expense"],
            },
            {
                label: "Deactivate",
                icon: "ri-archive-line",
                color: "text-secondary",
                value: mergedCardData["Deactivate"],
            },
            {
                label: "Draft",
                icon: "ri-time-line",
                color: "text-warning",
                value: mergedCardData["Draft"],
            },
            {
                label: "Submitted",
                icon: "ri-send-plane-fill",
                color: "text-dark",
                value: mergedCardData["Submitted"],
            },
            {
                label: "Filled",
                icon: "ri-file-list-3-line",
                color: "text-info",
                value: mergedCardData["Filled"],
            },
            {
                label: "Verified",
                icon: "ri-eye-fill",
                color: "text-success",
                value: mergedCardData["Verified"],
            },
            {
                label: "Approved",
                icon: "ri-check-double-fill",
                color: "text-success",
                value: mergedCardData["Approved"],
            },
            {
                label: "Financed",
                icon: "ri-hand-coin-fill",
                color: "text-primary",
                value: mergedCardData["Financed"],
            },
        ];

        const $cardContainer = $("#card-container");
        $cardContainer.html(
            cards
                .map(
                    (card) => `
            <div class="col-lg-3 col-md-6">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-light ${
                                    card.color
                                } rounded-circle fs-3 material-shadow">
                                    <i class="${card.icon} align-middle"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">${
                                    card.label
                                }</p>
                                <h4 class="mb-0"><span class="counter-value" data-target="${
                                    card.value
                                }">${formatCurrency(card.value)}</span></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `
                )
                .join("")
        );
    }

    function tableUpdateMonthlyTotals(monthlyTotals) {
        const $modalTableBody = $("#modal-table-body");
        const $modalTableFooter = $("#modal-table-footer");
        $modalTableBody.html(
            monthlyTotals.length
                ? monthlyTotals
                      .map(
                          (item) => `
                <tr>
                    <td>${item.MonthName || "-"}</td>
                    <td>${formatCurrency(item.FilledTotal)}</td>
                    <td>${formatCurrency(item.VerifiedTotal)}</td>
                    <td>${formatCurrency(item.ApprovedTotal)}</td>
                    <td>${formatCurrency(item.FinancedTotal)}</td>
                </tr>
            `
                      )
                      .join("")
                : `<tr><td colspan="5" class="text-center">No data available</td></tr>`
        );
        $modalTableFooter.html(`
        <tr>
            <td><strong>Total</strong></td>
            <td><strong>${formatCurrency(
                monthlyTotals.reduce(
                    (sum, item) => sum + parseFloat(item.FilledTotal || 0),
                    0
                )
            )}</strong></td>
            <td><strong>${formatCurrency(
                monthlyTotals.reduce(
                    (sum, item) => sum + parseFloat(item.VerifiedTotal || 0),
                    0
                )
            )}</strong></td>
            <td><strong>${formatCurrency(
                monthlyTotals.reduce(
                    (sum, item) => sum + parseFloat(item.ApprovedTotal || 0),
                    0
                )
            )}</strong></td>
            <td><strong>${formatCurrency(
                monthlyTotals.reduce(
                    (sum, item) => sum + parseFloat(item.FinancedTotal || 0),
                    0
                )
            )}</strong></td>
        </tr>
    `);
    }

    function tableUpdateDepartmentTotals(
        departmentTotals,
        yearId,
        previousYearId
    ) {
        const $departmentTableBody = $("#department-table-body");
        const $departmentTableFooter = $("#department-table-footer");

        if (departmentTotals.length) {
            $departmentTableBody.html(
                departmentTotals
                    .map(
                        (dept) => `
                <tr${
                    dept.department_name === null
                        ? ' style="background-color: #f8d7da;text-transform: capitalize;"'
                        : ""
                }>
                    <td style="text-align:left">${
                        dept.department_name || "Unknown"
                    } (${dept.department_code})</td>
                    <td>${formatCurrency(
                        dept["TotalFinancedTAmt_Y" + previousYearId]
                    )}</td>
                    <td>${formatCurrency(
                        dept["TotalFinancedTAmt_Y" + yearId]
                    )}</td>
                    <td>${
                        dept.VariationPercentage !== null
                            ? dept.VariationPercentage + "%"
                            : "-"
                    }</td>
                </tr>
            `
                    )
                    .join("")
            );

            const totalY7 = departmentTotals.reduce(
                (sum, dept) =>
                    sum + parseFloat(dept["TotalFinancedTAmt_Y" + yearId] || 0),
                0
            );
            const totalY6 = departmentTotals.reduce(
                (sum, dept) =>
                    sum +
                    parseFloat(
                        dept["TotalFinancedTAmt_Y" + previousYearId] || 0
                    ),
                0
            );
            const variation =
                totalY6 > 0
                    ? (((totalY7 - totalY6) / totalY6) * 100).toFixed(2)
                    : "-";

            $departmentTableFooter.html(`
            <tr>
                <td style="text-align:left"><strong>Total</strong></td>
                <td><strong>${formatCurrency(totalY6)}</strong></td>
                <td><strong>${formatCurrency(totalY7)}</strong></td>
                <td><strong>${
                    variation !== "-" ? variation + "%" : "-"
                }</strong></td>
            </tr>
        `);
        } else {
            $departmentTableBody.html(
                `<tr><td colspan="4" class="text-center">No data available</td></tr>`
            );
        }
    }

    function tableTopEmployee(topEmployees) {
        const $employeeTableBody = $("#employee-table-body");

        if (topEmployees.length) {
            $employeeTableBody.html(
                topEmployees
                    .map(
                        (emp, index) => `
                            <tr class="employee-row" data-empid="${emp.CrBy}">
                                <td class="toggle-icon text-center"><i class="ri-add-circle-fill"></i></td>
                                <td>${index + 1}</td>
                                <td style="text-align:left">${
                                    emp.employee_name
                                } - ${emp.EmpCode}</td>
                                <td style="text-align:left">${
                                    emp.department_name || "Unknown"
                                }</td>
                                <td class="text-center">${formatCurrency(
                                    emp.filled_total_amount
                                )}</td>
                                <td class="text-center">${formatCurrency(
                                    emp.payment_total_amount
                                )}</td>
                            </tr>
                            <tr class="employee-detail-row" id="detail-${
                                emp.CrBy
                            }" style="display:none;">
                                <td colspan="6" style="background-color: #f3f6f9;">
                                    <canvas id="chart-${
                                        emp.CrBy
                                    }" style="width:100%; height:200px;"></canvas>
                                    <div class="employee-detail-content"></div>
                                </td>
                            </tr>
                        `
                    )

                    .join("")
            );
        } else {
            $employeeTableBody.html(
                `<tr><td colspan="6" class="text-center">No data available</td></tr>`
            );
        }
    }

    function tableUpdateClaimTypeTotals(claimTypeTotals) {
        const $claimTypeTableBody = $("#claim-type-table-body");
        const $claimTypeTableFooter = $("#claim-type-table-footer");

        if (claimTypeTotals.length) {
            $claimTypeTableBody.html(
                claimTypeTotals
                    .map(
                        (item) => `
                <tr>
                    <td style="text-align:left">${
                        item.ClaimName || item.ClaimCode
                    }</td>
                    <td>${formatCurrency(item.FilledTotal)}</td>
                    <td>${formatCurrency(item.VerifiedTotal)}</td>
                    <td>${formatCurrency(item.ApprovedTotal)}</td>
                    <td>${formatCurrency(item.FinancedTotal)}</td>
                </tr>
            `
                    )
                    .join("")
            );

            const filledTotal = claimTypeTotals.reduce(
                (sum, c) => sum + parseFloat(c.FilledTotal || 0),
                0
            );
            const verifiedTotal = claimTypeTotals.reduce(
                (sum, c) => sum + parseFloat(c.VerifiedTotal || 0),
                0
            );
            const approvedTotal = claimTypeTotals.reduce(
                (sum, c) => sum + parseFloat(c.ApprovedTotal || 0),
                0
            );
            const financedTotal = claimTypeTotals.reduce(
                (sum, c) => sum + parseFloat(c.FinancedTotal || 0),
                0
            );

            $claimTypeTableFooter.html(`
            <tr>
                <td style="text-align:left"><strong>Total</strong></td>
                <td><strong>${formatCurrency(filledTotal)}</strong></td>
                <td><strong>${formatCurrency(verifiedTotal)}</strong></td>
                <td><strong>${formatCurrency(approvedTotal)}</strong></td>
                <td><strong>${formatCurrency(financedTotal)}</strong></td>
            </tr>
        `);
        } else {
            $claimTypeTableBody.html(
                `<tr><td colspan="5" class="text-center">No data available</td></tr>`
            );
        }
    }

    function chartRenderMonthlyExpense(monthlyTotals) {
        if (
            expenseMonthlyChart &&
            typeof expenseMonthlyChart.destroy === "function"
        ) {
            expenseMonthlyChart.destroy();
        }
        var expenseChartColors = getChartColorsArray("expense-monthly-chart");
        var options = {
            series: [
                {
                    name: "Filled Total",
                    type: "bar",
                    data: monthlyTotals.map((item) => item.FilledTotal || 0),
                },
                {
                    name: "Verified Total",
                    type: "bar",
                    data: monthlyTotals.map((item) => item.VerifiedTotal || 0),
                },
                {
                    name: "Approved Total",
                    type: "bar",
                    data: monthlyTotals.map((item) => item.ApprovedTotal || 0),
                },
                {
                    name: "Financed Total",
                    type: "area",
                    data: monthlyTotals.map((item) => item.FinancedTotal || 0),
                },
            ],
            chart: {
                height: 374,
                type: "line",
                toolbar: {
                    show: false,
                },
            },
            stroke: {
                curve: "smooth",
                dashArray: [0, 0, 0, 3],
                width: [0, 0, 0, 1],
            },
            fill: {
                opacity: [1, 1, 1, 0.1],
            },
            markers: {
                size: [0, 0, 0, 4],
                strokeWidth: 2,
                hover: {
                    size: 4,
                },
            },
            xaxis: {
                categories: monthlyTotals.map((item) => item.MonthName || ""),
                axisTicks: {
                    show: false,
                },
                axisBorder: {
                    show: false,
                },
            },
            grid: {
                show: true,
                xaxis: {
                    lines: {
                        show: true,
                    },
                },
                yaxis: {
                    lines: {
                        show: false,
                    },
                },
                padding: {
                    top: 0,
                    right: -2,
                    bottom: 15,
                    left: 10,
                },
            },
            legend: {
                show: true,
                horizontalAlign: "center",
                offsetX: 0,
                offsetY: -5,
                markers: {
                    width: 9,
                    height: 9,
                    radius: 6,
                },
                itemMargin: {
                    horizontal: 10,
                    vertical: 0,
                },
            },
            plotOptions: {
                bar: {
                    columnWidth: "30%",
                    barHeight: "70%",
                },
            },
            colors: expenseChartColors,
            tooltip: {
                shared: true,
                y: [
                    {
                        formatter: (val) =>
                            val !== undefined ? formatCurrency(val) : val,
                    },
                    {
                        formatter: (val) =>
                            val !== undefined ? formatCurrency(val) : val,
                    },
                    {
                        formatter: (val) =>
                            val !== undefined ? formatCurrency(val) : val,
                    },
                    {
                        formatter: (val) =>
                            val !== undefined ? formatCurrency(val) : val,
                    },
                ],
            },
        };
        expenseMonthlyChart = new ApexCharts(
            document.querySelector("#expense-monthly-chart"),
            options
        );
        expenseMonthlyChart.render();
    }

    function chartRenderDepartmentComparison(
        departmentTotals,
        yearId,
        previousYearId
    ) {
        if (departmentChart && typeof departmentChart.destroy === "function") {
            departmentChart.destroy();
        }
        var chartMultiColors = getChartColorsArray("multi_chart");
        var multiOptions = {
            series: [
                {
                    name: "Current Year",
                    type: "column",
                    data: departmentTotals.map(
                        (item) => item["TotalFinancedTAmt_Y" + yearId] || 0
                    ),
                },
                {
                    name: "Previous Year",
                    type: "column",
                    data: departmentTotals.map(
                        (item) =>
                            item["TotalFinancedTAmt_Y" + previousYearId] || 0
                    ),
                },
                {
                    name: "Variation (%)",
                    type: "line",
                    data: departmentTotals.map((item) => {
                        var current = item["TotalFinancedTAmt_Y" + yearId] || 0;
                        var previous =
                            item["TotalFinancedTAmt_Y" + previousYearId] || 0;
                        return previous
                            ? (((current - previous) / previous) * 100).toFixed(
                                  2
                              )
                            : 0;
                    }),
                },
            ],
            chart: {
                height: 350,
                type: "line",
                stacked: false,
                toolbar: {
                    show: false,
                },
            },
            dataLabels: {
                enabled: false,
            },
            stroke: {
                width: [1, 1, 4],
            },
            xaxis: {
                categories: departmentTotals.map(
                    (item) => item.department_code || "-"
                ),
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    trim: false,
                    style: {
                        fontSize: "11px",
                    },
                },
                tooltip: {
                    enabled: true,
                },
            },
            yaxis: [
                {
                    axisTicks: {
                        show: true,
                    },
                    axisBorder: {
                        show: true,
                        color: chartMultiColors[0],
                    },
                    labels: {
                        style: {
                            colors: chartMultiColors[0],
                        },
                    },
                    title: {
                        text: "Current Year Expenses",
                        style: {
                            color: chartMultiColors[0],
                            fontWeight: 600,
                        },
                    },
                    tooltip: {
                        enabled: true,
                    },
                },
                {
                    seriesName: "Previous Year",
                    opposite: true,
                    axisTicks: {
                        show: true,
                    },
                    axisBorder: {
                        show: true,
                        color: chartMultiColors[1],
                    },
                    labels: {
                        style: {
                            colors: chartMultiColors[1],
                        },
                    },
                    title: {
                        text: "Previous Year Expenses",
                        style: {
                            color: chartMultiColors[1],
                            fontWeight: 600,
                        },
                    },
                },
                {
                    seriesName: "Variation",
                    opposite: true,
                    axisTicks: {
                        show: true,
                    },
                    axisBorder: {
                        show: true,
                        color: chartMultiColors[2],
                    },
                    labels: {
                        style: {
                            colors: chartMultiColors[2],
                        },
                    },
                    title: {
                        text: "Variation (%)",
                        style: {
                            color: chartMultiColors[2],
                            fontWeight: 600,
                        },
                    },
                    formatter: (val) => formatCurrency(val.toFixed(2)) + "%",
                },
            ],
            tooltip: {
                fixed: {
                    enabled: true,
                    position: "topLeft",
                    offsetY: 30,
                    offsetX: 60,
                },
                y: [
                    {
                        formatter: (val) =>
                            val !== undefined ? formatCurrency(val) : val,
                    },
                    {
                        formatter: (val) =>
                            val !== undefined ? formatCurrency(val) : val,
                    },
                    {
                        formatter: (val) =>
                            val !== undefined
                                ? formatCurrency(val.toFixed(2)) + "%"
                                : val,
                    },
                ],
            },
            legend: {
                horizontalAlign: "center",
                position: "bottom",
                fontSize: "14px",
                fontWeight: 500,
                markers: {
                    width: 12,
                    height: 12,
                    radius: 12,
                },
            },
            colors: chartMultiColors,
        };
        departmentChart = new ApexCharts(
            document.querySelector("#multi_chart"),
            multiOptions
        );
        departmentChart.render();
    }

    function chartRenderClaimType(claimTypeTotals) {
        if (claimTypeChart && typeof claimTypeChart.destroy === "function") {
            claimTypeChart.destroy();
        }
        var claimTypeColors = getChartColorsArray("claim-type-chart");
        var claimTypeOptions = {
            series: [
                {
                    name: "Filled",
                    type: "area",
                    data: claimTypeTotals.map((item) =>
                        parseFloat(item.FilledTotal || 0)
                    ),
                },
                {
                    name: "Verified",
                    type: "bar",
                    data: claimTypeTotals.map((item) =>
                        parseFloat(item.VerifiedTotal || 0)
                    ),
                },
                {
                    name: "Approved",
                    type: "line",
                    data: claimTypeTotals.map((item) =>
                        parseFloat(item.ApprovedTotal || 0)
                    ),
                },
                {
                    name: "Financed",
                    type: "line",
                    data: claimTypeTotals.map((item) =>
                        parseFloat(item.FinancedTotal || 0)
                    ),
                },
            ],
            chart: {
                height: 370,
                type: "line",
                toolbar: {
                    show: false,
                },
            },
            stroke: {
                curve: "smooth",
                width: [2, 0, 2.2, 2.2],
                dashArray: [0, 0, 5, 3],
            },
            fill: {
                opacity: [0.2, 1, 1, 1],
            },
            markers: {
                size: [4, 0, 5, 5],
                hover: {
                    size: 6,
                },
            },
            xaxis: {
                categories: claimTypeTotals.map((item) => item.ClaimCode),
                axisTicks: {
                    show: false,
                },
                axisBorder: {
                    show: false,
                },
                labels: {
                    rotate: -45,
                    style: {
                        fontSize: "12px",
                    },
                },
            },
            grid: {
                show: true,
                xaxis: {
                    lines: {
                        show: true,
                    },
                },
                yaxis: {
                    lines: {
                        show: true,
                    },
                },
                padding: {
                    top: 0,
                    right: -2,
                    bottom: 15,
                    left: 10,
                },
            },
            legend: {
                show: true,
                horizontalAlign: "center",
                offsetX: 0,
                offsetY: -5,
                markers: {
                    width: 9,
                    height: 9,
                    radius: 6,
                },
                itemMargin: {
                    horizontal: 10,
                    vertical: 0,
                },
            },
            colors: claimTypeColors,
            plotOptions: {
                bar: {
                    columnWidth: "30%",
                },
            },
            tooltip: {
                shared: true,
                y: {
                    formatter: (val) =>
                        val != null ? formatCurrency(val) : val,
                },
            },
        };
        claimTypeChart = new ApexCharts(
            document.querySelector("#claim-type-chart"),
            claimTypeOptions
        );
        claimTypeChart.render();
    }

    function randomColor() {
        const r = Math.floor(Math.random() * 200);
        const g = Math.floor(Math.random() * 200);
        const b = Math.floor(Math.random() * 200);
        return `rgba(${r},${g},${b},0.7)`;
    }

    $(document).on("click", ".employee-row", function () {
        const $row = $(this);
        const empId = $row.data("empid");
        const $detailRow = $("#detail-" + empId);
        const $content = $detailRow.find(".employee-detail-content");
        const $icon = $row.find(".toggle-icon i");

        $(".employee-detail-row").not($detailRow).hide();
        $(".employee-row")
            .not($row)
            .removeClass("active-row")
            .find(".toggle-icon i")
            .removeClass("fa-minus-circle")
            .addClass("fa-plus-circle");

        if ($detailRow.is(":visible")) {
            $detailRow.hide();
            $row.removeClass("active-row");
            $icon.removeClass("fa-minus-circle").addClass("fa-plus-circle");
            return;
        }

        $row.addClass("active-row");
        $icon.removeClass("fa-plus-circle").addClass("fa-minus-circle");

        $content.html("<p>Loading...</p>");
        $detailRow.show();

        const selectedDates = datePicker.selectedDates;
        if (!selectedDates || selectedDates.length < 2) {
            alert("Please select a valid date range first.");
            return;
        }

        const billDateFrom = formatDateForAPI(selectedDates[0]);
        const billDateTo = formatDateForAPI(selectedDates[1]);

        $.ajax({
            url: "/get-employee-trend",
            method: "POST",
            data: {
                bill_date_from: billDateFrom,
                bill_date_to: billDateTo,
                employee_id: empId,
            },
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (res) {
                const data = Array.isArray(res) ? res : res.data;

                if (data && data.length) {
                    const months = [
                        "Jan",
                        "Feb",
                        "Mar",
                        "Apr",
                        "May",
                        "Jun",
                        "Jul",
                        "Aug",
                        "Sep",
                        "Oct",
                        "Nov",
                        "Dec",
                    ];
                    const activeMonths = months.filter((month) =>
                        data.some((row) => parseFloat(row[month]) > 0)
                    );

                    let html = `<table class="table table-bordered">
                    <thead>
                        <tr style="font-weight:bold; background:#f1f1f1;">
                            <th style="text-align:left;">Claim Type</th>`;
                    activeMonths.forEach(
                        (month) => (html += `<th>${month}</th>`)
                    );
                    html += `<th>Total</th></tr></thead><tbody>`;

                    const monthlySums = {};
                    activeMonths.forEach((month) => (monthlySums[month] = 0));
                    let grandTotal = 0;

                    data.forEach((row) => {
                        html += `<tr><td style="text-align:left;">${row.ClaimName}</td>`;
                        activeMonths.forEach((month) => {
                            const val = parseFloat(row[month]) || 0;
                            html += `<td>${formatCurrency(val)}</td>`;
                            monthlySums[month] += val;
                        });
                        const rowTotal = parseFloat(row.total_year) || 0;
                        html += `<td><b>${formatCurrency(
                            rowTotal
                        )}</b></td></tr>`;
                        grandTotal += rowTotal;
                    });

                    html += `<tr style="font-weight:bold; background:#f1f1f1;">
                    <td style="text-align:left;">Grand Total</td>`;
                    activeMonths.forEach((month) => {
                        html += `<td>${formatCurrency(
                            monthlySums[month]
                        )}</td>`;
                    });
                    html += `<td>${formatCurrency(grandTotal)}</td></tr>`;
                    html += "</tbody></table>";

                    $content.html(html);

                    const ctx = document
                        .getElementById(`chart-${empId}`)
                        .getContext("2d");
                    new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: activeMonths,
                            datasets: data.map((row) => ({
                                label: row.ClaimName,
                                data: activeMonths.map(
                                    (m) => parseFloat(row[m]) || 0
                                ),
                                backgroundColor: randomColor(),
                            })),
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: "bottom",
                                },
                                title: {
                                    display: true,
                                    text: "Monthly Claim Trend",
                                },
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                },
                            },
                        },
                    });
                } else {
                    $content.html("<p>No claim data found.</p>");
                }
            },
            error: function () {
                $content.html("<p class='text-danger'>Error loading data</p>");
            },
        });
    });

    $(document).on("click", "#exportExpenseMonthExcelBtn", function () {
        const button = this;
        const selectedDates = datePicker.selectedDates;

        if (!selectedDates || selectedDates.length < 2) {
            alert("Please select a valid date range.");
            return;
        }

        const billDateFrom = formatDateForAPI(selectedDates[0]);
        const billDateTo = formatDateForAPI(selectedDates[1]);

        $.ajax({
            url: "/export/expense-month-wise",
            method: "POST",
            data: JSON.stringify({
                bill_date_from: billDateFrom,
                bill_date_to: billDateTo,
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
                if (
                    xhr
                        .getResponseHeader("content-type")
                        .includes("application/json")
                ) {
                    data.text().then((text) => {
                        const response = JSON.parse(text);
                        alert(response.error || "Export failed.");
                    });
                    return;
                }

                const startDate = billDateFrom.replace(/-/g, "");
                const endDate = billDateTo.replace(/-/g, "");
                const url = window.URL.createObjectURL(data);
                const a = $("<a>", {
                    href: url,
                    download: `expense_month_wise_report_${startDate}_to_${endDate}.xlsx`,
                }).appendTo("body");
                a[0].click();
                a.remove();
                window.URL.revokeObjectURL(url);
                $("#exportModal").modal("hide");
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
            error: function (xhr, status, error) {
                console.error(
                    "Error exporting Excel:",
                    error,
                    xhr.responseText
                );
                alert(
                    "An error occurred while exporting the report: " +
                        (xhr.responseJSON?.error || "Unknown error")
                );
            },
        });
    });

    $(document).on("click", "#exportExpenseDepartmentExcelBtn", function () {
        const button = this;
        const selectedDates = datePicker.selectedDates;

        if (!selectedDates || selectedDates.length < 2) {
            alert("Please select a valid date range.");
            return;
        }

        const billDateFrom = formatDateForAPI(selectedDates[0]);
        const billDateTo = formatDateForAPI(selectedDates[1]);
        $.ajax({
            url: "/export/expense-department-wise",
            method: "POST",
            data: JSON.stringify({
                bill_date_from: billDateFrom,
                bill_date_to: billDateTo,
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
                if (
                    xhr
                        .getResponseHeader("content-type")
                        .includes("application/json")
                ) {
                    data.text().then((text) => {
                        const response = JSON.parse(text);
                        alert(response.error || "Export failed.");
                    });
                    return;
                }

                const startDate = billDateFrom.replace(/-/g, "");
                const endDate = billDateTo.replace(/-/g, "");
                const url = window.URL.createObjectURL(data);
                const a = $("<a>", {
                    href: url,
                    download: `expense_department_wise_report_${startDate}_to_${endDate}.xlsx`,
                }).appendTo("body");
                a[0].click();
                a.remove();
                window.URL.revokeObjectURL(url);
                $("#exportModal").modal("hide");
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
            error: function (xhr, status, error) {
                console.error(
                    "Error exporting Excel:",
                    error,
                    xhr.responseText
                );
                alert(
                    "An error occurred while exporting the report: " +
                        (xhr.responseJSON?.error || "Unknown error")
                );
            },
        });
    });

    $(document).on("click", "#exportExpenseClaimTypeExcelBtn", function () {
        const button = this;
        const selectedDates = datePicker.selectedDates;

        if (!selectedDates || selectedDates.length < 2) {
            alert("Please select a valid date range.");
            return;
        }

        const billDateFrom = formatDateForAPI(selectedDates[0]);
        const billDateTo = formatDateForAPI(selectedDates[1]);

        console.log("Sending payload:", {
            bill_date_from: billDateFrom,
            bill_date_to: billDateTo,
        });

        $.ajax({
            url: "/export/expense-claim-type-wise",
            method: "POST",
            data: JSON.stringify({
                bill_date_from: billDateFrom,
                bill_date_to: billDateTo,
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
                if (
                    xhr
                        .getResponseHeader("content-type")
                        .includes("application/json")
                ) {
                    data.text().then((text) => {
                        const response = JSON.parse(text);
                        alert(response.error || "Export failed.");
                    });
                    return;
                }

                const startDate = billDateFrom.replace(/-/g, "");
                const endDate = billDateTo.replace(/-/g, "");
                const url = window.URL.createObjectURL(data);
                const a = $("<a>", {
                    href: url,
                    download: `expense_claim_type_wise_report_${startDate}_to_${endDate}.xlsx`,
                }).appendTo("body");
                a[0].click();
                a.remove();
                window.URL.revokeObjectURL(url);
                $("#exportModal").modal("hide");
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
            error: function (xhr, status, error) {
                console.error(
                    "Error exporting Excel:",
                    error,
                    xhr.responseText
                );
                alert(
                    "An error occurred while exporting the report: " +
                        (xhr.responseJSON?.error || "Unknown error")
                );
            },
        });
    });
});
