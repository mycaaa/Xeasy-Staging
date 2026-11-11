<style>
    .expense-details-container {
        font-family: Arial, sans-serif;
        font-size: 12px;
        padding: 20px;
        background: white;
    }
    .expense-details-container table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 15px;
        font-size: 11px;
    }
    .expense-details-container table, .expense-details-container th, .expense-details-container td {
        border: 1px solid #ccc;
    }
    .expense-details-container th {
        background-color: #0062C4;
        color: white;
        padding: 6px 4px;
        text-align: center;
        font-weight: bold;
        font-size: 11px;
    }
    .expense-details-container td {
        padding: 4px;
        vertical-align: top;
    }
    .expense-details-container .header-title {
        font-size: 16px;
        color: #F20000;
        font-weight: bold;
        text-align: center;
        margin-bottom: 15px;
    }
    .expense-details-container .employee-info {
        text-align: center;
        margin-bottom: 15px;
        line-height: 1.6;
        font-size: 12px;
    }
    .expense-details-container .group-header {
        background-color: #0062C4;
        color: white;
        padding: 8px;
        font-weight: bold;
        font-size: 12px;
        text-align: left;
    }
    .expense-details-container .text-right {
        text-align: right;
    }
    .expense-details-container .text-center {
        text-align: center;
    }
    .expense-details-container .total-row {
        background-color: #FFFFB3;
        font-weight: bold;
    }
    .expense-details-container .remark-cell {
        max-width: 200px;
        word-wrap: break-word;
        text-align: left;
    }
    @media print {
        .btn, .modal-header, .modal-footer {
            display: none !important;
        }
        .expense-details-container {
            padding: 0;
        }
    }
</style>

<div class="expense-details-container">
    @if(isset($error))
        <div class="alert alert-danger text-center">{{ $error }}</div>
    @elseif(isset($monthExpense) && isset($employee))
        <div class="header-title">
            Expense Details ({{ date('F', mktime(0,0,0,$monthExpense->Month, 1, date('Y'))) }}/{{ $financialYear->Year ?? '' }})
        </div>
        
        <div class="employee-info">
            <b>Claimer:</b>&nbsp;
            {{ str_pad($employee->EmpCode ?? '', 4, '0', STR_PAD_LEFT) }}-{{ $employee->Name ?? '' }}<br>
            <b>{{ $employee->department_name ?? 'Sales' }}</b>&nbsp;
            @if(isset($employee->vertical_name) && $employee->vertical_name)
                ({{ $employee->vertical_name }}, 
            @endif
            @if(isset($employee->grade_name) && $employee->grade_name)
                {{ $employee->grade_name }}, 
            @endif
            @if(isset($employee->designation_name) && $employee->designation_name)
                {{ $employee->designation_name }}
            @endif
            @if(isset($employee->vertical_name) && $employee->vertical_name))@endif
            <br>
            
            @php
                $Hq = $employee->territory_name ?? ($employee->city_village_name ?? '');
            @endphp
            <b>HQ:&nbsp;</b>{{ $Hq }}&nbsp;,&nbsp;
            <b>State:&nbsp;</b>{{ ucwords(strtolower($employee->state_name ?? '')) }}
            
            @if(isset($employee->zone_name) && $employee->zone_name)
                ,&nbsp;<b>Zone:&nbsp;</b>{{ $employee->zone_name }}&nbsp;
            @endif
            @if(isset($employee->region_name) && $employee->region_name)
                ,&nbsp;<b>Region:&nbsp;</b>{{ $employee->region_name }}&nbsp;
            @endif
            <br>
            <b>Total Amount:&nbsp;</b>{{ number_format($monthExpense->Claim_Amount ?? 0, 2) }}/-&nbsp;&nbsp;
            <b>Submitted Date</b>:&nbsp;
            @if(isset($monthExpense->DateOfSubmit) && \App\Support\ZeroDate::has($monthExpense->DateOfSubmit) && $monthExpense->DateOfSubmit != '1970-01-01')
                {{ date('d-m-Y', strtotime($monthExpense->DateOfSubmit)) }}
            @else
                -
            @endif
        </div>
        <div class="d-flex">
            
        </div>
        <h4 style="margin-top: 20px; margin-bottom: 10px; color: #0062C4;">
            EXPENSE TYPE : {{ strtoupper($group->cgName . ' (' . $group->cgCode . ')') }}
        </h4>
        <div style="margin-bottom: 15px; font-size: 12px;">
            <strong>Submitted Date :</strong> {{ date('d-m-Y', strtotime($monthExpense->DateOfSubmit)) }}
        </div>
        
        <table style="width:100%; border-collapse: collapse; font-size: 11px;">
            <thead>
                <tr style="background-color: #0062C4; color: white;"></tr>
                <tr style="background-color: #0062C4; color: white;">
                    <th style="padding: 6px; border: 1px solid #ccc; text-align: center;">Sn</th>
                    <th style="padding: 6px; border: 1px solid #ccc; text-align: center;">Date</th>
                    <th style="padding: 6px; border: 1px solid #ccc; text-align: center;">Remark</th>
                    <th style="padding: 6px; border: 1px solid #ccc; text-align: center;">FromKm</th>
                    <th style="padding: 6px; border: 1px solid #ccc; text-align: center;">ToKm</th>
                    <th style="padding: 6px; border: 1px solid #ccc; text-align: center;">TotalKm</th>
                    @foreach($activeClaimTypes as $header)
                        <th style="padding: 6px; border: 1px solid #ccc; text-align: center;">{{ $header['code'] }}</th>
                    @endforeach
                    <th style="padding: 6px; border: 1px solid #ccc; text-align: center;">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // dd($details);
                    
                    $sn = 1; @endphp
                @foreach($details as $detail)
                    @php
                    $totalKm =$detail->odomtr_closing - $detail->odomtr_opening ;
                        $billDate = $detail->BillDate;
                        $amounts = $amountsByDate[$billDate] ?? [];
                    @endphp
                    <tr>
                        <td style="padding: 4px; border: 1px solid #ccc; text-align: center;">{{ $sn }}</td>
                        <td style="padding: 4px; border: 1px solid #ccc; text-align: center;">{{ date('d-m-Y', strtotime($detail->BillDate)) }}</td>
                        <td style="padding: 4px; border: 1px solid #ccc; text-align: left; max-width: 200px;">
                            {{ strlen($detail->Remark ?? '') > 50 ? substr($detail->Remark, 0, 50) . '...' : ($detail->Remark ?? '') }}
                        </td>
