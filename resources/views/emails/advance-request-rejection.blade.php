<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Advance Request - Rejected</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: #ffffff; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">

            <p>Dear Sir,</p>
            <p>This is to inform you that your advance request has been rejected. The details are as follows:</p>

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
                        <td style="padding: 8px 0; font-weight: bold;">Rejected By</td>
                        <td style="padding: 8px 0;">{{ $emailData['approver_name'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: bold;">Rejection Level</td>
                        <td style="padding: 8px 0;">Level {{ $emailData['approval_level'] ?? 'N/A' }}</td>
                    </tr>
                    @if (isset($emailData['activity_name']))
                        <tr>
                            <td style="padding: 8px 0; font-weight: bold;">Activity Details</td>
                            <td style="padding: 8px 0;">{{ $emailData['activity_name'] }} / {{ $emailData['activity_type'] ?? 'N/A' }}</td>
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
                        <td style="padding: 8px 0; font-size: 18px; color: #dc3545; font-weight: bold;">₹{{ number_format($emailData['amount'], 2) }}</td>
                    </tr>
                </table>
            </div>

            <p>For more details, please login to Xeasy.</p>

            @if (isset($emailData['remarks']) && $emailData['remarks'])
                <div style="background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                    <p style="margin: 0; font-weight: bold; color: #721c24;">Rejection Reason:</p>
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
