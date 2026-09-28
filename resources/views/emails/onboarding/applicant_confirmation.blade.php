<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Application Received - {{ config('app.name', 'Bridgeway Digital CMS') }}</title>
<style>
    body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; color: #222; }
    .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
    .header { background: #1a1a2e; color: #fff; padding: 32px; text-align: center; }
    .header h1 { margin: 0; font-size: 22px; }
    .header p { margin: 8px 0 0; font-size: 14px; opacity: .8; }
    .body { padding: 32px; }
    .ref-box { background: #f0f4ff; border: 2px solid #1a1a2e; border-radius: 6px; text-align: center; padding: 16px; margin: 20px 0; }
    .ref-box .label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
    .ref-box .ref { font-size: 24px; font-weight: bold; color: #1a1a2e; letter-spacing: 2px; margin-top: 4px; }
    .steps { margin: 24px 0; }
    .step { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 16px; }
    .step-num { background: #1a1a2e; color: #fff; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0; }
    .step-text h4 { margin: 0 0 2px; font-size: 14px; }
    .step-text p { margin: 0; font-size: 13px; color: #555; }
    .notice { background: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-top: 20px; }
    .footer { text-align: center; padding: 18px; font-size: 12px; color: #888; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Application Received</h1>
        <p>Thank you for submitting your application.</p>
    </div>
    <div class="body">
        <p style="font-size:15px;">Dear <strong>{{ $submission->applicant_name ?: 'Applicant' }}</strong>,</p>

        <p style="font-size:14px;">We have successfully received your application. Please keep your reference number safe; you will need it if you contact us.</p>

        <div class="ref-box">
            <div class="label">Your Reference Number</div>
            <div class="ref">{{ $submission->reference_number }}</div>
        </div>

        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text">
                    <h4>Application Under Review</h4>
                    <p>Our team will review your application and the documents you provided.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">
                    <h4>We Will Be In Touch</h4>
                    <p>Once the review is complete, a member of our team will contact you directly.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">
                    <h4>Further Information</h4>
                    <p>We may contact you if any additional information or documents are required.</p>
                </div>
            </div>
        </div>

        @php
            $message = $formConfig->getSetting('applicant_confirmation_message');
        @endphp

        @if ($message)
            <p style="font-size:14px;margin-top:16px;">{{ $message }}</p>
        @endif

        <div class="notice">
            <strong>Submitted:</strong> {{ $submission->submitted_at?->format('d M Y \a\t H:i') }}
        </div>

        <p style="font-size:13px;color:#666;margin-top:20px;">If you did not submit this application, please contact us immediately at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</p>
    </div>
    <div class="footer">
        {{ config('app.name', 'Bridgeway Digital CMS') }} &mdash; This is an automated message, please do not reply.
    </div>
</div>
</body>
</html>
