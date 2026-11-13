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
    $defaultRemark = 'Boarding Pass';
@endphp
<div id="punch-form-section">
    <div class="row modal-ncgid" data-modal-ncgid="{{ $cgId }}">
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Booking Date <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="booking_date" name="booking_date" value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->BookingDate ?? $expense_detail->BillDate ?? '') }}" style="min-width: 100%;" />
    </div>

    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Journey From <span class="text-danger">*</span></small>
        <select class="form-control select2-city" id="journey_from" name="journey_from"></select>
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Journey Upto <span class="text-danger">*</span></small>
        <select class="form-control select2-city" id="journey_upto" name="journey_upto"></select>
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Journey Start Date <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="journey_start" name="journey_start" value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->JourneyStartDt ?? '') }}" style="min-width: 100%;" />
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Journey End Date <span class="text-danger">*</span></small>
        <input type="text" class="form-control" id="journey_end" name="journey_end" value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->JourneyEndDt ?? '') }}" style="min-width: 100%;" />
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
<input type="hidden" name="totalAmount" id="totalAmount" value="{{ $expense_detail->FilledTAmt ?? '' }}">

<script>
    $(document).ready(function() {
        const prefilledData = {
            bookingDate: "{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->BookingDate ?? $expense_detail->BillDate ?? '') }}",
            fromId: "{{ \App\Models\CityCategory::canonicalId($expense_detail->punching_data->Journey_From_Id ?? 0) }}",
            fromName: "{{ trim($expense_detail->punching_data->JourneyFrom ?? '') }}",
            uptoId: "{{ \App\Models\CityCategory::canonicalId($expense_detail->punching_data->Journey_Upto_Id ?? 0) }}",
            uptoName: "{{ trim($expense_detail->punching_data->JourneyUpto ?? '') }}",
            startDate: "{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->JourneyStartDt ?? '') }}",
            endDate: "{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->JourneyEndDt ?? '') }}",
        };

        // Initialize Flatpickr for booking and journey dates with prefilled values
        flatpickr("#booking_date", {
            dateFormat: "d-m-Y",
            maxDate: "today",
            defaultDate: prefilledData.bookingDate || null
        });

        flatpickr("#journey_start", {
            dateFormat: "d-m-Y",
            maxDate: "today",
            defaultDate: prefilledData.startDate || null
        });

        flatpickr("#journey_end", {
            dateFormat: "d-m-Y",
            maxDate: "today",
            defaultDate: prefilledData.endDate || null
        });

        // Automatically calculate period_days
        function updatePeriodDays() {
            const start = new Date($("#journey_start").val());
            const end = new Date($("#journey_end").val());
            if (start && end) {
                const diff = Math.max(0, Math.ceil((end - start) / (1000 * 60 * 60 * 24)));
                $("#period_days").val(diff);
            }
        }

        $("#journey_start, #journey_end").on("change", updatePeriodDays);
        updatePeriodDays(); // initialize on load

        // Load cities into select2
        function loadCities(category = null) {
            let url = "/city-list";
            if (category) url += "/" + encodeURIComponent(category);

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
                            const matchedCity = data.find((c) => c.city_name?.trim().toLowerCase() === preName.trim().toLowerCase());
                            if (matchedCity) selectedCityId = matchedCity.ccid;
                        }

                        $select.empty().append('<option value="">Select City</option>');
                        data.forEach((city) => {
                            const isSelected = city.ccid == selectedCityId ? "selected" : "";
                            $select.append(`<option value="${city.ccid}" ${isSelected}>${city.city_name} - ${city.city_category}</option>`);
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