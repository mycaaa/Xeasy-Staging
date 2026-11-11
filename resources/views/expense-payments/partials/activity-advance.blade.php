{{-- resources/views/expense-payments/partials/activity-advance.blade.php --}}

@php
// Calculate total general activity advance from ledgerData
$generalActivityAdvance = 0;
$generalActivityDetails = [];
// Check all advance amounts in ledgerData that are for Activity purpose
for ($i = 1; $i <= 4; $i++) {

    $advanceAmt = @$ledgerData['advance_amt_' . $i] ?? 0;
    $advancePurpose = $ledgerData['advance_purpose_' . $i] ?? '' ;
    $advanceMonth = $ledgerData['advance_month_' . $i] ?? '' ;
    $advanceDate = $ledgerData['advance_date_' . $i] ?? '' ;
// echo $advanceAmt;
    if ($advanceAmt > 0) {
        $generalActivityAdvance += (float)$advanceAmt;
        $generalActivityDetails[] = [
            'amount' => $advanceAmt,
            'month' => $advanceMonth,
            'date' => $advanceDate,
            'purpose' => $advancePurpose,
            'id' => $ledgerData['id']
        ];
    }
}


$generalActivityBalance = $generalActivityAdvance;
@endphp

{{-- General Activity Balance Section --}}
@if($generalActivityBalance > 0 && empty($carriedAdvances))
<div style="background:#fff7ed; border-left:2px solid #fb923c; padding:6px 8px; margin-bottom:8px; font-size:9px; line-height:1.2;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
        <span style="font-weight:600; color:#c2410c;">{{ $generalActivityDetails[0]['purpose'] ?? "Activity" }}</span>
        {{-- <span style="background:#ffedd5; padding:1px 6px; border-radius:10px; font-weight:600;">
            ₹{{ number_format($generalActivityBalance, 0) }}
        </span> --}}
    </div>

    {{-- @if(count($generalActivityDetails) > 0)
    <div style="font-size:8px; color:#666; margin-bottom:4px;">
        @foreach($generalActivityDetails as $detail)
        <div style="justify-content:space-between;">
            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              ({{ $detail['month'] }})
            </span>
            <span>₹{{ number_format($detail['amount'], 0) }}</span>
        </div>
        @endforeach
    </div>
    @endif --}}
{{-- {{ print_r($generalActivityDetails); }} --}}
@foreach($generalActivityDetails as $index => $detail)
 <div style="font-size:8px; color:#666; margin-bottom:4px;margin-top:4px;">
        {{-- @foreach($generalActivityDetails as $detail) --}}
        <div style="justify-content:space-between;">
            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              ({{ $detail['month'] }})
            </span>
            <span>₹{{ number_format($detail['amount'], 0) }}</span>
        </div>
        {{-- @endforeach --}}

    </div>
    <input type="number"
        name="general_activity_adjustment[{{ $rowId }}]"
        id="adj_general_activity_{{ $rowId }}_{{$detail['id']}}_{{ $index }}"
        class="form-control adjustment-input adj-general-activity"
        value="0"
        data-row-id="{{ $rowId }}"
        data-index="{{ $index }}"
        data-genral-actid="{{ $detail['id'] }}"
        data-original="{{ $detail['amount']}}"
        data-month="{{ $detail['month'] }}"
        data-payment-date="{{ $detail['date'] }}"
        data-max="{{ $detail['amount'] }}"
        min="0"
        max="{{ $detail['amount'] }}"
        step="1"
        placeholder="Adjust amount" aria-label="Adjust amount"
        style="width:100%; height:24px; font-size:9px; text-align:center; border:1.5px solid #fb923c; border-radius:3px; padding:2px 4px; font-weight:600;">
@endforeach
</div>
@endif

{{-- Activity-wise Settlement --}}
@foreach($activityFinanced as $actId => $actData)
@php
$financed = (float)($actData['financed'] ?? 0);
$advance = (float)($actData['advance'] ?? 0);
$name = $actData['activity_name'];
$advanceMonth = $actData['advance_month'] ?? null;
$claimId = $actData['claim_id'] ?? $actId;

// Find specific advance for this activity entry
$activitySpecificAdvance = 0;
$paidAdvanceId = null;
$paidAdvanceMonth = $advanceMonth;
$paidAdvanceDate = $actData['payment_date'] ?? null;
$activityAdvanceId = $actData['activity_advance_id'] ?? null;

