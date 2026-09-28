<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Inquiry</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; max-width: 640px; margin: 0 auto; padding: 24px;">
    <h2 style="margin: 0 0 16px;">New Inquiry Received</h2>

    <table cellpadding="8" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%;">
        <tr><td style="background:#f6f6f6; width: 140px;"><strong>Name</strong></td><td>{{ $inquiry->name }}</td></tr>
        <tr><td style="background:#f6f6f6;"><strong>Email</strong></td><td>{{ $inquiry->email ?: '--' }}</td></tr>
        @if ($inquiry->phone)
            <tr><td style="background:#f6f6f6;"><strong>Phone</strong></td><td>{{ $inquiry->phone }}</td></tr>
        @endif
        @if ($inquiry->subject)
            <tr><td style="background:#f6f6f6;"><strong>Subject</strong></td><td>{{ $inquiry->subject }}</td></tr>
        @endif
        @if ($inquiry->form_name)
            <tr><td style="background:#f6f6f6;"><strong>Form</strong></td><td>{{ $inquiry->form_name }}</td></tr>
        @endif
        @if ($inquiry->type_of_service_required)
            <tr><td style="background:#f6f6f6;"><strong>Type of Service Required</strong></td><td>{{ $inquiry->type_of_service_required }}</td></tr>
        @endif
        @if ($inquiry->source_url)
            <tr><td style="background:#f6f6f6;"><strong>Source</strong></td><td>{{ $inquiry->source_url }}</td></tr>
        @endif
        <tr><td style="background:#f6f6f6;"><strong>Date</strong></td><td>{{ $inquiry->created_at->format('d M Y H:i') }}</td></tr>
    </table>

    <h3 style="margin-top: 24px;">Message</h3>
    <p style="white-space: pre-wrap; background:#f9f9f9; padding: 16px; border-left: 3px solid #ddd;">{{ $inquiry->message }}</p>

    @php($details = collect($inquiry->details ?? [])->except('attachment'))
    @if ($details->isNotEmpty())
        <h3 style="margin-top: 24px;">Additional Details</h3>
        <table cellpadding="8" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%;">
            @foreach ($details as $field => $value)
                <tr>
                    <td style="background:#f6f6f6; width: 140px;"><strong>{{ \Illuminate\Support\Str::headline($field) }}</strong></td>
                    <td>{{ is_array($value) ? collect($value)->implode(', ') : $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
