@extends('template.main')
@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Import' => null]">
        @include('admin.finder.partials.nav')

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Import operational sites</h3></div>
                <form method="POST" action="{{ route('admin.finder-import.run') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <p class="text-muted small">
                            Expects three columns: <code>Services Type</code>, <code>Post code</code>,
                            <code>Site Name</code>. The site name is only read in memory to tell rows
                            apart and to spot test/demo rows — it is never saved or shown anywhere, so
                            client names never end up on file. Rows that cannot be placed on a UK map
                            are still imported, just held back with a reason so nothing is lost.
                        </p>

                        <div class="mb-3">
                            <label class="form-label">CSV file</label>
                            <input type="file" name="csv" accept=".csv,text/csv" class="form-control form-control-sm">
                            @if ($defaultExists)
                                <div class="form-text">
                                    Leave empty to re-use the configured file:
                                    <code class="small">{{ $defaultFile }}</code>
                                </div>
                            @endif
                        </div>

                        <div class="mb-2">
                            <label class="form-label">What should happen</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" value="preview" id="mode-preview" checked>
                                <label class="form-check-label" for="mode-preview">
                                    <strong>Preview only</strong>
                                    <span class="d-block small text-muted">
                                        Shows how every row would be classified. Nothing is saved and no lookups are made.
                                    </span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" value="append" id="mode-append">
                                <label class="form-check-label" for="mode-append">
                                    <strong>Add and update</strong>
                                    <span class="d-block small text-muted">
                                        Brings in new sites and refreshes existing ones. Safe to repeat.
                                    </span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" value="replace" id="mode-replace">
                                <label class="form-check-label" for="mode-replace">
                                    <strong>Replace imported sites</strong>
                                    <span class="d-block small text-muted">
                                        Clears previously imported rows first. Sites you edited by hand are kept.
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"
                                onclick="return document.getElementById('mode-replace').checked
                                    ? confirm('Replace all imported sites? Sites you edited by hand will be kept.')
                                    : true;">
                            <i class="fa fa-play"></i> Run
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">How rows are handled</h3></div>
                <div class="card-body small">
                    <p class="mb-2">Postcodes are resolved free of charge using the UK's open postcode
                        data — including retired postcodes and partial ones, which fall back to the
                        district centre. A paid lookup is only used if all of that fails.</p>
                    <p class="mb-0">
                        Change the service mapping, the phrases that mark a test row, and the
                        postcode-to-city table in the
                        <a href="{{ route('admin.finder-settings.edit', ['tab' => 'import']) }}">configurator</a>.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if ($report)
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            {{ $report['dry_run'] ? 'Preview result (nothing saved)' : 'Import result' }}
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($report['counts'] as $label => $value)
                                <div class="col-6 col-md-4 mb-2">
                                    <div class="border rounded px-2 py-1">
                                        <div class="small text-muted text-capitalize">{{ str_replace('_', ' ', $label) }}</div>
                                        <div class="h5 mb-0">{{ number_format($value) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (! empty($report['inactive_reasons']))
                            <h6 class="mt-3">Held back</h6>
                            <table class="table table-sm mb-0">
                                @foreach ($report['inactive_reasons'] as $reason => $count)
                                    <tr>
                                        <td class="text-capitalize">{{ str_replace('_', ' ', $reason) }}</td>
                                        <td class="text-end">{{ $count }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if (! empty(array_filter($report['geocoding'] ?? [])))
                            <h6 class="mt-3">Coordinate lookups</h6>
                            <table class="table table-sm mb-0">
                                @foreach ($report['geocoding'] as $source => $count)
                                    <tr>
                                        <td class="text-capitalize">{{ str_replace('_', ' ', $source) }}</td>
                                        <td class="text-end">{{ $count }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            <p class="small text-muted mt-2 mb-0">
                                Only the "google" row costs money. Everything else is free.
                            </p>
                        @endif

                        @if (! empty($report['sample']))
                            <h6 class="mt-3">First rows</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead><tr><th>Postcode</th><th>Area</th><th>Counted</th><th>Reason</th></tr></thead>
                                    <tbody>
                                        @foreach ($report['sample'] as $row)
                                            <tr>
                                                <td class="small">{{ $row['postcode'] }}</td>
                                                <td class="small">{{ $row['area'] }}</td>
                                                <td class="small">{{ $row['active'] }}</td>
                                                <td class="small text-muted">{{ str_replace('_', ' ', $row['reason']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-muted">
                        Run a preview to see how your file would be interpreted before committing anything.
                    </div>
                </div>
            @endif
        </div>
    </x-layouts.page-wrapper>
@endsection
