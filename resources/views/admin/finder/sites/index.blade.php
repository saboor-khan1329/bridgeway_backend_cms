@extends('template.main')
@section('title', $config['title'])

@section('content')
    <x-layouts.page-wrapper :title="$config['title']" :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Sites' => null]">
        @include('admin.finder.partials.nav')

        <div class="col-12">
            <div class="alert alert-secondary py-2 small">
                <i class="fa fa-lock me-1"></i>
                Individual site identity is not stored — only postcode and service, matching what the
                website itself shows (area names and counts). Editing a site here protects it from
                future imports.
            </div>

            <x-finder.filters :action="route('admin.finder-sites.index')" search-placeholder="Postcode or area…"
                :controls="[
                    'area_id' => ['label' => 'Area', 'width' => 2, 'options' => $config['filters']['areas']],
                    'service_id' => ['label' => 'Service', 'width' => 2, 'options' => $config['filters']['services']],
                    'status' => ['label' => 'Status', 'width' => 2, 'options' => ['active' => 'Counted', 'hidden' => 'Held back']],
                    'reason' => ['label' => 'Held back because', 'width' => 2, 'options' => $config['filters']['reasons']],
                ]" />

            <form method="POST" action="{{ route('admin.finder-sites.bulk') }}" id="bulk-form">
                @csrf

                <div class="card mb-2 sf-bulk-bar">
                    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
                        <span class="small text-muted"><span id="selected-count">0</span> selected</span>
                        <select name="action" class="form-select form-select-sm" style="max-width: 220px;" id="bulk-action">
                            <option value="">Bulk action…</option>
                            <option value="activate">Count on the map</option>
                            <option value="deactivate">Hide from the map</option>
                            <option value="geocode">Find coordinates</option>
                            <option value="assign_area">Move to area…</option>
                            <option value="assign_service">Set service…</option>
                            <option value="delete">Delete</option>
                        </select>
                        <select name="area_id" class="form-select form-select-sm d-none" style="max-width: 220px;" id="bulk-area">
                            <option value="">— Unassigned —</option>
                            @foreach ($config['filters']['areas'] as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <select name="service_id" class="form-select form-select-sm d-none" style="max-width: 240px;" id="bulk-service">
                            <option value="">— None —</option>
                            @foreach ($config['filters']['services'] as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary" id="bulk-apply" disabled>Apply</button>
                    </div>
                </div>

                <x-layouts.index-card
                    :headers="['', 'ID', 'Postcode', 'Area', 'Service', 'Placed', 'Status', 'Actions']"
                    :pagination="$items->links()"
                    :summary="number_format($items->total()) . ' site(s)'">
                    @forelse ($items as $site)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $site->id }}" class="row-check"></td>
                            <td>{{ $site->id }}</td>
                            <td class="small">
                                {{ $site->postcode ?: $site->postcode_raw }}
                                @if (! $site->postcode && $site->outcode)
                                    <span class="badge bg-warning text-dark" title="Only the outward code could be read">{{ $site->outcode }}</span>
                                @endif
                                @if ($site->source === 'admin')
                                    <span class="badge bg-dark" title="Edited by an admin — protected from re-import">edited</span>
                                @endif
                            </td>
                            <td class="small">{{ $site->area->name ?? '—' }}</td>
                            <td class="small">{{ $site->service->title ?? '—' }}</td>
                            <td class="small">
                                @if ($site->latitude)
                                    <span class="text-success" title="{{ $site->geocode_source }}">
                                        <i class="fa fa-check"></i>
                                    </span>
                                @else
                                    <span class="text-danger"><i class="fa fa-xmark"></i></span>
                                @endif
                            </td>
                            <td>
                                @if ($site->is_active)
                                    <span class="badge bg-success">Counted</span>
                                @else
                                    <span class="badge bg-secondary" title="{{ $site->inactive_reason }}">
                                        {{ str_replace('_', ' ', (string) $site->inactive_reason) ?: 'Hidden' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.finder-sites.edit', $site) }}" class="btn btn-success btn-sm">
                                    <i class="fa fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No sites match these filters.</td></tr>
                    @endforelse
                </x-layouts.index-card>
            </form>
        </div>
    </x-layouts.page-wrapper>
@endsection

@push('scripts')
<script>
(function () {
    const form    = document.getElementById('bulk-form');
    const action  = document.getElementById('bulk-action');
    const apply   = document.getElementById('bulk-apply');
    const counter = document.getElementById('selected-count');
    const extras  = { assign_area: 'bulk-area', assign_service: 'bulk-service' };

    // A header checkbox that toggles the visible page only.
    const headerCell = document.querySelector('.admin-index-table thead th');
    if (headerCell) {
        headerCell.innerHTML = '<input type="checkbox" id="check-all">';
        headerCell.querySelector('#check-all').addEventListener('change', (event) => {
            document.querySelectorAll('.row-check').forEach(box => { box.checked = event.target.checked; });
            refresh();
        });
    }

    function refresh() {
        const selected = document.querySelectorAll('.row-check:checked').length;
        counter.textContent = selected;
        apply.disabled = selected === 0 || action.value === '';
    }

    form.addEventListener('change', (event) => {
        if (event.target.classList.contains('row-check')) refresh();
    });

    action.addEventListener('change', () => {
        Object.values(extras).forEach(id => document.getElementById(id).classList.add('d-none'));
        if (extras[action.value]) document.getElementById(extras[action.value]).classList.remove('d-none');
        refresh();
    });

    form.addEventListener('submit', (event) => {
        if (action.value === 'delete' &&
            !confirm(`Permanently delete ${document.querySelectorAll('.row-check:checked').length} site(s)?`)) {
            event.preventDefault();
        }
    });

    refresh();
})();
</script>
@endpush
