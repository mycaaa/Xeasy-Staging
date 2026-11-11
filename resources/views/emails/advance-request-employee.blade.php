<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Advance Request Submitted on Your Behalf</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: #ffffff; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">

            <p>Dear Sir,</p>
            <p>This is to inform you that an advance request has been submitted on your behalf. The details are as follows:</p>

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
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold;">Request by</td>
                        <td style="padding: 8px 0;">{{ $emailData['requester_code'] ?? 'N/A' }} - {{ $emailData['requester_name'] }} ({{ $emailData['requester_designation'] ?? 'N/A' }})</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold; vertical-align: top;">Activity Details</td>
                        <td style="padding: 8px 0;">
                            Name: {{ $emailData['activity_name'] ?? 'N/A' }}<br>
                            Type: {{ $emailData['activity_type'] ?? 'N/A' }}<br>
                            @if (isset($emailData['activity_crops']) && $emailData['activity_crops'])
                                Crop: {{ $emailData['activity_crops'] }}<br>
                            @endif
                            @if (isset($emailData['activity_varieties']) && $emailData['activity_varieties'])
                                Variety: {{ $emailData['activity_varieties'] }}<br>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold;">Activity Month</td>
                        <td style="padding: 8px 0;">{{ $emailData['month_year'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold;">Amount (INR)</td>
                        <td style="padding: 8px 0; font-size: 18px; color: #0066cc; font-weight: bold;">₹{{ number_format($emailData['amount'], 2) }}</td>
                    </tr>
                </table>
            </div>

            <p>For checking the status of the advance & further details, please login to Xeasy.</p>

            @if (isset($emailData['remarks']) && $emailData['remarks'])
                <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <p style="margin: 0; font-weight: bold; color: #856404;">Remarks:</p>
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
