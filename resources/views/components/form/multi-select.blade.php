@props([
    'label',
    'name',
    'options' => [],
    'selected' => [],
    'selectedLabels' => [],
    'required' => false,
    'placeholder' => 'Select options',
    'ajaxUrl' => null,
    'ajaxParams' => [],
    'ajaxCreateUrl' => null,
])

@php
    $oldValues = old($name);
    $selectedValues = array_values(array_filter(is_array($oldValues) ? $oldValues : (is_array($selected) ? $selected : []), fn ($value) => $value !== null && $value !== ''));
    $classes = $ajaxUrl ? 'form-control select2-ajax' : 'form-control select2';
    $selectAttributes = $attributes
        ->merge([
            'class' => $classes . ($errors->has($name) ? ' is-invalid' : ''),
            'data-placeholder' => $placeholder,
        ])
        ->merge($ajaxUrl ? [
            'data-ajax-url' => $ajaxUrl,
            'data-ajax-params' => json_encode($ajaxParams),
        ] : []);
    $modalId = 'faqCreateModal_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
@endphp

<div class="form-group admin-select-group">
    <label for="{{ $name }}" class="admin-select-label d-flex align-items-center gap-2">
        <span>{{ $label }} @if($required)<span class="text-danger">*</span>@endif</span>
        @if($ajaxCreateUrl)
            <button type="button"
                class="btn btn-xs btn-outline-success ms-auto"
                style="padding:2px 10px;font-size:0.75rem;white-space:nowrap;"
                data-bs-toggle="modal"
                data-bs-target="#{{ $modalId }}">
                <i class="fas fa-plus"></i> Create &amp; Attach New FAQ
            </button>
        @endif
    </label>
    <select name="{{ $name }}[]" id="{{ $name }}" multiple
        {{ $required ? 'required' : '' }}
        {{ $selectAttributes }}>
        @if($ajaxUrl)
            @foreach ($selectedValues as $value)
                <option value="{{ $value }}" selected>{{ $selectedLabels[$value] ?? $value }}</option>
            @endforeach
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" {{ in_array($value, $selectedValues) ? 'selected' : '' }}>
                {{ $text }}
            </option>
        @endforeach
    </select>
    @error($name)
        <span class="invalid-feedback d-block">{{ $message }}</span>
    @enderror
</div>

@if($ajaxCreateUrl)
{{-- ── Inline FAQ creator modal — scoped to this field via unique modalId ── --}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}_label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $modalId }}_label">
                    <i class="fas fa-plus-circle text-success me-1"></i>
                    Create &amp; Attach New FAQ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle"></i>
                    The new FAQ is saved immediately and attached to this field. No other data on the form is affected.
                </div>
                <div id="{{ $modalId }}_error" class="alert alert-danger py-2 small d-none"></div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Question <span class="text-danger">*</span></label>
                    <textarea id="{{ $modalId }}_question" class="form-control" rows="3"
                        placeholder="Enter the FAQ question..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Answer <span class="text-danger">*</span></label>
                    <textarea id="{{ $modalId }}_answer" class="form-control" rows="6"
                        placeholder="Enter the full answer..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="{{ $modalId }}_save">
                    <i class="fas fa-save me-1"></i> Save &amp; Attach
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var modalId     = '{{ $modalId }}';
    var selectName  = '{{ $name }}';
    var createUrl   = '{{ $ajaxCreateUrl }}';
    var csrfToken   = document.querySelector('meta[name="csrf-token"]').content;

    document.getElementById(modalId + '_save').addEventListener('click', function () {
        var question = document.getElementById(modalId + '_question').value.trim();
        var answer   = document.getElementById(modalId + '_answer').value.trim();
        var errorEl  = document.getElementById(modalId + '_error');
        var btn      = this;

        errorEl.classList.add('d-none');
        errorEl.textContent = '';

        if (!question) {
            errorEl.textContent = 'Question is required.';
            errorEl.classList.remove('d-none');
            return;
        }
        if (!answer) {
            errorEl.textContent = 'Answer is required.';
            errorEl.classList.remove('d-none');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

        fetch(createUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ question: question, answer: answer }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.errors) {
                var msgs = Object.values(data.errors).flat().join(' ');
                errorEl.textContent = msgs;
                errorEl.classList.remove('d-none');
                return;
            }

            // Add the new FAQ to the Select2 and select it
            var $select = window.$ ? $('#' + selectName) : null;
            if ($select && $select.data('select2')) {
                var option = new Option(data.text, data.id, true, true);
                $select.append(option).trigger('change');
            } else {
                // Fallback: plain select
                var sel = document.getElementById(selectName);
                if (sel) {
                    var opt = document.createElement('option');
                    opt.value = data.id;
                    opt.text  = data.text;
                    opt.selected = true;
                    sel.appendChild(opt);
                }
            }

            // Reset and close
            document.getElementById(modalId + '_question').value = '';
            document.getElementById(modalId + '_answer').value   = '';
            var bsModal = bootstrap.Modal.getInstance(document.getElementById(modalId));
            if (bsModal) bsModal.hide();
        })
        .catch(function () {
            errorEl.textContent = 'An error occurred. Please try again.';
            errorEl.classList.remove('d-none');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Attach';
        });
    });

    // Clear modal state on close
    document.getElementById(modalId).addEventListener('hidden.bs.modal', function () {
        document.getElementById(modalId + '_question').value = '';
        document.getElementById(modalId + '_answer').value   = '';
        document.getElementById(modalId + '_error').classList.add('d-none');
    });
})();
</script>
@endif
