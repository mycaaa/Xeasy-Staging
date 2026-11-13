@php
$totalAmount = 0;
$totalVerified = 0;
$totalApproved = 0;
$totalFinance = 0;
@endphp
<div class="row">
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Bill Date <span class="text-danger">*</span></small>
        <input type="text"
            class="form-control"
            id="bill_date"
            name="bill_date"
            value="{{ \App\Helpers\CommonHelper::toDisplayFormat($expense_detail->BillDate ?? '') }}"
            placeholder="Enter Bill Date">
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Activity Type <span class="text-danger">*</span></small>
        <select name="activity_type" id="activity_type" class="form-control">
            <option value="">-- Select Activity Type --</option>
            @foreach ($activity_types as $typeItem)
            <option value="{{ $typeItem->id }}"
                {{ (isset($expense_detail->activity_type) && $expense_detail->activity_type == $typeItem->id) ? 'selected' : '' }}>
                {{ $typeItem->type_name }}
            </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-1">
        <small class="form-text text-muted">Crops <span class="text-danger">*</span></small>
        <select name="crops[]" id="crops" class="form-control" multiple>
            @php
            $selectedCrops = isset($filledData->crop_list) ? explode(',', $filledData->crop_list) : [];
            @endphp
            @foreach ($crops as $cropItem)
            <option value="{{ $cropItem->id }}"
                {{ in_array($cropItem->id, $selectedCrops) ? 'selected' : '' }}>
                {{ $cropItem->name }}
            </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-1">
        @if ($employee->DepartmentId !== 17)
        <small class="form-text text-muted">Variety</small>
        <select name="variety[]" id="variety" class="form-control" multiple>
            @php
            $selectedVarieties = isset($filledData->variety_list) ? explode(',', $filledData->variety_list) : [];
            @endphp
            @foreach ($varieties as $variety)
            <option value="{{ $variety->id }}"
                {{ in_array($variety->id, $selectedVarieties) ? 'selected' : '' }}>
                {{ $variety->variety_name }}
            </option>
            @endforeach
        </select>
        @else
        <small class="form-text text-muted">Trial No. <span class="text-danger">*</span></small>
        <input type="text" name="trial_no[]" id="trial_no"
            value="{{ $expense_detail->traill_no ?? '' }}"
            placeholder="Enter Trial No." class="form-control">
        @endif
    </div>

