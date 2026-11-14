/* APPR-RO-01 (13 Nov 2026): view-only claim detail (approver screens / dashboard). Every field of the data entry
 * form (and anything added to it later, such as the participant editor) is disabled, the claim type cannot be
 * switched, documents cannot be uploaded, and the save / action buttons are hidden. Viewing, zooming and paging
 * through the documents still work. The server refuses changes from these users anyway (claim transitions). */
(function () {
    let observer = null;
    function lock(root) {
        $(root).find("input, select, textarea, button").not(".ro-keep, [name=view_mode], .chat-input, [id*=chat], [id*=Chat]").each(function () {
            const $el = $(this);
            if ($el.is("button") && !$el.is("[type=submit], .btn-success, .btn-danger, .btn-warning, .btn-primary, .btn-info, [id*=save], [id*=Save], [id*=add], [id*=Add], [id*=remove], [id*=Remove], [class*=remove], [class*=delete], [class*=add-]")) { return; }
            $el.prop("disabled", true).attr("data-ro-locked", "1");
        });
        $(root).find("[contenteditable=true]").attr("contenteditable", "false").attr("data-ro-locked", "1");
    }
    window.xeasyClaimReadOnly = function (on) {
        const $m = $("#claimDetailModal");
        if (!$m.length) { return; }
        if (observer) { observer.disconnect(); observer = null; }
        $m.toggleClass("claim-view-only", !!on);
        $m.find("#claimImageUploadWrapper").toggleClass("d-none", !!on);
        $m.find("#sltClaimTypeList").prop("disabled", !!on);
        $m.find("#saveBtn, #denyBtn, #holdBtn, #draftBtn, #filledBtn, #modalDeactivateClaimBtn, #revertUploadedBtn, #modalRevertDeactivatedBtn").toggleClass("d-none", !!on);
        $m.find(".claim-ro-badge").remove();
        if (!on) {
            $m.find("[data-ro-locked]").prop("disabled", false).removeAttr("data-ro-locked");
            return;
        }
        $m.find(".modal-title").first().append('<span class="claim-ro-badge badge bg-secondary ms-2" title="Approvers see the documents and the punched figures; changes are made by data entry or the verifier">View only</span>');
        const form = document.getElementById("dataEntryForm");
        lock(form);
        $m.find(".modal-body .form-section, #paxEditorContainer, [id^=pax], [id*=Pax]").each(function () { lock(this); });
        if (form && window.MutationObserver) {
            observer = new MutationObserver(function () { lock(form); });
            observer.observe(form, { childList: true, subtree: true });
        }
    };
    $(document).on("hidden.bs.modal", "#claimDetailModal", function () { if (observer) { observer.disconnect(); observer = null; } });
})();

