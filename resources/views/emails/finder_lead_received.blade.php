<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Quote Request</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; max-width: 640px; margin: 0 auto; padding: 24px;">
    <h2 style="margin: 0 0 4px;">New Quote Request</h2>
    <p style="margin: 0 0 20px; color: #666; font-size: 14px;">
        Submitted through the Service Finder on the website.
    </p>

    <table cellpadding="8" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%;">
        <tr><td style="background:#f6f6f6; width: 170px;"><strong>Name</strong></td><td>{{ $lead->name }}</td></tr>
        <tr><td style="background:#f6f6f6;"><strong>Phone</strong></td><td>{{ $lead->phone }}</td></tr>
        <tr><td style="background:#f6f6f6;"><strong>Email</strong></td><td>{{ $lead->email }}</td></tr>
        @if ($lead->company)
            <tr><td style="background:#f6f6f6;"><strong>Company</strong></td><td>{{ $lead->company }}</td></tr>
        @endif
        <tr><td style="background:#f6f6f6;"><strong>Service required</strong></td><td>{{ $lead->service_label ?: '—' }}</td></tr>
        <tr><td style="background:#f6f6f6;"><strong>Postcode / area</strong></td><td>{{ $lead->postcode_or_area }}</td></tr>
        @if ($lead->area)
            <tr><td style="background:#f6f6f6;"><strong>Coverage area</strong></td><td>{{ $lead->area->name }}</td></tr>
        @endif
        <tr><td style="background:#f6f6f6;"><strong>Start date</strong></td><td>{{ $lead->start_date ?: 'Not specified' }}</td></tr>
        <tr><td style="background:#f6f6f6;"><strong>Came from</strong></td><td>{{ str_replace('_', ' ', ucfirst($lead->origin)) }}</td></tr>
        @if (data_get($lead->meta, 'source_url'))
            <tr><td style="background:#f6f6f6;"><strong>Page</strong></td><td>{{ data_get($lead->meta, 'source_url') }}</td></tr>
        @endif
        <tr><td style="background:#f6f6f6;"><strong>Received</strong></td><td>{{ $lead->created_at->format('d M Y H:i') }}</td></tr>
    </table>

    <p style="margin-top: 24px; padding: 14px 16px; background:#fff6f6; border-left: 3px solid #e31e24; font-size: 14px;">
        The visitor was told a local specialist would call them back
        <strong>within 2 working hours</strong>.
    </p>
</body>
</html>
