@extends('template.main')

@php $readonly = $config['readonly'] ?? false; @endphp

@section('title', $config['title'])

@section('content')
    <x-layouts.page-wrapper :title="$config['title']" :breadcrumbs="[$config['title'] => null]">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="{{ $config['routes']['index'] }}" class="btn btn-warning btn-sm">
                    <i class="fa fa-arrow-left"></i> Back
                </a>
                <div class="d-flex align-items-center gap-2">
                    @if ($readonly)
                        <span class="badge bg-info">VIEW ONLY</span>
                    @endif
                </div>
            </div>

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

            <form method="POST"
                action="{{ $readonly ? '#' : ($item ? $config['routes']['update'] : $config['routes']['store']) }}"
                enctype="multipart/form-data" id="crud-form">
                @if (!$readonly)
                    @csrf
                    @if ($item)
                        @method('PUT')
                        @if ($item->updated_at ?? null)
                            <input type="hidden" name="_lock_updated_at" value="{{ $item->updated_at->getTimestamp() }}">
                        @endif
                    @endif
                @endif

                <div class="card">
                    <div class="card-body">
                        @foreach ($config['form'] as $row)
                            <div class="row">
                                @foreach ($row as $field)
                                    @php
                                        $name = $field['name'];
                                        $oldKey = ltrim(preg_replace('/\[(.*?)\]/', '.$1', $name), '.');
                                        $value = old($oldKey, $field['value'] ?? ($item->{$name} ?? ''));
                                        $col = $field['col'] ?? 6;
                                        $attr = $field['attr'] ?? [];
                                        $required = $field['required'] ?? false;
                                    @endphp
                                    <div class="col-md-{{ $col }}">
                                        @switch($field['type'])
                                            @case('text')
                                                <x-form.text-input :label="$field['label']" :name="$name" :value="$value"
                                                    :required="$required" :attr="$attr" />
                                            @break
                                            @case('number')
                                                <x-form.number-input :label="$field['label']" :name="$name" :value="$value"
                                                    :required="$required" :attr="$attr" />
                                            @break
                                            @case('textarea')
                                                <x-form.textarea :label="$field['label']" :name="$name" :value="$value"
                                                    :required="$required" :attr="$attr" :editor="$field['editor'] ?? true" />
                                            @break
                                            @case('image')
                                                <x-form.image-input :label="$field['label']" :name="$name"
                                                    :image="$item?->getImage($field['image_key'])"
                                                    :multiple="$field['multiple'] ?? false"
                                                    :directory="$field['directory'] ?? null"
                                                    :show-metadata="$field['show_metadata'] ?? true"
                                                    :required="$required" />
                                            @break
                                            @case('select')
                                                <x-form.select :label="$field['label']" :name="$name" :options="$field['options']"
                                                    :selected="$field['selected'] ?? $value" :required="$required"
                                                    :ajax-url="$field['ajax_url'] ?? null" :ajax-params="$field['ajax_params'] ?? []"
                                                    :selected-label="$field['selected_label'] ?? null"
                                                    :placeholder="$field['placeholder'] ?? null" />
                                            @break
                                            @case('multi-select')
                                                <x-form.multi-select :label="$field['label']" :name="$name" :options="$field['options']"
                                                    :selected="$field['selected']" :selected-labels="$field['selected_labels'] ?? []"
                                                    :required="$required" :placeholder="$field['placeholder'] ?? 'Select options'"
                                                    :ajax-url="$field['ajax_url'] ?? null" :ajax-params="$field['ajax_params'] ?? []"
                                                    :ajax-create-url="$field['ajax_create_url'] ?? null" />
                                            @break
                                        @endswitch
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    @if (!$readonly)
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-save"></i> {{ $item ? 'Update' : 'Save' }}
                            </button>
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </x-layouts.page-wrapper>

    @include('admin.shared.seo-toolkit')
@endsection
