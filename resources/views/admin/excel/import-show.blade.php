@extends('template.main')

@section('title', 'Import #'.$import->id)

@section('content')
    <x-layouts.page-wrapper :title="'Import #'.$import->id" :breadcrumbs="['Excel Management' => route('admin.excel.index'), 'Import' => null]">
        <div class="col-12">
            <div class="d-flex justify-content-between mb-3">
                <a href="{{ route('admin.excel.index') }}" class="btn btn-warning btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
                @if (in_array($import->status, ['failed', 'validated'], true))
                    <form action="{{ route('admin.excel.imports.retry', $import) }}" method="POST">
                        @csrf
                        <button class="btn btn-primary btn-sm"><i class="fas fa-redo"></i> Retry</button>
                    </form>
                @endif
            </div>
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-md-3">Resource</dt><dd class="col-md-9">{{ $import->resource }}</dd>
                        <dt class="col-md-3">Status</dt><dd class="col-md-9">{{ $import->status }}</dd>
                        <dt class="col-md-3">Mode</dt><dd class="col-md-9">{{ $import->mode }}{{ $import->dry_run ? ' (dry run)' : '' }}</dd>
                        <dt class="col-md-3">Lookup Field</dt><dd class="col-md-9">{{ $import->lookup_field }}</dd>
                        <dt class="col-md-3">Rows</dt><dd class="col-md-9">{{ $import->processed_rows }}/{{ $import->total_rows }} processed, {{ $import->failed_rows }} failed</dd>
                        <dt class="col-md-3">Failure</dt><dd class="col-md-9 text-danger">{{ $import->failure_message ?: 'None' }}</dd>
                        <dt class="col-md-3">Error Report</dt>
                        <dd class="col-md-9">
                            @if ($import->error_report_path)
                                <a href="{{ route('admin.excel.imports.errors', $import) }}">Download row error workbook</a>
                            @else
                                None
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Row Errors</strong></div>
                <div class="card-body">
                    @forelse (($import->row_errors ?? []) as $rowError)
                        <details class="mb-2">
                            <summary>Row {{ $rowError['row'] ?? '?' }} - lookup {{ $rowError['lookup'] ?? 'n/a' }}</summary>
                            <pre class="small bg-light border rounded p-2 mt-2">{{ json_encode($rowError['errors'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                        </details>
                    @empty
                        <div class="text-muted">No row errors.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
