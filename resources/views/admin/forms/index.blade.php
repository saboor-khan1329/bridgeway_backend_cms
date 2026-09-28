@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title" :breadcrumbs="['Forms Management' => null]">
    {{-- Summary Statistics --}}
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100" style="border-left: 4px solid #FF763A !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 mr-3 me-3" style="background: rgba(255, 118, 58, 0.12); color: #FF763A;">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                    </div>
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold">Total Submissions</span>
                        <h3 class="mb-0 font-weight-bold" style="color: #1a2733;">{{ number_format($totalSubmissions) }}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100" style="border-left: 4px solid #28a745 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 mr-3 me-3" style="background: rgba(40, 167, 69, 0.12); color: #28a745;">
                        <i class="fas fa-inbox fa-2x"></i>
                    </div>
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold">New Inquiries</span>
                        <h3 class="mb-0 font-weight-bold" style="color: #1a2733;">{{ number_format($newSubmissions) }}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100" style="border-left: 4px solid #dc3545 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 mr-3 me-3" style="background: rgba(220, 53, 69, 0.12); color: #dc3545;">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold">Spam Filtered</span>
                        <h3 class="mb-0 font-weight-bold" style="color: #1a2733;">{{ number_format($spamCount) }}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100" style="border-left: 4px solid #17a2b8 !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 mr-3 me-3" style="background: rgba(23, 162, 184, 0.12); color: #17a2b8;">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold">Registered Forms</span>
                        <h3 class="mb-0 font-weight-bold" style="color: #1a2733;">{{ count($formsMeta) }} Forms</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Website Forms Overview & Email Integration Configuration --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 font-weight-bold" style="color: #1a2733;">
                <i class="fas fa-envelope-cog me-2" style="color: #FF763A;"></i> Email Integration & Form Notification Routing
            </h5>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#emailSettingsCollapse">
                <i class="fas fa-cog me-1"></i> Configure Recipient Emails
            </button>
        </div>
        <div class="collapse show" id="emailSettingsCollapse">
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Configure which email address(es) receive notifications when visitors submit forms across different website and service pages. You can set a global fallback email or assign distinct recipients to specific forms (e.g. quote requests to sales, store audits to Amazon team).
                </p>

                <form action="{{ route('admin.forms.email-settings') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold">Default Global Notification Recipient(s)</label>
                            <input type="text" name="default_email" class="form-control form-control-sm"
                                placeholder="e.g. sales@bridgewaydigital.com, team@bridgewaydigital.com"
                                value="{{ old('default_email', $emailSettings['default']) }}">
                            <small class="text-muted">Separate multiple emails with commas. Used if no form-specific recipient is configured.</small>
                        </div>
                    </div>

                    <div class="row">
                        @foreach($formsMeta as $key => $meta)
                            <div class="col-md-6 mb-3">
                                <div class="p-3 border rounded bg-light h-100">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="{{ $meta['icon'] }} me-2 text-{{ $meta['badge'] }}"></i>
                                        <strong style="color: #1a2733;">{{ $meta['name'] }}</strong>
                                        <span class="badge bg-secondary ms-auto text-white">{{ $meta['key'] }}</span>
                                    </div>
                                    <div class="small text-muted mb-2"><i class="fas fa-map-pin me-1"></i> {{ $meta['location'] }}</div>
                                    <label class="form-label small mb-1">Target Recipient Email(s):</label>
                                    <input type="text" name="form_emails[{{ $key }}]" class="form-control form-control-sm"
                                        placeholder="Defaults to global email if left empty"
                                        value="{{ old("form_emails.{$key}", $emailSettings[$key]) }}">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-sm btn-primary" style="background-color: #FF763A; border-color: #FF763A;">
                            <i class="fas fa-save me-1"></i> Save Notification Email Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Form Submissions Management --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            {{-- Tabs by Form --}}
            <ul class="nav nav-pills card-header-pills mb-3 flex-wrap">
                <li class="nav-item">
                    <a class="nav-link {{ empty($selectedForm) ? 'active' : '' }}" href="{{ route('admin.forms.index', array_merge(request()->except('form', 'page'), [])) }}">
                        All Forms <span class="badge bg-light text-dark ms-1">{{ $totalSubmissions }}</span>
                    </a>
                </li>
                @foreach($formsMeta as $key => $meta)
                    <li class="nav-item">
                        <a class="nav-link {{ $selectedForm === $key ? 'active' : '' }}" href="{{ route('admin.forms.index', array_merge(request()->except('form', 'page'), ['form' => $key])) }}">
                            <i class="{{ $meta['icon'] }} me-1"></i> {{ $meta['name'] }}
                            @if(isset($countsByForm[$key]) && $countsByForm[$key] > 0)
                                <span class="badge bg-light text-dark ms-1">{{ $countsByForm[$key] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Filter and Search Row --}}
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small">Status:</span>
                    <a href="{{ route('admin.forms.index', array_merge(request()->except('status', 'page'), [])) }}"
                        class="btn btn-sm {{ empty($selectedStatus) ? 'btn-secondary' : 'btn-outline-secondary' }}">All</a>
                    <a href="{{ route('admin.forms.index', array_merge(request()->except('status', 'page'), ['status' => 'new'])) }}"
                        class="btn btn-sm {{ $selectedStatus === 'new' ? 'btn-success' : 'btn-outline-success' }}">New</a>
                    <a href="{{ route('admin.forms.index', array_merge(request()->except('status', 'page'), ['status' => 'read'])) }}"
                        class="btn btn-sm {{ $selectedStatus === 'read' ? 'btn-info text-white' : 'btn-outline-info' }}">Read</a>
                    <a href="{{ route('admin.forms.index', array_merge(request()->except('status', 'page'), ['status' => 'replied'])) }}"
                        class="btn btn-sm {{ $selectedStatus === 'replied' ? 'btn-primary' : 'btn-outline-primary' }}">Replied</a>
                    <a href="{{ route('admin.forms.index', array_merge(request()->except('status', 'page'), ['status' => 'archived'])) }}"
                        class="btn btn-sm {{ $selectedStatus === 'archived' ? 'btn-secondary' : 'btn-outline-secondary' }}">Archived</a>
                    <a href="{{ route('admin.forms.index', array_merge(request()->except('status', 'page'), ['status' => 'spam'])) }}"
                        class="btn btn-sm {{ $selectedStatus === 'spam' ? 'btn-danger' : 'btn-outline-danger' }}">Spam</a>
                </div>

                <form method="GET" action="{{ route('admin.forms.index') }}" class="d-flex gap-2">
                    @if($selectedForm)<input type="hidden" name="form" value="{{ $selectedForm }}">@endif
                    @if($selectedStatus)<input type="hidden" name="status" value="{{ $selectedStatus }}">@endif
                    <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm"
                        placeholder="Search submissions..." style="min-width: 220px;">
                    <button type="submit" class="btn btn-sm btn-primary" style="background-color: #FF763A; border-color: #FF763A;">
                        <i class="fas fa-search"></i>
                    </button>
                    @if($search !== '' || $selectedStatus || $selectedForm)
                        <a href="{{ route('admin.forms.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Form</th>
                        <th>Submitter Details</th>
                        <th>Service / Topic</th>
                        <th>Status</th>
                        <th>Date Received</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($submissions as $item)
                    <tr class="{{ is_null($item->read_at) && $item->status === 'new' ? 'table-warning-subtle font-weight-bold' : '' }}">
                        <td>#{{ $item->id }}</td>
                        <td>
                            @php
                                $meta = $formsMeta[$item->form_name] ?? null;
                            @endphp
                            @if($meta)
                                <span class="badge bg-{{ $meta['badge'] }} text-white">
                                    <i class="{{ $meta['icon'] }} me-1"></i> {{ $meta['name'] }}
                                </span>
                            @else
                                <span class="badge bg-secondary">{{ $item->form_name ?: 'Website Form' }}</span>
                            @endif
                            @if($item->hasAttachment())
                                <span class="badge bg-info text-white ms-1" title="Includes File Attachment"><i class="fas fa-paperclip"></i></span>
                            @endif
                        </td>
                        <td>
                            <div><strong>{{ $item->name ?: 'N/A' }}</strong></div>
                            @if($item->email)<div class="small"><a href="mailto:{{ $item->email }}">{{ $item->email }}</a></div>@endif
                            @if($item->phone)<div class="small text-muted"><i class="fas fa-phone fa-xs me-1"></i>{{ $item->phone }}</div>@endif
                        </td>
                        <td>
                            <div>{{ $item->type_of_service_required ?: ($item->subject ?: 'General Inquiry') }}</div>
                            @if($item->message)
                                <div class="small text-muted text-truncate" style="max-width: 250px;">
                                    {{ Str::limit($item->message, 60) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($item->status === 'new')
                                <span class="badge bg-success">New</span>
                            @elseif($item->status === 'read')
                                <span class="badge bg-info text-white">Read</span>
                            @elseif($item->status === 'replied')
                                <span class="badge bg-primary text-white">Replied</span>
                            @elseif($item->status === 'archived')
                                <span class="badge bg-secondary">Archived</span>
                            @elseif($item->status === 'spam')
                                <span class="badge bg-danger">Spam (Score: {{ $item->spam_score }})</span>
                            @else
                                <span class="badge bg-light text-dark">{{ ucfirst($item->status) }}</span>
                            @endif
                        </td>
                        <td class="small text-muted text-nowrap">
                            {{ $item->created_at?->diffForHumans() ?? 'Recently' }}
                            <div class="text-muted" style="font-size: 0.75rem;">{{ $item->created_at?->format('M d, Y H:i') }}</div>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.forms.show', $item) }}" class="btn btn-sm btn-info text-white me-1" title="View details">
                                <i class="fas fa-eye"></i> View
                            </a>
                            @if($item->hasAttachment())
                                <a href="{{ route('admin.forms.attachment', $item) }}" class="btn btn-sm btn-outline-secondary me-1" title="Download Attachment">
                                    <i class="fas fa-download"></i>
                                </a>
                            @endif
                            <form action="{{ route('admin.forms.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this form submission?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <div class="mb-2"><i class="fas fa-inbox fa-3x text-muted opacity-50"></i></div>
                            <p class="mb-0">No submissions found matching the selected criteria.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($submissions->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
</x-layouts.page-wrapper>
@endsection
