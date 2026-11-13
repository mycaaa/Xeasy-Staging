<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Claim Stage Report - {{ $company }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #333; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #15648C; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #15648C; text-decoration: underline; margin-bottom: 4px; }
        .header h2 { font-size: 14px; color: #333; margin-bottom: 4px; }
        .header .date { font-size: 10px; color: #666; }
        .logo-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: center; }
        th { background-color: #dde6ff; color: #1e3a8a; font-weight: bold; font-size: 9px; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .separator td { height: 6px; background: #f0f0f0; border: none; }
        .highlight-row { background-color: #FFFFB3; }
        .link-row td { color: #0066cc; }
        .kpi-row { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .kpi-card { text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 16%; }
        .kpi-card .value { font-size: 16px; font-weight: bold; color: #15648C; }
        .kpi-card .label { font-size: 8px; color: #666; }
        .footer { text-align: center; margin-top: 20px; font-size: 8px; color: #999; border-top: 1px solid #ddd; padding-top: 5px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CLAIM STAGE REPORT - {{ $company }}</h1>
        <h2>({{ $periodLabel }})</h2>
        <div class="date">(As on {{ $generatedAt }})</div>
    </div>

    {{-- Summary Table --}}
    <table>
        <thead>
            <tr>
                <th class="text-left" style="width:35%;">Claim Status</th>
                @foreach($months as $m)
                    <th>{{ $monthNames[$m] ?? 'M'.$m }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-left">Total Claimant</td>
                @foreach($months as $m)
                    <td>{{ $data[$m]['total_claimant'] ?? 0 }}</td>
                @endforeach
            </tr>
            <tr>
                <td class="text-left">Less: No claims submitted till date for same month</td>
                @foreach($months as $m)
                    <td>{{ $data[$m]['no_claims_submitted'] ?? 0 }}</td>
                @endforeach
            </tr>
            <tr class="bold highlight-row">
                <td class="text-left bold">Total Claims</td>
                @foreach($months as $m)
                    <td class="bold">{{ $data[$m]['total_claims'] ?? 0 }}</td>
                @endforeach
            </tr>
            <tr>
                <td class="text-left">Total paid</td>
                @foreach($months as $m)
                    <td>{{ $data[$m]['total_paid'] ?? 0 }}</td>
                @endforeach
            </tr>
            <tr class="bold highlight-row">
                <td class="text-left bold">% Complete</td>
                @foreach($months as $m)
                    <td class="bold">{{ $data[$m]['percent_complete'] ?? 0 }}%</td>
                @endforeach
            </tr>
            <tr class="separator"><td colspan="{{ count($months) + 1 }}"></td></tr>
            <tr class="link-row">
                <td class="text-left">Total open, final submission not done</td>
                @foreach($months as $m)
                    <td>{{ $data[$m]['open_final_submission'] ?? 0 }}</td>
                @endforeach
            </tr>
            <tr class="link-row">
                <td class="text-left">Total submitted, courier not sent</td>
                @foreach($months as $m)
                    <td>{{ $data[$m]['submitted_courier_not_sent'] ?? 0 }}</td>
                @endforeach
            </tr>
            <tr class="link-row">
                <td class="text-left">Total received, pending for verification</td>
                @foreach($months as $m)
                    <td>{{ $data[$m]['pending_verification'] ?? 0 }}</td>
                @endforeach
            </tr>
            <tr class="link-row">
                <td class="text-left">Total verified, pending for manager approval</td>
                @foreach($months as $m)
                    <td>{{ $data[$m]['pending_approval'] ?? 0 }}</td>
                @endforeach
            </tr>
            <tr class="link-row">
                <td class="text-left">Total approved, pending for payment</td>
                @foreach($months as $m)
                    <td>{{ $data[$m]['pending_payment'] ?? 0 }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Generated by xEasy Expense Management System | {{ \App\Support\ReportStamp::text() }}
    </div>
</body>
</html>
