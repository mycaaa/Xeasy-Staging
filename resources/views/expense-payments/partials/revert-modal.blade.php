@if(isSuperAdmin())
<!-- Revert Confirmation Modal -->
<div class="modal fade" id="revertPaymentModal" tabindex="-1" aria-labelledby="revertPaymentModalLabel" aria-hidden="true" style="z-index: 10550;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title mb-0" id="revertPaymentModalLabel">
                    <i class="fa fa-undo me-1"></i> Revert Payment Stage
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 text-start">
                <div class="alert alert-warning py-2 px-3 mb-3" style="font-size: 12px; border-left: 4px solid #ffc107;">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    <strong>Super Admin Action:</strong> Reverting will return the record(s) to <strong><span id="revertTargetStageText">previous</span></strong> stage.
                    <div id="revertAdvanceWarning" style="margin-top: 4px; display: none;">
                        <small class="text-danger">Advance adjustments tied to this record will be released back to the employee's available balance.</small>
                    </div>
                </div>

                <div class="card mb-3 bg-light border-0">
                    <div class="card-body p-2" id="revertDetailsBox" style="font-size: 12px; line-height: 1.6;">
                        <!-- Dynamically populated -->
                    </div>
                </div>

                <div class="mb-2">
                    <label for="revertReasonInput" class="form-label fw-bold mb-1" style="font-size: 12px;">
                        Reason for Reversion <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="revertReasonInput" rows="3"
                        placeholder="Enter clear, detailed reason for reverting this payment (minimum 5 characters)..."
                        style="font-size: 12px;" required></textarea>
                    <div class="form-text text-muted" style="font-size: 11px;">
                        <i class="fa fa-shield"></i> This reason and your user identity will be permanently recorded in the audit logs.
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger px-3" id="confirmRevertSubmitBtn" onclick="submitRevertPayment()">
                    <i class="fa fa-undo me-1"></i> Confirm Revert
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Revert Audit Logs Modal -->
<div class="modal fade" id="revertLogsModal" tabindex="-1" aria-labelledby="revertLogsModalLabel" aria-hidden="true" style="z-index: 10550;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title mb-0" id="revertLogsModalLabel">
                    <i class="fa fa-history me-1"></i> Payment Revert Audit Logs (Super Admin)
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div style="font-size: 12px; color: #555;">
                        <i class="fa fa-info-circle"></i> Showing recent payment reversion logs.
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="loadRevertLogsContent()">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
                <div id="revertLogsTableContainer" style="overflow-x: auto; max-height: 480px;">
                    <div class="text-center py-4 text-muted">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <div class="mt-2" style="font-size: 12px;">Loading logs...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let activeRevertIds = [];

function openRevertSingleModal(id, empName, empCode, month, amount, targetStage, currentStage) {
    activeRevertIds = [id];
    targetStage = targetStage || 'Pending';
    currentStage = currentStage || (targetStage === 'Pending' ? 'Approved' : 'Instructed');

    $('#revertTargetStageText').text(targetStage);
    $('#revertReasonInput').val('');

    if (targetStage === 'Pending') {
        $('#revertAdvanceWarning').show();
    } else {
        $('#revertAdvanceWarning').hide();
    }

    let html = `
        <div class="row g-1">
            <div class="col-6"><strong>Employee:</strong> ${empName} <span class="badge bg-secondary">${empCode}</span></div>
            <div class="col-6"><strong>Month / Year:</strong> ${month}</div>
            <div class="col-6"><strong>Amount:</strong> ₹${amount}</div>
            <div class="col-6"><strong>Stage Change:</strong> 
                <span class="badge bg-warning text-dark">${currentStage}</span> &rarr; 
                <span class="badge bg-danger">${targetStage}</span>
            </div>
        </div>
    `;
    $('#revertDetailsBox').html(html);

    let modalEl = document.getElementById('revertPaymentModal');
    let modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
}

