@props([
    'label',
    'name',
    'image' => null,
    'multiple' => false,
    'directory' => null,
    'showMetadata' => true,
    'required' => false,
])

@php
    $fieldId = 'img-' . md5($name);
    $isMultiple = (bool) $multiple;
    $fileInputName = $isMultiple ? $name . '[files][]' : $name . '[file]';
    $errorPrefix = $name;
    $fileErrorKey = $isMultiple ? $errorPrefix . '.files' : $errorPrefix . '.file';
    $pathErrorKey = $errorPrefix . '.path';
    $altErrorKey = $errorPrefix . '.alt';
    $titleErrorKey = $errorPrefix . '.title';
    $captionErrorKey = $errorPrefix . '.caption';
    $existingPath = data_get($image, 'path');
    $existingAlt = data_get($image, 'alt', '');
    $existingTitle = data_get($image, 'title', '');
    $existingCaption = data_get($image, 'caption', '');
    $existingDisk = data_get($image, 'disk', 'public');
    $existingWidth = data_get($image, 'width');
    $existingHeight = data_get($image, 'height');
    $selectedPath = old($name . '.path', $existingPath);
    $selectedDisk = old($name . '.disk', $existingDisk);
    $selectedAlt = old($name . '.alt', $existingAlt);
    $selectedTitle = old($name . '.title', $existingTitle);
    $selectedCaption = old($name . '.caption', $existingCaption);
    $selectedWidth = old($name . '.width', $existingWidth);
    $selectedHeight = old($name . '.height', $existingHeight);
    $previewUrl = $selectedPath ? \Illuminate\Support\Facades\Storage::disk($selectedDisk ?: 'public')->url($selectedPath) : null;
@endphp

