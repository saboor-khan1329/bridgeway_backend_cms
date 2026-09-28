@extends('template.main')

@section('title', 'Excel Management')

@section('content')
    <x-layouts.page-wrapper title="Excel Management" :breadcrumbs="['Excel Management' => null]">
        <div class="col-12">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="col-12" id="excel-dashboard">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase">Resources</div>
                            <div class="h3 mb-1">{{ count($resources) }}</div>
                            <div class="small text-muted">Visible import/export resource types.</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase">Recent Imports</div>
                            <div class="h3 mb-1">{{ number_format($imports->total()) }}</div>
                            <div class="small text-muted">Create, update, and relation jobs.</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase">Recent Exports</div>
                            <div class="h3 mb-1">{{ number_format($exports->total()) }}</div>
                            <div class="small text-muted">Quick and filtered export jobs.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12" id="excel-imports">
            <h4 class="mb-2">Imports</h4>
            <p class="text-muted mb-3">Use create for new records, update for existing records, and relation import for linked IDs.</p>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>Create Import</strong></div>
                <form action="{{ route('admin.excel.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="operation" value="create">
                    <input type="hidden" name="mode" value="update_or_create">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Resource</label>
                            <select name="resource" class="form-select js-excel-resource" data-target="create-lookup" data-operation="create" required>
                                @foreach ($resources as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                <a href="#" data-template-link>Download create template</a>
                            </div>
                        </div>
                        <input type="hidden" name="lookup_field" id="create-lookup-hidden" value="">
                        <div class="mb-3">
                            <label class="form-label">Excel / CSV File</label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv,.txt" required>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="dry_run" value="1" class="form-check-input" id="create-dry-run">
                            <label class="form-check-label" for="create-dry-run">Dry run only</label>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Queue Create</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>Update Import</strong></div>
                <form action="{{ route('admin.excel.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="operation" value="update">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Resource</label>
                            <select name="resource" class="form-select js-excel-resource" data-target="update-lookup" data-operation="update" required>
                                @foreach ($resources as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                <a href="#" data-template-link>Download update template</a>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lookup Field</label>
                            <select name="lookup_field" id="update-lookup" class="form-select js-template-lookup" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mode</label>
                            <select name="mode" class="form-select" required>
                                <option value="update_only">Update existing only</option>
                                <option value="update_or_create">Update or create</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Excel / CSV File</label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv,.txt" required>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="dry_run" value="1" class="form-check-input" id="update-dry-run">
                            <label class="form-check-label" for="update-dry-run">Dry run only</label>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Queue Update</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>Relation Import</strong></div>
                <form action="{{ route('admin.excel.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="operation" value="relations">
                    <input type="hidden" name="mode" value="update_only">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Resource</label>
                            <select name="resource" class="form-select js-excel-resource" data-target="relation-lookup" data-operation="relations" required>
                                @foreach ($resources as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                <a href="#" data-template-link>Download relation template</a>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lookup Field</label>
                            <select name="lookup_field" id="relation-lookup" class="form-select js-template-lookup" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Excel / CSV File</label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv,.txt" required>
                            <div class="form-text">Relation cells accept IDs like 1,2,3. Use clear or [] to remove all linked records.</div>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="dry_run" value="1" class="form-check-input" id="relation-dry-run">
                            <label class="form-check-label" for="relation-dry-run">Dry run only</label>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-success"><i class="fas fa-link"></i> Queue Relations</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12" id="excel-exports">
            <h4 class="mb-2">Exports</h4>
            <p class="text-muted mb-3">Quick export selects resource columns; custom query export can apply simple JSON filters.</p>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><strong>Quick Export</strong></div>
                <form action="{{ route('admin.excel.export') }}" method="POST">
                    @csrf
                    <input type="hidden" name="export_type" value="quick">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Resource</label>
                            <select name="resource" class="form-select js-excel-resource" data-target="quick-export-columns" required>
                                @foreach ($resources as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Columns</label>
                            <select name="columns[]" id="quick-export-columns" class="form-select" multiple size="10"></select>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-download"></i> Queue Quick Export</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><strong>Custom Query Export</strong></div>
                <form action="{{ route('admin.excel.export') }}" method="POST">
                    @csrf
                    <input type="hidden" name="export_type" value="custom_query">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Resource</label>
                            <select name="resource" class="form-select js-excel-resource" data-target="custom-export-columns" required>
                                @foreach ($resources as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Columns</label>
                            <select name="columns[]" id="custom-export-columns" class="form-select" multiple size="8"></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Filters JSON</label>
                            <textarea name="filters" class="form-control" rows="5" placeholder='[{"column":"status","operator":"=","value":1}]'></textarea>
                            <div class="form-text">Allowed operators: =, !=, &gt;, &gt;=, &lt;, &lt;=, like.</div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Queue Custom Export</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><strong>Recent Imports</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>ID</th><th>Resource</th><th>Status</th><th>Rows</th><th>File</th><th>Errors</th><th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($imports as $import)
                                <tr>
                                    <td>{{ $import->id }}</td>
                                    <td>{{ $import->resource }}</td>
                                    <td><span class="badge bg-{{ in_array($import->status, ['completed', 'validated'], true) ? 'success' : ($import->status === 'failed' ? 'danger' : 'secondary') }}">{{ $import->status }}</span></td>
                                    <td>{{ $import->processed_rows }}/{{ $import->total_rows }} processed, {{ $import->failed_rows }} failed</td>
                                    <td><a href="{{ route('admin.excel.imports.show', $import) }}">{{ $import->original_filename }}</a></td>
                                    <td style="min-width: 260px;">
                                        @if ($import->failure_message)
                                            <div class="text-danger">{{ $import->failure_message }}</div>
                                        @endif
                                        @if ($import->error_report_path)
                                            <a class="btn btn-outline-danger btn-xs mb-1" href="{{ route('admin.excel.imports.errors', $import) }}">Download error file</a>
                                        @endif
                                        @foreach (($import->row_errors ?? []) as $rowError)
                                            <details class="small">
                                                <summary>Row {{ $rowError['row'] ?? '?' }}</summary>
                                                <pre class="mb-0 small">{{ json_encode($rowError['errors'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                            </details>
                                        @endforeach
                                    </td>
                                    <td>{{ $import->created_at?->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center">No imports yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $imports->links() }}</div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><strong>Recent Exports</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>ID</th><th>Resource</th><th>Status</th><th>Rows</th><th>File</th><th>Error</th><th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($exports as $export)
                                <tr>
                                    <td>{{ $export->id }}</td>
                                    <td>{{ $export->resource }}</td>
                                    <td><span class="badge bg-{{ $export->status === 'completed' ? 'success' : ($export->status === 'failed' ? 'danger' : 'secondary') }}">{{ $export->status }}</span></td>
                                    <td>{{ $export->exported_rows }}</td>
                                    <td>
                                        @if ($export->status === 'completed')
                                            <a href="{{ route('admin.excel.download', $export) }}">{{ $export->filename }}</a>
                                        @else
                                            <a href="{{ route('admin.excel.exports.show', $export) }}">{{ $export->filename }}</a>
                                        @endif
                                    </td>
                                    <td class="text-danger">{{ $export->failure_message }}</td>
                                    <td>{{ $export->created_at?->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center">No exports yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $exports->links() }}</div>
            </div>
        </div>
    </x-layouts.page-wrapper>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lookups = @json($lookups);
            const columns = @json($columns);

            const fill = function (select, values, selectedAll = false) {
                select.innerHTML = '';
                values.forEach(function (value) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    option.selected = selectedAll;
                    select.appendChild(option);
                });
            };

            const refreshTemplateLink = function (form) {
                const resourceSelect = form.querySelector('.js-excel-resource');
                const templateLink = form.querySelector('[data-template-link]');

                if (!resourceSelect || !templateLink) {
                    return;
                }

                const resource = resourceSelect.value;
                const operation = resourceSelect.dataset.operation || 'update';
                const lookup = form.querySelector('.js-template-lookup')?.value || (lookups[resource] || [])[0] || 'id';
                templateLink.href = `{{ url('/admin/excel/templates') }}/${resource}?operation=${operation}&lookup_field=${lookup}`;
            };

            document.querySelectorAll('.js-excel-resource').forEach(function (resourceSelect) {
                const target = document.getElementById(resourceSelect.dataset.target);
                const refresh = function () {
                    const resource = resourceSelect.value;
                    if (target) {
                        const isLookup = resourceSelect.dataset.target.includes('lookup');
                        fill(target, isLookup ? (lookups[resource] || []) : (columns[resource] || []), !isLookup);
                    }
                    const hiddenCreateLookup = resourceSelect.closest('form')?.querySelector('#create-lookup-hidden');
                    if (hiddenCreateLookup) {
                        hiddenCreateLookup.value = (lookups[resource] || [])[0] || 'id';
                    }
                    refreshTemplateLink(resourceSelect.closest('form'));
                };
                resourceSelect.addEventListener('change', refresh);
                refresh();
            });

            document.querySelectorAll('.js-template-lookup').forEach(function (lookupSelect) {
                lookupSelect.addEventListener('change', function () {
                    refreshTemplateLink(lookupSelect.closest('form'));
                });
            });
        });
    </script>
@endpush
