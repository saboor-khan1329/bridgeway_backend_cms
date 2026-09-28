<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Onboarding Application - {{ $submission->reference_number }}</title>
<style>
    body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; color: #222; }
    .wrapper { max-width: 640px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
    .header { background: #1a1a2e; color: #fff; padding: 28px 32px; }
    .header h1 { margin: 0; font-size: 22px; }
    .header p { margin: 6px 0 0; font-size: 14px; opacity: .8; }
    .body { padding: 28px 32px; }
    .badge { display: inline-block; background: #1a1a2e; color: #fff; border-radius: 4px; padding: 4px 12px; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin-bottom: 16px; }
    table.info { width: 100%; border-collapse: collapse; margin: 16px 0; }
    table.info td { padding: 9px 12px; border-bottom: 1px solid #eee; font-size: 14px; }
    table.info td:first-child { font-weight: bold; color: #555; width: 38%; }
    .notice { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-top: 20px; }
    .footer { text-align: center; padding: 18px 32px; font-size: 12px; color: #888; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>New Onboarding Application Received</h1>
        <p>A new submission has been made through the onboarding portal.</p>
    </div>
    <div class="body">
        <div class="badge">{{ $submission->reference_number }}</div>

        <table class="info">
            <tr><td>Applicant Name</td><td>{{ $submission->applicant_name ?: '-' }}</td></tr>
            <tr><td>Email</td><td>{{ $submission->applicant_email ?: '-' }}</td></tr>
            <tr><td>Submitted At</td><td>{{ $submission->submitted_at?->format('d M Y H:i') }}</td></tr>
            <tr><td>IP Address</td><td>{{ $submission->ip_address ?: '-' }}</td></tr>
            <tr><td>Form Config</td><td>{{ $submission->formConfig?->name ?? '-' }}</td></tr>
        </table>

        <p style="font-size:14px;">The full application PDF is attached to this email. Please log in to the admin panel to review, update the status, and download individual uploaded documents.</p>

        @php
            $adminUrl = config('app.url');
        @endphp
        <p style="margin-top:20px;">
            <a href="{{ $adminUrl }}/admin/onboarding/submissions/{{ $submission->id }}"
               style="background:#1a1a2e;color:#fff;padding:10px 22px;border-radius:5px;text-decoration:none;font-size:14px;display:inline-block;">
               View Submission in Admin
            </a>
        </p>

        <div class="notice">
            <strong>Confidential:</strong> This email and its attachments contain sensitive applicant information. Handle in accordance with your data protection policy.
        </div>
    </div>
    <div class="footer">
        {{ config('app.name', 'Bridgeway Digital CMS') }} &mdash; Automated Notification &mdash; Do not reply to this email.
    </div>
</div>
</body>
</html>