<div class="form-group mb-4 js-image-field" id="{{ $fieldId }}" data-directory="{{ $directory ?? '' }}">
    <label class="fw-bold d-block">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>

    @if ($isMultiple)
        <input type="hidden" name="{{ $name }}[multiple]" value="1">
    @endif

    <div class="border rounded p-3 bg-light">
        <div class="image-preview mb-3 {{ $previewUrl ? '' : 'd-none' }}" data-image-preview-wrap style="max-width: 340px;">
            <div class="position-relative border rounded p-1 bg-white shadow-sm">
                <img src="{{ $previewUrl }}" alt="{{ $selectedAlt }}" data-image-preview class="img-fluid rounded" style="max-height: 180px; width: 100%; object-fit: contain; background: #f8f9fa;" />
                <div class="d-flex justify-content-between align-items-center mt-1 px-1">
                    <span class="small text-muted text-truncate" data-image-filename>{{ $selectedPath ? basename($selectedPath) : '' }}</span>
                    <span class="badge bg-light text-dark border small" data-image-dimensions>{{ $selectedWidth && $selectedHeight ? "{$selectedWidth} × {$selectedHeight} px" : '' }}</span>
                </div>
            </div>
        </div>

        <input type="file" name="{{ $fileInputName }}"
            class="form-control form-control-sm {{ $errors->has($fileErrorKey) ? 'is-invalid' : '' }}" accept="image/*"
            {{ $isMultiple ? 'multiple' : '' }} data-image-file>
        @error($fileErrorKey)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror

        <input type="hidden" name="{{ $name }}[path]" value="{{ $selectedPath }}" data-image-path>
        <input type="hidden" name="{{ $name }}[disk]" value="{{ $selectedDisk }}" data-image-disk>

        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-image-picker-open>
                <i class="fas fa-folder-open"></i> Browse File Manager
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-image-picker-clear>
                <i class="fas fa-eraser"></i> Clear Selection
            </button>
        </div>

        <div class="small {{ $errors->has($pathErrorKey) ? 'text-danger' : 'text-muted' }} text-break mt-2" data-image-path-label>
            {{ $selectedPath ? 'Selected asset: ' . $selectedPath : 'No asset selected from file manager.' }}
        </div>
        @error($pathErrorKey)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror

        <div class="form-check mt-2">
            <input type="checkbox" name="{{ $name }}[remove]" value="1" class="form-check-input"
                id="rm-{{ $fieldId }}" data-image-remove>
            <label class="form-check-label small" for="rm-{{ $fieldId }}">Remove this image</label>
        </div>

        @if ($showMetadata)
            <div class="mt-3 pt-2 border-top">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="small text-muted mb-1">Alt text (accessibility &amp; SEO)</label>
                        <input type="text" name="{{ $name }}[alt]"
                            value="{{ $selectedAlt }}"
                            class="form-control form-control-sm {{ $errors->has($altErrorKey) ? 'is-invalid' : '' }}" placeholder="Image alt text" data-image-alt>
                        @error($altErrorKey)
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="small text-muted mb-1">Title attribute</label>
                        <input type="text" name="{{ $name }}[title]"
                            value="{{ $selectedTitle }}"
                            class="form-control form-control-sm {{ $errors->has($titleErrorKey) ? 'is-invalid' : '' }}" placeholder="Image title tooltip" data-image-title>
                        @error($titleErrorKey)
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="small text-muted mb-1">Caption</label>
                        <textarea name="{{ $name }}[caption]" class="form-control form-control-sm {{ $errors->has($captionErrorKey) ? 'is-invalid' : '' }}" rows="2"
                            placeholder="Image caption or subtitle" data-image-caption>{{ $selectedCaption }}</textarea>
                        @error($captionErrorKey)
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-6">
                        <label class="small text-muted mb-1">Width (px)</label>
                        <input type="number" name="{{ $name }}[width]" min="0" max="10000"
                            value="{{ $selectedWidth }}"
                            class="form-control form-control-sm" placeholder="Width in pixels" data-image-width>
                    </div>

                    <div class="col-6">
                        <label class="small text-muted mb-1">Height (px)</label>
                        <input type="number" name="{{ $name }}[height]" min="0" max="10000"
                            value="{{ $selectedHeight }}"
                            class="form-control form-control-sm" placeholder="Height in pixels" data-image-height>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.js-image-field').forEach(function (field) {
                    if (field.dataset.imageBound === '1') {
                        return;
                    }

                    field.dataset.imageBound = '1';

                    const fileInput = field.querySelector('[data-image-file]');
                    const pathInput = field.querySelector('[data-image-path]');
                    const diskInput = field.querySelector('[data-image-disk]');
                    const removeInput = field.querySelector('[data-image-remove]');
                    const previewWrap = field.querySelector('[data-image-preview-wrap]');
                    const preview = field.querySelector('[data-image-preview]');
                    const filename = field.querySelector('[data-image-filename]');
                    const pathLabel = field.querySelector('[data-image-path-label]');
                    const directory = field.dataset.directory || '';

                    const widthInput = field.querySelector('[data-image-width]');
                    const heightInput = field.querySelector('[data-image-height]');
                    const altInput = field.querySelector('[data-image-alt]');
                    const titleInput = field.querySelector('[data-image-title]');
                    const dimBadge = field.querySelector('[data-image-dimensions]');

                    const setPreview = function (url, filePath, w, h) {
                        if (!preview || !previewWrap || !filename || !pathLabel) {
                            return;
                        }

                        if (url && filePath) {
                            preview.src = url;
                            previewWrap.classList.remove('d-none');
                            filename.textContent = filePath.split('/').pop() || filePath;
                            pathLabel.textContent = `Selected asset: ${filePath}`;
                            const widthVal = w || widthInput?.value;
                            const heightVal = h || heightInput?.value;
                            if (dimBadge) {
                                dimBadge.textContent = widthVal && heightVal ? `${widthVal} × ${heightVal} px` : '';
                            }
                            return;
                        }

                        preview.removeAttribute('src');
                        previewWrap.classList.add('d-none');
                        filename.textContent = '';
                        pathLabel.textContent = 'No asset selected from file manager.';
                        if (dimBadge) dimBadge.textContent = '';
                    };

                    field.querySelector('[data-image-picker-open]')?.addEventListener('click', function () {
                        if (typeof window.openAdminFileManagerPicker !== 'function') {
                            return;
                        }

                        window.openAdminFileManagerPicker({
                            directory: pathInput?.value
                                ? pathInput.value.split('/').slice(0, -1).join('/')
                                : directory,
                            imagesOnly: true,
                            onSelect(file) {
                                if (pathInput) {
                                    pathInput.value = file.path || '';
                                }

                                if (diskInput) {
                                    diskInput.value = file.disk || 'public';
                                }

                                if (fileInput) {
                                    fileInput.value = '';
                                }

                                if (removeInput) {
                                    removeInput.checked = false;
                                }

                                if (widthInput && file.width) {
                                    widthInput.value = file.width;
                                }

                                if (heightInput && file.height) {
                                    heightInput.value = file.height;
                                }

                                if (altInput && !altInput.value && file.name) {
                                    altInput.value = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]+/g, ' ');
                                }

                                setPreview(file.url || '', file.path || '', file.width, file.height);
                            },
                        });
                    });

                    field.querySelector('[data-image-picker-clear]')?.addEventListener('click', function () {
                        if (pathInput) {
                            pathInput.value = '';
                        }

                        if (fileInput) {
                            fileInput.value = '';
                        }

                        if (removeInput) {
                            removeInput.checked = false;
                        }

                        if (widthInput) widthInput.value = '';
                        if (heightInput) heightInput.value = '';

                        setPreview('', '');
                    });

                    fileInput?.addEventListener('change', function (event) {
                        const [file] = event.target.files || [];

                        if (!file) {
                            return;
                        }

                        if (pathInput) {
                            pathInput.value = '';
                        }

                        if (removeInput) {
                            removeInput.checked = false;
                        }

                        const reader = new FileReader();
                        reader.onload = function (loadEvent) {
                            const dataUrl = loadEvent.target?.result || '';
                            const tempImg = new Image();
                            tempImg.onload = function () {
                                if (widthInput) widthInput.value = tempImg.naturalWidth;
                                if (heightInput) heightInput.value = tempImg.naturalHeight;
                                setPreview(dataUrl, file.name, tempImg.naturalWidth, tempImg.naturalHeight);
                            };
                            tempImg.src = dataUrl;
                        };
                        reader.readAsDataURL(file);
                    });

                    removeInput?.addEventListener('change', function () {
                        if (!removeInput.checked) {
                            return;
                        }

                        if (fileInput) {
                            fileInput.value = '';
                        }

                        if (pathInput) {
                            pathInput.value = '';
                        }

                        if (widthInput) widthInput.value = '';
                        if (heightInput) heightInput.value = '';

                        setPreview('', '');
                    });
                });
            });
        </script>
    @endpush
@endonce
