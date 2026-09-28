@props([
    'headers' => [],
    'pagination' => null,
    'summary' => null,
])

<div class="card">
    <!-- Table -->
    <div class="card-body table-responsive">
        <table class="table table-striped table-bordered table-hover text-center admin-index-table" style="width: 100%;">
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>

        {{-- Pagination --}}
        @if ($summary || $pagination)
            <div class="mt-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div class="text-muted small">
                    {{ $summary }}
                </div>
                <div>
                    {!! $pagination ?? '' !!}
                </div>
            </div>
        @endif
    </div>
</div>
