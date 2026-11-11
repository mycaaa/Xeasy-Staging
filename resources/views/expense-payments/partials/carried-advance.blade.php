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
            $amount = $carAdv['amount'] ?? 0;
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
                style="background:#ffebee; border-left:3px solid #e53935; padding:8px; margin-bottom:8px; border-radius:4px; font-size:9px;">
                
                {{-- Header with title and month --}}
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span style="font-weight:700; color:#b71c1c; font-size:10px;">
                        ⏪ {{ $activityName }}
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
                    @if(isset($finalActivityAmount) && $finalActivityAmount > 0)
                    <div style="margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                            {{-- <label style="font-size:8px; color:#1976d2; font-weight:600;">
                                Adjust with Activity
                            </label> --}}
                            <span style="font-size:7px; color:#666;">
                                Max: ₹{{ number_format(min($finalActivityAmount, $amount)) }}
                            </span>
                        </div>
                        <div style="display:flex; align-items:center; gap:4px;">
                            <input type="number"
                                class="adjustment-input adj-carried-activity"
                                id="adj_carried_activity_{{ $uniqueId }}"
                                value="{{ (int)$activityAdjust }}"
                                min="0"
                                max="{{ (int)min($finalActivityAmount, $amount) }}"
                                data-type="activity"
                                data-advance-id="{{ $advanceId }}"
                                data-max-activity="{{ $finalActivityAmount }}"
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

    

@else
    <div style="color:#999; font-size:10px; text-align:center; padding:15px; background:#f5f5f5; border-radius:3px;">
        No carried advances
    </div>
@endif