// If we already have advance info from the data structure, use it directly
if ($advance > 0) {
    $activitySpecificAdvance = $advance;
    // Try to find matching paid advance for additional info
    foreach($allPaidAdvances as $paidAdvance) {
        if(isset($paidAdvance['activity_name_id']) && $paidAdvance['activity_name_id'] == $claimId) {
            // Match by activity_advance_id if available for exact match
            if ($activityAdvanceId && isset($paidAdvance['activity_advance_id']) && $paidAdvance['activity_advance_id'] == $activityAdvanceId) {
                $paidAdvanceId = $paidAdvance['advance_request_id'] ?? null;
                $paidAdvanceMonth = $paidAdvance['month'] ?? $paidAdvanceMonth;
                $paidAdvanceDate = $paidAdvance['payment_date'] ?? $paidAdvanceDate;
                break;
            }
        }
    }
    if (!$paidAdvanceId) {
        $paidAdvanceId = $activityAdvanceId ?? ('advance_' . $actId);
    }
} else {
    // Legacy path: look up in allPaidAdvances
    foreach($allPaidAdvances as $paidAdvance) {
        if(isset($paidAdvance['activity_name_id']) && $paidAdvance['activity_name_id'] == $claimId) {
            $activitySpecificAdvance = (float)($paidAdvance['payment_amount'] ?? 0);
            $paidAdvanceId = $paidAdvance['advance_request_id'] ?? null;
            $paidAdvanceMonth = $paidAdvance['month'];
            $paidAdvanceDate = $paidAdvance['payment_date'];
            break;
        }
    }
}

$availableAdvance = max($advance, $activitySpecificAdvance);
$fromSame = (int) floor(min($financed, $availableAdvance) / 1000) * 1000;
$remaining = $financed - $fromSame;

// Create unique advance ID for this activity
$advanceId = 'activity_' . $actId . '_' . ($paidAdvanceId ?? 'advance');

@endphp
 @if($availableAdvance > 0)
<div style="background:#e3f2fd; border-left:3px solid #1976d2; padding:6px; margin-bottom:8px; font-size:9px; border-radius:3px;" 
     id="activity_card_{{ $rowId }}_{{ $actId }}" 
     data-advance-id="{{ $advanceId }}">
    
    <div style="display:flex; justify-content:space-between; font-weight:bold; margin-bottom:4px;">
        <span>{{ Str::limit($name, 20) }}</span>
        {{-- <span style="color:#1976d2;">₹{{ number_format($financed, 0) }}</span> --}}
    </div>

    {{-- @if($paidAdvanceMonth)
    <div style="color:#666; font-size:8px; margin-bottom:4px;">
        Month: {{$paidAdvanceMonth }}
    </div>
    @endif --}}

    {{-- Same Activity Advance Input --}}
   
    <div style="margin-bottom:4px;">
          <div style=" justify-content:space-between; color:#2e7d32; margin-bottom:2px;">
             <span>({{ \Carbon\Carbon::create()->month((int)$paidAdvanceMonth)->format('M') }}/ @if(!empty($paidAdvanceDate) && \App\Support\ZeroDate::has($paidAdvanceDate))
                             {{ \Carbon\Carbon::parse($paidAdvanceDate)->format('y') }}
                        @endif ) </span>
            <span> {{ number_format($availableAdvance, 0) }}</span>
        </div>
        <div style="display:none; justify-content:space-between; color:#2e7d32; margin-bottom:2px;">
            <span>Remaining : </span>
            <span> ₹<span id="same_available_{{ $rowId }}_{{ $actId }}">{{ number_format($availableAdvance, 0) }}</span></span>
        </div>
        <input type="number"
            name="activity_same_advance[{{ $rowId }}][{{ $activityAdvanceId ?? $claimId }}]"
            id="adj_activity_same_{{ $rowId }}_{{ $actId }}"
            class="form-control adjustment-input adj-activity-same"
            value="{{ $fromSame }}"
            data-activity-id="{{ $actId }}"
            data-claim-id="{{ $claimId }}"
            data-activity-advance-id="{{ $activityAdvanceId ?? '' }}"
            data-advance-id="{{ $advanceId }}"
            data-original-max="{{ $availableAdvance }}"
            data-current-max="{{ $availableAdvance }}"
            data-financed="{{ $financed }}"
            data-month="{{ $paidAdvanceMonth }}"
            data-payment-month="{{ $paidAdvanceDate }}"
            data-row-id="{{ $rowId }}"
            data-category="activity"
            min="0"
            max="{{ $availableAdvance }}"
            step="1"
            style="width:100%; height:24px; font-size:9px; text-align:center; border:1px solid #4caf50; border-radius:3px;">
    </div>
   

    {{-- Remaining Amount --}}
    {{-- <div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:4px; border-top:1px dashed #1976d2;">
        <span style="font-weight:600;">Remaining to Finance:</span>
        <span id="activity_remaining_{{ $rowId }}_{{ $actId }}" 
              class="activity-remaining" 
              data-original="{{ $financed }}">
            ₹{{ number_format($remaining, 0) }}
        </span>
    </div> --}}
</div>
 @endif
@endforeach


















{{-- carried advance adjustment --}}



{{-- resources/views/expense-payments/partials/carried-advance.blade.php --}}
@php

    // $carriedAdvances = [];
    $activityAdjust = 0;
    $expenseAdjust = 0;
    
// print_r($carriedAdvances);
    $totalOriginalAmount = 0;
@endphp

