@php
    $label = is_int($fieldKey) ? 'Item '.($fieldKey + 1) : \Illuminate\Support\Str::headline($fieldKey);
    $textarea = is_string($value) && preg_match('/(description|detail|content|answer|quote|para|challenge|initiative|notice|text(one|two|three)?$)/i', (string) $fieldKey);
    $isImageObject = is_array($value) && !array_is_list($value) && (isset($value['src']) || isset($value['imgSrc']) || isset($value['img']) || isset($value['path']));
    $isImageScalar = is_string($value) && (preg_match('/^(image|img|imgSrc|icon|banner|thumbnail|bgImg|logo)$/i', (string) $fieldKey) || (preg_match('/(src|image|img|icon|banner|thumbnail)$/i', (string) $fieldKey) && !preg_match('/(description|detail|text|content|quote)/i', (string) $fieldKey)));
@endphp

@if($isImageObject)
    @php
        $srcKey = isset($value['src']) ? 'src' : (isset($value['imgSrc']) ? 'imgSrc' : (isset($value['img']) ? 'img' : 'path'));
        $srcValue = (string) ($value[$srcKey] ?? '');
        $altValue = (string) ($value['alt'] ?? '');
        $titleValue = (string) ($value['title'] ?? '');
        $captionValue = (string) ($value['caption'] ?? '');
        $widthValue = (string) ($value['width'] ?? '');
        $heightValue = (string) ($value['height'] ?? '');
        $imgPreviewUrl = $srcValue !== '' ? (str_starts_with($srcValue, 'http') || str_starts_with($srcValue, '/') ? $srcValue : asset('storage/'.$srcValue)) : null;
        $standardKeys = [$srcKey, 'alt', 'title', 'caption', 'width', 'height'];
        $extraKeys = array_diff_key($value, array_flip($standardKeys));
    @endphp
    <fieldset class="cms-fieldset cms-image-object js-cms-image-widget mb-3" data-name="{{ $name }}">
        <legend><i class="fas fa-image me-1 text-primary"></i> {{ $label }}</legend>
        <div class="row g-2">
            <div class="col-md-4 mb-2">
                <div class="border rounded p-2 text-center bg-white h-100 d-flex flex-column justify-content-center align-items-center position-relative shadow-sm" style="min-height: 160px;">
                    <div class="cms-img-preview-box w-100 d-flex align-items-center justify-content-center mb-2 {{ $imgPreviewUrl ? '' : 'd-none' }}" style="height: 120px; overflow: hidden; background: #f8f9fa; border-radius: 4px;">
                        <img src="{{ $imgPreviewUrl }}" alt="{{ $altValue }}" class="img-fluid js-cms-img-preview" style="max-height: 120px; max-width: 100%; object-fit: contain;">
                    </div>
                    <div class="cms-img-placeholder text-muted py-4 {{ $imgPreviewUrl ? 'd-none' : '' }}">
                        <i class="fas fa-image fa-3x mb-2 text-secondary opacity-50"></i>
                        <div class="small">No image selected</div>
                    </div>
                    <div class="w-100 d-flex gap-1 mt-auto">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100 js-cms-open-picker" data-src-key="{{ $srcKey }}">
                            <i class="fas fa-folder-open me-1"></i> Browse File Manager
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-cms-clear-image" title="Clear selection">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-8 mb-2">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Image Source / Path</label>
                        <input type="text" name="{{ $name }}[{{ $srcKey }}]" value="{{ $srcValue }}" class="form-control form-control-sm js-cms-img-src" placeholder="/images/... or file manager path">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Alt Text (SEO &amp; Accessibility)</label>
                        <input type="text" name="{{ $name }}[alt]" value="{{ $altValue }}" class="form-control form-control-sm js-cms-img-alt" placeholder="Descriptive alt text">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Title Attribute</label>
                        <input type="text" name="{{ $name }}[title]" value="{{ $titleValue }}" class="form-control form-control-sm js-cms-img-title" placeholder="Image tooltip / title">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Caption</label>
                        <input type="text" name="{{ $name }}[caption]" value="{{ $captionValue }}" class="form-control form-control-sm js-cms-img-caption" placeholder="Optional image caption">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1">Width (px)</label>
                        <input type="number" name="{{ $name }}[width]" value="{{ $widthValue }}" class="form-control form-control-sm js-cms-img-width" placeholder="Width in pixels">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1">Height (px)</label>
                        <input type="number" name="{{ $name }}[height]" value="{{ $heightValue }}" class="form-control form-control-sm js-cms-img-height" placeholder="Height in pixels">
                    </div>
                </div>
            </div>
        </div>
        @if(!empty($extraKeys))
            <div class="mt-2 pt-2 border-top">
                @foreach($extraKeys as $extraChildKey => $extraChildValue)
                    @include('admin.section-pages._field', ['name' => $name."[{$extraChildKey}]", 'fieldKey' => $extraChildKey, 'value' => $extraChildValue, 'depth' => $depth + 1])
                @endforeach
            </div>
        @endif
    </fieldset>
