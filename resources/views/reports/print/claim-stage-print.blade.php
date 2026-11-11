<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Claim Stage Report - {{ $company }}</title>
    <style>
        @page { size: landscape; margin: 15mm; }
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; background: white; }
        .slide { width: 100%; min-height: 680px; padding: 30px; position: relative; page-break-after: always; }
        .slide-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #15648C; }
        .slide-header .logo { width: 80px; }
        .slide-header .title { text-align: center; flex: 1; }
        .slide-header .title h1 { font-size: 22px; color: #15648C; text-decoration: underline; }
        .slide-header .title h2 { font-size: 16px; color: #333; margin-top: 4px; }
        .slide-header .title .date { font-size: 10px; color: #666; margin-top: 2px; }
        .slide-header .brand { width: 80px; text-align: right; color: #198754; font-weight: bold; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: center; font-size: 11px; }
        th { background: #E8C49C; color: #333; font-weight: bold; }
        .row-bold td { font-weight: bold; }
        .row-separator td { height: 8px; background: #f5f5f5; border: none; }
        .row-link td { color: #0066cc; text-decoration: underline; cursor: pointer; }
        .text-left { text-align: left; padding-left: 10px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin: 15px 0; }
        .kpi-card { text-align: center; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f8f9fa; }
        .kpi-card .value { font-size: 20px; font-weight: bold; }
        .kpi-card .label { font-size: 9px; color: #666; margin-top: 2px; }
        .print-btn { position: fixed; top: 10px; right: 10px; padding: 8px 20px; background: #15648C; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; z-index: 999; }
        .print-btn:hover { background: #0d4a6b; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print / Save as PDF</button>

    {{-- SLIDE 1: Claim Stage Summary --}}
    <div class="slide">
        <div class="slide-header">
            <div class="logo">
                <img alt="VNR logo" src="{{ public_path('assets/images/vnr-logo.png') }}" style="width:60px;" onerror="this.style.display='none'">
                <div style="font-size:9px;color:#333;">www.vnrseeds.com</div>
            </div>
            <div class="title">
                <h1>CLAIM STAGE REPORT - {{ $company }}</h1>
                @php
                    $mNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    $first = $mNames[$months[0] ?? 1] ?? 'Jan';
                    $last = $mNames[end($months) ?? 12] ?? 'Dec';
                @endphp
                <h2>({{ $first }}'25 - {{ $last }}'26)</h2>
                <div class="date">(As on {{ now()->format('d-m-Y h:i A') }})</div>
            </div>
            <div class="brand">
                vnr<span style="color:#dc3545;">seeds</span><br>
                <span style="font-size:8px;color:#666;">Corporate Internet Strategy</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="text-left" style="width:35%;">Claim Status</th>
                    @foreach($months as $m)
                        <th>{{ $mNames[$m] ?? '' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-left">Total Claimant</td>
                    @foreach($months as $m)<td>{{ $data[$m]['total_claimant'] ?? 0 }}</td>@endforeach
                </tr>
                <tr>
                    <td class="text-left">Less: No claims submitted till date for same month</td>
                    @foreach($months as $m)<td>{{ $data[$m]['no_claims_submitted'] ?? 0 }}</td>@endforeach
                </tr>
                <tr class="row-bold">
                    <td class="text-left">Total Claims</td>
                    @foreach($months as $m)<td>{{ $data[$m]['total_claims'] ?? 0 }}</td>@endforeach
                </tr>
                <tr>
                    <td class="text-left">Total paid</td>
                    @foreach($months as $m)<td>{{ $data[$m]['total_paid'] ?? 0 }}</td>@endforeach
                </tr>
                <tr class="row-bold">
                    <td class="text-left">% Complete</td>
                    @foreach($months as $m)<td>{{ $data[$m]['percent_complete'] ?? 0 }}%</td>@endforeach
                </tr>
                <tr class="row-separator"><td colspan="{{ count($months) + 1 }}"></td></tr>
                <tr class="row-link">
                    <td class="text-left">Total open, final submission not done</td>
                    @foreach($months as $m)<td>{{ $data[$m]['open_final_submission'] ?? 0 }}</td>@endforeach
                </tr>
                <tr class="row-link">
                    <td class="text-left">Total submitted, courier not sent</td>
                    @foreach($months as $m)<td>{{ $data[$m]['submitted_courier_not_sent'] ?? 0 }}</td>@endforeach
                </tr>
                <tr class="row-link">
                    <td class="text-left">Total received, pending for verification</td>
                    @foreach($months as $m)<td>{{ $data[$m]['pending_verification'] ?? 0 }}</td>@endforeach
                </tr>
                <tr class="row-link">
                    <td class="text-left">Total verified, pending for manager approval</td>
                    @foreach($months as $m)<td>{{ $data[$m]['pending_approval'] ?? 0 }}</td>@endforeach
                </tr>
                <tr class="row-link">
                    <td class="text-left">Total approved, pending for payment</td>
                    @foreach($months as $m)<td>{{ $data[$m]['pending_payment'] ?? 0 }}</td>@endforeach
                </tr>
            </tbody>
        </table>
    </div>

    {{-- SLIDE 2: TAT Analysis --}}
    <div class="slide">
        <div class="slide-header">
            <div class="logo"></div>
            <div class="title">
                <h1>TAT - MONTHLY ANALYSIS</h1>
                <h2>{{ $company }}</h2>
                <div class="date">(As on {{ now()->format('d-m-Y h:i A') }})</div>
            </div>
            <div class="brand">vnr<span style="color:#dc3545;">seeds</span></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Submit to Courier</th>
                    <th>Post to Receive</th>
                    <th>Receive to Verify</th>
                    <th>Verify to Approve</th>
                    <th>Approve to Pay</th>
                    <th>Courier Receipt to Pay</th>
                </tr>
            </thead>
            <tbody>
                @foreach($months as $m)
                    @if(isset($tatData[$m]))
                    <tr>
                        <td class="text-left">{{ $mNames[$m] ?? '' }}</td>
                        <td>{{ $tatData[$m]['submit_to_courier'] ?? 0 }}</td>
                        <td>{{ $tatData[$m]['post_to_receive'] ?? 0 }}</td>
                        <td>{{ $tatData[$m]['receive_to_verify'] ?? 0 }}</td>
                        <td>{{ $tatData[$m]['verify_to_approve'] ?? 0 }}</td>
                        <td>{{ $tatData[$m]['approve_to_pay'] ?? 0 }}</td>
                        <td style="font-weight:bold;">{{ $tatData[$m]['courier_receipt_to_pay'] ?? 0 }}</td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
