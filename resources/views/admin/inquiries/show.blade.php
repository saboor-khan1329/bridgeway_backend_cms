@php
    $isFormsRoute = request()->routeIs('admin.forms.*');
    $indexRoute = $isFormsRoute ? route('admin.forms.index') : route('admin.inquiries.index');
    $indexLabel = $isFormsRoute ? 'Forms Management' : 'Inquiries';
    $deleteRoute = $isFormsRoute ? route('admin.forms.destroy', $inquiry->id) : route('admin.inquiries.destroy', $inquiry->id);
    $statusRoute = $isFormsRoute ? route('admin.forms.status.patch', $inquiry->id) : route('admin.inquiries.status', $inquiry->id);
    $attachmentRoute = $isFormsRoute ? route('admin.forms.attachment', $inquiry) : route('admin.inquiries.attachment', $inquiry);
@endphp

@extends('template.main')
@section('title', $title)

@section('content')
    <x-layouts.page-wrapper :title="$title" :breadcrumbs="[$indexLabel => $indexRoute, '#'.$inquiry->id => null]">
        <div class="col-12">
            <div class="d-flex gap-2 mb-3">
                <a href="{{ $indexRoute }}" class="btn btn-warning btn-sm">
                    <i class="fa fa-arrow-left"></i> Back to {{ $indexLabel }}
                </a>
                @if ($inquiry->email)
                    <a href="mailto:{{ $inquiry->email }}" class="btn btn-success btn-sm">
                        <i class="fa fa-reply"></i> Reply by Email
                    </a>
                @endif
                <form method="POST" action="{{ $deleteRoute }}" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm" data-confirm-submit="delete">
                        <i class="fa fa-trash"></i> Delete
                    </button>
                </form>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th style="width:140px">Name</th><td>{{ $inquiry->name }}</td></tr>
                                <tr><th>Email</th><td>@if ($inquiry->email)<a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a>@else -- @endif</td></tr>
                                <tr><th>Phone</th><td>{{ $inquiry->phone ?: '--' }}</td></tr>
                                <tr><th>Form</th><td>{{ $inquiry->form_name ?: '--' }}</td></tr>
                                <tr><th>Subject</th><td>{{ $inquiry->subject ?: '--' }}</td></tr>
                                <tr><th>Type of Service Required</th><td>{{ $inquiry->type_of_service_required }}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th style="width:140px">Status</th>
                                    <td>
                                        {!! formatCell($inquiry, 'status') !!}
                                        @if ($inquiry->status === \App\Models\Inquiry::STATUS_SPAM)
                                            &nbsp;<small class="text-danger">(score: {{ $inquiry->spam_score ?? 0 }})</small>
                                        @endif
                                    </td>
                                </tr>
                                <tr><th>Source URL</th><td class="text-break">{{ $inquiry->source_url ?: '--' }}</td></tr>
                                <tr><th>IP</th><td>{{ $inquiry->ip ?: '--' }}</td></tr>
                                <tr><th>Spam Score</th><td>{{ $inquiry->spam_score ?? 0 }}</td></tr>
                                <tr><th>Captcha</th><td>{{ $inquiry->captcha_passed ? 'Passed' : 'Not verified' }}</td></tr>
                                <tr><th>Spam Reasons</th><td>{{ collect($inquiry->spam_reasons ?? [])->implode(', ') ?: '--' }}</td></tr>
                                <tr><th>Received</th><td>{{ $inquiry->created_at->format('d M Y H:i') }}</td></tr>
                            </table>
                        </div>
                    </div>

                    <h6 class="mt-3">Message</h6>
                    <div class="border rounded p-3 bg-light" style="white-space: pre-wrap;">{{ $inquiry->message }}</div>

                    @php($details = collect($inquiry->details ?? [])->except('attachment'))
                    @if ($details->isNotEmpty())
                        <h6 class="mt-4">Additional Details</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    @foreach ($details as $field => $value)
                                        <tr>
                                            <th style="width:180px">{{ \Illuminate\Support\Str::headline($field) }}</th>
                                            <td class="text-break">{{ is_array($value) ? collect($value)->implode(', ') : $value }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if (is_array(data_get($inquiry->details, 'attachment')))
                        <a class="btn btn-outline-primary mt-3" href="{{ $attachmentRoute }}">
                            Download {{ data_get($inquiry->details, 'attachment.name') }}
                        </a>
                    @endif

                    <h6 class="mt-4">Notification Delivery</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Attempts</th>
                                    <th>Recipients</th>
                                    <th>Last Attempt</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($mailDispatches as $dispatch)
                                    <tr>
                                        <td>{!! formatCell($dispatch, 'status') !!}</td>
                                        <td>{{ $dispatch->attempts }} / {{ $dispatch->max_attempts }}</td>
                                        <td class="text-break">{{ collect($dispatch->recipients ?? [])->implode(', ') ?: '--' }}</td>
                                        <td>{{ $dispatch->last_attempt_at?->format('d M Y H:i') ?: '--' }}</td>
                                        <td class="text-break">{{ $dispatch->error_message ?: '--' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted">No notification dispatch has been recorded for this inquiry.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <form action="{{ $statusRoute }}" method="POST" class="d-flex gap-2 align-items-center">
                        @csrf @method('PATCH')
                        <label class="me-2 mb-0 small text-muted">Update status:</label>
                        <select name="status" class="form-select form-select-sm" style="width: 200px;">
                            @foreach (\App\Models\Inquiry::STATUSES as $status)
                                <option value="{{ $status }}" {{ $inquiry->status === $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                        <button class="btn btn-primary btn-sm">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
