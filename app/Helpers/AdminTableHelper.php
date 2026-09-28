<?php

use Carbon\Carbon;
use Illuminate\Support\Str;

if (! function_exists('formatCell')) {
    function formatCell($data, $field)
    {
        $value = data_get($data, $field);

        if ($field === 'is_default') {
            return $value
                ? '<span class="badge badge-success">Default</span>'
                : '<span class="badge badge-light">--</span>';
        }

        if (Str::contains($field, 'status')) {
            if (is_string($value) && trim($value) !== '') {
                $normalized = Str::lower(trim($value));
                $tone = match ($normalized) {
                    'active', 'enabled', 'paid', 'completed', 'success', 'sent', 'replied' => 'success',
                    'processing', 'in_progress', 'refunded', 'read' => 'info',
                    'pending', 'pending_payment', 'new' => 'warning',
                    'inactive', 'disabled', 'archived' => 'secondary',
                    'failed', 'cancelled', 'canceled', 'unpaid', 'blocked', 'rejected', 'spam' => 'danger',
                    default => 'secondary',
                };

                return '<span class="badge badge-' .
                    $tone .
                    '">' .
                    e(Str::headline(str_replace('_', ' ', $normalized))) .
                    '</span>';
            }

            return '<span class="badge badge-' .
                ($value ? 'success' : 'secondary') .
                '">' .
                ($value ? 'Active' : 'Inactive') .
                '</span>';
        }

        if (is_bool($value)) {
            return '<span class="badge badge-' .
                ($value ? 'info' : 'light') .
                '">' .
                ($value ? 'Yes' : 'No') .
                '</span>';
        }

        if ($value instanceof Carbon) {
            return $value->format('d M Y');
        }

        return e($value);
    }
}
