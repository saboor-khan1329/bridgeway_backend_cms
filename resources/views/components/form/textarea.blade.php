@props(['label', 'name', 'value' => '', 'required' => false, 'editor' => true, 'attr' => []])

@php
    $textareaId = $attributes->get('id') ?? $name;
    $oldKey = ltrim(preg_replace('/\[(.*?)\]/', '.$1', $name), '.');

    $rawValue = old($oldKey, $value);
    if (is_array($rawValue) || is_object($rawValue)) {
        $safeValue = json_encode($rawValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $safeValue = (string) ($rawValue ?? '');
    }
    $textareaAttributes = $attributes
        ->merge(['class' => 'form-control' . ($errors->has($oldKey) ? ' is-invalid' : '') . ($editor ? ' js-rich-editor' : '')])
        ->merge($attr);
@endphp

<div class="form-group">
    <label for="{{ $textareaId }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
    <textarea name="{{ $name }}" id="{{ $textareaId }}" {{ $required ? 'required' : '' }}
        {{ $textareaAttributes }}>{{ $safeValue }}</textarea>

    @error($oldKey)
        <span class="invalid-feedback d-block">{{ $message }}</span>
    @enderror
</div>

@if ($editor)
    @once
        @push('vendor-scripts')
            <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
        @endpush
        @push('textareajs')
            <script>
                window.__initRichEditors = window.__initRichEditors || function () {
                    if (typeof tinymce === 'undefined') return;
                    document.querySelectorAll('textarea.js-rich-editor:not(.tinymce-bound)').forEach(function (el) {
                        el.classList.add('tinymce-bound');
                        tinymce.init({
                            target: el,
                            license_key: 'gpl',
                            height: 420,
                            menubar: 'edit view insert format tools table',
                            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount codesample',
                            toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table codesample | removeformat code fullscreen',
                            convert_urls: false,
                            branding: false,
                            promotion: false,
                            relative_urls: false,
                            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; font-size: 14px; }',
                        });
                    });
                };
                document.addEventListener('DOMContentLoaded', window.__initRichEditors);
            </script>
        @endpush
    @endonce
@endif
