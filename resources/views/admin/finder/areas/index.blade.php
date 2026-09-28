@extends('template.main')
@section('title', $config['title'])

@section('content')
    <x-layouts.page-wrapper :title="$config['title']" :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Areas' => null]">
        @include('admin.finder.partials.nav')

        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <a href="{{ route('admin.finder-areas.create') }}" class="btn btn-success btn-sm">
                    <i class="fa fa-plus"></i> New area
                </a>
                <div class="text-muted small">
                    Each active area with coordinates becomes one pin on the public map.
                </div>
            </div>

            <x-finder.filters :action="route('admin.finder-areas.index')" search-placeholder="Area or region…"
                :controls="[
                    'status' => ['label' => 'Status', 'width' => 2, 'options' => ['active' => 'Active', 'hidden' => 'Hidden']],
                    'missing' => ['label' => 'Needs attention', 'width' => 3, 'empty' => 'Anything',
                        'options' => ['coords' => 'Missing coordinates', 'featured' => 'No featured service']],
                ]" />

            <x-layouts.index-card
                :headers="['ID', 'Area', 'Prefixes', 'Sites', 'Services', 'Featured service', 'Status', 'Actions']"
                :pagination="$items->links()"
                :summary="$items->total() . ' area(s)'">
                @forelse ($items as $area)
                    <tr>
                        <td>{{ $area->id }}</td>
                        <td class="text-start">
                            <strong>{{ $area->name }}</strong>
                            @if ($area->region)
                                <div class="small text-muted">{{ $area->region }}</div>
                            @endif
                            @unless ($area->latitude)
                                <span class="badge bg-danger">No coordinates</span>
                            @endunless
                            @if ($area->centroid_is_locked)
                                <span class="badge bg-secondary" title="Pin position set manually">
                                    <i class="fa fa-lock"></i> pinned
                                </span>
                            @endif
                        </td>
                        <td class="small">{{ implode(', ', (array) $area->postcode_areas) ?: '—' }}</td>
                        <td>{{ number_format($area->active_sites_count) }}</td>
                        <td>{{ $area->services_count }}</td>
                        <td class="small">{{ $area->featuredService->title ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $area->is_active ? 'success' : 'secondary' }}">
                                {{ $area->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.finder-areas.coverage.edit', $area) }}"
                               class="btn btn-primary btn-sm me-1" title="Choose the services offered here">
                                <i class="fa fa-layer-group"></i>
                            </a>
                            <a href="{{ route('admin.finder-areas.edit', $area) }}" class="btn btn-success btn-sm me-1" title="Edit">
                                <i class="fa fa-pen"></i>
                            </a>
                            <form action="{{ route('admin.finder-areas.recount', $area) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-outline-secondary btn-sm me-1" title="Recount from its sites">
                                    <i class="fa fa-rotate"></i>
                                </button>
                            </form>
                            @unless ($area->latitude)
                                <form action="{{ route('admin.finder-areas.geocode', $area) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-outline-info btn-sm me-1" title="Find coordinates from the postcode prefix">
                                        <i class="fa fa-crosshairs"></i>
                                    </button>
                                </form>
                            @endunless
                            <form action="{{ route('admin.finder-areas.destroy', $area) }}" method="POST" class="d-inline">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" data-confirm-submit="delete"
                                        title="Delete this area (its sites are kept)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No coverage areas yet. Create one by hand, or
                            <a href="{{ route('admin.finder-import.edit') }}">import your site list</a> to build them automatically.
                        </td>
                    </tr>
                @endforelse
            </x-layouts.index-card>
        </div>
    </x-layouts.page-wrapper>
@endsection
