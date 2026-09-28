@extends('template.main')
@section('title', $config['title'])

@section('content')
    <x-layouts.page-wrapper :title="$config['title']" :breadcrumbs="[$config['title'] => null]">
        @php
            $currentSearch = trim((string) request('q', ''));
            $currentPerPage = max(1, min((int) request('per_page', 25), 100));
            $hasTotal = method_exists($items, 'total');
            $summary = $items->count() > 0
                ? ($hasTotal
                    ? 'Showing ' . number_format($items->firstItem()) . ' to ' . number_format($items->lastItem()) . ' of ' . number_format($items->total()) . ' results'
                    : 'Showing ' . number_format($items->firstItem()) . ' to ' . number_format($items->lastItem()))
                : 'No results found';
        @endphp

        <div class="col-12">
            <div class="mb-3 d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if (!empty($config['routes']['create']))
                        <a href="{{ $config['routes']['create'] }}" class="btn btn-success btn-sm">
                            <i class="fa fa-plus"></i> Add {{ \Illuminate\Support\Str::singular($config['title']) }}
                        </a>
                    @endif
                </div>

                <form method="GET" action="{{ url()->current() }}" class="d-flex flex-wrap gap-2">
                    <input type="search" name="q" value="{{ $currentSearch }}" class="form-control form-control-sm"
                        placeholder="Search {{ \Illuminate\Support\Str::lower($config['title']) }}" style="min-width: 240px;">
                    <select name="per_page" class="form-control form-control-sm" style="min-width: 100px;">
                        @foreach ([10, 25, 50, 100] as $perPage)
                            <option value="{{ $perPage }}" {{ $currentPerPage === $perPage ? 'selected' : '' }}>
                                {{ $perPage }} / page
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Search
                    </button>
                    @if ($currentSearch !== '' || request()->has('per_page'))
                        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                    @endif
                </form>
            </div>

            <x-layouts.index-card :headers="array_merge(array_values($config['columns']), ['Actions'])"
                :pagination="$items->links()" :summary="$summary">
                @forelse ($items as $data)
                    <tr>
                        @foreach ($config['columns'] as $field => $label)
                            <td>{!! formatCell($data, $field) !!}</td>
                        @endforeach
                        <td class="text-nowrap">
                            @if (!empty($config['routes']['show']))
                                <a href="{{ $config['routes']['show']($data) }}" class="btn btn-info btn-sm me-1" title="View">
                                    <i class="fa fa-eye"></i>
                                </a>
                            @endif
                            @if (!empty($config['routes']['edit']))
                                <a href="{{ $config['routes']['edit']($data) }}" class="btn btn-success btn-sm me-1" title="Edit">
                                    <i class="fa fa-pen"></i>
                                </a>
                            @endif
                            @if (!empty($config['routes']['delete']))
                                <form action="{{ $config['routes']['delete']($data) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" data-confirm-submit="delete" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($config['columns']) + 1 }}" class="text-center text-muted py-4">
                            No records found.
                        </td>
                    </tr>
                @endforelse
            </x-layouts.index-card>
        </div>
    </x-layouts.page-wrapper>
@endsection
