<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Expense Claim Update</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 640px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: #ffffff; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <p>Dear {{ $data['employeeName'] ?? 'Employee' }},</p>

            @if($kind === 'deduction')
                <p>The following expense claim amounts were changed during {{ ($data['stage'] ?? '') === 'approval' ? 'approval' : 'verification' }}:</p>
                <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 13px;">
                    <tr style="background:#f0f4f8;">
                        <th align="left" style="border:1px solid #ddd;">Claim</th>
                        <th align="left" style="border:1px solid #ddd;">Bill date</th>
                        <th align="right" style="border:1px solid #ddd;">Claimed (Rs)</th>
                        <th align="right" style="border:1px solid #ddd;">Allowed (Rs)</th>
                        <th align="right" style="border:1px solid #ddd;">Deducted (Rs)</th>
                        <th align="left" style="border:1px solid #ddd;">Remark</th>
                    </tr>
                    @foreach(($data['lines'] ?? []) as $line)
                        <tr>
                            <td style="border:1px solid #ddd;">{{ $line['claim'] }} (#{{ $line['exp_id'] }})</td>
                            <td style="border:1px solid #ddd;">{{ $line['bill_date'] }}</td>
                            <td align="right" style="border:1px solid #ddd;">{{ number_format($line['from'], 2) }}</td>
                            <td align="right" style="border:1px solid #ddd;">{{ number_format($line['to'], 2) }}</td>
                            <td align="right" style="border:1px solid #ddd;">{{ number_format($line['from'] - $line['to'], 2) }}</td>
                            <td style="border:1px solid #ddd;">{{ $line['remark'] !== '' ? $line['remark'] : '—' }}</td>
                        </tr>
                    @endforeach
                </table>
            @elseif($kind === 'month_approved')
                <p>Your expense claims for <strong>{{ $data['monthName'] ?? '' }}</strong> have been approved.</p>
                @if(!empty($data['amount']))<p><strong>Approved amount:</strong> Rs {{ number_format((float) $data['amount'], 2) }}</p>@endif
            @elseif($kind === 'month_financed')
                <p>Your expense claims for <strong>{{ $data['monthName'] ?? '' }}</strong> have been cleared by Finance and are queued for payment.</p>
                @if(!empty($data['amount']))<p><strong>Finance amount:</strong> Rs {{ number_format((float) $data['amount'], 2) }}</p>@endif
            @elseif($kind === 'payment_confirmed')
                <p>Payment for your <strong>{{ $data['monthName'] ?? '' }}</strong> expense claims has been made.</p>
                <p>
                    <strong>Amount:</strong> Rs {{ number_format((float) ($data['amount'] ?? 0), 2) }}<br>
                    <strong>Payment date:</strong> {{ $data['date'] ?? '—' }}
                    @if(!empty($data['utr']))<br><strong>UTR / reference:</strong> {{ $data['utr'] }}@endif
                </p>
            @endif

            @if(!empty($data['remark']))
                <p><strong>Remark:</strong> {{ $data['remark'] }}</p>
            @endif

            <p>For details, please login to Xeasy.</p>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <p style="margin: 0;">Regards,<br>Admin (Xeasy)</p>
            </div>
        </div>
    </div>
</body>

</html>
