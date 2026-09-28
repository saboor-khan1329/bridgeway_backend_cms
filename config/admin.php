<?php

return [
    'inquiry_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_INQUIRY_RECIPIENTS', ''))
    ))),
];
