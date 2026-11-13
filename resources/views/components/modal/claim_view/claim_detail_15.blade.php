<div class="row mb-3">
    <div class="col-md-12">
        <div class="d-flex justify-content-left">
            <div class="form-check form-check-inline">
                <input class="form-check-input view-toggle" type="radio" name="view_mode" id="view_punch_form"
                    value="punch_form" checked />
                <label class="form-check-label" for="view_punch_form">Form</label>
            </div>

            <div class="form-check form-check-inline">
                <input class="form-check-input view-toggle" type="radio" name="view_mode" id="view_chat"
                    value="chat" />
                <label class="form-check-label" for="view_chat">Chat</label>
            </div>
        </div>
    </div>
</div>
@php
    $remarkChat = collect($expense_detail->remark_chat ?? []);
    $defaultRemark = 'Bus Fare';
@endphp
<div id="punch-form-section">
    <div class="row modal-ncgid" data-modal-ncgid="{{ $cgId }}">


        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Agent Name <span class="text-danger">*</span></small>
            <select class="form-control select2-vendor" id="agent_name" name="agent_name"></select>
            <div id="other_agent_div" style="display:none;" class="mt-2">
                <input type="text" class="form-control" id="other_agent_name" name="other_agent_name"
                    placeholder="Enter Agent Name" aria-label="Enter Agent Name" />
            </div>
        </div>



        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Booking ID <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="pnr_number" name="pnr_number"
                value="{{ $expense_detail->punching_data->PNRNo ?? '' }}" />
        </div>



        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Booking Date <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="booking_date" name="booking_date"
                value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->BookingDate ?? $expense_detail->BillDate ?? '') }}" />
        </div>



        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Journey Date <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="journey_date" name="journey_date"
                value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->JourneyStartDt ?? '') }}" />
        </div>



        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Period
                <i class="ri-info-i info-icon" title="Between Booking & Journey Date"></i>
            </small>
            <input type="number" class="form-control" id="period_days" name="period_days"
                value="{{ $expense_detail->punching_data->StayDuration ?? 0 }}" readonly />
        </div>



        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Bus Name</small>
            <input type="text" class="form-control" id="flight_number" name="flight_number"
                value="{{ $expense_detail->punching_data->TrainBusName ?? '' }}" />
        </div>



        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Journey From <span class="text-danger">*</span></small>
            <select class="form-control select2-city" id="journey_from" name="journey_from"></select>
        </div>



        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Journey Upto <span class="text-danger">*</span></small>
            <select class="form-control select2-city" id="journey_upto" name="journey_upto"></select>
        </div>



        <div class="col-md-8 mb-1">
            <small class="form-text text-muted">Class <span class="text-danger">*</span></small>
            <div class="d-flex gap-3 flex-wrap">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_class" id="class_cc" value="CC"
                        {{ ($expense_detail->punching_data->Class ?? '') === 'CC' ? 'checked' : '' }} />
                    <label class="form-check-label" for="class_cc">CC - Chair Car</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_class" id="class_sl" value="SL"
                        {{ ($expense_detail->punching_data->Class ?? '') === 'SL' ? 'checked' : '' }} />
                    <label class="form-check-label" for="class_sl">SL - Sleeper</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_class" id="class_ac"
                        value="AC" {{ ($expense_detail->punching_data->Class ?? '') === 'AC' ? 'checked' : '' }} />
                    <label class="form-check-label" for="class_ac">AC - Air-Conditioned</label>
                </div>
            </div>
        </div>




        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Travel Insurance <span class="text-danger">*</span></small>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_insurance" id="insurance_yes"
                        value="1"
                        {{ ($expense_detail->punching_data->IsTravelInsurance ?? '') === '1' ? 'checked' : '' }} />
                    <label class="form-check-label" for="insurance_yes">Yes</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_insurance" id="insurance_no"
                        value="0"
                        {{ ($expense_detail->punching_data->IsTravelInsurance ?? '') === '0' ? 'checked' : '' }} />
                    <label class="form-check-label" for="insurance_no">No</label>
                </div>
            </div>
        </div>



        <div class="col-md-4 mb-1 d-none" id="insurance_amount_container">
            <small class="form-text text-muted">Insurance Amount <span class="text-danger">*</span></small>
            <input type="number" class="form-control" id="insurance_amount" name="insurance_amount"
                value="{{ $expense_detail->punching_data->TravelInsurance ?? '' }}" min="0" />
        </div>



        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Amount <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="totalAmount" name="totalAmount"
                value="{{ $expense_detail->FilledTAmt ?? '' }}" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Filled Amount (₹)</small>
            <input type="text" class="form-control" readonly
                value="{{ $expense_detail->punching_data->FilledTAmt ?? ($expense_detail->FilledTAmt ?? '') }}" />
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
        const prefilledData = {
            vendorId: "{{ $expense_detail->punching_data->Vendor_Id ?? 0 }}",
            vendorName: "{{ trim($expense_detail->punching_data->Agent ?? '') }}",
            fromId: "{{ \App\Models\CityCategory::canonicalId($expense_detail->punching_data->Journey_From_Id ?? 0) }}",
            fromName: "{{ trim($expense_detail->punching_data->JourneyFrom ?? '') }}",
            uptoId: "{{ \App\Models\CityCategory::canonicalId($expense_detail->punching_data->Journey_Upto_Id ?? 0) }}",
            uptoName: "{{ trim($expense_detail->punching_data->JourneyUpto ?? '') }}",
        };

        // Flatpickr
        flatpickr("#booking_date, #journey_date", {
            dateFormat: "d-m-Y",
            maxDate: "today",
            onChange: function() {
                const bookingVal = $("#booking_date").val();
                const journeyVal = $("#journey_date").val();
                if (!bookingVal || !journeyVal) return;

                function parseDMY(str) {
                    const p = str.split("-");
                    return p.length === 3 ? new Date(`${p[2]}-${p[1]}-${p[0]}`) : null;
                }

                const booking = parseDMY(bookingVal);
                const journey = parseDMY(journeyVal);
                if (booking && journey) {
                    const diffDays = Math.max(0, Math.ceil((journey - booking) / (1000 * 60 * 60 *
                        24)));
                    $("#period_days").val(diffDays);
                }
            }
        });

        function loadVendors() {
            $.getJSON("/vendor-list", function(data) {
                const $select = $("#agent_name");
                $select.empty().append('<option value="">Select Agent Name</option>');

                let selectedVendorId = prefilledData.vendorId;
                let matchFound = false;
                if (!selectedVendorId || selectedVendorId == "0") {
                    const matched = data.data.find(v => v.vendor_name.trim().toLowerCase() ===
                        prefilledData.vendorName.trim().toLowerCase());
                    if (matched) { selectedVendorId = matched.id; matchFound = true; }
                } else {
                    matchFound = true;
                }

                data.data.forEach(function(vendor) {
                    const isSelected = vendor.id == selectedVendorId ? "selected" : "";
                    $select.append(`<option value="${vendor.id}" ${isSelected}>${vendor.vendor_name}</option>`);
                });
                $select.append('<option value="0">Other</option>');

                $select.select2({ allowClear: true, dropdownParent: $("#claimDetailModal") });

                if (!matchFound) {
                    $select.val("0").trigger("change.select2");
                    $("#other_agent_div").show();
                    $("#other_agent_name").val(prefilledData.vendorName);
                }
            });
        }

        $("#agent_name").on("change", function() {
            if ($(this).val() == "0") {
                $("#other_agent_div").show();
            } else {
                $("#other_agent_div").hide();
                $("#other_agent_name").val("");
            }
        });

        function loadCities() {
            $.getJSON("/city-list", function(response) {
                const data = response.data;
                $(".select2-city").each(function() {
                    const $select = $(this);
                    const isFrom = $select.attr("id") === "journey_from";
                    const selectedId = isFrom ? prefilledData.fromId : prefilledData.uptoId;
                    const selectedName = isFrom ? prefilledData.fromName : prefilledData
                        .uptoName;

                    let finalId = selectedId;
                    if (!finalId || finalId == "0") {
                        const matched = data.find(c => c.city_name.trim().toLowerCase() ===
                            selectedName.trim().toLowerCase());
                        if (matched) finalId = matched.ccid;
                    }

                    $select.empty().append('<option value="">Select City</option>');
                    data.forEach(c => {
                        const isSelected = c.ccid == finalId ? "selected" : "";
                        $select.append(
                            `<option value="${c.ccid}" ${isSelected}>${c.city_name}</option>`
                            );
                    });

                    $select.select2({
                        allowClear: true,
                        dropdownParent: $("#claimDetailModal")
                    });
                });
            });
        }

        function toggleInsuranceAmount() {
            const isYes = $("#insurance_yes").is(":checked");
            const $container = $("#insurance_amount_container");
            const $input = $("#insurance_amount");
            if (isYes) {
                $container.removeClass("d-none");
                $input.prop("required", true);
            } else {
                $container.addClass("d-none");
                $input.prop("required", false).val('');
            }
        }

        $(document).on("change", "input[name='travel_insurance']", toggleInsuranceAmount);
        toggleInsuranceAmount();

        loadVendors();
        loadCities();

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