@if(count($carriedAdvances) > 0)
    @foreach($carriedAdvances as $carAdv)
        @php
        // print_r($carriedAdvances);
            $amount = $carAdv['amount'] ?? 0;
            $category = $carAdv['category'] ?? 0;
            $original = $carAdv['original'] ?? $amount;
            if($amount > 0) {
                $totalOriginalAmount += $amount;
            }
        @endphp

        @if($amount > 0)
            @php
                $advanceId = $carAdv['advance_id'];
                $uniqueId = $rowId . '_' . ($carAdv['advance_id'] ?? 1);
                // print_r($carAdv['category']);
                $activityName = getActivityNameById($carAdv['advance_id'] ?? 0);

if (!$activityName) {
    $activityName = ($carAdv['type'] == 'paid' || $carAdv['type'] == 'ledger')
        ? "Act :"
        : "Exp :";
}
            @endphp

            <div 
                class="balance-card carried-advance-block"
                data-advance-id="{{ $advanceId }}"
                data-category="carried"
                data-original="{{ $original }}"
                data-remaining="{{ $amount }}"
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
                
                {{-- Header with title and month --}}
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span style="font-weight:700; color:#b71c1c; font-size:10px;">
                        ⏪Carried : {{ $category == 'expense' ?"Expense": $activityName }}
                    </span>
               
                </div>


                {{-- Original and Remaining Amounts --}}
                <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:9px;">
                    <div>
                        <span style="color:#666;">Original:</span>
                        <span style="font-weight:600; margin-left:4px;">₹{{ number_format($original) }}</span>
                    </div>
                    <div>
                        <span style="color:#666;">Remaining:</span>
                        <span style="font-weight:700; color:#b71c1c; margin-left:4px;">₹{{ number_format($amount) }}</span>
                    </div>
                </div>

                {{-- Adjustment Section --}}
                <div style="margin-top:8px; border-top:1px dashed #e53935; padding-top:8px;">
                 
                    
                    {{-- Activity Adjustment --}}
                    @if(isset($carriedAdvances) && $carriedAdvances > 0)
                    <div style="margin-bottom:8px;">
                        {{-- <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                            <label style="font-size:8px; color:#1976d2; font-weight:600;">
                                Adjust with Activity
                            </label>
                            <span style="font-size:7px; color:#666;">
                                Max: ₹{{ number_format(min($finalActivityAmount, $amount)) }}
                            </span>
                        </div> --}}
                        <div style="display:flex; align-items:center; gap:4px;">
                            <input type="number"
                                class="adjustment-input adj-carried-activity"
                                id="adj_carried_activity_{{ $uniqueId }}"
                                value="{{ (int)$activityAdjust }}"
                                min="0"
                                max="{{ $amount}}"
                                data-type="activity"
                                data-advance-id="{{ $advanceId }}"
                                data-max-activity="{{ $amount }}"
                                data-max-total="{{ $amount }}"
                                data-carried-amt="{{ $amount }}"
                                data-row-id="{{ $rowId }}"
                                onkeypress="return isNumberKey(event)"
                                oninput="updateCarriedBalances('{{ $uniqueId }}', {{ $amount }})"
                                style="flex:1; height: 26px; text-align: center; border: 1px solid #1976d2; border-radius: 3px; font-size: 10px; background: white; padding: 2px 4px;">
                            {{-- <span style="font-size:8px; color:#1976d2; font-weight:600;">to Activity</span> --}}
                        </div>
                    </div>
                    @endif

                   
                </div>

                {{-- Date if available --}}
                @if(!empty($carAdv['date']) && \App\Support\ZeroDate::has($carAdv['date']))
                    <div style="font-size:7px; color:#999; margin-top:6px; text-align:right; border-top:1px solid #eee; padding-top:4px;">
                        Date: {{ \Carbon\Carbon::parse($carAdv['date'])->format('d-m-Y') }}
                    </div>
                @endif
            </div>
        @endif
    @endforeach

    {{-- JavaScript for balance updates --}}
    <script>
 function updateCarriedBalances(uniqueId, totalAmount) {
    const activityInput = document.getElementById('adj_carried_activity_' + uniqueId);
    if (!activityInput) return;

    let activityVal = parseFloat(activityInput.value) || 0;

    // Max allowed = min(activity claim, carried amount)
    let maxActivity = parseFloat(activityInput.getAttribute('data-max-activity') || totalAmount);
    let maxTotal = parseFloat(activityInput.getAttribute('data-max-total') || totalAmount);

    let allowedMax = Math.min(maxActivity, maxTotal);

    if (activityVal > allowedMax) {
        activityVal = allowedMax;
        activityInput.value = allowedMax;
    }

    if (activityVal < 0) {
        activityVal = 0;
        activityInput.value = 0;
    }

    // Update remaining on card
    const finalRemaining = totalAmount - activityVal;

    const card = activityInput.closest('.carried-advance-block');
    if (card) {
        card.setAttribute('data-remaining', finalRemaining);
    }

    if (typeof window.updateParentTotals === 'function') {
        window.updateParentTotals();
    }
}
    </script>

    
@endif