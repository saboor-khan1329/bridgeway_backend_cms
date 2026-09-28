<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Services\AdminFileManagerService;
use App\Support\ImageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class NavigationMenuController extends Controller
{
    private const ALLOWED_LOCATIONS = ['header', 'footer'];

    public function __construct(private readonly AdminFileManagerService $fileManager)
    {
    }

    // ── Overview ──────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.navigation.index', [
            'title' => 'Navigation',
        ]);
    }

    // ── Menu management page ──────────────────────────────────────────────────

    public function show(string $location): View
    {
        $this->validateLocation($location);
        $menu = NavigationMenu::forLocation($location);

        $rootItems = NavigationMenuItem::where('menu_id', $menu->id)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('admin.navigation.show', [
            'title'     => ucfirst($location).' Navigation',
            'location'  => $location,
            'menu'      => $menu,
            'rootItems' => $rootItems,
        ]);
    }

    // ── Menu settings (logo, cta, footer detail, etc.) ────────────────────────

    public function updateSettings(Request $request, string $location): RedirectResponse
    {
        $this->validateLocation($location);
        $menu = NavigationMenu::forLocation($location);

        $rules = $location === 'header'
            ? [
                'logo_src'    => 'nullable|string|max:500',
                'logo_alt'    => 'nullable|string|max:255',
                'logo_upload' => 'nullable|image|max:4096',
                'cta_text'    => 'nullable|string|max:255',
                'cta_href'    => 'nullable|string|max:500',
                'services_title' => 'nullable|string|max:255',
                'services_description' => 'nullable|string|max:1000',
                'portfolio_title' => 'nullable|string|max:255',
                'portfolio_image_src' => 'nullable|string|max:500',
                'portfolio_image_alt' => 'nullable|string|max:255',
                'portfolio_href' => 'nullable|string|max:500',
                'portfolio_cta_text' => 'nullable|string|max:255',
            ]
            : [
                'logo_src'    => 'nullable|string|max:500',
                'logo_alt'    => 'nullable|string|max:255',
                'logo_upload' => 'nullable|image|max:4096',
                'detail'      => 'nullable|string|max:1000',
                'rights'      => 'nullable|string|max:500',
                'info_label'  => 'nullable|string|max:255',
                'info_text'   => 'nullable|string|max:500',
                // Google review badge — leave the score blank to hide the badge.
                'google_rating'     => 'nullable|numeric|min:0|max:5',
                'google_review_url' => 'nullable|url|max:500',
            ];

        $validated = $request->validate($rules);
        unset($validated['logo_upload']);
        $meta = $menu->meta ?? [];

        foreach ($validated as $key => $value) {
            $meta[$key] = $value;
        }

        if ($location === 'header') {
            $services = (array) ($meta['services'] ?? []);
            $services['title'] = $validated['services_title'] ?? ($services['title'] ?? 'Services');
            $services['description'] = $validated['services_description'] ?? ($services['description'] ?? '');
            $meta['services'] = $services;

            $portfolio = (array) ($meta['portfolio'] ?? []);
            foreach (['title', 'image_src', 'image_alt', 'href', 'cta_text'] as $key) {
                $portfolio[$key] = $validated['portfolio_'.$key] ?? ($portfolio[$key] ?? null);
            }
            $portfolio['image'] = [
                'src' => $portfolio['image_src'] ?? data_get($portfolio, 'image.src'),
                'alt' => $portfolio['image_alt'] ?? data_get($portfolio, 'image.alt'),
            ];
            $portfolio['ctaText'] = $portfolio['cta_text'] ?? ($portfolio['ctaText'] ?? null);
            unset($portfolio['image_src'], $portfolio['image_alt'], $portfolio['cta_text']);
            $meta['portfolio'] = $portfolio;

            foreach (['services_title', 'services_description', 'portfolio_title', 'portfolio_image_src', 'portfolio_image_alt', 'portfolio_href', 'portfolio_cta_text'] as $key) {
                unset($meta[$key]);
            }
        }

        $meta['logo_src'] = $this->resolveNavImageSrc(
            $request->file('logo_upload'),
            $validated['logo_src'] ?? null,
            "managed/navigation/{$location}/logo"
        );

        $menu->meta = $meta;
        $menu->save();

        return back()->with('success', ucfirst($location).' settings saved.');
    }

    // ── Item create / store ───────────────────────────────────────────────────

    public function createItem(string $location): View
    {
        $this->validateLocation($location);
        $menu = NavigationMenu::forLocation($location);

        return view('admin.navigation.item-form', [
            'title'    => 'Add '.ucfirst($location).' Item',
            'location' => $location,
            'menu'     => $menu,
            'item'     => null,
            'children' => collect(),
        ]);
    }

    public function storeItem(Request $request, string $location): RedirectResponse
    {
        $this->validateLocation($location);
        $menu = NavigationMenu::forLocation($location);

        $validated    = $this->validateItemRequest($request, $location);
        $meta         = $this->buildMeta($request, $location, $validated['item_type']);
        [$lt, $lType, $lId] = $this->resolveLinkable($validated);

        $sortOrder = NavigationMenuItem::where('menu_id', $menu->id)
            ->whereNull('parent_id')
            ->max('sort_order') ?? -1;

        $item = NavigationMenuItem::create([
            'menu_id'       => $menu->id,
            'parent_id'     => null,
            'item_type'     => $validated['item_type'],
            'link_type'     => $lt,
            'linkable_type' => $lType,
            'linkable_id'   => $lId,
            'title'         => $validated['title'],
            'href'          => $validated['href'] ?? null,
            'target'        => $validated['target'] ?? '_self',
            'meta'          => $meta,
            'sort_order'    => $sortOrder + 1,
            'status'        => true,
        ]);

        $this->syncChildren($request, $item, $location);

        return redirect()
            ->route('admin.navigation.show', $location)
            ->with('success', 'Item added.');
    }

    // ── Item edit / update ────────────────────────────────────────────────────

    public function editItem(string $location, NavigationMenuItem $item): View
    {
        $this->validateLocation($location);

        // Eager-load linkable (for the item itself) and children with their linkables
        // to avoid N+1 queries in the form view
        $item->load(['linkable', 'menu']);

        $children = $item->children()
            ->with('linkable')
            ->orderBy('sort_order')
            ->get();

        return view('admin.navigation.item-form', [
            'title'    => 'Edit '.ucfirst($location).' Item',
            'location' => $location,
            'menu'     => $item->menu,
            'item'     => $item,
            'children' => $children,
        ]);
    }

    public function updateItem(Request $request, string $location, NavigationMenuItem $item): RedirectResponse
    {
        $this->validateLocation($location);

        $validated    = $this->validateItemRequest($request, $location);
        $meta         = $this->buildMeta($request, $location, $validated['item_type']);
        [$lt, $lType, $lId] = $this->resolveLinkable($validated);

        $item->update([
            'item_type'     => $validated['item_type'],
            'link_type'     => $lt,
            'linkable_type' => $lType,
            'linkable_id'   => $lId,
            'title'         => $validated['title'],
            'href'          => $validated['href'] ?? null,
            'target'        => $validated['target'] ?? '_self',
            'meta'          => $meta,
        ]);

        $this->syncChildren($request, $item, $location);

        return redirect()
            ->route('admin.navigation.show', $location)
            ->with('success', 'Item updated.');
    }

    // ── Item delete ───────────────────────────────────────────────────────────

    public function destroyItem(string $location, NavigationMenuItem $item): RedirectResponse
    {
        $this->validateLocation($location);
        $item->delete();

        return back()->with('success', 'Item deleted.');
    }

    // ── AJAX reorder ──────────────────────────────────────────────────────────

    public function reorder(Request $request, string $location): JsonResponse
    {
        $this->validateLocation($location);

        $validated = $request->validate([
            'items'   => 'required|array',
            'items.*' => 'integer',
        ]);

        $menu = NavigationMenu::forLocation($location);

        foreach ($validated['items'] as $index => $id) {
            NavigationMenuItem::where('id', $id)
                ->where('menu_id', $menu->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateLocation(string $location): void
    {
        abort_unless(in_array($location, self::ALLOWED_LOCATIONS, true), 404);
    }

    private function validateItemRequest(Request $request, string $location): array
    {
        $itemTypes = $location === 'header'
            ? ['nav_link']
            : ['locations_group', 'group', 'other_link'];

        return $request->validate([
            'item_type'         => ['required', 'string', 'in:'.implode(',', $itemTypes)],
            'link_type'         => ['nullable', 'string', 'in:'.implode(',', NavigationMenuItem::LINK_TYPES)],
            'linkable_id'       => 'nullable|integer|min:1',
            'title'             => 'required|string|max:500',
            'href'              => 'nullable|string|max:500',
            'target'            => 'nullable|string|in:_self,_blank',
            'navigation_kind'   => 'nullable|string|in:service_group,header_link',
            'mega_image_upload' => 'nullable|image|max:4096',
        ]);
    }

    /**
     * Determine link_type, linkable_type, linkable_id from validated input.
     * Returns [link_type, linkable_type|null, linkable_id|null].
     */
    private function resolveLinkable(array $validated): array
    {
        $linkType = $validated['link_type'] ?? NavigationMenuItem::LINK_TYPE_CUSTOM;

        if ($linkType === NavigationMenuItem::LINK_TYPE_CUSTOM || empty($validated['linkable_id'])) {
            return [NavigationMenuItem::LINK_TYPE_CUSTOM, null, null];
        }

        $modelClass = NavigationMenuItem::modelClassForType($linkType);
        if (! $modelClass) {
            return [NavigationMenuItem::LINK_TYPE_CUSTOM, null, null];
        }

        return [$linkType, $modelClass, (int) $validated['linkable_id']];
    }

    private function buildMeta(Request $request, string $location, string $itemType): array
    {
        $meta = [];

        if ($location === 'header' && $itemType === 'nav_link') {
            $meta['kind']             = $request->input('navigation_kind', 'header_link');
            $meta['mega_title']       = $request->input('mega_title');
            $meta['mega_description'] = $request->input('mega_description');
            $meta['mega_detail']      = $request->input('mega_detail');
            $meta['mega_image_src']   = $this->resolveNavImageSrc(
                $request->file('mega_image_upload'),
                $request->input('mega_image_src'),
                'managed/navigation/header/mega'
            );
            $meta['mega_image_alt']   = $request->input('mega_image_alt');
        }

        return array_filter($meta, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Resolves the src to store for a nav image field. A real uploaded file
     * always wins (stored via the shared AdminFileManagerService, same as
     * every other admin upload in the app, then resolved to an absolute
     * URL). Otherwise the manually-typed path/URL is kept as-is — this is
     * what every existing nav item already has (a path into the frontend's
     * own /public/images folder), so old entries keep working unchanged.
     */
    private function resolveNavImageSrc(?UploadedFile $file, ?string $typedSrc, string $directory): ?string
    {
        if ($file) {
            $stored = $this->fileManager->storeUploadedFile($file, $directory, true);
            return ImageFormatter::url($stored['path'] ?? null, $this->fileManager->diskName());
        }

        return $typedSrc;
    }

    /**
     * Delete and re-create child items from form arrays.
     * Supports both custom links and content-linked items.
     */
    private function syncChildren(Request $request, NavigationMenuItem $item, string $location): void
    {
        $childType = $location === 'header' ? 'sub_link' : 'link';
        $titles    = $request->input('child_title', []);

        if (empty($titles)) {
            $item->children()->delete();
            return;
        }

        $hrefs         = $request->input('child_href', []);
        $linkTypes     = $request->input('child_link_type', []);
        $linkableIds   = $request->input('child_linkable_id', []);
        $iconSrcs      = $request->input('child_icon_src', []);
        $iconAlts      = $request->input('child_icon_alt', []);

        // Delete existing children (we re-create them fully)
        $item->children()->delete();

        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }

            $linkType  = trim((string) ($linkTypes[$i] ?? 'custom')) ?: 'custom';
            $linkableId = (int) ($linkableIds[$i] ?? 0);

            $childLinkType     = NavigationMenuItem::LINK_TYPE_CUSTOM;
            $childLinkableType = null;
            $childLinkableId   = null;

            if ($linkType !== NavigationMenuItem::LINK_TYPE_CUSTOM && $linkableId > 0) {
                $modelClass = NavigationMenuItem::modelClassForType($linkType);
                if ($modelClass) {
                    $childLinkType     = $linkType;
                    $childLinkableType = $modelClass;
                    $childLinkableId   = $linkableId;
                }
            }

            $meta = [];
            if ($location === 'header' && ! empty($iconSrcs[$i])) {
                $meta['icon_src'] = trim((string) $iconSrcs[$i]);
                $meta['icon_alt'] = trim((string) ($iconAlts[$i] ?? ''));
            }

            NavigationMenuItem::create([
                'menu_id'       => $item->menu_id,
                'parent_id'     => $item->id,
                'item_type'     => $childType,
                'link_type'     => $childLinkType,
                'linkable_type' => $childLinkableType,
                'linkable_id'   => $childLinkableId,
                'title'         => $title,
                'href'          => trim((string) ($hrefs[$i] ?? '')) ?: null,
                'target'        => '_self',
                'meta'          => $meta ?: null,
                'sort_order'    => $i,
                'status'        => true,
            ]);
        }
    }
}
