{{-- resources/views/expense-payments/partials/expense-advance.blade.php --}}

@php
    $totalExpenseAdvance = 0;
    $companyId = (int) session('company_id');
    $isCompany11 = ($companyId === 11);
@endphp

@if(!empty($expenseAdvances) && count($expenseAdvances) > 0)

    @foreach($expenseAdvances as $index => $expAdv)
        @if(function_exists('isImprestAdvanceRecord') && isImprestAdvanceRecord($expAdv))
            @continue
        @endif
        @php
            $advance     = (int)($expAdv['advance_amount'] ?? 0);
            $month       = (int)($expAdv['month'] ?? 0);
            $netExp      = (int)($netExpense ?? 0);
            $paymentDate = $expAdv['payment_date'] ?? null;
            $advanceId   = $expAdv['expense_advance_id'] ?? $expAdv['id'] ?? $index;

            // For company 11, suggest full advance amount
            // For others, suggest min of net expense and advance, rounded down to nearest 1000
            if ($isCompany11) {
                $suggestedAdjust = $advance;
                $maxAdjust = $advance;
            } else {
                $rawAdjust = min($netExp, $advance);
                $suggestedAdjust = (int) floor($rawAdjust / 1000) * 1000;
                $maxAdjust = min($netExp, $advance);
            }

            if($advance > 0 && $netExp > 0){
                $rowColor = '#f3e5f5';
                $borderColor = '#9c27b0';
            } elseif($advance > 0 && $netExp == 0){
                $rowColor = '#fce4ec';
                $borderColor = '#d81b60';
            } elseif($advance == 0 && $netExp > 0){
                $rowColor = '#f5f5f5';
                $borderColor = '#9e9e9e';
            } else {
                continue;
            }

            $totalExpenseAdvance += $advance;
        @endphp

        <div class="expense-advance-row"
             style="background: {{ $rowColor }}; padding: 6px; margin-bottom: 6px;
                    border-left: 3px solid {{ $borderColor }}; border-radius: 3px;
                    font-size: 9px;">

            <div style="justify-content:space-between; margin-bottom:3px; color:#000;">
                <span>Expense:</span>
                @if($isCompany11 && isset($expAdv['source']) && $expAdv['source'] === 'prev_month_calculation')
                    <span style="font-size:8px;color:#e65100;">(Prev Month Adj)</span>
                @elseif($isCompany11)
                    <span style="font-size:8px;color:#15648C;">(Full Advance Allowed)</span>
                @endif
            </div>

            <div style="margin-top:3px;">
                <div style="justify-content:space-between; margin-bottom:2px;">
                    <span>
                        @if(isset($expAdv['month_name']))
                            ({{ $expAdv['month_name'] }})
                        @else
                            ({{ \Carbon\Carbon::create()->month((int)$month)->format('M') }}/
                            @if(!empty($paymentDate) && \App\Support\ZeroDate::has($paymentDate))
                                {{ \Carbon\Carbon::parse($paymentDate)->format('y') }}
                            @endif)
                        @endif
                    </span>
                    <span> ₹{{ number_format($advance) }}</span>
                </div>

                <input type="number"
                    class="form-control adjustment-input adj-expense"
                    id="adj_expense_{{ $rowId }}_{{ $advanceId }}"
                    data-expense-id="{{ $advanceId }}"
                    data-advance="{{ $advance }}"
                    data-max="{{ $advance }}"
                    data-allowed-max="{{ $advance }}"
                    data-row-id="{{ $rowId }}"
                    data-company-11="{{ $isCompany11 ? 'true' : 'false' }}"
                    value="{{ $suggestedAdjust }}"
                    min="0"
                    max="{{ $advance }}"
                    onkeypress="return isNumberKey(event)"
                    style="width:100%; height:24px; text-align:center;
                           border:1px solid #4caf50; border-radius:3px;
                           font-size:10px; padding:2px;">
            </div>
        </div>
    @endforeach

@else
    <div style="color:#999; font-size:10px; text-align:center; padding:4px;">-</div>
@endif


<script>
$(document).ready(function() {

    function calculateExpenseTotal(rowId) {
        let total = 0;

        $(`[id^="adj_expense_${rowId}"]`).each(function() {
            total += Number($(this).val()) || 0;
        });

        const $totalSpan = $(`#total_adj_expense_${rowId}`);
        if ($totalSpan.length) {
            $totalSpan.text('₹' + formatNumber(Math.round(total)));
        }

        return total;
    }

    $(document).on('input', '[id^="adj_expense_"]', function() {
        const $input = $(this);
        const maxAllowed = Number($input.data('allowed-max')) || 0;
        let currentVal = Number($input.val()) || 0;
        const rowId = $input.data('row-id');
        const isCompany11 = $input.data('company-11') === true || $input.data('company-11') === 'true';

        // For company 11, no upper limit restriction from net expense
        if (!isCompany11 && currentVal > maxAllowed) {
            $input.val(maxAllowed);
            currentVal = maxAllowed;
        }

        if (rowId) {
            calculateExpenseTotal(rowId);

            const $row = $input.closest('tr');
            if (typeof window.recalculateRow === 'function') {
                window.recalculateRow($row);
            }
        }
    });

    $('tr[id^="row_"]').each(function() {
        const rowId = $(this).attr('id').replace('row_', '');
        calculateExpenseTotal(rowId);
    });
});

// Format number
if (typeof window.formatNumber !== 'function') {
    window.formatNumber = function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    };
}
</script>
