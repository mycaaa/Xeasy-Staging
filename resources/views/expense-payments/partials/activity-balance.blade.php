{{-- resources/views/expense-payments/partials/activity-balance.blade.php --}}

@php
    $totalOriginalAmount = 0;

    // echo '<pre>';
    // print_r($allPaidAdvances);
    // echo '</pre>';
@endphp

{{-- ================= ACTIVITY ADVANCES FROM LEDGER ================= --}}
@if(isset($ledgerData) && empty($carriedAdvances))

    @for ($i = 1; $i <= 4; $i++)
        @php
            $advanceAmt = $ledgerData['advance_amt_' . $i] ?? 0;
            $advancePurpose = $ledgerData['advance_purpose_' . $i] ?? 'General Activity';
            $advanceMonth = $ledgerData['advance_month_' . $i] ?? '';
            $advanceDate = $ledgerData['advance_date_' . $i] ?? '';
        @endphp

        @if($advanceAmt > 0)
            @php
                $advanceId = 'ledger_' . $rowId . '_' . $ledgerData['id'].'_'.$i-1;
                $totalOriginalAmount += $advanceAmt;
            @endphp

            <div
    class="balance-card"
    data-advance-id="{{ $advanceId }}"
    data-month="{{ $advanceMonth }}"
    data-payment-date="{{ $advanceDate }}"
    data-category="ledger"
    data-original="{{ $advanceAmt }}"
    style="background:#fff7ed; border-left:2px solid #fb923c; padding:6px 8px; margin-bottom:8px; font-size:9px; line-height:1.2;">
                {{-- <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-weight:600;color:#0d47a1;">Activity Advance</span>
                    <span style="font-size:8px;color:#666;">{{ $advanceMonth }}</span>
                </div> --}}

                <div style="font-size:8px;margin-bottom:4px;">
                    {{ Str::limit($advancePurpose, 30) }}
                </div>

                <div style="display:flex;justify-content:space-between;">
                    <span>Original:</span>
                    <span>₹{{ number_format($advanceAmt) }}</span>
                </div>

                <div style="display:flex;justify-content:space-between;font-weight:700;margin-top:4px;padding-top:4px;border-top:1px dashed #1976d2;">
                    <span style="color:#0d47a1;">Remaining:</span>
                    <span id="balance_{{ $advanceId }}">₹{{ number_format($advanceAmt) }}</span>
                </div>
            </div>
        @endif
    @endfor
@endif


{{-- ================= PAID ADVANCES ================= --}}
@if(isset($allPaidAdvances) && !empty($allPaidAdvances))
    @foreach($allPaidAdvances as $paidIndex => $paid)

        @php
        //  print_r($paid);
            $amount = $paid['payment_amount'] ?? 0;
        @endphp

        @if($amount > 0)
            @php
            $advanceMonth = $paid['month'];
            $advancePaymentDate = $paid['payment_date'];
                // Use the unique activity_id (ara.id) for balance span ID
                $uniqueActivityId = $paid['activity_id'] ?? ($paid['activity_name_id'] ?? uniqid());
                $advanceId = 'paid_' . $rowId . '_' . $uniqueActivityId;
                $totalOriginalAmount += $amount;
            @endphp

           <div
    class="balance-card"
    data-advance-id="{{ $advanceId }}"
    data-month="{{ $advanceMonth }}"
    data-payment-date="{{ $advancePaymentDate }}"
    data-category="paid"
    data-original="{{ $amount }}"
    data-unique-id="{{ $uniqueActivityId }}"
        style="background:#e3f2fd;border-left:3px solid #1976d2;padding:6px 8px;margin-bottom:6px;border-radius:3px;font-size:9px;">

                <div style="font-size:8px;margin-bottom:4px;">
                    {{ Str::limit($paid['activity_name'] ?? 'Activity', 30) }}
                </div>

                <div style="display:flex;justify-content:space-between;">
                    <span>Original:</span>
                    <span>₹{{ number_format($amount) }}</span>
                </div>

                <div style="display:flex;justify-content:space-between;font-weight:700;margin-top:4px;padding-top:4px;border-top:1px dashed #4caf50;">
                    <span style="color:#1b5e20;">Remaining:</span>
                    <span id="balance_{{ $advanceId }}">₹{{ number_format($amount) }}</span>
                </div>
            </div>
        @endif
    @endforeach
@endif


