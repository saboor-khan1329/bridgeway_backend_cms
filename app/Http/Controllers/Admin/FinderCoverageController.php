<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinderArea;
use App\Models\Service;
use App\Services\FinderCoverageService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The coverage editor — where an operator decides what the finder advertises.
 *
 * This is the one finder screen that cannot use the shared CRUD form: each
 * attached service carries its own description, its own cross-sell list and a
 * featured flag, and the shared multi-select can only apply one identical
 * pivot value to every row. So the editor posts a single JSON document that
 * the browser builds, following the same hidden-textarea pattern the
 * onboarding form builder already uses.
 */
class FinderCoverageController extends Controller
{
    public function __construct(protected FinderCoverageService $coverage)
    {
    }

    public function edit(FinderArea $finderArea)
    {
        return view('admin.finder.coverage', [
            'title' => "Coverage: {$finderArea->name}",
            'area' => $finderArea,
            'rows' => $this->coverage->rowsForArea($finderArea),
            'catalog' => $this->catalog(),
        ]);
    }

    public function update(Request $request, FinderArea $finderArea)
    {
        $validated = $request->validate([
            'coverage_json' => 'required|string|max:200000',
        ]);

        $rows = json_decode($validated['coverage_json'], true);

        if (! is_array($rows) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'coverage_json' => 'The coverage data could not be read. Please reload the page and try again.',
            ]);
        }

        $valid = Service::query()->where('status', true)->pluck('id')->all();
        $clean = [];

        foreach ($rows as $index => $row) {
            $serviceId = (int) ($row['service_id'] ?? 0);

            if (! in_array($serviceId, $valid, true)) {
                continue;
            }

            $clean[] = [
                'service_id' => $serviceId,
                'description' => mb_substr(trim((string) ($row['description'] ?? '')), 0, 1000),
                'related_service_ids' => array_slice(
                    array_filter(
                        array_map('intval', (array) ($row['related_service_ids'] ?? [])),
                        fn ($id) => in_array($id, $valid, true)
                    ),
                    0,
                    8
                ),
                'is_featured' => (bool) ($row['is_featured'] ?? false),
                'is_active' => ! array_key_exists('is_active', $row) || (bool) $row['is_active'],
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];
        }

        $this->coverage->syncArea($finderArea, $clean);

        return redirect()
            ->route('admin.finder-areas.coverage.edit', $finderArea)
            ->with('success', count($clean).' service(s) saved for '.$finderArea->name.'.');
    }

    /**
     * Attach one service to many areas at once — the "where do we offer Key
     * Holding?" direction, which is how coverage is usually rolled out.
     */
    public function bulkEdit(Request $request)
    {
        $serviceId = (int) $request->query('service_id', 0);
        $service = $serviceId ? Service::query()->find($serviceId) : null;

        return view('admin.finder.coverage-bulk', [
            'title' => 'Roll Out a Service',
            'catalog' => $this->catalog(),
            'service' => $service,
            'areas' => FinderArea::query()->orderBy('name')->get(['id', 'name', 'region', 'active_sites_count']),
            'selected' => $service
                ? FinderArea::query()->whereHas('services', fn ($q) => $q->where('services.id', $service->id))
                    ->pluck('finder_areas.id')->all()
                : [],
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'area_ids' => 'nullable|array',
            'area_ids.*' => 'integer|exists:finder_areas,id',
            'description' => 'nullable|string|max:1000',
        ]);

        $service = Service::query()->findOrFail($validated['service_id']);

        $this->coverage->syncService(
            $service,
            $validated['area_ids'] ?? [],
            $validated['description'] ?? null
        );

        return redirect()
            ->route('admin.finder-coverage.bulk', ['service_id' => $service->id])
            ->with('success', $service->title.' is now offered in '.count($validated['area_ids'] ?? []).' area(s).');
    }

    /**
     * Service catalog for the pickers, grouped by category so a long list
     * stays navigable.
     *
     * @return array<int, array{id:int, title:string, category:string, card_description:string}>
     */
    protected function catalog(): array
    {
        return Service::query()
            ->where('status', true)
            ->with('categories')
            ->orderBy('title')
            ->get(['id', 'title', 'card_description'])
            ->map(fn (Service $service) => [
                'id' => (int) $service->id,
                'title' => (string) $service->title,
                'category' => (string) ($service->categories->first()?->name ?? 'Other'),
                'card_description' => (string) ($service->card_description ?? ''),
            ])
            ->all();
    }
}
