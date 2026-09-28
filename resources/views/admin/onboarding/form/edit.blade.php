@extends('template.main')
@section('title', $title)

@section('content')
@php
    $formJson = old('form_config', $config ? json_encode($config->form_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{"title":"","description":"","steps":[]}');
    $settingsJson = old('settings', $config ? json_encode($config->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}');
    $settingsData = json_decode($settingsJson ?: '{}', true) ?: [];
@endphp

<x-layouts.page-wrapper :title="$title"
    :breadcrumbs="['Onboarding Forms' => route('admin.onboarding.forms.index'), $title => null]">
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('admin.onboarding.forms.index') }}" class="btn btn-warning btn-sm">
                <i class="fa fa-arrow-left"></i> Back
            </a>
            @if ($config)
                <a href="{{ route('admin.onboarding.submissions.index') }}?form_id={{ $config->id }}" class="btn btn-info btn-sm">
                    <i class="fa fa-list"></i> View Submissions ({{ $config->submissions()->count() }})
                </a>
                @if ($config->is_active)
                    <span class="badge bg-success align-self-center">Currently Active</span>
                @else
                    <form method="POST" action="{{ route('admin.onboarding.forms.activate', $config->id) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-primary btn-sm" data-confirm-submit="activate this form config">
                            <i class="fa fa-check-circle"></i> Activate This Form
                        </button>
                    </form>
                @endif
            @endif
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the errors below:</strong>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" id="onboarding-builder-form"
            action="{{ $config ? route('admin.onboarding.forms.update', $config->id) : route('admin.onboarding.forms.store') }}">
            @csrf
            @if ($config) @method('PUT') @endif

            <div class="card mb-3">
                <div class="card-header fw-semibold">Form Details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Form Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                value="{{ old('name', $config?->name ?? '') }}" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" name="description" class="form-control"
                                value="{{ old('description', $config?->description ?? '') }}" maxlength="1000">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Delivery & Display Settings</span>
                    <small class="text-muted">Saved as validated JSON automatically</small>
                </div>
                <div class="card-body">
                    <input type="hidden" name="settings" id="settings-json" value="{{ e($settingsJson) }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Admin Notification Email</label>
                            <input type="email" class="form-control js-setting" data-key="admin_email"
                                value="{{ $settingsData['admin_email'] ?? '' }}" placeholder="admin@bridgewaydigital.test">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Draft Save Key</label>
                            <input type="text" class="form-control js-setting" data-key="draft_save_key"
                                value="{{ $settingsData['draft_save_key'] ?? 'bridgewaydigital-onboarding-draft' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Applicant Email Subject</label>
                            <input type="text" class="form-control js-setting" data-key="applicant_confirmation_subject"
                                value="{{ $settingsData['applicant_confirmation_subject'] ?? 'Your Application Has Been Received' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Applicant Confirmation Email</label>
                            <select class="form-select js-setting-bool" data-key="send_applicant_confirmation">
                                <option value="1" {{ ($settingsData['send_applicant_confirmation'] ?? true) ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ !($settingsData['send_applicant_confirmation'] ?? true) ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Applicant Email Message</label>
                            <textarea class="form-control js-setting" data-key="applicant_confirmation_message" rows="3">{{ $settingsData['applicant_confirmation_message'] ?? '' }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Progress Bar</label>
                            <select class="form-select js-setting-bool" data-key="show_progress">
                                <option value="1" {{ ($settingsData['show_progress'] ?? true) ? 'selected' : '' }}>Show</option>
                                <option value="0" {{ !($settingsData['show_progress'] ?? true) ? 'selected' : '' }}>Hide</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Step Sidebar</label>
                            <select class="form-select js-setting-bool" data-key="show_step_list">
                                <option value="1" {{ ($settingsData['show_step_list'] ?? true) ? 'selected' : '' }}>Show</option>
                                <option value="0" {{ !($settingsData['show_step_list'] ?? true) ? 'selected' : '' }}>Hide</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Local Draft Saving</label>
                            <select class="form-select js-setting-bool" data-key="draft_save_enabled">
                                <option value="1" {{ ($settingsData['draft_save_enabled'] ?? true) ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ !($settingsData['draft_save_enabled'] ?? true) ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">Visual Form Builder</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-step-btn">
                            <i class="fa fa-plus"></i> Add Step
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#advanced-json">
                            <i class="fa fa-code"></i> Advanced JSON
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Public Form Title</label>
                            <input type="text" class="form-control" id="builder-title">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Public Form Description</label>
                            <input type="text" class="form-control" id="builder-description">
                        </div>
                    </div>
                    <div id="builder-steps" class="d-flex flex-column gap-3"></div>

                    <div class="collapse mt-3" id="advanced-json">
                        <div class="alert alert-warning small">
                            Advanced JSON is synchronized with the visual builder. Use it for repeatable child fields, group validation rules, or bulk edits.
                        </div>
                        <textarea name="form_config" id="form-config-editor" class="form-control font-monospace" rows="24"
                            style="font-size:12px;">{{ $formJson }}</textarea>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="json-format-btn">
                                <i class="fa fa-code"></i> Format JSON
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="json-validate-btn">
                                <i class="fa fa-check"></i> Validate JSON
                            </button>
                            <span id="json-status" class="align-self-center small"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-save"></i> {{ $config ? 'Save Changes' : 'Create Form Config' }}
                </button>
                <a href="{{ route('admin.onboarding.forms.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.page-wrapper>
@endsection

@push('scripts')
<script>
(function () {
    const editor = document.getElementById('form-config-editor');
    const settingsJson = document.getElementById('settings-json');
    const stepsWrap = document.getElementById('builder-steps');
    const titleInput = document.getElementById('builder-title');
    const descriptionInput = document.getElementById('builder-description');
    const status = document.getElementById('json-status');
    const types = ['text','email','tel','date','select','textarea','checkbox','file','file_pair','repeatable','static_text','group_validation'];
    let config = safeParse(editor.value) || { title: '', description: '', steps: [] };

    function safeParse(value) {
        try { return JSON.parse(value || '{}'); } catch (e) { return null; }
    }

    function slug(value, fallback) {
        return (value || fallback || 'field')
            .toString()
            .trim()
            .replace(/[^a-zA-Z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .toLowerCase() || fallback;
    }

    function syncSettings() {
        const data = safeParse(settingsJson.value) || {};
        document.querySelectorAll('.js-setting').forEach((input) => data[input.dataset.key] = input.value);
        document.querySelectorAll('.js-setting-bool').forEach((input) => data[input.dataset.key] = input.value === '1');
        settingsJson.value = JSON.stringify(data, null, 2);
    }

    function syncEditor() {
        config.title = titleInput.value;
        config.description = descriptionInput.value;
        config.steps = (config.steps || []).map((step, index) => ({ ...step, order: index + 1 }));
        editor.value = JSON.stringify(config, null, 2);
        syncSettings();
    }

    function render() {
        titleInput.value = config.title || '';
        descriptionInput.value = config.description || '';
        stepsWrap.innerHTML = '';
        (config.steps || []).forEach((step, stepIndex) => {
            const card = document.createElement('div');
            card.className = 'card border';
            card.innerHTML = `
                <div class="card-header bg-light">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3"><input class="form-control form-control-sm" data-step-prop="title" value="${escapeHtml(step.title || '')}" placeholder="Step title"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" data-step-prop="id" value="${escapeHtml(step.id || '')}" placeholder="step_id"></div>
                        <div class="col-md-2"><input class="form-control form-control-sm" data-step-prop="icon" value="${escapeHtml(step.icon || '')}" placeholder="icon"></div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" data-step-prop="is_enabled">
                                <option value="1" ${step.is_enabled !== false ? 'selected' : ''}>Enabled</option>
                                <option value="0" ${step.is_enabled === false ? 'selected' : ''}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-3 text-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-step-up>Up</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-step-down>Down</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-add-field>Add Field</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-step>Delete</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Required</th><th>Enabled</th><th>Options / Hint</th><th></th></tr></thead>
                            <tbody data-fields></tbody>
                        </table>
                    </div>
                </div>`;
            stepsWrap.appendChild(card);
            bindStep(card, step, stepIndex);
        });
        syncEditor();
    }

    function bindStep(card, step, stepIndex) {
        const fieldsBody = card.querySelector('[data-fields]');
        (step.fields || []).forEach((field, fieldIndex) => fieldsBody.appendChild(fieldRow(field, stepIndex, fieldIndex)));

        card.querySelectorAll('[data-step-prop]').forEach((input) => input.addEventListener('input', () => {
            const prop = input.dataset.stepProp;
            step[prop] = prop === 'is_enabled' ? input.value === '1' : input.value;
            if (prop === 'title' && !step.id) step.id = slug(input.value, 'step');
            syncEditor();
        }));
        card.querySelector('[data-add-field]').addEventListener('click', () => {
            step.fields = step.fields || [];
            step.fields.push({ key: 'new_field', label: 'New Field', type: 'text', required: false, col_span: 1, order: step.fields.length + 1, is_enabled: true });
            render();
        });
        card.querySelector('[data-remove-step]').addEventListener('click', () => {
            if (confirm('Delete this step from the draft configuration?')) {
                config.steps.splice(stepIndex, 1);
                render();
            }
        });
        card.querySelector('[data-step-up]').addEventListener('click', () => move(config.steps, stepIndex, -1));
        card.querySelector('[data-step-down]').addEventListener('click', () => move(config.steps, stepIndex, 1));
    }

    function fieldRow(field, stepIndex, fieldIndex) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input class="form-control form-control-sm" data-field-prop="label" value="${escapeHtml(field.label || '')}"></td>
            <td><input class="form-control form-control-sm" data-field-prop="key" value="${escapeHtml(field.key || '')}"></td>
            <td><select class="form-select form-select-sm" data-field-prop="type">${types.map(t => `<option value="${t}" ${field.type === t ? 'selected' : ''}>${t}</option>`).join('')}</select></td>
            <td><select class="form-select form-select-sm" data-field-prop="required"><option value="0" ${!field.required ? 'selected' : ''}>No</option><option value="1" ${field.required ? 'selected' : ''}>Yes</option></select></td>
            <td><select class="form-select form-select-sm" data-field-prop="is_enabled"><option value="1" ${field.is_enabled !== false ? 'selected' : ''}>Yes</option><option value="0" ${field.is_enabled === false ? 'selected' : ''}>No</option></select></td>
            <td><input class="form-control form-control-sm" data-field-prop="hint" value="${escapeHtml(field.hint || '')}" placeholder="Hint or select options: a=Label,b=Label"></td>
            <td class="text-nowrap"><button type="button" class="btn btn-outline-secondary btn-sm" data-field-up>Up</button> <button type="button" class="btn btn-outline-secondary btn-sm" data-field-down>Down</button> <button type="button" class="btn btn-outline-danger btn-sm" data-remove-field>Delete</button></td>`;
        tr.querySelectorAll('[data-field-prop]').forEach((input) => input.addEventListener('input', () => {
            const prop = input.dataset.fieldProp;
            if (prop === 'required' || prop === 'is_enabled') field[prop] = input.value === '1';
            else if (prop === 'type') field[prop] = input.value;
            else field[prop] = input.value;
            if (prop === 'label' && !field.key) field.key = slug(input.value, 'field');
            if (field.type === 'select' && field.hint) {
                field.options = Object.fromEntries(field.hint.split(',').map(v => v.trim()).filter(Boolean).map(v => {
                    const parts = v.split('=');
                    return [parts[0].trim(), (parts[1] || parts[0]).trim()];
                }));
            }
            syncEditor();
        }));
        tr.querySelector('[data-remove-field]').addEventListener('click', () => {
            config.steps[stepIndex].fields.splice(fieldIndex, 1);
            render();
        });
        tr.querySelector('[data-field-up]').addEventListener('click', () => move(config.steps[stepIndex].fields, fieldIndex, -1));
        tr.querySelector('[data-field-down]').addEventListener('click', () => move(config.steps[stepIndex].fields, fieldIndex, 1));
        return tr;
    }

    function move(list, index, direction) {
        const next = index + direction;
        if (next < 0 || next >= list.length) return;
        [list[index], list[next]] = [list[next], list[index]];
        render();
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    }

    document.getElementById('add-step-btn').addEventListener('click', () => {
        config.steps = config.steps || [];
        config.steps.push({ id: 'new_step', title: 'New Step', icon: 'user', order: config.steps.length + 1, is_enabled: true, fields: [] });
        render();
    });
    titleInput.addEventListener('input', syncEditor);
    descriptionInput.addEventListener('input', syncEditor);
    document.querySelectorAll('.js-setting,.js-setting-bool').forEach((input) => input.addEventListener('input', syncSettings));
    document.getElementById('json-format-btn').addEventListener('click', () => {
        const parsed = safeParse(editor.value);
        if (!parsed) {
            status.textContent = 'Invalid JSON';
            status.className = 'align-self-center small text-danger';
            return;
        }
        config = parsed;
        status.textContent = 'Valid JSON and builder refreshed';
        status.className = 'align-self-center small text-success';
        render();
    });
    document.getElementById('json-validate-btn').addEventListener('click', () => document.getElementById('json-format-btn').click());
    document.getElementById('onboarding-builder-form').addEventListener('submit', syncEditor);

    render();
})();
</script>
@endpush