</div>
<div class="table-responsive modal-ncgid" data-modal-ncgid="{{ $cgId }}">
    <table class="table table-bordered expense-table" style="margin-top: 10px;">
        <thead class="table-light">
            <tr>
                <th>Expense Head</th>
                <th>Attachment</th>
                <th>Claim Amt</th>
                @if ($viewContext === 'verify')
                <th>Ver. Amt</th>
                <th>Ver. Remark</th>
                @endif
                @if ($viewContext === 'approve')
                <th>Ver. Amt</th>
                <th>Ver. Remark</th>
                <th>Appr. Amt</th>
                <th>Appr. Remark</th>
                @endif
                @if ($viewContext === 'finance')
                <th>Ver. Amt</th>
                <th>Ver. Remark</th>
                <th>Appr. Amt</th>
                <th>Appr. Remark</th>
                <th>Fin. Amt</th>
                <th>Fin. Remark</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($expenses as $expense)
            @php
            $head = $expense['value'];

            $amount = $filledData?->{$head} ?? null;
            $verAmt = $filledData?->{"ver_{$head}"} ?? null;
            $appAmt = $filledData?->{"app_{$head}"} ?? null;
            $finAmt = $filledData?->{"pay_{$head}"} ?? null;

            $verRemark = $filledData?->{"ver_remark_{$head}"} ?? '';
            $appRemark = $filledData?->{"app_remark_{$head}"} ?? '';
            $finRemark = $filledData?->{"pay_remark_{$head}"} ?? '';



            $totalAmount += $amount;
            $totalVerified += $verAmt;
            $totalApproved += $appAmt;
            $totalFinance += $finAmt;

            $fileColumn = str_replace('[]', '', $expense['file_input']);
            $files = isset($filledData->{$fileColumn}) ? json_decode($filledData->{$fileColumn}, true) : [];
            @endphp

            <tr>
                <td style="font-size: 12px;">
                    <input type="file" class="file-input" name="files_{{ $expense['value'] }}[]" multiple hidden>
                    {{ $expense['label'] }}
                    @if ($expense['file_required'])
                    <span class="text-danger">*</span>
                    @endif
                </td>

                <td class="text-center">
                    <div class="file-upload-wrapper">
                        <i class="ri-upload-2-fill upload-icon" title="Upload files"></i>

                        <div class="file-list">
                            @if (is_array($files))
                            @foreach ($files as $file)
                            <div class="d-flex align-items-center justify-content-between my-1 p-1 border rounded bg-white shadow-sm" style="min-width: 180px;">
                                <span class="file-name text-truncate" data-preloaded="1" data-file="{{ $file }}" style="cursor: pointer; max-width: 140px;" title="{{ $file }}">
                                    {{ $file }}
                                </span>
                                <div class="d-flex align-items-center gap-1 ms-1">
                                    <i class="ri-exchange-line switch-expense-head text-primary" title="Switch to another expense head" style="cursor: pointer; font-size: 15px;"></i>
                                    <i class="ri-delete-bin-line remove-file text-danger" title="Remove file" style="cursor: pointer; font-size: 15px;"></i>
                                </div>
                            </div>
                            @endforeach
                            @endif
                        </div>
                    </div>
                </td>

                {{-- CLAIM AMOUNT --}}
                <td>
                    <input
                        type="text"
                        class="form-control amount claim"
                        value="{{ $amount }}"
                        @if(in_array($viewContext, ['verify', 'approve' , 'finance' ]))
                        readonly
                        @endif>
                </td>

                {{-- VERIFY-PENDING --}}
                @if ($viewContext === 'verify')
                <td><input type="text" class="form-control amount verified" value="{{ $verAmt }}"></td>
                <td><input type="text" class="form-control remark" value="{{ $verRemark }}"></td>
                @endif

                {{-- APPROVE-PENDING --}}
                @if ($viewContext === 'approve')
                {{-- Verified fields readonly --}}
                <td><input type="text" class="form-control amount verified" value="{{ $verAmt }}" readonly></td>
                <td><input type="text" class="form-control remark" value="{{ $verRemark }}" readonly></td>

                {{-- Approver editable --}}
                <td><input type="text" class="form-control amount approved" value="{{ $appAmt }}"></td>
                <td><input type="text" class="form-control remark" value="{{ $appRemark }}"></td>
                @endif

                {{-- FINANCE-PENDING --}}
                @if ($viewContext === 'finance')
                {{-- Verified readonly --}}
                <td><input type="text" class="form-control amount verified" value="{{ $verAmt }}" readonly></td>
                <td><input type="text" class="form-control remark" value="{{ $verRemark }}" readonly></td>

                {{-- Approved readonly --}}
                <td><input type="text" class="form-control amount approved" value="{{ $appAmt }}" readonly></td>
                <td><input type="text" class="form-control remark" value="{{ $appRemark }}" readonly></td>

                {{-- Finance editable --}}
                <td><input type="text" class="form-control amount finance" value="{{ $finAmt }}"></td>
                <td><input type="text" class="form-control remark" value="{{ $finRemark }}"></td>
                @endif
            </tr>
            @endforeach

            {{-- TOTAL ROW --}}
            <tr style="background-color: #f8f9fa; font-weight: bold;">
                <td colspan="2" class="text-start">Total:</td>

                {{-- Claim total --}}
                <td><input id="totalAmount" type="text" class="form-control text-end" value="{{ number_format($totalAmount) }}" readonly></td>

                @if ($viewContext === 'verify')
                <td><input id="totalVerified" type="text" class="form-control text-end" value="{{ number_format($totalVerified) }}" readonly></td>
                <td></td>
                @endif

                @if ($viewContext === 'approve')
                <td><input id="totalVerified" type="text" class="form-control text-end" value="{{ number_format($totalVerified) }}" readonly></td>
                <td></td>

                <td><input id="totalApproved" type="text" class="form-control text-end" value="{{ number_format($totalApproved) }}" readonly></td>
                <td></td>
                @endif

                @if ($viewContext === 'finance')
                <td><input id="totalVerified" type="text" class="form-control text-end" value="{{ number_format($totalVerified) }}" readonly></td>
                <td></td>

                <td><input id="totalApproved" type="text" class="form-control text-end" value="{{ number_format($totalApproved) }}" readonly></td>
                <td></td>

                <td><input id="totalFinance" type="text" class="form-control text-end" value="{{ number_format($totalFinance) }}" readonly></td>
                <td></td>
                @endif
            </tr>
        </tbody>
    </table>
</div>
@php
    $needsParticipants = app(\App\Services\ClaimRules\ClaimSubmissionValidator::class)->activityRequiresParticipants((int) ($claimId ?? 0), session('expense_connection'));
    $participantFiles = $filledData?->file_participant_list ?? null;
