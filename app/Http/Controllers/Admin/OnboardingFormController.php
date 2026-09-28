<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingFormConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnboardingFormController extends Controller
{
    public function index()
    {
        $configs = OnboardingFormConfig::query()
            ->withCount('submissions')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->paginate(25);

        return view('admin.onboarding.form.index', [
            'title'   => 'Onboarding Form Configurator',
            'configs' => $configs,
        ]);
    }

    public function create()
    {
        return view('admin.onboarding.form.edit', [
            'title'  => 'Create Form Configuration',
            'config' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateFormRequest($request);

        $formConfig = $this->parseAndValidateJson($request, 'form_config');
        $settings   = $this->parseAndValidateJson($request, 'settings', true);

        $record = OnboardingFormConfig::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'form_config' => $formConfig,
            'settings'    => $settings,
            'is_active'   => false,
        ]);

        return redirect()
            ->route('admin.onboarding.forms.edit', $record->id)
            ->with('success', 'Form configuration created. Activate it when ready.');
    }

    public function edit(OnboardingFormConfig $form)
    {
        return view('admin.onboarding.form.edit', [
            'title'  => 'Edit Form Configuration: ' . $form->name,
            'config' => $form,
        ]);
    }

    public function update(Request $request, OnboardingFormConfig $form)
    {
        $validated = $this->validateFormRequest($request);

        $formConfig = $this->parseAndValidateJson($request, 'form_config');
        $settings   = $this->parseAndValidateJson($request, 'settings', true);

        $form->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'form_config' => $formConfig,
            'settings'    => $settings,
        ]);

        return redirect()
            ->route('admin.onboarding.forms.edit', $form->id)
            ->with('success', 'Form configuration saved.');
    }

    public function activate(OnboardingFormConfig $form)
    {
        DB::transaction(function () use ($form) {
            $form->activate();
        });

        return redirect()
            ->route('admin.onboarding.forms.index')
            ->with('success', "\"{$form->name}\" is now the active onboarding form.");
    }

    public function destroy(OnboardingFormConfig $form)
    {
        if ($form->is_active) {
            return back()->withErrors(['error' => 'Cannot delete the active form configuration. Activate another first.']);
        }

        if ($form->submissions()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete a form that has submissions. Deactivate and archive instead.']);
        }

        $form->delete();

        return redirect()
            ->route('admin.onboarding.forms.index')
            ->with('success', 'Form configuration deleted.');
    }

    private function validateFormRequest(Request $request): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'form_config' => ['required', 'string'],
            'settings'    => ['nullable', 'string'],
        ]);
    }

    private function parseAndValidateJson(Request $request, string $key, bool $nullable = false): mixed
    {
        $raw = trim((string) $request->input($key, ''));

        if ($raw === '') {
            if ($nullable) {
                return null;
            }
            throw ValidationException::withMessages([$key => "The {$key} field is required."]);
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                $key => 'Invalid JSON: ' . json_last_error_msg(),
            ]);
        }

        $key === 'form_config'
            ? $this->assertFormConfigShape($decoded)
            : $this->assertSettingsShape($decoded);

        return $decoded;
    }

    private function assertSettingsShape(mixed $settings): void
    {
        if ($settings === null || is_array($settings)) {
            return;
        }

        throw ValidationException::withMessages([
            'settings' => 'Settings must be a JSON object.',
        ]);
    }

    private function assertFormConfigShape(mixed $config): void
    {
        $errors = [];
        $allowedTypes = [
            'text', 'email', 'tel', 'date', 'select', 'textarea', 'checkbox',
            'file', 'file_pair', 'repeatable', 'static_text', 'group_validation',
        ];

        if (! is_array($config)) {
            throw ValidationException::withMessages(['form_config' => 'Form configuration must be a JSON object.']);
        }

        if (trim((string) ($config['title'] ?? '')) === '') {
            $errors[] = 'Form title is required.';
        }

        $steps = $config['steps'] ?? null;
        if (! is_array($steps) || $steps === []) {
            $errors[] = 'At least one step is required.';
        }

        $seenSteps = [];
        foreach (is_array($steps) ? $steps : [] as $stepIndex => $step) {
            if (! is_array($step)) {
                $errors[] = "Step #".($stepIndex + 1).' must be an object.';
                continue;
            }

            $stepId = trim((string) ($step['id'] ?? ''));
            if ($stepId === '' || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $stepId)) {
                $errors[] = "Step #".($stepIndex + 1).' needs a stable ID using letters, numbers, dashes, or underscores.';
            } elseif (isset($seenSteps[$stepId])) {
                $errors[] = "Duplicate step ID: {$stepId}.";
            }
            $seenSteps[$stepId] = true;

            if (trim((string) ($step['title'] ?? '')) === '') {
                $errors[] = "Step {$stepId} needs a title.";
            }

            $fields = $step['fields'] ?? [];
            if (! is_array($fields)) {
                $errors[] = "Step {$stepId} fields must be an array.";
                continue;
            }

            $seenFields = [];
            foreach ($fields as $fieldIndex => $field) {
                if (! is_array($field)) {
                    $errors[] = "Field #".($fieldIndex + 1)." in step {$stepId} must be an object.";
                    continue;
                }

                $fieldKey = trim((string) ($field['key'] ?? ''));
                $type = (string) ($field['type'] ?? 'text');

                if ($fieldKey === '' || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $fieldKey)) {
                    $errors[] = "Field #".($fieldIndex + 1)." in step {$stepId} needs a stable key.";
                } elseif (isset($seenFields[$fieldKey])) {
                    $errors[] = "Duplicate field key {$fieldKey} in step {$stepId}.";
                }
                $seenFields[$fieldKey] = true;

                if (! in_array($type, $allowedTypes, true)) {
                    $errors[] = "Field {$fieldKey} has unsupported type {$type}.";
                }

                if ($type === 'select' && ! is_array($field['options'] ?? null)) {
                    $errors[] = "Select field {$fieldKey} must have options.";
                }

                if ($type === 'repeatable' && ! is_array($field['fields'] ?? null)) {
                    $errors[] = "Repeatable field {$fieldKey} must have child fields.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['form_config' => $errors]);
        }
    }
}
