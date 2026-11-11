<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Advance Request - Approved</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: #ffffff; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">

            <p>Dear Sir,</p>
            <p>This is to inform you that your advance request has been approved. The details are as follows:</p>

            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold; width: 35%;">Request Ref. No.</td>
                        <td style="padding: 8px 0;">{{ $emailData['request_no'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold;">Request Date</td>
                        <td style="padding: 8px 0;">{{ $emailData['submitted_date'] ?? $emailData['created_date'] ?? 'N/A' }}</td>
                    </tr>
                    @if (isset($emailData['requester_name']))
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold;">Requested By</td>
                            <td style="padding: 8px 0;">{{ $emailData['requester_name'] }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold;">Approved By</td>
                        <td style="padding: 8px 0;">{{ $emailData['approver_name'] ?? 'N/A' }}</td>
                    </tr>
                    @if (isset($emailData['activity_name']))
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold; vertical-align: top;">Activity Details</td>
                            <td style="padding: 8px 0;">
                                Name: {{ $emailData['activity_name'] }}<br>
                                Type: {{ $emailData['activity_type'] ?? 'N/A' }}<br>
                                @if (isset($emailData['activity_crops']) && $emailData['activity_crops'])
                                    Crop: {{ $emailData['activity_crops'] }}<br>
                                @endif
                                @if (isset($emailData['activity_varieties']) && $emailData['activity_varieties'])
                                    Variety: {{ $emailData['activity_varieties'] }}<br>
                                @endif
                            </td>
                        </tr>
                    @endif
                    @if (isset($emailData['month_year']))
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold;">Request Month</td>
                            <td style="padding: 8px 0;">{{ $emailData['month_year'] }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold;">Amount (INR)</td>
                        <td style="padding: 8px 0; font-size: 18px; color: #28a745; font-weight: bold;">₹{{ number_format($emailData['amount'], 2) }}</td>
                    </tr>
                    @if (isset($emailData['purpose']))
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold; vertical-align: top;">Purpose</td>
                            <td style="padding: 8px 0;">{{ $emailData['purpose'] }}</td>
                        </tr>
                    @endif
                </table>
            </div>

            @if (isset($emailData['activities']) && count($emailData['activities']) > 0)
                <div style="margin: 20px 0;">
                    <p style="font-weight: bold; margin-bottom: 10px;">Activity Breakdown:</p>
                    <table style="width: 100%; border-collapse: collapse; border: 1px solid #dee2e6;">
                        <thead>
                            <tr style="background-color: #e9ecef;">
                                <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Employee</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Activity Type</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Purpose</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #dee2e6;">Amount (INR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($emailData['activities'] as $activity)
                                <tr>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">{{ $activity['employee_name'] }}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">{{ $activity['activity_type'] ?? 'N/A' }}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">{{ $activity['purpose'] ?? 'N/A' }}</td>
                                    <td style="padding: 8px; text-align: right; border: 1px solid #dee2e6;">₹{{ number_format($activity['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <p>For checking the status of the advance & further details, please login to Xeasy.</p>

            @if (isset($emailData['remarks']) && $emailData['remarks'])
                <div style="background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <p style="margin: 0; font-weight: bold; color: #155724;">Remarks:</p>
                    <p style="margin: 5px 0 0 0;">{{ $emailData['remarks'] }}</p>
                </div>
            @endif

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <p style="margin: 0;">Regards,<br>Admin (Xeasy)</p>
            </div>
        </div>
    </div>
</body>

</html>
