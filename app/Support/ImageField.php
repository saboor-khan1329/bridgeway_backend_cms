<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ImageField
{
    public static function singleRules(string $field, int $maxKb = 4096, bool $metadata = true): array
    {
        $rules = [
            "{$field}.file" => "nullable|image|max:{$maxKb}",
            "{$field}.path" => 'nullable|string|max:500',
            "{$field}.disk" => 'nullable|string|max:50',
            "{$field}.remove" => 'nullable|boolean',
        ];

        if ($metadata) {
            $rules["{$field}.alt"] = 'nullable|string|max:255';
            $rules["{$field}.title"] = 'nullable|string|max:255';
            $rules["{$field}.caption"] = 'nullable|string|max:1000';
            $rules["{$field}.width"] = 'nullable|integer|min:0|max:10000';
            $rules["{$field}.height"] = 'nullable|integer|min:0|max:10000';
        }

        return $rules;
    }

    public static function remapValidationException(
        string $field,
        ValidationException $exception,
        bool $multiple = false
    ): ValidationException {
        $messages = [];
        $fallbackKey = $multiple ? "{$field}.files" : "{$field}.file";

        foreach ($exception->errors() as $key => $errorMessages) {
            $mappedKey = match ((string) $key) {
                'file', 'files' => $fallbackKey,
                'path' => "{$field}.path",
                'disk' => "{$field}.disk",
                'alt' => "{$field}.alt",
                'title' => "{$field}.title",
                'caption' => "{$field}.caption",
                'width' => "{$field}.width",
                'height' => "{$field}.height",
                default => $fallbackKey,
            };

            foreach (Arr::wrap($errorMessages) as $message) {
                $messages[$mappedKey][] = $message;
            }
        }

        return ValidationException::withMessages($messages);
    }
}
