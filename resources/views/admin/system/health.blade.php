@extends('template.main')
@section('title', $title ?? 'System Health')

@section('content')
<x-layouts.page-wrapper :title="'System Health'" :breadcrumbs="['Dashboard' => route('admin.dashboard'), 'System Health' => null]">

    <div class="col-12">

        {{-- ── Refresh bar ─── --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted small"><i class="fas fa-clock me-1"></i>Checked at {{ now()->format('d M Y H:i:s') }} ({{ config('app.timezone') }})</div>
            <a href="{{ route('admin.system.health') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </a>
        </div>

        @php
        $statusMeta = [
            'ok'      => ['bg' => 'success', 'icon' => 'fas fa-check-circle',      'text' => 'OK'],
            'warning' => ['bg' => 'warning', 'icon' => 'fas fa-exclamation-circle', 'text' => 'Warning'],
            'error'   => ['bg' => 'danger',  'icon' => 'fas fa-times-circle',       'text' => 'Error'],
            'skipped' => ['bg' => 'secondary','icon' => 'fas fa-minus-circle',      'text' => 'N/A'],
        ];

        $overall = collect($checks)->contains(fn ($c) => ($c['status'] ?? 'ok') === 'error') ? 'error'
                 : (collect($checks)->contains(fn ($c) => ($c['status'] ?? 'ok') === 'warning') ? 'warning' : 'ok');
        @endphp

        {{-- ── Overall banner ─── --}}
        @php $ob = $statusMeta[$overall]; @endphp
        <div class="alert alert-{{ $ob['bg'] }} d-flex align-items-center gap-2 mb-4">
            <i class="{{ $ob['icon'] }} fa-lg"></i>
            <strong>System {{ ucfirst($overall) }}</strong>
            @if ($overall === 'ok') — All checks passed. @endif
            @if ($overall === 'warning') — One or more checks need attention. @endif
            @if ($overall === 'error') — One or more critical checks failed! @endif
        </div>

        {{-- ── Status cards ─── --}}
        <div class="row g-3 mb-4">

            {{-- Database --}}
            @php $db = $checks['database']; $dm = $statusMeta[$db['status']]; @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1"><i class="fas fa-database me-1 text-primary"></i>Database</h6>
                                <span class="badge bg-{{ $dm['bg'] }}">{{ $db['label'] }}</span>
                                <div class="text-muted small mt-1">{{ $db['detail'] }}</div>
                            </div>
                            <i class="{{ $dm['icon'] }} fa-xl text-{{ $dm['bg'] }}"></i>
                        </div>
                        <div class="mt-2 small text-muted">Driver: <code>{{ config('database.default') }}</code></div>
                    </div>
                </div>
            </div>

            {{-- Redis --}}
            @php $rd = $checks['redis']; $rm = $statusMeta[$rd['status']]; @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1"><i class="fas fa-server me-1 text-danger"></i>Redis</h6>
                                <span class="badge bg-{{ $rm['bg'] }}">{{ $rd['label'] }}</span>
                                <div class="text-muted small mt-1">{{ $rd['detail'] }}</div>
                            </div>
                            <i class="{{ $rm['icon'] }} fa-xl text-{{ $rm['bg'] }}"></i>
                        </div>
                        <div class="mt-2 small text-muted">
                            Cache: <code>{{ config('cache.default') }}</code> &nbsp;|&nbsp;
                            Queue: <code>{{ config('queue.default') }}</code>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cache --}}
            @php $ca = $checks['cache']; $cm = $statusMeta[$ca['status']]; @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1"><i class="fas fa-bolt me-1 text-warning"></i>Cache</h6>
                                <span class="badge bg-{{ $cm['bg'] }}">{{ $ca['label'] }}</span>
                                <div class="text-muted small mt-1">{{ $ca['detail'] }}</div>
                            </div>
                            <i class="{{ $cm['icon'] }} fa-xl text-{{ $cm['bg'] }}"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Queue --}}
            @php $qu = $checks['queue']; $qm = $statusMeta[$qu['status']]; @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1"><i class="fas fa-tasks me-1 text-info"></i>Queue Worker</h6>
                                <span class="badge bg-{{ $qm['bg'] }}">{{ $qu['label'] }}</span>
                                <div class="text-muted small mt-1">{{ $qu['detail'] }}</div>
                            </div>
                            <i class="{{ $qm['icon'] }} fa-xl text-{{ $qm['bg'] }}"></i>
                        </div>
                        @if (!empty($qu['queues']))
                        <div class="mt-2">
                            @foreach ($qu['queues'] as $qname => $qsize)
                            <span class="badge bg-light text-dark border me-1">{{ $qname }}: {{ $qsize }}</span>
                            @endforeach
                            <span class="badge {{ $qu['failed'] > 0 ? 'bg-danger' : 'bg-success' }} ms-1">
                                Failed: {{ $qu['failed'] }}
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Storage --}}
            @php $st = $checks['storage']; $sm = $statusMeta[$st['status']]; @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1"><i class="fas fa-hdd me-1 text-secondary"></i>Storage</h6>
                                <span class="badge bg-{{ $sm['bg'] }}">{{ $st['label'] }}</span>
                                <div class="text-muted small mt-1 text-truncate" style="max-width:220px;" title="{{ $st['detail'] }}">{{ $st['detail'] }}</div>
                            </div>
                            <i class="{{ $sm['icon'] }} fa-xl text-{{ $sm['bg'] }}"></i>
                        </div>
                        <div class="mt-2 small text-muted">
                            Onboarding dir: <code>{{ $st['onboarding_dir_exists'] ? '✓ exists' : '— not created yet' }}</code>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Application --}}
            @php $ap = $checks['application']; $am = $statusMeta[$ap['status']]; @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1"><i class="fab fa-laravel me-1 text-danger"></i>Application</h6>
                                <span class="badge bg-{{ $am['bg'] }}">{{ $ap['label'] }}</span>
                                <div class="text-muted small mt-1">{{ $ap['detail'] }}</div>
                            </div>
                            <i class="{{ $am['icon'] }} fa-xl text-{{ $am['bg'] }}"></i>
                        </div>
                        <div class="mt-2 small text-muted">
                            TZ: <code>{{ $ap['tz'] }}</code> &nbsp;|&nbsp; URL: <code>{{ $ap['url'] }}</code>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- /row --}}

        {{-- ── Queue worker command hint ─── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-terminal me-2 text-dark"></i>Queue Worker Command</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">Run this command on the server (or add to a Supervisor config) to process queued emails:</p>
                <pre class="bg-dark text-white rounded p-3 mb-2 small">php artisan queue:work {{ config('queue.default') }} --queue=onboarding,default,mail-outbox --tries=3 --sleep=3 --timeout=120</pre>
                @if ($checks['queue']['failed'] > 0)
                <div class="alert alert-warning py-2 mb-0 small">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>{{ $checks['queue']['failed'] }} failed job(s)</strong> detected.
                    Run <code>php artisan queue:retry all</code> to retry, or <code>php artisan queue:flush</code> to discard.
                </div>
                @endif
            </div>
        </div>

        {{-- ── JSON endpoint hint ─── --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <div class="small text-muted">
                    <i class="fas fa-code me-1"></i>
                    JSON endpoint for external monitoring:
                    <code>GET {{ route('admin.system.health.json') }}</code>
                </div>
                <a href="{{ route('admin.system.health.json') }}" target="_blank" class="btn btn-sm btn-outline-dark">
                    View JSON
                </a>
            </div>
        </div>

    </div>
</x-layouts.page-wrapper>
@endsection
