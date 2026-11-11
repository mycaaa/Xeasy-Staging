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
    $defaultRemark = '2/4 Wheeler';
@endphp
<div id="punch-form-section">
    <div class="row mb-1 modal-ncgid" data-modal-ncgid="{{ $cgId }}">
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Travel Date <span class="text-danger">*</span></small>
            @php
                $rawBillDate = $expense_detail->BillDate ?? '';
                $travelDateDisplay = (!empty($rawBillDate) && \App\Support\ZeroDate::has($rawBillDate) && $rawBillDate !== '1970-01-01')
                    ? \App\Helpers\CommonHelper::toDisplayFormat($rawBillDate)
                    : '';
            @endphp
            <input type="text" class="form-control" id="travel_date" name="travel_date"
                value="{{ $travelDateDisplay }}" />
        </div>

        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Vehicle Reg. No. <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="vehicle_reg" name="vehicle_reg"
                value="{{ !empty($expense_detail->punching_data->VehicleReg) ? $expense_detail->punching_data->VehicleReg : ($employee_detail['registration_no'] ?? '') }}" />
        </div>

        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Vehicle Type <span class="text-danger">*</span></small>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input vehicle-type-radio" type="radio" name="vehicle" id="vehicle2w" value="2"
                        {{ (isset($expense_detail->WType) && $expense_detail->WType == '2') 
                            || (empty($expense_detail->WType) && isset($employee_detail['vehicle_type']) && $employee_detail['vehicle_type'] == '2') ? 'checked' : '' }} />
                    <label class="form-check-label" for="vehicle2w">2W</label>
                </div>

                <div class="form-check form-check-inline">
                    <input class="form-check-input vehicle-type-radio" type="radio" name="vehicle" id="vehicle4w" value="4"
                        {{ (isset($expense_detail->WType) && $expense_detail->WType == '4') 
                            || (empty($expense_detail->WType) && isset($employee_detail['vehicle_type']) && $employee_detail['vehicle_type'] == '4') ? 'checked' : '' }} />
                    <label class="form-check-label" for="vehicle4w">4W</label>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-1">
        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">
                Overnight or multi-day travel?
                <i class="ri-info-i info-icon" title="Select Yes if the travel is more than one day or readings are uploaded for multiple dates"></i>
            </small>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="multi_day_trip" id="multi_day_yes" value="yes" />
                    <label class="form-check-label" for="multi_day_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="multi_day_trip" id="multi_day_no" value="no" checked />
                    <label class="form-check-label" for="multi_day_no">No</label>
                </div>
            </div>
        </div>
    </div>
    <div class="row trip-dates" style="display: none;">
        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Trip Started On <span class="text-danger">*</span></small>
            @php
                $firstWheelerRow   = ($expense_detail->wheeler_entries instanceof \Illuminate\Support\Collection)
                    ? $expense_detail->wheeler_entries->first()
                    : $expense_detail->wheeler_entries;
                $rawJourneyStart   = $firstWheelerRow->JourneyStartDt ?? '';
                $journeyStartDisplay = (!empty($rawJourneyStart) && \App\Support\ZeroDate::has($rawJourneyStart))
                    ? \App\Helpers\CommonHelper::toDisplayFormat($rawJourneyStart)
                    : '';
            @endphp
            <input type="text"
                class="form-control"
                value="{{ $journeyStartDisplay }}"
                id="trip_started_on"
                name="trip_started_on"
                placeholder="" />
        </div>
        <div class="col-md-6">
            <small class="form-text text-muted">Trip Ended On <span class="text-danger">*</span></small>
            @php
                $rawJourneyEnd   = $firstWheelerRow->JourneyEndDt ?? '';
                $journeyEndDisplay = (!empty($rawJourneyEnd) && \App\Support\ZeroDate::has($rawJourneyEnd))
                    ? \App\Helpers\CommonHelper::toDisplayFormat($rawJourneyEnd)
                    : '';
            @endphp
            <input type="text"
                class="form-control"
                value="{{ $journeyEndDisplay }}"
                id="trip_ended_on"
                name="trip_ended_on"
                placeholder="" />
        </div>
    </div>
    <div class="table-responsive mb-1 mt-2">
        <table class="table table-bordered align-middle text-center mb-1 punching_form_tbl">
            <thead class="table-light">
                <tr>
                    <th>Sn</th>
                    <th>Opening Reading</th>
                    <th>Closing Reading</th>
                    <th>Km Running</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><input type="number" class="form-control" value="{{ $expense_detail->odomtr_opening ?? '' }}" id="opening_reading" name="opening_reading" placeholder="" /></td>
                    <td><input type="number" class="form-control" value="{{ $expense_detail->odomtr_closing ?? '' }}" id="closing_reading" name="closing_reading" placeholder="" /></td>
                    <td><input type="number" class="form-control" value="{{ $expense_detail->TotKm ?? '' }}" id="km_running" name="km_running" placeholder="" readonly /></td>
                </tr>
            </tbody>
        </table>
    </div>
    @php
        // GPS-ODO-01: where the phone was when each odometer photo was taken
        $gps = \App\Services\Claims\OdometerGps::class;
        $hasOpen = $gps::valid(is_numeric($expense_detail->opening_lat ?? null) ? (float) $expense_detail->opening_lat : null, is_numeric($expense_detail->opening_lng ?? null) ? (float) $expense_detail->opening_lng : null);
        $hasClose = $gps::valid(is_numeric($expense_detail->closing_lat ?? null) ? (float) $expense_detail->closing_lat : null, is_numeric($expense_detail->closing_lng ?? null) ? (float) $expense_detail->closing_lng : null);
        $gpsKm = ($hasOpen && $hasClose) ? $gps::distanceKm($expense_detail->opening_lat, $expense_detail->opening_lng, $expense_detail->closing_lat, $expense_detail->closing_lng) : null;
        $claimedKm = (float) ($expense_detail->TotKm ?? 0);
    @endphp
    {{-- M-02: opening below the previous closing / very long day --}}
    {!! \App\Services\Claims\OdometerContinuity::panelHtml((string) session('expense_connection', 'expense'), (int) ($expense_detail->ClaimYearId ?? 0) ?: (int) session('year_id'), (int) ($expense_detail->ExpId ?? 0)) !!}
    <div class="small mb-2 px-1" id="odo_gps_box">
        <strong>Photo location:</strong>
        Opening
        @if ($hasOpen)
            <a href="{{ $gps::mapUrl($expense_detail->opening_lat, $expense_detail->opening_lng) }}" target="_blank" rel="noopener">{{ round((float) $expense_detail->opening_lat, 5) }}, {{ round((float) $expense_detail->opening_lng, 5) }}</a>
        @else
            <span class="text-muted">not recorded</span>
        @endif
        &nbsp;|&nbsp; Closing
        @if ($hasClose)
            <a href="{{ $gps::mapUrl($expense_detail->closing_lat, $expense_detail->closing_lng) }}" target="_blank" rel="noopener">{{ round((float) $expense_detail->closing_lat, 5) }}, {{ round((float) $expense_detail->closing_lng, 5) }}</a>
        @else
            <span class="text-muted">not recorded</span>
        @endif
        @if ($gpsKm !== null)
            &nbsp;|&nbsp; Straight-line distance <strong>{{ $gpsKm }} km</strong>
            @if ($claimedKm > 0 && $gpsKm > $claimedKm)
                <span class="badge bg-danger ms-1">more than the {{ $claimedKm }} km claimed - check</span>
            @endif
            &nbsp;<a href="https://www.google.com/maps/dir/{{ rawurlencode($expense_detail->opening_lat.','.$expense_detail->opening_lng) }}/{{ rawurlencode($expense_detail->closing_lat.','.$expense_detail->closing_lng) }}" target="_blank" rel="noopener">route</a>
        @endif
    </div>
    <div class="row mb-1">
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Rate Mode</small>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input rate-mode" type="radio" name="rate_mode" id="rate_auto" value="auto" checked>
                    <label class="form-check-label" for="rate_auto">Auto</label>
                </div>
                @if (config('claim_rules.running_km.manual_rate', false))
                <div class="form-check form-check-inline">
                    <input class="form-check-input rate-mode" type="radio" name="rate_mode" id="rate_manual" value="manual">
                    <label class="form-check-label" for="rate_manual">Manual</label>
                </div>
                @endif
                <small class="d-block text-muted">Correct the readings; the rate comes from the employee's eligibility.</small>
            </div>
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Rate per KM (₹)</small>
            <input type="number" step="0.01" class="form-control" id="rate_per_km" name="rate_per_km" readonly />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Total Amount (₹)</small>
            <input type="text" class="form-control font-weight-bold" id="totalAmount" style="background-color: #e9ecef;" name="total_amount" value="{{ $expense_detail->punching_data->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" readonly />
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
<input type="hidden" id="rate_2w" value="{{ $expense_detail->rate_2w ?? 0 }}" />
<input type="hidden" id="rate_4w" value="{{ $expense_detail->rate_4w ?? 0 }}" />
<script>
    $(document).ready(function() {
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

        flatpickr("#travel_date", {
            dateFormat: "d-m-Y",
            enableYearSelection: true,
            maxDate: "today",
            onReady: function() {
                this.isDisabled = false;
            },
        });

        flatpickr("#trip_ended_on", {
            dateFormat: "d-m-Y",
            enableYearSelection: true,
            maxDate: "today",
            onReady: function() {
                this.isDisabled = false;
            },
        });

        flatpickr("#trip_started_on", {
            dateFormat: "d-m-Y",
            enableYearSelection: true,
            maxDate: "today",
            onReady: function() {
                this.isDisabled = false;
            },
        });

        $(document).on("input", "#opening_reading, #closing_reading", function() {
            let opening = parseFloat($("#opening_reading").val()) || 0;
            let closing = parseFloat($("#closing_reading").val()) || 0;
            let kmRunning = closing - opening;

            if (kmRunning > 0) {
                $("#km_running").val(kmRunning);
            } else {
                $("#km_running").val("");
            }
            calculateTotalAmount();
        });

        $(document).on("input", "#rate_per_km", function() {
            if ($("#rate_manual").is(":checked")) {
                calculateTotalAmount();
            }
        });

        $(".rate-mode").change(function() {
            if ($("#rate_manual").is(":checked")) {
                $("#rate_per_km").prop("readonly", false).css("background", "#fff");
            } else {
                $("#rate_per_km").prop("readonly", true).css("background", "#e9ecef");
                updateRatePerKm();
            }
        });

        $(".vehicle-type-radio").on("change", function() {
            if ($("#rate_auto").is(":checked")) {
                updateRatePerKm();
            }
        });

        $('input[name="multi_day_trip"]').on('change', function() {
            if ($(this).val() === 'yes') {
                $('.trip-dates').slideDown();
            } else {
                $('.trip-dates').slideUp();
            }
        });

        function updateRatePerKm() {
            let vehicleType = $("input[name='vehicle']:checked").val();
            let rate = vehicleType == "2" ? parseFloat($("#rate_2w").val()) : parseFloat($("#rate_4w").val());

            if (rate > 0) {
                $("#rate_per_km").val(rate.toFixed(2));
                calculateTotalAmount();
            } else {
                fetchRateFromServer();
            }
        }

        function fetchRateFromServer() {
            let employeeId = $("#employee_id").val();
            let billDate = $("#travel_date").val();
            let vehicleType = $("input[name='vehicle']:checked").val();

            $.post('calculate-rate', {
                employee_id: employeeId,
                bill_date: billDate,
                vehicle_type: vehicleType,
                _token: $('meta[name="csrf-token"]').attr('content')
            }, function(response) {
                if (response.success) {
                    $("#rate_per_km").val(response.rate.toFixed(2));
                    calculateTotalAmount();
                }
            });
        }

        function calculateTotalAmount() {
            let km = parseFloat($("#km_running").val()) || 0;
            let rate = parseFloat($("#rate_per_km").val()) || 0;

            if (km > 0 && rate > 0) {
                $("#totalAmount").val((km * rate).toFixed(2));
            } else {
                $("#totalAmount").val("");
            }
        }

        updateRatePerKm();
    });
</script>