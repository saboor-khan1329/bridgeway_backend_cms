{{--
    Module navigation shared by every Service Finder screen, so the tool
    reads as one workspace rather than seven unrelated pages.
--}}
@php
    $finderTabs = [
        ['route' => 'admin.finder.dashboard',      'match' => 'admin.finder.dashboard',   'icon' => 'fa-gauge',        'label' => 'Overview'],
        ['route' => 'admin.finder-areas.index',    'match' => 'admin.finder-areas.*',     'icon' => 'fa-map-location-dot', 'label' => 'Areas'],
        ['route' => 'admin.finder-coverage.bulk',  'match' => 'admin.finder-coverage.*',  'icon' => 'fa-layer-group',  'label' => 'Roll Out'],
        ['route' => 'admin.finder-sites.index',    'match' => 'admin.finder-sites.*',     'icon' => 'fa-location-dot', 'label' => 'Sites'],
        ['route' => 'admin.finder-import.edit',    'match' => 'admin.finder-import.*',    'icon' => 'fa-file-import',  'label' => 'Import'],
        ['route' => 'admin.finder-leads.index',    'match' => 'admin.finder-leads.*',     'icon' => 'fa-envelope-open-text', 'label' => 'Quote Requests'],
        ['route' => 'admin.finder-searches.index', 'match' => 'admin.finder-searches.*',  'icon' => 'fa-chart-line',   'label' => 'Searches'],
        ['route' => 'admin.finder-usage.index',    'match' => 'admin.finder-usage.*',     'icon' => 'fa-gauge-high',   'label' => 'API Usage'],
        ['route' => 'admin.finder-settings.edit',  'match' => 'admin.finder-settings.*',  'icon' => 'fa-sliders',      'label' => 'Configurator'],
    ];
@endphp

@include('admin.finder.partials.styles')

@unless (config('service_finder.enabled', true))
    {{--
        The environment kill switch hides the sidebar entry, but these screens
        still answer on a direct URL or a bookmark. Without this an operator
        can spend a while editing settings and never work out why the website
        is unchanged, so the state is stated plainly and the remedy with it.
    --}}
    <div class="col-12">
        <div class="alert alert-dark d-flex align-items-start gap-2 border-0 shadow-sm" role="alert">
            <i class="fas fa-power-off mt-1"></i>
            <div>
                <strong>The Service Finder is switched off for the whole site.</strong>
                <div class="small mt-1">
                    Nothing renders on the website and quote requests are refused, whatever
                    is configured here. Your changes still save normally and take effect the
                    moment it is switched back on.
                    <br>
                    To re-enable it, set <code>SERVICE_FINDER_ENABLED=true</code> in the
                    backend <code>.env</code> file.
                </div>
            </div>
        </div>
    </div>
@endunless

<div class="col-12 sf-admin-nav">
    <ul class="nav nav-pills flex-wrap gap-1 mb-3 bg-white p-2 rounded shadow-sm">
        @foreach ($finderTabs as $tab)
            <li class="nav-item">
                <a href="{{ route($tab['route']) }}"
                   class="nav-link py-1 px-3 {{ request()->routeIs($tab['match']) ? 'active' : 'text-dark' }}">
                    <i class="fas {{ $tab['icon'] }} me-1"></i>{{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
