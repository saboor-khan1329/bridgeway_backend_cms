@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="[$title => null]">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.onboarding.forms.create') }}" class="btn btn-success btn-sm">
                    <i class="fa fa-plus"></i> New Form Configuration
                </a>
                <a href="{{ route('admin.onboarding.submissions.index') }}" class="btn btn-info btn-sm ms-2">
                    <i class="fa fa-list"></i> View Submissions
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Submissions</th>
                            <th>Steps</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($configs as $cfg)
                        <tr>
                            <td>{{ $cfg->id }}</td>
                            <td>
                                <strong>{{ $cfg->name }}</strong>
                                @if ($cfg->description)
                                    <div class="text-muted small">{{ Str::limit($cfg->description, 80) }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($cfg->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>{{ number_format($cfg->submissions_count) }}</td>
                            <td>{{ count($cfg->form_config['steps'] ?? []) }} steps</td>
                            <td>{{ $cfg->updated_at->format('d M Y H:i') }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.onboarding.forms.edit', $cfg->id) }}" class="btn btn-sm btn-success me-1" title="Edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @if (!$cfg->is_active)
                                    <form method="POST" action="{{ route('admin.onboarding.forms.activate', $cfg->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-primary me-1" title="Activate" data-confirm-submit="activate this form config">
                                            <i class="fa fa-check-circle"></i> Activate
                                        </button>
                                    </form>
                                    @if ($cfg->submissions_count === 0)
                                        <form method="POST" action="{{ route('admin.onboarding.forms.destroy', $cfg->id) }}" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger" title="Delete" data-confirm-submit="delete">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No form configurations yet. <a href="{{ route('admin.onboarding.forms.create') }}">Create one</a> or run the seeder.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $configs->links() }}</div>
    </div>
</x-layouts.page-wrapper>
@endsection
