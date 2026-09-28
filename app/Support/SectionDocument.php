<?php

namespace App\Support;

use App\Models\ContentBlock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Validates and persists the structured section-editor request. */
class SectionDocument
{
    public static function fromInput(array $input, ?Model $owner = null): array
    {
        if ($input === []) {
            throw ValidationException::withMessages(['sections' => 'Add at least one section.']);
        }

        $sections = [];
        $ids = [];

        foreach (array_values($input) as $index => $section) {
            if (! is_array($section)) {
                throw ValidationException::withMessages(['sections' => "Section {$index} is invalid."]);
            }

            $type = trim((string) ($section['type'] ?? ''));
            $id = trim((string) ($section['id'] ?? ''));

            if (! FrontendSectionRegistry::supports($type)) {
                throw ValidationException::withMessages(['sections' => "Section {$index} has unsupported type '{$type}'."]);
            }

            if ($id === '' || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,190}$/', $id) || in_array($id, $ids, true)) {
                throw ValidationException::withMessages(['sections' => "Section {$index} needs a unique, URL-safe CMS key."]);
            }

            $prototype = self::prototype($type, $owner, $id);
            $data = ContentFieldStore::normalize($section['data'] ?? [], $prototype);
            if (! is_array($data)) {
                throw ValidationException::withMessages(['sections' => "Section {$index} fields are invalid."]);
            }

            $ids[] = $id;
            $sections[] = [
                'id' => $id,
                'type' => $type,
                'enabled' => filter_var($section['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'data' => $data,
            ];
        }

        return $sections;
    }

    public static function sync(Model $owner, array $sections): void
    {
        $keys = [];

        foreach ($sections as $order => $section) {
            $keys[] = $section['id'];
            $block = ContentBlock::updateOrCreate([
                'blockable_type' => $owner::class,
                'blockable_id' => $owner->getKey(),
                'section_key' => $section['id'],
            ], [
                'type' => $section['type'],
                'sort_order' => $order,
                'is_active' => $section['enabled'],
            ]);

            ContentFieldStore::replace($block, $section['data']);
        }

        ContentBlock::query()
            ->where('blockable_type', $owner::class)
            ->where('blockable_id', $owner->getKey())
            ->whereNotNull('section_key')
            ->whereNotIn('section_key', $keys)
            ->delete();
    }

    public static function forEditor(Model $owner): array
    {
        return $owner->contentBlocks->map(fn (ContentBlock $block) => [
            'id' => $block->section_key ?: $block->type.'-'.$block->id,
            'type' => $block->type,
            'enabled' => (bool) $block->is_active,
            'data' => $block->cmsData(),
        ])->values()->all();
    }

    public static function blankSection(string $type, ?Model $owner = null): array
    {
        if (! FrontendSectionRegistry::supports($type)) {
            throw ValidationException::withMessages(['add_section_type' => 'Choose a supported section type.']);
        }

        return [
            'id' => $type.'-'.substr(bin2hex(random_bytes(5)), 0, 10),
            'type' => $type,
            'enabled' => true,
            'data' => ContentFieldStore::blankLike(self::prototype($type, $owner)),
        ];
    }

    private static function prototype(string $type, ?Model $owner = null, ?string $id = null): array
    {
        $query = ContentBlock::query()->where('type', $type)->with('fields');

        if ($owner && $owner->exists && $id) {
            $owned = (clone $query)
                ->where('blockable_type', $owner::class)
                ->where('blockable_id', $owner->getKey())
                ->where('section_key', $id)
                ->first();

            if ($owned) {
                return $owned->cmsData();
            }
        }

        return FrontendSectionSchema::prototype($type);
    }
}
