{{-- activity-opr-balance.blade.php --}}
@php $hasAdvances = false;
// echo ' <pre>';
//    print_r($expenseAdvances) ;
// echo '</pre>';

@endphp

<div style="font-size:9px; line-height:1.2;">
    {{-- ===== Process Carried Advances (New Structure) ===== --}}
    @if(!empty($carriedAdvances) && is_array($carriedAdvances))
        @foreach($carriedAdvances as $advance)
        
            @php
                $amount = $advance['amount'] ?? 0;
                $original = $advance['original'] ?? 0;
                $advanceId = $advance['advance_id'] ?? '';
                $activityName = getActivityNameById($advance['advance_id'] ?? 0) ?? 'Activity';
                $category = $advance['category'] ?? '';
                $month = $advance['month'] ?? '';
                $paymentDate = $advance['payment_date'] ?? '';
                $index = $advance['index'] ?? '';
                
                // Helper function to check if month is numeric
                $isNumericMonth = is_numeric($month);
            @endphp

            @if($amount > 0)
                @php $hasAdvances = true; @endphp
                
                {{-- Ledger Category --}}
                @if($category == 'ledger')
                    <div style="background:#fff7ed; border-left:2px solid #fb923c; padding:6px 8px; margin-bottom:8px; font-size:9px; line-height:1.2;">
                       
                        <div style="display:flex; justify-content:space-between; font-weight:600; color:#b71c1c;">
                            <span> {{ $activityName }}</span>
                            <span>₹{{ number_format($amount) }}</span>
                        </div>
                        <div style="font-size:8px; color:#666; display:flex; justify-content:space-between;">
                            <span>Original: ₹{{ number_format($original) }}</span>
                            {{-- <span>Index: {{ $index }}</span> --}}
                        </div>
                        @if(!empty($paymentDate) && \App\Support\ZeroDate::has($paymentDate))
                            <div style="font-size:8px; color:#666;">
                                Date: {{ \Carbon\Carbon::parse($paymentDate)->format('d-m-Y') }}
                            </div>
                        @endif
                        @if(!empty($month))
                            <div style="font-size:8px; color:#666; margin-top:2px;">
                                Month: {{ is_numeric($month) ? \Carbon\Carbon::create()->month((int)$month)->format('M') : $month }}
                            </div>
                        @endif
                    </div>
                
                {{-- Paid Category --}}
                @elseif($category == 'paid')
                    <div style="border-left:2px solid #1e88e5; padding:6px 8px; margin-bottom:8px; background:#f4f9ff; border-radius:3px;">
                        <div style="display:flex; justify-content:space-between; font-weight:600; color:#0d47a1;">
                            <span>{{ $activityName }}</span>
                            <span>₹{{ number_format($amount) }}</span>
                        </div>
                        <div style="font-size:8px; color:#666; display:flex; justify-content:space-between;">
                            <span>Original: ₹{{ number_format($original) }}</span>
                            {{-- <span>Index: {{ $index }}</span> --}}
                        </div>
                        @if(!empty($month))
                            <div style="font-size:8px; color:#666; margin-top:2px;">
                                @if($isNumericMonth )
                                    <span>{{ \Carbon\Carbon::create()->month((int)$month)->format('M') }}/{{ \Carbon\Carbon::parse($paymentDate)->format('y') }}</span>
                              
                                @endif
                            </div>
                        @endif
                        @if(!empty($paymentDate) && \App\Support\ZeroDate::has($paymentDate) && !$isNumericMonth)
                            <div style="font-size:8px; color:#666;">
                                Date: {{ \Carbon\Carbon::parse($paymentDate)->format('d-m-Y') }}
                            </div>
                        @endif
                    </div>
                
                {{-- Expense Category --}}
                @elseif($category == 'expense')
                    <div style="border-left:2px solid #9c27b0; padding:6px 8px; margin-bottom:8px; background:#f3e5f5; border-radius:3px;">
                        <div style="display:flex; justify-content:space-between; font-weight:600; color:#6a1b9a;">
                            <span>Expense </span>
                            <span>₹{{ number_format($amount) }}</span>
                        </div>
                        <div style="font-size:8px; color:#666; display:flex; justify-content:space-between;">
                            <span>Original: ₹{{ number_format($original) }}</span>
                            {{-- <span>Index: {{ $index }}</span> --}}
                        </div>
                        @if(!empty($month))
                            <div style="font-size:8px; color:#666; margin-top:2px;">
                                @if($isNumericMonth )
                                    <span>{{ \Carbon\Carbon::create()->month((int)$month)->format('M') }}/{{ \Carbon\Carbon::parse($paymentDate)->format('y') }}</span>
                                {{-- @else
                                    <span>Month: {{ $month }}</span> --}}
                                @endif
                            </div>
                        @endif
                        @if(!empty($paymentDate) && \App\Support\ZeroDate::has($paymentDate) && !$isNumericMonth)
                            <div style="font-size:8px; color:#666;">
                                Date: {{ \Carbon\Carbon::parse($paymentDate)->format('d-m-Y') }}
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        @endforeach
    @endif

    {{-- ===== Legacy Support: Ledger Stored Advances (Old) ===== --}}
    @if(!empty($ledgerData) && empty($carriedAdvances))
        @for($j = 1; $j <= 4; $j++)
            @php
                $advAmount  = $ledgerData['advance_amt_' . $j] ?? 0;
                $advPurpose = $ledgerData['advance_purpose_' . $j] ?? '';
                $advMonth   = $ledgerData['advance_month_' . $j] ?? '';
                $advDate    = $ledgerData['advance_date_' . $j] ?? '';
            @endphp

            @if($advAmount > 0 && !empty($advPurpose) && $advPurpose != '-')
                @php $hasAdvances = true; @endphp
                <div style="background:#fff7ed; border-left:2px solid #fb923c; padding:6px 8px; margin-bottom:8px; font-size:9px; line-height:1.2;">
                    <div style="display:flex; justify-content:space-between; font-weight:600; color:#b71c1c;">
                        <span>{{ $advPurpose }}</span>
                        <span>₹{{ number_format($advAmount) }}</span>
                    </div>
                    <div style="font-size:8px; color:#666; display:flex; justify-content:space-between;">
                        <span>{{ $advMonth }}</span>
                        @if(!empty($advDate) && \App\Support\ZeroDate::has($advDate))
                            <span>{{ \Carbon\Carbon::parse($advDate)->format('d-m-Y') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        @endfor
    @endif

    {{-- ===== Legacy Support: DB Paid Advances ===== --}}
    @if(!empty($allPaidAdvances) && empty($carriedAdvances))
        @foreach($allPaidAdvances as $advance)
            @php
                $amount  = $advance['payment_amount'] ?? 0;
                $purpose = $advance['activity_name'] ?? '';
                $month   = $advance['month'] ?? '';
                $date    = $advance['payment_date'] ?? '';
                $isNumericMonth = is_numeric($month);
            @endphp

            @if($amount > 0 && !empty($purpose))
                @php $hasAdvances = true; @endphp
                <div style="background: rgb(227, 242, 253); border-left: 3px solid rgb(25, 118, 210); padding: 6px 8px; margin-bottom: 8px; font-size: 9px; border-radius: 3px;">
                    <div style="display:flex; justify-content:space-between; font-weight:600; color:#0d47a1;">
                        <span>{{ $purpose }}</span>
                        <span>₹{{ number_format($amount) }}</span>
                    </div>
                    <div style="font-size:8px; color:#666; display:flex; justify-content:space-between;">
                        @if(!empty($month))
                            @if($isNumericMonth && !empty($date) && \App\Support\ZeroDate::has($date))
                                <span>{{ \Carbon\Carbon::create()->month((int)$month)->format('M') }}/{{ \Carbon\Carbon::parse($date)->format('y') }}</span>
                            @elseif($isNumericMonth)
                                <span>{{ \Carbon\Carbon::create()->month((int)$month)->format('M') }}</span>
                            @else
                                <span>{{ $month }}</span>
                            @endif
                        @endif
                        @if(!empty($date) && \App\Support\ZeroDate::has($date))
                            <span>{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach
    @endif

    {{-- ===== Expense Advances ===== --}}
    @if(!empty($expenseAdvances) && count($expenseAdvances) > 0)
        @foreach($expenseAdvances as $index => $expAdv)
            @if(function_exists('isImprestAdvanceRecord') && isImprestAdvanceRecord($expAdv))
                @continue
            @endif
            @php
                $advance     = (int)($expAdv['advance_amount'] ?? 0);
                $netExp      = (int)($netExpense ?? 0);
                $month       = $expAdv['month'] ?? '';
                $paymentDate = $expAdv['payment_date'] ?? null;
                $advanceId   = $expAdv['expense_advance_id'] ?? $expAdv['id'] ?? $index;
                $isNumericMonth = is_numeric($month);

                // Auto adjust = min(net expense, advance) - initial suggestion only
                $suggestedAdjust = min($netExp, $advance);
                $maxAdjust       = min($netExp, $advance);

                // Color logic (same pattern as activity advance)
                if($advance > 0 && $netExp > 0){
                    $rowColor = '#f3e5f5';   // purple (advance + expense both exist)
                    $borderColor = '#9c27b0';
                } elseif($advance > 0 && $netExp == 0){
                    $rowColor = '#fce4ec';   // light pink (advance but no expense)
                    $borderColor = '#d81b60';
                } elseif($advance == 0 && $netExp > 0){
                    $rowColor = '#f5f5f5';   // grey (expense but no advance)
                    $borderColor = '#9e9e9e';
                } else {
                    continue;
                }
                
                $totalExpenseAdvance += $advance;
            @endphp

            @if($advance > 0)
                @php $hasAdvances = true; @endphp
                <div class="expense-advance-row"
                     style="background: {{ $rowColor }}; padding: 6px 8px; margin-bottom: 8px;
                            border-left: 3px solid {{ $borderColor }}; border-radius: 3px;
                            font-size: 9px;">

                    {{-- Net Expense --}}
                    <div style="display:flex; justify-content:space-between; margin-bottom:3px; color:#000;">
                        <span style="font-weight:600;">Expense:</span>
                        <span style="font-weight:bold;">₹{{ number_format($advance) }}</span>
                    </div>

                    {{-- Month and Payment Date --}}
                    @if(!empty($month) || (!empty($paymentDate) && \App\Support\ZeroDate::has($paymentDate)))
                        <div style="font-size:8px; color:#666; display:flex; justify-content:space-between;">
                            @if(!empty($month))
                                @if($isNumericMonth && !empty($paymentDate) && \App\Support\ZeroDate::has($paymentDate))
                                    <span>{{ \Carbon\Carbon::create()->month((int)$month)->format('M') }}/{{ \Carbon\Carbon::parse($paymentDate)->format('y') }}</span>
                                @elseif($isNumericMonth)
                                    <span>{{ \Carbon\Carbon::create()->month((int)$month)->format('M') }}</span>
                                @else
                                    <span>{{ $month }}</span>
                                @endif
                            @endif
                            @if(!empty($paymentDate) && \App\Support\ZeroDate::has($paymentDate))
                                <span>{{ \Carbon\Carbon::parse($paymentDate)->format('d-m-Y') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    @endif

    {{-- ===== No Advances ===== --}}
    @if(!$hasAdvances)
        <div style="color:#999; text-align:center; padding:4px 0;">-</div>
    @endif
</div>