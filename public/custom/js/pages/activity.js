$(document).ready(function () {
    // Load default tab content (categories)
    loadTabContent('categories');

    // Handle tab clicks
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const tab = $(e.target).data('tab');
        loadTabContent(tab);
    });

    function loadTabContent(tab) {
        $.ajax({
            url: "activity/tab-content",
            type: "GET",
            data: { tab: tab },
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                $(`#${tab}`).html(response);
                // Reinitialize DataTables for the loaded content
                $(`#${tab} .table`).DataTable({
                    ordering: false,
                    searching: true,
                    paging: true,
                    info: true,
                    lengthChange: true,
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                });
                // Reinitialize Select2 for any select elements
                $(`#${tab} .form-select`).select2({
                    placeholder: "Select an option",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(`#${tab} .modal`)
                });
            },
            error: function (xhr) {
                console.error(`Failed to load ${tab} content:`, xhr);
                showAlert("danger", "ri-error-warning-line", `Failed to load ${tab} content.`);
            },
        });
    }
});