<?php

$bool = static fn (string $key, bool $default): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
    FILTER_NULL_ON_FAILURE
) ?? $default;

$emails = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $email): string => trim($email),
    explode(',', $value)
)));

return [
    'recipients' => $emails((string) env('ADMIN_INQUIRY_RECIPIENTS', '')),

    'notifications' => [
        'enabled' => $bool('INQUIRY_NOTIFICATIONS_ENABLED', true),
        'queued' => $bool('INQUIRY_NOTIFICATIONS_QUEUED', true),
    ],

    'rate_limit' => [
        'per_minute' => (int) env('INQUIRY_RATE_LIMIT_PER_MINUTE', 5),
    ],

    'spam' => [
        'enabled' => $bool('INQUIRY_SPAM_DETECTION_ENABLED', true),
        'honeypot_field' => env('INQUIRY_HONEYPOT_FIELD', 'company'),
        'minimum_seconds' => (int) env('INQUIRY_MINIMUM_SECONDS', 2),
        'max_links' => (int) env('INQUIRY_MAX_LINKS', 3),
        'blocked_keywords' => array_values(array_filter(array_map(
            static fn (string $keyword): string => trim($keyword),
            explode(',', (string) env('INQUIRY_BLOCKED_KEYWORDS', ''))
        ))),
        'block_score' => (int) env('INQUIRY_SPAM_BLOCK_SCORE', 5),
    ],

    'captcha' => [
        'enabled' => $bool('INQUIRY_CAPTCHA_ENABLED', false),
        'driver' => env('INQUIRY_CAPTCHA_DRIVER', 'turnstile'),
        'site_key' => env('INQUIRY_CAPTCHA_SITE_KEY'),
        'secret' => env('INQUIRY_CAPTCHA_SECRET'),
        'token_field' => env('INQUIRY_CAPTCHA_TOKEN_FIELD', 'captcha_token'),
        'timeout' => (int) env('INQUIRY_CAPTCHA_TIMEOUT', 5),
        'fail_open' => $bool('INQUIRY_CAPTCHA_FAIL_OPEN', false),
        'recaptcha_min_score' => (float) env('INQUIRY_RECAPTCHA_MIN_SCORE', 0.5),
    ],
];
