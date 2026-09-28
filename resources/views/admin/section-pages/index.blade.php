@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="[$title => null]">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <p class="mb-0 text-muted">Database-driven pages rendered by the existing Next.js section templates.</p>
                <a href="{{ route($routeName.'.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Page</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Title</th><th>Slug</th><th>Template</th><th>Sections</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item->title }}</td>
                            <td><code>{{ $item instanceof \App\Models\ContentPage ? $item->slug : $item->slug?->slug }}</code></td>
                            <td>{{ $item instanceof \App\Models\ContentPage ? $item->template : 'inner-service' }}</td>
                            <td>{{ $item->content_blocks_count }}</td>
                            <td><span class="badge {{ $item->status ? 'badge-success' : 'badge-secondary' }}">{{ $item->status ? 'Published' : 'Draft' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route($routeName.'.edit', $item) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route($routeName.'.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this page and its sections?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No pages found. Run WebsiteContentSeeder to import the initial authored content.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif
        </div>

        @if(!empty($extraPages) && $extraPages->count() > 0)
        <div class="card mt-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Custom Development Route Pages</h4>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Title</th><th>Slug</th><th>Template</th><th>Sections</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @foreach($extraPages as $extra)
                        <tr>
                            <td><strong>{{ $extra->title }}</strong></td>
                            <td><code>{{ $extra->slug }}</code></td>
                            <td><span class="badge bg-info text-dark">{{ $extra->template }}</span></td>
                            <td>{{ $extra->content_blocks_count }}</td>
                            <td><span class="badge {{ $extra->status ? 'badge-success' : 'badge-secondary' }}">{{ $extra->status ? 'Published' : 'Draft' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.content-pages.edit', $extra) }}" class="btn btn-sm btn-outline-primary">Edit Page</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</x-layouts.page-wrapper>
@endsection