@endphp
@if($needsParticipants || !empty($filledData?->participant_names) || (!empty($participantFiles) && $participantFiles !== '[]'))
    {{-- Tracker #35: activity participants on punch / verifier / approver screens --}}
    <x-modal.claim_view.participants_panel title="Activity participants"
        :text="$filledData?->participant_names ?? ''"
        :department="$filledData?->participant_department ?? null"
        :count="$filledData?->participant_count ?? null"
        :files="$participantFiles ?? []" />
@endif
<script>
    $(document).ready(function() {


        $(document).on('input', '.amount', function() {
            if (typeof window.updateTotals === 'function') {
                window.updateTotals();
            }
        });

        if (typeof window.updateTotals === 'function') {
            window.updateTotals();
        }

        $('#activity_type').select2({
            dropdownParent: $('#claimDetailModal'),
            width: '100%',
            placeholder: 'Select Activity Type',
            allowClear: true
        });

        $('#crops').select2({
            dropdownParent: $('#claimDetailModal'),
            width: '100%',
            placeholder: 'Select Crops',
            allowClear: true
        });

        $('#variety').select2({
            dropdownParent: $('#claimDetailModal'),
            width: '100%',
            placeholder: 'Select Varieties',
            allowClear: true
        });

        flatpickr("#bill_date", {
            dateFormat: "d-m-Y",
            enableYearSelection: true,
            maxDate: "today",
            onReady: function() {
                this.isDisabled = false;
            },
        });


        $(document).on('change', '#crops', function() {
            var selectedCrops = $(this).val();

            if (selectedCrops && selectedCrops.length > 0) {
                $.ajax({
                    url: '/get-varieties',
                    method: 'POST',
                    data: {
                        crop_ids: selectedCrops,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        $('#variety').empty();
                        $.each(response, function(index, variety) {
                            $('#variety').append(
                                $('<option>', {
                                    value: variety.id,
                                    text: variety.variety_name
                                })
                            );
                        });
                        $('#variety').trigger('change');
                    }
                });
            } else {
                $('#variety').empty().trigger('change');
            }
        });



        $(document).off('click', '.upload-icon');
        $(document).off('change', '.file-input');
        $(document).off('click', '.remove-file');

        let fileDialogOpen = false;



        $(document).on('click', '.upload-icon', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (fileDialogOpen) return;


            const input = $(this).closest('tr').find('.file-input')[0];
            if (!input) {
                console.warn('No file input found for this upload icon.');
                return;
            }

            fileDialogOpen = true;
            input.click();
        });



        $(document).on('change', '.file-input', function() {
            const input = this;
            const fileListDiv = $(this).closest('tr').find('.file-list');



            setTimeout(() => {
                fileDialogOpen = false;
            }, 300);

            const existingFiles = new Set(
                fileListDiv.find('.file-name').map(function() {
                    return $(this).data('file');
                }).get()
            );

            Array.from(input.files).forEach((file) => {
                if (existingFiles.has(file.name)) {
                    showAlert("danger", "ri-error-warning-line", `File "${file.name}" is already selected.`);
                    return;
                }

                const fileDiv = $('<div></div>');
                const fileNameLink = $('<span class="file-name"></span>')
                    .text(file.name)
                    .attr('data-preloaded', 0)
                    .attr('data-file', file.name)
                    .css('cursor', 'pointer')
                    .on('click', function() {
                        const fileURL = URL.createObjectURL(file);
                        window.open(fileURL, '_blank');
                    });

                const removeBtn = $('<i class="ri-delete-bin-line remove-file" title="Remove file"></i>');
                removeBtn.on('click', function() {
                    removeFile(input, file.name);
                    fileDiv.remove();
                });

                fileDiv.append(fileNameLink, removeBtn);
                fileListDiv.append(fileDiv);
            });
        });



        $(document).on('click', '.remove-file', function() {
            const parentDiv = $(this).parent();
            const fileName = parentDiv.find('.file-name').data('file');
            const isPreloaded = parentDiv.find('.file-name').data('preloaded') == 1;
            const input = $(this).closest('.file-upload-wrapper').find('.file-input')[0];

            if (!confirm(`Are you sure you want to remove "${fileName}"?`)) return;

            if (isPreloaded) {
                parentDiv.attr('data-remove', '1').hide();
            } else {
                removeFile(input, fileName);
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



        function updateTotals() {
            $('#totalAmount').val(numberFormat(sumInputs('.claim')));
            $('#totalVerified').val(numberFormat(sumInputs('.verified')));
            $('#totalApproved').val(numberFormat(sumInputs('.approved')));
            $('#totalFinance').val(numberFormat(sumInputs('.finance')));
        }

        function sumInputs(selector) {
            let total = 0;
            $(selector).each(function() {
                total += parseFloat($(this).val().replace(/,/g, '') || 0);
            });
            return total;
        }

        function numberFormat(num) {
            return num.toLocaleString('en-IN', {
                maximumFractionDigits: 2
            });
        }

        window.updateTotals = updateTotals;
        updateTotals();
    });
</script>
