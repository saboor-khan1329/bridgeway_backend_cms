@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="['Navigation' => route('admin.navigation.index'), ucfirst($location).' Navigation' => route('admin.navigation.show', $location), $title => null]">
    <div class="col-12">

        <div class="mb-3">
            <a href="{{ route('admin.navigation.show', $location) }}" class="btn btn-warning btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Fix the following errors:</strong>
                <ul class="mb-0 mt-2">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" enctype="multipart/form-data"
            action="{{ $item ? route('admin.navigation.items.update', [$location, $item]) : route('admin.navigation.items.store', $location) }}">
            @csrf
            @if ($item) @method('PUT') @endif

            {{-- ── ITEM TYPE ───────────────────────────────────────────────── --}}
            @if ($location === 'header')
                <input type="hidden" name="item_type" value="nav_link">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Header Placement</h5></div>
                    <div class="card-body">
                        <select name="navigation_kind" class="form-control">
                            <option value="service_group" @selected(old('navigation_kind', $item?->getMeta('kind', 'service_group')) === 'service_group')>Services mega-menu group</option>
                            <option value="header_link" @selected(old('navigation_kind', $item?->getMeta('kind')) === 'header_link')>Top-level header link</option>
                        </select>
                    </div>
                </div>
            @else
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Item Type</h5></div>
                    <div class="card-body">
                        <div class="row">
                            @php
                                $typeOptions = [
                                    'group'           => ['Group (e.g. Security Services, Company)', 'text-primary'],
                                    'locations_group' => ['Locations Group (Services Locations section)', 'text-success'],
                                    'other_link'      => ['Other Link (bottom row: Policy, T&C, Blogs)', 'text-warning'],
                                ];
                                $currentType = old('item_type', $item?->item_type ?? 'group');
                            @endphp
                            @foreach ($typeOptions as $val => [$label, $cls])
                                <div class="col-md-4 mb-2">
                                    <div class="form-check border rounded p-3 h-100 {{ $currentType === $val ? 'border-primary bg-light' : '' }}">
                                        <input class="form-check-input" type="radio" name="item_type"
                                            id="type_{{ $val }}" value="{{ $val }}"
                                            {{ $currentType === $val ? 'checked' : '' }}
                                            onchange="updateFooterForm()">
                                        <label class="form-check-label" for="type_{{ $val }}">
                                            <strong class="{{ $cls }}">{{ ucwords(str_replace('_', ' ', $val)) }}</strong>
                                            <br><small class="text-muted">{{ $label }}</small>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── LINK TYPE ───────────────────────────────────────────────── --}}
            @php
                $currentLinkType   = old('link_type', $item?->link_type ?? 'custom');
                $currentLinkableId = old('linkable_id', $item?->linkable_id);
                $linkTypeOptions = [
                    'custom'   => ['Custom URL',   'Enter any URL manually.',                             'fas fa-link',   'text-secondary'],
                    'service'  => ['Service Page', 'Link to a service — URL auto-syncs if slug changes.', 'fas fa-briefcase', 'text-primary'],
                    'category' => ['Category Page','Link to a category listing page.',                    'fas fa-sitemap','text-success'],
                    'blog'     => ['Blog Post',    'Link to a specific blog post.',                       'fas fa-blog',   'text-warning'],
                    'page'     => ['Static Page',  'Link to an editable static page.',                    'fas fa-file-alt','text-dark'],
                ];
                $ajaxUrls = [
                    'service'  => route('admin.ajax.search', 'services'),
                    'category' => route('admin.ajax.search', 'nav-categories'),
                    'blog'     => route('admin.ajax.search', 'blogs'),
                    'page'     => route('admin.ajax.search', 'pages'),
                ];
                // Pre-load label for currently linked item
                $linkedLabel = '';
                if ($item && $item->linkable_id && $item->link_type !== 'custom') {
                    $linkedLabel = match($item->link_type) {
                        'service'  => \App\Support\AdminSelectLabel::service($item->linkable),
                        'category' => $item->linkable?->name ?? '',
                        'location' => $item->linkable?->title ?? '',
                        'blog'     => $item->linkable?->title ?? '',
                        'page'     => $item->linkable?->page_title ?? '',
                        default    => '',
                    };
                }
            @endphp

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Link Type</h5></div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        @foreach ($linkTypeOptions as $val => [$label, $desc, $icon, $cls])
                            <div class="col-6 col-md-4 col-lg-2-4" style="flex:0 0 20%;max-width:20%;">
                                <div class="border rounded p-2 text-center link-type-card {{ $currentLinkType === $val ? 'border-primary bg-light' : '' }}"
                                    style="cursor:pointer;" onclick="setLinkType('{{ $val }}')">
                                    <input type="radio" name="link_type" value="{{ $val }}"
                                        id="lt_{{ $val }}" class="d-none"
                                        {{ $currentLinkType === $val ? 'checked' : '' }}>
                                    <i class="{{ $icon }} {{ $cls }} d-block mb-1" style="font-size:1.3rem;"></i>
                                    <strong class="d-block small">{{ $label }}</strong>
                                    <span class="text-muted" style="font-size:0.7rem;">{{ $desc }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Auto-sync notice for non-custom --}}
                    <div id="linkSyncNotice" class="alert alert-info py-2 small mb-0 {{ $currentLinkType === 'custom' ? 'd-none' : '' }}">
                        <i class="fas fa-sync-alt me-1"></i>
                        <strong>URL auto-synced:</strong> If the selected item's slug changes in the CMS,
                        this navigation link updates automatically — no manual edits needed.
                        The <em>Custom URL (fallback)</em> field below is only used if the linked item is deleted.
                    </div>
                </div>
            </div>

            {{-- ── LINKED ITEM SELECTOR (shown for non-custom types) ────────── --}}
            @foreach ($ajaxUrls as $ltype => $ajaxUrl)
                <div id="linked_{{ $ltype }}_wrap" class="card mb-3 {{ $currentLinkType === $ltype ? '' : 'd-none' }}">
                    <div class="card-header">
                        <h5 class="mb-0">Select {{ ucfirst($ltype) }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">
                                {{ ucfirst($ltype) }} <span class="text-danger">*</span>
                            </label>
                            <select name="linkable_id" id="linked_select_{{ $ltype }}"
                                class="form-control select2-ajax-link"
                                data-ajax-url="{{ $ajaxUrl }}"
                                data-placeholder="Search {{ $ltype }}s...">
                                @if ($currentLinkType === $ltype && $currentLinkableId && $linkedLabel)
                                    <option value="{{ $currentLinkableId }}" selected>{{ $linkedLabel }}</option>
                                @endif
                            </select>
                            <p class="text-muted small mt-1 mb-0">
                                <i class="fas fa-info-circle"></i>
                                The display title can be overridden below. The URL is resolved automatically.
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- ── BASIC FIELDS ─────────────────────────────────────────────── --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Display &amp; URL</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold">
                                Display Title <span class="text-danger">*</span>
                                <span id="titleHint" class="text-muted fw-normal {{ $currentLinkType === 'custom' ? 'd-none' : '' }}">
                                    (leave blank to use the linked item's name)
                                </span>
                            </label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                value="{{ old('title', $item?->title) }}"
                                placeholder="e.g. Security Services"
                                {{ $currentLinkType === 'custom' ? 'required' : '' }}>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4 mb-3" id="hrefWrap">
                            <label class="form-label small fw-semibold">
                                <span id="hrefLabel">URL / Path</span>
                                <span id="hrefHint" class="text-muted fw-normal d-none">
                                    (fallback only — used if linked item is deleted)
                                </span>
                            </label>
                            <input type="text" name="href" class="form-control"
                                value="{{ old('href', $item?->href) }}"
                                placeholder="/about-us or #">
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-semibold">Open In</label>
                            <select name="target" class="form-control">
                                <option value="_self"  {{ old('target', $item?->target ?? '_self') === '_self'  ? 'selected' : '' }}>Same Tab</option>
                                <option value="_blank" {{ old('target', $item?->target ?? '_self') === '_blank' ? 'selected' : '' }}>New Tab</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── MEGA MENU (header nav_link only) ──────────────────────────── --}}
            @if ($location === 'header')
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Mega Menu (optional)</h5>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="hasMegaMenu"
                                {{ (old('mega_title', $item?->getMeta('mega_title')) || $children->isNotEmpty()) ? 'checked' : '' }}
                                onchange="toggleMegaMenu(this.checked)">
                            <label class="form-check-label" for="hasMegaMenu">Has Mega Menu</label>
                        </div>
                    </div>
                    <div class="card-body" id="megaMenuPanel"
                        style="{{ (old('mega_title', $item?->getMeta('mega_title')) || $children->isNotEmpty()) ? '' : 'display:none' }}">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label small fw-semibold">Title</label>
                                <input type="text" name="mega_title" class="form-control"
                                    value="{{ old('mega_title', $item?->getMeta('mega_title')) }}"
                                    placeholder="Expert security services delivering outstanding protection outcomes.">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label small fw-semibold">Description</label>
                                <textarea name="mega_description" class="form-control" rows="2"
                                    placeholder="Our mission is to make people and places safer...">{{ old('mega_description', $item?->getMeta('mega_description')) }}</textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label small fw-semibold">Right-side Detail Text</label>
                                <input type="text" name="mega_detail" class="form-control"
                                    value="{{ old('mega_detail', $item?->getMeta('mega_detail')) }}"
                                    placeholder="Comprehensive security services protect businesses...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-semibold">Right-side Image URL</label>
                                <input type="text" name="mega_image_src" class="form-control @error('mega_image_upload') is-invalid @enderror"
                                    value="{{ old('mega_image_src', $item?->getMeta('mega_image_src')) }}"
                                    placeholder="/images/security_menu_img.webp">
                                <div class="mt-2">
                                    <label class="form-label small text-muted mb-1">Or upload an image (replaces the URL above)</label>
                                    <input type="file" name="mega_image_upload" accept="image/*"
                                        class="form-control form-control-sm @error('mega_image_upload') is-invalid @enderror">
                                    @error('mega_image_upload')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-semibold">Image Alt</label>
                                <input type="text" name="mega_image_alt" class="form-control"
                                    value="{{ old('mega_image_alt', $item?->getMeta('mega_image_alt')) }}"
                                    placeholder="mega-menu-img">
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── CHILD LINKS ──────────────────────────────────────────────── --}}
            @php
                $showChildren = $location === 'header'
                    || in_array($item?->item_type ?? 'group', ['group', 'locations_group']);
                $childLabel = $location === 'header' ? 'Sub-Links (Mega Menu)' : 'Links in this Section';
            @endphp

            <div class="card mb-3" id="childrenCard" style="{{ $showChildren ? '' : 'display:none' }}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $childLabel }}</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addChildBtn">
                        <i class="fas fa-plus"></i> Add Link
                    </button>
                </div>
                <div class="card-body p-2">
                    <div id="childrenList">
                        @php
                            $isHeader = $location === 'header';
                            // If old() values exist (form re-submitted), use those; else use saved children
                            $hasSavedOld = !empty(old('child_title'));
                        @endphp

                        @if ($hasSavedOld)
                            @foreach (old('child_title', []) as $ci => $childTitle)
                                @php
                                    $cLinkType   = old('child_link_type.'.$ci, 'custom');
                                    $cLinkableId = old('child_linkable_id.'.$ci);
                                @endphp
                                <x-nav-child-row
                                    :index="$ci"
                                    :is-header="$isHeader"
                                    :title="$childTitle"
                                    :href="old('child_href.'.$ci)"
                                    :link-type="$cLinkType"
                                    :linkable-id="$cLinkableId"
                                    :linkable-label="''"
                                    :icon-src="old('child_icon_src.'.$ci)"
                                    :icon-alt="old('child_icon_alt.'.$ci)"
                                />
                            @endforeach
                        @else
                            @foreach ($children as $ci => $child)
                                @php
                                    $cLabel = '';
                                    if ($child->link_type !== 'custom' && $child->linkable) {
                                        $cLabel = match($child->link_type) {
                                            'service'  => \App\Support\AdminSelectLabel::service($child->linkable),
                                            'category' => $child->linkable?->name ?? '',
                                            'page'     => $child->linkable?->page_title ?? '',
                                            default    => $child->linkable?->title ?? '',
                                        };
                                    }
                                @endphp
                                <x-nav-child-row
                                    :index="$ci"
                                    :is-header="$isHeader"
                                    :title="$child->title"
                                    :href="$child->href"
                                    :link-type="$child->link_type ?? 'custom'"
                                    :linkable-id="$child->linkable_id"
                                    :linkable-label="$cLabel"
                                    :icon-src="$child->getMeta('icon_src')"
                                    :icon-alt="$child->getMeta('icon_alt')"
                                />
                            @endforeach
                        @endif
                    </div>
                    <p class="text-muted small mt-2 mb-0 px-2">
                        <i class="fas fa-info-circle"></i> All sub-links save together with this item.
                    </p>
                </div>
            </div>

            {{-- ── SUBMIT ───────────────────────────────────────────────────── --}}
            <div class="text-end">
                <a href="{{ route('admin.navigation.show', $location) }}" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> {{ $item ? 'Update' : 'Save' }}
                </button>
            </div>

        </form>
    </div>
