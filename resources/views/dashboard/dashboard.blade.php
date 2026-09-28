@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="['Dashboard' => null]">
    <div class="col-12">
        <div class="row g-3 mb-4">
            @foreach($stats as $card)
            <div class="col-6 col-md-4 col-xl-3">
                <a href="{{ route($card['route']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small text-uppercase fw-semibold">{{ $card['label'] }}</div>
                                <div class="h3 mb-0 fw-bold">{{ number_format($card['value']) }}</div>
                            </div>
                            <i class="{{ $card['icon'] }} fa-2x text-{{ $card['tone'] }}"></i>
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-inbox me-2 text-danger"></i>Recent Website Inquiries</h6>
                <a href="{{ route('admin.inquiries.index') }}" class="btn btn-sm btn-outline-danger">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Form</th><th>Name</th><th>Email</th><th>Status</th><th>Received</th><th></th></tr></thead>
                    <tbody>
                    @forelse($recentInquiries as $inquiry)
                        <tr>
                            <td>{{ $inquiry->form_name ?: 'Website' }}</td>
                            <td>{{ $inquiry->name }}</td>
                            <td>{{ $inquiry->email }}</td>
                            <td>{!! formatCell($inquiry, 'status') !!}</td>
                            <td>{{ $inquiry->created_at?->diffForHumans() }}</td>
                            <td class="text-end"><a href="{{ route('admin.inquiries.show', $inquiry) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No inquiries yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0 fw-semibold">Quick Access</h6></div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="{{ route('admin.amazon-services.create') }}" class="btn btn-outline-warning btn-sm text-dark"><i class="fab fa-amazon me-1"></i> New Amazon Service</a>
                <a href="{{ route('admin.development-services.create') }}" class="btn btn-outline-info btn-sm"><i class="fas fa-laptop-code me-1"></i> New Development Service</a>
                <a href="{{ route('admin.forms.index') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-clipboard-list me-1"></i> Forms Management</a>
                <a href="{{ route('admin.content-pages.create') }}" class="btn btn-outline-secondary btn-sm">New Content Page</a>
                <a href="{{ route('admin.service-pages.create') }}" class="btn btn-outline-secondary btn-sm">New Service Page</a>
                <a href="{{ route('admin.blogs.create') }}" class="btn btn-outline-success btn-sm"><i class="fas fa-pen me-1"></i> New Blog Post</a>
                <a href="{{ route('admin.navigation.index') }}" class="btn btn-outline-secondary btn-sm">Edit Navigation</a>
                <a href="{{ route('admin.settings.edit') }}" class="btn btn-outline-secondary btn-sm">Site Settings</a>
            </div>
        </div>
    </div>
</x-layouts.page-wrapper>
@endsection
