@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="[$title => null]">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.onboarding.forms.index') }}" class="btn btn-warning btn-sm">
                <i class="fa fa-arrow-left"></i> Form Configs
            </a>
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                    placeholder="Search name, email, reference…" style="min-width:220px;">
                <select name="status" class="form-control form-control-sm">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $val => $label)
                        <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="per_page" class="form-control form-control-sm">
                    @foreach ([10, 25, 50, 100] as $pp)
                        <option value="{{ $pp }}" {{ (int) request('per_page', 25) === $pp ? 'selected' : '' }}>{{ $pp }}/page</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button>
                @if (request('q') || request('status'))
                    <a href="{{ route('admin.onboarding.submissions.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                @endif
            </form>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Reference</th>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Emails Sent</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($submissions as $sub)
                        <tr>
                            <td><code>{{ $sub->reference_number }}</code></td>
                            <td>{{ $sub->applicant_name ?: '—' }}</td>
                            <td>{{ $sub->applicant_email ?: '—' }}</td>
                            <td>
                                @php
                                    $statusClass = match($sub->status) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'reviewing' => 'warning',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $statusClass }}">{{ $sub->getStatusLabel() }}</span>
                            </td>
                            <td>
                                @if ($sub->email_sent_to_admin) <span class="badge bg-success" title="Admin">A</span> @else <span class="badge bg-secondary" title="Admin">A</span> @endif
                                @if ($sub->email_sent_to_applicant) <span class="badge bg-success" title="Applicant">P</span> @else <span class="badge bg-secondary" title="Applicant">P</span> @endif
                            </td>
                            <td>{{ $sub->submitted_at->format('d M Y H:i') }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.onboarding.submissions.show', $sub->id) }}" class="btn btn-sm btn-info me-1" title="View">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.onboarding.submissions.pdf', $sub->id) }}" class="btn btn-sm btn-secondary me-1" title="Download PDF">
                                    <i class="fa fa-file-pdf"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.onboarding.submissions.destroy', $sub->id) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="Delete" data-confirm-submit="delete this submission and all its files">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No submissions yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $submissions->appends(request()->query())->links() }}</div>
    </div>
</x-layouts.page-wrapper>
@endsection
