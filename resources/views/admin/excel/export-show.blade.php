@extends('template.main')

@section('title', 'Export #'.$export->id)

@section('content')
    <x-layouts.page-wrapper :title="'Export #'.$export->id" :breadcrumbs="['Excel Management' => route('admin.excel.index'), 'Export' => null]">
        <div class="col-12">
            <div class="d-flex justify-content-between mb-3">
                <a href="{{ route('admin.excel.index') }}" class="btn btn-warning btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
                @if ($export->status === 'failed')
                    <form action="{{ route('admin.excel.exports.retry', $export) }}" method="POST">
                        @csrf
                        <button class="btn btn-primary btn-sm"><i class="fas fa-redo"></i> Retry</button>
                    </form>
                @endif
            </div>
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-md-3">Resource</dt><dd class="col-md-9">{{ $export->resource }}</dd>
                        <dt class="col-md-3">Status</dt><dd class="col-md-9">{{ $export->status }}</dd>
                        <dt class="col-md-3">Rows</dt><dd class="col-md-9">{{ $export->exported_rows }}</dd>
                        <dt class="col-md-3">Columns</dt><dd class="col-md-9">{{ implode(', ', $export->columns ?? []) ?: 'Default' }}</dd>
                        <dt class="col-md-3">Filters</dt><dd class="col-md-9"><pre class="small mb-0">{{ json_encode($export->filters ?? [], JSON_PRETTY_PRINT) }}</pre></dd>
                        <dt class="col-md-3">File</dt>
                        <dd class="col-md-9">
                            @if ($export->status === 'completed')
                                <a href="{{ route('admin.excel.download', $export) }}">{{ $export->filename }}</a>
                            @else
                                {{ $export->filename }}
                            @endif
                        </dd>
                        <dt class="col-md-3">Failure</dt><dd class="col-md-9 text-danger">{{ $export->failure_message ?: 'None' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection
