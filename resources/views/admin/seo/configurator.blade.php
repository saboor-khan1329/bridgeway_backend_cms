@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="['SEO Configurator' => null]">
    <div class="col-12">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the SEO configuration:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <div class="card h-100 border-primary">
                    <div class="card-body">
                        <div class="text-primary small fw-bold text-uppercase">Precedence</div>
                        <h5 class="fw-bold mb-2">Page SEO wins first</h5>
                        <p class="text-muted mb-0">Item/page SEO overrides template defaults. Template values override sitewide fallbacks. Empty fields safely fall through.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-success small fw-bold text-uppercase">Placeholders</div>
                        <h5 class="fw-bold mb-2">Reusable templates</h5>
                        <p class="text-muted mb-0">Use <code>{title}</code> <code>{description}</code> <code>{url}</code> <code>{image}</code> <code>{author}</code> <code>{published_at}</code> <code>{modified_at}</code> <code>{category}</code> in meta, schema, or OG JSON — resolved per page at render time.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-warning small fw-bold text-uppercase">Visual Studio</div>
                        <h5 class="fw-bold mb-2">Loop builder + live preview</h5>
                        <p class="text-muted mb-0">Each JSON field has a <b>Visual builder</b> (objects &amp; lists with <b>+ Add item</b> / drag-reorder for <code>@graph</code>, <code>itemListElement</code>, FAQ, <code>hasCredential</code>…), a <b>live JSON-LD / OG preview</b>, and JSON validation before save.</p>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.seo.configurator.update') }}">
            @csrf
            @method('PUT')

            <ul class="nav nav-tabs" role="tablist">
                @foreach ($groups as $group => $fields)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" data-bs-toggle="tab"
                            data-bs-target="#seo-{{ $group }}" role="tab">
                            {{ $group === 'global' ? 'Sitewide' : ($templates[$group] ?? Str::headline($group)) }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content border border-top-0 bg-white p-3 mb-3">
                @foreach ($groups as $group => $fields)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="seo-{{ $group }}" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1">{{ $group === 'global' ? 'Sitewide Fallbacks' : ($templates[$group] ?? Str::headline($group)) }}</h5>
                                <p class="text-muted small mb-0">
                                    {{ $group === 'global'
                                        ? 'Used only when item and template SEO are empty.'
                                        : 'Used only when the specific page/item has no SEO value for that field.' }}
                                </p>
                            </div>
                            <span class="badge bg-light text-dark border">{{ count($fields) }} controls</span>
                        </div>

                        <div class="row g-3">
                            @foreach ($fields as $field)
                                @php
                                    $key = $field['key'];
                                    $value = old($key, $values[$key] ?? '');
                                    $col = $field['col'] ?? 6;
                                @endphp
                                <div class="col-lg-{{ $col }}">
                                    <label class="form-label small fw-semibold" for="{{ $key }}">{{ $field['label'] }}</label>
                                    @if ($field['type'] === 'select')
                                        <select class="form-select" name="{{ $key }}" id="{{ $key }}">
                                            @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}" {{ (string) $value === (string) $optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field['type'] === 'json')
                                        <textarea class="form-control font-monospace js-json-field" name="{{ $key }}" id="{{ $key }}" rows="8"
                                            data-label="{{ $field['label'] }}">{{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $value }}</textarea>
                                        <div class="d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm js-format-json" data-target="{{ $key }}">
                                                <i class="fas fa-code"></i> Format
                                            </button>
                                            <span class="small align-self-center js-json-status" data-status-for="{{ $key }}"></span>
                                        </div>
                                    @elseif ($field['type'] === 'textarea')
                                        <textarea class="form-control" name="{{ $key }}" id="{{ $key }}" rows="4">{{ is_array($value) ? '' : $value }}</textarea>
                                    @else
                                        <input class="form-control" type="text" name="{{ $key }}" id="{{ $key }}" value="{{ is_array($value) ? '' : $value }}">
                                    @endif
                                    @error($key)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Schema & Open Graph Presets</span>
                    <small class="text-muted">Copy into the correct field, then adjust placeholders if needed</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($presets as $preset)
                            <div class="col-lg-4">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <div class="fw-semibold">{{ $preset['label'] }}</div>
                                    <div class="small text-muted mb-2">Target: <code>{{ $preset['target'] }}</code></div>
                                    <button type="button"
                                        class="btn btn-outline-primary btn-sm js-apply-preset"
                                        data-target="{{ $preset['target'] }}"
                                        data-value="{{ e(json_encode($preset['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}">
                                        <i class="fas fa-copy"></i> Apply Preset
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">Saving bumps the frontend cache key automatically.</div>
                <button class="btn btn-success">
                    <i class="fas fa-save"></i> Save SEO Configurator
                </button>
            </div>
        </form>
    </div>
</x-layouts.page-wrapper>

@include('admin.shared.seo-toolkit')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function setStatus(id, ok, message) {
        const status = document.querySelector(`[data-status-for="${id}"]`);
        if (!status) return;
        status.textContent = message;
        status.className = `small align-self-center ${ok ? 'text-success' : 'text-danger'}`;
    }

    document.querySelectorAll('.js-format-json').forEach((button) => {
        button.addEventListener('click', function () {
            const id = button.dataset.target;
            const input = document.getElementById(id);
            try {
                if (!input.value.trim()) {
                    setStatus(id, true, 'Empty: will fall through');
                    return;
                }
                input.value = JSON.stringify(JSON.parse(input.value), null, 2);
                setStatus(id, true, 'Valid JSON');
            } catch (error) {
                setStatus(id, false, error.message);
            }
        });
    });

    document.querySelectorAll('.js-apply-preset').forEach((button) => {
        button.addEventListener('click', function () {
            const target = document.getElementById(button.dataset.target);
            if (!target) {
                return;
            }

            target.value = button.dataset.value || '';
            const tabPane = target.closest('.tab-pane');
            const tabButton = tabPane ? document.querySelector(`[data-bs-target="#${tabPane.id}"]`) : null;
            if (tabButton && window.bootstrap?.Tab) {
                new bootstrap.Tab(tabButton).show();
            }
            setStatus(target.id, true, 'Preset applied. Review and save.');
            target.focus();
        });
    });
});
</script>
@endpush
