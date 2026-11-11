{{-- PRIV-01 (7 Nov 2026): employee privacy notice for Xeasy (web and mobile app), drafted on the lines of the
     Digital Personal Data Protection Act, 2023. HR / Legal to confirm the grievance officer and retention periods. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xeasy privacy notice</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1f2933; background: #f7f8fa; margin: 0; line-height: 1.55; }
        main { max-width: 820px; margin: 0 auto; padding: 24px 16px 48px; background: #fff; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; } h2 { font-size: 1.05rem; margin: 22px 0 6px; }
        p, li { font-size: .95rem; } .muted { color: #6b7280; font-size: .85rem; }
        table { border-collapse: collapse; width: 100%; font-size: .9rem; } th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; } th { background: #f3f4f6; }
    </style>
</head>
<body>
<main>
    <h1>Xeasy privacy notice</h1>
    <p class="muted">For employees of VNR Seeds Pvt. Ltd. and its group companies who use Xeasy (web and mobile app). Version 1.0, 7 November 2026.</p>

    <h2>1. Who is responsible</h2>
    <p>VNR Seeds Pvt. Ltd. (and, for its employees, the group company that employs you) decides why and how your personal data is processed in Xeasy and is the data fiduciary under the Digital Personal Data Protection Act, 2023.</p>

    <h2>2. What we collect</h2>
    <table>
        <tr><th>Data</th><th>Where it comes from</th></tr>
        <tr><td>Name, employee code, department, grade, headquarters, reporting manager, eligibility (lodging, DA, vehicle rates)</td><td>HR records (HRIMS)</td></tr>
        <tr><td>Expense claims: bills, tickets, hotel and travel details, GST number, amounts, remarks</td><td>You, and the punching team who key your bills</td></tr>
        <tr><td>Photos and files you upload: bill images, odometer photos, UPI payment screenshots, boarding passes</td><td>You, or the travel vendor (boarding passes)</td></tr>
        <tr><td>Location of the phone when an odometer photo is taken (latitude and longitude)</td><td>The Xeasy app, only at the moment of the odometer upload</td></tr>
        <tr><td>Vehicle details (registration, insurance, licence), as needed for running-km claims</td><td>HR records (HRIMS)</td></tr>
        <tr><td>Bank account used for reimbursement, payment references (UTR), advances and their adjustment</td><td>HR records, Finance and the bank</td></tr>
        <tr><td>Login details, app device token, and a log of actions in Xeasy (who did what, when)</td><td>Xeasy</td></tr>
    </table>
    <p>Xeasy does not ask for health, religion, caste or similar sensitive data. Please do not upload documents that show more than the claim needs.</p>

    <h2>3. Why we use it</h2>
    <ul>
        <li>To check, approve and pay your expense claims and advances under the company's travel and expense policy.</li>
        <li>To verify claims: matching bills, odometer readings and the location of odometer photos, and finding duplicate or wrong claims.</li>
        <li>To keep accounts and meet tax, GST, audit and other legal requirements.</li>
        <li>To run and secure the system (logins, access control, error logs).</li>
    </ul>
    <p>We process this data because it is needed for your employment and for these legal obligations (legitimate uses under section 7 of the Act). The phone location is collected only at the odometer upload; Xeasy does not track your location at other times.</p>

    <h2>4. Who sees it</h2>
    <ul>
        <li>You; your reporting manager and the approvers in your chain; the punching, verification, Finance and Accounts teams; HR; Admin users who run the system.</li>
        <li>Internal and statutory auditors and, when the law requires it, government authorities.</li>
        <li>Service providers who process data for us under contract: cloud hosting and file storage, the bill-reading (OCR) service, the bank, the accounting system (Focus) and the e-mail service. They may use it only to provide their service to us.</li>
    </ul>
    <p>We do not sell personal data or use it for advertising.</p>

    <h2>5. How long we keep it</h2>
    <p>Claim records, bills and payment records are kept for at least 8 years after the end of the financial year, as accounting and tax laws require. Odometer location points and app logs are kept for as long as the related claim record. After that, data is deleted or anonymised unless a legal claim or audit needs it.</p>

    <h2>6. How we protect it</h2>
    <p>Access is by login and role (you see your own claims; managers see their team; staff see what their job needs). Files are kept in access-controlled storage, connections are encrypted, and changes to claims and payments are logged.</p>

    <h2>7. Your rights</h2>
    <ul>
        <li>See a summary of your data in Xeasy, and ask for a correction if something is wrong or incomplete.</li>
        <li>Ask for data to be erased when it is no longer needed and no law requires us to keep it.</li>
        <li>Raise a grievance and, if it is not resolved, approach the Data Protection Board of India.</li>
        <li>Nominate a person to exercise these rights if you are unable to.</li>
    </ul>

    <h2>8. Contact</h2>
    <p>For questions, corrections or grievances, write to the HR department (Grievance Officer: {{ config('app.privacy_contact', 'to be named by HR') }}). We aim to reply within 30 days.</p>

    <h2>9. Changes</h2>
    <p>If we change this notice, the new version will be shown here and in the app, with its date.</p>
</main>
</body>
</html>