$(document).ready(function () {
    let uploadedFiles = [];
    let currentFileIndex = 0;
    let currentZoom = 1;
    let currentRotation = 0;
    let translateX = 0;
    let translateY = 0;
    let isDragging = false;
    const baseUrl = window.location.origin;
    let claimTypeCgMap = {}; // claimId -> cgId, populated by claimTypeList()

    // Helper functions for universally opening/closing modals with Bootstrap 5 and jQuery fallbacks
    function showModal(selectorOrEl) {
        const el = typeof selectorOrEl === "string" ? document.querySelector(selectorOrEl) : (selectorOrEl && selectorOrEl.jquery ? selectorOrEl[0] : selectorOrEl);
        if (!el) return;
        if (typeof bootstrap !== "undefined" && bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        } else if (typeof $ !== "undefined" && typeof $.fn.modal === "function") {
            $(el).modal("show");
        }
    }

    function hideModal(selectorOrEl) {
        const el = typeof selectorOrEl === "string" ? document.querySelector(selectorOrEl) : (selectorOrEl && selectorOrEl.jquery ? selectorOrEl[0] : selectorOrEl);
        if (!el) return;
        if (typeof bootstrap !== "undefined" && bootstrap && bootstrap.Modal) {
            const inst = bootstrap.Modal.getInstance(el) || bootstrap.Modal.getOrCreateInstance(el);
            inst.hide();
        } else if (typeof $ !== "undefined" && typeof $.fn.modal === "function") {
            $(el).modal("hide");
        }
    }

    function loadDefaultFiles() {
        uploadedFiles = [];
        currentFileIndex = 0;
        currentZoom = 1;
        currentRotation = 0;
        translateX = translateY = 0;
        displayThumbnails();
        if (uploadedFiles.length > 0) {
            showFile(0);
        } else {
            $("#mainViewer").html(
                '<div class="text-center text-muted">No files available.</div>'
            );
        }
    }

    function displayThumbnails() {
        const thumbnailList = $("#thumbnailList");
        thumbnailList.empty();

        if (uploadedFiles.length === 0) {
            thumbnailList.append(
                '<div class="text-center text-muted">No files to display.</div>'
            );
            return;
        }

        const shortCodeCounts = {};
        uploadedFiles.forEach((file) => {
            const shortCode = file.short_code || "Document";
            shortCodeCounts[shortCode] = (shortCodeCounts[shortCode] || 0) + 1;
        });

        const shortCodeIndices = {};
        uploadedFiles.forEach((file, index) => {
            let displayName = file.short_code || "Document";
            if (shortCodeCounts[displayName] > 1) {
                shortCodeIndices[displayName] =
                    (shortCodeIndices[displayName] || 0) + 1;
                displayName = `${displayName} (${shortCodeIndices[displayName]})`;
            }

            const thumbnail = $("<div>").addClass("thumbnail-container");

            if (file.type.startsWith("image/")) {
                const img = $("<img>")
                    .addClass("thumbnail-item")
                    .attr("data-index", index)
                    .attr("src", file.url)
                    .attr("alt", file.name);
                thumbnail.append(img);
                thumbnail.append(
                    $("<div>").addClass("thumbnail-label").text(displayName)
                );
            } else if (file.type === "application/pdf") {
                const pdfThumb = $("<div>")
                    .addClass("thumbnail-item pdf-thumbnail")
                    .attr("data-index", index)
                    .html('<i class="ri-file-pdf-line"></i>');
                thumbnail.append(pdfThumb);
                thumbnail.append(
                    $("<div>").addClass("thumbnail-label").text(displayName)
                );
            }

            thumbnailList.append(thumbnail);
        });

        if (uploadedFiles.length > 0) {
            thumbnailList.find('[data-index="0"]').addClass("active");
        }
        updateNavigationButtons();
    }

    $(document).on("click", ".thumbnail-item", function () {
        const index = parseInt($(this).attr("data-index"));
        showFile(index);
        updateActiveState(index);
    });

    function updateActiveState(index) {
        $(".thumbnail-item").removeClass("active");
        $(`.thumbnail-item[data-index="${index}"]`).addClass("active");
        currentFileIndex = index;
        updateNavigationButtons();
    }

    function showFile(index) {
        if (index >= uploadedFiles.length || index < 0) return;

        const file = uploadedFiles[index];
        const mainViewer = $("#mainViewer");

        mainViewer.html(`
            <div class="loading-spinner">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);

        updateFileInfo(file, index);

        currentZoom = 1;
        currentRotation = 0;
        translateX = translateY = 0;

        if (file.type.startsWith("image/")) {
            loadImage(file, mainViewer);
        } else if (file.type === "application/pdf") {
            loadPDF(file, mainViewer);
        }
    }

    function loadImage(file, container) {


        container.html(`
            <div class="image-container" id="imageContainer">
                <img src="${file.url}" id="mainImage" alt="${file.name}">
            </div>
            <div class="viewer-controls">
                <button class="control-btn" id="zoomOut" title="Zoom Out"><i class="ri-zoom-out-line"></i></button>
                <div class="zoom-display" id="zoomDisplay">100%</div>
                <button class="control-btn" id="zoomIn" title="Zoom In"><i class="ri-zoom-in-line"></i></button>
                <button class="control-btn" id="zoomReset" title="Reset"><i class="ri-refresh-line"></i></button>
                <div class="control-divider"></div>
                <button class="control-btn" id="rotateLeft" title="Rotate Left"><i class="ri-anticlockwise-line"></i></button>
                <button class="control-btn" id="rotateRight" title="Rotate Right"><i class="ri-clockwise-line"></i></button>
                <div class="control-divider"></div>
                <button class="control-btn" id="downloadImage" title="Download"><i class="ri-download-line"></i></button>
                <button class="control-btn" id="openImage" title="Open in New Tab"><i class="ri-external-link-line"></i></button>
            </div>
        `);


        $("#mainImage").on("error", function () {
            $(this).closest(".image-container").html(
                `<div class="text-center p-4">Preview not available for this image format. <a href="${file.url}" target="_blank">Download</a></div>`
            );


            $(".viewer-controls").hide();
        });

        updateImageTransform();
        setupImageInteractions();
    }

    function loadPDF(file, container) {
        container.html(`
            <div style="width:100%;height:100%;">
                <iframe src="${file.url}" class="pdf-viewer"></iframe>
            </div>
            <div class="viewer-controls">
                <button class="control-btn" id="downloadPDF"><i class="ri-download-line"></i></button>
                <button class="control-btn" id="openPDF"><i class="ri-external-link-line"></i></button>
            </div>
        `);
    }

    function updateImageTransform() {
        const $img = $("#mainImage");
        $img.css({
            transform: `translate(${translateX}px, ${translateY}px) scale(${currentZoom}) rotate(${currentRotation}deg)`,
            transformOrigin: "center center",
        });
        $("#zoomDisplay").text(`${Math.round(currentZoom * 100)}%`);
    }

    function setupImageInteractions() {
        const $container = $("#imageContainer");

        $container.off(
            "wheel mousedown mousemove mouseup mouseleave touchstart touchmove touchend dblclick"
        );

        $container.on("wheel", function (e) {
            e.preventDefault();
            const delta = e.originalEvent.deltaY;
            if (delta > 0) {
                currentZoom = Math.max(currentZoom * 0.9, 0.3);
            } else {
                currentZoom = Math.min(currentZoom * 1.1, 10);
            }
            updateImageTransform();
        });

        $container
            .on("mousedown", function (e) {
                if (e.which !== 1 || currentZoom <= 1) return;
                isDragging = true;
                startX = e.clientX - translateX;
                startY = e.clientY - translateY;
                $container.addClass("dragging");
                e.preventDefault();
            })
            .on("mousemove", function (e) {
                if (!isDragging) return;
                translateX = e.clientX - startX;
                translateY = e.clientY - startY;
                updateImageTransform();
            })
            .on("mouseup mouseleave", function () {
                if (isDragging) {
                    isDragging = false;
                    $container.removeClass("dragging");
                }
            });

        $container
            .on("touchstart", function (e) {
                if (currentZoom <= 1) return;
                const touch = e.originalEvent.touches[0];
                startX = touch.clientX - translateX;
                startY = touch.clientY - translateY;
                isDragging = true;
            })
            .on("touchmove", function (e) {
                if (!isDragging) return;
                const touch = e.originalEvent.touches[0];
                translateX = touch.clientX - startX;
                translateY = touch.clientY - startY;
                updateImageTransform();
                e.preventDefault();
            })
            .on("touchend touchcancel", function () {
                isDragging = false;
            });

        $container.on("dblclick", function () {
            currentZoom = 1;
            currentRotation = 0;
            translateX = translateY = 0;
            updateImageTransform();
        });
    }

    $(document).on("click", "#zoomIn", function () {
        currentZoom = Math.min(currentZoom * 1.25, 10);
        updateImageTransform();
    });

    $(document).on("click", "#zoomOut", function () {
        currentZoom = Math.max(currentZoom * 0.8, 0.3);
        updateImageTransform();
    });

    $(document).on("click", "#zoomReset", function () {
        currentZoom = 1;
        currentRotation = 0;
        translateX = translateY = 0;
        updateImageTransform();
    });

    $(document).on("click", "#rotateLeft", function () {
        currentRotation -= 90;
        updateImageTransform();
    });

    $(document).on("click", "#rotateRight", function () {
        currentRotation += 90;
        updateImageTransform();
    });

    $(document).on("click", "#downloadImage, #downloadPDF", function () {
        const file = uploadedFiles[currentFileIndex];
        const link = document.createElement("a");
        link.href = file.url;
        link.download = file.name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    $(document).on("click", "#openImage, #openPDF", function () {
        const file = uploadedFiles[currentFileIndex];
        window.open(file.url, "_blank");
    });

    $("#prevBtn").on("click", function () {
        if (currentFileIndex > 0) {
            showFile(currentFileIndex - 1);
            updateActiveState(currentFileIndex - 1);
        }
    });

    $("#nextBtn").on("click", function () {
        if (currentFileIndex < uploadedFiles.length - 1) {
            showFile(currentFileIndex + 1);
            updateActiveState(currentFileIndex + 1);
        }
    });

    function updateNavigationButtons() {
        $("#prevBtn").prop("disabled", currentFileIndex === 0);
        $("#nextBtn").prop(
            "disabled",
            currentFileIndex === uploadedFiles.length - 1
        );
    }

    function updateFileInfo(file, index) {
        $("#fileName").text(file.is_payment_receipt ? `${file.short_code}: ${file.name}` : file.name);
        $("#fileType").text(file.type);
        $("#fileIndex").text(`${index + 1} of ${uploadedFiles.length}`);
    }

    $(document).on("keydown", function (e) {
        if ($("#claimDetailModal").hasClass("show")) {
            if (e.key === "ArrowLeft") $("#prevBtn").click();
            else if (e.key === "ArrowRight") $("#nextBtn").click();
            else if (e.key === "+" || e.key === "=") $("#zoomIn").click();
            else if (e.key === "-") $("#zoomOut").click();
            else if (e.key === "0") $("#zoomReset").click();
        }
    });

    $(document).on("click", ".upload-icon", function () {
        $(this).siblings(".file-input").trigger("click");
    });

    $(document).on("change", ".file-input", function () {
        const input = this;
        const fileListDiv = $(this).siblings(".file-list");
        const existingFiles = new Set(
            fileListDiv
                .find(".file-name")
                .map(function () {
                    return $(this).data("file");
                })
                .get()
        );

        Array.from(input.files).forEach((file) => {
            if (existingFiles.has(file.name)) {
                showAlert(
                    "danger",
                    "ri-error-warning-line",
                    `File "${file.name}" is already selected.`
                );
                return;
            }

            const fileDiv = $('<div class="d-flex align-items-center justify-content-between my-1 p-1 border rounded bg-white shadow-sm" style="min-width: 180px;"></div>');
            const fileNameLink = $('<span class="file-name text-truncate"></span>')
                .text(file.name)
                .attr("data-preloaded", 0)
                .attr("data-file", file.name)
                .attr("title", file.name)
                .css({ "cursor": "pointer", "max-width": "140px" })
                .on("click", function () {
                    const fileURL = URL.createObjectURL(file);
                    window.open(fileURL, "_blank");
                });

            const actionsDiv = $('<div class="d-flex align-items-center gap-1 ms-1"></div>');
            const switchBtn = $('<i class="ri-exchange-line switch-expense-head text-primary" title="Switch to another expense head" style="cursor: pointer; font-size: 15px;"></i>');
            const removeBtn = $('<i class="ri-delete-bin-line remove-file text-danger" title="Remove file" style="cursor: pointer; font-size: 15px;"></i>');
            removeBtn.on("click", function () {
                removeFile(input, file.name);
                fileDiv.remove();
            });

            actionsDiv.append(switchBtn, removeBtn);
            fileDiv.append(fileNameLink, actionsDiv);
            fileListDiv.append(fileDiv);
        });
    });

    $(document).on("click", ".remove-file", function () {
        const parentDiv = $(this).closest(".file-list > div");
        const fileName = parentDiv.find(".file-name").data("file") || parentDiv.find(".file-name").text().trim();
        const isPreloaded = parentDiv.find(".file-name").data("preloaded") == 1;
        const input = $(this)
            .closest(".file-upload-wrapper")
            .find(".file-input")[0];

        if (!confirm(`Are you sure you want to remove "${fileName}"?`)) return;

        if (isPreloaded) {
            parentDiv.attr("data-remove", "1").hide();
        } else {
            if (input) removeFile(input, fileName);
            parentDiv.remove();
        }
    });

    function removeFile(input, fileName) {
        const dt = new DataTransfer();
        Array.from(input.files).forEach((file) => {
            if (file.name !== fileName) dt.items.add(file);
        });
        input.files = dt.files;
    }

    window.updateTotals = function () {
        $("#totalAmount").val(numberFormat(sumInputs(".claim")));
        $("#totalVerified").val(numberFormat(sumInputs(".verified")));
        $("#totalApproved").val(numberFormat(sumInputs(".approved")));
        $("#totalFinance").val(numberFormat(sumInputs(".finance")));
    };

    function sumInputs(selector) {
        let total = 0;
        $(selector).each(function () {
            total += parseFloat($(this).val().replace(/,/g, "") || 0);
        });
        return total;
    }

    function numberFormat(num) {
        return num.toLocaleString("en-IN", {
            maximumFractionDigits: 2
        });
    }

    function parseDisplayDate(str) {
        if (!str) return null;
        const parts = str.split("-");
        if (parts.length === 3 && parts[2].length === 4) {
            return new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
        }
        return new Date(str);
    }

    function validateAndUpload(config, validateDuplicate = true) {
        const $btn = config.$btn;
        const claimid = $("#sltClaimTypeList").val();
        const cgid = $(".modal-ncgid").attr("data-modal-ncgid");
        const expid = $("#claimDetailModal").attr("data-modal-expid");
        const empid = $("#claimDetailModal").attr("data-modal-empid");

        $btn.prop("disabled", true).html(config.loadingHtml);


        const isDenyAction = config.actionUrl === "deny-claim";
        const {
            isValid,
            errorMessages,
            expensesData
        } =
            validateClaimForm(claimid, cgid, expid);

        if (!isValid && !isDenyAction) {
            showAlert("danger", "ri-error-warning-line", errorMessages.join("<br>"));
            $btn.prop("disabled", false).html(config.idleHtml);
            return;
        }


        const totalAmount = ($("#totalAmount").val() || "0").replace(/,/g, "");
        const totalVerified = ($("#totalVerified").val() || "0").replace(/,/g, "");
        const totalApproved = ($("#totalApproved").val() || "0").replace(/,/g, "");
        const totalFinance = ($("#totalFinance").val() || "0").replace(/,/g, "");
        const isFilled = $btn.data("is-filled");

        const isDataPunch = window.location.pathname.includes("data-punch");
        const isVerifier = window.location.pathname.includes("verify-");

        const uploadFormData = new FormData();
        uploadFormData.append("claimid", claimid);
        uploadFormData.append("cgid", cgid);
        uploadFormData.append("expid", expid);
        uploadFormData.append("empid", empid);
        uploadFormData.append("totalAmount", totalAmount);
        uploadFormData.append("totalVerified", totalVerified);
        uploadFormData.append("totalApproved", totalApproved);
        uploadFormData.append("totalFinance", totalFinance);
        uploadFormData.append("is_filled", isFilled);
        uploadFormData.append("is_data_punch", isDataPunch ? "1" : "0");
        uploadFormData.append("is_verifier", isVerifier ? "1" : "0");
        uploadFormData.append("is_validate_duplicate", validateDuplicate);

        $("#dataEntryForm").serializeArray().filter((item) => item.name !== "pax_details[]").forEach((item, index) => {
            uploadFormData.append(`formData[${index}][name]`, item.name);
            uploadFormData.append(`formData[${index}][value]`, item.value);
        });

        if (Array.isArray(expensesData) && expensesData.length > 0) {
            expensesData.forEach((expense, index) => {
                if (!expense) return;
                const fields = [
                    "label", "trial_no", "value",
                    "claim_amount",
                    "verified_amount", "verified_remark",
                    "approved_amount", "approved_remark",
                    "finance_amount", "finance_remark",
                ];
                fields.forEach(f =>
                    uploadFormData.append(`expensesData[${index}][${f}]`, expense[f] ?? "")
                );
                uploadFormData.append(
                    `expensesData[${index}][preloaded_files]`,
                    JSON.stringify(expense.preloaded_files ?? [])
                );
                (expense.new_files ?? []).forEach((file, fIndex) =>
                    uploadFormData.append(`expensesData[${index}][files][${fIndex}]`, file)
                );
            });
        }
        if (parseInt(claimid) === 1) {
            const paxValues = $("#pax_details").val() ?? [];
            let baseIndex = $("#dataEntryForm").serializeArray()
                .filter((item) => item.name !== "pax_details[]").length;

            paxValues.forEach((val) => {
                uploadFormData.append(`formData[${baseIndex}][name]`, "pax_details[]");
                uploadFormData.append(`formData[${baseIndex}][value]`, val);
                baseIndex++;
            });
        }
        if ([16, 17, 42].includes(parseInt(claimid))) {
            const particularsData = [];
            $("#items_table tbody tr").each(function () {
                const selectVal = $(this).find('select[name="particulars[]"]').val();
                const inputVal = $(this).find('input[name="particulars[]"]').val();
                const particulars = selectVal?.trim() ? selectVal : inputVal?.trim() ? inputVal : "";
                const remark = $(this).find('input[name="remark[]"]').val() || "";
                const amount = $(this).find('input[name="amount[]"]').val() || 0;
                if (particulars.trim() || remark.trim() || amount != 0)
                    particularsData.push({
                        particulars,
                        remark,
                        amount
                    });
            });
            particularsData.forEach((row, i) => {
                uploadFormData.append(`particularsData[${i}][particulars]`, row.particulars);
                uploadFormData.append(`particularsData[${i}][remark]`, row.remark);
                uploadFormData.append(`particularsData[${i}][amount]`, row.amount);
            });
        }


        $.ajax({
            url: `${baseUrl}/upload-claim-files`,
            type: "POST",
            data: uploadFormData,
            processData: false,
            contentType: false,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
            },
            beforeSend: () => startLoader({
                currentTarget: $btn[0]
            }),
            success(uploadRes) {
                // DUP-BILL-01: the same bill is already on another employee's claim
                if (uploadRes && uploadRes.data && uploadRes.data.duplicate_bill_warning) {
                    showAlert("warning", "ri-error-warning-line", uploadRes.data.duplicate_bill_warning);
                }

                // PUNCH-SAVE-01 (14 Nov 2026): Save only stores the data and files (no status action). It went on to post to
                // "<base>/null", and the page showed "The route null could not be found." after a good save.
                if (!config.actionUrl) {
                    if (uploadRes && uploadRes.success === false) {
                        showAlert("danger", "ri-error-warning-line", uploadRes.message || "Could not save.");
                    } else {
                        config.onSuccess(uploadRes || {}, expid, claimid, uploadRes);
                    }
                    endLoader({ currentTarget: $btn[0] });
                    $btn.prop("disabled", false).html(config.idleHtml);
                    return;
                }

                const actionFormData = new FormData();
                actionFormData.append("expid", expid);
                actionFormData.append("claimid", claimid);
                actionFormData.append("_token", $('meta[name="csrf-token"]').attr("content"));

                $.ajax({
                    url: `${baseUrl}/${config.actionUrl}`,
                    type: "POST",
                    data: actionFormData,
                    processData: false,
                    contentType: false,
                    success(res) {
                        if (res.success === false) {
                            showAlert("danger", "ri-error-warning-line", res.message);
                            return;
                        }
                        config.onSuccess(res, expid, claimid, uploadRes);
                    },
                    error(xhr) {
                        const messages = Array.isArray(xhr.responseJSON?.message) ?
                            xhr.responseJSON.message :
                            [xhr.responseJSON?.message || "An error occurred."];
                        showAlert("danger", "ri-error-warning-line", messages.join("<br>"));
                    },
                    complete() {
                        endLoader({
                            currentTarget: $btn[0]
                        });
                        $btn.prop("disabled", false).html(config.idleHtml);
                    },
                });
            },
            error(xhr) {
                const messages = Array.isArray(xhr.responseJSON?.message) ?
                    xhr.responseJSON.message :
                    [xhr.responseJSON?.message || "An error occurred."];
                showAlert("danger", "ri-error-warning-line", messages.join("<br>"));
                endLoader({
                    currentTarget: $btn[0]
                });
                $btn.prop("disabled", false).html(config.idleHtml);
            },
        });
    }

    function validateClaimForm(claimid, cgid, expid) {
        let isValid = true;
        let errorMessages = [];
        let expensesData = [];

        if (cgid == 7) {
            let totalClaimAmount = 0;
            let trialNo = $("#trial_no").length ?
                $("#trial_no").val().trim() :
                "";

            const requiredFields = [{
                id: "bill_date",
                name: "Bill Date"
            },
            {
                id: "activity_type",
                name: "Activity Type"
            },
            {
                id: "crops",
                name: "Crops"
            },
            ];

            requiredFields.forEach((f) => {
                const value = $(`#${f.id}`).val();
                if (!value || value.length === 0) {
                    isValid = false;
                    $(`#${f.id}`).css("border", "1px solid red");
                    errorMessages.push(`${f.name} is required.`);
                } else {
                    $(`#${f.id}`).css("border", "");
                }
            });

            if ($("#trial_no").length > 0 && !$("#trial_no").val().trim()) {
                isValid = false;
                $("#trial_no").css("border", "1px solid red");
                errorMessages.push("Trial No. is required.");
            } else {
                $("#trial_no").css("border", "");
            }

            $(".expense-table tbody tr").each(function () {
                const row = $(this);
                const headName = row.find("td:first").text().trim();
                if (!headName || headName === "Total:") return;

                const claimAmt = parseFloat(
                    row.find(".claim").val().replace(/,/g, "") || 0
                );
                totalClaimAmount += claimAmt;

                const fileInput = row.find(".file-input")[0];
                const preloadedFiles = [];
                const newFiles = [];

                row.find(".file-list .file-name").each(function () {
                    const fileName = $(this).data("file");
                    const isRemoved =
                        $(this).parent().attr("data-remove") == "1";
                    const isPreloaded = $(this).data("preloaded") == 1;
                    if (!isRemoved && isPreloaded)
                        preloadedFiles.push(fileName);
                });

                if (fileInput && fileInput.files.length > 0) {
                    Array.from(fileInput.files).forEach((file) =>
                        newFiles.push(file)
                    );
                }

                let rowValid = true;
                const totalFiles = preloadedFiles.length + newFiles.length;

                if (totalFiles > 0 && claimAmt <= 0) {
                    rowValid = false;
                    errorMessages.push(
                        `Claim amount must be > 0 for "${headName}" because file is selected.`
                    );
                }

                if (claimAmt > 0 && totalFiles === 0) {
                    rowValid = false;
                    errorMessages.push(
                        `At least one file is required for "${headName}" because claim amount is > 0.`
                    );
                }

                const verAmtInput = row.find(".verified");
                const verRemarkInput = row.find("td").eq(4).find("input");

                const appAmtInput = row.find(".approved");
                const appRemarkInput = row.find("td").eq(6).find("input");

                const finAmtInput = row.find(".finance");
                const finRemarkInput = row.find("td").eq(8).find("input");

                const verAmt = verAmtInput.length ?
                    parseFloat(verAmtInput.val().replace(/,/g, "") || 0) :
                    null;
                const appAmt = appAmtInput.length ?
                    parseFloat(appAmtInput.val().replace(/,/g, "") || 0) :
                    null;
                const finAmt = finAmtInput.length ?
                    parseFloat(finAmtInput.val().replace(/,/g, "") || 0) :
                    null;

                const verRemark = verRemarkInput.length ?
                    verRemarkInput.val().trim() :
                    "";
                const appRemark = appRemarkInput.length ?
                    appRemarkInput.val().trim() :
                    "";
                const finRemark = finRemarkInput.length ?
                    finRemarkInput.val().trim() :
                    "";

                if (verAmtInput.length && verAmt !== claimAmt) {
                    if (!verRemark) {
                        isValid = false;
                        rowValid = false;
                        verRemarkInput.css("border", "1px solid red");
                        errorMessages.push(
                            `Verified Remark is required for "${headName}" because Verified Amount (${verAmt}) ≠ Claim Amount (${claimAmt}).`
                        );
                    } else {
                        verRemarkInput.css("border", "");
                    }
                }
                if (appAmtInput.length && appAmt !== verAmt) {
                    if (!appRemark) {
                        isValid = false;
                        rowValid = false;
                        appRemarkInput.css("border", "1px solid red");

                        errorMessages.push(
                            `Approved Remark is required for "${headName}" because Approved Amount (${appAmt}) ≠ Verified Amount (${verAmt}).`
                        );
                    } else {
                        appRemarkInput.css("border", "");
                    }
                }

                if (finAmtInput.length && finAmt !== appAmt) {
                    if (!finRemark) {
                        isValid = false;
                        rowValid = false;
                        finRemarkInput.css("border", "1px solid red");

                        errorMessages.push(
                            `Finance Remark is required for "${headName}" because Finance Amount (${finAmt}) ≠ Approved Amount (${appAmt}).`
                        );
                    } else {
                        finRemarkInput.css("border", "");
                    }
                }

                row.css("background-color", rowValid ? "" : "#f8d7da");
                if (!rowValid) isValid = false;

                expensesData.push({
                    label: headName,
                    value: expid,
                    trial_no: trialNo,
                    claim_amount: claimAmt,
                    verified_amount: verAmt,
                    verified_remark: verRemark,
                    approved_amount: appAmt,
                    approved_remark: appRemark,
                    finance_amount: finAmt,
                    finance_remark: finRemark,
                    preloaded_files: preloadedFiles,
                    new_files: newFiles,
                });
            });

            if (totalClaimAmount <= 0) {
                isValid = false;
                errorMessages.push(
                    "Total claim amount must be greater than 0."
                );
                $("#totalAmount").css("border", "1px solid red");
            } else {
                $("#totalAmount").css("border", "");
            }
        } else if (cgid == 1 && claimid == 1) {

            // Required field definitions matching the form
            const requiredFields = [
                { id: "billing_person", name: "Billing Person" },
                { id: "city_category_select", name: "City - Category" },
                { id: "hotel_name_select", name: "Hotel Name" },
                { id: "bill_date", name: "Bill Date" },
                { id: "arrival_datetime", name: "Arrival Date / Time" },
                { id: "departure_datetime", name: "Departure Date / Time" },
                { id: "payment_mode", name: "Payment Mode" },
                { id: "totalAmount", name: "Total Amount" },
            ];

            // Only validate PAX No if Group Lodging toggle is ON
            const isGroupLodgingOn = ($("#modalGroupLodgingCheckbox").length > 0 && $("#modalGroupLodgingCheckbox").is(":checked")) ||
                                     ($("#lodgingGroupCheckbox").length > 0 && $("#lodgingGroupCheckbox").is(":checked"));
            if (isGroupLodgingOn) {
                const paxInputId = $("#modalLodgingPaxInput").length > 0 ? "modalLodgingPaxInput" : ($("#lodgingPaxInput").length > 0 ? "lodgingPaxInput" : "pax_count");
                requiredFields.push({ id: paxInputId, name: "PAX No." });
            }

            requiredFields.forEach((f) => {
                const $el = $(`#${f.id}`);
                if ($el.length === 0) return;
                const value = $el.val()?.trim?.() ?? $el.val();
                if (!value || value.length === 0) {
                    isValid = false;
                    $el.css("border", "1px solid red");
                    errorMessages.push(`${f.name} is required.`);
                } else {
                    $el.css("border", "");
                }
            });

            // If "Other" city selected, require the free-text city name
            const citySelectVal = $("#city_category_select").val();
            if (citySelectVal === "0") {
                const otherCity = $("#other_city_name").val()?.trim();
                if (!otherCity) {
                    isValid = false;
                    $("#other_city_name").css("border", "1px solid red");
                    errorMessages.push("City Name is required when 'Other' is selected.");
                } else {
                    $("#other_city_name").css("border", "");
                }
            }

            // If "Other" hotel selected, require the free-text hotel name
            const hotelSelectVal = $("#hotel_name_select").val();
            if (hotelSelectVal === "0") {
                const otherHotel = $("#other_hotel_name").val()?.trim();
                if (!otherHotel) {
                    isValid = false;
                    $("#other_hotel_name").css("border", "1px solid red");
                    errorMessages.push("Hotel Name is required when 'Other' is selected.");
                } else {
                    $("#other_hotel_name").css("border", "");
                }
            }

            // Arrival must be before Departure
            const arrival = $("#arrival_datetime").val();
            const departure = $("#departure_datetime").val();
            if (arrival && departure) {
                const arrDate = new Date(arrival);
                const depDate = new Date(departure);
                if (depDate <= arrDate) {
                    isValid = false;
                    errorMessages.push("Departure Date / Time must be after Arrival Date / Time.");
                }
            }



            // Total amount must be > 0
            const totalClaimValue = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalClaimValue || totalClaimValue <= 0) {
                isValid = false;
                $("#totalAmount").css("border", "1px solid red");
                errorMessages.push("Total Amount must be greater than 0.");
            } else {
                $("#totalAmount").css("border", "");
            }
        } else if (cgid == 1 && claimid == 2) {
            const requiredFields = [{
                id: "agent_name",
                name: "Agent Name",
                type: "select"
            },
            {
                id: "pnr_number",
                name: "PNR Number",
                type: "text"
            },
            {
                id: "booking_date",
                name: "Booking Date",
                type: "text"
            },
            {
                id: "journey_date",
                name: "Journey Date",
                type: "text"
            },
            {
                id: "journey_from",
                name: "Journey From",
                type: "select"
            },
            {
                id: "journey_upto",
                name: "Journey Upto",
                type: "select"
            },
            ];

            requiredFields.forEach((f) => {
                const $el = $(`#${f.id}`);
                let value = $el.val();
                if (f.type === "select") value = value || "";
                if (!value || value.trim().length === 0) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                }
            });

            if (!$("input[name='travel_class']:checked").val()) {
                isValid = false;
                errorMessages.push("Travel Class is required.");
            }

            const travelInsurance = $(
                "input[name='travel_insurance']:checked"
            ).val();
            if (!travelInsurance) {
                isValid = false;
                errorMessages.push("Travel Insurance selection is required.");
            }

            if (travelInsurance === "1") {
                const amount = parseFloat($("#insurance_amount").val());
                if (!amount || amount <= 0) {
                    isValid = false;
                    errorMessages.push(
                        "Insurance Amount must be greater than 0."
                    );
                }
            }

            const flightNumber = $("#flight_number").val()?.trim();
            if (flightNumber && flightNumber.length > 100) {
                isValid = false;
                errorMessages.push("Flight Name/Number is too long.");
            }

            const periodDays = parseInt($("#period_days").val() || 0);
            if (periodDays < 0) {
                isValid = false;
                errorMessages.push(
                    "Period between Booking and Journey Date cannot be negative."
                );
            }

            const totalClaimValue = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalClaimValue || totalClaimValue <= 0) {
                isValid = false;
                errorMessages.push("Amount must be greater than 0.");
            }
        } else if (cgid == 1 && claimid == 3) {
            const requiredFields = [{
                id: "Mode",
                name: "Mode",
                type: "select"
            },
            {
                id: "journey_from",
                name: "Journey From",
                type: "select"
            },
            {
                id: "journey_upto",
                name: "Journey Upto",
                type: "select"
            },
            ];

            requiredFields.forEach((f) => {
                const $el = $(`#${f.id}`);
                let value = $el.val();
                if (f.type === "select") value = value || "";
                if (!value || value.trim().length === 0) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                }
            });

            const totalClaimValue = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalClaimValue || totalClaimValue <= 0) {
                isValid = false;
                errorMessages.push("Amount must be greater than 0.");
            }
        } else if (cgid == 1 && claimid == 4) {
            const requiredFields = [{
                id: "billing_person",
                name: "Billing Person"
            },
            {
                id: "billing_address",
                name: "Billing Address"
            },
            {
                id: "agency_name",
                name: "Agency Name"
            },
            {
                id: "agency_address",
                name: "Agency Address"
            },
            {
                id: "date_of_travel",
                name: "Bill Date"
            },
            {
                id: "journey_start",
                name: "Journey Start"
            },
            {
                id: "journey_end",
                name: "Journey End"
            },
            {
                id: "charges_type",
                name: "Charges Type"
            },
            {
                id: "vehicle_class",
                name: "Vehicle Class"
            },
            {
                id: "totalAmount",
                name: "Total Amount"
            },
            ];

            requiredFields.forEach((f) => {
                const $el = $(`#${f.id}`);
                const value = $el.val()?.trim();
                if (!value || value.length === 0) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                }
            });

            const dateOfTravel = parseDisplayDate($("#date_of_travel").val());
            const today = new Date();
            if (dateOfTravel && dateOfTravel > today) {
                isValid = false;
                errorMessages.push("Bill Date cannot be a future date.");
            }

            const start = $("#journey_start").val();
            const end = $("#journey_end").val();
            if ((!start && end) || (start && !end)) {
                isValid = false;
                errorMessages.push(
                    "Both Journey Start and Journey End are required."
                );
            }

            const totalAmount = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalAmount || totalAmount <= 0) {
                isValid = false;
                errorMessages.push("Total Amount must be greater than 0.");
            }
            const invoiceNo = $("#invoice_no").val()?.trim();
            if (invoiceNo && invoiceNo.length > 100) {
                isValid = false;
                errorMessages.push("Invoice No. is too long.");
            }
        } else if (cgid == 1 && claimid == 7) {
            const requiredFields = [{
                id: "travel_date",
                name: "Travel Date"
            },
            {
                id: "vehicle_reg",
                name: "Vehicle Reg. No."
            },
            {
                id: "totalAmount",
                name: "Amount"
            },
            ];

            requiredFields.forEach((f) => {
                const value = $(`#${f.id}`).val()?.trim();
                if (!value || value.length === 0) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                }
            });

            const travelDate = $("#travel_date").val()?.trim();
            if (travelDate) {
                const enteredDate = parseDisplayDate(travelDate);
                const today = new Date();
                if (enteredDate > today) {
                    isValid = false;
                    errorMessages.push("Travel Date cannot be a future date.");
                }
            }

            const totalAmount = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalAmount || totalAmount <= 0) {
                isValid = false;
                errorMessages.push("Amount must be greater than 0.");
            }

            const vehicleReg = $("#vehicle_reg").val()?.trim();
            if (vehicleReg && vehicleReg.length > 50) {
                isValid = false;
                errorMessages.push("Vehicle Reg. No. is too long.");
            }

            const multiDay = $("input[name='multi_day_trip']:checked").val();
            if (multiDay === "yes") {
                const tripStart = $("#trip_started_on").val()?.trim();
                const tripEnd = $("#trip_ended_on").val()?.trim();

                if (!tripStart) {
                    isValid = false;
                    errorMessages.push(
                        "Trip Started On is required when multi-day travel is Yes."
                    );
                }
                if (!tripEnd) {
                    isValid = false;
                    errorMessages.push(
                        "Trip Ended On is required when multi-day travel is Yes."
                    );
                }
                if (tripStart && tripEnd) {
                    const startDate = new Date(tripStart);
                    const endDate = new Date(tripEnd);
                    const today = new Date();

                    if (endDate < startDate) {
                        isValid = false;
                        errorMessages.push(
                            "Trip Ended On cannot be earlier than Trip Started On."
                        );
                    }
                    if (startDate > today || endDate > today) {
                        isValid = false;
                        errorMessages.push(
                            "Trip dates cannot be in the future."
                        );
                    }
                }
            }
        } else if (cgid == 1 && claimid == 9) {
            const requiredFields = [{
                id: "person",
                name: "Person"
            },
            {
                id: "bill_date",
                name: "Bill Date"
            },
            {
                id: "totalAmount",
                name: "Amount"
            },
            ];

            requiredFields.forEach((f) => {
                const value = $(`#${f.id}`).val()?.trim();
                if (!value || value.length === 0) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                    $(`#${f.id}`).css("border", "1px solid red");
                } else {
                    $(`#${f.id}`).css("border", "");
                }
            });

            const totalAmount = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalAmount || totalAmount <= 0) {
                isValid = false;
                errorMessages.push("Amount must be greater than 0.");
            }
        } else if (cgid == 1 && claimid == 14) {
            const requiredFields = [{
                id: "agent_name",
                name: "Agent Name",
                type: "select"
            },
            {
                id: "pnr_number",
                name: "PNR Number",
                type: "text"
            },
            {
                id: "booking_date",
                name: "Booking Date",
                type: "text"
            },
            {
                id: "journey_date",
                name: "Journey Date",
                type: "text"
            },
            {
                id: "journey_from",
                name: "Journey From",
                type: "select"
            },
            {
                id: "journey_upto",
                name: "Journey Upto",
                type: "select"
            },
            {
                id: "travel_class",
                name: "Class",
                type: "select"
            },
            {
                id: "booking_confirmed",
                name: "Booking Status",
                type: "radio",
            },
            ];

            requiredFields.forEach((f) => {
                const $el = $(`#${f.id}`);
                let value = $el.val();
                if (f.type === "select") value = value || "";
                if (!value || value.trim().length === 0) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                }
            });

            const travelInsurance = $(
                "input[name='travel_insurance']:checked"
            ).val();
            if (!travelInsurance) {
                isValid = false;
                errorMessages.push("Travel Insurance selection is required.");
            }

            if (travelInsurance === "1") {
                const amount = parseFloat($("#insurance_amount").val());
                if (!amount || amount <= 0) {
                    isValid = false;
                    errorMessages.push(
                        "Insurance Amount must be greater than 0."
                    );
                }
            }

            const flightNumber = $("#flight_number").val()?.trim();
            if (flightNumber && flightNumber.length > 100) {
                isValid = false;
                errorMessages.push("Train Name/Number is too long.");
            }

            const periodDays = parseInt($("#period_days").val() || 0);
            if (periodDays < 0) {
                isValid = false;
                errorMessages.push(
                    "Period between Booking and Journey Date cannot be negative."
                );
            }

            const totalClaimValue = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalClaimValue || totalClaimValue <= 0) {
                isValid = false;
                errorMessages.push("Amount must be greater than 0.");
            }
        } else if (cgid == 1 && claimid == 15) {
            const requiredFields = [{
                id: "agent_name",
                name: "Agent Name",
                type: "select"
            },
            {
                id: "pnr_number",
                name: "PNR Number",
                type: "text"
            },
            {
                id: "booking_date",
                name: "Booking Date",
                type: "text"
            },
            {
                id: "journey_date",
                name: "Journey Date",
                type: "text"
            },
            {
                id: "journey_from",
                name: "Journey From",
                type: "select"
            },
            {
                id: "journey_upto",
                name: "Journey Upto",
                type: "select"
            },
            ];

            requiredFields.forEach((f) => {
                const $el = $(`#${f.id}`);
                let value = $el.val();
                if (f.type === "select") value = value || "";
                if (!value || value.trim().length === 0) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                }
            });

            if (!$("input[name='travel_class']:checked").val()) {
                isValid = false;
                errorMessages.push("Travel Class is required.");
            }

            const travelInsurance = $(
                "input[name='travel_insurance']:checked"
            ).val();
            if (!travelInsurance) {
                isValid = false;
                errorMessages.push("Travel Insurance selection is required.");
            }

            if (travelInsurance === "1") {
                const amount = parseFloat($("#insurance_amount").val());
                if (!amount || amount <= 0) {
                    isValid = false;
                    errorMessages.push(
                        "Insurance Amount must be greater than 0."
                    );
                }
            }

            const flightNumber = $("#flight_number").val()?.trim();
            if (flightNumber && flightNumber.length > 100) {
                isValid = false;
                errorMessages.push("Bus Name/Number is too long.");
            }

            const periodDays = parseInt($("#period_days").val() || 0);
            if (periodDays < 0) {
                isValid = false;
                errorMessages.push(
                    "Period between Booking and Journey Date cannot be negative."
                );
            }

            const totalClaimValue = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalClaimValue || totalClaimValue <= 0) {
                isValid = false;
                errorMessages.push("Amount must be greater than 0.");
            }
        } else if (
            cgid == 1 &&
            (claimid == 16 || claimid == 17 || claimid == 42)
        ) {
            const billDate = $("#bill_date").val()?.trim();
            if (!billDate) {
                isValid = false;
                errorMessages.push("Bill Date is required.");
            }

            // Only validate PAX No if Group Meal toggle is ON
            if (claimid == 17) {
                const isGroupMealOn = ($("#modalGroupMealCheckbox").length > 0 && $("#modalGroupMealCheckbox").is(":checked")) ||
                                      ($("#groupMealCheckbox").length > 0 && $("#groupMealCheckbox").is(":checked"));
                if (isGroupMealOn) {
                    const mealPaxVal = $("#modalMealPaxInput").length > 0 ? $("#modalMealPaxInput").val()?.trim() : $("#mealPaxInput").val()?.trim();
                    if (!mealPaxVal) {
                        isValid = false;
                        errorMessages.push("PAX No. is required for Group Meal claim.");
                    }
                }
            }

            let totalAmount = 0;
            let hasValidRow = false;

            $("#items_table tbody tr").each(function (index) {
                const $row = $(this);

                let particular = "";
                const $particularSelect = $row.find(
                    "select[name='particulars[]']"
                );
                const $particularInput = $row.find(
                    "input[name='particulars[]']"
                );

                if ($particularSelect.length > 0) {
                    particular = $particularSelect.val()?.trim() || "";
                } else if ($particularInput.length > 0) {
                    particular = $particularInput.val()?.trim() || "";
                }

                const remark =
                    $row.find("input[name='remark[]']").val()?.trim() || "";
                const amount =
                    parseFloat($row.find("input[name='amount[]']").val()) || 0;

                if (particular || remark || amount > 0) {
                    hasValidRow = true;

                    if (!particular) {
                        isValid = false;
                        errorMessages.push(
                            `Particular is required in row ${index + 1}.`
                        );
                    }

                    if (amount <= 0) {
                        isValid = false;
                        errorMessages.push(
                            `Amount must be greater than 0 in row ${index + 1}.`
                        );
                    }

                    expensesData.push({
                        particular,
                        remark,
                        amount
                    });
                }

                totalAmount += amount;
            });

            if (!hasValidRow) {
                isValid = false;
                errorMessages.push("At least one item row is required.");
            }

            if (totalAmount <= 0) {
                isValid = false;
                errorMessages.push("Total Amount must be greater than 0.");
            }
        } else if (cgid == 1 && claimid == 18) {
            const requiredFields = [{
                id: "booking_date",
                name: "Booking Date"
            },
            {
                id: "journey_from",
                name: "Journey From"
            },
            {
                id: "journey_upto",
                name: "Journey Upto"
            },
            {
                id: "journey_start",
                name: "Journey Start Date"
            },
            {
                id: "journey_end",
                name: "Journey End Date"
            },
            ];

            requiredFields.forEach((f) => {
                const value = $(`#${f.id}`).val();
                if (!value || value.trim().length === 0) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                }
            });

            const startDate = parseDisplayDate($("#journey_start").val());
            const endDate = parseDisplayDate($("#journey_end").val());
            if (startDate && endDate && startDate > endDate) {
                isValid = false;
                errorMessages.push(
                    "Journey End Date must be after Journey Start Date."
                );
            }

            const bookingDate = parseDisplayDate($("#booking_date").val());
            if (bookingDate && startDate && startDate < bookingDate) {
                isValid = false;
                errorMessages.push(
                    "Journey Start Date cannot be before Booking Date."
                );
            }
        } else if (cgid == 1 && (claimid == 19 || claimid == 20)) {
            const requiredFields = [{
                id: "bill_date",
                name: "Bill Date"
            }];

            requiredFields.forEach((f) => {
                const value = $(`#${f.id}`).val()?.trim();
                if (!value) {
                    isValid = false;
                    errorMessages.push(`${f.name} is required.`);
                }
            });

            const totalClaimValue = parseFloat(
                $("#totalAmount").val().replace(/,/g, "") || 0
            );
            if (!totalClaimValue || totalClaimValue <= 0) {
                isValid = false;
                errorMessages.push("Amount must be greater than 0.");
            }
        }

        return {
            isValid,
            errorMessages,
            expensesData
        };
    }

    $("#saveBtn").on("click", function (e) {
        e.preventDefault();
        validateAndUpload({
            $btn: $(this),
            actionUrl: null,
            loadingHtml: '<i class="ri-loader-2-line spinner"></i> Saving...',
            idleHtml: '<i class="ri-save-line me-1"></i>Save',
            onSuccess(res, expid, claimid) {
                showAlert("success", "ri-checkbox-circle-line",
                    res.message || "Data and files uploaded successfully!");
                claimTypeList(claimid);
                fetchUploadedFiles(expid, claimid);
                if ($.fn.DataTable.isDataTable("#claimReportTable")) {
                    $("#claimReportTable").DataTable().ajax.reload(null, false);
                }
            },
        }, false);
    });


    $("#holdBtn").on("click", function () {
        validateAndUpload({
            $btn: $(this),
            actionUrl: "hold-claim",
            loadingHtml: '<i class="ri-loader-2-line spinner"></i> Holding...',
            idleHtml: '<i class="ri-pause-line me-1"></i>Hold',
            onSuccess(res, expid) {
                showAlert("success", "ri-pause-circle-line", res.message);
                $("#claimDetailModal").modal("hide");
                updateStatusBadgeAndRemoveRow(expid, res.data.holdCount, res.data.draftCount);
            },
        }, true);
    });


    $("#draftBtn").on("click", function () {
        validateAndUpload({
            $btn: $(this),
            actionUrl: "draft-claim",
            loadingHtml: '<i class="ri-loader-2-line spinner"></i> Saving Draft...',
            idleHtml: '<i class="ri-draft-line me-1"></i>Draft',
            onSuccess(res, expid) {
                showAlert("success", "ri-draft-fill", res.message);
                $("#claimDetailModal").modal("hide");
                updateStatusBadgeAndRemoveRow(expid, res.data.holdCount, res.data.draftCount);
            },
        }, true);
    });


    $("#filledBtn").on("click", function () {
        validateAndUpload({
            $btn: $(this),
            actionUrl: "filled-claim",
            loadingHtml: '<i class="ri-loader-2-line spinner"></i> Marking Filled...',
            idleHtml: '<i class="ri-checkbox-circle-line me-1"></i>Filled',
            onSuccess(res, expid) {
                showAlert("success", "ri-checkbox-circle-line", res.message);
                $("#claimDetailModal").modal("hide");
                const row = $(`tr[data-row-id="${expid}"]`);
                row.fadeOut(300, () => row.remove());
                if (res.data.filledCount !== undefined)
                    $(".status-btn.filled span").last().text(`(${res.data.filledCount})`);
                if (res.data.uploadedCount !== undefined)
                    $(".status-btn.uploaded span").last().text(`(${res.data.uploadedCount})`);
                if (res.data.holdCount !== undefined)
                    $(".status-btn.hold span").last().text(`(${res.data.holdCount})`);
            },
        }, true);
    });

    $("#denyBtn").on("click", function () {
        if (!confirm("Are you sure you want to Deny this claim?")) {
            return;
        }
        validateAndUpload({
            $btn: $(this),
            actionUrl: "deny-claim",
            loadingHtml: '<i class="ri-loader-2-line spinner"></i> Denying...',
            idleHtml: '<i class="ri-close-circle-line me-1"></i>Deny',
            onSuccess(res, expid) {
                showAlert("warning", "ri-close-circle-line", res.message || "Claim has been Denied.");
                $("#claimDetailModal").modal("hide");
                const row = $(`tr[data-row-id="${expid}"]`);
                row.fadeOut(300, () => row.remove());
                if (res.data.filledCount !== undefined)
                    $(".status-btn.filled span").last().text(`(${res.data.filledCount})`);
                if (res.data.uploadedCount !== undefined)
                    $(".status-btn.uploaded span").last().text(`(${res.data.uploadedCount})`);
                if (res.data.holdCount !== undefined)
                    $(".status-btn.hold span").last().text(`(${res.data.holdCount})`);
                if (res.data.draftCount !== undefined)
                    $(".status-btn.draft span").last().text(`(${res.data.draftCount})`);
                if (res.data.deniedCount !== undefined)
                    $(".status-btn.denied span").last().text(`(${res.data.deniedCount})`);
            },
        }, false);
    });

    $("#revertUploadedBtn").on("click", function () {
        if (!confirm("Are you sure you want to Revert this denied claim back to Uploaded status?")) {
            return;
        }
        const $btn = $(this);
        const claimid = $("#sltClaimTypeList").val();
        const expid = $("#claimDetailModal").attr("data-modal-expid");
        $btn.prop("disabled", true).html('<i class="ri-loader-2-line spinner"></i> Reverting...');

        $.ajax({
            url: `${baseUrl}/revert-uploaded-claim`,
            type: "POST",
            data: {
                _token: $('meta[name="csrf-token"]').attr("content"),
                expid: expid,
                claimid: claimid,
            },
            success(res) {
                showAlert("success", "ri-checkbox-circle-line", res.message || "Claim reverted to Uploaded status.");
                $("#claimDetailModal").modal("hide");
                const row = $(`tr[data-row-id="${expid}"]`);
                row.fadeOut(300, () => row.remove());
                if (res.data?.filledCount !== undefined)
                    $(".status-btn.filled span").last().text(`(${res.data.filledCount})`);
                if (res.data?.uploadedCount !== undefined)
                    $(".status-btn.uploaded span").last().text(`(${res.data.uploadedCount})`);
                if (res.data?.holdCount !== undefined)
                    $(".status-btn.hold span").last().text(`(${res.data.holdCount})`);
                if (res.data?.draftCount !== undefined)
                    $(".status-btn.draft span").last().text(`(${res.data.draftCount})`);
                if (res.data?.deniedCount !== undefined)
                    $(".status-btn.denied span").last().text(`(${res.data.deniedCount})`);
                if ($.fn.DataTable.isDataTable("#claimReportTable")) {
                    $("#claimReportTable").DataTable().ajax.reload(null, false);
                }
            },
            error(xhr) {
                const msg = xhr.responseJSON?.message || "Failed to revert claim.";
                showAlert("danger", "ri-error-warning-line", msg);
            },
            complete() {
                $btn.prop("disabled", false).html('<i class="ri-arrow-go-back-line me-1"></i>Revert to Uploaded');
            }
        });
    });

    $(document).on("click", ".revert-claim", function (e) {
        e.preventDefault();
        const expid = $(this).data("expid");
        const claimid = $(this).data("claim-id");
        if (!confirm("Are you sure you want to revert this claim (ExpId: " + expid + ") back to Uploaded status?")) {
            return;
        }
        const $btn = $(this);
        $btn.prop("disabled", true);

        $.ajax({
            url: `${baseUrl}/revert-uploaded-claim`,
            type: "POST",
            data: {
                _token: $('meta[name="csrf-token"]').attr("content"),
                expid: expid,
                claimid: claimid,
            },
            success(res) {
                showAlert("success", "ri-checkbox-circle-line", res.message || "Claim reverted to Uploaded status.");
                if ($.fn.DataTable.isDataTable("#claimReportTable")) {
                    $("#claimReportTable").DataTable().ajax.reload(null, false);
                }
                if (res.data?.filledCount !== undefined)
                    $(".status-btn.filled span").last().text(`(${res.data.filledCount})`);
                if (res.data?.uploadedCount !== undefined)
                    $(".status-btn.uploaded span").last().text(`(${res.data.uploadedCount})`);
                if (res.data?.holdCount !== undefined)
                    $(".status-btn.hold span").last().text(`(${res.data.holdCount})`);
                if (res.data?.draftCount !== undefined)
                    $(".status-btn.draft span").last().text(`(${res.data.draftCount})`);
                if (res.data?.deniedCount !== undefined)
                    $(".status-btn.denied span").last().text(`(${res.data.deniedCount})`);
            },
            error(xhr) {
                const msg = xhr.responseJSON?.message || "Failed to revert claim.";
                showAlert("danger", "ri-error-warning-line", msg);
                $btn.prop("disabled", false);
            }
        });
    });

    $("#claimDetailModal").on("hidden.bs.modal", function () {
        $("#dataEntryForm")[0].reset();
        currentFileIndex = 0;
        currentZoom = 1;
        currentRotation = 0;
        translateX = translateY = 0;
        $("#imageContainer").off();
    });

    function updateStatusBadgeAndRemoveRow(
        expid,
        holdCount = null,
        draftCount = null
    ) {
        const $row = $(`#claimReportTable tbody tr[data-row-id="${expid}"]`);
        if ($row.length) {
            $("#claimReportTable").DataTable().row($row).remove().draw(false);
        }
        if (holdCount !== null)
            $(".status-btn.hold span").last().text(`(${holdCount})`);
        if (draftCount !== null)
            $(".status-btn.draft span").last().text(`(${draftCount})`);
    }

    function claimTypeList(id = null) {
        const apiUrl = `${baseUrl}/get-claim-types`;
        $.ajax({
            url: apiUrl,
            method: "GET",
            contentType: "application/json",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                let $select = $("#sltClaimTypeList");
                $select.empty();

                // If no data, show a placeholder option but still allow loadClaimDetail to work
                if (!response.success || !response.data || response.data.length === 0) {
                    $select.append('<option value="">No types assigned</option>');
                    // Select2 with minimal config so the dropdown doesn't break
                    $select.select2({
                        dropdownParent: $("#claimDetailModal"),
                        width: "250px",
                        placeholder: "No types assigned",
                        allowClear: false,
                    });
                    return; // loadClaimDetail already called directly — nothing more to do here
                }

                claimTypeCgMap = {};
                response.data.forEach((group) => {
                    (group.children || []).forEach((item) => {
                        claimTypeCgMap[item.id] = parseInt(item.cgid);
                    });
                });

                $select.select2({
                    dropdownParent: $("#claimDetailModal"),
                    width: "250px",
                    placeholder: "Select Document Type",
                    allowClear: true,
                    data: response.data,
                });

                if (id) {
                    // Use change.select2 (not plain change) so our delegated handler
                    // (which would double-fire loadClaimDetail) is not triggered
                    $select.val(id).trigger("change.select2");
                }
            },
            error: function (xhr) {
                // Non-fatal — loadClaimDetail is already running independently
                console.warn("Failed to fetch claim types:", xhr.responseText || "Error");
            },
        });
    }

    function fetchUploadedFiles(expid, claim_id) {
        $.ajax({
            url: `${baseUrl}/get-uploaded-files/${expid}/${claim_id}`,
            type: "GET",
            dataType: "json",
            success: function (response) {
                processUploadedFiles(
                    response.data?.uploaded_files ?? null
                );
            },
            error: function () {
                uploadedFiles = [];
                displayThumbnails();
            },
        });
    }

    $(document).on("click", ".copy-icon", function () {
        const $icon = $(this);
        const expId = $("#expIdValue").text();
        if (!expId) return alert("Missing expense ID!");
        navigator.clipboard
            .writeText(expId)
            .then(() => {
                $icon.addClass("success");
                setTimeout(() => $icon.removeClass("success"), 800);
            })
            .catch(() => console.error("Copy failed"));
    });

    $(document).on("click", ".reload-icon", function () {
        const $icon = $(this);
        const $modal = $("#claimDetailModal");
        const claimId = $modal.attr("data-modal-claimid");
        const expId = $modal.attr("data-modal-expid");
        if (!claimId || !expId) return alert("Missing claim or expense ID!");
        $icon.addClass("success");
        setTimeout(() => $icon.removeClass("success"), 800);
        loadClaimDetail(claimId, expId, this);
    });

    function loadClaimDetail(claimid, expId, triggerBtn = null, loadFiles = true) {
        if (!claimid || !expId) {
            $("#dataEntryForm").html(
                '<div class="alert alert-warning">Please select a claim type and ensure ExpId is set.</div>'
            );
            return;
        }

        $.ajax({
            url: `${baseUrl}/claim-detail`,
            method: "GET",
            data: {
                claim_id: claimid,
                expid: expId
            },
            beforeSend: function () {
                if (triggerBtn) startLoader({
                    currentTarget: triggerBtn
                });
                $("#dataEntryForm").html(
                    '<div class="text-primary text-center py-3">Loading claim details...</div>'
                );
            },
            success: function (response) {
                try {
                    if (response.html) {
                        $("#dataEntryForm").html(response.html);
                        
                        // Initialize form after HTML insertion with delay to ensure DOM is ready
                        setTimeout(() => {
                            if (typeof window.initClaimDetailForm === 'function') {
                                window.initClaimDetailForm();
                                console.log('✅ Claim detail form initialized');
                            }
                            window.xeasyClaimReadOnly(String($("#claimDetailModal").attr("data-readonly")) === "1"); // APPR-RO-01
                        }, 100);
                    } else {
                        $("#dataEntryForm").html(
                            '<div class="alert alert-danger">No details found.</div>'
                        );
                    }
                } catch (error) {
                    console.error('❌ Error inserting claim detail HTML:', error);
                    $("#dataEntryForm").html(
                        '<div class="alert alert-danger">Error loading claim details: ' + error.message + '</div>'
                    );
                    return;
                }

                if (loadFiles && response.uploaded_files !== undefined) {
                    processUploadedFiles(response.uploaded_files);
                }
                
                // 🏨 AUTO-FILL PAX FIELDS WITH EXISTING DATA
                if (response.pax_data) {
                    const pax = response.pax_data;
                    const claimId = $("#claimDetailModal").attr("data-modal-claimid");
                    
                    if (claimId == 1) {  // Lodging - has enhanced PAX management
                        // Set the PAX count
                        if (pax.NoOfPAX) $("#pax_count").val(pax.NoOfPAX).trigger('change');
                        
                        // Initialize PAX participants (calls the new function in blade template)
                        if (typeof window.initializePaxFromResponse === 'function') {
                            window.initializePaxFromResponse(pax);
                            console.log('✅ PAX data initialized via new system');
                        } else {
                            // Fallback to old system if new one not available
                            if (pax.PaxDetails) {
                                const paxIds = pax.PaxDetails.split(',').filter(id => id.trim());
                                $("#pax_details").val(paxIds).trigger('change');
                            }
                            if (pax.PaxDetails_Other) $("#pax_details_other").val(pax.PaxDetails_Other);
                            console.log('⚠️ Using fallback PAX initialization');
                        }
                    } else if (claimId == 14) {  // Rail Fare - no PAX fields currently
                        console.log('ℹ️ Rail Fare form: PAX fields not implemented in this version');
                    } else if (claimId == 17) {  // Group Meal - no dedicated PAX fields
                        console.log('ℹ️ Meal form: PAX fields not implemented in this version');
                    } else if (claimId == 42) {  // General Expense - no PAX fields currently
                        console.log('ℹ️ General Expense form: PAX fields not implemented in this version');
                    }
                    
                    console.log('🏨 PAX data received from backend:', pax);
                }
            },
            error: function () {
                $("#dataEntryForm").html(
                    '<div class="alert alert-danger">Error loading claim details.</div>'
                );
            },
            complete: function () {
                if (triggerBtn) endLoader({
                    currentTarget: triggerBtn
                });
            },
        });
    }

    function processUploadedFiles(data) {
        uploadedFiles = [];

        if (!data) {
            displayThumbnails();
            return;
        }

        if (Array.isArray(data)) {
            uploadedFiles = data.map((file) => {
                const ext = file.file_path.split(".").pop().toLowerCase();
                let type = "application/octet-stream";
                if (["jpg","jpeg","png","gif","bmp","webp","heic"].includes(ext)) type = "image/" + ext;
                else if (ext === "pdf") type = "application/pdf";
                // UAT E-05: the lodging online payment receipt (UploadSequence 110) is labelled as such.
                const shortCode = file.is_payment_receipt
                    ? (file.file_label || "Online Payment Receipt")
                    : (file.file_path.split("_")[0] || "Document");
                return { url: file.file_url, name: file.file_path, type, short_code: shortCode, is_payment_receipt: !!file.is_payment_receipt };
            });
        } else {
            Object.keys(data).forEach((key) => {
                const category = data[key];
                const shortCode = category.short_code || "Document";
                (category.files || []).forEach((file) => {
                    const ext = file.file_path.split(".").pop().toLowerCase();
                    let type = "application/octet-stream";
                    if (["jpg","jpeg","png","gif","bmp","webp","heic"].includes(ext)) type = "image/" + ext;
                    else if (ext === "pdf") type = "application/pdf";
                    uploadedFiles.push({ url: file.file_url, name: file.file_path, type, short_code: shortCode });
                });
            });
        }

        displayThumbnails();
        if (uploadedFiles.length > 0) showFile(0);
        else $("#mainViewer").html('<div class="text-center text-muted">No files available.</div>');
    }

    $(document).on("click", ".view-claim", function (e) {
        e.preventDefault();
        // handled inline by main.js when inside eHomeModal
        if ($(this).closest("#eHomeModal").length) return;
        const claimid = $(this).data("claim-id");
        const expId = $(this).data("expid");
        const cgId = $(this).data("cgid");
        const empId = $(this).data("empid");
        const empName = $(this).data("empname") || "";
        const empCode = $(this).data("empcode") || "";
        const modal = $("#claimDetailModal");
        // Only remove stored jQuery data, not event listeners on the modal element itself
        modal.removeData();
        modal.find("#dataEntryForm").html("");
        modal.find(".file-list").empty();
        modal.find("#mainViewer").html('<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div> Loading files...</div>');
        // Reset the dropdown without triggering the change handler
        modal.find("#sltClaimTypeList").val(null).trigger("change.select2");
        modal.find("#fileName, #fileType, #fileIndex, #expIdValue").text("");
        modal.find("#empDisplayValue").text(empName || empCode);
        uploadedFiles = [];
        currentFileIndex = 0;
        currentZoom = 1;
        currentRotation = 0;
        translateX = translateY = 0;
        modal.attr({
            "data-modal-claimid": claimid,
            "data-modal-expid": expId,
            "data-modal-cgid": cgId,
            "data-modal-empid": empId,
        });
        // APPR-RO-01: opened from an approver screen (or the dashboard) -> documents and data entry are view-only.
        // REVERT-EDIT-01: never for the claimant's own claim - a claim returned / reverted to the claimant must stay
        // editable from the dashboard (the server still decides what may change at which stage).
        const viewer = String(modal.attr("data-viewer-empid") || "");
        const ownClaim = viewer !== "" && viewer !== "0" && String(empId || "") === viewer;
        const readOnly = !ownClaim && (String(modal.attr("data-readonly-page")) === "1" || $(this).closest("#dayModal, #rmDayModal, #approver-tab").length > 0);
        modal.attr("data-readonly", readOnly ? "1" : "0");
        window.xeasyClaimReadOnly(readOnly);
        $("#expIdValue").text(expId);
        // Populate the claim-type dropdown (async, for UI only — does NOT trigger load)
        claimTypeList(claimid);
        // Load claim detail immediately with known IDs — no waiting for select2
        loadClaimDetail(claimid, expId);
        modal.modal("show");
        
        // 🏨 Load PAX details for supported claim types (Lodging, Rail Fare, Meal, General)
        // Get year ID and company ID from page context
        // ADV-WEB-01: getCurrentYearId / fYearId exist only on some pages; "getCurrentYearId?.()" still throws when it is not declared
        const yearId = modal.attr("data-year-id") || (typeof getCurrentYearId === 'function' ? getCurrentYearId() : null) || (typeof fYearId !== 'undefined' ? fYearId : null) || $('#selYear').val() || $('input[name="YearId"]').val() || $('meta[name="xeasy-year-id"]').attr('content');
        const comId = $('#modalCompany').val() || $('meta[name="company-id"]').attr('content') || undefined;
        if (typeof initializePaxEditor === 'function' && yearId) {
            initializePaxEditor(expId, claimid, yearId, comId);
        }
    });

    // Show upload button for both Bill claims (cgId == 1) and Activity claims (cgId == 7)
    function toggleUploadButton(cgId) {
        if (parseInt(cgId) === 1 || parseInt(cgId) === 7) {
            $("#claimImageUploadWrapper").show();
        } else {
            $("#claimImageUploadWrapper").hide();
        }
    }

    function applyClaimTypeSwitch(claimid, cgid, expId) {
        const modal = $("#claimDetailModal");
        modal.attr("data-modal-claimid", claimid).attr("data-modal-cgid", cgid);
        loadClaimDetail(claimid, expId);
        toggleUploadButton(cgid);
    }

    $(document).on("change", "#sltClaimTypeList", function () {
        // Only fire when the user manually changes the dropdown, not on programmatic init
        if ($(this).data("init-change")) {
            $(this).removeData("init-change");
            return;
        }
        const $sel = $(this);
        const newClaimId = $sel.val();
        if (!newClaimId) return;

        const modal = $("#claimDetailModal");
        const expId = modal.attr("data-modal-expid");
        const oldClaimId = modal.attr("data-modal-claimid");
        const oldCgId = parseInt(modal.attr("data-modal-cgid"));
        const newCgId = claimTypeCgMap[newClaimId];
        const isSameType = String(newClaimId) === String(oldClaimId);
        const involvesActivity = oldCgId === 7 || newCgId === 7;

        if (isSameType || !involvesActivity || !expId) {
            applyClaimTypeSwitch(newClaimId, newCgId, expId);
            return;
        }

        // Activity is involved on one side — check whether there's anything
        // uploaded under the current head that actually needs migrating.
        startPageLoader();
        $.ajax({
            url: `${baseUrl}/get-uploaded-files/${expId}/${oldClaimId}`,
            type: "GET",
            dataType: "json",
            success: function (res) {
                const files = flattenUploadedFilesForMapping(res.data?.uploaded_files);
                if (!files.length) {
                    // When switching Activity to Activity with no files, transfer amounts and details via backend
                    if (oldCgId === 7 && newCgId === 7) {
                        $.ajax({
                            url: `${baseUrl}/claim-change-head`,
                            type: "POST",
                            data: {
                                expid: expId,
                                new_claim_id: newClaimId,
                                file_mappings: [],
                                _token: $('meta[name="csrf-token"]').attr("content"),
                            },
                            complete: function () {
                                endPageLoader();
                                applyClaimTypeSwitch(newClaimId, newCgId, expId);
                            }
                        });
                        return;
                    }
                    endPageLoader();
                    applyClaimTypeSwitch(newClaimId, newCgId, expId);
                    return;
                }
                openChangeHeadModal({
                    expId,
                    oldClaimId,
                    newClaimId,
                    oldCgId,
                    newCgId,
                    files,
                    $sel,
                });
            },
            error: function () {
                // Fall back to the simple switch rather than blocking the user
                endPageLoader();
                applyClaimTypeSwitch(newClaimId, newCgId, expId);
            },
        });
    });

    // --- Change Claim Head: file -> new-head mapping modal ---------------

    function flattenUploadedFilesForMapping(data) {
        if (!data) return [];
        const files = [];
        if (Array.isArray(data)) {
            data.forEach((f) => files.push({
                file_name: f.file_path,
                file_url: f.file_url,
                current_label: (f.file_column || "").replace(/^File_/, "").replace(/_/g, " "),
                claim_amt: f.claim_amt ?? "",
                ver_amt: f.ver_amt ?? "",
                app_amt: f.app_amt ?? "",
                fin_amt: f.fin_amt ?? "",
                head_slug: f.head_slug ?? "",
            }));
        } else {
            Object.keys(data).forEach((col) => {
                const group = data[col];
                (group.files || []).forEach((f) => files.push({
                    file_name: f.file_path,
                    file_url: f.file_url,
                    current_label: group.category_name || group.short_code || "Unknown",
                    claim_amt: f.claim_amt ?? group.amount ?? "",
                    ver_amt: f.ver_amt ?? "",
                    app_amt: f.app_amt ?? "",
                    fin_amt: f.fin_amt ?? "",
                    head_slug: f.head_slug ?? col.replace(/^file_/, ""),
                }));
            });
        }
        return files;
    }

    function normalizeLabel(str) {
        return (str || "").toLowerCase().replace(/[^a-z0-9]+/g, "");
    }

    function suggestTarget(currentLabel, fileName, options) {
        const needle = normalizeLabel(currentLabel) + normalizeLabel(fileName);
        let best = null;
        let bestScore = 0;
        options.forEach((opt) => {
            const optNorm = normalizeLabel(opt.label);
            let score = 0;
            if (optNorm && needle.includes(optNorm)) score = optNorm.length + 10;
            else if (optNorm && optNorm.includes(normalizeLabel(currentLabel))) score = normalizeLabel(currentLabel).length + 5;
            if (score > bestScore) {
                bestScore = score;
                best = opt;
            }
        });
        return best;
    }

    function fetchTargetOptions(newCgId, newClaimId, callback) {
        if (newCgId === 7) {
            $.get(`${baseUrl}/get-activity-expense-heads/${newClaimId}`, function (res) {
                const heads = (res.data?.expense_heads || []).map((h) => ({
                    value: h.value,
                    label: h.label,
                }));
                callback(heads);
            }).fail(() => callback([]));
        } else {
            $.get(`${baseUrl}/get-bill-upload-categories`, function (res) {
                const cats = (res.data || []).map((c) => ({
                    value: c.value,
                    label: c.label,
                }));
                callback(cats);
            }).fail(() => callback([]));
        }
    }

    function openChangeHeadModal(ctx) {
        fetchTargetOptions(ctx.newCgId, ctx.newClaimId, function (options) {
            const $modal = $("#changeHeadModal");
            if (ctx.oldCgId === 7 && ctx.newCgId === 7) {
                $("#changeHeadWarning").html(
                    `<i class="ri-information-line me-1"></i>Switching between <strong>Activities</strong> ` +
                    `transfers your activity details (Bill Date, Activity Type, Crops, Varieties) and amounts. ` +
                    `Assign each document to its new head and confirm the amount to transfer.`
                );
            } else {
                const oldLabel = ctx.oldCgId === 7 ? "Activity" : "Bill";
                const newLabel = ctx.newCgId === 7 ? "Activity" : "Bill";
                $("#changeHeadWarning").html(
                    `<i class="ri-error-warning-line me-1"></i>Switching from <strong>${oldLabel}</strong> to <strong>${newLabel}</strong>. ` +
                    `Assign each uploaded file to where it belongs under the new head and specify its amount.`
                );
            }

            const optionsHtml = options.length
                ? options.map((o) => `<option value="${o.value}">${o.label}</option>`).join("")
                : '<option value="">No categories available</option>';

            const $mapping = $("#changeHeadFileMapping").empty();
            ctx.files.forEach((f, idx) => {
                const suggestion = suggestTarget(f.current_label, f.file_name, options);
                const isImage = /\.(jpg|jpeg|png|gif|bmp|webp)$/i.test(f.file_name);
                const thumb = isImage
                    ? `<img src="${f.file_url}" style="width:42px;height:42px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">`
                    : `<i class="ri-file-pdf-2-line text-danger" style="font-size:32px;"></i>`;

                const initialAmt = (f.claim_amt !== "" && f.claim_amt !== null && f.claim_amt !== undefined)
                    ? String(f.claim_amt).replace(/,/g, '')
                    : '';
                const amtBadge = initialAmt ? ` <span class="badge bg-success-subtle text-success">₹${numberFormat(initialAmt)}</span>` : '';

                const $row = $(`
                    <div class="border rounded p-2 mb-2 bg-light shadow-sm change-head-file-row" 
                         data-file-name="${f.file_name}"
                         data-old-head="${f.head_slug || ''}">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center" style="gap:10px; min-width:220px; max-width:320px;">
                                ${thumb}
                                <div style="min-width:0;">
                                    <div class="small fw-semibold text-truncate" style="max-width:240px;" title="${f.file_name}">${f.file_name}</div>
                                    <div class="small text-muted">Currently: <strong>${f.current_label || "Unknown"}</strong>${amtBadge}</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-end" style="min-width:320px;">
                                <div style="width: 200px;">
                                    <label class="form-label small text-muted mb-0 d-block" style="font-size:11px;">Target Category <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm change-head-target">
                                        <option value="">Select category...</option>
                                        ${optionsHtml}
                                    </select>
                                </div>
                                <div style="width: 120px;">
                                    <label class="form-label small text-muted mb-0 d-block" style="font-size:11px;">Amount (₹)</label>
                                    <input type="number" step="any" class="form-control form-control-sm change-head-amount" placeholder="Amount" value="${initialAmt}">
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                if (suggestion) {
                    $row.find(".change-head-target").val(suggestion.value);
                } else {
                    $row.find(".change-head-target").addClass("border-warning");
                }
                $mapping.append($row);
            });

            $modal.data("ctx", ctx);
            $modal.one("shown.bs.modal", function () {
                endPageLoader();
            });
            showModal($modal);
        });
    }

    $(document).on("hidden.bs.modal", "#changeHeadModal", function () {
        const ctx = $(this).data("ctx");
        if (ctx && !ctx._confirmed) {
            // User cancelled — revert the dropdown to the previous head
            ctx.$sel.data("init-change", true).val(ctx.oldClaimId).trigger("change.select2");
        }
    });

    $(document).on("click", "#changeHeadConfirmBtn", function () {
        const $modal = $("#changeHeadModal");
        const ctx = $modal.data("ctx");
        if (!ctx) return;

        const fileMappings = [];
        let hasEmpty = false;
        $modal.find(".change-head-file-row").each(function () {
            const fileName = $(this).data("file-name");
            const oldHead = $(this).data("old-head");
            const target = $(this).find(".change-head-target").val();
            const amount = $(this).find(".change-head-amount").val();
            if (!target) {
                hasEmpty = true;
                $(this).find(".change-head-target").addClass("border-danger");
            }
            fileMappings.push({
                file_name: fileName,
                old_head: oldHead,
                target: target,
                amount: amount !== "" ? parseFloat(amount) : null,
            });
        });

        if (hasEmpty) {
            showAlert("danger", "ri-error-warning-line", "Please select a category for every file before continuing.");
            return;
        }

        const $btn = $(this);
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span> Moving files & details...');

        $.ajax({
            url: `${baseUrl}/claim-change-head`,
            type: "POST",
            data: {
                expid: ctx.expId,
                new_claim_id: ctx.newClaimId,
                file_mappings: fileMappings,
                _token: $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (res) {
                if (!res.success) {
                    showAlert("danger", "ri-error-warning-line", res.message || "Failed to change claim head.");
                    return;
                }
                ctx._confirmed = true;
                hideModal($modal);
                showAlert("success", "ri-checkbox-circle-line", res.message || "Claim head changed successfully.");
                applyClaimTypeSwitch(ctx.newClaimId, ctx.newCgId, ctx.expId);
                fetchUploadedFiles(ctx.expId, ctx.newClaimId);
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || "Failed to change claim head.";
                showAlert("danger", "ri-error-warning-line", msg);
            },
            complete: function () {
                $btn.prop("disabled", false).html('<i class="ri-check-line me-1"></i>Confirm & Move Files');
            },
        });
    });

    $(document).on("click", "#sendMessage", function (e) {
        e.preventDefault();
        const replyMessage = $("#replyMessage").val().trim();
        const expid = $("#expIdValue").text().trim();
        if (replyMessage === "")
            return showAlert(
                "danger",
                "ri-error-warning-line",
                "Please type something"
            );

        $.ajax({
            url: `${baseUrl}/send-message`,
            type: "POST",
            data: {
                message: replyMessage,
                expid: expid,
                _token: $("meta[name='csrf-token']").attr("content"),
            },
            success: function (response) {
                $("#chat-messages").append(response.html);
                document.getElementById("chat-messages").scrollTop =
                    document.getElementById("chat-messages").scrollHeight;
                $("#replyMessage").val("");
            },
            error: function () {
                alert("Failed to send message");
            },
        });
    });

    // Trigger on modal open via .view-claim
    $(document).on("click", ".view-claim", function () {
        const cgId = $(this).data("cgid");
        toggleUploadButton(cgId);
    });

    // Also toggle when claim type changes (cgId stored on modal)
    $(document).on("change", "#sltClaimTypeList", function () {
        const cgId = $("#claimDetailModal").attr("data-modal-cgid");
        toggleUploadButton(cgId);
    });

    // Handle file selection → auto-upload
    $(document).on("change", "#claimImageInput", function () {
        const file = this.files[0];
        if (!file) return;

        const expId = $("#claimDetailModal").attr("data-modal-expid");
        if (!expId) {
            showAlert("danger", "ri-error-warning-line", "No expense ID found.");
            return;
        }

        const formData = new FormData();
        formData.append("image", file);
        formData.append("expid", expId);
        formData.append("_token", $('meta[name="csrf-token"]').attr("content"));

        const $label = $('label[for="claimImageInput"]');
        const origHtml = $label.html();
        $label.html('<span class="spinner-border spinner-border-sm"></span>').prop("disabled", true);

        $.ajax({
            url: `${baseUrl}/claim-upload-image`,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (!res.success) {
                    showAlert("danger", "ri-error-warning-line", res.message || "Upload failed.");
                    return;
                }
                const f = res.data;
                const ext = f.file_path.split(".").pop().toLowerCase();
                let type = "application/octet-stream";
                if (["jpg","jpeg","png","gif","bmp","webp","heic"].includes(ext)) type = "image/" + ext;
                else if (ext === "pdf") type = "application/pdf";
                uploadedFiles.push({ url: f.file_url, name: f.file_path, type, short_code: "Uploaded" });
                displayThumbnails();
                showFile(uploadedFiles.length - 1);
                showAlert("success", "ri-checkbox-circle-line", "Image uploaded successfully.");

                // If currently viewing Activity claim (cgId == 7), reload files
                const modal = $("#claimDetailModal");
                const cgId = parseInt(modal.attr("data-modal-cgid"));
                const claimId = modal.attr("data-modal-claimid");
                if (cgId === 7 && expId && claimId) {
                    fetchUploadedFiles(expId, claimId);
                    loadClaimDetail(claimId, expId, null, false);
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || "Upload failed.";
                showAlert("danger", "ri-error-warning-line", msg);
            },
            complete: function () {
                $label.html(origHtml).prop("disabled", false);
                // reset input so same file can be re-selected
                $("#claimImageInput").val("");
            },
        });
    });

    // --- Switch image / amount between heads within the same activity ---
    let switchContext = null;

    function ensureSwitchActivityHeadModal() {
        if ($("#switchActivityHeadModal").length === 0) {
            const modalHtml = `
            <div class="modal fade" id="switchActivityHeadModal" data-bs-backdrop="static" tabindex="-1"
                aria-labelledby="switchActivityHeadModalLabel" aria-hidden="true" style="z-index: 1070;">
                <div class="modal-dialog modal-dialog-centered" style="z-index: 1075;">
                    <div class="modal-content shadow-lg border">
                        <div class="modal-header py-2 bg-light">
                            <h6 class="modal-title fw-bold" id="switchActivityHeadModalLabel">
                                <i class="ri-exchange-funds-line me-1 text-primary"></i>Switch Image & Details to Another Head
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">File / Document:</label>
                                <div class="d-flex align-items-center p-2 rounded bg-light border">
                                    <i class="ri-file-image-line me-2 fs-18 text-primary"></i>
                                    <span id="switchHeadFileName" class="fw-semibold text-truncate small" style="max-width: 380px;"></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">Current Expense Head:</label>
                                <input type="text" id="switchHeadCurrentName" class="form-control form-control-sm bg-light" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark mb-1">Move To Expense Head <span class="text-danger">*</span>:</label>
                                <select id="switchHeadTargetSelect" class="form-select form-select-sm">
                                    <option value="">-- Select Target Expense Head --</option>
                                </select>
                            </div>
                            <div class="p-2 border rounded bg-light-subtle">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" id="switchHeadMoveAmountCheck" checked>
                                    <label class="form-check-label small fw-bold" for="switchHeadMoveAmountCheck">
                                        Also move Amount & Remarks to target head
                                    </label>
                                </div>
                                <div id="switchHeadAmountPreview" class="small text-muted ps-4">
                                    <span>Claim Amount: <strong id="switchHeadClaimAmtPreview">₹0</strong></span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                                <i class="ri-close-line me-1"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="switchHeadConfirmBtn">
                                <i class="ri-check-line me-1"></i>Confirm & Move
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
            $("body").append(modalHtml);
        }
    }

    $(document).on("click", ".switch-expense-head", function (e) {
        e.preventDefault();
        e.stopPropagation();

        ensureSwitchActivityHeadModal();

        const $fileItem = $(this).closest(".file-list > div");
        const $sourceRow = $(this).closest("tr");
        const fileName = $fileItem.find(".file-name").attr("data-file") || $fileItem.find(".file-name").text().trim();
        const sourceHeadName = $sourceRow.find("td").first().clone().children().remove().end().text().trim() 
                              || $sourceRow.find("td:first").text().replace(/\*/g, "").trim();
        const sourceRowIndex = $sourceRow.index();

        const claimAmt = String($sourceRow.find(".claim").val() || "0").trim();
        const verAmt = $sourceRow.find(".verified").val() || "";
        const verRemark = $sourceRow.find("td").eq(4).find("input").val() || "";
        const appAmt = $sourceRow.find(".approved").val() || "";
        const appRemark = $sourceRow.find("td").eq(6).find("input").val() || "";
        const finAmt = $sourceRow.find(".finance").val() || "";
        const finRemark = $sourceRow.find("td").eq(8).find("input").val() || "";

        // Populate target options from all other rows in the .expense-table
        const $targetSelect = $("#switchHeadTargetSelect").empty();
        $targetSelect.append('<option value="">-- Select Target Expense Head --</option>');

        $(".expense-table tbody tr").each(function () {
            const $r = $(this);
            const headName = $r.find("td").first().clone().children().remove().end().text().trim()
                          || $r.find("td:first").text().replace(/\*/g, "").trim();
            if (!headName || headName.toLowerCase().startsWith("total") || $r.is($sourceRow)) return;
            const rIndex = $r.index();
            $targetSelect.append(`<option value="${rIndex}">${headName}</option>`);
        });

        $("#switchHeadFileName").text(fileName);
        $("#switchHeadCurrentName").val(sourceHeadName);
        $("#switchHeadClaimAmtPreview").text("₹" + claimAmt);
        const parsedClaim = parseFloat(claimAmt.replace(/,/g, ""));
        $("#switchHeadMoveAmountCheck").prop("checked", !isNaN(parsedClaim) && parsedClaim > 0);

        switchContext = {
            $fileItem,
            $sourceRow,
            fileName,
            sourceHeadName,
            sourceRowIndex,
            claimAmt,
            verAmt,
            verRemark,
            appAmt,
            appRemark,
            finAmt,
            finRemark,
        };

        showModal("#switchActivityHeadModal");

        setTimeout(function () {
            $(".modal-backdrop").last().css("z-index", "1065");
            $("#switchActivityHeadModal").css("z-index", "1070");
        }, 100);
    });

    $(document).on("click", "#switchHeadConfirmBtn", function (e) {
        e.preventDefault();
        if (!switchContext) return;
        const targetRowIndex = $("#switchHeadTargetSelect").val();
        if (targetRowIndex === "" || targetRowIndex === undefined) {
            showAlert("danger", "ri-error-warning-line", "Please select a target expense head.");
            return;
        }

        const $targetRow = $(".expense-table tbody tr").eq(parseInt(targetRowIndex));
        if (!$targetRow.length) return;

        const targetHeadName = $targetRow.find("td").first().clone().children().remove().end().text().trim() 
                              || $targetRow.find("td:first").text().replace(/\*/g, "").trim();
        const moveAmount = $("#switchHeadMoveAmountCheck").is(":checked");

        // 1. Move file item DOM element to target row's .file-list
        const $targetFileList = $targetRow.find(".file-list");
        $targetFileList.append(switchContext.$fileItem);

        // 2. If file was newly selected in file-input, transfer file object to target file input
        const sourceInput = switchContext.$sourceRow.find(".file-input")[0];
        const targetInput = $targetRow.find(".file-input")[0];
        if (sourceInput && targetInput && sourceInput.files.length > 0) {
            const dtSource = new DataTransfer();
            const dtTarget = new DataTransfer();
            Array.from(targetInput.files).forEach(f => dtTarget.items.add(f));

            Array.from(sourceInput.files).forEach(f => {
                if (f.name === switchContext.fileName) {
                    dtTarget.items.add(f);
                } else {
                    dtSource.items.add(f);
                }
            });
            sourceInput.files = dtSource.files;
            targetInput.files = dtTarget.files;
        }

        // 3. Move amount and remarks if checked
        if (moveAmount) {
            const currentTargetClaim = parseFloat($targetRow.find(".claim").val()?.replace(/,/g, "") || 0);
            const sourceClaim = parseFloat(switchContext.claimAmt.replace(/,/g, "") || 0);
            const newTargetClaim = currentTargetClaim > 0 ? (currentTargetClaim + sourceClaim) : sourceClaim;

            if (newTargetClaim > 0) {
                $targetRow.find(".claim").val(numberFormat(newTargetClaim));
            }
            switchContext.$sourceRow.find(".claim").val("");

            // MOVE-01: verified / approved / finance amounts ADD to the target head (they were
            // overwritten, losing the target's own amount); remarks are joined.
            const addAmt = function ($t, $s, sel) {
                const sv = String($s.find(sel).val() || "").replace(/,/g, "").trim();
                if (sv === "") return;
                const tv = String($t.find(sel).val() || "").replace(/,/g, "").trim();
                const sum = (parseFloat(tv) || 0) + (parseFloat(sv) || 0);
                $t.find(sel).val(numberFormat(sum));
                $s.find(sel).val("");
            };
            const joinRemark = function ($t, $s, col) {
                const si = $s.find("td").eq(col).find("input"), ti = $t.find("td").eq(col).find("input");
                const sv = (si.val() || "").trim();
                if (!sv) return;
                const tv = (ti.val() || "").trim();
                ti.val(tv && tv !== sv ? tv + "; " + sv : sv);
                si.val("");
            };
            addAmt($targetRow, switchContext.$sourceRow, ".verified");
            addAmt($targetRow, switchContext.$sourceRow, ".approved");
            addAmt($targetRow, switchContext.$sourceRow, ".finance");
            joinRemark($targetRow, switchContext.$sourceRow, 4);
            joinRemark($targetRow, switchContext.$sourceRow, 6);
            joinRemark($targetRow, switchContext.$sourceRow, 8);

            if (typeof window.updateTotals === "function") {
                window.updateTotals();
            }
        }

        // 4. Highlight target row
        $targetRow.css("background-color", "#d1e7dd");
        setTimeout(() => {
            $targetRow.css("background-color", "");
        }, 1500);

        hideModal("#switchActivityHeadModal");

        showAlert("success", "ri-checkbox-circle-line", `"${switchContext.fileName}" successfully switched from "${switchContext.sourceHeadName}" to "${targetHeadName}".`);
        switchContext = null;
    });
});