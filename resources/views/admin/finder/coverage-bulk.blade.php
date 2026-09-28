@extends('template.main')
@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Roll Out' => null]">
        @include('admin.finder.partials.nav')

        <div class="col-12">
            <div class="alert alert-info py-2">
                <i class="fa fa-circle-info me-1"></i>
                Answers the question <em>"where do we offer this service?"</em> — pick a service, tick every area
                that offers it, and save once. To fine-tune the wording for a single area, use its
                <a href="{{ route('admin.finder-areas.index') }}">coverage editor</a> instead.
            </div>

            {{-- Choosing a service reloads with its current areas ticked. --}}
            <form method="GET" action="{{ route('admin.finder-coverage.bulk') }}" class="card mb-3">
                <div class="card-body d-flex flex-wrap gap-2 align-items-end">
                    <div style="min-width: 320px;">
                        <label class="form-label small text-muted mb-1">Service</label>
                        <select name="service_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Choose a service…</option>
                            @foreach (collect($catalog)->groupBy('category') as $category => $services)
                                <optgroup label="{{ $category }}">
                                    @foreach ($services as $option)
                                        <option value="{{ $option['id'] }}" @selected($service && $service->id === $option['id'])>
                                            {{ $option['title'] }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            @if ($service)
                <form method="POST" action="{{ route('admin.finder-coverage.bulk.update') }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="service_id" value="{{ $service->id }}">

                    <div class="card">
                        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                            <h3 class="card-title mb-0 me-auto">Areas offering {{ $service->title }}</h3>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-select="all">Select all</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-select="none">Clear</button>
                            <button type="button" class="btn btn-sm btn-outline-info" data-select="sites"
                                    title="Only areas that already have operational sites">With sites</button>
                        </div>

                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">
                                    Description applied to every area selected (optional)
                                </label>
                                <textarea name="description" rows="2" class="form-control form-control-sm"
                                          placeholder="Leave blank to use the service's own card description.">{{ old('description') }}</textarea>
                            </div>

                            <div class="row g-2" id="area-list">
                                @foreach ($areas as $area)
                                    <div class="col-md-4 col-lg-3">
                                        <label class="d-flex align-items-center gap-2 border rounded px-2 py-1 mb-0">
                                            <input type="checkbox" name="area_ids[]" value="{{ $area->id }}"
                                                   data-sites="{{ $area->active_sites_count }}"
                                                   @checked(in_array($area->id, $selected, true))>
                                            <span class="flex-grow-1">
                                                {{ $area->name }}
                                                <span class="text-muted small d-block">
                                                    {{ $area->active_sites_count }} site(s)
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-save"></i> Save coverage for {{ $service->title }}
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </x-layouts.page-wrapper>
@endsection

@push('scripts')
<script>
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-select]');
    if (!button) return;

    const mode = button.dataset.select;
    document.querySelectorAll('#area-list input[type=checkbox]').forEach((box) => {
        box.checked = mode === 'all' ? true
            : mode === 'none' ? false
            : Number(box.dataset.sites) > 0;
    });
});
</script>
@endpush