@elseif(is_array($value) && !array_is_list($value))
    <fieldset class="cms-fieldset"><legend>{{ $label }}</legend>@foreach($value as $childKey => $childValue) @include('admin.section-pages._field', ['name' => $name."[{$childKey}]", 'fieldKey' => $childKey, 'value' => $childValue, 'depth' => $depth + 1]) @endforeach</fieldset>
@elseif(is_array($value))
    <fieldset class="cms-fieldset cms-repeater" data-repeater-name="{{ $name }}"><legend>{{ $label }} <small class="text-muted">(repeatable)</small></legend>
        @foreach($value as $itemIndex => $itemValue)
            <div class="cms-repeater-item position-relative">
                <button type="button" class="btn btn-outline-danger btn-sm remove-repeater-item float-right"><i class="fas fa-minus"></i> Remove</button>
                @if(is_array($itemValue))
                    @foreach($itemValue as $childKey => $childValue) @include('admin.section-pages._field', ['name' => $name."[{$itemIndex}][{$childKey}]", 'fieldKey' => $childKey, 'value' => $childValue, 'depth' => $depth + 1]) @endforeach
                @else
                    @include('admin.section-pages._field', ['name' => $name."[{$itemIndex}]", 'fieldKey' => $itemIndex, 'value' => $itemValue, 'depth' => $depth + 1])
                @endif
            </div>
        @endforeach
        @if($value !== [])<button type="button" class="btn btn-outline-primary btn-sm add-repeater-item"><i class="fas fa-plus"></i> Add {{ \Illuminate\Support\Str::singular($label) }}</button>@else<p class="text-muted">No items configured.</p>@endif
    </fieldset>
@elseif(is_bool($value))
    <div class="form-group"><input type="hidden" name="{{ $name }}" value="0"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="field-{{ md5($name) }}" name="{{ $name }}" value="1" @checked($value)><label class="custom-control-label" for="field-{{ md5($name) }}">{{ $label }}</label></div></div>
@elseif(is_int($value) || is_float($value))
    <div class="form-group"><label>{{ $label }}</label><input type="number" step="{{ is_float($value) ? 'any' : '1' }}" name="{{ $name }}" value="{{ $value }}" class="form-control"></div>
@elseif($textarea)
    <div class="form-group"><label>{{ $label }}</label><textarea name="{{ $name }}" rows="4" class="form-control">{{ $value }}</textarea></div>
@elseif($isImageScalar)
    @php
        $scalarPreviewUrl = $value !== '' ? (str_starts_with($value, 'http') || str_starts_with($value, '/') ? $value : asset('storage/'.$value)) : null;
    @endphp
    <div class="form-group js-cms-scalar-image-group mb-3">
        <label class="fw-semibold">{{ $label }}</label>
        <div class="d-flex align-items-center gap-2">
            <div class="js-cms-scalar-preview-wrap {{ $scalarPreviewUrl ? '' : 'd-none' }}" style="width: 42px; height: 42px; flex-shrink: 0;">
                <img src="{{ $scalarPreviewUrl }}" class="js-cms-scalar-preview rounded border shadow-sm" style="width: 42px; height: 42px; object-fit: cover; background: #f8f9fa;">
            </div>
            <input type="text" name="{{ $name }}" value="{{ $value }}" class="form-control js-cms-scalar-input" placeholder="Media path or URL">
            <button type="button" class="btn btn-outline-primary btn-sm text-nowrap js-cms-scalar-browse" title="Browse File Manager">
                <i class="fas fa-folder-open"></i> Browse
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm js-cms-scalar-clear" title="Clear">
                <i class="fas fa-eraser"></i>
            </button>
        </div>
        <small class="form-text text-muted">Select an image from the File Manager or enter a root-relative/absolute URL.</small>
    </div>
@else
    <div class="form-group"><label>{{ $label }}</label><input type="text" name="{{ $name }}" value="{{ $value }}" class="form-control">@if(preg_match('/(src|image|img|icon|href|url)$/i', (string) $fieldKey))<small class="form-text text-muted">Use a root-relative media path or an absolute URL.</small>@endif</div>
@endif
