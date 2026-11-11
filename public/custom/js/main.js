// E-Home 3-level accordion toggle helper
function ehToggle(id, row, arrowSel) {
    var children = document.querySelectorAll(".eh-child-" + id);
    var arrow = row.querySelector(arrowSel);
    var isOpen = children.length && !children[0].classList.contains("d-none");
    children.forEach(function (el) {
        if (isOpen) {
            el.classList.add("d-none");
            // hide any open inline viewer row that belongs to this child
            var expId = el.querySelector && el.querySelector(".view-claim");
            if (expId) {
                var vid = "eh-inline-" + $(expId).data("expid");
                var $vrow = $("#" + vid);
                if ($vrow.length) { $vrow.addClass("d-none"); }
            }
            // also collapse grandchildren
            var grandId = el.dataset.dt || el.dataset.cg;
            if (grandId) {
                document.querySelectorAll(".eh-child-" + grandId).forEach(function (g) {
                    g.classList.add("d-none");
                    // hide inline viewer for each grandchild claim row too
                    var gExp = g.querySelector && g.querySelector(".view-claim");
                    if (gExp) {
                        var gvid = "eh-inline-" + $(gExp).data("expid");
                        var $gvrow = $("#" + gvid);
                        if ($gvrow.length) { $gvrow.addClass("d-none"); }
                    }
                });
                var ga = el.querySelector(".dt-arr, .cg-arr");
                if (ga) ga.style.transform = "rotate(0deg)";
            }
        } else {
            el.classList.remove("d-none");
        }
    });
    if (arrow) arrow.style.transform = isOpen ? "rotate(0deg)" : "rotate(90deg)";
}

function showAlert(
    type = "primary",
    icon = "ri-user-smile-line",
    message = "Primary - Rounded label alert",
    duration = 5000
) {
    const timestamp = Date.now();
    const alertId = `alert-${timestamp}`;
    const progressId = `progress-${timestamp}`;

    const displayLabel = type.toLowerCase() === 'danger' ? 'Notice' : (type.charAt(0).toUpperCase() + type.slice(1));
    const alertHTML = `
        <div id="${alertId}" class="alert alert-${type} alert-dismissible alert-label-icon rounded-label fade show material-shadow position-relative overflow-hidden mt-2" role="alert" style="min-width: 300px;">
            <div id="${progressId}" class="position-absolute top-0 start-0 bg-${type}" style="height: 4px; width: 0%; transition: width ${duration}ms linear;"></div>
            <i class="${icon} label-icon"></i><strong>${displayLabel}</strong> - ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    let container = document.getElementById("alert-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "alert-container";
        container.style.position = "fixed";
        container.style.top = "20px";
        container.style.right = "20px";
        container.style.zIndex = "1055";
        container.style.maxWidth = "350px";
        document.body.appendChild(container);
    }

    container.insertAdjacentHTML("beforeend", alertHTML);

    setTimeout(() => {
        const progressBar = document.getElementById(progressId);
        if (progressBar) {
            progressBar.style.width = "100%";
        }
    }, 50);

    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.classList.remove("show");
            alert.classList.add("fade");
            setTimeout(() => alert.remove(), 500);
        }
    }, duration);
}

function toggleSwitchText() {
    const isActiveCheckbox = document.getElementById("is_active");
    const label = document.getElementById("is_active_label");
    label.textContent = isActiveCheckbox.checked ? "Active" : "Inactive";
}

function startLoader(event) {
    const button = event.currentTarget;
    let modalSelector = button.getAttribute("data-bs-target");

    if (!modalSelector) {
        const nearestModal = button.closest(".modal");
        if (nearestModal) {
            modalSelector = `#${nearestModal.id}`;
        }
    }

    if (!modalSelector) {
        console.error("Modal not found.");
        return;
    }

    const modal = document.querySelector(modalSelector);

    if (!modal) {
        console.error("Modal element not found.");
        return;
    }

    let progressContainer = modal.querySelector(".progress-container");

    if (!progressContainer) {
        progressContainer = document.createElement("div");
        progressContainer.className = "progress-container";
        progressContainer.style.display = "none";
        progressContainer.innerHTML = `
            <div class="progres" style="height: 5px;">
                <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
            </div>`;
        modal.querySelector(".modal-content").prepend(progressContainer);
    }

    const icon = button.querySelector(".label-icon");
    const loader = button.querySelector(".loader");

    if (progressContainer) {
        button.disabled = true;
        if (icon) icon.classList.remove("ri-check-double-line");
        if (loader) loader.style.display = "inline-block";
        progressContainer.style.display = "block";
    } else {
        console.error("Progress container element not found.");
    }
}

function endLoader(event) {
    const button = event.currentTarget;
    let modalSelector = button.getAttribute("data-bs-target");

    if (!modalSelector) {
        const nearestModal = button.closest(".modal");
        if (nearestModal) {
            modalSelector = `#${nearestModal.id}`;
        }
    }

    if (!modalSelector) {
        console.error("Modal not found.");
        return;
    }

    const modal = document.querySelector(modalSelector);

    if (!modal) {
        console.error("Modal element not found.");
        return;
    }

    const progressContainer = modal.querySelector(".progress-container");
    const icon = button.querySelector(".label-icon");
    const loader = button.querySelector(".loader");

    if (progressContainer) {
        button.disabled = false;
        if (icon) icon.classList.add("ri-check-double-line");
        if (loader) loader.style.display = "none";
        progressContainer.style.display = "none";
    } else {
        console.error("Progress container element not found.");
    }
}

function spinStartLoader(event) {
    const button = event.currentTarget;
    const loader = button.querySelector(".loader");
    if (loader) {
        button.disabled = true;
        loader.style.display = "inline-block";
        button.dataset.originalText = button.textContent.trim();
        button.textContent = "";
        button.appendChild(loader);
    } else {
        console.error("Loader element not found.");
    }
}

