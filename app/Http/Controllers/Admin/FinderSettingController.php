<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\ServiceFinderSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The Service Finder configurator.
 *
 * Renders itself entirely from ServiceFinderSettings::FIELDS — the same
 * definitions the application reads at runtime — so a new tunable appears in
 * the UI, gets validated and takes effect from a single declaration. There is
 * no per-field markup to keep in sync.
 */
class FinderSettingController extends Controller
{
    public function edit(Request $request)
    {
        $tab = $request->query('tab');
        $tab = array_key_exists($tab, ServiceFinderSettings::TABS)
            ? $tab
            : array_key_first(ServiceFinderSettings::TABS);

        return view('admin.finder.settings', [
            'title' => 'Service Finder Configurator',
            'tabs' => ServiceFinderSettings::TABS,
            'activeTab' => $tab,
            'fields' => $this->fieldsByTab(),
        ]);
    }

    public function update(Request $request)
    {
        $tab = (string) $request->input('_tab', '');
        $fields = $this->fieldsByTab()[$tab] ?? [];

        if ($fields === []) {
            return back()->with('error', 'Unknown settings tab.');
        }

        $validated = $request->validate($this->rulesFor($fields));
        $this->assertTemplateTokens($fields, $validated);

        $payload = [];

        foreach ($fields as $key => $field) {
            $value = $validated[$key] ?? null;

            // Unchecked toggles never reach the request at all.
            if (($field['type'] ?? '') === 'toggle') {
                $value = $request->boolean($key) ? '1' : '0';
            }

            // Pasted artwork is rendered on every visitor's map, so it is
            // reduced to a safe subset of SVG before it is ever stored —
            // sanitising on the way in means nothing downstream has to
            // remember to do it on the way out.
            if ($key === 'search_pin_svg') {
                $value = ServiceFinderSettings::sanitiseSvg($value);
            }

            $payload[ServiceFinderSettings::PREFIX.$key] = $value === null ? '' : (string) $value;
        }

        // bulkPut clears the settings cache and bumps the public cache, so the
        // website reflects the change on its next request.
        SiteSetting::bulkPut($payload, ServiceFinderSettings::GROUP);

        return redirect()
            ->route('admin.finder-settings.edit', ['tab' => $tab])
            ->with('success', ServiceFinderSettings::TABS[$tab].' settings saved.');
    }

    /** Restore one tab to its shipped defaults. */
    public function reset(Request $request)
    {
        $tab = (string) $request->input('_tab', '');
        $fields = $this->fieldsByTab()[$tab] ?? [];

        if ($fields === []) {
            return back()->with('error', 'Unknown settings tab.');
        }

        SiteSetting::query()
            ->whereIn('key', array_map(
                fn ($key) => ServiceFinderSettings::PREFIX.$key,
                array_keys($fields)
            ))
            ->delete();

        return redirect()
            ->route('admin.finder-settings.edit', ['tab' => $tab])
            ->with('success', ServiceFinderSettings::TABS[$tab].' settings restored to defaults.');
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected function fieldsByTab(): array
    {
        $grouped = array_fill_keys(array_keys(ServiceFinderSettings::TABS), []);

        foreach (ServiceFinderSettings::FIELDS as $key => $field) {
            $grouped[$field['tab']][$key] = $field;
        }

        return $grouped;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, string>
     */
    protected function rulesFor(array $fields): array
    {
        $rules = [];

        foreach ($fields as $key => $field) {
            $rules[$key] = match ($field['type'] ?? 'text') {
                'toggle' => 'nullable|boolean',
                'number' => 'nullable|numeric'
                    .(isset($field['min']) ? '|min:'.$field['min'] : '')
                    .(isset($field['max']) ? '|max:'.$field['max'] : ''),
                'select' => 'nullable|string|in:'.implode(',', array_keys($field['options'] ?? [])),
                'lines', 'keyvalue' => 'nullable|string|max:20000',
                'textarea' => 'nullable|string|max:'.($field['max'] ?? 2000),
                default => 'nullable|string|max:'.($field['max'] ?? 255),
            };
        }

        return $rules;
    }

    /**
     * Templates are only useful if their placeholders survive editing. A
     * heading that has lost its {area} token would silently render the same
     * text for every location, so it is rejected at save time rather than
     * discovered on the live site.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $validated
     */
    protected function assertTemplateTokens(array $fields, array $validated): void
    {
        $errors = [];

        foreach ($fields as $key => $field) {
            $tokens = $field['tokens'] ?? [];
            $value = (string) ($validated[$key] ?? '');

            if ($tokens === [] || $value === '') {
                continue;
            }

            // Catch typos such as {aera} or {Area} that would never resolve.
            preg_match_all('/\{[a-zA-Z_]+\}/', $value, $matches);
            $unknown = array_diff(array_unique($matches[0] ?? []), $tokens);

            if ($unknown !== []) {
                $errors[$key] = 'Unknown placeholder(s) '.implode(', ', $unknown)
                    .'. Available here: '.implode(', ', $tokens).'.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
