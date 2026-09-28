@props(['label', 'name', 'value' => '', 'required' => false, 'attr' => []])

@php
    $oldKey = ltrim(preg_replace('/\[(.*?)\]/', '.$1', $name), '.');
    $fieldValue = old($oldKey, $value);
    $inputAttributes = $attributes
        ->merge(['class' => 'form-control' . ($errors->has($oldKey) ? ' is-invalid' : '')])
        ->merge($attr);
@endphp

<div class="form-group">
    <label for="{{ $name }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
    <input type="number" 
           name="{{ $name }}" 
           id="{{ $name }}" 
           value="{{ $fieldValue }}"
           {{ $required ? 'required' : '' }}
           {{ $inputAttributes }}>
    @error($oldKey)
        <span class="invalid-feedback d-block">{{ $message }}</span>
    @enderror
</div>
