<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SeoHelper;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Support\FrontendSectionRegistry;
use App\Support\SectionDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReusableSectionController extends Controller
{
    private const TEMPLATES = ['global', 'contact'];

    public function index()
    {
        $items = ContentPage::query()
            ->where('is_cms_managed', true)
            ->where(function ($query) {
                $query->where('template', 'global')
                    ->orWhere('slug', 'get-a-free-quote');
            })
            ->withCount('contentBlocks')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(25);

        return view('admin.section-pages.index', [
            'title' => 'Reusable Sections & Globals',
            'items' => $items,
            'routeName' => 'admin.reusable-sections',
        ]);
    }

    public function create()
    {
        return $this->form(new ContentPage([
            'status' => true,
            'template' => 'global',
            'is_cms_managed' => true,
        ]));
    }

    public function store(Request $request)
    {
        [$data, $seo, $sections] = $this->validated($request);

        $page = DB::transaction(function () use ($data, $seo, $sections) {
            $page = ContentPage::create($data);
            if ($page->template !== 'global') {
                $page->seo()->create($seo);
            }
            SectionDocument::sync($page, $sections);
            return $page;
        });

        return redirect()->route('admin.reusable-sections.edit', $page)->with('success', 'Reusable section created.');
    }

    public function edit(ContentPage $contentPage)
    {
        abort_unless($contentPage->is_cms_managed, 404);
        $contentPage->load(['contentBlocks.fields', 'seo']);
        return $this->form($contentPage);
    }

    public function update(Request $request, ContentPage $contentPage)
    {
        abort_unless($contentPage->is_cms_managed, 404);
        [$data, $seo, $sections] = $this->validated($request, $contentPage);

        DB::transaction(function () use ($contentPage, $data, $seo, $sections) {
            $contentPage->update($data);
            if ($contentPage->template !== 'global') {
                $contentPage->seo()->updateOrCreate([], $seo);
            }
            SectionDocument::sync($contentPage, $sections);
        });

        return back()->with('success', 'Reusable section updated.');
    }

    public function destroy(ContentPage $contentPage)
    {
        abort_unless($contentPage->is_cms_managed, 404);
        $contentPage->delete();
        return redirect()->route('admin.reusable-sections.index')->with('success', 'Reusable section deleted.');
    }

    private function form(ContentPage $page)
    {
        $sections = $page->exists ? SectionDocument::forEditor($page) : [];
        if ($sections === []) {
            $sections[] = SectionDocument::blankSection('sertHero', $page);
        }

        return view('admin.section-pages.form', [
            'title' => $page->exists ? 'Edit Reusable Section' : 'Create Reusable Section',
            'item' => $page,
            'routeName' => 'admin.reusable-sections',
            'templates' => self::TEMPLATES,
            'sections' => old('sections', $sections),
            'sectionTypes' => FrontendSectionRegistry::types(),
            'seo' => $page->seo,
        ]);
    }

    private function validated(Request $request, ?ContentPage $page = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'slug' => [
                'required',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'max:191',
                Rule::unique('content_pages', 'slug')->ignore($page?->id),
            ],
            'template' => ['required', Rule::in(self::TEMPLATES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'sections' => ['required', 'array', 'min:1', 'max:80'],
            'sections.*' => ['array'],
            'sections.*.id' => ['required', 'string', 'max:191'],
            'sections.*.type' => ['required', Rule::in(FrontendSectionRegistry::types())],
            'sections.*.enabled' => ['nullable', 'boolean'],
            'sections.*.data' => ['nullable', 'array'],
            'add_section_type' => ['nullable', Rule::in(FrontendSectionRegistry::types())],
            ...SeoHelper::rules(),
        ]);

        $input = $validated['sections'];
        if (! empty($validated['add_section_type'])) {
            $input[] = SectionDocument::blankSection($validated['add_section_type'], $page);
        }
        $sections = SectionDocument::fromInput($input, $page);

        $seo = SeoHelper::data($validated);
        foreach (array_keys(SeoHelper::rules()) as $key) {
            unset($validated[$key]);
        }
        unset($validated['sections'], $validated['add_section_type']);
        $validated['status'] = $request->boolean('status');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_cms_managed'] = true;

        return [$validated, $seo, $sections];
    }
}
