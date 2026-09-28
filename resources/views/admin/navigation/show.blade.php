@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="['Navigation' => route('admin.navigation.index'), $title => null]">
    <div class="col-12">

        {{-- Back --}}
        <div class="mb-3">
            <a href="{{ route('admin.navigation.index') }}" class="btn btn-warning btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Navigation
            </a>
        </div>

        {{-- ── SETTINGS CARD ──────────────────────────────────────────────── --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-cog me-1"></i>
                    {{ ucfirst($location) }} Settings
                </h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#settingsPanel" aria-expanded="false">
                    Toggle
                </button>
            </div>
            <div class="collapse" id="settingsPanel">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.navigation.settings', $location) }}">
                        @csrf
                        @method('PUT')

                        @if ($location === 'header')
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Logo URL / Path</label>
                                    <input type="text" name="logo_src" class="form-control @error('logo_upload') is-invalid @enderror"
                                        value="{{ old('logo_src', $menu->getMeta('logo_src')) }}"
                                        placeholder="/images/logo.svg">
                                    <div class="mt-2">
                                        <label class="form-label small text-muted mb-1">Or upload a logo (replaces the URL above)</label>
                                        <input type="file" name="logo_upload" accept="image/*"
                                            class="form-control form-control-sm @error('logo_upload') is-invalid @enderror">
                                        @error('logo_upload')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Logo Alt Text</label>
                                    <input type="text" name="logo_alt" class="form-control"
                                        value="{{ old('logo_alt', $menu->getMeta('logo_alt', 'header-logo')) }}"
                                        placeholder="header-logo">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">CTA Button Text</label>
                                    <input type="text" name="cta_text" class="form-control"
                                        value="{{ old('cta_text', $menu->getMeta('cta_text')) }}"
                                        placeholder="Contact Us">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">CTA Button URL</label>
                                    <input type="text" name="cta_href" class="form-control"
                                        value="{{ old('cta_href', $menu->getMeta('cta_href')) }}"
                                        placeholder="/contact-us">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Mega Menu Title</label>
                                    <input type="text" name="services_title" class="form-control" value="{{ old('services_title', $menu->getMeta('services.title', 'Services')) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Mega Menu Description</label>
                                    <input type="text" name="services_description" class="form-control" value="{{ old('services_description', $menu->getMeta('services.description')) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Portfolio Title</label>
                                    <input type="text" name="portfolio_title" class="form-control" value="{{ old('portfolio_title', $menu->getMeta('portfolio.title')) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Portfolio Image Path</label>
                                    <input type="text" name="portfolio_image_src" class="form-control" value="{{ old('portfolio_image_src', $menu->getMeta('portfolio.image.src')) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Portfolio Image Alt</label>
                                    <input type="text" name="portfolio_image_alt" class="form-control" value="{{ old('portfolio_image_alt', $menu->getMeta('portfolio.image.alt')) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Portfolio URL</label>
                                    <input type="text" name="portfolio_href" class="form-control" value="{{ old('portfolio_href', $menu->getMeta('portfolio.href')) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Portfolio CTA Text</label>
                                    <input type="text" name="portfolio_cta_text" class="form-control" value="{{ old('portfolio_cta_text', $menu->getMeta('portfolio.ctaText')) }}">
                                </div>
                            </div>
                        @else
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Footer Logo URL / Path</label>
                                    <input type="text" name="logo_src" class="form-control @error('logo_upload') is-invalid @enderror"
                                        value="{{ old('logo_src', $menu->getMeta('logo_src')) }}"
                                        placeholder="/images/footer_logo.svg">
                                    <div class="mt-2">
                                        <label class="form-label small text-muted mb-1">Or upload a logo (replaces the URL above)</label>
                                        <input type="file" name="logo_upload" accept="image/*"
                                            class="form-control form-control-sm @error('logo_upload') is-invalid @enderror">
                                        @error('logo_upload')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Logo Alt Text</label>
                                    <input type="text" name="logo_alt" class="form-control"
                                        value="{{ old('logo_alt', $menu->getMeta('logo_alt', 'footer-logo')) }}"
                                        placeholder="footer-logo">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label small fw-semibold">Tagline / Detail</label>
                                    <input type="text" name="detail" class="form-control"
                                        value="{{ old('detail', $menu->getMeta('detail')) }}"
                                        placeholder="Making people and places the best they can be.">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-semibold">Copyright Text</label>
                                    <input type="text" name="rights" class="form-control"
                                        value="{{ old('rights', $menu->getMeta('rights')) }}"
                                        placeholder="© 2026 BridgeWay Digital. All Rights Reserved">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label small fw-semibold">Company Info Label</label>
                                    <input type="text" name="info_label" class="form-control"
                                        value="{{ old('info_label', $menu->getMeta('info_label')) }}"
                                        placeholder="Company Reg No.">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label small fw-semibold">Company Info Text</label>
                                    <input type="text" name="info_text" class="form-control"
                                        value="{{ old('info_text', $menu->getMeta('info_text')) }}"
                                        placeholder="11495442 Registered. in England & Wales">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label small fw-semibold">Google Rating</label>
                                    <input type="number" step="0.1" min="0" max="5" name="google_rating" class="form-control"
                                        value="{{ old('google_rating', $menu->getMeta('google_rating')) }}"
                                        placeholder="4.8">
                                    <small class="text-muted">Leave blank to hide the review badge.</small>
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label class="form-label small fw-semibold">Google Review URL</label>
                                    <input type="text" name="google_review_url" class="form-control"
                                        value="{{ old('google_review_url', $menu->getMeta('google_review_url')) }}"
                                        placeholder="https://share.google/...">
                                </div>
                                <div class="col-12">
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-info-circle"></i>
                                        Contact details, copyright and social media URLs are managed in
                                        <a href="{{ route('admin.settings.edit') }}">Site Settings</a>.
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="text-end mt-2">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ── NAV ITEMS CARD ─────────────────────────────────────────────── --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-1"></i>
                    @if ($location === 'header') Nav Links @else Link Sections @endif
                    <span class="badge bg-secondary ms-2">{{ $rootItems->count() }}</span>
                </h5>
                <a href="{{ route('admin.navigation.items.create', $location) }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Item
                </a>
            </div>
            <div class="card-body p-0">

                @if ($rootItems->isEmpty())
                    <div class="p-4 text-center text-muted">
                        No items yet. <a href="{{ route('admin.navigation.items.create', $location) }}">Add one.</a>
                    </div>
                @else
                    <ul class="list-group list-group-flush" id="sortable-root" data-location="{{ $location }}" data-parent-id="">
                        @foreach ($rootItems as $item)
                            <li class="list-group-item px-3 py-2" data-id="{{ $item->id }}">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="drag-handle text-muted mt-1" style="cursor:grab; font-size:1.1rem;">&#8942;&#8942;</span>

                                    <div class="flex-grow-1">
                                        {{-- Item header row --}}
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge bg-secondary text-uppercase" style="font-size:0.65rem;">
                                                {{ str_replace('_', ' ', $item->item_type) }}
                                            </span>
                                            @php
                                                $lt = $item->link_type ?? 'custom';
                                                $ltColors = ['custom'=>'bg-light text-dark border','service'=>'bg-primary','category'=>'bg-success','location'=>'bg-info text-dark','blog'=>'bg-warning text-dark'];
                                            @endphp
                                            @if ($lt !== 'custom')
                                                <span class="badge {{ $ltColors[$lt] ?? 'bg-secondary' }}" style="font-size:0.65rem;">
                                                    <i class="fas fa-sync-alt me-1" style="font-size:0.55rem;"></i>{{ ucfirst($lt) }}
                                                </span>
                                            @endif
                                            <strong>{{ $item->title }}</strong>
                                            @if ($lt === 'custom' && $item->href)
                                                <span class="text-muted small">{{ $item->href }}</span>
                                            @elseif ($lt !== 'custom')
                                                <span class="text-muted small fst-italic">URL auto-resolved</span>
                                            @endif
                                            @if ($location === 'header')
                                                @if ($item->getMeta('mega_title') || $item->children->isNotEmpty())
                                                    <span class="badge bg-info text-dark" style="font-size:0.65rem;">Mega Menu</span>
                                                @endif
                                            @endif
                                            <span class="ms-auto d-flex gap-2">
                                                <a href="{{ route('admin.navigation.items.edit', [$location, $item]) }}"
                                                    class="btn btn-xs btn-outline-primary" style="padding:2px 8px;font-size:0.75rem;">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form method="POST"
                                                    action="{{ route('admin.navigation.items.destroy', [$location, $item]) }}"
                                                    onsubmit="return confirm('Delete this item and all its sub-items?')"
                                                    class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-outline-danger"
                                                        style="padding:2px 8px;font-size:0.75rem;">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </span>
                                        </div>

                                        {{-- Sub-items --}}
                                        @if ($item->children->isNotEmpty())
                                            <ul class="list-unstyled ms-4 mt-2 mb-1 sortable-children"
                                                data-location="{{ $location }}"
                                                data-parent-id="{{ $item->id }}">
                                                @foreach ($item->children as $child)
                                                    <li class="d-flex align-items-center gap-2 py-1 border-bottom text-sm"
                                                        data-id="{{ $child->id }}">
                                                        <span class="drag-handle text-muted" style="cursor:grab;">&#8942;</span>
                                                        <small>
                                                            @if ($child->getMeta('icon_src'))
                                                                <i class="fas fa-image text-muted me-1"></i>
                                                            @endif
                                                            <strong>{{ $child->title }}</strong>
                                                            @if ($child->href)
                                                                <span class="text-muted">— {{ $child->href }}</span>
                                                            @endif
                                                        </small>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @if ($rootItems->isNotEmpty())
                <div class="card-footer text-muted small">
                    <i class="fas fa-info-circle"></i>
                    Drag items to reorder. Changes save automatically.
                </div>
            @endif
        </div>

    </div>
</x-layouts.page-wrapper>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    function saveOrder(el, items, parentId) {
        const location = el.dataset.location;
        fetch('{{ url("admin/navigation") }}/' + location + '/reorder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ items: items, parent_id: parentId || null }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Order saved.', 'success');
            } else {
                showToast('Failed to save order.', 'danger');
            }
        })
        .catch(() => showToast('Failed to save order.', 'danger'));
    }

    // Root list
    const rootEl = document.getElementById('sortable-root');
    if (rootEl) {
        Sortable.create(rootEl, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                const ids = [...rootEl.querySelectorAll(':scope > li[data-id]')]
                    .map(li => parseInt(li.dataset.id));
                saveOrder(rootEl, ids, null);
            }
        });
    }

    // Sub-item lists
    document.querySelectorAll('.sortable-children').forEach(function (el) {
        Sortable.create(el, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                const parentId = parseInt(el.dataset.parentId);
                const ids = [...el.querySelectorAll(':scope > li[data-id]')]
                    .map(li => parseInt(li.dataset.id));
                saveOrder(el, ids, parentId);
            }
        });
    });

    function showToast(msg, type) {
        const div = document.createElement('div');
        div.className = 'alert alert-' + type + ' position-fixed bottom-0 end-0 m-3 py-2 px-3';
        div.style.zIndex = 9999;
        div.style.minWidth = '200px';
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 2500);
    }
});
</script>
@endpush
