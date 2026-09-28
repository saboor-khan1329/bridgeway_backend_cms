<?php

namespace Database\Seeders;

use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        $navigation = $this->defaultNavigation();
        $footer = $this->defaultFooter();

        DB::transaction(function () use ($navigation, $footer): void {
            $this->seedHeader($navigation);
            $this->seedFooter($footer);
        });
    }

    private function seedHeader(array $data): void
    {
        $services = (array) ($data['services'] ?? []);
        $menu = NavigationMenu::firstOrCreate(
            ['location' => 'header'],
            ['name' => 'Header Navigation', 'meta' => [], 'status' => true]
        );

        if (! $this->shouldPopulate($menu)) {
            return;
        }

        $menu->items()->delete();
        $menu->update([
            'name' => 'Header Navigation',
            'status' => true,
            'meta' => [
                'logo_src' => '/images/logo.svg',
                'logo_alt' => 'BridgeWay Digital',
                'logo_href' => '/',
                'cta_text' => 'ESTIMATE A PROJECT',
                'cta_href' => '/get-a-free-quote',
                'services' => [
                    'label' => 'Services',
                    'title' => $services['title'] ?? 'Services',
                    'description' => $services['description'] ?? '',
                    'socials' => $services['socials'] ?? [],
                ],
                'portfolio' => [
                    'title' => 'Bridgeway Digital Portfolio',
                    'image' => ['src' => '/images/mega-menu-img.webp', 'alt' => 'BridgeWay Digital portfolio'],
                    'href' => '/portfolio',
                    'ctaText' => 'VIEW OUR PORTFOLIO',
                ],
            ],
        ]);

        foreach ((array) ($services['links'] ?? []) as $order => $group) {
            $parent = $this->item($menu, null, [
                'item_type' => 'nav_link',
                'title' => $group['title'] ?? 'Service',
                'href' => $this->rooted($group['url'] ?? '#'),
                'meta' => ['kind' => 'service_group'],
                'sort_order' => $order,
            ]);

            foreach ((array) ($group['subLinks'] ?? []) as $childOrder => $link) {
                $this->item($menu, $parent, [
                    'item_type' => 'sub_link',
                    'title' => $link['text'] ?? 'Service',
                    'href' => $this->rooted($link['url'] ?? '#'),
                    'sort_order' => $childOrder,
                ]);
            }
        }

        foreach ([['Portfolio', '/portfolio'], ['About', '/about-us']] as $offset => [$title, $href]) {
            $this->item($menu, null, [
                'item_type' => 'nav_link',
                'title' => $title,
                'href' => $href,
                'meta' => ['kind' => 'header_link'],
                'sort_order' => count($services['links'] ?? []) + $offset,
            ]);
        }
    }

    private function seedFooter(array $data): void
    {
        $menu = NavigationMenu::firstOrCreate(
            ['location' => 'footer'],
            ['name' => 'Footer Navigation', 'meta' => [], 'status' => true]
        );

        if (! $this->shouldPopulate($menu)) {
            return;
        }

        $menu->items()->delete();
        $logo = (array) ($data['logo'] ?? []);
        $menu->update([
            'name' => 'Footer Navigation',
            'status' => true,
            'meta' => [
                'logo_src' => $logo['src'] ?? '/images/white-logo.svg',
                'logo_alt' => $logo['alt'] ?? 'BridgeWay Logo',
                'logo_href' => $logo['href'] ?? '/',
                'social_links' => $data['socialLinks'] ?? [],
            ],
        ]);

        foreach ((array) ($data['linkGroups'] ?? []) as $order => $group) {
            $parent = $this->item($menu, null, [
                'item_type' => 'group',
                'title' => $group['title'] ?? 'Links',
                'href' => '#',
                'sort_order' => $order,
            ]);

            foreach ((array) ($group['links'] ?? []) as $childOrder => $link) {
                $this->item($menu, $parent, [
                    'item_type' => 'link',
                    'title' => $link['text'] ?? 'Link',
                    'href' => $this->rooted($link['href'] ?? '#'),
                    'sort_order' => $childOrder,
                ]);
            }
        }

        foreach ((array) ($data['legalLinks'] ?? []) as $offset => $link) {
            $this->item($menu, null, [
                'item_type' => 'other_link',
                'title' => $link['text'] ?? 'Legal',
                'href' => $this->rooted($link['href'] ?? '#'),
                'sort_order' => count($data['linkGroups'] ?? []) + $offset,
            ]);
        }
    }

    private function shouldPopulate(NavigationMenu $menu): bool
    {
        if (! $menu->items()->exists()) {
            return true;
        }

        // Upgrade only the known legacy seed. Never overwrite editor changes
        // on an already-BridgeWay menu when seeders are re-run.
        return $menu->items()
            ->whereNull('parent_id')
            ->whereIn('title', ['Security Services', 'Security Systems', 'Sectors', 'Locations'])
            ->exists();
    }

    private function item(NavigationMenu $menu, ?NavigationMenuItem $parent, array $attributes): NavigationMenuItem
    {
        return NavigationMenuItem::create(array_merge([
            'menu_id' => $menu->id,
            'parent_id' => $parent?->id,
            'link_type' => NavigationMenuItem::LINK_TYPE_CUSTOM,
            'linkable_type' => null,
            'linkable_id' => null,
            'target' => '_self',
            'meta' => [],
            'status' => true,
        ], $attributes));
    }

    private function rooted(string $url): string
    {
        if ($url === '' || $url === '#') {
            return '#';
        }

        if (preg_match('#^(?:https?://|tel:|mailto:|/)#i', $url)) {
            return $url;
        }

        return '/'.ltrim($url, '/');
    }

    private function defaultNavigation(): array
    {
        return ['services' => [
            'title' => 'Services',
            'description' => 'World-class solutions, unparalleled growth. BridgeWay Digital drives your business forward',
            'links' => [
                ['title' => 'Amazon Marketing Services', 'url' => '/amazon-marketing-services', 'subLinks' => [
                    ['text' => 'Amazon PPC Management Services', 'url' => '/amazon-ppc-management-services'],
                    ['text' => 'Amazon SEO Services', 'url' => '/amazon-seo-services'],
                    ['text' => 'Amazon Product Listing Services', 'url' => '/amazon-product-listing-services'],
                    ['text' => 'Amazon Product Research Services', 'url' => '/amazon-product-research-services'],
                    ['text' => 'Amazon Product Photography Services', 'url' => '/amazon-photography-service'],
                ]],
                ['title' => 'SEO Services', 'url' => '#', 'subLinks' => [
                    ['text' => 'Local SEO Services', 'url' => '/local-seo-services'],
                    ['text' => 'Technical SEO Services', 'url' => '/technical-seo-agency'],
                    ['text' => 'International SEO Services', 'url' => '/international-seo-services'],
                ]],
                ['title' => 'Website Development Services', 'url' => '/web-development-company', 'subLinks' => [
                    ['text' => 'AngularJS Web Development', 'url' => '/angular-development-company'],
                    ['text' => 'Magento Web Development', 'url' => '/magento-development-company'],
                    ['text' => 'White Label Web Development', 'url' => '/white-label-web-development'],
                    ['text' => 'Ecommerce Web Development', 'url' => '/e-commerce-website-development'],
                    ['text' => 'Laravel Web Development', 'url' => '/laravel-development-company'],
                    ['text' => 'Python Web Development', 'url' => '/python-development-services'],
                    ['text' => 'Shopify Web Development', 'url' => '/shopify-development-services'],
                    ['text' => 'ReactJS Web Development', 'url' => '/react-js-development-company'],
                    ['text' => 'PHP Web Development', 'url' => '/php-development-company'],
                ]],
                ['title' => 'Digital Marketing', 'url' => '/digital-marketing-services', 'subLinks' => []],
                ['title' => 'Video Animation', 'url' => '/video-animation-services', 'subLinks' => []],
            ],
            'socials' => [
                ['channel' => 'facebook', 'icon' => ['src' => '/images/fb-logo.svg', 'alt' => 'facebook-logo']],
                ['channel' => 'twitter', 'icon' => ['src' => '/images/x-logo.svg', 'alt' => 'x-logo']],
                ['channel' => 'linkedin', 'icon' => ['src' => '/images/linkedIn-logo.svg', 'alt' => 'linkedIn-logo']],
                ['channel' => 'instagram', 'icon' => ['src' => '/images/ig-logo.svg', 'alt' => 'instagram-logo']],
                ['channel' => 'whatsapp', 'icon' => ['src' => '/images/whatsapp-logo.svg', 'alt' => 'whatsapp-logo']],
            ],
        ]];
    }

    private function defaultFooter(): array
    {
        return [
            'linkGroups' => [
                ['title' => 'COMPANY', 'links' => [['text' => 'About us', 'href' => '/about-us'], ['text' => 'Testimonials', 'href' => '/#testimonials'], ['text' => 'Portfolio', 'href' => '/portfolio'], ['text' => 'Contact us', 'href' => '/get-a-free-quote']]],
                ['title' => 'RESOURCES', 'links' => [['text' => 'Blogs', 'href' => '/blogs'], ['text' => 'Work', 'href' => '/blogs']]],
                ['title' => 'DIGITAL', 'links' => [['text' => 'Website Development', 'href' => '/web-development-company'], ['text' => 'SEO Services', 'href' => '/technical-seo-agency'], ['text' => 'Digital Marketing', 'href' => '/digital-marketing-services'], ['text' => 'Video Animation', 'href' => '/video-animation-services']]],
                ['title' => 'E-COMMERCE', 'links' => [['text' => 'Amazon PPC', 'href' => '/amazon-ppc-management-services'], ['text' => 'Amazon SEO', 'href' => '/amazon-seo-services'], ['text' => 'Amazon Photography', 'href' => '/amazon-photography-service'], ['text' => 'Product Research', 'href' => '/amazon-product-research-services']]],
            ],
            'socialLinks' => [
                ['channel' => 'facebook', 'icon' => ['src' => '/images/footer/facebook-01-01.svg', 'alt' => 'Facebook']],
                ['channel' => 'linkedin', 'icon' => ['src' => '/images/footer/in-logo-version-for-brand-representation.webp', 'alt' => 'LinkedIn']],
                ['channel' => 'whatsapp', 'icon' => ['src' => '/images/footer/whatsapp-contact-icon-for-chat-support.webp', 'alt' => 'WhatsApp']],
                ['channel' => 'twitter', 'icon' => ['src' => '/images/footer/x-logo-icon-formerly-known-as-twitter.webp', 'alt' => 'Twitter']],
                ['channel' => 'instagram', 'icon' => ['src' => '/images/footer/instagram-logo-for-brand-engagement-on-instagram.webp', 'alt' => 'Instagram']],
            ],
            'legalLinks' => [['text' => 'Terms and conditions', 'href' => '/terms-and-conditions'], ['text' => 'Privacy policy', 'href' => '/privacy-policy']],
            'logo' => ['src' => '/images/white-logo.svg', 'alt' => 'BridgeWay Logo', 'href' => '/'],
        ];
    }
}