</x-layouts.page-wrapper>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
var IS_HEADER = {{ $location === 'header' ? 'true' : 'false' }};
var AJAX_URLS = @json($ajaxUrls);
var ICON_LIBRARY = @json(\App\Support\IconLibrary::all());

// ── Link type selector ──────────────────────────────────────────────────────
function setLinkType(type) {
    document.querySelectorAll('input[name="link_type"]').forEach(r => r.checked = (r.value === type));
    document.querySelectorAll('.link-type-card').forEach(c => {
        c.classList.remove('border-primary', 'bg-light');
    });
    document.querySelector('.link-type-card:has(#lt_' + type + ')') &&
        document.getElementById('lt_' + type).closest('.link-type-card')
            .classList.add('border-primary', 'bg-light');

    // Show/hide linked item selectors
    ['service', 'category', 'location', 'blog', 'page'].forEach(t => {
        var wrap = document.getElementById('linked_' + t + '_wrap');
        if (wrap) wrap.classList.toggle('d-none', t !== type);
    });

    var isCustom = type === 'custom';
    var notice = document.getElementById('linkSyncNotice');
    if (notice) notice.classList.toggle('d-none', isCustom);

    var titleHint = document.getElementById('titleHint');
    if (titleHint) titleHint.classList.toggle('d-none', isCustom);

    var hrefHint  = document.getElementById('hrefHint');
    var hrefLabel = document.getElementById('hrefLabel');
    if (hrefHint && hrefLabel) {
        hrefHint.classList.toggle('d-none', isCustom);
        hrefLabel.textContent = isCustom ? 'URL / Path' : 'Custom URL (fallback)';
    }

    // Re-init Select2 for the visible selector
    if (!isCustom && window.$ && $.fn.select2) {
        var $sel = $('#linked_select_' + type);
        if (!$sel.data('select2')) {
            $sel.select2({
                theme: 'bootstrap-5',
                width: '100%',
                minimumInputLength: 0,
                ajax: {
                    url: AJAX_URLS[type],
                    dataType: 'json',
                    delay: 200,
                    data: params => ({ q: params.term || '', limit: 25 }),
                    processResults: data => ({ results: data.results || [] }),
                    cache: true,
                },
            });
        }
    }
}

