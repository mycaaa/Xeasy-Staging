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
    $defaultRemark = 'Hired Vehicle';
    $pd = $expense_detail->punching_data ?? null;

    $isValidDate = function ($d) {
        return !empty($d) &&
            \App\Support\ZeroDate::has($d) &&
            \App\Support\ZeroDate::has($d) &&
            !str_starts_with((string) $d, '0000-00-00') &&
            !str_starts_with((string) $d, '1970-01-01') &&
            $d !== '30-11--0001';
    };

    $rawBillDate = $isValidDate($pd->BillDate ?? null)
        ? $pd->BillDate
        : ($isValidDate($expense_detail->BillDate ?? null) ? $expense_detail->BillDate : null);

    $billDateDisplay = $rawBillDate ? \App\Helpers\CommonHelper::toDisplayFormat($rawBillDate) : '';
@endphp
<div id="punch-form-section">
    <div class="row modal-ncgid" data-modal-ncgid="{{ $cgId }}">
        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Billing Person <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="billing_person" name="billing_person"
                value="{{ $pd->BillingName ?? ($employee_detail['employee_detail']['full_name'] ?? '') }}"
                style="min-width: 100%;" />
        </div>

        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Billing Address</small>
            <input type="text" class="form-control" id="billing_address" name="billing_address"
                value="{{ $pd->BillingAddress ?? '' }}" style="min-width: 100%;" />
        </div>

        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Agency Name <span class="text-danger">*</span></small>
            <select class="form-control select2-vendor" id="agency_name" name="agency_name"></select>
            <div id="other_agency_div" style="display:none;" class="mt-2">
                <input type="text" class="form-control" id="other_agency_name" name="other_agency_name"
                    placeholder="Enter Agency Name" aria-label="Enter Agency Name" />
            </div>
        </div>

        <div class="col-md-6 mb-1">
            <small class="form-text text-muted">Agency Address <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="agency_address" name="agency_address"
                value="{{ $pd->AgencyAddress ?? '' }}" style="min-width: 100%;" />
        </div>

        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Invoice No.</small>
            <input type="text" class="form-control" id="invoice_no" name="invoice_no"
                value="{{ $pd->Invoice ?? '' }}" style="min-width: 100%;" />
        </div>

        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Bill Date <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="date_of_travel" name="date_of_travel"
                value="{{ $billDateDisplay }}"
                style="min-width: 100%;" />
        </div>


        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Trip Started On <span class="text-danger">*</span> </small>
            <input type="text"
                value="{{ \App\Helpers\CommonHelper::toDisplayFormat($pd->JourneyStartDt ?? '') }}"
                class="form-control" id="journey_start" name="journey_start" placeholder="" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Trip Ended On <span class="text-danger">*</span></small>
            <input type="text"
                value="{{ \App\Helpers\CommonHelper::toDisplayFormat($pd->JourneyEndDt ?? '') }}"
                class="form-control" id="journey_end" name="journey_end" placeholder="" />
        </div>

        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Class / Reg </small>
            <input type="text" class="form-control" id="vehicle_class" name="vehicle_class"
                value="{{ $pd->VehicleReg ?? '' }}" style="min-width: 100%;" />
        </div>

        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Charges Type <span class="text-danger">*</span></small>
            <select class="form-control" id="charges_type" name="charges_type">
                <option value="">Select Type</option>
                <option value="Per KM"
                    {{ ($pd->DailyBasisCharges ?? '') === 'Per KM' ? 'selected' : '' }}>Per
                    KM</option>
                <option value="Fixed"
                    {{ ($pd->DailyBasisCharges ?? '') === 'Fixed' ? 'selected' : '' }}>Fixed
                </option>
                <option value="Hourly"
                    {{ ($pd->DailyBasisCharges ?? '') === 'Fixed+Fuel' ? 'selected' : '' }}>
                    Fixed+Fuel</option>
            </select>
        </div>

        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Total Amount <span class="text-danger">*</span></small>
            <input type="text" class="form-control" id="totalAmount" name="totalAmount"
                value="{{ $expense_detail->FilledTAmt ?? '' }}" style="min-width: 100%;" />
        </div>
        <div class="col-md-4 mb-1">
            <small class="form-text text-muted">Filled Amount (₹)</small>
            <input type="text" class="form-control" readonly
                value="{{ $pd->FilledTAmt ?? ($expense_detail->FilledTAmt ?? '') }}" />
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
            vendorId: "{{ $pd->Vendor_Id ?? 0 }}",
            vendorName: "{{ trim($pd->AgencyName ?? '') }}",
            startId: "{{ \App\Models\CityCategory::canonicalId($pd->Journey_Start_Id ?? 0) }}",
            startName: "{{ trim($pd->JourneyStart ?? '') }}",
            endId: "{{ \App\Models\CityCategory::canonicalId($pd->Journey_End_Id ?? 0) }}",
            endName: "{{ trim($pd->JourneyEnd ?? '') }}",
        };
        loadAgencies();
        loadCities();

        flatpickr("#date_of_travel", {
            dateFormat: "d-m-Y",
            maxDate: "today",
        });

        flatpickr("#journey_start", {
            enableTime: true,
            dateFormat: "d-m-Y H:i",
            maxDate: "today",
            time_24hr: true,
        });
        flatpickr("#journey_end", {
            enableTime: true,
            dateFormat: "d-m-Y H:i",
            maxDate: "today",
            time_24hr: true,
        });

        function decodeHtmlEntities(str) {
            const textarea = document.createElement("textarea");
            textarea.innerHTML = str;
            return textarea.value;
        }

        function loadAgencies() {
            $.ajax({
                url: "/vendor-list/Agency",
                type: "GET",
                dataType: "json",
                success: function(data) {
                    const $select = $("#agency_name");
                    $select.empty().append('<option value="">Select Agency</option>');
                    let selectedId = prefilledData.vendorId;
                    const vendorName = decodeHtmlEntities(prefilledData.vendorName || "")
                        .trim()
                        .toLowerCase();
                    let matchFound = false;
                    if (!selectedId || selectedId == "0") {
                        const matched = data.data.find((v) => {
                            const name = decodeHtmlEntities(v.vendor_name || "")
                                .trim()
                                .toLowerCase();
                            return name === vendorName;
                        });
                        if (matched) {
                            selectedId = matched.id;
                            matchFound = true;
                        }
                    } else {
                        matchFound = true;
                    }
                    data.data.forEach((vendor) => {
                        const isSelected = vendor.id == selectedId ? "selected" : "";
                        $select.append(
                            `<option value="${vendor.id}" ${isSelected}>${decodeHtmlEntities(vendor.vendor_name)}</option>`
                            );
                    });
                    $select.append('<option value="0">Other</option>');
                    $select.select2({
                        allowClear: true,
                        dropdownParent: $("#claimDetailModal"),
                        minimumInputLength: 0,
                    });

                    // if no match found, show Other input with prefilled name
                    if (!matchFound) {
                        $select.val("0").trigger("change.select2");
                        $("#other_agency_div").show();
                        $("#other_agency_name").val(prefilledData.vendorName);
                    }
                },
            });
        }

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
                        const isStart = $select.attr("id") === "journey_start";
                        const preId = isStart ? prefilledData.startId : prefilledData.endId;
                        const preName = isStart ? prefilledData.startName : prefilledData
                            .endName;
                        let selectedCityId = preId;
                        if (!selectedCityId || selectedCityId == "0") {
                            const matchedCity = data.find((c) => c.city_name?.trim()
                                .toLowerCase() === preName.trim().toLowerCase());
                            if (matchedCity) selectedCityId = matchedCity.ccid;
                        }
                        $select.empty().append('<option value="">Select City</option>');
                        data.forEach((city) => {
                            const isSelected = city.ccid == selectedCityId ?
                                "selected" : "";
                            $select.append(
                                `<option value="${city.ccid}" ${isSelected}>${city.city_name} - ${city.city_category}</option>`
                                );
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

        $(document).on("change", "#agency_name", function(e) {
            e.preventDefault();
            const BASE_URL = "{{ url('/') }}";
            const vendorId = $(this).val();
            if (vendorId == "0") {
                $("#other_agency_div").show();
                $("#agency_address").val("");
                return;
            }
            $("#other_agency_div").hide();
            $("#other_agency_name").val("");
            if (!vendorId) return;
            const button = this;
            $.ajax({
                url: BASE_URL + `/vendors/` + vendorId + "/edit",
                type: "GET",
                dataType: "json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                beforeSend: function() {
                    startLoader({
                        currentTarget: button
                    });
                },
                success: function(response) {
                    if (response.success) {
                        const vendor = response.data;
                        $("#agency_address").val(vendor.vendor_address || "");
                    } else {
                        showAlert("danger", "ri-error-warning-line", response.message || "Error fetching vendor.");
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.message || "Failed to fetch vendor.";
                    showAlert("danger", "ri-error-warning-line", errorMsg);
                },
                complete: function() {
                    endLoader({
                        currentTarget: button
                    });
                },
            });
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
