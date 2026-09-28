<?php

return [
    'disk' => env('ADMIN_FILE_MANAGER_DISK', 'public'),
    'quota_bytes' => (int) env('ADMIN_FILE_MANAGER_QUOTA_BYTES', 5 * 1024 * 1024 * 1024),
    'max_upload_kb' => (int) env('ADMIN_FILE_MANAGER_MAX_UPLOAD_KB', 10240),
    'allowed_extensions' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ADMIN_FILE_MANAGER_ALLOWED_EXTENSIONS',
            'jpg,jpeg,png,gif,webp,svg,ico,pdf,doc,docx,xls,xlsx,csv,txt,zip'
        ))
    ))),
    'image_extensions' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ADMIN_FILE_MANAGER_IMAGE_EXTENSIONS',
            'jpg,jpeg,png,gif,webp,svg,ico'
        ))
    ))),
];