function spinEndLoader(event) {
    const button = event.currentTarget;
    const loader = button.querySelector(".loader");
    if (loader) {
        button.disabled = false;
        loader.style.display = "none";
        button.textContent = button.dataset.originalText || "Save";
    } else {
        console.error("Loader element not found.");
    }
}

function startSimpleLoader(event) {
    const button = event.currentTarget;
    const icon = button.querySelector(".label-icon");
    const loader = button.querySelector(".loader");

    button.disabled = true;
    if (icon) icon.classList.remove("ri-check-double-line");
    if (loader) loader.style.display = "inline-block";
}

function endSimpleLoader(event) {
    const button = event.currentTarget;
    const icon = button.querySelector(".label-icon");
    const loader = button.querySelector(".loader");

    button.disabled = false;
    if (icon) icon.classList.add("ri-check-double-line");
    if (loader) loader.style.display = "none";
}

function startPageLoader() {
    $("#preloader").attr("style", "");
}

function endPageLoader() {
    $("#preloader").attr("style", "opacity: 0; visibility: hidden;");
}

function loadClaimTypes(selectedClaimId = null) {
    $.ajax({
        url: "get-claim-types",
        method: "GET",
        success: function (data) {
            const $select = $("#claimDetailModal").find("#claimTypeSelect");
            $select.empty().select2({
                data: data,
                placeholder: "Select Claim Type",
                width: "200px",
                allowClear: true,
                dropdownParent: $("#claimDetailModal"),
            });

            if (selectedClaimId) {
                $select.val(selectedClaimId).trigger("change");
            }
        },
        error: function () {
            console.error("Failed to load claim types.");
        },
    });
}

