<?php

namespace App\Support;

use App\Models\ContentBlock;
use App\Models\ContentBlockField;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stores a section as typed leaf fields rather than one opaque JSON document.
 * Paths use JSON Pointer escaping so arbitrary CMS keys remain reversible.
 */
class ContentFieldStore
{
    private const MAX_DEPTH = 12;
    private const MAX_FIELDS = 2000;
    private const MAX_VALUE_LENGTH = 100000;

    public static function replace(ContentBlock $block, array $data): void
    {
        $rows = [];
        self::flatten($data, '', $rows, 0);

        if (count($rows) > self::MAX_FIELDS) {
            throw ValidationException::withMessages([
                'sections' => 'A section cannot contain more than '.self::MAX_FIELDS.' fields.',
            ]);
        }

        DB::transaction(function () use ($block, $rows): void {
            $block->fields()->delete();

            foreach (array_chunk($rows, 250) as $chunk) {
                $block->fields()->createMany($chunk);
            }
        });

        $block->unsetRelation('fields');
    }

    public static function read(ContentBlock $block): array
    {
        $fields = $block->relationLoaded('fields')
            ? $block->fields
            : $block->fields()->orderBy('sort_order')->orderBy('id')->get();

        $root = [];

        foreach ($fields as $field) {
            $segments = self::segments($field->path);
            $value = self::cast($field->value_type, $field->value);

            if ($segments === []) {
                $root = is_array($value) ? $value : [];
                continue;
            }

            self::set($root, $segments, $value);
        }

        return $root;
    }

    /** Sanitize posted CMS data while preserving intentional scalar types. */
    public static function normalize(mixed $value, mixed $prototype = null, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages(['sections' => 'Section fields are nested too deeply.']);
        }

        if (is_array($value)) {
            $normalized = [];
            $listPrototype = is_array($prototype) && array_is_list($prototype)
                ? ($prototype[0] ?? null)
                : null;

            foreach ($value as $key => $nested) {
                if (! is_int($key) && ! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,100}$/', (string) $key)) {
                    throw ValidationException::withMessages(['sections' => "Invalid CMS field name '{$key}'."]);
                }

                $nestedPrototype = is_array($prototype)
                    ? ($prototype[$key] ?? $listPrototype)
                    : null;
                $normalized[$key] = self::normalize($nested, $nestedPrototype, $depth + 1);
            }

            return array_is_list($value) ? array_values($normalized) : $normalized;
        }

        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);
        if (mb_strlen($string) > self::MAX_VALUE_LENGTH) {
            throw ValidationException::withMessages(['sections' => 'A CMS field is too long.']);
        }

        if ((is_int($prototype) && filter_var($string, FILTER_VALIDATE_INT) === false)
            || (is_float($prototype) && (! is_numeric($string) || ! is_finite((float) $string)))) {
            throw ValidationException::withMessages(['sections' => 'Enter a valid number for numeric CMS fields.']);
        }
        if (is_bool($prototype) && filter_var($string, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
            throw ValidationException::withMessages(['sections' => 'Enter a valid boolean for checkbox CMS fields.']);
        }

        return match (true) {
            is_bool($prototype) => filter_var($string, FILTER_VALIDATE_BOOLEAN),
            is_int($prototype) => filter_var($string, FILTER_VALIDATE_INT) !== false ? (int) $string : 0,
            is_float($prototype) => is_numeric($string) ? (float) $string : 0.0,
            default => $string,
        };
    }

    /** Produce an empty editor row with the same typed field structure. */
    public static function blankLike(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return $value === [] ? [] : [self::blankLike($value[0])];
            }

            return collect($value)->map(fn ($nested) => self::blankLike($nested))->all();
        }

        return match (true) {
            is_bool($value) => false,
            is_int($value) => 0,
            is_float($value) => 0.0,
            default => '',
        };
    }

    private static function flatten(mixed $value, string $path, array &$rows, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages(['sections' => 'Section fields are nested too deeply.']);
        }

        if (is_array($value)) {
            $rows[] = self::row($path, array_is_list($value) ? 'array' : 'object', null, count($rows));
            foreach ($value as $key => $nested) {
                $childPath = $path.'/'.self::escape((string) $key);
                self::flatten($nested, $childPath, $rows, $depth + 1);
            }
            return;
        }

        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            $value === null => 'null',
            default => 'string',
        };

        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'null' => null,
            default => (string) $value,
        };

        $rows[] = self::row($path, $type, $stored, count($rows));
    }

    private static function row(string $path, string $type, ?string $value, int $order): array
    {
        return ['path' => $path, 'value_type' => $type, 'value' => $value, 'sort_order' => $order];
    }

    private static function cast(string $type, ?string $value): mixed
    {
        return match ($type) {
            'array', 'object' => [],
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => $value === '1',
            'null' => null,
            default => (string) $value,
        };
    }

    private static function set(array &$root, array $segments, mixed $value): void
    {
        $cursor =& $root;
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            $key = ctype_digit($segment) ? (int) $segment : $segment;
            if (! isset($cursor[$key]) || ! is_array($cursor[$key])) {
                $cursor[$key] = [];
            }
            $cursor =& $cursor[$key];
        }

        $key = ctype_digit((string) $last) ? (int) $last : $last;
        $cursor[$key] = $value;
    }

    private static function segments(string $path): array
    {
        if ($path === '') {
            return [];
        }

        return array_map(
            fn (string $segment) => str_replace(['~1', '~0'], ['/', '~'], $segment),
            explode('/', ltrim($path, '/'))
        );
    }

    private static function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
