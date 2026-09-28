<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SeoHelper;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\Service;
use App\Support\FrontendSectionRegistry;
use App\Support\SectionDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DevelopmentServiceController extends Controller
{
    public function index()
    {
        $items = Service::query()
            ->whereHas('contentBlocks', fn ($query) => $query->whereNotNull('section_key'))
            ->with('slug')
            ->withCount('contentBlocks')
            ->orderBy('title')
            ->paginate(25);

        // Also fetch development content pages if any exist (e.g. web-development-company, angular)
        $contentDevPages = ContentPage::query()
            ->where('is_cms_managed', true)
            ->whereIn('slug', ['web-development-company', 'angular-development-company'])
            ->withCount('contentBlocks')
            ->get();

        return view('admin.section-pages.index', [
            'title' => 'Development Services',
            'items' => $items,
            'extraPages' => $contentDevPages,
            'routeName' => 'admin.development-services',
        ]);
    }

    public function create()
    {
        return $this->form(new Service(['status' => true]));
    }

    public function store(Request $request)
    {
        [$data, $slug, $seo, $sections] = $this->validated($request);

        $service = DB::transaction(function () use ($data, $slug, $seo, $sections) {
            $service = Service::create($data);
            $service->slug()->create(['slug' => $slug]);
            $service->seo()->create($seo);
            SectionDocument::sync($service, $sections);
            return $service;
        });

        return redirect()->route('admin.development-services.edit', $service)->with('success', 'Development service page created.');
    }

    public function edit(Service $servicePage)
    {
        $servicePage->load(['slug', 'contentBlocks.fields', 'seo']);
        return $this->form($servicePage);
    }

    public function update(Request $request, Service $servicePage)
    {
        [$data, $slug, $seo, $sections] = $this->validated($request, $servicePage);

        DB::transaction(function () use ($servicePage, $data, $slug, $seo, $sections) {
            $servicePage->update($data);
            $servicePage->slug()->updateOrCreate([], ['slug' => $slug]);
            $servicePage->seo()->updateOrCreate([], $seo);
            SectionDocument::sync($servicePage, $sections);
        });

        return back()->with('success', 'Development service page updated.');
    }

    public function destroy(Service $servicePage)
    {
        DB::transaction(function () use ($servicePage) {
            $servicePage->contentBlocks()->delete();
            $servicePage->delete();
        });

        return redirect()->route('admin.development-services.index')->with('success', 'Development service page deleted.');
    }

    private function form(Service $page)
    {
        $sections = $page->exists ? SectionDocument::forEditor($page) : [];
        if ($sections === []) {
            $sections[] = SectionDocument::blankSection('serviceHero', $page);
        }

        return view('admin.section-pages.form', [
            'title' => $page->exists ? 'Edit Development Service Page' : 'Create Development Service Page',
            'item' => $page,
            'routeName' => 'admin.development-services',
            'templates' => ['inner-service'],
            'sections' => old('sections', $sections),
            'sectionTypes' => FrontendSectionRegistry::types(),
            'seo' => $page->seo,
        ]);
    }

    private function validated(Request $request, ?Service $page = null): array
    {
        $slugId = $page?->slug?->id;
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:191', Rule::unique('slugs', 'slug')->ignore($slugId)],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'sections' => ['required', 'array', 'min:1', 'max:80'],
            'sections.*' => ['array'],
            'sections.*.id' => ['required', 'string', 'max:191'],
            'sections.*.type' => ['required', Rule::in(FrontendSectionRegistry::types())],
            'sections.*.enabled' => ['nullable', 'boolean'],
            'sections.*.data' => ['nullable', 'array'],
            'add_section_type' => ['nullable', Rule::in(FrontendSectionRegistry::types())],
            ...SeoHelper::rules(),
        ]);

        $slug = $validated['slug'];
        $input = $validated['sections'];
        if (! empty($validated['add_section_type'])) {
            $input[] = SectionDocument::blankSection($validated['add_section_type'], $page);
        }
        $sections = SectionDocument::fromInput($input, $page);

        $seo = SeoHelper::data($validated);
        foreach (array_keys(SeoHelper::rules()) as $key) {
            unset($validated[$key]);
        }
        unset($validated['slug'], $validated['sections'], $validated['add_section_type']);
        $validated['status'] = $request->boolean('status');

        return [$validated, $slug, $seo, $sections];
    }
}
