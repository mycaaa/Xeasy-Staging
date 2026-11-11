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
    $defaultRemark = 'Team Meeting Expenses';
@endphp
<div id="punch-form-section">
    <div class="row modal-ncgid" data-modal-ncgid="{{ $cgId }}">
    <div class="col-md-4 mb-3">
        <small class="form-text text-muted">Bill Date <span class="text-danger">*</span></small>
        <input type="text"
            class="form-control"
            value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->punching_data->BillDate ?? $expense_detail->BillDate ?? '') }}"
            id="bill_date"
            name="bill_date" />
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

    <div class="col-12">
        <table class="table table-bordered" id="items_table">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">Sr. No.</th>
                    <th>Particulars</th>
                    <th>Remark</th>
                    <th>Amount</th>
                    <th style="width: 80px;">
                        <button type="button" class="btn btn-sm btn-success" id="add_item">
                            <i class="ri-add-circle-fill"></i>
                        </button>
                    </th>
                </tr>
            </thead>

            <tbody>
                @php
                $predefinedOptions = [
                'Meeting Hall',
                'Lodging',
                'Meals',
                'Business Entertainment',
                'Others',
                ];
                @endphp

                @if(!empty($expense_detail->punching_data->particulars) && count($expense_detail->punching_data->particulars) > 0)
                @foreach($expense_detail->punching_data->particulars as $index => $item)
                @php
                $title = trim($item->Title ?? '');
                @endphp
                <tr>
                    <td class="text-center sr-no">{{ $index + 1 }}</td>

                    <td>
                        @if(empty($title) || in_array($title, $predefinedOptions))
                        <select name="particulars[]" class="form-control">
                            <option value="">-- Select Particular --</option>
                            @foreach ($predefinedOptions as $option)
                            <option value="{{ $option }}" {{ $title === $option ? 'selected' : '' }}>
                                {{ $option }}
                            </option>
                            @endforeach
                        </select>
                        @else
                        <input type="text" name="particulars[]" class="form-control"
                            value="{{ $title }}" placeholder="Particulars" aria-label="Particulars" />
                        @endif
                    </td>

                    <td>
                        <input type="text" name="remark[]" class="form-control"
                            value="{{ $item->Remark }}" placeholder="Remark" />
                    </td>

                    <td>
                        <input type="number" name="amount[]" class="form-control amount_input"
                            value="{{ $item->Amount }}" placeholder="Amount" />
                    </td>

                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger remove_item">
                            <i class="ri-delete-bin-fill"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
                @else
                <tr>
                    <td class="text-center sr-no">1</td>
                    <td>
                        <select name="particulars[]" class="form-control">
                            <option value="">-- Select Particular --</option>
                            @foreach ($predefinedOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="text" name="remark[]" class="form-control" placeholder="Remark" aria-label="Remark" /></td>
                    <td><input type="number" name="amount[]" class="form-control amount_input" placeholder="Amount" aria-label="Amount" /></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger remove_item">
                            <i class="ri-delete-bin-fill"></i>
                        </button>
                    </td>
                </tr>
                @endif
            </tbody>

            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total:</th>
                    <th colspan="2">
                        <input type="text" id="totalAmount" class="form-control" readonly value="{{ $expense_detail->punching_data->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" />
                    </th>
                </tr>
                <tr>
                    <th colspan="3" class="text-end">Filled Amount (₹):</th>
                    <th colspan="2">
                        <input type="text" class="form-control" readonly value="{{ $expense_detail->punching_data->FilledTAmt ?? $expense_detail->FilledTAmt ?? '' }}" />
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="col-12">
        {{-- Tracker #35: Team Meeting participants on punch / verifier / approver screens --}}
        <x-modal.claim_view.participants_panel title="Team Meeting participants"
            :pax="$expense_detail->punching_data->NoOfPAX ?? null"
            :text="$expense_detail->punching_data->PaxDetails ?? ''"
            :others="$expense_detail->punching_data->PaxDetails_Other ?? ''" />
    </div>
    </div>
</div>
<x-modal.claim_view.remark_chat :remarks="$remarkChat" />
<input type="hidden" id="employee_id" value="{{ $expense_detail->CrBy ?? '' }}" />

<script>
    $(document).ready(function() {
        // Flatpickr init
        flatpickr("#bill_date", {
            dateFormat: "d-m-Y",
            maxDate: "today",
        });

        // Update total
        function updateTotal() {
            let total = 0;
            $(".amount_input").each(function() {
                const val = parseFloat($(this).val());
                if (!isNaN(val)) total += val;
            });
            $("#totalAmount").val(total.toFixed(2));
        }

        // Update Sr. No.
        function updateSerialNumbers() {
            $("#items_table tbody tr").each(function(index) {
                $(this).find(".sr-no").text(index + 1);
            });
        }

        // Add new row
        $("#add_item").click(function() {
            const predefinedOptions = [
                'Meeting Hall',
                'Lodging',
                'Meals',
                'Business Entertainment',
                'Others'
            ];

            let optionsHtml = '<option value="">-- Select Particular --</option>';
            predefinedOptions.forEach(opt => {
                optionsHtml += `<option value="${opt}">${opt}</option>`;
            });

            const newRow = `
            <tr>
                <td class="text-center sr-no"></td>
                <td>
                    <select name="particulars[]" class="form-control">
                        ${optionsHtml}
                    </select>
                </td>
                <td><input type="text" name="remark[]" class="form-control" placeholder="Remark" aria-label="Remark" /></td>
                <td><input type="number" name="amount[]" class="form-control amount_input" placeholder="Amount" aria-label="Amount" /></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger remove_item">
                        <i class="ri-delete-bin-fill"></i>
                    </button>
                </td>
            </tr>`;

            $("#items_table tbody").append(newRow);
            updateSerialNumbers();
            updateTotal();
        });

        $(document).on("change", "select[name='particulars[]']", function() {
            const selectedValue = $(this).val();
            if (!selectedValue) return;

            let duplicate = false;

            // Check for duplicates
            $("select[name='particulars[]']").not(this).each(function() {
                if ($(this).val() === selectedValue) {
                    duplicate = true;
                    return false; // break loop
                }
            });

            if (duplicate) {
                showAlert("warning", "ri-alert-line", "This particular is already selected!");
                $(this).val(""); // reset
            }
        });


        // Remove row
        $(document).on("click", ".remove_item", function() {
            if ($("#items_table tbody tr").length > 0) {
                $(this).closest("tr").remove();
                updateSerialNumbers();
                updateTotal();
            } else {
                showAlert("info", "ri-information-line", "At least one row is required");
            }
        });

        // On amount change
        $(document).on("input", ".amount_input", function() {
            updateTotal();
        });

        // Initialize totals and Sr numbers
        updateSerialNumbers();
        updateTotal();

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
