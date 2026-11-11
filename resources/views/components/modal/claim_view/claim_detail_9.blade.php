<div class="row mb-3">
    <div class="col-md-12">
        <div class="d-flex justify-content-left">
            <div class="form-check form-check-inline">
                <input class="form-check-input view-toggle" type="radio"
                       name="view_mode" id="view_punch_form" value="punch_form" checked />
                <label class="form-check-label" for="view_punch_form">Form</label>
            </div>

            <div class="form-check form-check-inline">
                <input class="form-check-input view-toggle" type="radio"
                       name="view_mode" id="view_chat" value="chat" />
                <label class="form-check-label" for="view_chat">Chat</label>
            </div>
        </div>
    </div>
</div>
@php
    $remarkChat = collect($expense_detail->remark_chat ?? []);
    $defaultRemark = 'Vehicle Fuel';
@endphp
<div id="punch-form-section">
    <div class="row mb-1 modal-ncgid" data-modal-ncgid="{{ $cgId }}">
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Person <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="person" name="person" value="{{ $expense_detail->punching_data->BillingName ?? $employee_detail['employee_detail']['full_name'] ?? '' }}" />
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Person Address</small>
        <input class="form-control" id="person_address" name="person_address" value="{{ $expense_detail->punching_data->BillingAddress ?? '' }}">
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Bill Date <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="bill_date" name="bill_date" value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->BillDate ?? $expense_detail->BillDate ?? '') }}" />
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Amount <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="totalAmount" name="totalAmount" value="{{ $expense_detail->FilledTAmt ?? '' }}" />
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Filled Amount (₹)</small>
        <input type="text" class="form-control" readonly value="{{ $expense_detail->punching_data->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" />
    </div>
    <div class="col-md-4 mb-1">
        <small class="form-text text-muted">Punching Remark</small>
        <input type="text" 
               class="form-control" 
               id="filledRemark" 
               name="filledRemark"
               value="{{ trim($expense_detail->Remark ?? '') !== '' ? $expense_detail->Remark : $defaultRemark }}"
               style="min-width: 100%;" />
    </div>
    </div>
</div>
<x-modal.claim_view.remark_chat :remarks="$remarkChat" />
<input type="hidden" id="employee_id" value="{{ $expense_detail->CrBy ?? '' }}" />
<script>
    $(document).ready(function() {
        flatpickr("#bill_date", {
            dateFormat: "d-m-Y",
            enableYearSelection: true,
            maxDate: "today",
            onReady: function() {
                this.isDisabled = false;
            },
        });

        // Toggle between Punch Form and Chat
        $('.view-toggle').on('change', function() {
            if ($(this).val() === 'punch_form') {
                $('#punch-form-section').show();
                $('#chat-section').hide();
            } else {
                $('#punch-form-section').hide();
                $('#chat-section').show();
            }
        });
    });
</script>