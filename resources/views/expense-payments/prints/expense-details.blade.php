{{-- resources/views/prints/expense-details.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense Details</title>
    <style>
        body { font-family: "Times New Roman", serif; font-size:14px; margin:20px; }
        .header { text-align:center; }
        .header h2 { color:red; margin:0; }
        .sub { font-size:20px; font-weight:bold; }
        .info { font-size:18px; margin:3px 0; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th, td { border:1px solid #333; padding:6px; }
        th { background:#0b5c5c; color:#fff; }
        .subhead th { background:#dde6ff; color: #1e3a8a;}
        .center { text-align:center; }
        .right { text-align:right; }
        .total { background:#e6e6a8; font-weight:bold; }
        .yellow { background:#e6e6a8; font-weight:bold; }
    </style>
</head>
<body>

<div class="header">
    <h2>Expense Details ( {{ $monthName }}/{{ $yearId }} )</h2>
    <div class="sub">Claimer: {{ $employee->code }}-{{ $employee->name }}</div>
    <div class="info">
        Processing ( {{ $employee->grade ?? '' }}, {{ $employee->designation ?? 'Assistant Supervisor' }} )
    </div>
    <div class="info">
        <strong>HQ:</strong> {{ $employee->hq ?? '' }} ,
        <strong>State:</strong> {{ $employee->state ?? '' }}
        <strong>Total Amount:</strong> {{ number_format($grandTotal,2) }}/-
        <strong>Submitted Date:</strong> {{ date('d-m-Y') }}
    </div>
</div>

{{-- ================= SUMMARY ROW ================= --}}
<table>
    <tr>
        <th>Claimer - Code</th>
        <th>Total Claims</th>
        <th>Submited Date</th>
        <th>Claimed Amt</th>
        <th>Verified Amt</th>
        <th>Approver Amt</th>
        <th>Payment Amount</th>
        <th>Advance Amount</th>
        <th>Paid Amount</th>
        <th>Paid Option</th>
        <th>Payment Date</th>
        <th>Payment Remark</th>
    </tr>
    <tr class="total">
        <td>{{ $employee->code }}-{{ $employee->name }}</td>
        <td class="center">{{ $claimCount }}</td>
        <td class="center">{{ date('d-m-Y') }}</td>
        <td class="right">{{ number_format($totalClaimed,2) }}</td>
        <td class="right">{{ number_format($totalVerified,2) }}</td>
        <td class="right">{{ number_format($totalApproved,2) }}</td>
        <td class="right">{{ number_format($grandTotal,2) }}</td>
        <td class="right">{{ $monthFinal->Fin_AdvancePay ?? 0 }}</td>
        <td class="right">{{ $monthFinal->Fin_PayAmt ?? 0 }}</td>
        <td>{{ $monthFinal->Fin_PayOption ?? '' }}</td>
        <td class="center">
            {{ !empty($monthFinal->Fin_PayDate) ? date('d-m-Y', strtotime($monthFinal->Fin_PayDate)) : '' }}
        </td>
        <td>{{ $monthFinal->Fin_PayRemark ?? '' }}</td>
    </tr>
</table>

{{-- ================= FORMAT 1 ================= --}}
@if($format == 1)
<table>
    <tr class="subhead">
        <th>Sn</th>
        <th>Claim ID</th>
        <th>Claim Type</th>
        <th>Upload Date</th>
        <th>Bill Date</th>
        <th>Claimed Amt</th>
        <th>Verified Amt</th>
        <th>Approver Amt</th>
        <th>Paid Amt</th>
    </tr>

    @php
        $sn = 1;
        $claimedTotal = 0;
    @endphp

    @foreach($claimDetails as $row)
        @php
            $amt = $row->FinancedTAmt ?? 0;
            $claimedTotal += $amt;
        @endphp
        <tr>
            <td class="center">{{ $sn++ }}</td>
            <td class="center">{{ $row->ExpId }}</td>
            <td>{{ $row->ClaimName }}</td>
            <td class="center">{{ date('d-m-Y', strtotime($row->CrDate)) }}</td>
            <td class="center">{{ date('d-m-Y', strtotime($row->BillDate)) }}</td>
            <td class="right">{{ number_format($row->FilledTAmt,2) }}</td>
            <td class="right">{{ number_format($row->VerifyTAmt,2) }}</td>
            <td class="right">{{ number_format($row->ApprTAmt,2) }}</td>
            <td class="right">{{ number_format($row->FinancedTAmt,2) }}</td>
        </tr>
    @endforeach

    <tr class="total">
        <td colspan="5" class="right">Total:</td>
        <td class="right">{{ number_format($totalClaimed,2) }}</td>
        <td class="right">{{ number_format($totalClaimed,2) }}</td>
        <td class="right">{{ number_format($totalClaimed,2) }}</td>
        <td class="right">{{ number_format($totalClaimed,2) }}</td>
    </tr>
</table>
@endif

{{-- ================= FORMAT 2 (TRAVEL) ================= --}}
@if($format == 2)
<table>
    <tr>
        <th colspan="6" style="background:#dde6ff;color:#1e3a8a;text-align:left;">
            EXPENSE TYPE : TRAVEL (TRV)
        </th>
    </tr>
    <tr class="subhead">
        <th>Sn</th>
        <th>Date</th>
        <th>Remark</th>
        <th>2W</th>
        <th>4W</th>
        <th>Total/Fin</th>
    </tr>

    @php
        $sn = 1;
        $total = 0;
        // echo '<pre>';
        // print_r($travelRows);
        //   echo '</pre>';
    @endphp

    @foreach($travelRows as $row)
        @php
            $amt = $row->total ?? 0;
            $total += $amt;
        @endphp
        <tr>
            <td class="center">{{ $sn++ }}</td>
            <td class="center">{{ date('d-m-Y', strtotime($row->BillDate)) }}</td>
            <td>{{ $row->Remark }}</td>
            <td class="right">{{ number_format($amt,0) }}</td>
            <td></td>
            <td class="right yellow">{{ number_format($amt,0) }}</td>
        </tr>
    @endforeach

    <tr class="total">
        <td colspan="3" class="right">Total:</td>
        <td class="right">{{ number_format($total,0) }}</td>
        <td></td>
        <td class="right">{{ number_format($total,0) }}</td>
    </tr>
</table>
@endif

</body>
</html>
