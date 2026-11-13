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
    $defaultRemark = 'Postage Courier';
    $pd = $expense_detail->punching_data;
    $savedProvider = $pd->ServiceProvider ?? '';
    $savedSourceCity = $pd->JourneyFrom ?? '';
    $savedDestCity = $pd->JourneyUpto ?? '';
@endphp
<div id="punch-form-section">
    <div class="row mb-1 modal-ncgid" data-modal-ncgid="{{ $cgId }}">

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Sender Name</small>
        <input type="text" class="form-control" id="sender_name" name="sender_name"
            value="{{ $pd->SenderName ?? $employee_detail['employee_detail']['full_name'] ?? '' }}" />
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Sender Address</small>
        <input type="text" class="form-control" id="sender_address" name="sender_address"
            value="{{ $pd->SenderAddress ?? '' }}" />
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Provider Name <span class="text-danger">*</span></small>
        <select class="form-control" id="service_provider" name="service_provider"></select>
        <div id="other_cp_div" style="display:none;" class="mt-2">
            <input type="text" class="form-control" id="other_cp_name" name="other_cp_name"
                value="" placeholder="Enter Provider Name" aria-label="Enter Provider Name" />
        </div>
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Weight Charged <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="weight_charged" name="weight_charged"
            value="{{ $pd->WeightCharged ?? '' }}" />
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Docket No. <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="docket_number" name="docket_number"
            value="{{ $pd->DocketNumber ?? '' }}" />
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Booked Date</small>
        <input type="text" class="form-control" id="bill_date" name="bill_date"
            value="{{ \App\Helpers\CommonHelper::toDisplayFormat($pd->DocketBookedDt ?? $expense_detail->BillDate ?? '') }}" />
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Receiver Name <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="receiver_name" name="receiver_name"
            value="{{ $pd->ReceiverName ?? '' }}" />
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Receiver Address</small>
        <input type="text" class="form-control" id="receiver_address" name="receiver_address"
            value="{{ $pd->ReceiverAddress ?? '' }}" />
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Source City</small>
        <select class="form-control" id="source_city" name="source_city"></select>
        <div id="other_city_div" style="display:none;" class="mt-2">
            <input type="text" class="form-control" id="other_city_name" name="other_city_name"
                value="" placeholder="Enter City Name" aria-label="Enter City Name" />
        </div>
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Destination City</small>
        <select class="form-control" id="dest_city" name="dest_city"></select>
        <div id="other2_city_div" style="display:none;" class="mt-2">
            <input type="text" class="form-control" id="other2_city_name" name="other2_city_name"
                value="" placeholder="Enter City Name" aria-label="Enter City Name" />
        </div>
    </div>

    <div class="col-md-4 mb-1">
        <small class="form-text text-muted">Amount <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="totalAmount" name="totalAmount"
            value="{{ $expense_detail->FilledTAmt ?? '' }}" />
    </div>
    <div class="col-md-4 mb-1">
        <small class="form-text text-muted">Filled Amount (₹)</small>
        <input type="text" class="form-control" readonly
            value="{{ $pd->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" />
    </div>
    <div class="col-md-4 mb-1">
        <small class="form-text text-muted">Punching Remark</small>
        <input type="text" class="form-control" id="filledRemark" name="filledRemark"
            value="{{ trim($expense_detail->Remark ?? '') !== '' ? $expense_detail->Remark : $defaultRemark }}"
            style="min-width: 100%;" />
    </div>

    </div>
</div>
<x-modal.claim_view.remark_chat :remarks="$remarkChat" />
<input type="hidden" id="employee_id" value="{{ $expense_detail->CrBy ?? '' }}" />

<script>
$(document).ready(function() {
    const savedProvider = "{{ $savedProvider }}";
    const savedSourceCity = "{{ $savedSourceCity }}";
    const savedDestCity = "{{ $savedDestCity }}";

    flatpickr("#bill_date", {
        dateFormat: "d-m-Y",
        maxDate: "today",
    });

    // Load courier providers
    $.getJSON("/get-courier-providers", function(data) {
        const $sel = $("#service_provider");
        $sel.empty().append('<option value="">Select Provider</option>');
        let matched = false;
        (data.data || []).forEach(function(v) {
            const isSelected = v.name === savedProvider ? "selected" : "";
            if (isSelected) matched = true;
            $sel.append(`<option value="${v.name}" ${isSelected}>${v.name}</option>`);
        });
        $sel.append('<option value="0">Other</option>');
        $sel.select2({ allowClear: true, dropdownParent: $("#claimDetailModal") });

        if (!matched && savedProvider) {
            $sel.val("0").trigger("change.select2");
            $("#other_cp_div").show();
            $("#other_cp_name").val(savedProvider);
        }
    });

    $("#service_provider").on("change", function() {
        if ($(this).val() == "0") {
            $("#other_cp_div").show();
        } else {
            $("#other_cp_div").hide();
            $("#other_cp_name").val("");
        }
    });

    // Load cities
    $.getJSON("/city-list", function(response) {
        const cities = response.data || [];

        function buildCitySelect(selectId, otherDivId, savedName) {
            const $sel = $("#" + selectId);
            $sel.empty().append('<option value="">Select City</option>');
            let matched = false;
            cities.forEach(function(c) {
                const isSelected = (c.city_name === savedName || (savedName && (c.aliases || []).includes(String(savedName).trim()))) ? "selected" : ""; // CITY-03
                if (isSelected) matched = true;
                $sel.append(`<option value="${c.city_name}" ${isSelected}>${c.city_name}</option>`);
            });
            $sel.append('<option value="0">Other</option>');
            $sel.select2({ allowClear: true, dropdownParent: $("#claimDetailModal") });

            if (!matched && savedName) {
                $sel.val("0").trigger("change.select2");
                $("#" + otherDivId).show();
                $("#" + otherDivId).find("input").val(savedName);
            }
        }

        buildCitySelect("source_city", "other_city_div", savedSourceCity);
        buildCitySelect("dest_city", "other2_city_div", savedDestCity);
    });

    $("#source_city").on("change", function() {
        $("#other_city_div").toggle($(this).val() == "0");
        if ($(this).val() != "0") $("#other_city_name").val("");
    });

    $("#dest_city").on("change", function() {
        $("#other2_city_div").toggle($(this).val() == "0");
        if ($(this).val() != "0") $("#other2_city_name").val("");
    });

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
