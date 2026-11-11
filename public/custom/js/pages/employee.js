$(document).ready(function () {
    // ─── MEDIATOR & ELIGIBILITY PANEL ───
    const employeeId = window.location.pathname.split('/').pop();
    let mediatorData = {};
    let policyData = {};

    // Load mediator info only if mediator panel exists on page
    function loadMediatorInfo() {
        if ($('#mediator-view').length === 0) {
            return;
        }
        $.ajax({
            url: `/api/employee-mediator/info/${employeeId}`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    mediatorData = response.employee;
                    
                    // Populate mediator dropdown
                    const mediatorSelect = $('#mediator-select');
                    mediatorSelect.empty().append('<option value="">-- Select Mediator --</option>');
                    (response.available_mediators || []).forEach(m => {
                        mediatorSelect.append(`<option value="${m.EmployeeID}">${m.EmpCode} — ${m.MediatorName}</option>`);
                    });
                    if (mediatorData.current_mediator_id) {
                        mediatorSelect.val(mediatorData.current_mediator_id);
                    }

                    // Display current mediator
                    const mediatorDisplay = $('#mediator-view');
                    if (mediatorData.current_mediator_name) {
                        mediatorDisplay.html(`
                            <div style="padding:8px;background:#e7f3ff;border-radius:4px;border-left:3px solid #0ab39c;">
                                <div style="font-size:12px;font-weight:600;color:#0d6efd;">${mediatorData.current_mediator_name}</div>
                                <div style="font-size:10px;color:#666;margin-top:2px;">Assigned as Data Punch Mediator</div>
                            </div>
                        `);
                    } else {
                        mediatorDisplay.html(`
                            <div style="padding:8px;background:#f8f9fa;border-radius:4px;color:#666;font-size:11px;">
                                <i class="ri-alert-line text-warning me-1"></i>No mediator assigned yet
                            </div>
                        `);
                    }
                }
            },
            error: function () {
                $('#mediator-view').html('<div class="text-danger" style="font-size:11px;"><i class="ri-error-warning-line me-1"></i>Failed to load mediator info</div>');
            }
        });
    }

    // Mediator edit handlers
    $('#mediator-edit-btn').on('click', function () {
        $('#mediator-view').addClass('d-none');
        $('#mediator-edit').removeClass('d-none');
    });

    $('#mediator-cancel-btn').on('click', function () {
        $('#mediator-edit').addClass('d-none');
        $('#mediator-view').removeClass('d-none');
    });

    $('#mediator-save-btn').on('click', function () {
        const mediatorId = $('#mediator-select').val();
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> Saving...');

        $.ajax({
            url: `/api/employee-mediator/assign/${employeeId}`,
            type: 'POST',
            data: {
                mediator_id: mediatorId || null,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', 'ri-checkbox-circle-line', 'Mediator updated successfully');
                    loadMediatorInfo();
                    $('#mediator-edit').addClass('d-none');
                    $('#mediator-view').removeClass('d-none');
                } else {
                    showAlert('danger', 'ri-error-warning-line', response.message);
                    btn.prop('disabled', false).html('<i class="ri-check-line"></i> Save');
                }
            },
            error: function () {
                showAlert('danger', 'ri-error-warning-line', 'Failed to update mediator');
                btn.prop('disabled', false).html('<i class="ri-check-line"></i> Save');
            }
        });
    });

    // Load mediator info on page ready if elements exist
    loadMediatorInfo();

    // ─── EXISTING EMPLOYEE CLAIMS MODAL CODE ───
    const modalEl = document.getElementById("employeeClaimsModal");
    if (!modalEl) {
        return;
    }
    const claimsModal = new bootstrap.Modal(modalEl);
    const $statusFilter = $("#employeeClaimsStatusFilter");
    const $tableBody = $("#employeeClaimsTableBody");
    const $meta = $("#employeeClaimsMeta");
    const $emptyState = $("#employeeClaimsEmptyState");
    const COLUMN_COUNT = 23;
    const defaultRowHtml = placeholderRow("Select a record to load claims.");

    let cachedClaims = [];

    function placeholderRow(message, css = "text-muted") {
        return `<tr><td colspan="${COLUMN_COUNT}" class="text-center ${css} py-4">${message}</td></tr>`;
    }

    function formatDate(dateStr) {
        if (!dateStr || dateStr === "0000-00-00") {
            return "";
        }

        const date = new Date(dateStr);
        if (Number.isNaN(date.getTime())) {
            return dateStr;
        }

        const day = String(date.getDate()).padStart(2, "0");
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const year = date.getFullYear();

        return `${day}-${month}-${year}`;
    }

    function formatAmount(amount) {
        if (amount === null || amount === undefined || amount === "") {
            return "-";
        }

        return new Intl.NumberFormat("en-IN", {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(Number(amount));
    }

    function latestAction(claim) {
        if (claim.FinancedDate && claim.FinancedDate !== "0000-00-00") {
            return `Finance (${formatDate(claim.FinancedDate)})`;
        }
        if (claim.VerifyDate && claim.VerifyDate !== "0000-00-00") {
            return `Verified (${formatDate(claim.VerifyDate)})`;
        }
        if (claim.ApprDate && claim.ApprDate !== "0000-00-00") {
            return `Approved (${formatDate(claim.ApprDate)})`;
        }
        if (claim.CrDate && claim.CrDate !== "0000-00-00") {
            return `Created (${formatDate(claim.CrDate)})`;
        }
        return "-";
    }

    function populateStatusFilter() {
        const statuses = [
            ...new Set(
                cachedClaims
                    .map((claim) => claim.ClaimStatus)
                    .filter((status) => !!status && status !== "0")
            ),
        ].sort();
        const currentValue = $statusFilter.val();
        $statusFilter.find("option:not([value=''])").remove();
        statuses.forEach((status) => {
            $statusFilter.append(
                `<option value="${status}">${status}</option>`
            );
        });
        $statusFilter.val(currentValue || "");
    }

    function safeText(value) {
        if (value === null || value === undefined || value === "") {
            return "";
        }
        return $("<div>").text(value).html();
    }

    function formatFlag(flag, truthyLabel = "Yes", falsyLabel = "No") {
        // Empty or null values
        if (flag === null || flag === undefined || flag === "") {
            return '<span class="text-muted">-</span>';
        }

        const numFlag = Number(flag);

        // Special: CLAIM DENIED = 2
        if (numFlag === 2) {
            return '<span class="text-danger">Denied</span>';
        }

        const normalized =
            flag === true ||
            flag === 1 ||
            flag === "1" ||
            flag === "true" ||
            flag === "yes" ||
            flag === "Y";

        return `
            <span class="${normalized ? "text-success" : "text-warning"}">
                ${normalized ? truthyLabel : falsyLabel}
            </span>
        `;
    }

    function formatBackdateStatus(status) {
        return safeText(status);
    }

    function renderClaims() {
        const selectedStatus = $statusFilter.val();
        const claimsToRender = selectedStatus
            ? cachedClaims.filter(
                  (claim) => claim.ClaimStatus === selectedStatus
              )
            : cachedClaims;

        if (!claimsToRender.length) {
            $tableBody.html("");
            $emptyState.removeClass("d-none");
            return;
        }

        const rowsHtml = claimsToRender
            .map(
                (claim, index) => `
    <tr class="${Number(claim.FilledOkay) === 2 ? 'table-danger' : (claim.c_verify !== 'Y' && claim.c_verify !== '1' ? '' : '')}">
        <td>
            ${index + 1}
            ${Number(claim.FilledOkay) === 2 ? '<span class="badge bg-danger ms-1" style="font-size:9px;">Denied</span>' : ''}
            ${Number(claim.FilledOkay) !== 2 && claim.c_verify !== 'Y' && claim.c_verify !== '1' && Number(claim.c_verify) !== 1 ? '<span class="badge bg-warning text-dark ms-1" style="font-size:9px;">Pending Verify</span>' : ''}
        </td>
        <td>${safeText(claim.ExpId)}</td>
        <td>${safeText(claim.ClaimName)}</td>

        <!-- Bill Date -->
        <td>
            <input type="text" class="form-control form-control-sm"
                style="width:80px;border:none;background:transparent;"
                value="${formatDate(claim.BillDate)}"
                readonly>
        </td>

        <!-- Cr Date -->
        <td>
            <input type="text" class="form-control form-control-sm"
                style="width:80px;border:none;background:transparent;"
                value="${formatDate(claim.CrDate)}"
                readonly>
        </td>

        <!-- Claimant Section -->
        <td >
            <input type="text" class="form-control form-control-sm"
                style="width:50px;text-align:right;border:none;background:transparent;"
                value="${formatAmount(claim.FilledTAmt)}"
                readonly>
        </td>

        <td >${makeRemarkInput(
            claim.DateEntryRemark
        )}</td>

        <td >${formatFlag(claim.c_verify)}</td>
        <td >${formatFlag(claim.FilledOkay)}</td>

        <!-- Verified Section -->
        <td >${formatFlag(claim.v_verify)}</td>

        <td >
            <input type="text" class="form-control form-control-sm"
                style="width:50px;text-align:right;border:none;background:transparent;"
                value="${formatAmount(claim.VerifyTAmt)}"
                readonly>
        </td>

        <td >
            <input type="text" class="form-control form-control-sm"
                style="width:80px;border:none;background:transparent;"
                value="${formatDate(claim.VerifyDate)}"
                readonly>
        </td>

        <td >${makeRemarkInput(
            claim.VerifyTRemark
        )}</td>

        <!-- Approved Section -->
        <td >
            <input type="text" class="form-control form-control-sm"
                style="width:50px;text-align:right;border:none;background:transparent;"
                value="${formatAmount(claim.ApprTAmt)}"
                readonly>
        </td>

        <td >
            <input type="text" class="form-control form-control-sm"
                style="width:80px;border:none;background:transparent;"
                value="${formatDate(claim.ApprDate)}"
                readonly>
        </td>

        <td >${makeRemarkInput(
            claim.ApprTRemark
        )}</td>

        <!-- Finance Section -->
        <td >
            <input type="text" class="form-control form-control-sm"
                style="width:50px;text-align:right;border:none;background:transparent;"
                value="${formatAmount(claim.FinancedTAmt)}"
                readonly>
        </td>

        <td >
            <input type="text" class="form-control form-control-sm"
                style="width:80px;border:none;background:transparent;"
                value="${formatDate(claim.FinancedDate)}"
                readonly>
        </td>

        <td >${makeRemarkInput(
            claim.FinancedTRemark
        )}</td>

        <!-- Status -->
        <td>${safeText(claim.ClaimStatus)}</td>

        <!-- Odometers -->
        <td>${safeText(claim.App_Odometer)}</td>
        <td>${formatBackdateStatus(claim.Backdate_Odometer_Status)}</td>

        <!-- Action -->
        <td></td>
    </tr>
    `
            )
            .join("");

        $emptyState.addClass("d-none");
        $tableBody.html(rowsHtml);
    }

    function makeRemarkInput(value) {
        const full = safeText(value);
        const short = full.length > 20 ? full.substring(0, 20) : full;

        return `
            <input type="text" class="form-control form-control-sm"
                style="width:100px;border:none;background:transparent;"
                value="${short}"
                title="${full}"
                data-bs-toggle="tooltip"
                data-bs-placement="bottom"
                readonly>
        `;
    }

    function getStatusBadgeClass(status) {
        if (!status) return "bg-secondary-subtle text-secondary";

        switch (status.toLowerCase()) {
            case "deactivate":
                return "bg-dark-subtle text-dark";

            case "draft":
            case "submitted":
                return "bg-warning-subtle text-warning";

            case "filled":
                return "bg-info-subtle text-info";

            case "verified":
                return "bg-primary-subtle text-primary";

            case "approved":
                return "bg-success-subtle text-success";

            case "finance approved":
            case "financed":
                return "bg-teal-subtle text-teal";

            default:
                return "bg-secondary-subtle text-secondary";
        }
    }

    function setMetaText(employeeId, monthName, total) {
        const parts = [];
        // if (employeeId) {
        //     parts.push(`Employee ID: ${employeeId}`);
        // }
        if (monthName) {
            parts.push(`Month: ${monthName}`);
        }
        parts.push(`Total Claims: ${total}`);
        $meta.text(parts.join(" Â· "));
    }

    $statusFilter.on("change", function () {
        renderClaims();
    });

    // ─── FULLSCREEN TOGGLE FOR CLAIMS MODAL ───
    const $fullscreenBtn = $("#btnToggleClaimsFullscreen");
    const $fullscreenIcon = $("#claimsFullscreenIcon");
    const $claimsModalDialog = $(modalEl).find(".modal-dialog");

    function setClaimsFullscreen(isFull) {
        if (isFull) {
            $claimsModalDialog.addClass("modal-fullscreen");
            $fullscreenIcon.removeClass("ri-fullscreen-line").addClass("ri-fullscreen-exit-line");
            $fullscreenBtn.attr("title", "Exit Fullscreen");
        } else {
            $claimsModalDialog.removeClass("modal-fullscreen");
            $fullscreenIcon.removeClass("ri-fullscreen-exit-line").addClass("ri-fullscreen-line");
            $fullscreenBtn.attr("title", "Toggle Fullscreen");
        }
    }

    $fullscreenBtn.on("click", function (e) {
        e.preventDefault();
        const isFull = $claimsModalDialog.hasClass("modal-fullscreen");
        setClaimsFullscreen(!isFull);
    });

    $(modalEl).on("hidden.bs.modal", function () {
        setClaimsFullscreen(false);
    });

    $(modalEl).find(".modal-header").on("dblclick", function (e) {
        if ($(e.target).closest("select, button, a, input").length) return;
        $fullscreenBtn.trigger("click");
    });

    $(document).on("click", ".view-employee-claims", function (event) {
        event.preventDefault();
        const button = this;
        const month = $(this).data("month");
        const monthName = $(this).data("month-name");
        const employeeId = $(this).data("employee-id");
        const fetchUrl = $(this).data("fetch-url");
        if (!fetchUrl || !employeeId) {
            showAlert(
                "warning",
                "ri-error-warning-line",
                "Missing employee information."
            );
            return;
        }
        claimsModal.show();
        $statusFilter.val("");
        $meta.text("");
        cachedClaims = [];
        $emptyState.addClass("d-none");
        $tableBody.html(
            placeholderRow("Fetching claims, please wait...", "text-primary")
        );
        $.ajax({
            url: fetchUrl,
            type: "GET",
            data: {
                month,
                crby: employeeId,
            },
            dataType: "json",
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            success: function (response) {
                cachedClaims = Array.isArray(response) ? response : [];
                populateStatusFilter();
                renderClaims();
                setMetaText(employeeId, monthName, cachedClaims.length);
            },
            error: function (xhr) {
                $tableBody.html(
                    placeholderRow(
                        "Failed to load claims. Please try again.",
                        "text-danger"
                    )
                );
                $emptyState.addClass("d-none");
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message || "Unable to fetch claims."
                );
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    });

    $(document).on("click", ".return-claim-btn", function () {
        const $btn = $(this);
        const employeeId = $btn.data("employee-id");
        const month = $btn.data("month");

        Swal.fire({
            title: "Return / Reopen Claim?",
            text: "Please provide a reason to return and reopen this month's claim for the employee:",
            input: "textarea",
            inputPlaceholder: "Enter reason for returning / reopening...",
            inputAttributes: {
                rows: 3,
                maxlength: 500
            },
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, Return & Reopen",
            cancelButtonText: "Cancel",
            confirmButtonColor: "#f7b84b",
            cancelButtonColor: "#6c757d",
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return "Reason is required to return / reopen the claim!";
                }
            }
        }).then((result) => {
            if (!result.isConfirmed) return;

            const reason = (result.value || "").trim();
            $btn.prop("disabled", true);

            $.ajax({
                url: "/employee/return-claim",
                method: "POST",
                data: {
                    employee_id: employeeId,
                    month: month,
                    reason: reason,
                    _token: $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (res) {
                    if (res.success) {
                        showAlert("success", "ri-checkbox-circle-line", res.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        $btn.prop("disabled", false);
                        showAlert("danger", "ri-error-warning-line", res.message);
                    }
                },
                error: function () {
                    $btn.prop("disabled", false);
                    showAlert("danger", "ri-error-warning-line", "Error processing return.");
                },
            });
        });
    });

    $(document).on("change", ".allow-submit-toggle", function () {
        const $chk = $(this);

        if (!$chk.is(":checked")) {
            $chk.prop("checked", true);
            return;
        }

        // revert visually while waiting for confirmation
        $chk.prop("checked", false);

        const employeeId = $chk.data("employee-id");
        const month = $chk.data("month");

        Swal.fire({
            title: "Allow Submission?",
            text: "Please provide a reason for allowing this employee to submit claims for this month:",
            input: "textarea",
            inputPlaceholder: "Enter reason for allowing submission...",
            inputAttributes: {
                rows: 3,
                maxlength: 500
            },
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Yes, Allow Submit",
            cancelButtonText: "Cancel",
            confirmButtonColor: "#0ab39c",
            cancelButtonColor: "#6c757d",
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return "Reason is required to allow submission!";
                }
            }
        }).then((result) => {
            if (!result.isConfirmed) return;

            const reason = (result.value || "").trim();
            $chk.prop("checked", true);

            $.ajax({
                url: "/employee/toggle-allow-submit",
                method: "POST",
                data: {
                    employee_id: employeeId,
                    month: month,
                    allow: "Y",
                    reason: reason,
                    _token: $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (res) {
                    if (res.success) {
                        $chk.prop("disabled", true).css({ opacity: "0.6", cursor: "not-allowed" });
                        $chk.closest(".form-check").attr("title", "Already allowed — cannot be reverted");
                        showAlert("success", "ri-checkbox-circle-line", "Submit allowed.");
                    } else {
                        $chk.prop("checked", false);
                        showAlert("danger", "ri-error-warning-line", "Failed to update permission.");
                    }
                },
                error: function () {
                    $chk.prop("checked", false);
                    showAlert("danger", "ri-error-warning-line", "Error updating permission.");
                },
            });
        });
    });

    // Backend Submission - Submit claims for deactivated employees
    $(document).on("click", ".backend-submit-btn", function () {
        const $btn = $(this);
        const employeeId = $btn.data("employee-id");
        const month = $btn.data("month");

        Swal.fire({
            title: "Backend Submission",
            text: "Are you sure you want to submit claims for this month?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, Submit",
            cancelButtonText: "Cancel",
            confirmButtonColor: "#405189",
            cancelButtonColor: "#6c757d",
        }).then((result) => {
            if (!result.isConfirmed) return;

            $btn.prop("disabled", true).html('<i class="ri-loader-4-line ri-spin"></i> Submitting...');

            $.ajax({
                url: "/employee/backend-submit-claim",
                method: "POST",
                data: {
                    employee_id: employeeId,
                    month: month,
                    _token: $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (res) {
                    if (res.success) {
                        showAlert("success", "ri-checkbox-circle-line", res.message || "Claim submitted successfully.");
                        $btn.replaceWith('<span class="badge bg-success-subtle text-success">Submitted</span>');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert("danger", "ri-error-warning-line", res.message || "Failed to submit claim.");
                        $btn.prop("disabled", false).html('<i class="ri-send-plane-line"></i> Submit');
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.message || "Failed to submit claim. Please try again.";
                    showAlert("danger", "ri-error-warning-line", msg);
                    $btn.prop("disabled", false).html('<i class="ri-send-plane-line"></i> Submit');
                },
            });
        });
    });
});