function openRevertSelectedModal(targetStage, currentStage) {
    targetStage = targetStage || 'Pending';
    currentStage = currentStage || (targetStage === 'Pending' ? 'Approved' : 'Instructed');

    let selected = [];
    $('.row-selector:checked').each(function() {
        let recId = $(this).data('id');
        if (recId) {
            selected.push(recId);
        }
    });

    if (selected.length === 0) {
        alert('Please select at least one record to revert using the checkboxes.');
        return;
    }

    activeRevertIds = selected;
    $('#revertTargetStageText').text(targetStage);
    $('#revertReasonInput').val('');

    if (targetStage === 'Pending' || targetStage === 'previous') {
        $('#revertAdvanceWarning').show();
    } else {
        $('#revertAdvanceWarning').hide();
    }

    let actionText = (targetStage === 'previous')
        ? `Reverting from <span class="badge bg-warning text-dark">${currentStage}</span> to previous stages (Approved &rarr; Pending, Instructed &rarr; Approved).`
        : `Reverting from <span class="badge bg-warning text-dark">${currentStage}</span> to <span class="badge bg-danger">${targetStage}</span>`;

    let html = `
        <div class="row g-1">
            <div class="col-12"><strong>Selected Records:</strong> <span class="badge bg-primary">${selected.length} records</span></div>
            <div class="col-12 mt-1"><strong>Action:</strong> ${actionText}</div>
        </div>
    `;
    $('#revertDetailsBox').html(html);

    let modalEl = document.getElementById('revertPaymentModal');
    let modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
}

function submitRevertPayment() {
    let reason = $('#revertReasonInput').val().trim();
    if (reason.length < 5) {
        alert('Please enter a valid reason for reversion (minimum 5 characters).');
        $('#revertReasonInput').focus();
        return;
    }

    if (activeRevertIds.length === 0) {
        alert('No records selected for reversion.');
        return;
    }

    let btn = $('#confirmRevertSubmitBtn');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Reverting...');

    $.ajax({
        url: "{{ route('expense-payments.revert') }}",
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            ids: activeRevertIds,
            reason: reason
        },
        success: function(response) {
            btn.prop('disabled', false).html('<i class="fa fa-undo me-1"></i> Confirm Revert');
            if (response.status === 'success') {
                alert(response.message || 'Payment reverted successfully.');
                location.reload();
            } else {
                alert('Error: ' + (response.message || 'Revert failed.'));
            }
        },
        error: function(xhr) {
            btn.prop('disabled', false).html('<i class="fa fa-undo me-1"></i> Confirm Revert');
            let msg = 'Failed to revert payment.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                msg = xhr.responseText;
            }
            alert('Error: ' + msg);
        }
    });
}

function openRevertLogsModal(recordId, employeeId) {
    let modalEl = document.getElementById('revertLogsModal');
    let modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();

    loadRevertLogsContent(recordId, employeeId);
}

function loadRevertLogsContent(recordId, employeeId) {
    $('#revertLogsTableContainer').html(`
        <div class="text-center py-4 text-muted">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <div class="mt-2" style="font-size: 12px;">Loading audit logs...</div>
        </div>
    `);

    let url = "{{ route('expense-payments.revert-logs') }}";
    let params = {};
    if (recordId) params.record_id = recordId;
    if (employeeId) params.employee_id = employeeId;

    $.ajax({
        url: url,
        type: 'GET',
        data: params,
        success: function(response) {
            if (response.status === 'success' && response.data && response.data.length > 0) {
                let rows = '';
                response.data.forEach(function(item) {
                    rows += `
                        <tr>
                            <td class="text-nowrap">${item.reverted_at}</td>
                            <td><strong>${item.employee_name}</strong> <span class="badge bg-secondary">${item.employee_code}</span></td>
                            <td>${item.month_name}</td>
                            <td class="text-end">₹${item.amount}</td>
                            <td class="text-center">
                                <span class="badge bg-warning text-dark">${item.from_stage}</span> &rarr;
                                <span class="badge bg-info text-dark">${item.to_stage}</span>
                            </td>
                            <td style="max-width: 250px; word-break: break-word;">${item.reason}</td>
                            <td>${item.reverted_by_name}</td>
                        </tr>
                    `;
                });

                let tableHtml = `
                    <table class="table table-bordered table-striped table-sm mb-0" style="font-size: 11px;">
                        <thead class="table-dark">
                            <tr>
                                <th>Date/Time</th>
                                <th>Employee</th>
                                <th>Month</th>
                                <th>Amount</th>
                                <th>Stage Change</th>
                                <th>Reason</th>
                                <th>Reverted By</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                `;
                $('#revertLogsTableContainer').html(tableHtml);
            } else {
                $('#revertLogsTableContainer').html(`
                    <div class="text-center py-4 text-muted" style="font-size: 12px;">
                        <i class="fa fa-info-circle fa-2x mb-2"></i>
                        <div>No revert logs found.</div>
                    </div>
                `);
            }
        },
        error: function(xhr) {
            $('#revertLogsTableContainer').html(`
                <div class="text-center py-4 text-danger" style="font-size: 12px;">
                    <i class="fa fa-times-circle fa-2x mb-2"></i>
                    <div>Failed to load audit logs.</div>
                </div>
            `);
        }
    });
}
</script>
@endif
