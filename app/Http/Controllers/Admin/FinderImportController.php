<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FinderSiteImporter;
use Illuminate\Http\Request;
use Throwable;

/**
 * Import runner.
 *
 * Wraps the same importer the console command uses, so the UI and the CLI can
 * never disagree. An operator uploads an operations export, previews exactly
 * how every row would be classified, and only then commits — which matters
 * because a careless forced import would rebuild the whole site table.
 */
class FinderImportController extends Controller
{
    public function edit()
    {
        return view('admin.finder.import', [
            'title' => 'Import Sites',
            'defaultFile' => (string) config('service_finder.import.default_file'),
            'defaultExists' => is_file((string) config('service_finder.import.default_file')),
            'report' => session('finder_import_report'),
        ]);
    }

    public function run(Request $request, FinderSiteImporter $importer)
    {
        $data = $request->validate([
            'csv' => 'nullable|file|mimes:csv,txt|max:8192',
            'mode' => 'required|string|in:preview,append,replace',
        ]);

        $path = (string) config('service_finder.import.default_file');

        if ($request->hasFile('csv')) {
            // Kept out of the public disk: the file lists client sites.
            $path = $request->file('csv')->storeAs(
                'finder-imports',
                'upload-'.now()->format('Ymd-His').'.csv',
                'local'
            );
            $path = storage_path('app/'.$path);
        }

        if (! is_file($path)) {
            return back()->with('error', 'No CSV found at '.$path.'. Upload a file instead.');
        }

        try {
            $report = $importer->import($path, [
                'dry_run' => $data['mode'] === 'preview',
                'force' => $data['mode'] === 'replace',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Import failed: '.$exception->getMessage());
        }

        $message = $data['mode'] === 'preview'
            ? 'Preview complete — nothing was saved.'
            : 'Import complete and coverage rebuilt.';

        return back()
            ->with('success', $message)
            ->with('finder_import_report', $report);
    }
}
