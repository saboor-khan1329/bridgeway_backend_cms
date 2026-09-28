{{--
    Filter bar shared by the Service Finder listings (areas, sites, leads,
    searches).

    Every control is a plain GET input, so filters survive pagination, are
    linkable, and need no JavaScript. Pass $controls as:
      ['name' => ['label' => 'Area', 'options' => [value => label], 'width' => 3]]
--}}
@props([
    'action',
    'controls' => [],
    'searchPlaceholder' => 'Search…',
    'showSearch' => true,
    'perPage' => true,
])

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
            @if ($showSearch)
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <input type="search" name="q" value="{{ request('q') }}"
                           class="form-control form-control-sm" placeholder="{{ $searchPlaceholder }}">
                </div>
            @endif

            @foreach ($controls as $name => $control)
                <div class="col-md-{{ $control['width'] ?? 2 }}">
                    <label class="form-label small text-muted mb-1">{{ $control['label'] }}</label>
                    <select name="{{ $name }}" class="form-select form-select-sm">
                        <option value="">{{ $control['empty'] ?? 'All' }}</option>
                        @foreach ($control['options'] as $value => $label)
                            <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            @if ($perPage)
                <div class="col-md-auto">
                    <label class="form-label small text-muted mb-1">Per page</label>
                    <select name="per_page" class="form-select form-select-sm">
                        @foreach ([25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fa fa-filter"></i> Filter
                </button>
                <a href="{{ $action }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>

            {{ $slot ?? '' }}
        </form>
    </div>
</div>
