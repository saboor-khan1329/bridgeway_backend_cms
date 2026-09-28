@extends('template.main')
@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="['Settings' => null]">
        <div class="col-12">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @foreach ($groups as $group => $fields)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0 text-capitalize">{{ str_replace('_', ' ', $group) }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach ($fields as $field)
                                    @php
                                        $key = $field['key'];
                                        $type = $field['type'];
                                        $col = $field['col'] ?? 6;
                                        $value = $values[$key] ?? null;
                                        $inputType = match ($type) {
                                            'email' => 'email',
                                            'url' => 'url',
                                            'number' => 'number',
                                            default => 'text',
                                        };
                                        $listValues = old($key, is_array($value) ? $value : []);
                                        if ($type === 'repeatable-email' || $type === 'repeatable-text') {
                                            $listValues = is_array($listValues) ? $listValues : [];
                                            if ($listValues === []) {
                                                $listValues = [''];
                                            }
                                        }
                                    @endphp

                                    <div class="col-md-{{ $col }} mb-3">
                                        @if ($type === 'image')
                                            <x-form.image-input
                                                :label="$field['label']"
                                                :name="$key"
                                                :image="['path' => $value, 'disk' => 'public']"
                                                :directory="$field['directory']"
                                                :show-metadata="$field['show_metadata'] ?? false" />
                                        @elseif ($type === 'textarea')
                                            <label for="{{ $key }}" class="form-label small fw-semibold">{{ $field['label'] }}</label>
                                            <textarea name="{{ $key }}" id="{{ $key }}" rows="4"
                                                class="form-control">{{ old($key, is_array($value) ? '' : $value) }}</textarea>
                                            @if (!empty($field['hint']))
                                                <div class="form-text">{{ $field['hint'] }}</div>
                                            @endif
                                            @error($key)
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        @elseif ($type === 'repeatable-email' || $type === 'repeatable-text')
                                            <label class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                                                <span>{{ $field['label'] }}</span>
                                                <span class="text-muted">Max {{ $field['max_items'] ?? 4 }}</span>
                                            </label>

                                            <div class="border rounded p-3 bg-light js-repeatable-field"
                                                data-name="{{ $key }}"
                                                data-input-type="{{ $type === 'repeatable-email' ? 'email' : 'text' }}"
                                                data-placeholder="{{ $field['label'] }}"
                                                data-max-items="{{ $field['max_items'] ?? 4 }}">
                                                <div class="d-flex flex-column gap-2" data-repeatable-list>
                                                    @foreach ($listValues as $item)
                                                        <div class="input-group input-group-sm">
                                                            <input type="{{ $type === 'repeatable-email' ? 'email' : 'text' }}"
                                                                name="{{ $key }}[]"
                                                                value="{{ $item }}"
                                                                class="form-control"
                                                                placeholder="{{ $field['label'] }}">
                                                            <button type="button" class="btn btn-outline-danger" data-repeatable-remove>
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-repeatable-add>
                                                    <i class="fas fa-plus"></i> Add Another
                                                </button>
                                            </div>
                                            @error($key)
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                            @error($key . '.*')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        @elseif ($type === 'select')
                                            <label for="{{ $key }}" class="form-label small fw-semibold">{{ $field['label'] }}</label>
                                            <select name="{{ $key }}" id="{{ $key }}" class="form-control">
                                                @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" {{ (string) old($key, is_array($value) ? '' : $value) === (string) $optionValue ? 'selected' : '' }}>
                                                        {{ $optionLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error($key)
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        @else
                                            <label for="{{ $key }}" class="form-label small fw-semibold">{{ $field['label'] }}</label>
                                            <input type="{{ $inputType }}" name="{{ $key }}" id="{{ $key }}"
                                                value="{{ old($key, is_array($value) ? '' : $value) }}"
                                                class="form-control"
                                                @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
                                                @if(isset($field['max']) && $type === 'number') max="{{ $field['max'] }}" @endif>
                                            @error($key)
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="text-end">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </x-layouts.page-wrapper>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-repeatable-field').forEach(function (container) {
                if (container.dataset.repeatableBound === '1') {
                    return;
                }

                container.dataset.repeatableBound = '1';

                const name = container.dataset.name;
                const type = container.dataset.inputType || 'text';
                const placeholder = container.dataset.placeholder || '';
                const maxItems = Number(container.dataset.maxItems || 4);
                const list = container.querySelector('[data-repeatable-list]');
                const addButton = container.querySelector('[data-repeatable-add]');

                const updateState = function () {
                    const rows = list.querySelectorAll('.input-group');
                    addButton.disabled = rows.length >= maxItems;
                };

                const bindRemove = function (button) {
                    button.addEventListener('click', function () {
                        const rows = list.querySelectorAll('.input-group');

                        if (rows.length <= 1) {
                            const input = rows[0]?.querySelector('input');
                            if (input) {
                                input.value = '';
                            }
                        } else {
                            button.closest('.input-group')?.remove();
                        }

                        updateState();
                    });
                };

                list.querySelectorAll('[data-repeatable-remove]').forEach(bindRemove);

                addButton?.addEventListener('click', function () {
                    const rows = list.querySelectorAll('.input-group');

                    if (rows.length >= maxItems) {
                        return;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.className = 'input-group input-group-sm';
                    wrapper.innerHTML = `
                        <input type="${type}" name="${name}[]" value="" class="form-control" placeholder="${placeholder}">
                        <button type="button" class="btn btn-outline-danger" data-repeatable-remove>
                            <i class="fas fa-trash"></i>
                        </button>
                    `;

                    list.appendChild(wrapper);
                    bindRemove(wrapper.querySelector('[data-repeatable-remove]'));
                    updateState();
                });

                updateState();
            });

            // ── Advanced Inquiries UI ─────────────────────────────────────
            // Both widgets keep the original textarea as the form source of
            // truth (same keys, same storage format — zero data loss). The
            // textarea stays usable behind a "raw" toggle.

            // 1) Blocked keywords → tag chips (serialises to comma list)
            (function () {
                const ta = document.getElementById('inquiry_blocked_keywords');
                if (!ta) return;

                const wrap = document.createElement('div');
                wrap.className = 'border rounded p-2 bg-light';
                ta.parentNode.insertBefore(wrap, ta);
                ta.classList.add('d-none');
                ta.setAttribute('rows', '2');

                const chipBox = document.createElement('div');
                chipBox.className = 'd-flex flex-wrap gap-1 mb-2';
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.placeholder = 'Type a keyword and press Enter (e.g. casino)';
                const hint = document.createElement('div');
                hint.className = 'small text-muted mt-1';
                hint.innerHTML = 'Inquiries containing any of these keywords are scored as spam. <a href="#" data-raw-toggle>Edit raw</a>';
                wrap.appendChild(chipBox); wrap.appendChild(input); wrap.appendChild(hint);

                function keywords() {
                    return ta.value.split(',').map(s => s.trim()).filter(Boolean);
                }
                function render() {
                    chipBox.innerHTML = '';
                    keywords().forEach(function (kw, i) {
                        const chip = document.createElement('span');
                        chip.className = 'badge bg-white text-dark border d-inline-flex align-items-center gap-1';
                        chip.innerHTML = '<span></span><button type="button" class="btn-close" style="font-size:.55rem"></button>';
                        chip.querySelector('span').textContent = kw;
                        chip.querySelector('button').addEventListener('click', function () {
                            const list = keywords(); list.splice(i, 1);
                            ta.value = list.join(','); render();
                        });
                        chipBox.appendChild(chip);
                    });
                }
                input.addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter' && e.key !== ',') return;
                    e.preventDefault();
                    const kw = input.value.trim().replace(/,+$/, '');
                    if (!kw) return;
                    const list = keywords();
                    if (!list.includes(kw)) { list.push(kw); ta.value = list.join(','); render(); }
                    input.value = '';
                });
                hint.querySelector('[data-raw-toggle]').addEventListener('click', function (e) {
                    e.preventDefault(); ta.classList.toggle('d-none'); render();
                });
                ta.addEventListener('input', render);
                render();
            })();

            // 2) Field-specific rules → structured rows (serialises to "field=kw1,kw2" lines)
            (function () {
                const ta = document.getElementById('inquiry_field_block_rules');
                if (!ta) return;

                const FIELDS = ['name', 'email', 'phone', 'company', 'subject', 'message'];
                const wrap = document.createElement('div');
                wrap.className = 'border rounded p-2 bg-light';
                ta.parentNode.insertBefore(wrap, ta);
                ta.classList.add('d-none');

                const rows = document.createElement('div');
                const actions = document.createElement('div');
                actions.className = 'd-flex align-items-center gap-2 mt-2';
                actions.innerHTML = '<button type="button" class="btn btn-outline-primary btn-sm" data-add-rule><i class="fas fa-plus"></i> Add rule</button>' +
                    '<span class="small text-muted">Blocks inquiries when the chosen field contains any keyword. <a href="#" data-raw-toggle>Edit raw</a></span>';
                wrap.appendChild(rows); wrap.appendChild(actions);

                function parse() {
                    return ta.value.split('\n').map(function (line) {
                        const idx = line.indexOf('=');
                        if (idx < 1) return null;
                        return { field: line.slice(0, idx).trim(), keywords: line.slice(idx + 1).trim() };
                    }).filter(Boolean);
                }
                function serialise() {
                    ta.value = Array.from(rows.querySelectorAll('[data-rule-row]')).map(function (row) {
                        const f = row.querySelector('select').value;
                        const k = row.querySelector('input').value.trim();
                        return k ? (f + '=' + k) : null;
                    }).filter(Boolean).join('\n');
                }
                function addRow(rule) {
                    const row = document.createElement('div');
                    row.className = 'd-flex gap-2 mb-2';
                    row.setAttribute('data-rule-row', '');
                    const sel = document.createElement('select');
                    sel.className = 'form-select form-select-sm';
                    sel.style.maxWidth = '140px';
                    FIELDS.forEach(function (f) { sel.appendChild(new Option(f, f, false, rule && rule.field === f)); });
                    if (rule && !FIELDS.includes(rule.field)) sel.appendChild(new Option(rule.field, rule.field, false, true));
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control form-control-sm';
                    input.placeholder = 'keyword1,keyword2';
                    input.value = rule ? rule.keywords : '';
                    const del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'btn btn-outline-danger btn-sm';
                    del.innerHTML = '<i class="fas fa-trash"></i>';
                    del.addEventListener('click', function () { row.remove(); serialise(); });
                    sel.addEventListener('change', serialise);
                    input.addEventListener('input', serialise);
                    row.appendChild(sel); row.appendChild(input); row.appendChild(del);
                    rows.appendChild(row);
                }
                actions.querySelector('[data-add-rule]').addEventListener('click', function () { addRow(null); });
                actions.querySelector('[data-raw-toggle]').addEventListener('click', function (e) {
                    e.preventDefault(); ta.classList.toggle('d-none');
                });
                ta.addEventListener('input', function () { rows.innerHTML = ''; parse().forEach(addRow); });
                parse().forEach(addRow);
            })();
        });
    </script>
@endpush
