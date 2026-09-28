@extends('template.main')

@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="['Service Finder' => null]">
        @include('admin.finder.partials.nav')

        <x-layouts.stat-card label="Coverage areas" :value="$stats['areas_active'].' / '.$stats['areas_total']" tone="primary" />
        <x-layouts.stat-card label="Sites counted" :value="number_format($stats['sites_active'])" tone="success" />
        <x-layouts.stat-card label="New quote requests" :value="number_format($stats['leads_new'])"
            :tone="$stats['leads_new'] > 0 ? 'danger' : null" />
        <x-layouts.stat-card label="Searches (30 days)" :value="number_format($stats['searches_30d'])" />

        {{--
            Where the section is currently live.

            Without this the only way to answer "is it on the location pages?"
            is to open the configurator and read four separate toggles. It is
            the first thing anyone asks about this module, so it goes at the
            top rather than behind a tab.
        --}}
        @php
            $placementSummary = [
                'Home page' => \App\Support\ServiceFinderSettings::get('placement_home'),
                'Service pages' => \App\Support\ServiceFinderSettings::get('placement_service'),
                'Sector pages' => \App\Support\ServiceFinderSettings::get('placement_sector'),
                'Location pages' => \App\Support\ServiceFinderSettings::get('placement_location'),
            ];
        @endphp
        <div class="col-12">
            <div class="card mb-3 sf-placements">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-eye me-1"></i> Where it appears</h3>
                    <a href="{{ route('admin.finder-settings.edit', ['tab' => 'placement']) }}"
                       class="btn btn-sm btn-outline-primary">Change</a>
                </div>
                <div class="card-body d-flex flex-wrap gap-2">
                    @foreach ($placementSummary as $label => $live)
                        <span class="sf-placement-pill {{ $live ? 'is-live' : 'is-off' }}">
                            <i class="fas {{ $live ? 'fa-circle-check' : 'fa-circle-minus' }}"></i>
                            {{ $label }}
                            <strong>{{ $live ? 'Live' : 'Off' }}</strong>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Health --}}
        <div class="col-12 sf-health">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-stethoscope me-1"></i> Configuration health</h3>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.finder.rebuild') }}"
                              onsubmit="return confirm('Recount every area from its sites? Your manual choices are kept.');">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-rotate"></i> Rebuild coverage
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.finder.clear-cache') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="fa fa-broom"></i> Clear website cache
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    @foreach ($health as $check)
                        <div class="alert alert-{{ $check['level'] }} d-flex justify-content-between align-items-center py-2 mb-2">
                            <span>{{ $check['message'] }}</span>
                            @if ($check['route'])
                                <a href="{{ $check['route'] }}" class="btn btn-sm btn-light">Review</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Demand gaps: the most actionable report in the module --}}
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-magnifying-glass-location me-1"></i> Demand gaps (30 days)</h3>
                </div>
                <div class="card-body p-0">
                    @forelse ($gaps as $gap)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span>{{ $gap->term }}</span>
                            <span class="badge bg-warning text-dark">{{ $gap->hits }}</span>
                        </div>
                    @empty
                        <p class="text-muted px-3 py-3 mb-0">
                            No unmatched searches — every visitor found coverage.
                        </p>
                    @endforelse
                </div>
                @if (count($gaps))
                    <div class="card-footer small text-muted">
                        People searched for these and found nothing. Consider adding an area or extending coverage.
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fire me-1"></i> Top searches (30 days)</h3>
                </div>
                <div class="card-body p-0">
                    @forelse ($topSearches as $row)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span>{{ $row->term }}</span>
                            <span class="badge bg-secondary">{{ $row->hits }}</span>
                        </div>
                    @empty
                        <p class="text-muted px-3 py-3 mb-0">No searches recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Recent leads --}}
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-envelope-open-text me-1"></i> Latest quote requests</h3>
                    <a href="{{ route('admin.finder-leads.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Name</th><th>Service</th><th>Location</th><th>From</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentLeads as $lead)
                                <tr>
                                    <td>{{ $lead->name }}</td>
                                    <td class="small">{{ $lead->service_label ?: '—' }}</td>
                                    <td class="small">{{ $lead->area->name ?? $lead->postcode_or_area }}</td>
                                    <td class="small text-muted">{{ str_replace('_', ' ', $lead->origin) }}</td>
                                    <td><span class="badge bg-{{ $lead->status === 'new' ? 'danger' : 'secondary' }}">{{ $lead->status }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.finder-leads.show', $lead) }}" class="btn btn-xs btn-info">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted text-center py-3">No quote requests yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-diagram-project me-1"></i> Where leads come from</h3>
                </div>
                <div class="card-body p-0">
                    @forelse ($leadsByOrigin as $row)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span class="text-capitalize">{{ str_replace('_', ' ', $row->origin) }}</span>
                            <span class="badge bg-primary">{{ $row->total }}</span>
                        </div>
                    @empty
                        <p class="text-muted px-3 py-3 mb-0">No leads in the last 90 days.</p>
                    @endforelse
                    @if ($stats['leads_spam'] > 0)
                        <div class="d-flex justify-content-between px-3 py-2 text-muted small">
                            <span>Blocked as spam</span>
                            <span>{{ $stats['leads_spam'] }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-location-crosshairs me-1"></i> Busiest areas</h3>
                </div>
                <div class="card-body p-0">
                    @forelse ($topAreas as $row)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span>{{ $row->name }}</span>
                            <span class="badge bg-secondary">{{ $row->hits }}</span>
                        </div>
                    @empty
                        <p class="text-muted px-3 py-3 mb-0">No matched searches yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
