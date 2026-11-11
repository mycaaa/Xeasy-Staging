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
    $defaultRemark = 'Air Fare';
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
            <small class="form-text text-muted">PNR Number <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="pnr_number" name="pnr_number" value="{{ $expense_detail->punching_data->PNRNo ?? '' }}" style="min-width: 100%;" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Booking Date <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="booking_date" name="booking_date" value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->BookingDate ?? '') }}" style="min-width: 100%;" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Journey Date <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="journey_date" name="journey_date" value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->JourneyStartDt ?? '') }}" style="min-width: 100%;" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">
                Period
                <i class="ri-info-i info-icon" title="Between Booking & Journey Date"></i>
            </small>
            <input type="number" class="form-control" id="period_days" name="period_days" value="{{ $expense_detail->punching_data->StayDuration ?? 0 }}" readonly style="min-width: 100%;" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Flight Name / Number</small>
            <input type="text" class="form-control" id="flight_number" name="flight_number" value="{{ $expense_detail->punching_data->TrainBusName ?? '' }}" style="min-width: 100%;" />
        </div>

        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Journey From <span class="text-danger">*</span></small>
            <select class="form-control select2-city" id="journey_from" name="journey_from">
            </select>
        </div>
        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Journey Upto <span class="text-danger">*</span></small>
            <select class="form-control select2-city" id="journey_upto" name="journey_upto">
            </select>
        </div>

        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Class <span class="text-danger">*</span></small>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_class" id="class_economy" value="Economy" {{ ($expense_detail->punching_data->Class ?? '') === 'Economy' ? 'checked' : '' }} />
                    <label class="form-check-label" for="class_economy">Economy</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_class" id="class_business" value="Business" {{ ($expense_detail->punching_data->Class ?? '') === 'Business' ? 'checked' : '' }} />
                    <label class="form-check-label" for="class_business">Business</label>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Travel Insurance <span class="text-danger">*</span></small>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_insurance" id="insurance_yes" value="1" {{ ($expense_detail->punching_data->IsTravelInsurance ?? '') === '1' ? 'checked' : '' }} />
                    <label class="form-check-label" for="insurance_yes">Yes</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="travel_insurance" id="insurance_no" value="0" {{ ($expense_detail->punching_data->IsTravelInsurance ?? '') === '0' ? 'checked' : '' }} />
                    <label class="form-check-label" for="insurance_no">No</label>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-1 d-none" id="insurance_amount_container">
            <small class="form-text text-muted">Insurance Amount <span class="text-danger">*</span></small>
            <input type="number" class="form-control" id="insurance_amount" name="insurance_amount"
                value="{{ $expense_detail->punching_data->TravelInsurance ?? '' }}"
                min="0" style="min-width: 100%;" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Amount <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="totalAmount" name="totalAmount"
                value="{{ $expense_detail->FilledTAmt ?? '' }}" style="min-width: 100%;" />
        </div>
        <div class="col-md-4 mb-1">
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
        const prefilledData = {
            vendorId: "{{ $expense_detail->punching_data->Vendor_Id ?? 0 }}",
            vendorName: "{{ trim($expense_detail->punching_data->Agent ?? '') }}",
            fromId: "{{ $expense_detail->punching_data->Journey_From_Id ?? 0 }}",
            fromName: "{{ trim($expense_detail->punching_data->JourneyFrom ?? '') }}",
            uptoId: "{{ $expense_detail->punching_data->Journey_Upto_Id ?? 0 }}",
            uptoName: "{{ trim($expense_detail->punching_data->JourneyUpto ?? '') }}",
        };

        flatpickr("#booking_date, #journey_date", {
            dateFormat: "d-m-Y",
            maxDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                const bookingStr = $("#booking_date").val();
                const journeyStr = $("#journey_date").val();
                if (bookingStr && journeyStr) {
                    const [bDay, bMonth, bYear] = bookingStr.split("-");
                    const [jDay, jMonth, jYear] = journeyStr.split("-");
                    const booking = new Date(`${bYear}-${bMonth}-${bDay}`);
                    const journey = new Date(`${jYear}-${jMonth}-${jDay}`);
                    const diffTime = journey - booking;
                    const diffDays = Math.max(0, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
                    $("#period_days").val(diffDays);
                }
            },
        });

        function loadVendors(type = null) {
            let url = "/vendor-list";
            if (type) url += "/" + encodeURIComponent(type);

            $.ajax({
                url: url,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    const $select = $("#agent_name");
                    $select.empty().append('<option value="">Select Agent Name</option>');

                    let selectedVendorId = prefilledData.vendorId;
                    const vendorName = prefilledData.vendorName?.trim().toLowerCase();
                    let matchFound = false;

                    if (!selectedVendorId || selectedVendorId == "0") {
                        const matchedVendor = data.data.find(v => v.vendor_name?.trim().toLowerCase() === vendorName);
                        if (matchedVendor) {
                            selectedVendorId = matchedVendor.id;
                            matchFound = true;
                        }
                    } else {
                        matchFound = true;
                    }

                    data.data.forEach(function(vendor) {
                        const isSelected = vendor.id == selectedVendorId ? "selected" : "";
                        $select.append(`<option value="${vendor.id}" ${isSelected}>${vendor.vendor_name}</option>`);
                    });
                    $select.append('<option value="0">Other</option>');

                    $select.select2({
                        allowClear: true,
                        dropdownParent: $("#claimDetailModal"),
                        minimumInputLength: 0,
                    });

                    if (!matchFound) {
                        $select.val("0").trigger("change.select2");
                        $("#other_agent_div").show();
                        $("#other_agent_name").val(prefilledData.vendorName);
                    }
                },
                error: function(xhr) {},
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

        function loadCities(category = null) {
            let url = "/airport-list";
            if (category) {
                url += "/" + encodeURIComponent(category);
            }

            $.ajax({
                url: url,
                type: "GET",
                dataType: "json",
                success: function(response) {
                    const data = response.data;

                    $(".select2-city").each(function() {
                        const $select = $(this);
                        const isFrom = $select.attr("id") === "journey_from";
                        const preId = isFrom ? prefilledData.fromId : prefilledData.uptoId;
                        const preName = isFrom ? prefilledData.fromName : prefilledData.uptoName;
                        let selectedCityId = preId;

                        if (!selectedCityId || selectedCityId == "0") {
                            const matchedCity = data.find((c) => c.city?.trim().toLowerCase() === preName.trim().toLowerCase());
                            if (matchedCity) {
                                selectedCityId = matchedCity.id;
                            }
                        }

                        $select.empty().append('<option value="">Select City</option>');
                        data.forEach(function(city) {
                            const isSelected = city.id == selectedCityId ? "selected" : "";

                            const optionText = `${city.name} - ${city.city}`;
                            $select.append(`<option value="${city.id}" ${isSelected}>${optionText}</option>`);
                        });

                        $select.select2({
                            allowClear: true,
                            dropdownParent: $("#claimDetailModal"),
                            minimumInputLength: 0,
                        });
                    });
                },
                error: function(xhr) {},
            });
        }

        function toggleInsuranceAmount() {
            const isYes = $("#insurance_yes").is(":checked");
            const $amountContainer = $("#insurance_amount_container");
            const $amountInput = $("#insurance_amount");

            if (isYes) {
                $amountContainer.removeClass("d-none");
                $amountInput.prop("required", true);
            } else {
                $amountContainer.addClass("d-none");
                $amountInput.prop("required", false).val("");
            }
        }

        toggleInsuranceAmount();

        $(document).on("change", "input[name='travel_insurance']", toggleInsuranceAmount);

        loadVendors("Agent");
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