<td style="padding: 4px; border: 1px solid #ccc; text-align: right;">
    {{ $detail->odomtr_opening > 0 ? $detail->odomtr_opening : '' }}
</td>

<td style="padding: 4px; border: 1px solid #ccc; text-align: right;">
    {{ $detail->odomtr_closing > 0 ? $detail->odomtr_closing : '' }}
</td>

<td style="padding: 4px; border: 1px solid #ccc; text-align: right;">
    {{ $totalKm > 0 ? $totalKm : '' }}
</td>
                        @foreach($activeClaimTypes as $header)
                            <td style="padding: 4px; border: 1px solid #ccc; text-align: right;">
                                {{ isset($amounts[$header['code']]) && $amounts[$header['code']] > 0 ? number_format($amounts[$header['code']], 0) : '' }}
                            </td>
                        @endforeach
                        <td style="padding: 4px; border: 1px solid #ccc; text-align: right; background-color: #FFFFB3; font-weight: bold;">
                            {{ number_format($detail->Tot2Fill ?? 0, 0) }}
                        </td>
                    </tr>
                    @php $sn++; @endphp
                @endforeach
                
                <tr style="background-color: #FFFFB3; font-weight: bold;">
                    <td colspan="3" style="padding: 6px; border: 1px solid #ccc; text-align: right;">Total:</td>
                    <td style="padding: 4px; border: 1px solid #ccc; text-align: right;">{{ $totKmAmt > 0 ? $totKmAmt : '' }}</td>
                    <td style="padding: 4px; border: 1px solid #ccc; text-align: right;"></td>
                    <td style="padding: 4px; border: 1px solid #ccc; text-align: right;"></td>
                    @foreach($activeClaimTypes as $header)
                        <td style="padding: 4px; border: 1px solid #ccc; text-align: right;">
                            {{ isset($typeTotals[$header['code']]) && $typeTotals[$header['code']] > 0 ? number_format($typeTotals[$header['code']], 0) : '' }}
                        </td>
                    @endforeach
                    <td style="padding: 4px; border: 1px solid #ccc; text-align: right;">{{ number_format($totalAmount, 0) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <div class="alert alert-warning text-center">No expense details found for the selected criteria.</div>
    @endif
</div>