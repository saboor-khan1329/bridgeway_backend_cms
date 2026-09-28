<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExcelExportJob;
use App\Jobs\ProcessExcelImportJob;
use App\Models\ExcelExport;
use App\Models\ExcelImport;
use App\Services\ExcelManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ExcelManagementController extends Controller
{
    public function index(ExcelManagementService $service)
    {
        return view('admin.excel.index', [
            'imports' => ExcelImport::query()->latest()->paginate(10, ['*'], 'imports_page'),
            'exports' => ExcelExport::query()->latest()->paginate(10, ['*'], 'exports_page'),
            'resources' => $service->resourceOptions(),
            'lookups' => collect(config('excel_management.resources'))->mapWithKeys(
                fn ($config, $key) => [$key => $config['lookup_fields']]
            ),
            'columns' => collect(config('excel_management.resources'))->mapWithKeys(
                fn ($config, $key) => [$key => array_keys($service->columnOptions($key))]
            ),
        ]);
    }

    public function import(Request $request)
    {
        $resources = array_keys(config('excel_management.resources', []));
        $validated = $request->validate([
            'resource' => ['required', Rule::in($resources)],
            'lookup_field' => 'required|string|max:64',
            'mode' => ['required', Rule::in(['update_or_create', 'update_only'])],
            'operation' => ['required', Rule::in(['create', 'update', 'relations'])],
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'dry_run' => 'nullable|boolean',
        ]);

        $config = config('excel_management.resources.'.$validated['resource']);

        if (! in_array($validated['lookup_field'], $config['lookup_fields'], true)) {
            throw ValidationException::withMessages([
                'lookup_field' => 'Select a lookup field allowed for this resource.',
            ]);
        }

        if ($validated['operation'] === 'relations') {
            $validated['mode'] = 'update_only';
        }

        $path = $request->file('file')->store('excel/imports');
        $import = ExcelImport::query()->create([
            'resource' => $validated['resource'],
            'lookup_field' => $validated['lookup_field'],
            'mode' => $validated['mode'],
            'dry_run' => (bool) ($validated['dry_run'] ?? false),
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'stored_path' => $path,
            'status' => 'pending',
            'metadata' => [
                'mime' => $request->file('file')->getClientMimeType(),
                'size' => $request->file('file')->getSize(),
                'operation' => $validated['operation'],
            ],
            'created_by' => $request->user()?->id,
        ]);

        ProcessExcelImportJob::dispatch($import->id);

        return redirect()->route('admin.excel.imports.show', $import)->with('success', 'Import queued.');
    }

    public function export(Request $request, ExcelManagementService $service)
    {
        $resources = array_keys(config('excel_management.resources', []));
        $validated = $request->validate([
            'resource' => ['required', Rule::in($resources)],
            'columns' => 'nullable|array',
            'columns.*' => 'string|max:64',
            'filters' => 'nullable|string|max:10000',
            'export_type' => ['nullable', Rule::in(['quick', 'custom_query'])],
        ]);

        $filters = $service->parseFilters($validated['filters'] ?? null);
        $allowedColumns = array_keys($service->columnOptions($validated['resource']));
        $columns = array_values(array_intersect($validated['columns'] ?? [], $allowedColumns));

        $export = ExcelExport::query()->create([
            'resource' => $validated['resource'],
            'filename' => $validated['resource'].'-export-'.now()->format('Ymd-His').'.xlsx',
            'status' => 'pending',
            'columns' => $columns,
            'filters' => $filters,
            'metadata' => ['export_type' => $validated['export_type'] ?? 'quick'],
            'created_by' => $request->user()?->id,
            'disk' => 'local',
        ]);

        ProcessExcelExportJob::dispatch($export->id);

        return redirect()->route('admin.excel.exports.show', $export)->with('success', 'Export queued.');
    }

    public function download(ExcelExport $export)
    {
        abort_unless($export->status === 'completed' && $export->stored_path, 404);
        abort_unless(Storage::disk($export->disk ?: 'local')->exists($export->stored_path), 404);

        return Storage::disk($export->disk ?: 'local')->download($export->stored_path, $export->filename);
    }

    public function template(Request $request, string $resource, ExcelManagementService $service)
    {
        abort_unless(array_key_exists($resource, config('excel_management.resources', [])), 404);

        $operation = $request->validate([
            'operation' => ['nullable', Rule::in(['create', 'update', 'relations'])],
            'lookup_field' => 'nullable|string|max:64',
        ]);
        $lookupField = $operation['lookup_field'] ?? null;

        if ($lookupField && ! in_array($lookupField, config('excel_management.resources.'.$resource.'.lookup_fields', []), true)) {
            throw ValidationException::withMessages([
                'lookup_field' => 'Select a lookup field allowed for this resource.',
            ]);
        }

        $operation = $operation['operation'] ?? 'update';

        return Excel::download(
            $service->templateExport($resource, $operation, $lookupField),
            $resource.'-'.$operation.'-template.xlsx'
        );
    }

    public function showImport(ExcelImport $import)
    {
        return view('admin.excel.import-show', ['import' => $import]);
    }

    public function showExport(ExcelExport $export)
    {
        return view('admin.excel.export-show', ['export' => $export]);
    }

    public function retryImport(ExcelImport $import)
    {
        abort_unless(in_array($import->status, ['failed', 'validated'], true), 404);

        $import->update([
            'status' => 'pending',
            'processed_rows' => 0,
            'failed_rows' => 0,
            'row_errors' => [],
            'failure_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        ProcessExcelImportJob::dispatch($import->id);

        return back()->with('success', 'Import retry queued.');
    }

    public function retryExport(ExcelExport $export)
    {
        abort_unless($export->status === 'failed', 404);

        $export->update([
            'status' => 'pending',
            'failure_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        ProcessExcelExportJob::dispatch($export->id);

        return back()->with('success', 'Export retry queued.');
    }

    public function downloadImportErrors(ExcelImport $import)
    {
        abort_unless($import->error_report_path && Storage::exists($import->error_report_path), 404);

        return Storage::download($import->error_report_path, 'import-'.$import->id.'-errors.xlsx');
    }
}
