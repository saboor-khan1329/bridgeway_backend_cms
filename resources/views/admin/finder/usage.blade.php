@extends('template.main')
@section('title', $title)

@php
    use App\Models\FinderUsageDaily;

    // ---- chart geometry -----------------------------------------------
    // Plain inline SVG, no charting library: two polylines over a shared
    // day axis. Coordinates are computed once here rather than in JS so the
    // chart renders correctly with no client-side work at all.
    $chartWidth = 700;
    $chartHeight = 220;
    $padLeft = 34;
    $padBottom = 24;
    $padTop = 12;
    $plotWidth = $chartWidth - $padLeft - 12;
    $plotHeight = $chartHeight - $padTop - $padBottom;

    $labels = $series['labels'];
    $count = count($labels);
    $maxValue = max(1, ...$series['series'][FinderUsageDaily::MAP_LOAD], ...$series['series'][FinderUsageDaily::AUTOCOMPLETE_SESSION]);
    // Round the axis ceiling up to a friendlier number than the raw max.
    $axisMax = max(4, (int) ceil($maxValue / 4) * 4);

    $pointsFor = function (array $values) use ($count, $plotWidth, $plotHeight, $padLeft, $padTop, $axisMax) {
        if ($count <= 1) {
            return '';
        }

        $points = [];
        foreach ($values as $i => $value) {
            $x = $padLeft + ($i / ($count - 1)) * $plotWidth;
            $y = $padTop + $plotHeight - ($value / $axisMax) * $plotHeight;
            $points[] = round($x, 1).','.round($y, 1);
        }

        return implode(' ', $points);
    };

    $mapLine = $pointsFor($series['series'][FinderUsageDaily::MAP_LOAD]);
    $acLine = $pointsFor($series['series'][FinderUsageDaily::AUTOCOMPLETE_SESSION]);

    // Show roughly 6 x-axis labels regardless of range, so a 90-day chart
    // does not print ninety overlapping dates.
    $labelEvery = max(1, (int) ceil($count / 6));
@endphp

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'API Usage' => null]">
        @include('admin.finder.partials.nav')

        <div class="col-12">
            <div class="alert alert-info d-flex align-items-start gap-2 border-0 shadow-sm sf-usage-note" role="status">
                <i class="fas fa-circle-info mt-1"></i>
                <div>
                    <strong>What this page shows.</strong> Google does not hand live quota or
                    billing figures to an application key — reading the real number needs a
                    Google Cloud Billing service-account credential this project does not have
                    set up. What is counted below instead is what this app itself asked Google
                    for: one event logged the moment the map actually loads, and one logged per
                    new address-autocomplete session (never per keystroke, matching how Google
                    bills a session). It normally matches Google's real figure closely — it
                    would only drift if a request left the browser and never reached Google, an
                    ad blocker or a network failure.
                </div>
            </div>
        </div>

        <x-layouts.stat-card label="Map loads ({{ $days }}d)"
            :value="number_format($series['totals'][FinderUsageDaily::MAP_LOAD])" tone="primary" />
        <x-layouts.stat-card label="Autocomplete sessions ({{ $days }}d)"
            :value="number_format($series['totals'][FinderUsageDaily::AUTOCOMPLETE_SESSION])" tone="primary" />
        <x-layouts.stat-card label="Map loads (all-time)"
            :value="number_format($allTime[FinderUsageDaily::MAP_LOAD] ?? 0)" />
        <x-layouts.stat-card label="Autocomplete sessions (all-time)"
            :value="number_format($allTime[FinderUsageDaily::AUTOCOMPLETE_SESSION] ?? 0)" />

        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-chart-line me-1"></i> Daily calls</h3>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Date range">
                        @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $value => $label)
                            <a href="{{ route('admin.finder-usage.index', ['days' => $value]) }}"
                               class="btn {{ $days === $value ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="card-body">
                    @if ($count > 1)
                        <div class="sf-usage-chart-wrap">
                            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="sf-usage-chart"
                                 role="img" aria-label="Daily map loads and autocomplete sessions">
                                {{-- horizontal gridlines + y-axis labels --}}
                                @for ($g = 0; $g <= 4; $g++)
                                    @php
                                        $gy = $padTop + $plotHeight - ($g / 4) * $plotHeight;
                                        $gValue = (int) round(($g / 4) * $axisMax);
                                    @endphp
                                    <line x1="{{ $padLeft }}" y1="{{ $gy }}" x2="{{ $chartWidth - 12 }}" y2="{{ $gy }}"
                                          stroke="#e9ecef" stroke-width="1" />
                                    <text x="{{ $padLeft - 8 }}" y="{{ $gy + 3 }}" font-size="10" fill="#8a94a6"
                                          text-anchor="end">{{ $gValue }}</text>
                                @endfor

                                {{-- x-axis day labels, thinned so they never overlap --}}
                                @foreach ($labels as $i => $label)
                                    @if ($i % $labelEvery === 0 || $i === $count - 1)
                                        @php $lx = $padLeft + ($i / ($count - 1)) * $plotWidth; @endphp
                                        <text x="{{ $lx }}" y="{{ $chartHeight - 6 }}" font-size="10" fill="#8a94a6"
                                              text-anchor="middle">{{ $label }}</text>
                                    @endif
                                @endforeach

                                <polyline points="{{ $mapLine }}" fill="none" stroke="#2F95D9" stroke-width="2"
                                          stroke-linejoin="round" stroke-linecap="round" />
                                <polyline points="{{ $acLine }}" fill="none" stroke="#E31E24" stroke-width="2"
                                          stroke-linejoin="round" stroke-linecap="round" />
                            </svg>
                        </div>

                        <div class="d-flex flex-wrap gap-3 mt-2 small">
                            <span class="sf-usage-legend"><i style="background:#2F95D9"></i> Map loads</span>
                            <span class="sf-usage-legend"><i style="background:#E31E24"></i> Autocomplete sessions</span>
                        </div>
                    @else
                        <p class="text-muted mb-0">Not enough days of data yet to draw a chart.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-file-import me-1"></i> Import geocoding (one-off, not ongoing traffic)</h3></div>
                <div class="card-body">
                    <p class="text-muted">
                        Separate from the live counters above: resolving a site's postcode to a
                        map position during a CSV import. Free UK open data is tried first;
                        Google is only billed when that fails. This total is permanent and only
                        grows when you run an import, not from ordinary website visitors.
                    </p>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="border rounded p-3">
                                <div class="small text-muted">Resolved free (postcodes.io)</div>
                                <div class="h4 mb-0">{{ number_format($importGeocodesFree) }}</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3">
                                <div class="small text-muted">Resolved via Google (billed)</div>
                                <div class="h4 mb-0">{{ number_format($importGeocodesGoogle) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
