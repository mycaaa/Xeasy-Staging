<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Claim Approval Pending</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: #ffffff; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h2 style="color: #0066cc; margin-bottom: 20px;">Claim Approval Pending</h2>

            <p>Dear Team,</p>

            <p>Please take necessary action to pending claims (approval level) for your team member <strong>{{ $employeeName }}</strong>.</p>

            <p><strong>Month:</strong> {{ $monthName }}</p>

            <p>For details, please login to Xeasy.</p>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <p style="margin: 0;">Regards,<br>Admin (Xeasy)</p>
            </div>
        </div>
    </div>
</body>

</html>