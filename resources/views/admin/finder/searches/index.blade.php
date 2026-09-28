@extends('template.main')
@section('title', $config['title'])

@section('content')
    <x-layouts.page-wrapper :title="$config['title']" :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Searches' => null]">
        @include('admin.finder.partials.nav')

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Demand gaps</h3></div>
                <div class="card-body p-0">
                    @forelse ($config['stats']['gaps'] as $gap)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span>{{ $gap->term }}</span>
                            <span class="badge bg-warning text-dark">{{ $gap->hits }}</span>
                        </div>
                    @empty
                        <p class="text-muted px-3 py-3 mb-0">Every search found coverage.</p>
                    @endforelse
                </div>
                <div class="card-footer small text-muted">Searches that returned nothing.</div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Most searched</h3></div>
                <div class="card-body p-0">
                    @forelse ($config['stats']['top'] as $row)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span>{{ $row->term }}</span>
                            <span class="badge bg-secondary">{{ $row->hits }}</span>
                        </div>
                    @empty
                        <p class="text-muted px-3 py-3 mb-0">No searches recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Busiest areas</h3></div>
                <div class="card-body p-0">
                    @forelse ($config['stats']['areas'] as $row)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span>{{ $row->name }}</span>
                            <span class="badge bg-primary">{{ $row->hits }}</span>
                        </div>
                    @empty
                        <p class="text-muted px-3 py-3 mb-0">No matched searches.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12">
            <x-finder.filters :action="route('admin.finder-searches.index')" search-placeholder="Search term…"
                :controls="[
                    'match_type' => ['label' => 'Match', 'width' => 2,
                        'options' => array_combine($config['match_types'], array_map(fn ($t) => ucfirst(str_replace('_', ' ', $t)), $config['match_types']))],
                    'days' => ['label' => 'Period', 'width' => 2, 'empty' => 'Last 30 days',
                        'options' => [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year']],
                ]">
                <div class="col-md-auto">
                    <label class="form-label small text-muted mb-1 d-block">&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="document.getElementById('prune-form').submit();">
                        <i class="fa fa-broom"></i> Prune old logs
                    </button>
                </div>
            </x-finder.filters>

            <form id="prune-form" method="POST" action="{{ route('admin.finder-searches.prune') }}" class="d-none">
                @csrf
            </form>

            <x-layouts.index-card
                :headers="['ID', 'Search', 'Postcode', 'Matched area', 'Match', 'Results', 'When']"
                :pagination="$items->links()"
                :summary="number_format($items->total()) . ' search(es) in the last ' . $config['days'] . ' days'">
                @forelse ($items as $search)
                    <tr>
                        <td>{{ $search->id }}</td>
                        <td class="text-start">{{ $search->query }}</td>
                        <td class="small">{{ $search->postcode ?: '—' }}</td>
                        <td class="small">{{ $search->area->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $search->match_type === 'none' ? 'warning text-dark' : 'secondary' }}">
                                {{ str_replace('_', ' ', (string) $search->match_type) }}
                            </span>
                        </td>
                        <td>{{ $search->results_count }}</td>
                        <td class="small">{{ $search->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No searches in this period.</td></tr>
                @endforelse
            </x-layouts.index-card>
        </div>
    </x-layouts.page-wrapper>
@endsection
