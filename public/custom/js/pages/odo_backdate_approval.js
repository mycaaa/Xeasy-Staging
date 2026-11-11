$(document).ready(function () {
    flatpickr(".effective-date-input", {
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d-m-Y",
        maxDate: "today",
    });

    // Store original values for reset
    $(".is-active-switch, .approval-select, .delayed-day-select, input[name^='verticals']").each(function () {
        $(this).data(
            "original-value",
            $(this).is(":checkbox") ? $(this).is(":checked") : $(this).val()
        );
    });

    $(".save-btn").on("click", function () {
        var departmentId = $(this).data("department-id");
        var $row = $('tr[data-department-id="' + departmentId + '"]');
        var $checkbox = $row.find(".is-active-switch");
        var $select = $row.find(".approval-select");
        var $delayedDay = $row.find(".delayed-day-select");
        var isActive = $checkbox.is(":checked") ? 1 : 0;
        var effectiveDate = $row.find(".effective-date-input").val();
        var approvalType = $select.val();

        var delayedDay = $delayedDay.val();

        // Collect selected verticals
        var verticals = $row.find("input[name='verticals[" + departmentId + "][]']:checked")
                           .map(function() { return parseInt($(this).val()); }).get();

        if (isActive) {
            // Validation
            if (!approvalType) {
                showAlert("danger", "ri-error-warning-line", "Approval Type is required when Enable Check is selected.");
                return;
            }
            if (!effectiveDate) {
                showAlert("danger", "ri-error-warning-line", "Effective Date is required when Enable Check is selected.");
                return;
            }
            if (!delayedDay) {
                showAlert("danger", "ri-error-warning-line", "Delayed Day is required when Enable Check is selected.");
                return;
            }
            if (verticals.length === 0) {
                showAlert("danger", "ri-error-warning-line", "At least one vertical must be selected when Enable Check is selected.");
                return;
            }
        }

        $("#confirmSaveModal").modal("show");
        $("#confirmSaveBtn")
            .off("click")
            .on("click", function () {
                saveSetting(
                    this,
                    departmentId,
                    isActive,
                    approvalType,
                    delayedDay,
                    effectiveDate,
                    verticals,
                    $row,
                    $checkbox,
                    $select,
                    $delayedDay
                );
                $("#confirmSaveModal").modal("hide");
            });
    });

    // Reset modal
    $("#confirmSaveModal").on("hidden.bs.modal", function () {
        var departmentId = $("#confirmSaveBtn").data("department-id");
        if (!departmentId) return;

        var $row = $('tr[data-department-id="' + departmentId + '"]');
        var $checkbox = $row.find(".is-active-switch");
        var $select = $row.find(".approval-select");
        var $delayedDay = $row.find(".delayed-day-select");
        var $verticals = $row.find("input[name='verticals[" + departmentId + "][]']");

        $checkbox.prop("checked", $checkbox.data("original-value"));
        $select.val($select.data("original-value"));
        $delayedDay.val($delayedDay.data("original-value"));
        $verticals.each(function() {
            $(this).prop("checked", $(this).data("original-value"));
        });
    });

    function saveSetting(buttonEl, departmentId, isActive, approvalType, delayedDay, effectiveDate, verticals, $row, $checkbox, $select, $delayedDay) {
        var data = {
            is_active: isActive,
            approval_type: approvalType,
            effective_date: effectiveDate,
            delayed_day: delayedDay,
            verticals: verticals
        };

        $.ajax({
            url: "odo-backdate-setting/" + departmentId,
            type: "POST",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            data: data,
            beforeSend: function () {
                startLoader({ currentTarget: buttonEl });
            },
            success: function (response) {
                showAlert("success", "ri-checkbox-circle-line", response.message || "Setting updated successfully!");
                $checkbox.data("original-value", isActive);
                $select.data("original-value", approvalType);
                $delayedDay.data("original-value", delayedDay);
                $row.find("input[name='verticals[" + departmentId + "][]']").each(function() {
                    $(this).data("original-value", $(this).is(":checked"));
                });
            },
            error: function (xhr) {
                var errorMsg = xhr.responseJSON?.message || "An error occurred";
                showAlert("danger", "ri-error-warning-line", errorMsg);
                $checkbox.prop("checked", $checkbox.data("original-value"));
                $select.val($select.data("original-value"));
                $delayedDay.val($delayedDay.data("original-value"));
                $row.find("input[name='verticals[" + departmentId + "][]']").each(function() {
                    $(this).prop("checked", $(this).data("original-value"));
                });
            },
            complete: function () {
                endLoader({ currentTarget: buttonEl });
            },
        });
    }
});
