@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="[$title => null]">
    <div class="col-12">
        @if($errors->any())
            <div class="alert alert-danger"><strong>Please fix the highlighted fields.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ $item->exists ? route($routeName.'.update', $item) : route($routeName.'.store') }}" id="cms-page-form">
            @csrf
            @if($item->exists) @method('PUT') @endif
            <input type="hidden" name="add_section_type" id="add-section-type" value="">

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Page settings</h3></div>
                <div class="card-body"><div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title', $item->title) }}" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Slug</label><input name="slug" class="form-control" value="{{ old('slug', $item instanceof \App\Models\ContentPage ? $item->slug : $item->slug?->slug) }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></div>
                    @if($item instanceof \App\Models\ContentPage)
                        <div class="col-md-6 mb-3"><label class="form-label">Frontend template</label><select name="template" class="form-control">@foreach($templates as $template)<option value="{{ $template }}" @selected(old('template', $item->template) === $template)>{{ \Illuminate\Support\Str::headline($template) }}</option>@endforeach</select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Sort order</label><input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', $item->sort_order ?? 0) }}"></div>
                    @else
                        <div class="col-md-9 mb-3"><label class="form-label">Short description</label><textarea name="short_description" class="form-control" rows="3">{{ old('short_description', $item->short_description) }}</textarea></div>
                    @endif
                    <div class="col-md-3 mb-3 d-flex align-items-end"><div class="custom-control custom-switch mb-2"><input type="hidden" name="status" value="0"><input type="checkbox" class="custom-control-input" id="status" name="status" value="1" @checked(old('status', $item->status ?? true))><label class="custom-control-label" for="status">Published</label></div></div>
                </div></div>
            </div>

            @if($item->template !== 'global')
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">SEO and social sharing</h3></div>
                <div class="card-body"><div class="row">
                    <div class="col-12 mb-3"><label class="form-label">Title tag</label><input name="meta_title" maxlength="255" class="form-control" value="{{ old('meta_title', $seo?->meta_title) }}"></div>
                    <div class="col-12 mb-3"><label class="form-label">Meta description</label><textarea name="meta_description" rows="3" class="form-control">{{ old('meta_description', $seo?->meta_description) }}</textarea></div>
                    <div class="col-12 mb-3"><label class="form-label">Canonical URL</label><input name="canonical_url" maxlength="500" class="form-control" value="{{ old('canonical_url', $seo?->canonical_url) }}" placeholder="/page-slug or https://example.com/page"></div>
                    <div class="col-12 mb-3"><label class="form-label">Keywords</label><input name="keywords" class="form-control" value="{{ old('keywords', $seo?->keywords) }}" placeholder="Comma-separated"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Robots indexing</label><select name="robots_index" class="form-control"><option value="index" @selected(old('robots_index', $seo?->robots_index ?? 'index') === 'index')>Index</option><option value="noindex" @selected(old('robots_index', $seo?->robots_index) === 'noindex')>No index</option></select></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Robots links</label><select name="robots_follow" class="form-control"><option value="follow" @selected(old('robots_follow', $seo?->robots_follow ?? 'follow') === 'follow')>Follow</option><option value="nofollow" @selected(old('robots_follow', $seo?->robots_follow) === 'nofollow')>No follow</option></select></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Open Graph title</label><input name="og_title" class="form-control" value="{{ old('og_title', $seo?->og_title) }}"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Open Graph type</label><select name="og_type" class="form-control"><option value="website" @selected(old('og_type', $seo?->og_type ?? 'website') === 'website')>Website</option><option value="article" @selected(old('og_type', $seo?->og_type) === 'article')>Article</option></select></div>
                    <div class="col-12 mb-3"><label class="form-label">Open Graph description</label><textarea name="og_description" rows="3" class="form-control">{{ old('og_description', $seo?->og_description) }}</textarea></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Open Graph URL</label><input name="og_url" class="form-control" value="{{ old('og_url', $seo?->og_url) }}"></div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Open Graph image</label>
                        <div class="input-group">
                            <input name="og_image" id="og_image" class="form-control" value="{{ old('og_image', $seo?->og_image) }}" placeholder="/images/... or file manager path">
                            <button type="button" class="btn btn-outline-primary js-seo-img-picker" data-target="og_image"><i class="fas fa-folder-open"></i> Browse</button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3"><label class="form-label">Twitter title</label><input name="twitter_title" class="form-control" value="{{ old('twitter_title', $seo?->twitter_title) }}"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Twitter card</label><select name="twitter_card" class="form-control"><option value="summary_large_image" @selected(old('twitter_card', $seo?->twitter_card ?? 'summary_large_image') === 'summary_large_image')>Large image</option><option value="summary" @selected(old('twitter_card', $seo?->twitter_card) === 'summary')>Summary</option></select></div>
                    <div class="col-12 mb-3"><label class="form-label">Twitter description</label><textarea name="twitter_description" rows="3" class="form-control">{{ old('twitter_description', $seo?->twitter_description) }}</textarea></div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Twitter image</label>
                        <div class="input-group">
                            <input name="twitter_image" id="twitter_image" class="form-control" value="{{ old('twitter_image', $seo?->twitter_image) }}" placeholder="/images/... or file manager path">
                            <button type="button" class="btn btn-outline-primary js-seo-img-picker" data-target="twitter_image"><i class="fas fa-folder-open"></i> Browse</button>
                        </div>
                    </div>
                    <div class="col-12"><input type="hidden" name="enable_schema" value="0"><div class="custom-control custom-switch mb-2"><input type="checkbox" class="custom-control-input" id="enable-schema" name="enable_schema" value="1" @checked(old('enable_schema', $seo?->enable_schema ?? true))><label class="custom-control-label font-weight-bold" for="enable-schema">Enable Schema Markup for this page</label></div></div>

                    <div class="col-12 mt-2 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label font-weight-bold mb-0" for="page-schema">
                                <i class="fas fa-code me-1 text-primary"></i> Page-Level Schema Configuration (JSON-LD Override)
                            </label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm js-format-page-schema" data-target="page-schema">
                                    <i class="fas fa-magic me-1"></i> Format / Validate JSON
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm js-insert-starter-schema" data-target="page-schema" data-template="{{ $item instanceof \App\Models\ContentPage ? $item->template : 'service' }}">
                                    <i class="fas fa-file-code me-1"></i> Insert Starter Schema
                                </button>
                            </div>
                        </div>
                        <textarea name="schema" id="page-schema" rows="7" class="form-control font-monospace" placeholder="Leave empty to use the global/template schema from the SEO Configurator, or paste custom JSON-LD schema here...">{{ old('schema', is_array($seo?->schema) ? json_encode($seo->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $seo?->schema) }}</textarea>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Override Rule:</strong> If configured, this custom schema will be used exclusively for this page. If left blank, the page automatically inherits the template/sitewide schema from the SEO Configurator.
                            </small>
                            <span class="small js-page-schema-status"></span>
                        </div>
                    </div>
                </div></div>
            </div>

            @endif
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h4 mb-0">Page sections</h2>
                <div class="d-flex gap-2"><select id="section-type-picker" class="form-control form-control-sm">@foreach($sectionTypes as $type)<option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>@endforeach</select><button type="button" id="add-section" class="btn btn-outline-primary btn-sm text-nowrap"><i class="fas fa-plus"></i> Add section</button></div>
            </div>
            <p class="text-muted">Each component has typed fields. Drag-free Move buttons preserve predictable keyboard access and exact frontend order.</p>

            <div id="cms-sections">
                @foreach($sections as $sectionIndex => $section)
                    <div class="card cms-section mb-3">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div><strong>{{ \Illuminate\Support\Str::headline($section['type']) }}</strong><small class="text-muted ml-2">Component: {{ $section['type'] }}</small></div>
                            <div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary move-section-up" title="Move up"><i class="fas fa-arrow-up"></i></button><button type="button" class="btn btn-outline-secondary move-section-down" title="Move down"><i class="fas fa-arrow-down"></i></button><button type="button" class="btn btn-outline-danger remove-section" title="Remove"><i class="fas fa-trash"></i></button></div>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="sections[{{ $sectionIndex }}][type]" value="{{ $section['type'] }}">
                            <div class="row"><div class="col-md-8 mb-3"><label class="form-label">Section key</label><input name="sections[{{ $sectionIndex }}][id]" class="form-control" value="{{ $section['id'] }}" required></div><div class="col-md-4 mb-3 d-flex align-items-end"><input type="hidden" name="sections[{{ $sectionIndex }}][enabled]" value="0"><div class="custom-control custom-switch mb-2"><input type="checkbox" class="custom-control-input" id="section-enabled-{{ $sectionIndex }}" name="sections[{{ $sectionIndex }}][enabled]" value="1" @checked($section['enabled'])><label class="custom-control-label" for="section-enabled-{{ $sectionIndex }}">Enabled</label></div></div></div>
                            <div class="cms-fields">@foreach(($section['data'] ?? []) as $key => $value) @include('admin.section-pages._field', ['name' => "sections[{$sectionIndex}][data][{$key}]", 'fieldKey' => $key, 'value' => $value, 'depth' => 0]) @endforeach</div>
                            @if(($section['data'] ?? []) === [])<p class="text-muted mb-0">This component has no configurable content fields.</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card"><div class="card-footer d-flex justify-content-between"><a href="{{ route($routeName.'.index') }}" class="btn btn-outline-secondary">Back</a><button class="btn btn-success"><i class="fas fa-save"></i> Save page</button></div></div>
        </form>
    </div>
</x-layouts.page-wrapper>
@endsection

@push('head')
<style>.cms-fieldset{border:1px solid #dee2e6;border-radius:.35rem;padding:1rem;margin-bottom:1rem}.cms-fieldset>legend{font-size:1rem;width:auto;padding:0 .4rem}.cms-repeater-item{border-left:3px solid #dee2e6;padding-left:1rem;margin-bottom:1rem}.cms-section>.card-header{cursor:default}.cms-fields .form-text{font-size:.75rem}</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('cms-page-form');
    const sections = document.getElementById('cms-sections');
    const renumberSections = () => {
        if (!sections) return;
        [...sections.querySelectorAll(':scope > .cms-section')].forEach((section, index) => {
            section.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(/^sections\[\d+\]/, `sections[${index}]`));
            section.querySelectorAll('[id]').forEach(el => { if (el.id.startsWith('section-enabled-')) el.id = `section-enabled-${index}`; });
            section.querySelectorAll('label[for]').forEach(el => { if (el.htmlFor.startsWith('section-enabled-')) el.htmlFor = `section-enabled-${index}`; });
        });
    };
    const clearClone = node => node.querySelectorAll('input, textarea, select').forEach(el => {
        if (el.type === 'hidden' || el.type === 'checkbox') return;
        el.value = '';
    });
    const renumberRepeater = repeater => {
        const prefix = repeater.dataset.repeaterName;
        [...repeater.querySelectorAll(':scope > .cms-repeater-item')].forEach((item, newIndex) => {
            const firstNamed = item.querySelector('[name]');
            const suffix = firstNamed?.name.slice(prefix.length) || '';
            const match = suffix.match(/^\[(\d+)\]/);
            if (!match) return;
            const oldPrefix = `${prefix}[${match[1]}]`, newPrefix = `${prefix}[${newIndex}]`;
            item.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(oldPrefix, newPrefix));
            item.querySelectorAll('[data-repeater-name]').forEach(el => el.dataset.repeaterName = el.dataset.repeaterName.replace(oldPrefix, newPrefix));
        });
    };

    document.getElementById('add-section')?.addEventListener('click', () => {
        document.getElementById('add-section-type').value = document.getElementById('section-type-picker').value;
        form.requestSubmit();
    });

    sections?.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const section = button.closest('.cms-section');
        if (button.classList.contains('remove-section')) { if (confirm('Remove this section?')) { section.remove(); renumberSections(); } return; }
        if (button.classList.contains('move-section-up') && section.previousElementSibling) sections.insertBefore(section, section.previousElementSibling);
        if (button.classList.contains('move-section-down') && section.nextElementSibling) sections.insertBefore(section.nextElementSibling, section);
        if (button.classList.contains('move-section-up') || button.classList.contains('move-section-down')) renumberSections();

        if (button.classList.contains('remove-repeater-item')) {
            const item = button.closest('.cms-repeater-item');
            const repeater = item.parentElement;
            if (repeater.querySelectorAll(':scope > .cms-repeater-item').length > 1) { item.remove(); renumberRepeater(repeater); }
        }
        if (button.classList.contains('add-repeater-item')) {
            const repeater = button.closest('.cms-repeater');
            const items = [...repeater.querySelectorAll(':scope > .cms-repeater-item')];
            if (!items.length) return;
            const clone = items[items.length - 1].cloneNode(true);
            const oldIndex = items.length - 1, newIndex = items.length;
            const oldPrefix = `${repeater.dataset.repeaterName}[${oldIndex}]`, newPrefix = `${repeater.dataset.repeaterName}[${newIndex}]`;
            clone.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(oldPrefix, newPrefix));
            clone.querySelectorAll('[data-repeater-name]').forEach(el => el.dataset.repeaterName = el.dataset.repeaterName.replace(oldPrefix, newPrefix));
            clearClone(clone);
            repeater.insertBefore(clone, button);
        }
    });

    // File Manager Integration & Image Fields
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('button, .btn');
        if (!btn) return;

        // CMS Image Object Widget Picker
        if (btn.classList.contains('js-cms-open-picker')) {
            const widget = btn.closest('.js-cms-image-widget');
            if (!widget) return;
            const srcInput = widget.querySelector('.js-cms-img-src');
            const altInput = widget.querySelector('.js-cms-img-alt');
            const titleInput = widget.querySelector('.js-cms-img-title');
            const widthInput = widget.querySelector('.js-cms-img-width');
            const heightInput = widget.querySelector('.js-cms-img-height');
            const previewImg = widget.querySelector('.js-cms-img-preview');
            const previewBox = widget.querySelector('.cms-img-preview-box');
            const placeholder = widget.querySelector('.cms-img-placeholder');

            if (typeof window.openAdminFileManagerPicker === 'function') {
                window.openAdminFileManagerPicker({
                    imagesOnly: true,
                    onSelect(file) {
                        const chosenUrl = file.url || (file.path ? (file.path.startsWith('/') || file.path.startsWith('http') ? file.path : '/storage/' + file.path) : '');
                        if (srcInput) srcInput.value = chosenUrl;
                        if (widthInput && file.width) widthInput.value = file.width;
                        if (heightInput && file.height) heightInput.value = file.height;
                        if (altInput && !altInput.value && file.name) {
                            altInput.value = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]+/g, ' ').trim();
                        }
                        if (titleInput && !titleInput.value && file.name) {
                            titleInput.value = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]+/g, ' ').trim();
                        }
                        if (previewImg) previewImg.src = chosenUrl;
                        if (previewBox) previewBox.classList.remove('d-none');
                        if (placeholder) placeholder.classList.add('d-none');
                    }
                });
            }
        }

        // CMS Image Object Widget Clear
        if (btn.classList.contains('js-cms-clear-image')) {
            const widget = btn.closest('.js-cms-image-widget');
            if (!widget) return;
            widget.querySelectorAll('.js-cms-img-src, .js-cms-img-alt, .js-cms-img-title, .js-cms-img-caption, .js-cms-img-width, .js-cms-img-height').forEach(i => i.value = '');
            const previewImg = widget.querySelector('.js-cms-img-preview');
            const previewBox = widget.querySelector('.cms-img-preview-box');
            const placeholder = widget.querySelector('.cms-img-placeholder');
            if (previewImg) previewImg.src = '';
            if (previewBox) previewBox.classList.add('d-none');
            if (placeholder) placeholder.classList.remove('d-none');
        }

        // CMS Scalar Image Browse
        if (btn.classList.contains('js-cms-scalar-browse')) {
            const group = btn.closest('.js-cms-scalar-image-group');
            if (!group) return;
            const input = group.querySelector('.js-cms-scalar-input');
            const preview = group.querySelector('.js-cms-scalar-preview');
            const previewWrap = group.querySelector('.js-cms-scalar-preview-wrap');

            if (typeof window.openAdminFileManagerPicker === 'function') {
                window.openAdminFileManagerPicker({
                    imagesOnly: true,
                    onSelect(file) {
                        const chosenUrl = file.url || (file.path ? (file.path.startsWith('/') || file.path.startsWith('http') ? file.path : '/storage/' + file.path) : '');
                        if (input) input.value = chosenUrl;
                        if (preview) preview.src = chosenUrl;
                        if (previewWrap) previewWrap.classList.remove('d-none');
                    }
                });
            }
        }

        // CMS Scalar Image Clear
        if (btn.classList.contains('js-cms-scalar-clear')) {
            const group = btn.closest('.js-cms-scalar-image-group');
            if (!group) return;
            const input = group.querySelector('.js-cms-scalar-input');
            const preview = group.querySelector('.js-cms-scalar-preview');
            const previewWrap = group.querySelector('.js-cms-scalar-preview-wrap');
            if (input) input.value = '';
            if (preview) preview.src = '';
            if (previewWrap) previewWrap.classList.add('d-none');
        }

        // SEO Open Graph / Twitter Image Picker
        if (btn.classList.contains('js-seo-img-picker')) {
            const targetId = btn.dataset.target;
            const targetInput = document.getElementById(targetId);
            if (targetInput && typeof window.openAdminFileManagerPicker === 'function') {
                window.openAdminFileManagerPicker({
                    imagesOnly: true,
                    onSelect(file) {
                        targetInput.value = file.url || (file.path ? (file.path.startsWith('/') || file.path.startsWith('http') ? file.path : '/storage/' + file.path) : '');
                    }
                });
            }
        }

        // Page Schema Format / Validate
        if (btn.classList.contains('js-format-page-schema')) {
            const target = document.getElementById(btn.dataset.target);
            const status = document.querySelector('.js-page-schema-status');
            if (!target) return;
            const val = target.value.trim();
            if (!val) {
                if (status) status.innerHTML = '<span class="text-muted">Empty (inherits global/template schema)</span>';
                return;
            }
            try {
                const parsed = JSON.parse(val);
                target.value = JSON.stringify(parsed, null, 2);
                if (status) status.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Valid JSON-LD</span>';
            } catch (err) {
                if (status) status.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> ' + err.message + '</span>';
            }
        }

        // Page Schema Starter Template Insert
        if (btn.classList.contains('js-insert-starter-schema')) {
            const target = document.getElementById(btn.dataset.target);
            const status = document.querySelector('.js-page-schema-status');
            if (!target) return;
            if (target.value.trim() && !confirm('This will replace the current schema content with starter template. Continue?')) {
                return;
            }
            const template = btn.dataset.template || 'service';
            let starter = {};
            if (template === 'amazon_service' || template === 'service') {
                starter = {
                    "@context": "https://schema.org",
                    "@type": "Service",
                    "name": "{title}",
                    "description": "{meta_description}",
                    "provider": {
                        "@type": "Organization",
                        "name": "Bridgeway Digital",
                        "url": "https://bridgewaydigital.com"
                    },
                    "areaServed": "Worldwide"
                };
            } else if (template === 'blog' || template === 'blogs') {
                starter = {
                    "@context": "https://schema.org",
                    "@type": "Article",
                    "headline": "{title}",
                    "description": "{meta_description}",
                    "author": {
                        "@type": "Organization",
                        "name": "Bridgeway Digital"
                    }
                };
            } else {
                starter = {
                    "@context": "https://schema.org",
                    "@type": "WebPage",
                    "name": "{title}",
                    "description": "{meta_description}",
                    "url": "{url}"
                };
            }
            target.value = JSON.stringify(starter, null, 2);
            if (status) status.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Starter schema inserted</span>';
        }
    });

    // Real-time preview updates when manually typing into source fields
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('js-cms-img-src')) {
            const widget = e.target.closest('.js-cms-image-widget');
            if (!widget) return;
            const previewImg = widget.querySelector('.js-cms-img-preview');
            const previewBox = widget.querySelector('.cms-img-preview-box');
            const placeholder = widget.querySelector('.cms-img-placeholder');
            const val = e.target.value.trim();
            if (val) {
                if (previewImg) previewImg.src = val;
                if (previewBox) previewBox.classList.remove('d-none');
                if (placeholder) placeholder.classList.add('d-none');
            } else {
                if (previewImg) previewImg.src = '';
                if (previewBox) previewBox.classList.add('d-none');
                if (placeholder) placeholder.classList.remove('d-none');
            }
        }
        if (e.target.classList.contains('js-cms-scalar-input')) {
            const group = e.target.closest('.js-cms-scalar-image-group');
            if (!group) return;
            const preview = group.querySelector('.js-cms-scalar-preview');
            const previewWrap = group.querySelector('.js-cms-scalar-preview-wrap');
            const val = e.target.value.trim();
            if (val) {
                if (preview) preview.src = val;
                if (previewWrap) previewWrap.classList.remove('d-none');
            } else {
                if (preview) preview.src = '';
                if (previewWrap) previewWrap.classList.add('d-none');
            }
        }
    });
});
</script>
@endpush
