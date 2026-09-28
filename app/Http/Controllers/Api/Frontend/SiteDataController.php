<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\SiteSetting;
use App\Models\ContentPage;
use App\Support\HtmlCleaner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SiteDataController extends BaseFrontendController
{
    /** Return the complete Next.js layout contract in one cached response. */
    public function index(): JsonResponse
    {
        return $this->success($this->cached('site_data', function (): array {
            $settings = SiteSetting::allCached();

            return [
                'contact' => $this->contact($settings),
                'countries' => $this->countries($settings['country_options'] ?? []),
                'navigation' => $this->navigation(),
                'footer' => $this->footer(),
                'metadata' => [
                    'defaultTitle' => $settings['website_default_title'] ?? '',
                    'titleTemplate' => $settings['website_title_template'] ?? '',
                    'description' => $settings['website_meta_description'] ?? '',
                ],
                'globals' => $this->globals(),
            ];
        }));
    }

    private function contact(array $settings): array
    {
        $emails = (array) ($settings['contact_email_addresses'] ?? []);
        $phones = (array) ($settings['contact_phone_numbers'] ?? []);
        $email = $emails[0] ?? $settings['contact_email'] ?? '';
        $phone = $phones[0] ?? $settings['contact_phone'] ?? '';
        $phoneHref = $settings['contact_phone_href'] ?? null;

        if (! $phoneHref && $phone) {
            $phoneHref = 'tel:'.preg_replace('/[^+0-9]/', '', (string) $phone);
        }

        return [
            'email' => $email,
            'phone' => [
                'display' => $phone,
                'href' => HtmlCleaner::safeUrl((string) $phoneHref) ?? '#',
            ],
            'locations' => [
                'usa' => $settings['location_usa'] ?? '',
                'uk' => $settings['location_uk'] ?? '',
                'uae' => $settings['location_uae'] ?? '',
                'nl' => $settings['location_nl'] ?? '',
            ],
            'socialMedia' => [
                'facebook' => $this->safe($settings['social_facebook'] ?? ''),
                'linkedin' => $this->safe($settings['social_linkedin'] ?? ''),
                'whatsapp' => $this->safe($settings['social_whatsapp'] ?? ''),
                'twitter' => $this->safe($settings['social_twitter'] ?? ''),
                'instagram' => $this->safe($settings['social_instagram'] ?? ''),
            ],
            'footer' => ['copyright' => $settings['copyright_text'] ?? ''],
        ];
    }

    private function globals(): array
    {
        $page = ContentPage::where('template', 'global')->where('slug', 'floating-ctas')
            ->where('status', true)->with('contentBlocks.fields')->first();
        $block = $page?->contentBlocks->first(fn ($block) => $block->is_active && $block->type === 'floatingCtas');
        return ['floatingCtas' => $block?->cmsData()];
    }

    private function navigation(): array
    {
        $menu = $this->menu('header');
        $meta = $menu?->meta ?? [];
        $roots = $this->roots($menu);
        $servicesMeta = (array) ($meta['services'] ?? []);
        $portfolioMeta = (array) ($meta['portfolio'] ?? []);
        $portfolioImage = (array) ($portfolioMeta['image'] ?? []);
        $serviceGroups = $roots->filter(fn (NavigationMenuItem $item) => data_get($item->meta, 'kind') === 'service_group');

        return [
            'logo' => [
                'src' => $meta['logo_src'] ?? '',
                'alt' => $meta['logo_alt'] ?? '',
                'href' => $this->safe($meta['logo_href'] ?? ''),
            ],
            'services' => [
                'label' => $servicesMeta['label'] ?? '',
                'title' => $servicesMeta['title'] ?? '',
                'description' => $servicesMeta['description'] ?? '',
                'links' => $serviceGroups->map(fn (NavigationMenuItem $item) => [
                    'title' => $item->title,
                    'url' => $this->itemUrl($item),
                    'subLinks' => $item->children->map(fn (NavigationMenuItem $child) => [
                        'text' => $child->title,
                        'url' => $this->itemUrl($child),
                    ])->values()->all(),
                ])->values()->all(),
                'socials' => array_values((array) ($servicesMeta['socials'] ?? [])),
            ],
            'links' => $roots
                ->filter(fn (NavigationMenuItem $item) => data_get($item->meta, 'kind') === 'header_link')
                ->map(fn (NavigationMenuItem $item) => ['text' => $item->title, 'href' => $this->itemUrl($item)])
                ->values()->all(),
            'cta' => [
                'text' => $meta['cta_text'] ?? '',
                'href' => $this->safe($meta['cta_href'] ?? ''),
            ],
            'portfolio' => [
                'title' => $portfolioMeta['title'] ?? '',
                'image' => [
                    'src' => $portfolioImage['src'] ?? '',
                    'alt' => $portfolioImage['alt'] ?? '',
                ],
                'href' => $this->safe($portfolioMeta['href'] ?? ''),
                'ctaText' => $portfolioMeta['ctaText'] ?? '',
            ],
        ];
    }

    private function footer(): array
    {
        $menu = $this->menu('footer');
        $meta = $menu?->meta ?? [];
        $roots = $this->roots($menu);

        return [
            'linkGroups' => $roots->where('item_type', 'group')->map(fn (NavigationMenuItem $group) => [
                'title' => $group->title,
                'links' => $group->children->map(fn (NavigationMenuItem $item) => [
                    'text' => $item->title,
                    'href' => $this->itemUrl($item),
                ])->values()->all(),
            ])->values()->all(),
            'socialLinks' => array_values((array) ($meta['social_links'] ?? [])),
            'legalLinks' => $roots->where('item_type', 'other_link')->map(fn (NavigationMenuItem $item) => [
                'text' => $item->title,
                'href' => $this->itemUrl($item),
            ])->values()->all(),
            'logo' => [
                'src' => $meta['logo_src'] ?? '',
                'alt' => $meta['logo_alt'] ?? '',
                'href' => $this->safe($meta['logo_href'] ?? ''),
            ],
        ];
    }

    private function menu(string $location): ?NavigationMenu
    {
        return NavigationMenu::query()->where('location', $location)->where('status', true)->first();
    }

    private function roots(?NavigationMenu $menu): Collection
    {
        if (! $menu) {
            return collect();
        }

        return NavigationMenuItem::query()
            ->where('menu_id', $menu->id)
            ->whereNull('parent_id')
            ->where('status', true)
            ->with(['children' => fn ($query) => $query->where('status', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    private function itemUrl(NavigationMenuItem $item): string
    {
        return $this->safe($item->href ?? '#') ?: '#';
    }

    private function safe(mixed $url): string
    {
        return HtmlCleaner::safeUrl(trim((string) $url)) ?? '';
    }

    private function countries(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/\r\n|\r|\n/', $value);
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn ($country) => trim((string) $country))
            ->filter()->unique()->values()->all();
    }
}