{{-- ================= EXPENSE ADVANCES ================= --}}
@if(isset($expenseAdvances) && !empty($expenseAdvances))
    @foreach($expenseAdvances as $expAdv)
        @if(function_exists('isImprestAdvanceRecord') && isImprestAdvanceRecord($expAdv))
            @continue
        @endif
        @php
        // print_r($expenseAdvances);
            $amount = $expAdv['advance_amount'] ?? 0;
        @endphp

        @if($amount > 0)
            @php
              $advanceMonth = $expAdv['month'];
            $advancePaymentDate = $expAdv['payment_date'];
                $advanceId = 'expense_' . $rowId . '_' . ($expAdv['expense_advance_id'] ?? uniqid());
                $totalOriginalAmount += $amount;
            @endphp

            <div
    class="balance-card"
    data-advance-id="{{ $advanceId }}"
        data-month="{{ $advanceMonth }}"
    data-payment-date="{{ $advancePaymentDate }}"
    data-category="expense"
    data-original="{{ $amount }}"
    style="background:#f3e5f5;border-left:3px solid #9c27b0;padding:6px 8px;margin-bottom:6px;border-radius:3px;font-size:9px;">
                {{-- <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-weight:600;color:#4a148c;">Expense Advance</span>
                    <span style="font-size:8px;color:#666;">{{ $expAdv['month'] ?? '' }}</span>
                </div> --}}

                <div style="font-size:8px;margin-bottom:4px;">Expense</div>

                <div style="display:flex;justify-content:space-between;">
                    <span>Original:</span>
                    <span>₹{{ number_format($amount) }}</span>
                </div>

                <div style="display:flex;justify-content:space-between;font-weight:700;margin-top:4px;padding-top:4px;border-top:1px dashed #9c27b0;">
                    <span style="color:#4a148c;">Remaining:</span>
                    <span id="balance_{{ $advanceId }}">₹{{ number_format($amount) }}</span>
                </div>
            </div>
        @endif
    @endforeach
@endif


{{-- ================= CARRIED ADVANCES ================= --}}
@if(isset($carriedAdvances) && !empty($carriedAdvances))
    @foreach($carriedAdvances as $carAdv)
        @if(function_exists('isImprestAdvanceRecord') && isImprestAdvanceRecord($carAdv))
            @continue
        @endif
        @php
        // print_r($carAdv);
            $amount = $carAdv['amount'] ?? 0;

        @endphp

        @if($amount > 0)
            @php
                $advanceMonth = $carAdv['month']?? 1;
                // $paymentDate = $carAdv['payment_date'];
                $category = $carAdv['category'];
            $advancePaymentDate = $carAdv['payment_date'] ?? '';
              $advanceId = 'carried_' . $rowId . '_' . ($carAdv['advance_id']?? uniqid());
                // $advanceId = $carAdv['advance_id'];
                $totalOriginalAmount += $amount;
                // echo();
                 $activityName = getActivityNameById($carAdv['advance_id'] ?? 0) ?? 'Activity';
            @endphp

           <div
    class="balance-card"
    data-advance-id="{{ $advanceId }}"
        data-month="{{ $advanceMonth }}"
    data-payment-date="{{ $advancePaymentDate }}"
    data-category="carried"
    data-original="{{ $amount }}"
    style="
{{ $category == 'ledger'
    ? 'background:#fff7ed; border-left:2px solid #fb923c; padding:6px 8px; margin-bottom:8px; font-size:9px; line-height:1.2;'
    : ($category == 'paid'
        ? 'background:rgb(227,242,253); border-left:3px solid rgb(25,118,210); padding:6px 8px; margin-bottom:8px; font-size:9px; border-radius:3px;'
        : ($category == 'expense'
            ? 'border-left:2px solid #9c27b0; padding:6px 8px; margin-bottom:8px; background:#f3e5f5; border-radius:3px;'
            : 'background:#ffebee; border-left:3px solid #c62828; padding:6px 8px; margin-bottom:8px; font-size:9px; border-radius:3px;'
        )
    )
}}"
>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-weight:600;color:#b71c1c;">Carried</span>
                    <span style="font-size:8px;color:#666;">@if(!empty($carAdv['month']) && str_contains((string)$carAdv['month'], '/')){{ $carAdv['month'] }}@else{{ \Carbon\Carbon::create()->month((int)$advanceMonth)->format('M') }}/{{ \Carbon\Carbon::parse($advancePaymentDate)->format('y') }}@endif</span>
                </div>

                <div style="font-size:8px;margin-bottom:4px;">
                    {{ Str::limit( $category =="expense" ? "Expense":$activityName ?? 'Carried', 30) }}
                </div>

                <div style="display:flex;justify-content:space-between;">
                    <span>Original:</span>
                    <span>₹{{ number_format($amount) }}</span>
                </div>

                <div style="display:flex;justify-content:space-between;font-weight:700;margin-top:4px;padding-top:4px;border-top:1px dashed #e53935;">
                    <span style="color:#b71c1c;">Remaining:</span>
                    <span id="balance_{{ $advanceId }}">₹{{ number_format($amount) }}</span>
                </div>
            </div>
        @endif
    @endforeach
@endif


{{-- ================= TOTAL ================= --}}
@if($totalOriginalAmount > 0)
    <!-- <div style="margin-top:8px;padding:8px;background:#dde6ff;color:#1e3a8a;border-radius:3px;text-align:center;">
        <div style="font-size:9px;opacity:0.9;">TOTAL REMAINING BALANCE</div>
        <div style="font-size:14px;font-weight:800;">
            ₹{{ number_format($totalOriginalAmount) }}
        </div>
    </div> -->
@else
    <div style="color:#999;font-size:10px;text-align:center;padding:15px;background:white;border-radius:3px;">
        No advances available
    </div>
@endif
