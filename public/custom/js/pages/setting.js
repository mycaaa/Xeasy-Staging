$(document).ready(function () {
    function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }
    $("#timeZone, #language").select2();
    const activeTab = getQueryParam("tab") || "general";
    $(".nav-link").removeClass("active");
    $(".tab-pane").removeClass("show active");
    $(`#${activeTab}-tab`).addClass("active");
    $(`#${activeTab}`).addClass("show active");

    $(".nav-link").on("click", function (e) {
        e.preventDefault();
        const tabId = $(this).attr("href").split("#")[1];
        const newUrl = `${window.location.pathname}?tab=${tabId}`;
        window.history.pushState({ tab: tabId }, "", newUrl);
        $(".nav-link").removeClass("active");
        $(".tab-pane").removeClass("show active");
        $(this).addClass("active");
        $(`#${tabId}`).addClass("show active");
    });

    window.onpopstate = function (event) {
        const tabId = getQueryParam("tab") || "general";
        $(".nav-link").removeClass("active");
        $(".tab-pane").removeClass("show active");
        $(`#${tabId}-tab`).addClass("active");
        $(`#${tabId}`).addClass("show active");
    };

    $(document).on("click", "#database-tab", function (event) {
        fetchCompanies(event);
    });

    $(document).on(
        "click",
        "[data-bs-target='#configModal']",
        function (event) {
            const companyId = $(this).data("company-id");
            const companyName = $(this).data("company-name");

            $("#hrims_company_id").val(companyId);
            $("#expense_company_id").val(companyId);
            $("#modalCompanyName").text(`- ${companyName}`);

            fetchCompanyConfig(companyId, event);

            $("#dbTabs .nav-link").removeClass("active");
            $("#dbTabContent .tab-pane").removeClass("show active");
            $("#hrims-tab").addClass("active");
            $("#hrims").addClass("show active");
        }
    );

    $("#hrims .btn-primary").on("click", function (event) {
        const button = event.currentTarget;
        const formData = {
            company_id: $("#hrims_company_id").val(),
            db_name: $("#hrims input[name='db_name']").val(),
            db_connection: $("#hrims_db_connection").val(),
            db_host: $("#hrims_db_host").val(),
            db_port: $("#hrims_db_port").val(),
            db_database: $("#hrims_db_database").val(),
            db_username: $("#hrims_db_username").val(),
            db_password: $("#hrims_db_password").val(),
            is_active: $("#hrims #is_active").is(":checked") ? 1 : 0,
        };
        saveCompanyConfig(formData, button);
    });

    $("#expense .btn-primary").on("click", function (event) {
        const button = event.currentTarget;
        const formData = {
            company_id: $("#expense_company_id").val(),
            db_name: $("#expense input[name='db_name']").val(),
            db_connection: $("#expense_db_connection").val(),
            db_host: $("#expense_db_host").val(),
            db_port: $("#expense_db_port").val(),
            db_database: $("#expense_db_database").val(),
            db_username: $("#expense_db_username").val(),
            db_password: $("#expense_db_password").val(),
            is_active: $("#expense #is_active").is(":checked") ? 1 : 0,
        };
        saveCompanyConfig(formData, button);
    });

    $(document).on("click", "#save-theme-settings", function () {
        let settings = {
            layout: $('input[name="data-layout"]:checked').val() || "vertical",
            sidebar_user_profile: $("#sidebarUserProfile").is(":checked"),
            theme: $('input[name="data-theme"]:checked').val() || "default",
            color_scheme:
                $('input[name="data-bs-theme"]:checked').val() || "light",
            sidebar_visibility:
                $('input[name="data-sidebar-visibility"]:checked').val() ||
                "show",
            layout_width:
                $('input[name="data-layout-width"]:checked').val() || "fluid",
            layout_position:
                $('input[name="data-layout-position"]:checked').val() ||
                "fixed",
            topbar_color:
                $('input[name="data-topbar"]:checked').val() || "light",
            sidebar_size:
                $('input[name="data-sidebar-size"]:checked').val() || "lg",
            sidebar_view:
                $('input[name="data-layout-style"]:checked').val() || "default",
            sidebar_color:
                $('input[name="data-sidebar"]:checked').val() || "light",
            sidebar_image:
                $('input[name="data-sidebar-image"]:checked').val() || "none",
            primary_color:
                $('input[name="data-theme-colors"]:checked').val() || "default",
            preloader:
                $('input[name="data-preloader"]:checked').val() || "disable",
            body_image:
                $('input[name="data-body-image"]:checked').val() || "none",
        };

        $.ajax({
            url: "/settings/theme",
            type: "POST",
            data: JSON.stringify(settings),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                showAlert(
                    "success",
                    "ri-checkbox-circle-line",
                    response.message || "Settings saved successfully!"
                );
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message ||
                        "An error occurred while saving settings."
                );
            },
        });
    });

    $("#generalSettingsForm").on("submit", function (e) {
        e.preventDefault();
        var formData = new FormData(this);

        $.ajax({
            url: "/settings/general", // Laravel route
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                showAlert(
                    "success",
                    "ri-checkbox-circle-line",
                    response.message || "Settings saved successfully!"
                );
            },
            error: function (xhr) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    xhr.responseJSON?.message ||
                        "An error occurred while saving settings."
                );
            },
        });
    });

     let viewer;

    // Initialize Viewer.js when image is available
    function initializeViewer() {
        if (typeof Viewer !== 'undefined' && $('#logoLivePreview').attr('src')) {
            if (viewer) viewer.destroy();
            viewer = new Viewer(document.getElementById('logoLivePreview'), {
                inline: true,
                viewed: function() {
                    viewer.zoomTo(1); // Reset zoom on view
                },
                toolbar: {
                    zoomIn: true,
                    zoomOut: true,
                    oneToOne: true,
                    reset: true,
                    prev: false,
                    next: false,
                    rotateLeft: true,
                    rotateRight: true,
                    flipHorizontal: true,
                    flipVertical: true,
                    fullscreen: true
                },
                title: [function(image) {
                    return image.alt + ' (' + (image.width) + ' × ' + (image.height) + ')';
                }]
            });
            $('#imageViewer').show();
        } else if (typeof Viewer === 'undefined') {
            console.error('Viewer.js is not loaded. Advanced preview disabled.');
            alert('Advanced preview functionality is unavailable due to a loading error.');
        }
    }

    // Fetch and fill data when #general-tab is clicked
    $('#general-tab').on('click', function() {
        $.ajax({
            url: '/settings/general',
            type: 'GET',
            success: function(response) {
                if (response.data) {
                    var settings = response.data;
                    $('#projectName').val(settings.project_name || '');
                    $('#timeZone').val(settings.time_zone || 'IST');
                    $('#language').val(settings.default_language || 'en');
                    $('#siteUrl').val(settings.site_url || '');
                    $('#contactInfo').val(settings.contact_info || '');
                    $('#siteDescription').val(settings.site_description || '');
                    // Update logo links and buttons
                    if (settings.logo_path) {
                        var logoUrl = '/storage/' + settings.logo_path;
                        $('#logoLivePreview').attr('src', logoUrl).show();
                        $('#viewLogoLink').attr('href', logoUrl).show();
                        $('#downloadLogoLink').attr('href', logoUrl).show();
                        $('#deleteLogoBtn').show();
                        initializeViewer();
                    } else {
                        $('#logoLivePreview').hide().attr('src', '');
                        $('#viewLogoLink').hide();
                        $('#downloadLogoLink').hide();
                        $('#deleteLogoBtn').hide();
                        if (viewer) viewer.destroy();
                        $('#imageViewer').hide();
                    }
                } else {
                    alert(response.message || 'No settings found');
                }
            },
            error: function(xhr) {
                alert('Error fetching settings: ' + xhr.responseText);
            }
        });
    });

    // Trigger view/zoom (reinitialize viewer if needed)
    $('#viewLogoLink').on('click', function(e) {
        e.preventDefault();
        initializeViewer();
    });

    // Download logo
    $('#downloadLogoLink').on('click', function(e) {
        e.preventDefault();
        var link = $(this).attr('href');
        window.open(link, '_blank');
    });

    // Delete logo
    $('#deleteLogoBtn').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete the logo?')) {
            $.ajax({
                url: '/settings/delete-logo',
                type: 'POST',
                data: { _token: $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    alert('Logo deleted successfully!');
                    $('#logoLivePreview').hide().attr('src', '');
                    $('#viewLogoLink').hide();
                    $('#downloadLogoLink').hide();
                    $('#deleteLogoBtn').hide();
                    if (viewer) viewer.destroy();
                    $('#imageViewer').hide();
                },
                error: function(xhr) {
                    alert('Error deleting logo: ' + xhr.responseText);
                }
            });
        }
    });

    // Live preview of uploaded logo
    $('#logo').on('change', function() {
        var input = this;
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#logoLivePreview').attr('src', e.target.result).show();
                initializeViewer();
            };
            reader.readAsDataURL(input.files[0]);
        }
    });


    function fetchCompanies(event) {
        const button = event.currentTarget;
        $.ajax({
            url: "company",
            method: "GET",
            dataType: "json",
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            success: function (response) {
                if (response.success) {
                    const tbody = $("#database table tbody");
                    tbody.empty();

                    response.data.forEach(function (company) {
                        const row = `
                        <tr>
                            <td>${company.id}</td>
                            <td>${company.company_name}</td>
                            <td>${company.company_code}</td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#configModal"
                                    data-company-id="${company.id}"
                                    data-company-name="${company.company_name}">
                                    <i class="ri-add-circle-fill"></i> Config
                                </button>
                            </td>
                        </tr>
                    `;
                        tbody.append(row);
                    });
                } else {
                    console.error(
                        "Failed to fetch companies:",
                        response.message
                    );
                    alert("Failed to fetch companies: " + response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error:", status, error);
                alert("An error occurred while fetching companies.");
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    }

    function fetchCompanyConfig(companyId, event) {
        const button = event.currentTarget;
        $.ajax({
            url: `get-company-config/${companyId}`,
            method: "GET",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            dataType: "json",
            beforeSend: function () {
                startLoader({
                    currentTarget: button,
                });
            },
            success: function (response) {
                if (response.success) {
                    if (response.data.hrims) {
                        const hrims = response.data.hrims;
                        $("#hrims_db_connection").val(
                            hrims.db_connection || "mysql"
                        );
                        $("#hrims_db_host").val(hrims.db_host || "127.0.0.1");
                        $("#hrims_db_port").val(hrims.db_port || "3306");
                        $("#hrims_db_database").val(
                            hrims.db_database || "hrims"
                        );
                        $("#hrims_db_username").val(
                            hrims.db_username || "root"
                        );
                        $("#hrims_db_password").val(hrims.db_password || "");
                        $("#hrims #is_active").prop(
                            "checked",
                            hrims.status == 1
                        );
                        $("#hrims #is_active_label").text(
                            hrims.status == 1 ? "Active" : "Inactive"
                        );
                    }

                    if (response.data.expense) {
                        const expense = response.data.expense;
                        $("#expense_db_connection").val(
                            expense.db_connection || "mysql"
                        );
                        $("#expense_db_host").val(
                            expense.db_host || "127.0.0.1"
                        );
                        $("#expense_db_port").val(expense.db_port || "3306");
                        $("#expense_db_database").val(
                            expense.db_database || "expense"
                        );
                        $("#expense_db_username").val(
                            expense.db_username || "root"
                        );
                        $("#expense_db_password").val(
                            expense.db_password || ""
                        );
                        $("#expense #is_active").prop(
                            "checked",
                            expense.status == 1
                        );
                        $("#expense #is_active_label").text(
                            expense.status == 1 ? "Active" : "Inactive"
                        );
                    }
                } else {
                    console.error("Failed to fetch config:", response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error:", status, error);
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    }

    function saveCompanyConfig(formData, button) {
        $.ajax({
            url: "save-config",
            method: "POST",
            data: JSON.stringify(formData),
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
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
                        `${formData.db_name.toUpperCase()} configuration saved successfully!`
                    );
                    $("#configModal").modal("hide");
                } else {
                    showAlert(
                        "danger",
                        "ri-error-warning-line",
                        response.message || "An error occurred while saving."
                    );
                }
            },
            error: function (xhr, status, error) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    response.message ||
                        "An error occurred while saving the configuration."
                );
            },
            complete: function () {
                endLoader({
                    currentTarget: button,
                });
            },
        });
    }
});
