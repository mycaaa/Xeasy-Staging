<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; margin: 0; padding: 0; color: #111; }
    h2 { font-size: 18px; margin: 0 0 4px; color: #c00; }
    .header-block { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #0062C4; padding-bottom: 6px; }
    .header-block p { margin: 3px 0; font-size: 13px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 14px; table-layout: fixed; }
    th, td { border: 1px solid #999; padding: 2px 3px; vertical-align: middle; word-wrap: break-word; overflow: hidden; }
    thead { display: table-row-group; }
    thead th { background: #dde6ff; color: #1e3a8a; font-size: 7px; text-align: center; white-space: normal; word-wrap: break-word; }
    tbody td { font-size: 7px; }
    .section-title { background: #dde6ff; color: #1e3a8a; font-weight: bold; font-size: 10px;
                     padding: 4px 8px; margin-bottom: 0; }
    .total-row td { background: #FFFFB3; font-weight: bold; }
    .right { text-align: right; }
    .center { text-align: center; }
    .left { text-align: left; }
    .declaration { font-size: 9px; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 6px; }
</style>
</head>
<body>

{{-- ══ HEADER ══ --}}
<div class="header-block">
    {{-- BRAND-01: company mark on printed claims --}}
    @if (is_file(public_path('custom/vnr_logo_print.png')))
        <img src="{{ public_path('custom/vnr_logo_print.png') }}" alt="VNR" style="float:right;height:52px;margin-left:10px;">
    @endif
    <h2>Expense Details ({{ $monthName }}/{{ $fyLabel }})</h2>
    <p><strong>Claimer:</strong> {{ $empDisplay }}</p>
    <p>
        <strong>{{ $emp->department_name ?? '' }}</strong>
        @if(!empty($emp->vertical_name)) &nbsp;({{ $emp->vertical_name }})@endif
        @if(!empty($emp->grade_name)), {{ $emp->grade_name }}@endif
        @if(!empty($emp->designation_name)), {{ $emp->designation_name }}@endif
    </p>
    @php
        $hqT = !empty($emp->territory_name) ? $emp->territory_name : '';
        $hqL = !empty($emp->city_village_name) ? $emp->city_village_name : '';
    @endphp
    @if($hqT && $hqL)
        <p><strong>Territory:</strong> {{ $hqT }} &nbsp;,&nbsp; <strong>HQ/Job-Location:</strong> {{ $hqL }}</p>
    @elseif($hqT)
        <p><strong>Territory:</strong> {{ $hqT }}</p>
    @elseif($hqL)
        <p><strong>HQ/Job-Location:</strong> {{ $hqL }}</p>
    @endif
    <p>
        <strong>State:</strong> {{ ucwords(strtolower($emp->state_name ?? '')) }}
        @if(($emp->DepartmentId ?? 0) == 15)
            @if(!empty($emp->zone_name)), &nbsp;<strong>Zone:</strong> {{ $emp->zone_name }}@endif
            @if(!empty($emp->region_name)), &nbsp;<strong>Region:</strong> {{ $emp->region_name }}@endif
        @endif
    </p>
    {{-- PRINT-AFTER-SUBMIT-01 (14 Nov 2026): before the month's final submission the header showed "Total Amount: 0/-"
         and "Open / Pending"; the month's total and date are shown once it is submitted --}}
    @php $pdSubmitted = !empty($mlist->DateOfSubmit) && \App\Support\ZeroDate::has($mlist->DateOfSubmit); @endphp
    <p>
        @if ($pdSubmitted)
            <strong>Total Amount:</strong> {{ floatval($mlist->Claim_Amount ?? 0) }}/-
            &nbsp;&nbsp;
            <strong>Submitted Date:</strong> {{ \Carbon\Carbon::parse($mlist->DateOfSubmit)->format('d-m-Y') }}
        @else
            <strong>Not submitted yet</strong> &nbsp;-&nbsp; draft statement; the month's total and submission date appear after the final submission.
        @endif
    </p>
</div>

{{-- ══ SECTIONS ══ --}}
@foreach($sections as $sec)
<div class="section-title">
    EXPENSE TYPE : {{ $sec['cgName'] }}
</div>

<table>
    <thead>
        <tr>
            <th style="width:18px;">Sn</th>
            <th style="width:40px;">Date</th>
            <th style="width:70px;">Remark</th>
            @if($sec['isKm'] && $sec['has7'])
                <th style="width:35px;">FromKm</th>
                <th style="width:35px;">ToKm</th>
                <th style="width:35px;">TotalKm</th>
                <th style="width:35px;">2W</th>
                <th style="width:35px;">4W</th>
            @endif
            @foreach($sec['visibleCols'] as $col)
                <th style="width:40px;">{{ strtoupper($col->ClaimCode) }}</th>
            @endforeach
            <th style="width:45px;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sec['rows'] as $i => $row)
        @php
            // Recalculate row total from actual column values to ensure tally is correct
            $calcTotal = 0;
            if ($sec['isKm'] && $sec['has7']) {
                $calcTotal += $row['twoW'] + $row['fourW'];
            }
            foreach ($sec['visibleCols'] as $col) {
                $calcTotal += $row['cols'][$col->ClaimId] ?? 0;
            }
            // Use DB value if available and non-zero, else use calculated
            $displayTotal = $row['rowTotal'] > 0 ? $row['rowTotal'] : $calcTotal;
        @endphp
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td class="center">{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
            <td class="left">{{ $row['remark'] }}</td>
            @if($sec['isKm'] && $sec['has7'])
                <td class="center">{{ $row['km']['from'] ?? '' }}</td>
                <td class="center">{{ $row['km']['to'] ?? '' }}</td>
                <td class="center">{{ isset($row['km']['tot']) ? floatval($row['km']['tot']) : '' }}</td>
                <td class="right">{{ $row['twoW'] > 0 ? intval($row['twoW']) : '' }}</td>
                <td class="right">{{ $row['fourW'] > 0 ? intval($row['fourW']) : '' }}</td>
            @endif
            @foreach($sec['visibleCols'] as $col)
                <td class="right">{{ ($row['cols'][$col->ClaimId] ?? 0) > 0 ? intval($row['cols'][$col->ClaimId]) : '' }}</td>
            @endforeach
            <td class="right" style="background:#FFFFB3;font-weight:bold;">
                {{ $displayTotal > 0 ? intval($displayTotal) : '' }}
            </td>
        </tr>
        @endforeach

        {{-- TOTAL ROW --}}
        @php
            // header col count: Sn + Date + Remark = 3
            // + KM section adds: FromKm, ToKm, TotalKm, 2W, 4W = 5 cols
            // + visibleCols
            // + Total = 1
            // Total row: colspan covers Sn+Date+Remark [+FromKm+ToKm if km] = 3 or 5
            $totalColspan = ($sec['isKm'] && $sec['has7']) ? 5 : 3;
        @endphp
        <tr class="total-row">
            <td colspan="{{ $totalColspan }}" class="right"><strong>Total:</strong></td>
            @if($sec['isKm'] && $sec['has7'])
                <td class="center"><strong>{{ $sec['totKmAmt'] > 0 ? $sec['totKmAmt'].' KM' : '' }}</strong></td>
                <td class="right"><strong>{{ $sec['grand2W'] > 0 ? intval($sec['grand2W']) : '' }}</strong></td>
                <td class="right"><strong>{{ $sec['grand4W'] > 0 ? intval($sec['grand4W']) : '' }}</strong></td>
            @endif
            @foreach($sec['visibleCols'] as $col)
                <td class="right"><strong>{{ ($sec['colTotals'][$col->ClaimId] ?? 0) > 0 ? intval($sec['colTotals'][$col->ClaimId]) : '' }}</strong></td>
            @endforeach
            <td class="right"><strong>{{ $sec['grandTotal'] > 0 ? intval($sec['grandTotal']) : '' }}</strong></td>
        </tr>
    </tbody>
</table>
@endforeach

{{-- ══ DECLARATION (nn=2 only) ══ --}}
@if($nn == 2)
<p class="declaration">
    I hereby declare that all expenses claimed are solely for business travel and do not include any personal expenses.
    I understand that the company is not liable for any personal expenditures. In case of any incorrect or false claims,
    I agree to indemnify the company for any resulting loss or liability.
</p>
@endif

<x-report-stamp />
</body>
</html>
