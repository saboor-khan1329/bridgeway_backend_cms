@extends('template.main')
@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title"
        :breadcrumbs="['Service Finder' => route('admin.finder.dashboard'), 'Quote Requests' => route('admin.finder-leads.index'), '#'.$lead->id => null]">

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">{{ $lead->name }}</h3>
                    <a href="{{ route('admin.finder-leads.index') }}" class="btn btn-sm btn-warning">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped mb-0">
                        <tr><th style="width: 200px;">Phone</th>
                            <td><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></td></tr>
                        <tr><th>Email</th>
                            <td><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></td></tr>
                        <tr><th>Company</th><td>{{ $lead->company ?: '—' }}</td></tr>
                        <tr><th>Service required</th><td>{{ $lead->service_label ?: '—' }}</td></tr>
                        <tr><th>Postcode / area</th><td>{{ $lead->postcode_or_area }}</td></tr>
                        <tr><th>Coverage area</th>
                            <td>
                                @if ($lead->area)
                                    <a href="{{ route('admin.finder-areas.coverage.edit', $lead->area) }}">{{ $lead->area->name }}</a>
                                @else
                                    <span class="text-muted">Not matched to a coverage area</span>
                                @endif
                            </td></tr>
                        <tr><th>Start date</th><td>{{ $lead->start_date ?: 'Not specified' }}</td></tr>
                        <tr><th>Came from</th><td class="text-capitalize">{{ str_replace('_', ' ', $lead->origin) }}</td></tr>
                        <tr><th>Page</th><td class="small">{{ data_get($lead->meta, 'source_url') ?: '—' }}</td></tr>
                        <tr><th>Received</th><td>{{ $lead->created_at?->format('d M Y H:i') }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Progress</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.finder-leads.status', $lead) }}" class="mb-3">
                        @csrf @method('PATCH')
                        <select name="status" class="form-select form-select-sm mb-2">
                            @foreach (\App\Models\FinderLead::STATUSES as $status)
                                <option value="{{ $status }}" @selected($lead->status === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-success w-100">Update status</button>
                    </form>

                    <form method="POST" action="{{ route('admin.finder-leads.resend', $lead) }}" class="mb-2">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary w-100">
                            <i class="fa fa-paper-plane"></i> Re-send admin notification
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.finder-leads.destroy', $lead) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger w-100" data-confirm-submit="delete">
                            <i class="fa fa-trash"></i> Delete request
                        </button>
                    </form>
                </div>
            </div>

            @if ($lead->spam_score > 0)
                <div class="card mb-3 border-warning">
                    <div class="card-header"><h3 class="card-title mb-0">Spam assessment</h3></div>
                    <div class="card-body">
                        <p class="mb-2">Score <strong>{{ $lead->spam_score }}</strong></p>
                        <ul class="mb-0 small">
                            @foreach ((array) $lead->spam_reasons as $reason)
                                <li>{{ str_replace('_', ' ', $reason) }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Technical</h3></div>
                <div class="card-body small text-muted">
                    <div>IP: {{ data_get($lead->meta, 'ip') ?: '—' }}</div>
                    <div class="text-break">Agent: {{ data_get($lead->meta, 'user_agent') ?: '—' }}</div>
                    <div>Captcha: {{ $lead->captcha_passed ? 'passed' : 'not used' }}</div>
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
