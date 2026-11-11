{{-- resources/views/expense-payments/partials/finance-amounts.blade.php --}}
@php
    // This is a placeholder - replace with actual data from your controller
    $finalActivityAmount = 0;
    $finalDAAmount = 0;
    $netExpense = $record->total_claim ?? 0;
    $grandTotal = $record->total_claim ?? 0;
@endphp

<td style="vertical-align:top; padding:5px;">
    <strong>{{ number_format($finalActivityAmount) }}</strong>
    {{-- Activities WITH Advance --}}
    <div style="font-size:10px; color:#1976d2; margin-top:2px;">
        {{-- Sample data - replace with actual activities --}}
        @if($record['EmployeeID'] == 1611)
            Retailer Meeting: 15,000
        @endif
    </div>
    {{-- Activities WITHOUT Advance --}}
    <div style="font-size:10px; color:#999; margin-top:2px;">
        {{-- Sample data - replace with actual activities --}}
        Field: 7,203
    </div>
</td>
<td>{{ number_format($finalDAAmount) }}</td>
<td>{{ number_format($netExpense) }}</td>
<td><strong>{{ number_format($grandTotal) }}</strong></td>