$(document).ready(function () {
    if ($("#company").length && $("#company").is("select")) {
        $("#company").select2({
            placeholder: "-- Select Company --",
            allowClear: true,
        });
    }
    if ($("#financial_year").length && $("#financial_year").is("select")) {
        $("#financial_year").select2({
            placeholder: "-- Select Financial Year --",
            allowClear: true,
        });
    }

    // EHOME-MAIN-01 (11 Nov 2026): the eHome Submit button had no handler; it submits the month through the same
    // route as the claimant screen (claim.php Claim_Submit) and reloads the eHome view it sits in
    $(document).on("click", ".ehome-submit-btn", function (e) {
        e.preventDefault();
        const $btn = $(this);
        const month = $btn.data("month");
        const $box = $btn.closest("#eHomeContainer");
        const empId = $box.data("employee-id") || "";
        const monthName = new Date(2000, (parseInt(month, 10) || 1) - 1, 1).toLocaleString("en-IN", { month: "long" });
        if (!confirm("Submit the claims of " + monthName + " for verification? After submitting you cannot add or change claims of this month.")) return;
        $btn.prop("disabled", true).text("Submitting...");
        $.ajax({
            url: "/claimant/month/submit",
            method: "POST",
            data: { month: month, ei: empId, year_id: $box.data("year-id") || "", _token: $('meta[name="csrf-token"]').attr("content") },
            success: function (res) {
                const ok = res && (String(res.Code) === "300" || res.success === true);
                alert((res && (res.msg || res.message)) || (ok ? "Submitted." : "Could not submit."));
                if (ok) {
                    if ($btn.closest("#myEhomeBody").length && window.loadMyEhome) window.loadMyEhome();
                    else $('.ehome-btn[data-employee-id="' + empId + '"]').first().trigger("click");
                } else {
                    $btn.prop("disabled", false).text("Submit");
                }
            },
            error: function (x) {
                alert((x.responseJSON && (x.responseJSON.msg || x.responseJSON.message)) || "Could not submit the month.");
                $btn.prop("disabled", false).text("Submit");
            }
        });
    });

    // Load E-Home dashboard into modal body
    $(document).on("click", ".ehome-btn", function (e) {
        e.preventDefault();
        const modalEl = document.getElementById("eHomeModal");
        if (!modalEl) { console.error("eHomeModal not found in DOM"); return; }
        const employeeId = $(this).data("employee-id");
        const yearId = $(this).data("year-id") || "";
        const companyId = $(this).data("company-id") || "";
        const $body = $("#eHomeBody");
        $body.html('<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading...</div>');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        $.ajax({
            url: "/expense/ehome-dashboard",
            method: "GET",
            data: {
                employee_id: employeeId,
                year_id: yearId,
                company_id: companyId
            },
            success: function (html) { $body.html(html); },
            error: function () {
                $body.html('<div class="text-center text-danger py-4"><i class="ri-error-warning-line me-1"></i> Failed to load E-Home dashboard.</div>');
            }
        });
    });

    $("#clearCacheBtn").on("click", function () {
        $.ajax({
            url: "clear-caches",
            type: "GET",
            success: function (response) {
                showAlert(
                    "success",
                    "ri-checkbox-circle-line",
                    response.message || "Cache cleared successfully!"
                );
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message ||
                    "An error occurred while clearing the cache."
                );
            },
        });
    });
    $("#companyModal").on("shown.bs.modal", function () {
        $.ajax({
            url: "companies-list",
            method: "GET",
            success: function (response) {
                const companySelect = $("#companySelect");
                companySelect.empty();
                companySelect.append(
                    '<option value="" disabled selected>Select a company</option>'
                );
                response.forEach((company) => {
                    companySelect.append(
                        `<option value="${company.id}">${company.name}</option>`
                    );
                });
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message
                );
            },
        });
    });
    $("#companySelect").on("change", function () {
        const company = $(this).val();
        const fySelect = $("#fySelect");
        fySelect.prop("disabled", true);
        fySelect.empty();
        fySelect.append(
            '<option value="" disabled selected>Select a financial year</option>'
        );
        if (company) {
            $.ajax({
                url: "financial-years.list",
                method: "GET",
                data: {
                    company: company
                },
                success: function (response) {
                    if (Array.isArray(response) && response.length > 0) {
                        response.forEach((year) => {
                            fySelect.append(
                                `<option value="${year.id}">${year.name}</option>`
                            );
                        });
                    } else {
                        fySelect.append(
                            '<option value="" disabled>No financial years available</option>'
                        );
                    }
                    fySelect.prop("disabled", false);
                },
                error: function (xhr) {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        xhr.responseJSON?.message
                    );
                },
            });
        }
    });
    $("#submitSelection").on("click", function () {
        const button = this;
        const companyId = $("#companySelect").val();
        const yearId = $("#fySelect").val();
        if (!companyId || !yearId) {
            showAlert(
                "danger",
                "ri-error-warning-line",
                "Please select both a company and a financial year."
            );
            return;
        }
        $.ajax({
            url: "switch-database",
            method: "POST",
            beforeSend: function () {
                startLoader({
                    currentTarget: button
                });
            },
            data: {
                company_id: companyId,
                year_id: yearId,
                _token: $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                if (response.success) {
                    showAlert(
                        "success",
                        "ri-checkbox-circle-line",
                        response.message || "Database switched successfully!"
                    );
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message
                    );
                }
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message
                );
            },
            complete: function () {
                endLoader({
                    currentTarget: button
                });
            },
        });
    });


    window.companyYearsData = window.companyYearsData || {};


    function loadModalFinancialYears(companyId, selectTopYear = false) {
        const financialYearSelect = $('#modalFinancialYear');
        const sessionCompanyId = parseInt($('meta[name="current-company-id"]').attr('content') || $('#modalCompany').data('current-company-id') || 0);
        const currentYearId = $('meta[name="current-year-id"]').attr('content');

        $.ajax({
            url: `/api/financial-years/${companyId}`,
            method: 'GET',
            success: function (response) {
                if (response.success && response.years && response.years.length > 0) {
                    financialYearSelect.empty();

                    response.years.forEach(function (year) {
                        const option = $('<option></option>')
                            .attr('value', year.YearId)
                            .text(year.Year);
                        financialYearSelect.append(option);
                    });

                    // When switching company or when explicitly requested, always select the top of the financial year list
                    if (selectTopYear || (sessionCompanyId && companyId !== sessionCompanyId)) {
                        financialYearSelect.val(response.years[0].YearId).trigger('change');
                    } else if (currentYearId && financialYearSelect.find(`option[value="${currentYearId}"]`).length > 0) {
                        financialYearSelect.val(currentYearId).trigger('change');
                    } else {
                        financialYearSelect.val(response.years[0].YearId).trigger('change');
                    }
                } else {
                    financialYearSelect.empty();
                    financialYearSelect.append('<option value="">No years available</option>');
                }
            },
            error: function (xhr) {
                console.error('Error loading financial years:', xhr);
                financialYearSelect.empty();
                financialYearSelect.append('<option value="">Error loading years</option>');
            }
        });
    }


    $('#modalCompany').on('change', function () {
        const companyId = parseInt($(this).val());
        if (companyId) {
            loadModalFinancialYears(companyId, true);
        }
    });


    $('#companyYearModal').on('show.bs.modal', function () {
        const currentCompanyId = parseInt($('#modalCompany').val());
        if (currentCompanyId) {
            loadModalFinancialYears(currentCompanyId, false);
        }
    });


    $('#switchCompanyYear').on('click', function () {
        const companyId = $('#modalCompany').val();
        const yearId = $('#modalFinancialYear').val();

        if (!companyId || !yearId) {
            showAlert(
                'danger',
                'ri-error-warning-line',
                'Please select both company and financial year.'
            );
            return;
        }

        const button = this;

        $.ajax({
            url: '/api/switch-company-year',
            method: 'POST',
            data: {
                company_id: companyId,
                year_id: yearId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },


            beforeSend: function () {
                startSimpleLoader({
                    currentTarget: button
                });
            },

            success: function (response) {
                if (response.success) {
                    showAlert(
                        'success',
                        'ri-checkbox-circle-line',
                        'Company and financial year switched successfully!'
                    );


                    if (response.reload) {
                        setTimeout(() => window.location.reload(), 1000);
                    }
                } else {
                    showAlert(
                        'danger',
                        'ri-error-warning-line',
                        response.message || 'Failed to switch company/year'
                    );
                }
            },

            error: function (xhr) {
                let errorMessage = 'Failed to switch company/year';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showAlert('danger', 'ri-error-warning-line', errorMessage);
            },


            complete: function () {
                endSimpleLoader({
                    currentTarget: button
                });
            }
        });
    });


    const gitCommandInfo = {
        status: {
            title: "Git Status",
            description: "Shows the current state of the working directory and staging area — what changes have been staged, which haven't, and which files aren't being tracked.",
            command: "git status",
        },
        pull: {
            title: "Git Pull",
            description: "Fetches and merges changes from the remote repository into the current branch.",
            command: "git pull",
        },
        add: {
            title: "Git Add .",
            description: "Adds all modified and new files to the staging area, preparing them for commit.",
            command: "git add .",
        },
        commit: {
            title: "Git Commit (default msg)",
            description: "Commits all staged changes with a default message.",
            command: 'git commit -m "Auto commit from web panel"',
        },
        push: {
            title: "Git Push",
            description: "Pushes your committed changes to the remote repository.",
            command: "git push",
        },
        fetch: {
            title: "Git Fetch",
            description: "Downloads objects and refs from the remote repository without merging them.",
            command: "git fetch",
        },
        branch: {
            title: "Git Branch",
            description: "Lists existing branches and highlights the current branch.",
            command: "git branch",
        },
        log: {
            title: "Git Log (last 5)",
            description: "Displays the last 5 commits in a concise one-line format.",
            command: "git log -5 --oneline",
        },
        "refresh-ignore": {
            title: "Refresh .gitignore Changes",
            description: "Untracks all cached files and reapplies .gitignore rules, then commits those changes.",
            command: 'git rm -r --cached . && git add . && git commit -m "Apply .gitignore changes"',
        },
        "force-pull": {
            title: "Force Pull (Discard Local & Sync with Remote)",
            description: "Forcibly discards all local changes and resets your local branch to match the remote main branch.",
            command: "git fetch --all && git reset --hard origin/main",
        },
        "force-push": {
            title: "Force Push (Overwrite Remote)",
            description: "Forcibly pushes local changes to remote, overwriting the remote branch.",
            command: "git push --force",
        },
        "reset-local": {
            title: "Keep Git Version (Discard Local Changes)",
            description: "Fetches remote main and resets local files to match it — discarding all local modifications.",
            command: "git fetch origin main && git reset --hard origin/main",
        },
    };
    $("#gitCommand").on("change", function () {
        const cmd = $(this).val();
        const info = gitCommandInfo[cmd];
        if (info) {
            $("#commandInfoBox")
                .removeClass("d-none")
                .html(
                    `<strong>${cmd}</strong><br>
                 <span class="text-muted d-block mb-1">${info.description}</span>
                 <code class="bg-light border px-2 py-1 d-block">${info.command}</code>`
                );
        } else {
            $("#commandInfoBox").addClass("d-none").empty();
        }
    });
    $("#showGitInfo").on("click", function () {
        let html = '<div class="list-group">';
        Object.entries(gitCommandInfo).forEach(([key, info], i) => {
            html += `
            <div class="list-group-item">
                <strong>${i + 1}. ${info.title}</strong><br>
                <span class="text-muted small">${info.description}</span>
                <pre class="bg-light border mt-2 p-2 rounded small mb-0">${info.command
                }</pre>
            </div>
        `;
        });
        html += "</div>";

        $("#gitCommandDetails").html(html);
        $("#gitFormSection").addClass("d-none");
        $("#gitInfoSection").removeClass("d-none");
    });
    $("#backToForm").on("click", function () {
        $("#gitInfoSection").addClass("d-none");
        $("#gitFormSection").removeClass("d-none");
    });
    $("#gitCommandBtn").on("click", function () {
        $("#gitCommand").val("");
        $("#gitPassword").val("");
        $("#gitOutputContainer").addClass("d-none");
        $("#gitOutput").text("");
        $("#gitInfoSection").addClass("d-none");
        $("#commandInfoBox").addClass("d-none");
        $("#gitFormSection").removeClass("d-none");
        $("#gitCommandModal").modal("show");
    });
    $("#runGitCommand").on("click", function () {
        const cmd = $("#gitCommand").val();
        const password = $("#gitPassword").val();

        if (!cmd) {
            showAlert(
                "warning",
                "ri-alert-line",
                "Please select a Git command first."
            );
            return;
        }

        if (!password) {
            showAlert("warning", "ri-lock-line", "Please enter your password.");
            return;
        }

        Swal.fire({
            title: "Confirm Git Command",
            html: `You are about to run:<br><strong>${cmd}</strong><br><br>Do you want to continue?`,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Yes, Run it",
            cancelButtonText: "Cancel",
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
        }).then((result) => {
            if (result.isConfirmed) {
                const $btn = $("#runGitCommand");
                $btn.prop("disabled", true).text("Processing...");

                $.ajax({
                    url: "/run-git-command",
                    type: "POST",
                    data: {
                        command: cmd,
                        password: password,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    success: function (res) {
                        $("#gitOutputContainer").removeClass("d-none");

                        if (res.success) {
                            const outputText = Array.isArray(res.data) ?
                                res.data.join("\n") :
                                JSON.stringify(res.data, null, 2);
                            $("#gitOutput").text(outputText);

                            showAlert(
                                "success",
                                "ri-checkbox-circle-line",
                                res.message ||
                                "Git command executed successfully!"
                            );
                        } else {
                            $("#gitOutput").text(
                                res.message || "Operation failed."
                            );
                            showAlert(
                                "error",
                                "ri-close-circle-line",
                                res.message || "Something went wrong."
                            );
                        }
                    },
                    error: function (xhr) {
                        $("#gitOutputContainer").removeClass("d-none");

                        const errorMessage =
                            xhr.responseJSON?.message ||
                            xhr.responseJSON?.error ||
                            "Something went wrong.";

                        $("#gitOutput").text("Error: " + errorMessage);
                        showAlert(
                            "error",
                            "ri-close-circle-line",
                            errorMessage
                        );
                    },
                    complete: function () {
                        $btn.prop("disabled", false).text("Run Command");
                    },
                });
            }
        });
    });

    // Auto-show Company/Year Modal if flag is set
    if ($('#companyYearModalTrigger').attr('data-show') === 'true') {
        const modal = new bootstrap.Modal(document.getElementById('companyYearModal'));
        modal.show();

        // Ensure financial years are loaded
        const currentCompanyId = parseInt($('#modalCompany').val());
        if (currentCompanyId) {
            loadModalFinancialYears(currentCompanyId, false);
        }
    }

    // Search initialization
    const searchInput = document.getElementById("empSearchOptions");
    const searchDropdown = document.getElementById("search-dropdown");
    const notificationList = document.querySelector(".notification-list");
    const statusFilter = document.getElementById("statusFilter");
    const simpleBar = document.querySelector("[data-simplebar]");
    let debounceTimer;
    let currentPage = 1;
    let searchTerm = "";
    let statusFilterValue = statusFilter ? statusFilter.value : "A";
    let loading = false;
    let hasMore = true;
    let activeItemIndex = -1;

    if (!searchInput || !searchDropdown || !notificationList) {
        // Search elements are only present on specific pages — skip search init silently
    } else {
    // ── Search init: only runs on pages with the employee search bar ──

    const simpleBarInstance =
        (simpleBar && SimpleBar?.instances?.get(simpleBar)) || null;
    const scrollElement = simpleBarInstance ?
        simpleBarInstance.getScrollElement() :
        notificationList;

    if (scrollElement) {

    document.addEventListener("keydown", function (event) {
        if (event.ctrlKey && event.key === "e") {
            event.preventDefault();
            searchInput.focus();
            resetAndFetch(searchInput.value.trim());
            searchDropdown.classList.add("show");
        }
    });

    searchInput.addEventListener("keydown", function (event) {
        const items = notificationList.querySelectorAll(".dropdown-item");
        if (items.length === 0) return;

        if (event.key === "ArrowDown") {
            event.preventDefault();
            if (activeItemIndex < items.length - 1) {
                activeItemIndex++;
                updateActiveItem(items);
                scrollToActiveItem(items[activeItemIndex]);
            }
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            if (activeItemIndex > 0) {
                activeItemIndex--;
                updateActiveItem(items);
                scrollToActiveItem(items[activeItemIndex]);
            }
        } else if (event.key === "Enter" && activeItemIndex >= 0) {
            event.preventDefault();
            const selectedItem = items[activeItemIndex];
            if (selectedItem) {
                window.location.href = selectedItem.href;
            }
        }
    });

    searchInput.addEventListener("focus", function () {
        resetAndFetch("");
        searchDropdown.classList.add("show");
        activeItemIndex = -1;
    });

    searchInput.addEventListener("input", function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            resetAndFetch(this.value.trim());
            searchDropdown.classList.add("show");
            activeItemIndex = -1;
        }, 300);
    });

    statusFilter?.addEventListener("change", function () {
        statusFilterValue = this.value || "A";
        resetAndFetch(searchInput.value.trim());
        searchDropdown.classList.add("show");
        activeItemIndex = -1;
    });

    document
        .getElementById("search-close-options")
        ?.addEventListener("click", function () {
            searchInput.value = "";
            if (statusFilter) {
                statusFilter.value = "A";
                statusFilterValue = "A";
            }
            searchDropdown.classList.remove("show");
            notificationList.innerHTML = "";
            activeItemIndex = -1;
        });

    function resetAndFetch(term) {
        searchTerm = term;
        currentPage = 1;
        hasMore = true;
        notificationList.innerHTML = "";
        activeItemIndex = -1;
        fetchEmployees();
    }

    function fetchEmployees() {
        if (loading || !hasMore) return;
        loading = true;

        fetch("/employees/search", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                    ?.content || "",
            },
            body: JSON.stringify({
                search: searchTerm,
                page: currentPage,
                status: statusFilterValue,
            }),
        })
            .then((response) => {
                if (!response.ok)
                    throw new Error("Network response was not ok");
                return response.json();
            })
            .then((data) => {
                const {
                    employees,
                    has_more
                } = data;
                if (employees.length === 0 && currentPage === 1) {
                    notificationList.innerHTML =
                        '<div class="text-center text-muted py-2">No employees found</div>';
                    hasMore = false;
                } else {
                    appendResults(employees);
                    hasMore = has_more;
                    if (hasMore) currentPage++;
                }
                loading = false;
            })
            .catch((error) => {
                console.error("Error:", error);
                notificationList.innerHTML =
                    '<div class="text-center text-muted py-2">Error loading results</div>';
                loading = false;
            });
    }

    function appendResults(employees) {
        employees.forEach((employee) => {
            const employeeItem = document.createElement("a");
            const empStatus = employee.status || employee.EmpStatus || "A";
            const statusInfo = getStatusLabel(empStatus);
            const statusLabel = employee.status_label || (employee.is_resigned ? "Resigned" : statusInfo.label);
            const isResigned = statusLabel === "Resigned" || empStatus === "R" || Boolean(employee.is_resigned);
            const isDeactive = statusLabel === "Deactive" || empStatus === "D";
            const statusClass = isResigned
                ? "badge bg-warning-subtle text-warning"
                : (isDeactive
                    ? "badge bg-danger-subtle text-danger"
                    : "badge bg-success-subtle text-success");

            const isValidDate = (d) => d && d !== '0000-00-00' && d !== '';
            const formatDate = (d) => {
                if (!isValidDate(d)) return null;
                const dt = new Date(d);
                return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            };

            const resDate = formatDate(employee.DateOfResignation);
            const sepDate = formatDate(employee.DateOfSepration);

            // Row 3: rendered if resignation/separation date exists
            const dateRow = (resDate || sepDate) ? `
                <div class="fs-10 mt-1" style="line-height:1.3;">
                    ${resDate ? `<span class="text-warning fw-semibold me-2">Res Dt.: ${resDate}</span>` : ''}
                    ${sepDate ? `<span class="text-danger fw-semibold">Sep Dt.: ${sepDate}</span>` : ''}
                </div>` : '';

            employeeItem.href = `${window.location.origin}/employee/${employee.EmployeeID}`;
            employeeItem.className = "dropdown-item notify-item py-2";
            employeeItem.innerHTML = `
                <div class="d-flex">
                    <img src="${employee.image_url}"
                         class="me-3 rounded-circle avatar-xs" alt="user-pic"
                         onerror="this.src='/custom/no_user.png'">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="m-0" style="font-size:12px;">${employee.EmpCode} - ${employee.EmployeeName}</h6>
                            <span class="fs-11 ${statusClass}">${statusLabel}</span>
                        </div>
                        <div class="fs-11 text-muted mt-1" style="line-height:1.3;">
                            ${employee.grade_name || "-"} - ${employee.department_name || "-"}
                        </div>
                        ${dateRow}
                    </div>
                </div>
            `;
            notificationList.appendChild(employeeItem);
        });
    }

    function getStatusLabel(code) {
        switch (code) {
            case "A":
                return {
                    label: "Active",
                    className: "badge bg-success-subtle text-success",
                };
            case "R":
                return {
                    label: "Resigned",
                    className: "badge bg-warning-subtle text-warning",
                };
            case "D":
                return {
                    label: "Deactive",
                    className: "badge bg-danger-subtle text-danger",
                };
            default:
                return {
                    label: "Unknown",
                    className: "badge bg-secondary-subtle text-secondary",
                };
        }
    }

    function updateActiveItem(items) {
        items.forEach((item, index) => {
            item.classList.toggle("active", index === activeItemIndex);
        });
    }

    function scrollToActiveItem(activeItem) {
        if (!activeItem || !simpleBarInstance) return;
        const scrollContainer = scrollElement;
        const itemTop = activeItem.offsetTop;
        const itemBottom = itemTop + activeItem.offsetHeight;
        const containerTop = scrollContainer.scrollTop;
        const containerBottom = containerTop + scrollContainer.clientHeight;

        if (itemTop < containerTop) {
            scrollContainer.scrollTop = itemTop;
        } else if (itemBottom > containerBottom) {
            scrollContainer.scrollTop =
                itemBottom - scrollContainer.clientHeight;
        }
    }

    let scrollTimeout;
    scrollElement.addEventListener("scroll", function () {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            const nearBottom =
                this.scrollTop + this.clientHeight >= this.scrollHeight - 20;
            if (nearBottom && !loading && hasMore) {
                fetchEmployees();
            }
        }, 100);
    });

    document.addEventListener("click", function (event) {
        const appSearch = document.querySelector(".app-search");
        if (appSearch && !appSearch.contains(event.target)) {
            searchDropdown.classList.remove("show");
            notificationList.innerHTML = "";
            searchInput.value = "";
            activeItemIndex = -1;
        }
    });

    } // end if (scrollElement)
    } // end else: search elements found

    // -- E-Home expand/collapse --
    $(document).on("click", ".eh-expand-btn", function () {
        const $btn = $(this);
        const target = $btn.data("target");
        const month = $btn.data("month");
        const type = $btn.data("type");
        const employeeId = $btn.data("employee");
        const $panel = $("#" + target);
        const isOpen = !$panel.hasClass("d-none");

        $panel.toggleClass("d-none");
        $btn.css("transform", isOpen ? "rotate(0deg)" : "rotate(90deg)");

        if (!isOpen && !$panel.data("loaded")) {
            if (!month || !type || !employeeId) return;
            $panel.data("loaded", true);

            const yearId = $btn.data("year-id") || $("#eHomeContainer").data("year-id") || "";
            const companyId = $btn.data("company-id") || $("#eHomeContainer").data("company-id") || "";

            $.ajax({
                url: "/expense/ehome-month-details",
                method: "GET",
                data: {
                    month: month,
                    type: type,
                    employee_id: employeeId,
                    year_id: yearId,
                    company_id: companyId
                },
                success: function (res) {
                    const $loader = $panel.find(".eh-day-loader");
                    const $table = $panel.find(".eh-day-table");
                    const $tbody = $panel.find(".eh-day-tbody");
                    $loader.addClass("d-none");

                    if (!res.success || !res.data || !res.data.length) {
                        $panel.find(".eh-expand-panel, .eh-expand-panel-closed")
                            .append('<div class="text-center text-muted fst-italic py-2">No details found.</div>');
                        return;
                    }

                    const fmt = v => (v !== null && v !== undefined && v !== '' && !isNaN(v) && parseFloat(v) >= 0) ? "₹" + Number(v).toLocaleString("en-IN") : "—";

                    // Colors: dark -> medium -> light
                    const dark = type === "open" ? "#2c6e91" : "#4b8f29d9";
                    const medium = type === "open" ? "#d0e8f5" : "#d6edcc";
                    const light = type === "open" ? "#f0f8ff" : "#f4fbf0";
                    const darkTx = "#fff";
                    const medTx = type === "open" ? "#1a4f6e" : "#2d5a1a";

                    // Columns: arrow + Exp ID + Claim Type + Amount + (open: KM,Status,Verified | closed: Ver,Appr,Fin,Status)
                    const detailCols = type === "open" ? 3 : 4;
                    const totalCols = 4 + detailCols; // arrow + expid + type + amt + detail cols

                    let html = "";
                    let idx = 0;

                    // Group by cgCode -> BillDate
                    const byCg = {};
                    res.data.forEach(d => {
                        const cg = d.cgCode || "Other";
                        const bd = d.BillDate || "Unknown";
                        if (!byCg[cg]) byCg[cg] = {};
                        if (!byCg[cg][bd]) byCg[cg][bd] = [];
                        byCg[cg][bd].push(d);
                    });

                    // Build thead
                    let thead = `<thead><tr style="background:${dark};color:${darkTx};font-size:11px;">
                        <th style="width:24px;"></th>
                        <th>Exp ID</th><th>Claim Type</th><th>Amount</th>`;
                    if (type === "open") {
                        thead += `<th>KM</th><th>Status</th><th>Verified</th>`;
                    } else {
                        thead += `<th>Verified</th><th>Approved</th><th>Finance</th><th>Status</th>`;
                    }
                    thead += `</tr></thead>`;

                    Object.entries(byCg).forEach(([cgCode, dateMap]) => {
                        const cgAll = Object.values(dateMap).flat();
                        const cgTotal = cgAll.reduce((s, d) => s + parseFloat(d.FilledTAmt || 0), 0);
                        const cgId = target + "-cg-" + (idx++);

                        // Level 1: cgCode -- DARK
                        html += `<tr class="eh-cg-row" data-cg="${cgId}" style="background:${dark};color:${darkTx};cursor:pointer;font-size:12px;font-weight:700;"
                            onclick="ehToggle('${cgId}',this,'.cg-arr')">
                            <td><span class="cg-arr" style="display:inline-block;transition:transform .2s;font-size:14px;font-weight:700;"><i class="ri-arrow-right-s-fill"></i></span></td>
                            <td colspan="${totalCols - 2}" style="text-align:left;letter-spacing:.5px;">${cgCode}
                                <span style="background:rgba(255,255,255,.2);border-radius:20px;padding:1px 8px;font-size:10px;margin-left:6px;">${cgAll.length}</span>
                            </td>
                            <td style="text-align:right;">${fmt(cgTotal)}</td>
                        </tr>`;

                        Object.entries(dateMap).forEach(([date, claims]) => {
                            const bd = date !== "Unknown" ? date.split("-").reverse().join("/") : "—";
                            const dateTotal = claims.reduce((s, d) => s + parseFloat(d.FilledTAmt || 0), 0);
                            const dtId = target + "-dt-" + (idx++);

                            // Level 2: BillDate -- MEDIUM
                            html += `<tr class="eh-dt-row eh-child-${cgId} d-none" data-dt="${dtId}" style="background:${medium};color:${medTx};cursor:pointer;font-size:11px;font-weight:600;"
                                onclick="ehToggle('${dtId}',this,'.dt-arr')">
                                <td style="padding-left:20px;"><span class="dt-arr" style="display:inline-block;transition:transform .2s;font-size:13px;font-weight:700;color:${dark};"><i class="ri-arrow-right-s-fill"></i></span></td>
                                <td colspan="${totalCols - 2}" style="text-align:left;">${bd}
                                    <span style="background:${dark};color:white;border-radius:20px;padding:1px 7px;font-size:10px;margin-left:6px;">${claims.length}</span>
                                </td>
                                <td style="text-align:right;">${fmt(dateTotal)}</td>
                            </tr>`;

                            // Level 3: Claims -- LIGHT
                            claims.forEach(d => {
                                // Badge logic
                                let rowStyle = `background:${light};font-size:11px;`;
                                let badge = '';
                                if (Number(d.FilledOkay) === 2) {
                                    rowStyle = `background:#ffe0e0;font-size:11px;`;
                                    badge = '<span class="badge bg-danger" style="font-size:8px;margin-left:4px;">Denied</span>';
                                } else if (d.c_verify && d.c_verify !== 'Y' && d.c_verify !== '1') {
                                    badge = '<span class="badge bg-warning text-dark" style="font-size:8px;margin-left:4px;">C-Unverified</span>';
                                }

                                html += `<tr class="eh-claim-row eh-child-${dtId} d-none" style="${rowStyle}">
                                    <td style="padding-left:36px;"></td>
                                    <td>
                                        <a href="#" class="view-claim fw-semibold text-primary"
                                            data-expid="${d.ExpId}"
                                            data-claim-id="${d.ClaimId}"
                                            data-cgid="${d.cgId}"
                                            data-empid="${d.CrBy}">
                                            ${d.ExpId}
                                        </a>${badge}
                                    </td>
                                    <td>${d.ClaimName || "—"}</td>
                                    <td style="text-align:right;">${fmt(d.FilledTAmt)}</td>`;
                                if (type === "open") {
                                    html += `<td>${parseFloat(d.TotKm) > 0 ? d.TotKm + " km" : "—"}</td>
                                        <td><span class="sp sp-filled" style="font-size:10px;">${Number(d.FilledOkay) === 2 ? 'Denied' : (d.ClaimStatus || "—")}</span></td>
                                        <td>${d.v_verify === "Y" ? '<span class="sp sp-verified" style="font-size:10px;">Verified</span>' : "—"}</td>`;
                                } else {
                                    html += `<td style="text-align:right;">${fmt(d.VerifyTAmt)}</td>
                                        <td style="text-align:right;">${fmt(d.ApprTAmt)}</td>
                                        <td style="text-align:right;">${fmt(d.FinancedTAmt)}</td>
                                        <td><span class="sp sp-paid" style="font-size:10px;">${Number(d.FilledOkay) === 2 ? 'Denied' : (d.ClaimStatus || "—")}</span></td>`;
                                }
                                html += `</tr>`;
                            });
                        });
                    });

                    // inject thead + tbody into the single table
                    $table.find("thead").remove();
                    $table.prepend(thead);
                    $tbody.html(html);
                    $table.removeClass("d-none");
                },
                error: function () {
                    $panel.find(".eh-day-loader").html('<span class="text-danger small">Failed to load details.</span>');
                }
            });
        }
    });

    // -- E-Home month row click -> trigger expand button --
    $(document).on("click", ".eh-month-row", function (e) {
        if ($(e.target).closest("button, a").length) return;
        $(this).find(".eh-expand-btn").trigger("click");
    });

    // -- E-Home: view-claim inside eHomeModal -> inline Document Viewer + Form below ExpId row --
    $(document).on("click", "#eHomeModal .view-claim, #myEhomeBody .view-claim", function (e) { // EHOME-MAIN-01: also on the employee dashboard
        e.preventDefault();
        e.stopPropagation();

        const $link   = $(this);
        const expId   = $link.data("expid");
        const claimId = $link.data("claim-id");
        const cgId    = $link.data("cgid");
        const empId   = $link.data("empid");
        const $tr     = $link.closest("tr");
        const rowId   = "eh-inline-" + expId;
        const $existing = $("#" + rowId);

        // -- toggle if already rendered --
        if ($existing.length) {
            $existing.toggleClass("d-none");
            $link.toggleClass("fw-bold");
            return;
        }

        const colCount = $tr.closest("table").find("thead tr:first th").length || $tr.find("td").length || 6;

        // -- insert skeleton row --
        const $row = $(`
            <tr id="${rowId}" class="eh-inline-viewer-row">
                <td colspan="${colCount}" class="p-0 border-top-0">
                    <div class="eh-inline-viewer" style="background:#fff;border-top:3px solid #d6edcc;">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2"
                             style="background:#d6edcc;color:#1a4f6e;font-size:12px;">
                            <span><i class="ri-file-text-line me-1"></i>
                                <strong>Exp ID: ${expId}</strong>
                            </span>
                            <button type="button" class="btn-close btn-close-white btn-sm eh-inline-viewer-close"
                                    data-row="${rowId}" style="font-size:10px;"></button>
                        </div>
                        <div class="row g-0 eh-inline-body" style="min-height:420px;">
                            <div class="col-md-5 border-end d-flex align-items-center justify-content-center"
                                  id="eh-viewer-${expId}" style="background:#f8f9fa;min-height:420px;">
                                <div class="text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading...</div>
                            </div>
                            <div class="col-md-7 p-3 overflow-auto" id="eh-form-${expId}"
                                 style="max-height:520px;">
                                <div class="text-muted text-center py-5">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        `);
        $tr.after($row);
        $link.addClass("fw-bold");

        // -- fetch form HTML + uploaded files in parallel --
        const formReq = $.ajax({
            url: "/claim-detail",
            method: "GET",
            data: { claim_id: claimId, expid: expId }
        });

        const filesReq = $.ajax({
            url: `/get-uploaded-files/${expId}/${claimId}`,
            method: "GET"
        });

        // -- render form --
        formReq.done(function (res) {
            const html = res.html || '<div class="alert alert-warning mb-0">No detail available.</div>';
            $(`#eh-form-${expId}`).html(html);
        }).fail(function () {
            $(`#eh-form-${expId}`).html('<div class="alert alert-danger m-3">Failed to load form.</div>');
        });

        // -- render image/pdf viewer --
        filesReq.done(function (res) {
            const $viewer = $(`#eh-viewer-${expId}`);
            let files = [];

            if (res.data && res.data.uploaded_files) {
                const raw = res.data.uploaded_files;
                if (Array.isArray(raw)) {
                    files = raw.map(f => ehBuildFile(f.file_path, f.file_url));
                } else {
                    Object.values(raw).forEach(cat => {
                        (cat.files || []).forEach(f => files.push(ehBuildFile(f.file_path, f.file_url)));
                    });
                }
            }

            if (!files.length) {
                $viewer.html('<div class="text-muted text-center p-4"><i class="ri-image-off-line fs-3 d-block mb-1"></i>No files</div>');
                return;
            }

            ehRenderViewer($viewer, files, expId);
        }).fail(function () {
            $(`#eh-viewer-${expId}`).html('<div class="text-danger text-center p-4">Failed to load files.</div>');
        });
    });

    function ehBuildFile(filePath, fileUrl) {
        const ext  = filePath.split(".").pop().toLowerCase();
        const type = ["jpg","jpeg","png","gif","bmp","webp","heic"].includes(ext)
            ? "image/" + ext
            : ext === "pdf" ? "application/pdf" : "application/octet-stream";
        return { url: fileUrl, name: filePath, type };
    }

    function ehRenderViewer($container, files, uid) {
        let idx = 0;

        function buildThumb(f, i) {
            if (f.type.startsWith("image/")) {
                return `<img src="${f.url}" class="eh-thumb${i===0?' active':''}" data-i="${i}"
                             style="width:52px;height:52px;object-fit:cover;cursor:pointer;border-radius:4px;border:2px solid ${i===0?'#d6edcc':'#dee2e6'};margin:2px;"
                             onerror="this.src='/custom/no_user.png'">`;
            }
            return `<div class="eh-thumb${i===0?' active':''}" data-i="${i}"
                         style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;cursor:pointer;border-radius:4px;border:2px solid ${i===0?'#d6edcc':'#dee2e6'};background:#f0f0f0;margin:2px;font-size:22px;color:#c00;">
                        <i class="ri-file-pdf-line"></i></div>`;
        }

        function renderMain(f) {
            if (f.type.startsWith("image/")) {
                return `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                            <img src="${f.url}" style="max-width:100%;max-height:340px;object-fit:contain;border-radius:4px;"
                                 onerror="this.src='/custom/no_user.png'">
                        </div>`;
            }
            return `<iframe src="${f.url}" style="width:100%;height:340px;border:none;border-radius:4px;"></iframe>`;
        }

        const thumbsHtml = files.map((f, i) => buildThumb(f, i)).join("");

        $container.html(`
            <div style="display:flex;flex-direction:column;height:100%;padding:8px;">
                <div class="eh-main-view" id="eh-main-${uid}" style="flex:1;min-height:300px;">
                    ${renderMain(files[0])}
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:2px;padding-top:6px;border-top:1px solid #dee2e6;margin-top:6px;">
                    ${thumbsHtml}
                    <a href="${files[0].url}" target="_blank" download
                       style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;border-radius:4px;border:1px solid #dee2e6;background:#f8f9fa;color:#d6edcc;font-size:18px;margin:2px;"
                       title="Download"><i class="ri-download-line"></i></a>
                </div>
            </div>
        `);

        $container.on("click", ".eh-thumb", function () {
            idx = parseInt($(this).data("i"));
            $container.find(".eh-thumb").css("border-color", "#dee2e6");
            $(this).css("border-color", "#d6edcc");
            $(`#eh-main-${uid}`).html(renderMain(files[idx]));
            // update download link
            $container.find("a[download]").attr("href", files[idx].url);
        });
    }

    // -- close inline viewer --
    $(document).on("click", ".eh-inline-viewer-close", function () {
        const rowId = $(this).data("row");
        const expId = rowId.replace("eh-inline-", "");
        $(`[data-expid="${expId}"]`).removeClass("fw-bold");
        $("#" + rowId).remove();
    });

});
