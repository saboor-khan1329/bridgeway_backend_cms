@props([
    'label',
    'name',
    'options' => [],
    'selected' => null,
    'selectedLabel' => null,
    'required' => false,
    'attr' => [],
    'ajaxUrl' => null,
    'ajaxParams' => [],
    'placeholder' => null,
])

@php
    $oldKey = ltrim(preg_replace('/\[(.*?)\]/', '.$1', $name), '.');
    $selectedValue = old($oldKey, $selected);
    $defaultPlaceholder = $placeholder ?: 'Select '.$label;
    $classes = $ajaxUrl ? 'form-control select2-ajax' : 'form-control select2';
    $selectAttributes = $attributes
        ->merge(['class' => $classes . ($errors->has($oldKey) ? ' is-invalid' : '')])
        ->merge($attr)
        ->merge([
            'data-placeholder' => $defaultPlaceholder,
        ])
        ->merge($ajaxUrl ? [
            'data-ajax-url' => $ajaxUrl,
            'data-ajax-params' => json_encode($ajaxParams),
        ] : []);
@endphp

<div class="form-group admin-select-group">
    <label for="{{ $name }}" class="admin-select-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
    <select name="{{ $name }}" id="{{ $name }}" 
            {{ $required ? 'required' : '' }}
            {{ $selectAttributes }}>
        <option value=""></option>
        @if($ajaxUrl && $selectedValue)
            <option value="{{ $selectedValue }}" selected>{{ $selectedLabel ?: $selectedValue }}</option>
        @endif
        @foreach($options as $value => $text)
            <option value="{{ $value }}" {{ ((string) $selectedValue === (string) $value) ? 'selected' : '' }}>
                {{ $text }}
            </option>
        @endforeach
    </select>
    @error($oldKey)
        <span class="invalid-feedback d-block">{{ $message }}</span>
    @enderror
</div>
