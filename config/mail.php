<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    'notification_mailer' => env('NOTIFICATION_MAILER', env('MAIL_MAILER', 'smtp')),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "log", "array", "failover"
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'notification-smtp' => [
            'transport' => env('NOTIFICATION_MAIL_TRANSPORT', 'smtp'),
            'url' => env('NOTIFICATION_MAIL_URL'),
            'host' => env('NOTIFICATION_MAIL_HOST', env('MAIL_HOST', 'smtp.mailgun.org')),
            'port' => env('NOTIFICATION_MAIL_PORT', env('MAIL_PORT', 587)),
            'encryption' => env('NOTIFICATION_MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls')),
            'username' => env('NOTIFICATION_MAIL_USERNAME', env('MAIL_USERNAME')),
            'password' => env('NOTIFICATION_MAIL_PASSWORD', env('MAIL_PASSWORD')),
            'timeout' => null,
            'local_domain' => env('NOTIFICATION_MAIL_EHLO_DOMAIN', env('MAIL_EHLO_DOMAIN')),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    'notification_from' => [
        'address' => env('NOTIFICATION_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'name' => env('NOTIFICATION_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Example')),
    ],

    'notification_reply_to' => [
        'address' => env('NOTIFICATION_MAIL_REPLY_TO_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'name' => env('NOTIFICATION_MAIL_REPLY_TO_NAME', env('MAIL_FROM_NAME', 'Example')),
    ],

    'account_approval_alert_recipients' => array_values(array_filter(array_map(
        static fn (string $email) => trim($email),
        explode(',', (string) env('ACCOUNT_APPROVAL_ALERT_RECIPIENTS', ''))
    ))),

    'support_ticket_alert_recipients' => array_values(array_filter(array_map(
        static fn (string $email) => trim($email),
        explode(',', (string) env('SUPPORT_TICKET_ALERT_RECIPIENTS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
