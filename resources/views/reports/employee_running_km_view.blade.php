@extends('layouts.standalone')

@section('title')Running KM – {{ $emp->employee_name }}@endsection

@push('head')
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #333; background: #fff; padding: 12px; }

    /* ── Header ── */
    .page-header {
        background: #dde6ff; color: #1e3a8a;
        padding: 10px 14px; border-radius: 5px 5px 0 0;
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 0;
    }
    .page-header h5 { font-size: 14px; font-weight: 700; margin: 0; }
    .page-header .sub { font-size: 11px; opacity: .8; }

    /* ── Info strip ── */
    .info-strip {
        display: flex; flex-wrap: wrap; gap: 8px;
        background: #f0f7fb; border: 1px solid #b8d9f0;
        border-top: none; padding: 8px 12px; margin-bottom: 10px;
        border-radius: 0 0 5px 5px;
    }
    .info-item { min-width: 120px; }
    .info-item .lbl { font-size: 10px; color: #888; }
    .info-item .val { font-size: 12px; font-weight: 600; color: #15648C; }

    /* ── PCD banner ── */
    .pcd-banner {
        background: #e8f4fd; border: 1px solid #90caf9;
        border-radius: 4px; padding: 8px 12px; margin-bottom: 10px;
        font-size: 11px;
    }
    .pcd-banner strong { color: #15648C; }
    .pcd-banner .pcd-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 5px; }
    .pcd-banner .pcd-item .lbl { font-size: 10px; color: #666; }
    .pcd-banner .pcd-item .val { font-size: 13px; font-weight: 700; color: #0d47a1; }

    /* ── Table ── */
    .km-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .km-table th {
        background: #dde6ff; color: #1e3a8a;
        padding: 5px 4px; text-align: center;
        border: 1px solid #0d4f70; white-space: nowrap;
        font-size: 10px;
    }
    .km-table th.sub-th { background: #dde6ff; font-size: 9px; color: #1e3a8a;}
    .km-table th.gt-th  { background: #dde6ff; color: #1e3a8a;}
    .km-table td {
        padding: 4px 4px; border: 1px solid #ddd;
        text-align: right; white-space: nowrap;
    }
    .km-table td.yr-cell { font-weight: 700; text-align: left; background: #f5f5f5; white-space: nowrap; }
    .km-table td.total-cell { background: #fff8e1; font-weight: 700; }
    .km-table tr.grand-row td { background: #dde6ff; color: #1e3a8a; font-weight: 700; text-align: center; }
    .km-table tr:nth-child(even) td:not(.yr-cell):not(.total-cell) { background: #fafafa; }
    .km-table .zero { color: #ccc; }

    /* ── Summary bar ── */
    .summary-bar {
        display: flex; flex-wrap: wrap; gap: 8px;
        margin-top: 10px; padding: 8px 10px;
        background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;
    }
    .summary-bar .s-item { font-size: 11px; }
    .summary-bar .s-item span { font-weight: 700; color: #15648C; }

    /* ── No data ── */
    .no-data { text-align: center; padding: 30px; color: #999; font-size: 13px; }

    /* ── Print ── */
    @media print {
        body { padding: 4px; }
        .no-print { display: none; }
    }
</style>
@endpush

@section('content')
{{-- ── Header ──────────────────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h5>{{ $emp->employee_name }}</h5>
        <div class="sub">{{ $emp->EmpCode }} &nbsp;|&nbsp; Running KM Report (All Years)</div>
    </div>
    <div class="no-print" style="display:flex;gap:6px;">
        <button onclick="window.print()"
                style="background:#fff;color:#15648C;border:none;padding:4px 10px;border-radius:3px;font-size:11px;cursor:pointer;">
            🖨 Print
        </button>
    </div>
</div>

{{-- ── Employee info strip ──────────────────────────────────────────────── --}}
<div class="info-strip">
    <div class="info-item">
        <div class="lbl">Department</div>
        <div class="val">{{ $emp->department_name ?: '—' }}</div>
    </div>
    <div class="info-item">
        <div class="lbl">Grade</div>
        <div class="val">{{ $emp->grade_name ?: '—' }}</div>
    </div>
    <div class="info-item">
        <div class="lbl">Vertical</div>
        <div class="val">{{ $emp->vertical_name ?: '—' }}</div>
    </div>
    <div class="info-item">
        <div class="lbl">Policy</div>
        <div class="val">{{ $emp->policy_name ?: '—' }}</div>
    </div>
    <div class="info-item">
        <div class="lbl">Vehicle Type</div>
        <div class="val">{{ $emp->vehicle_type == 2 ? '2-Wheeler' : ($emp->vehicle_type == 4 ? '4-Wheeler' : '—') }}</div>
    </div>
    <div class="info-item">
        <div class="lbl">Status</div>
        <div class="val" style="color:{{ $emp->EmpStatus === 'A' ? '#28a745' : '#e65100' }};">
            {{ $emp->EmpStatus === 'A' ? 'Active' : 'Deactivated' }}
        </div>
    </div>
</div>

{{-- ── Pre-April 2024 data ───────────────────────────────────────────────── --}}
@if($pcd)
<div class="pcd-banner">
    <strong>📋 Pre-April 2024 Data (Policy Change Details)</strong>
    @if($pcd['policy']) <span style="margin-left:8px;color:#555;">Policy: {{ $pcd['policy'] }}</span> @endif
    <div class="pcd-grid">
        <div class="pcd-item">
            <div class="lbl">2W KM Before Apr'24</div>
            <div class="val">{{ number_format($pcd['before_apr24']) }}</div>
        </div>
        <div class="pcd-item">
            <div class="lbl">4W KM After Apr'24</div>
            <div class="val">{{ number_format($pcd['after_apr24']) }}</div>
        </div>
        <div class="pcd-item">
            <div class="lbl">Total Run (All Time)</div>
            <div class="val">{{ number_format($pcd['total_run']) }}</div>
        </div>
        @if($pcd['remaining_km'])
        <div class="pcd-item">
            <div class="lbl">Remaining KM</div>
            <div class="val">{{ $pcd['remaining_km'] }}</div>
        </div>
        @endif
        @if($pcd['existing_monthly_km'])
        <div class="pcd-item">
            <div class="lbl">Existing Monthly KM</div>
            <div class="val">{{ $pcd['existing_monthly_km'] }}</div>
        </div>
        @endif
        @if($pcd['proposed_monthly_km'])
        <div class="pcd-item">
            <div class="lbl">Proposed Monthly KM</div>
            <div class="val">{{ $pcd['proposed_monthly_km'] }}</div>
        </div>
        @endif
    </div>
</div>
@endif

{{-- ── KM Table ─────────────────────────────────────────────────────────── --}}
@if(count($yearData) > 0)
<div style="overflow-x:auto;">
<table class="km-table">
    <thead>
        <tr>
            <th rowspan="2" style="min-width:80px;text-align:left;padding-left:6px;">Year</th>
            @foreach($monthLabels as $m)
                <th colspan="2">{{ $m }}</th>
            @endforeach
            <th colspan="3" class="gt-th">Year Total</th>
        </tr>
        <tr>
            @foreach($monthLabels as $m)
                <th class="sub-th">2W</th>
                <th class="sub-th">4W</th>
            @endforeach
            <th class="sub-th gt-th">2W</th>
            <th class="sub-th gt-th">4W</th>
            <th class="sub-th gt-th">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($yearData as $yr)
        <tr>
            <td class="yr-cell">{{ $yr['year_label'] }}</td>
            @foreach($yr['months'] as $m)
                <td class="{{ $m['km_2w'] > 0 ? '' : 'zero' }}">
                    {{ $m['km_2w'] > 0 ? number_format($m['km_2w']) : '—' }}
                </td>
                <td class="{{ $m['km_4w'] > 0 ? '' : 'zero' }}">
                    {{ $m['km_4w'] > 0 ? number_format($m['km_4w']) : '—' }}
                </td>
            @endforeach
            <td class="total-cell">{{ number_format($yr['total_2w']) }}</td>
            <td class="total-cell">{{ number_format($yr['total_4w']) }}</td>
            <td class="total-cell">{{ number_format($yr['total_2w'] + $yr['total_4w']) }}</td>
        </tr>
        @endforeach

        {{-- Grand total row --}}
        <tr class="grand-row">
            <td style="text-align:left;padding-left:6px;">Grand Total</td>
            @foreach($monthLabels as $m)
                <td></td><td></td>
            @endforeach
            <td>{{ number_format($grandTotal2W) }}</td>
            <td>{{ number_format($grandTotal4W) }}</td>
            <td>{{ number_format($grandTotal2W + $grandTotal4W) }}</td>
        </tr>
    </tbody>
</table>
</div>

{{-- ── Summary bar ─────────────────────────────────────────────────────── --}}
<div class="summary-bar">
    <div class="s-item">Years with data: <span>{{ count($yearData) }}</span></div>
    <div class="s-item">Grand 2W KM: <span>{{ number_format($grandTotal2W) }}</span></div>
    <div class="s-item">Grand 4W KM: <span>{{ number_format($grandTotal4W) }}</span></div>
    <div class="s-item">Grand Total KM: <span>{{ number_format($grandTotal2W + $grandTotal4W) }}</span></div>
    @if($pcd)
    <div class="s-item">Pre-Apr'24 2W KM: <span>{{ number_format($pcd['before_apr24']) }}</span></div>
    <div class="s-item">Overall Total (incl. pre-Apr'24): <span>{{ number_format($grandTotal2W + $grandTotal4W + $pcd['before_apr24']) }}</span></div>
    @endif
</div>

@else
<div class="no-data">
    No running KM data found for this employee.
</div>
@endif
@endsection
