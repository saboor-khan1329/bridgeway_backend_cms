@props([
    'index',
    'isHeader'      => false,
    'title'         => '',
    'href'          => '',
    'linkType'      => 'custom',
    'linkableId'    => null,
    'linkableLabel' => '',
    'iconSrc'       => '',
    'iconAlt'       => '',
])

@php
    $isCustom  = ($linkType === 'custom');
    $ajaxUrls  = [
        'service'  => route('admin.ajax.search', 'services'),
        'category' => route('admin.ajax.search', 'nav-categories'),
        'location' => route('admin.ajax.search', 'locations'),
        'blog'     => route('admin.ajax.search', 'blogs'),
        'page'     => route('admin.ajax.search', 'pages'),
    ];
    $rowId = 'child_row_' . $index . '_' . uniqid();

    $iconLibrary    = \App\Support\IconLibrary::all();
    // A previously saved path outside the library is kept as a custom option
    // so existing data is never lost.
    $isCustomIcon   = $iconSrc && ! \App\Support\IconLibrary::has($iconSrc);
@endphp

<div class="child-row border rounded p-3 mb-2" style="background:#f8f9fa;" data-row-id="{{ $rowId }}">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="drag-handle text-muted" style="cursor:grab; user-select:none;">&#8942;&#8942;</span>
        <button type="button" class="btn btn-outline-danger remove-child"
            style="padding:2px 8px;font-size:0.75rem;">
            <i class="fas fa-trash"></i> Remove
        </button>
    </div>

    <div class="row g-2 align-items-start">

        {{-- Link type --}}
        <div class="col-md-2">
            <label class="form-label small">Link Type</label>
            <select name="child_link_type[]"
                class="form-control form-control-sm child-link-type-select"
                data-row="{{ $rowId }}"
                data-ajax-urls="{{ json_encode($ajaxUrls) }}">
                <option value="custom"   {{ $linkType === 'custom'   ? 'selected' : '' }}>Custom URL</option>
                <option value="service"  {{ $linkType === 'service'  ? 'selected' : '' }}>Service</option>
                <option value="category" {{ $linkType === 'category' ? 'selected' : '' }}>Category</option>
                <option value="location" {{ $linkType === 'location' ? 'selected' : '' }}>Location</option>
                <option value="blog"     {{ $linkType === 'blog'     ? 'selected' : '' }}>Blog</option>
                <option value="page"     {{ $linkType === 'page'     ? 'selected' : '' }}>Static Page</option>
            </select>
        </div>

        {{-- Display title — always one input per row --}}
        <div class="col-md-{{ $isHeader ? 2 : 3 }}">
            <label class="form-label small">
                Display Title
                <span class="child-title-req text-danger">{{ $isCustom ? '*' : '' }}</span>
            </label>
            <input type="text" name="child_title[]"
                class="form-control form-control-sm"
                value="{{ $title }}"
                placeholder="{{ $isCustom ? 'Link title' : 'Override name (optional)' }}">
        </div>

        {{-- Custom URL field — always in DOM, hidden for non-custom so it submits empty (array stays aligned) --}}
        <div class="col-md-{{ $isHeader ? 2 : 4 }} child-url-wrap {{ $isCustom ? '' : 'd-none' }}">
            <label class="form-label small">URL <span class="text-danger">*</span></label>
            <input type="text" name="child_href[]"
                class="form-control form-control-sm"
                value="{{ $href }}"
                placeholder="/page-url or #">
        </div>

        {{-- Linked item select — shown for non-custom types --}}
        <div class="col-md-{{ $isHeader ? 2 : 4 }} child-linked-wrap {{ $isCustom ? 'd-none' : '' }}">
            <label class="form-label small">
                Linked Item
                <span class="text-danger">{{ ! $isCustom ? '*' : '' }}</span>
            </label>
            <input type="hidden" name="child_linkable_id[]"
                class="child-linkable-id-input"
                value="{{ $linkableId ?? '' }}">
            <select class="form-control form-control-sm child-linked-select"
                data-ajax-url="{{ $ajaxUrls[$linkType] ?? '' }}"
                data-placeholder="Search {{ $linkType }}s...">
                @if ($linkableId && $linkableLabel)
                    <option value="{{ $linkableId }}" selected>{{ $linkableLabel }}</option>
                @endif
            </select>
        </div>

        @if ($isHeader)
            {{-- Icon — visual library picker; "Default" keeps current frontend behaviour --}}
            <div class="col-md-2">
                <label class="form-label small">Icon</label>
                <select name="child_icon_src[]" class="form-control form-control-sm icon-library-select">
                    <option value="">Default (no icon)</option>
                    @foreach ($iconLibrary as $icon)
                        <option value="{{ $icon['value'] }}" {{ $iconSrc === $icon['value'] ? 'selected' : '' }}>
                            {{ $icon['label'] }}
                        </option>
                    @endforeach
                    @if ($isCustomIcon)
                        <option value="{{ $iconSrc }}" selected>Custom: {{ $iconSrc }}</option>
                    @endif
                </select>
            </div>
            {{-- Icon Alt --}}
            <div class="col-md-2">
                <label class="form-label small">Icon Alt</label>
                <input type="text" name="child_icon_alt[]"
                    class="form-control form-control-sm"
                    value="{{ $iconAlt }}"
                    placeholder="bell-icon">
            </div>
        @else
            {{-- Always emit icon arrays for alignment even for footer --}}
            <input type="hidden" name="child_icon_src[]" value="">
            <input type="hidden" name="child_icon_alt[]" value="">
        @endif

    </div>{{-- /row --}}
