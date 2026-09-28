<?php

$bool = static fn (string $key, bool $default): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
    FILTER_NULL_ON_FAILURE
) ?? $default;

return [
    'enabled' => $bool('MAIL_OUTBOX_ENABLED', true),

    'queue' => [
        'enabled' => $bool('MAIL_OUTBOX_QUEUE_ENABLED', $bool('INQUIRY_NOTIFICATIONS_QUEUED', true)),
        'name' => env('MAIL_OUTBOX_QUEUE', 'mail-outbox'),
    ],

    'schedule' => [
        'enabled' => $bool('MAIL_OUTBOX_SCHEDULE_ENABLED', true),
    ],

    'cache_store' => env('MAIL_OUTBOX_CACHE_STORE'),

    'batch_size' => max(1, (int) env('MAIL_OUTBOX_BATCH_SIZE', 25)),
    'max_attempts' => max(1, (int) env('MAIL_OUTBOX_MAX_ATTEMPTS', 10)),
    'retry_seconds' => max(30, (int) env('MAIL_OUTBOX_RETRY_SECONDS', 300)),
    'lock_seconds' => max(30, (int) env('MAIL_OUTBOX_LOCK_SECONDS', 120)),
];
