@extends('template.main')
@section('title', $title)

@section('content')
<x-layouts.page-wrapper :title="$title"
    :breadcrumbs="['Submissions' => route('admin.onboarding.submissions.index'), $title => null]">
    <div class="col-12">
        {{-- Top Actions --}}
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('admin.onboarding.submissions.index') }}" class="btn btn-warning btn-sm">
                <i class="fa fa-arrow-left"></i> Back
            </a>
            <a href="{{ route('admin.onboarding.submissions.pdf', $submission->id) }}"
               class="btn btn-secondary btn-sm" target="_blank">
                <i class="fa fa-file-pdf"></i> Download PDF
            </a>
            @if ($submission->applicant_email)
            <a href="mailto:{{ $submission->applicant_email }}" class="btn btn-success btn-sm">
                <i class="fa fa-envelope"></i> Email Applicant
            </a>
            @endif
            <form method="POST" action="{{ route('admin.onboarding.submissions.destroy', $submission->id) }}" class="d-inline">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" data-confirm-submit="permanently delete this submission and all files">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </form>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
        @endif

        <div class="row g-3">
            {{-- Left: Meta + Status --}}
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header fw-semibold">Submission Summary</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><th style="width:130px">Reference</th><td><code>{{ $submission->reference_number }}</code></td></tr>
                            <tr><th>Applicant</th><td>{{ $submission->applicant_name ?: '—' }}</td></tr>
                            <tr><th>Email</th><td>{{ $submission->applicant_email ?: '—' }}</td></tr>
                            <tr><th>Submitted</th><td>{{ $submission->submitted_at->format('d M Y H:i') }}</td></tr>
                            <tr><th>IP</th><td>{{ $submission->ip_address ?: '—' }}</td></tr>
                            <tr><th>Admin Email</th><td>{{ $submission->email_sent_to_admin ? '<span class="badge bg-success">Sent</span>' : '<span class="badge bg-secondary">Not sent</span>' }}</td></tr>
                            <tr><th>Applicant Email</th><td>{{ $submission->email_sent_to_applicant ? '<span class="badge bg-success">Sent</span>' : '<span class="badge bg-secondary">Not sent</span>' }}</td></tr>
                            <tr><th>Form Config</th><td>{{ $submission->formConfig?->name ?? '—' }}</td></tr>
                        </table>
                    </div>
                </div>

                {{-- Status Update --}}
                <div class="card">
                    <div class="card-header fw-semibold">Status &amp; Notes</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.onboarding.submissions.status', $submission->id) }}">
                            @csrf @method('PATCH')
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    @foreach ($statuses as $val => $label)
                                        <option value="{{ $val }}" {{ $submission->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Admin Notes</label>
                                <textarea name="admin_notes" class="form-control form-control-sm" rows="4">{{ $submission->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="fa fa-save"></i> Update Status
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Right: Field Data + Files --}}
            <div class="col-lg-8">
                @php $steps = $submission->getSnapshotSteps(); @endphp

                @foreach ($steps as $step)
                    @php
                        $stepId = $step['id'];
                        $stepData = $submission->getStepData($stepId);
                        $stepFiles = $submission->files->filter(fn($f) => str_starts_with($f->field_key, $stepId));
                    @endphp

                    <div class="card mb-3">
                        <div class="card-header fw-semibold" style="background:#1a1a2e;color:#fff;">
                            {{ $step['title'] ?? $stepId }}
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                @foreach ($step['fields'] ?? [] as $field)
                                    @php
                                        $key   = $field['key'];
                                        $type  = $field['type'] ?? 'text';
                                        $label = $field['label'] ?? $key;
                                    @endphp

                                    @if (in_array($type, ['static_text', 'section_header', 'group_validation']))
                                        @continue
                                    @endif

                                    @if ($type === 'file' || $type === 'file_pair')
                                        @php $fFiles = $submission->files->filter(fn($f) => str_starts_with($f->field_key, $key)); @endphp
                                        <tr>
                                            <th style="width:35%;padding:8px 12px;">{{ $label }}</th>
                                            <td style="padding:8px 12px;">
                                                @forelse ($fFiles as $f)
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <i class="fa fa-{{ $f->isImage() ? 'image' : 'file-pdf' }} text-secondary"></i>
                                                        <a href="{{ route('admin.onboarding.submissions.file', [$submission->id, $f->id]) }}"
                                                            class="text-decoration-none">
                                                            {{ $f->original_name }}
                                                        </a>
                                                        <small class="text-muted">({{ $f->formatSize() }})</small>
                                                    </div>
                                                @empty
                                                    <span class="text-muted">—</span>
                                                @endforelse
                                            </td>
                                        </tr>

                                    @elseif ($type === 'repeatable')
                                        @php $items = $stepData[$key] ?? []; @endphp
                                        @if (is_array($items) && count($items) > 0)
                                            @foreach ($items as $i => $itemData)
                                                <tr class="table-light">
                                                    <td colspan="2" class="fw-semibold px-3 py-2" style="font-size:12px;color:#444;">
                                                        {{ $label }} #{{ $i + 1 }}
                                                    </td>
                                                </tr>
                                                @foreach ($field['fields'] ?? [] as $child)
                                                    <tr>
                                                        <th style="width:35%;padding:6px 12px 6px 24px;font-size:12px;">{{ $child['label'] ?? $child['key'] }}</th>
                                                        <td style="padding:6px 12px;font-size:12px;">
                                                            @php $v = $itemData[$child['key']] ?? ''; @endphp
                                                            @if ($v !== '' && $v !== null)
                                                                {!! nl2br(e((string) $v)) !!}
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        @else
                                            <tr>
                                                <th style="width:35%;padding:8px 12px;">{{ $label }}</th>
                                                <td style="padding:8px 12px;"><span class="text-muted">No records</span></td>
                                            </tr>
                                        @endif

                                    @else
                                        @php $raw = $stepData[$key] ?? ''; @endphp
                                        <tr>
                                            <th style="width:35%;padding:8px 12px;">{{ $label }}</th>
                                            <td style="padding:8px 12px;">
                                                @if ($type === 'checkbox')
                                                    @if ($raw === true || $raw === '1' || $raw === 'true')
                                                        <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
                                                    @else
                                                        <span class="badge bg-secondary">No</span>
                                                    @endif
                                                @elseif ($raw !== '' && $raw !== null)
                                                    {!! nl2br(e((string) $raw)) !!}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.page-wrapper>
@endsection
