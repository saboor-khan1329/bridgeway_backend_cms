@extends('template.main')

@section('title', $config['title'])

@section('content')
    <x-layouts.page-wrapper :title="$config['title']" :breadcrumbs="[$config['title'] => null]">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <a href="{{ $config['routes']['index'] }}" class="btn btn-warning btn-sm">
                    <i class="fa fa-arrow-left"></i> Back
                </a>

                <div class="d-flex flex-wrap gap-2">
                    @if (!empty($config['routes']['edit']))
                        <a href="{{ $config['routes']['edit'] }}" class="btn btn-success btn-sm">
                            <i class="fa fa-pen"></i> Edit
                        </a>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @foreach ($config['form'] as $row)
                        <div class="row">
                            @foreach ($row as $field)
                                @php
                                    $col = $field['col'] ?? 6;
                                    $name = $field['name'] ?? null;
                                    $value = $field['value'] ?? ($name ? data_get($item, $name) : null);
                                    $selectedLabels = $field['selected_labels'] ?? [];
                                    $options = $field['options'] ?? [];
                                    $image = isset($field['image_key']) ? $item?->getImage($field['image_key']) : null;
                                    $imageUrl = $image ? \App\Support\ImageFormatter::url($image->path, $image->disk) : null;
                                @endphp

                                <div class="col-md-{{ $col }} mb-4">
                                    <div class="small text-muted fw-bold mb-1">{{ $field['label'] }}</div>

                                    @switch($field['type'])
                                        @case('image')
                                            @if ($image && $imageUrl)
                                                <div class="border rounded p-3 bg-light">
                                                    <img src="{{ $imageUrl }}" alt="{{ $image->alt ?: $field['label'] }}"
                                                        class="img-fluid rounded border mb-2" style="max-height: 220px;">
                                                    <div class="small text-muted text-break">{{ $image->path }}</div>
                                                    @if ($image->alt)
                                                        <div class="small mt-2"><strong>Alt:</strong> {{ $image->alt }}</div>
                                                    @endif
                                                    @if ($image->title)
                                                        <div class="small"><strong>Title:</strong> {{ $image->title }}</div>
                                                    @endif
                                                    @if ($image->caption)
                                                        <div class="small"><strong>Caption:</strong> {{ $image->caption }}</div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="border rounded p-3 bg-light text-muted">No image assigned.</div>
                                            @endif
                                        @break

                                        @case('textarea')
                                            @if ($value)
                                                @if ($field['editor'] ?? false)
                                                    <div class="border rounded p-3 bg-light">{!! $value !!}</div>
                                                @else
                                                    <div class="border rounded p-3 bg-light" style="white-space: pre-wrap;">{{ $value }}</div>
                                                @endif
                                            @else
                                                <div class="border rounded p-3 bg-light text-muted">No value provided.</div>
                                            @endif
                                        @break

                                        @case('select')
                                            <div class="border rounded p-3 bg-light">
                                                {{ $options[(string) $value] ?? $options[$value] ?? ($value !== null && $value !== '' ? $value : 'Not set') }}
                                            </div>
                                        @break

                                        @case('multi-select')
                                            <div class="border rounded p-3 bg-light">
                                                @if ($selectedLabels !== [])
                                                    {{ implode(', ', array_values($selectedLabels)) }}
                                                @else
                                                    <span class="text-muted">No records assigned.</span>
                                                @endif
                                            </div>
                                        @break

                                        @default
                                            <div class="border rounded p-3 bg-light">
                                                @if ($value instanceof \Carbon\CarbonInterface)
                                                    {{ $value->format('d M Y H:i') }}
                                                @elseif (is_bool($value))
                                                    {{ $value ? 'Yes' : 'No' }}
                                                @elseif ($value !== null && $value !== '')
                                                    {{ $value }}
                                                @else
                                                    <span class="text-muted">Not set</span>
                                                @endif
                                            </div>
                                    @endswitch
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