// ── Mega menu toggle ────────────────────────────────────────────────────────
window.toggleMegaMenu = function (show) {
    document.getElementById('megaMenuPanel').style.display = show ? '' : 'none';
};

// ── Footer item type change ─────────────────────────────────────────────────
window.updateFooterForm = function () {
    var type = document.querySelector('input[name="item_type"]:checked')?.value;
    var card = document.getElementById('childrenCard');
    if (card) card.style.display = (type === 'group' || type === 'locations_group') ? '' : 'none';
};

// ── Child row builder — matches nav-child-row component structure exactly ─────
// ONE title[], ONE href[], ONE linkable_id[], ONE link_type[] per row — arrays stay aligned
function buildChildRow() {
    var div = document.createElement('div');
    div.className = 'child-row border rounded p-3 mb-2';
    div.style.background = '#f8f9fa';

    var ajaxUrlsJson = JSON.stringify(AJAX_URLS).replace(/'/g, '&#39;');
    var colUrl  = IS_HEADER ? '2' : '4';
    var colLinked = IS_HEADER ? '2' : '4';
    var colTitle = IS_HEADER ? '2' : '3';

    var iconOptions = '<option value="">Default (no icon)</option>' +
        ICON_LIBRARY.map(function (icon) {
            return '<option value="' + icon.value + '">' + icon.label + '</option>';
        }).join('');

    var iconHTML = IS_HEADER
        ? `<div class="col-md-2">
               <label class="form-label small">Icon</label>
               <select name="child_icon_src[]" class="form-control form-control-sm icon-library-select">${iconOptions}</select>
           </div>
           <div class="col-md-2">
               <label class="form-label small">Icon Alt</label>
               <input type="text" name="child_icon_alt[]" class="form-control form-control-sm"
                   placeholder="bell-icon">
           </div>`
        : `<input type="hidden" name="child_icon_src[]" value="">
           <input type="hidden" name="child_icon_alt[]" value="">`;

    div.innerHTML = `
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="drag-handle text-muted" style="cursor:grab;user-select:none;">&#8942;&#8942;</span>
            <button type="button" class="btn btn-outline-danger remove-child"
                style="padding:2px 8px;font-size:0.75rem;">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        <div class="row g-2 align-items-start">
            <div class="col-md-2">
                <label class="form-label small">Link Type</label>
                <select name="child_link_type[]"
                    class="form-control form-control-sm child-link-type-select"
                    data-ajax-urls='${ajaxUrlsJson}'>
                    <option value="custom">Custom URL</option>
                    <option value="service">Service</option>
                    <option value="category">Category</option>
                    <option value="location">Location</option>
                    <option value="blog">Blog</option>
                    <option value="page">Static Page</option>
                </select>
            </div>
            <div class="col-md-${colTitle}">
                <label class="form-label small">Display Title <span class="text-danger">*</span></label>
                <input type="text" name="child_title[]" class="form-control form-control-sm"
                    placeholder="Link title">
            </div>
            <div class="col-md-${colUrl} child-url-wrap">
                <label class="form-label small">URL <span class="text-danger">*</span></label>
                <input type="text" name="child_href[]" class="form-control form-control-sm"
                    placeholder="/page-url or #">
            </div>
            <div class="col-md-${colLinked} child-linked-wrap d-none">
                <label class="form-label small">Linked Item <span class="text-danger">*</span></label>
                <input type="hidden" name="child_linkable_id[]" class="child-linkable-id-input" value="">
                <select class="form-control form-control-sm child-linked-select" data-placeholder="Search...">
                    <option value=""></option>
                </select>
            </div>
            ${iconHTML}
        </div>`;
    return div;
}

document.getElementById('addChildBtn')?.addEventListener('click', function () {
    var list = document.getElementById('childrenList');
    var row  = buildChildRow();
    list.appendChild(row);
    if (typeof window.initChildNavRow === 'function') window.initChildNavRow(row);
});

document.getElementById('childrenList')?.addEventListener('click', function (e) {
    if (e.target.closest('.remove-child')) e.target.closest('.child-row').remove();
});

// ── Sortable children ─────────────────────────────────────────────────────────
var childList = document.getElementById('childrenList');
if (childList && typeof Sortable !== 'undefined') {
    Sortable.create(childList, { handle: '.drag-handle', animation: 150 });
}

// ── Init parent-item linked-item Select2 dropdowns on page load ───────────────
document.addEventListener('DOMContentLoaded', function () {
    if (!window.$ || !$.fn.select2) return;

    ['service', 'category', 'location', 'blog', 'page'].forEach(function (type) {
        var wrap = document.getElementById('linked_' + type + '_wrap');
        if (!wrap || wrap.classList.contains('d-none')) return;
        $('#linked_select_' + type).select2({
            theme: 'bootstrap-5', width: '100%', minimumInputLength: 0,
            ajax: {
                url: AJAX_URLS[type], dataType: 'json', delay: 200,
                data: p => ({ q: p.term || '', limit: 25 }),
                processResults: d => ({ results: d.results || [] }),
                cache: true,
            },
        });
    });
});
</script>
@endpush
