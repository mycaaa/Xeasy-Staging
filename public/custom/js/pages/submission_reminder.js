function stageRender(data, row) {
    const sendDay = parseInt(row.conditions?.send_day || row.debugger?.send_day || "", 10);
    function ordinalSuffix(n) {
        if (!n) return "";
        const j = n % 10, k = n % 100;
        if (j === 1 && k !== 11) return n + "st";
        if (j === 2 && k !== 12) return n + "nd";
        if (j === 3 && k !== 13) return n + "rd";
        return n + "th";
    }
    const day = ordinalSuffix(sendDay);
    if (data == 1) return `<span class="badge bg-primary-subtle text-primary">First Reminder \u2013 ${day}</span>`;
    if (data == 2) return `<span class="badge bg-warning-subtle text-warning">Second Reminder \u2013 ${day}</span>`;
    if (data == 3) return `<span class="badge bg-danger-subtle text-danger">Final Reminder \u2013 ${day}</span>`;
    return '<span class="badge bg-secondary">-</span>';
}

const commonColumns = [
    { data: null, render: (data, type, row, meta) => meta.row + 1 },
    { data: "employee_name" },
    { data: "email" },
    { data: "month" },
    { data: "status" },
    { data: "stage", className: "text-center", render: (data, type, row) => stageRender(data, row) },
    { data: "email_template_subject" },
    { data: "conditions.already_sent" },
];

$(document).ready(function () {
    $("#submissionReminderTable").DataTable({
        ordering: true, searching: true, paging: true, info: true,
        lengthChange: true, pageLength: 10, lengthMenu: [10, 25, 50, 100],
        ajax: {
            url: "/submission-reminder/emails?preview=1",
            type: "GET",
            dataSrc: (json) => json.data || [],
            error: (xhr) => alert("Failed to load data: " + (xhr.responseJSON?.message || "Unknown error")),
        },
        columns: commonColumns,
    });

    $("#hardcopyReminderTable").DataTable({
        ordering: true, searching: true, paging: true, info: true,
        lengthChange: true, pageLength: 10, lengthMenu: [10, 25, 50, 100],
        ajax: {
            url: "/receiving-pending/emails?preview=1",
            type: "GET",
            dataSrc: (json) => json.data || [],
            error: (xhr) => alert("Failed to load data: " + (xhr.responseJSON?.message || "Unknown error")),
        },
        columns: commonColumns,
    });

    // Escalation table: initialize with deferLoading so AJAX only fires when tab is first opened
    let escalationLoaded = false;

    $("#escalationTable").DataTable({
        ordering: true, searching: true, paging: true, info: true,
        lengthChange: true, pageLength: 10, lengthMenu: [10, 25, 50, 100],
        deferLoading: 0,
        ajax: {
            url: "/hardcopy-escalation/emails?preview=1",
            type: "GET",
            dataSrc: (json) => json.data || [],
            error: (xhr) => alert("Failed to load escalation data: " + (xhr.responseJSON?.message || "Unknown error")),
        },
        columns: [
            { data: null, render: (data, type, row, meta) => meta.row + 1 },
            { data: "manager_email" },
            { data: "employee_name" },
            { data: "employee_id" },
            { data: "month" },
            { data: "submission_date" },
            { data: "stage", className: "text-center", render: (data, type, row) => stageRender(data, row) },
            { data: "email_template_subject" },
            { data: "conditions.already_sent" },
        ],
    });

    $('#hardcopy-tab').on('shown.bs.tab', function () {
        $("#hardcopyReminderTable").DataTable().columns.adjust().draw();
    });

    $('#escalation-tab').on('shown.bs.tab', function () {
        const dt = $("#escalationTable").DataTable();
        if (!escalationLoaded) {
            escalationLoaded = true;
            dt.ajax.reload();
        }
        dt.columns.adjust();
    });

    $("#sendEmails").click(function (e) {
        e.preventDefault();
        $("#sendEmailsModal").modal("show");
    });

    $("#confirmSendEmailsBtn").click(function (e) {
        e.preventDefault();
        const button = e.currentTarget;
        $.ajax({
            url: "/submission-reminder/send",
            type: "POST",
            headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") },
            dataType: "json",
            beforeSend: () => startLoader({ currentTarget: button }),
            success: function (response) {
                $("#sendEmailsModal").modal("hide");
                if (response.success) {
                    showAlert("success", "ri-checkbox-circle-line", response.message || "Reminder emails sent successfully!");
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showAlert("danger", "ri-error-warning-line", response.message || "An error occurred while sending emails.");
                }
            },
            error: function (xhr) {
                $("#sendEmailsModal").modal("hide");
                showAlert("danger", "ri-error-warning-line", xhr.responseJSON?.message || "Failed to send reminder emails.");
            },
            complete: () => endLoader({ currentTarget: button }),
        });
    });
});
