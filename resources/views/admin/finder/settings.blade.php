@extends('template.main')
@section('title', $title)

@php
    use App\Support\ServiceFinderSettings;
@endphp

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Configurator' => null]">
        @include('admin.finder.partials.nav')

        <div class="col-12 sf-settings">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <ul class="nav nav-tabs mb-0">
                @foreach ($tabs as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === $key ? 'active' : '' }}"
                           href="{{ route('admin.finder-settings.edit', ['tab' => $key]) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>

            {{-- The Guide tab is documentation, not settings: it renders on its
                 own and never shows a form, because there is nothing on it to
                 save and a "Save Guide" button would be a trap. --}}
            @if ($activeTab === 'guide')
                @include('admin.finder.partials.guide')
            @else

            <form method="POST" action="{{ route('admin.finder-settings.update') }}">
                @csrf @method('PUT')
                <input type="hidden" name="_tab" value="{{ $activeTab }}">

                <div class="card border-top-0 rounded-top-0">
                    <div class="card-body">
                        {{-- Toggles are rendered as a single-column settings list, not the
                             2-up grid below: two switches side by side only line up when
                             their help text happens to be the same length, and the moment
                             one wraps to a second line while its neighbour doesn't, the
                             pair looks scattered even though the row itself is aligned.
                             A full-width row with the switch pinned to a fixed right-hand
                             column can never do that, at any text length or viewport. --}}
                        @php $toggleFields = collect($fields[$activeTab])->filter(fn ($field) => ($field['type'] ?? 'text') === 'toggle'); @endphp
                        @if ($toggleFields->isNotEmpty())
                            <div class="sf-toggle-list mb-3">
                                @foreach ($toggleFields as $key => $field)
                                    @php
                                        $value = old($key, ServiceFinderSettings::raw($key));
                                        $id    = 'sf_'.$key;
                                    @endphp
                                    <div class="sf-toggle-row">
                                        <div class="sf-toggle-text">
                                            <label for="{{ $id }}" class="form-label fw-semibold mb-0">{{ $field['label'] }}</label>
                                            @isset($field['help'])
                                                <div class="form-text mb-0">{{ $field['help'] }}</div>
                                            @endisset
                                        </div>
                                        <div class="form-check form-switch sf-toggle-switch">
                                            <input type="hidden" name="{{ $key }}" value="0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="{{ $id }}" name="{{ $key }}" value="1"
                                                   @checked(filter_var($value, FILTER_VALIDATE_BOOL))>
                                            <label class="form-check-label small text-muted" for="{{ $id }}">Enabled</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="row">
                            {{-- The form is generated from the same field definitions the
                                 application reads at runtime, so a setting can never exist
                                 in code but be missing from this screen. Toggles were
                                 already rendered above, as a list — skipped here. --}}
                            @foreach ($fields[$activeTab] as $key => $field)
                                @continue(($field['type'] ?? 'text') === 'toggle')
                                @php
                                    $value = old($key, ServiceFinderSettings::raw($key));
                                    $type  = $field['type'] ?? 'text';
                                    $id    = 'sf_'.$key;
                                @endphp

                                <div class="col-md-{{ $field['col'] ?? 6 }} mb-3">
                                    <label for="{{ $id }}" class="form-label fw-semibold">{{ $field['label'] }}</label>

                                    @switch($type)
                                        @case('number')
                                            <input type="number" class="form-control" id="{{ $id }}" name="{{ $key }}"
                                                   value="{{ $value }}"
                                                   @isset($field['min']) min="{{ $field['min'] }}" @endisset
                                                   @isset($field['max']) max="{{ $field['max'] }}" @endisset
                                                   step="{{ $field['step'] ?? 1 }}">
                                            @break

                                        @case('select')
                                            <select class="form-select" id="{{ $id }}" name="{{ $key }}">
                                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>
                                                        {{ $optionLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @break

                                        @case('lines')
                                        @case('keyvalue')
                                            <textarea class="form-control font-monospace" id="{{ $id }}" name="{{ $key }}"
                                                      rows="{{ $field['rows'] ?? 6 }}"
                                                      spellcheck="false">{{ $value }}</textarea>
                                            @break

                                        @case('textarea')
                                            <textarea class="form-control" id="{{ $id }}" name="{{ $key }}"
                                                      rows="{{ $field['rows'] ?? 3 }}">{{ $value }}</textarea>
                                            @break

                                        @default
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="{{ $id }}" name="{{ $key }}"
                                                       value="{{ $value }}"
                                                       @isset($field['max']) maxlength="{{ $field['max'] }}" @endisset>
                                                @if (str_ends_with($key, '_color'))
                                                    <input type="color" class="form-control form-control-color"
                                                           value="{{ preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#000000' }}"
                                                           oninput="document.getElementById('{{ $id }}').value = this.value;"
                                                           title="Pick a colour">
                                                @endif
                                            </div>
                                    @endswitch

                                    @isset($field['tokens'])
                                        <div class="form-text">
                                            Placeholders:
                                            @foreach ($field['tokens'] as $token)
                                                <code>{{ $token }}</code>@if (! $loop->last), @endif
                                            @endforeach
                                        </div>
                                    @endisset

                                    @isset($field['help'])
                                        <div class="form-text">{{ $field['help'] }}</div>
                                    @endisset
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                onclick="if (confirm('Restore this tab to its default values?')) document.getElementById('reset-form').submit();">
                            <i class="fa fa-rotate-left"></i> Restore defaults
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i> Save {{ $tabs[$activeTab] }}
                        </button>
                    </div>
                </div>
            </form>

            <form id="reset-form" method="POST" action="{{ route('admin.finder-settings.reset') }}" class="d-none">
                @csrf
                <input type="hidden" name="_tab" value="{{ $activeTab }}">
            </form>

            @endif
        </div>
    </x-layouts.page-wrapper>
@endsection
