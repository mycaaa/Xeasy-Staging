$(document).ready(function () {
    flatpickr(".effective-from-input, .effective-to-input", {
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d-m-Y",
        maxDate: "today" // Prevent future dates for effective_from
    });

    // Store original values for reset
    $(".is-active-switch, .auto-approve-select, .monthly-km-input, .effective-from-input, .effective-to-input, input[name^='verticals']").each(function () {
        $(this).data(
            "original-value",
            $(this).is(":checkbox") ? $(this).is(":checked") : $(this).val()
        );
    });

    // Real-time validation for KM input
    $(".monthly-km-input").on("input", function() {
        var value = $(this).val();
        var $this = $(this);
        
        // Remove any existing validation classes
        $this.removeClass("is-invalid is-valid");
        
        if (value && value.trim() !== '') {
            var kmValue = parseInt(value);
            if (isNaN(kmValue) || kmValue < 1) {
                $this.addClass("is-invalid");
                showTooltipError($this, "Must be a positive number");
            } else if (kmValue > 999999) {
                $this.addClass("is-invalid");
                showTooltipError($this, "Cannot exceed 999,999");
            } else {
                $this.addClass("is-valid");
                hideTooltipError($this);
            }
        } else {
            hideTooltipError($this);
        }
    });

    // Date validation for effective dates
    $(".effective-from-input").on("change", function() {
        var $this = $(this);
        var $toInput = $this.closest("tr").find(".effective-to-input");
        var fromDate = new Date($this.val());
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        $this.removeClass("is-invalid is-valid");
        
        if ($this.val()) {
            if (fromDate > today) {
                $this.addClass("is-invalid");
                showTooltipError($this, "Cannot be in the future");
            } else {
                $this.addClass("is-valid");
                hideTooltipError($this);
                
                // Update effective_to minimum date
                if ($toInput.length) {
                    var fp = $toInput[0]._flatpickr;
                    if (fp) {
                        fp.set('minDate', $this.val());
                    }
                }
            }
        }
    });

    $(".effective-to-input").on("change", function() {
        var $this = $(this);
        var $fromInput = $this.closest("tr").find(".effective-from-input");
        var toDate = new Date($this.val());
        var fromDate = new Date($fromInput.val());
        
        $this.removeClass("is-invalid is-valid");
        
        if ($this.val() && $fromInput.val()) {
            if (toDate <= fromDate) {
                $this.addClass("is-invalid");
                showTooltipError($this, "Must be after Effective From date");
            } else {
                $this.addClass("is-valid");
                hideTooltipError($this);
            }
        }
    });

    // Helper functions for validation tooltips
    function showTooltipError($element, message) {
        hideTooltipError($element);
        $element.attr("title", message);
        $element.tooltip({
            placement: "top",
            trigger: "manual"
        }).tooltip("show");
    }

    function hideTooltipError($element) {
        if ($element.attr("data-bs-original-title")) {
            $element.tooltip("dispose");
        }
        $element.removeAttr("title");
    }

    $(".save-btn").on("click", function () {
        var departmentId = $(this).data("department-id");
        var $row = $('tr[data-department-id="' + departmentId + '"]');
        var $checkbox = $row.find(".is-active-switch");
        var $autoApprove = $row.find(".auto-approve-select");
        var $monthlyKm = $row.find(".monthly-km-input");
        var $effectiveFrom = $row.find(".effective-from-input");
        var $effectiveTo = $row.find(".effective-to-input");
        
        var isActive = $checkbox.is(":checked") ? 1 : 0;
        var autoApprove = $autoApprove.val();
        var monthlyKmLimit = $monthlyKm.val();
        var effectiveFrom = $effectiveFrom.val();
        var effectiveTo = $effectiveTo.val();

        // Collect selected verticals
        var verticals = $row.find("input[name='verticals[" + departmentId + "][]']:checked")
                           .map(function() { return parseInt($(this).val()); }).get();

        // Comprehensive validation
        var validationErrors = [];

        if (isActive) {
            // All fields are required when department is active (except Effective To)
            
            // Effective From is required
            if (!effectiveFrom) {
                validationErrors.push("Effective From date is required when department is active.");
            }

            // Monthly KM Limit is required
            if (!monthlyKmLimit || monthlyKmLimit.trim() === '') {
                validationErrors.push("Monthly KM limit is required when department is active.");
            }

            // At least one vertical must be selected
            if (verticals.length === 0) {
                validationErrors.push("At least one vertical must be selected when department is active.");
            }

            // Date validations
            if (effectiveFrom) {
                var fromDate = new Date(effectiveFrom);
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (fromDate > today) {
                    validationErrors.push("Effective From date cannot be in the future.");
                }
            }

            if (effectiveTo && effectiveFrom) {
                var fromDate = new Date(effectiveFrom);
                var toDate = new Date(effectiveTo);
                
                if (toDate <= fromDate) {
                    validationErrors.push("Effective To date must be after Effective From date.");
                }
            }

            // KM limit validation (when provided)
            if (monthlyKmLimit && monthlyKmLimit.trim() !== '') {
                var kmValue = parseInt(monthlyKmLimit);
                if (isNaN(kmValue) || kmValue < 1) {
                    validationErrors.push("Monthly KM limit must be a positive number.");
                } else if (kmValue > 999999) {
                    validationErrors.push("Monthly KM limit cannot exceed 999,999.");
                }
            }

            // Business logic warnings
            if (monthlyKmLimit && autoApprove === 'N') {
                // This is a warning, not an error
                console.warn("KM limit is set but auto-approve is disabled for department " + departmentId);
            }
        }

        // Monthly KM validation (even when inactive)
        if (monthlyKmLimit && monthlyKmLimit.trim() !== '') {
            var kmValue = parseInt(monthlyKmLimit);
            if (isNaN(kmValue) || kmValue < 1) {
                validationErrors.push("Monthly KM limit must be a positive number if specified.");
            } else if (kmValue > 999999) {
                validationErrors.push("Monthly KM limit cannot exceed 999,999.");
            }
        }

        // Show validation errors
        if (validationErrors.length > 0) {
            var errorMessage = validationErrors.join("<br>");
            showAlert("danger", "ri-error-warning-line", errorMessage);
            return;
        }

        // Show confirmation modal
        $("#confirmSaveModal").modal("show");
        $("#confirmSaveBtn")
            .off("click")
            .on("click", function () {
                saveSetting(
                    this,
                    departmentId,
                    isActive,
                    autoApprove,
                    monthlyKmLimit,
                    effectiveFrom,
                    effectiveTo,
                    verticals,
                    $row,
                    $checkbox,
                    $autoApprove,
                    $monthlyKm,
                    $effectiveFrom,
                    $effectiveTo
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
        var $autoApprove = $row.find(".auto-approve-select");
        var $monthlyKm = $row.find(".monthly-km-input");
        var $effectiveFrom = $row.find(".effective-from-input");
        var $effectiveTo = $row.find(".effective-to-input");
        var $verticals = $row.find("input[name='verticals[" + departmentId + "][]']");

        $checkbox.prop("checked", $checkbox.data("original-value"));
        $autoApprove.val($autoApprove.data("original-value"));
        $monthlyKm.val($monthlyKm.data("original-value"));
        $effectiveFrom.val($effectiveFrom.data("original-value"));
        $effectiveTo.val($effectiveTo.data("original-value"));
        $verticals.each(function() {
            $(this).prop("checked", $(this).data("original-value"));
        });
    });

    function saveSetting(buttonEl, departmentId, isActive, autoApprove, monthlyKmLimit, effectiveFrom, effectiveTo, verticals, $row, $checkbox, $autoApprove, $monthlyKm, $effectiveFrom, $effectiveTo) {
        var data = {
            is_active: isActive,
            auto_approve_if_below_limit: autoApprove,
            monthly_km_limit: monthlyKmLimit || null,
            effective_from: effectiveFrom,
            effective_to: effectiveTo || null,
            dept_active: isActive ? 'Y' : 'N',
            verticals: verticals
        };

        $.ajax({
            url: "access-running-km-setting/" + departmentId,
            type: "POST",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            data: data,
            beforeSend: function () {
                startLoader({ currentTarget: buttonEl });
            },
            success: function (response) {
                showAlert("success", "ri-checkbox-circle-line", response.message || "Access running KM setting updated successfully!");
                $checkbox.data("original-value", isActive);
                $autoApprove.data("original-value", autoApprove);
                $monthlyKm.data("original-value", monthlyKmLimit);
                $effectiveFrom.data("original-value", effectiveFrom);
                $effectiveTo.data("original-value", effectiveTo);
                $row.find("input[name='verticals[" + departmentId + "][]']").each(function() {
                    $(this).data("original-value", $(this).is(":checked"));
                });
            },
            error: function (xhr) {
                var errorMsg = "An error occurred";
                
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        // Handle validation errors
                        var errors = [];
                        for (var field in xhr.responseJSON.errors) {
                            errors = errors.concat(xhr.responseJSON.errors[field]);
                        }
                        errorMsg = errors.join("<br>");
                    }
                }
                
                showAlert("danger", "ri-error-warning-line", errorMsg);
                
                // Reset form to original values
                $checkbox.prop("checked", $checkbox.data("original-value"));
                $autoApprove.val($autoApprove.data("original-value"));
                $monthlyKm.val($monthlyKm.data("original-value"));
                $effectiveFrom.val($effectiveFrom.data("original-value"));
                $effectiveTo.val($effectiveTo.data("original-value"));
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