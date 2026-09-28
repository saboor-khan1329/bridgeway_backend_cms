<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\SeoConfiguratorFields;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeoConfiguratorController extends Controller
{
    public function edit()
    {
        return view('admin.seo.configurator', [
            'title' => 'SEO Configurator',
            'groups' => SeoConfiguratorFields::grouped(),
            'templates' => SeoConfiguratorFields::TEMPLATES,
            'presets' => SeoConfiguratorFields::presets(),
            'values' => SiteSetting::allCached(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];

        foreach (SeoConfiguratorFields::fields() as $field) {
            $rules[$field['key']] = match ($field['type']) {
                'select' => ['nullable', 'string', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                'json' => 'nullable|string|max:'.(int) ($field['max'] ?? 30000),
                'textarea' => 'nullable|string|max:'.(int) ($field['max'] ?? 5000),
                default => 'nullable|string|max:'.(int) ($field['max'] ?? 1000),
            };
        }

        $validated = $request->validate($rules);
        $this->assertJsonFields($validated);

        SiteSetting::bulkPut($validated, 'seo');

        return back()->with('success', 'SEO configurator saved and frontend cache bumped.');
    }

    private function assertJsonFields(array $values): void
    {
        $errors = [];

        foreach (SeoConfiguratorFields::fields() as $field) {
            if (($field['type'] ?? '') !== 'json') {
                continue;
            }

            $value = trim((string) ($values[$field['key']] ?? ''));
            if ($value === '') {
                continue;
            }

            json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[$field['key']] = 'Invalid JSON: '.json_last_error_msg();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
