@extends('template.main')
@section('title', $config['title'])

@section('content')
    <x-layouts.page-wrapper :title="$config['title']" :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Quote Requests' => null]">
        @include('admin.finder.partials.nav')

        <div class="col-12">
            <div class="d-flex justify-content-end mb-2">
                <a href="{{ route('admin.finder-leads.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
                    <i class="fa fa-file-csv"></i> Export CSV
                </a>
            </div>

            <x-finder.filters :action="route('admin.finder-leads.index')" search-placeholder="Name, email, phone…"
                :controls="[
                    'status' => ['label' => 'Status', 'width' => 2, 'empty' => 'Active (no spam)',
                        'options' => array_combine($config['filters']['statuses'], array_map('ucfirst', $config['filters']['statuses']))],
                    'origin' => ['label' => 'Came from', 'width' => 2, 'options' =>
                        array_combine($config['filters']['origins'], array_map(fn ($o) => ucfirst(str_replace('_', ' ', $o)), $config['filters']['origins']))],
                    'area_id' => ['label' => 'Area', 'width' => 2, 'options' => $config['filters']['areas']],
                ]" />

            <x-layouts.index-card
                :headers="['ID', 'Contact', 'Service', 'Location', 'Start', 'From', 'Status', 'Received', 'Actions']"
                :pagination="$items->links()"
                :summary="number_format($items->total()) . ' request(s)'">
                @forelse ($items as $lead)
                    <tr class="{{ $lead->read_at ? '' : 'fw-semibold' }}">
                        <td>{{ $lead->id }}</td>
                        <td class="text-start">
                            {{ $lead->name }}
                            <div class="small text-muted">{{ $lead->email }} · {{ $lead->phone }}</div>
                            @if ($lead->company)<div class="small text-muted">{{ $lead->company }}</div>@endif
                        </td>
                        <td class="small">{{ $lead->service_label ?: '—' }}</td>
                        <td class="small">
                            {{ $lead->postcode_or_area }}
                            @if ($lead->area)<div class="text-muted">{{ $lead->area->name }}</div>@endif
                        </td>
                        <td class="small">{{ $lead->start_date ?: '—' }}</td>
                        <td class="small text-muted">{{ str_replace('_', ' ', $lead->origin) }}</td>
                        <td>
                            <span class="badge bg-{{ match ($lead->status) {
                                'new' => 'danger', 'contacted' => 'warning', 'quoted' => 'info',
                                'closed' => 'success', default => 'secondary' } }}">
                                {{ ucfirst($lead->status) }}
                            </span>
                            @if ($lead->spam_score > 0)
                                <span class="badge bg-dark" title="{{ implode(', ', (array) $lead->spam_reasons) }}">
                                    spam {{ $lead->spam_score }}
                                </span>
                            @endif
                        </td>
                        <td class="small">{{ $lead->created_at?->format('d M Y H:i') }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.finder-leads.show', $lead) }}" class="btn btn-info btn-sm">
                                <i class="fa fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No quote requests match these filters.</td></tr>
                @endforelse
            </x-layouts.index-card>
        </div>
    </x-layouts.page-wrapper>
@endsection