</div>

@once
@push('scripts')
<script>
(function () {
    function initChildRow(row) {
        var typeSelect  = row.querySelector('.child-link-type-select');
        var urlWrap     = row.querySelector('.child-url-wrap');
        var linkedWrap  = row.querySelector('.child-linked-wrap');
        var linkedSel   = row.querySelector('.child-linked-select');
        var hiddenId    = row.querySelector('.child-linkable-id-input');

        if (! typeSelect) return;

        // Init Select2 on the linked selector if it has a pre-selected value
        if (linkedSel && hiddenId && hiddenId.value && window.$) {
            var ajaxUrl = linkedSel.dataset.ajaxUrl;
            if (ajaxUrl) {
                $(linkedSel).select2({
                    theme: 'bootstrap-5', width: '100%', minimumInputLength: 0,
                    ajax: {
                        url: ajaxUrl, dataType: 'json', delay: 200,
                        data: p => ({ q: p.term || '', limit: 25 }),
                        processResults: d => ({ results: d.results || [] }),
                        cache: true,
                    },
                }).on('select2:select', function (e) {
                    if (hiddenId) hiddenId.value = e.params.data.id;
                });
            }
        }

        typeSelect.addEventListener('change', function () {
            var type     = this.value;
            var isCustom = type === 'custom';
            if (urlWrap)    urlWrap.classList.toggle('d-none', ! isCustom);
            if (linkedWrap) linkedWrap.classList.toggle('d-none', isCustom);
            if (hiddenId && isCustom) hiddenId.value = '';

            // Update linked Select2 AJAX URL
            if (! isCustom && linkedSel && window.$) {
                var ajaxUrls = {};
                try { ajaxUrls = JSON.parse(typeSelect.dataset.ajaxUrls || '{}'); } catch (e) {}
                var newUrl = ajaxUrls[type] || '';

                if ($(linkedSel).data('select2')) {
                    $(linkedSel).select2('destroy');
                }
                if (newUrl) {
                    linkedSel.dataset.ajaxUrl = newUrl;
                    $(linkedSel).select2({
                        theme: 'bootstrap-5', width: '100%', minimumInputLength: 0,
                        ajax: {
                            url: newUrl, dataType: 'json', delay: 200,
                            data: p => ({ q: p.term || '', limit: 25 }),
                            processResults: d => ({ results: d.results || [] }),
                            cache: true,
                        },
                    }).on('select2:select', function (e) {
                        if (hiddenId) hiddenId.value = e.params.data.id;
                    });
                }
            }
        });
    }

    // ── Visual icon picker (Select2 with SVG previews from the frontend) ──
    var ICON_PREVIEW_BASE = @json(rtrim((string) config('app.frontend_url', ''), '/'));

    function iconOptionTemplate(option) {
        if (! option.id) return option.text;
        var img = ICON_PREVIEW_BASE
            ? '<img src="' + ICON_PREVIEW_BASE + option.id + '" style="width:18px;height:18px;margin-right:8px;vertical-align:middle;" onerror="this.style.display=\'none\'">'
            : '';
        return $('<span>' + img + option.text + '</span>');
    }

    function initIconSelect(select) {
        if (! window.$ || ! $.fn.select2 || select.dataset.iconSelectBound === '1') return;
        select.dataset.iconSelectBound = '1';
        $(select).select2({
            theme: 'bootstrap-5',
            width: '100%',
            minimumResultsForSearch: 5,
            templateResult: iconOptionTemplate,
            templateSelection: iconOptionTemplate,
        });
    }

    // Init existing rows on page load
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.child-row').forEach(initChildRow);
        document.querySelectorAll('.icon-library-select').forEach(initIconSelect);
    });

    // Expose so JS-created rows can also be initted
    window.initChildNavRow = function (row) {
        initChildRow(row);
        row.querySelectorAll('.icon-library-select').forEach(initIconSelect);
    };
})();
</script>
@endpush
@endonce
