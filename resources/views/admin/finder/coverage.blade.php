@extends('template.main')
@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title"
        :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Areas' => route('admin.finder-areas.index'), $area->name => null]">
        @include('admin.finder.partials.nav')

        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <a href="{{ route('admin.finder-areas.index') }}" class="btn btn-warning btn-sm">
                    <i class="fa fa-arrow-left"></i> Back to areas
                </a>
                <div class="small text-muted sf-page-meta">
                    {{ number_format($area->active_sites_count) }} site(s) counted here
                    <span class="d-none d-sm-inline">·</span>
                    <span class="d-block d-sm-inline">
                        prefixes {{ implode(', ', (array) $area->postcode_areas) ?: '—' }}
                    </span>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="alert alert-info py-2">
                <i class="fa fa-circle-info me-1"></i>
                These are the services the website advertises for <strong>{{ $area->name }}</strong>.
                Site counts come from your imported data; everything else is yours to write.
                One service should be <strong>featured</strong> — that is the one the map popup promotes.
            </div>

            <form method="POST" action="{{ route('admin.finder-areas.coverage.update', $area) }}" id="coverage-form">
                @csrf @method('PUT')
                <input type="hidden" name="coverage_json" id="coverage-json">

                <div class="card">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                        <h3 class="card-title mb-0 me-auto">Services offered in {{ $area->name }}</h3>
                        <select id="service-picker" class="form-select form-select-sm" style="max-width: 340px;">
                            <option value="">Add a service…</option>
                            @foreach (collect($catalog)->groupBy('category') as $category => $services)
                                <optgroup label="{{ $category }}">
                                    @foreach ($services as $service)
                                        <option value="{{ $service['id'] }}"
                                                data-title="{{ $service['title'] }}"
                                                data-card="{{ $service['card_description'] }}">
                                            {{ $service['title'] }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-success" id="add-service">
                            <i class="fa fa-plus"></i> Add
                        </button>
                    </div>

                    <div class="card-body" id="coverage-rows">
                        <p class="text-muted mb-0" id="coverage-empty">
                            No services yet. Pick one above to start advertising cover here.
                        </p>
                    </div>

                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i> Save coverage
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </x-layouts.page-wrapper>
@endsection

@push('scripts')
<script>
(function () {
    // The editor keeps its state in one array and re-renders from it; the
    // hidden textarea is only written at submit time. Per-row fields (a
    // description, a cross-sell list, a featured flag) cannot be expressed
    // through the shared CRUD form, which is why this screen is bespoke.
    const CATALOG = @json($catalog);
    const TITLES = Object.fromEntries(CATALOG.map(s => [s.id, s.title]));
    let rows = @json($rows);

    const container = document.getElementById('coverage-rows');
    const empty     = document.getElementById('coverage-empty');
    const picker    = document.getElementById('service-picker');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    function render() {
        empty.style.display = rows.length ? 'none' : 'block';
        container.querySelectorAll('.coverage-row').forEach(el => el.remove());

        rows.forEach((row, index) => {
            const related = CATALOG
                .filter(s => s.id !== row.service_id)
                .map(s => `<option value="${s.id}" ${(row.related_service_ids || []).includes(s.id) ? 'selected' : ''}>
                             ${escapeHtml(s.title)}</option>`)
                .join('');

            const el = document.createElement('div');
            el.className = 'coverage-row border rounded p-3 mb-3';
            el.innerHTML = `
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <strong class="me-auto">${escapeHtml(row.title || TITLES[row.service_id] || 'Service')}</strong>
                    ${row.sites_count > 0
                        ? `<span class="badge bg-info" title="Sites counted from your imported data">${row.sites_count} site(s)</span>`
                        : '<span class="badge bg-secondary" title="Added by hand — no imported sites yet">manual</span>'}
                    <label class="btn btn-sm ${row.is_featured ? 'btn-danger' : 'btn-outline-danger'} mb-0">
                        <input type="radio" name="featured" class="d-none" data-index="${index}" ${row.is_featured ? 'checked' : ''}>
                        <i class="fa fa-star"></i> ${row.is_featured ? 'Featured' : 'Feature'}
                    </label>
                    <label class="btn btn-sm ${row.is_active ? 'btn-outline-success' : 'btn-outline-secondary'} mb-0">
                        <input type="checkbox" class="d-none js-active" data-index="${index}" ${row.is_active ? 'checked' : ''}>
                        ${row.is_active ? 'Visible' : 'Hidden'}
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-move" data-index="${index}" data-dir="-1"
                            ${index === 0 ? 'disabled' : ''} title="Move up"><i class="fa fa-arrow-up"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-move" data-index="${index}" data-dir="1"
                            ${index === rows.length - 1 ? 'disabled' : ''} title="Move down"><i class="fa fa-arrow-down"></i></button>
                    <button type="button" class="btn btn-sm btn-danger js-remove" data-index="${index}" title="Stop advertising this service here">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
                <div class="row g-2">
                    <div class="col-md-7">
                        <label class="form-label small text-muted mb-1">Description shown on the result card</label>
                        <textarea class="form-control form-control-sm js-description" rows="2" data-index="${index}"
                                  placeholder="${escapeHtml(row.card_description || 'Leave blank to use the service’s own description.')}"
                                  >${escapeHtml(row.description || '')}</textarea>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted mb-1">Related services to suggest</label>
                        <select class="form-select form-select-sm select2 js-related" data-index="${index}" multiple>${related}</select>
                    </div>
                </div>`;
            container.appendChild(el);
        });

        // Re-initialise Select2 on the freshly rendered multi-selects.
        if (window.jQuery && jQuery.fn.select2) {
            jQuery(container).find('.js-related').select2({ width: '100%', placeholder: 'None' });
        }
    }

    container.addEventListener('click', (event) => {
        const remove = event.target.closest('.js-remove');
        const move   = event.target.closest('.js-move');

        if (remove) {
            const index = Number(remove.dataset.index);
            if (rows[index].sites_count > 0 &&
                !confirm(`${rows[index].title} has ${rows[index].sites_count} site(s) here. Remove it anyway?`)) {
                return;
            }
            rows.splice(index, 1);
            render();
        }

        if (move) {
            const index = Number(move.dataset.index);
            const target = index + Number(move.dataset.dir);
            if (target >= 0 && target < rows.length) {
                [rows[index], rows[target]] = [rows[target], rows[index]];
                render();
            }
        }
    });

    container.addEventListener('change', (event) => {
        const index = Number(event.target.dataset.index);
        if (Number.isNaN(index)) return;

        if (event.target.name === 'featured') {
            rows.forEach((row, i) => { row.is_featured = (i === index); });
            render();
        }
        if (event.target.classList.contains('js-active')) {
            rows[index].is_active = event.target.checked;
            render();
        }
    });

    container.addEventListener('input', (event) => {
        if (event.target.classList.contains('js-description')) {
            rows[Number(event.target.dataset.index)].description = event.target.value;
        }
    });

    document.getElementById('add-service').addEventListener('click', () => {
        const option = picker.selectedOptions[0];
        const id = Number(picker.value);
        if (!id) return;

        if (rows.some(row => row.service_id === id)) {
            alert('That service is already listed for this area.');
            return;
        }

        rows.push({
            service_id: id,
            title: option.dataset.title,
            card_description: option.dataset.card || '',
            description: '',
            related_service_ids: [],
            is_featured: rows.length === 0,
            is_active: true,
            sites_count: 0,
        });
        picker.value = '';
        render();
    });

    document.getElementById('coverage-form').addEventListener('submit', () => {
        // Multi-selects are read at submit time rather than tracked on every
        // change, which keeps Select2's own events out of the state loop.
        container.querySelectorAll('.js-related').forEach(select => {
            const index = Number(select.dataset.index);
            rows[index].related_service_ids = Array.from(select.selectedOptions).map(o => Number(o.value));
        });
        rows.forEach((row, index) => { row.sort_order = index; });
        document.getElementById('coverage-json').value = JSON.stringify(rows);
    });

    render();
})();
</script>
@endpush
