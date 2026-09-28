<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\AdminFileManagerService;
use App\Support\ImageField;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteSettingController extends Controller
{
    // Contact details, country options and social URLs are CMS-managed and
    // published through the frontend site endpoint.
    public const FIELDS = [
        'general' => [
            ['key' => 'website_default_title', 'label' => 'Default Website Title', 'type' => 'text', 'col' => 6],
            ['key' => 'website_title_template', 'label' => 'Website Title Template', 'type' => 'text', 'col' => 6, 'hint' => 'Use %s for the page title.'],
            ['key' => 'website_meta_description', 'label' => 'Default Website Meta Description', 'type' => 'textarea', 'col' => 12],
            ['key' => 'site_name', 'label' => 'Site Name', 'type' => 'text', 'col' => 6],
            ['key' => 'site_tagline', 'label' => 'Site Tagline', 'type' => 'text', 'col' => 6],
            ['key' => 'copyright_text', 'label' => 'Copyright Text', 'type' => 'text', 'col' => 6],
            ['key' => 'admin_email', 'label' => 'Admin Email', 'type' => 'email', 'col' => 6],
        ],
        'contact' => [
            ['key' => 'contact_email_addresses', 'label' => 'Contact Emails', 'type' => 'repeatable-email', 'col' => 6],
            ['key' => 'contact_phone_numbers', 'label' => 'Contact Phone Numbers', 'type' => 'repeatable-text', 'col' => 6],
            ['key' => 'contact_phone_href', 'label' => 'Phone Link', 'type' => 'text', 'col' => 6],
            ['key' => 'location_usa', 'label' => 'USA Address', 'type' => 'text', 'col' => 6],
            ['key' => 'location_uk', 'label' => 'UK Address', 'type' => 'text', 'col' => 6],
            ['key' => 'location_uae', 'label' => 'UAE Address', 'type' => 'text', 'col' => 6],
            ['key' => 'location_nl', 'label' => 'Netherlands Address', 'type' => 'text', 'col' => 6],
            ['key' => 'country_options', 'label' => 'Contact Form Countries', 'type' => 'textarea', 'col' => 12, 'max' => 20000, 'hint' => 'One country per line.'],
        ],
        'social' => [
            ['key' => 'social_facebook', 'label' => 'Facebook URL', 'type' => 'url', 'col' => 6],
            ['key' => 'social_linkedin', 'label' => 'LinkedIn URL', 'type' => 'url', 'col' => 6],
            ['key' => 'social_whatsapp', 'label' => 'WhatsApp URL', 'type' => 'url', 'col' => 6],
            ['key' => 'social_twitter', 'label' => 'X / Twitter URL', 'type' => 'url', 'col' => 6],
            ['key' => 'social_instagram', 'label' => 'Instagram URL', 'type' => 'url', 'col' => 6],
        ],
        'branding' => [
            ['key' => 'blog_listing_per_page', 'label' => 'Blog Cards Per Page', 'type' => 'number', 'col' => 6, 'min' => 1, 'max' => 50],
            ['key' => 'blog_featured_limit', 'label' => 'Featured Blog Cards', 'type' => 'number', 'col' => 6, 'min' => 0, 'max' => 20],
            ['key' => 'logo', 'label' => 'Logo', 'type' => 'image', 'col' => 6, 'directory' => 'managed/site-settings/logo', 'show_metadata' => false],
            ['key' => 'favicon', 'label' => 'Favicon', 'type' => 'image', 'col' => 6, 'directory' => 'managed/site-settings/favicon', 'show_metadata' => false],
            ['key' => 'blog_default_image', 'label' => 'Default Blog Image', 'type' => 'image', 'col' => 6, 'directory' => 'managed/site-settings/blog', 'show_metadata' => false],
            ['key' => 'blog_default_author_image', 'label' => 'Default Blog Author Image', 'type' => 'image', 'col' => 6, 'directory' => 'managed/site-settings/blog-authors', 'show_metadata' => false],
            ['key' => 'blog_default_image_alt', 'label' => 'Default Blog Image Alt Text', 'type' => 'text', 'col' => 6],
            ['key' => 'blog_default_author_image_alt', 'label' => 'Default Author Image Alt Text', 'type' => 'text', 'col' => 6],
            ['key' => 'blog_default_author_name', 'label' => 'Default Blog Author Name', 'type' => 'text', 'col' => 6],
        ],
        'inquiries' => [
            [
                'key' => 'inquiry_spam_detection_enabled',
                'label' => 'Spam Detection',
                'type' => 'select',
                'options' => ['1' => 'Enabled', '0' => 'Disabled'],
                'col' => 6,
            ],
            ['key' => 'inquiry_spam_block_score', 'label' => 'Spam Block Score', 'type' => 'number', 'col' => 6, 'min' => 1, 'max' => 100],
            ['key' => 'inquiry_blocked_keywords', 'label' => 'Blocked Inquiry Keywords', 'type' => 'textarea', 'col' => 12, 'max' => 10000],
            [
                'key' => 'inquiry_field_block_rules',
                'label' => 'Field-Specific Spam Rules',
                'type' => 'textarea',
                'col' => 12,
                'max' => 10000,
                'hint' => 'One rule per line, for example: phone=090078601,123456 or message=casino,crypto,loan',
            ],
        ],
    ];

    public function __construct(private readonly AdminFileManagerService $fileManager)
    {
    }

    public function edit()
    {
        $values = SiteSetting::allCached();

        return view('admin.settings.edit', [
            'title' => 'Site Settings',
            'groups' => self::FIELDS,
            'values' => $values,
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::FIELDS as $fields) {
            foreach ($fields as $field) {
                $key = $field['key'];

                match ($field['type']) {
                    'image' => $rules = array_merge($rules, ImageField::singleRules($key, 2048, false)),
                    'repeatable-email' => $rules += [
                        $key => 'nullable|array|max:4',
                        "{$key}.*" => 'nullable|email|max:191',
                    ],
                    'repeatable-text' => $rules += [
                        $key => 'nullable|array|max:4',
                        "{$key}.*" => 'nullable|string|max:64',
                    ],
                    'textarea' => $rules[$key] = 'nullable|string|max:'.(int) ($field['max'] ?? 5000),
                    'email' => $rules[$key] = 'nullable|email|max:191',
                    'url' => $rules[$key] = 'nullable|url|max:500',
                    'number' => $rules[$key] = 'nullable|integer|min:'.(int) ($field['min'] ?? 0).'|max:'.(int) ($field['max'] ?? PHP_INT_MAX),
                    'select' => $rules[$key] = ['nullable', 'string', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                    default => $rules[$key] = 'nullable|string|max:'.(int) ($field['max'] ?? 1000),
                };
            }
        }

        $validated = $request->validate($rules);
        $existing = SiteSetting::allCached();
        $cleanupPaths = [];

        foreach (self::FIELDS as $group => $fields) {
            $bag = [];

            foreach ($fields as $field) {
                $key = $field['key'];

                if ($field['type'] === 'image') {
                    try {
                        [$bag[$key], $cleanupPath] = $this->resolveManagedImage(
                            $request->input($key, []),
                            $request->file($key . '.file'),
                            (string) ($existing[$key] ?? ''),
                            (string) $field['directory']
                        );
                    } catch (ValidationException $exception) {
                        throw ImageField::remapValidationException($key, $exception);
                    }

                    if ($cleanupPath) {
                        $cleanupPaths[] = $cleanupPath;
                    }

                    continue;
                }

                if (str_starts_with($field['type'], 'repeatable-')) {
                    $bag[$key] = $this->normalizedList($validated[$key] ?? []);
                    continue;
                }

                $bag[$key] = $validated[$key] ?? null;
            }

            if ($bag !== []) {
                SiteSetting::bulkPut($bag, $group);
            }
        }

        foreach (array_unique(array_filter($cleanupPaths)) as $cleanupPath) {
            if (str_starts_with((string) $cleanupPath, 'managed/site-settings/')) {
                $this->fileManager->deleteManagedFileIfUnreferenced(
                    $this->fileManager->diskName(),
                    (string) $cleanupPath,
                    'upload'
                );
            }
        }

        $emails = $validated['contact_email_addresses'] ?? [];
        $phones = $validated['contact_phone_numbers'] ?? [];
        SiteSetting::bulkPut([
            'contact_email' => $emails[0] ?? null,
            'contact_phone' => $phones[0] ?? null,
        ], 'contact');

        return back()->with('success', 'Settings updated.');
    }

    protected function normalizedList(array $values): array
    {
        return collect($values)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->take(4)
            ->values()
            ->all();
    }

    protected function resolveManagedImage(
        mixed $input,
        mixed $file,
        string $currentPath,
        string $directory
    ): array {
        $payload = is_array($input) ? $input : [];

        if ($file) {
            $meta = $this->fileManager->storeUploadedFile($file, $directory, true);

            return [
                $meta['path'] ?? null,
                $currentPath !== '' ? $currentPath : null,
            ];
        }

        $selectedPath = trim((string) ($payload['path'] ?? ''));

        if ($selectedPath !== '') {
            $meta = $this->fileManager->assertManagedImagePath($selectedPath);

            return [
                $meta['path'] ?? null,
                $currentPath !== '' && $currentPath !== ($meta['path'] ?? null) ? $currentPath : null,
            ];
        }

        if (! empty($payload['remove'])) {
            return [null, $currentPath !== '' ? $currentPath : null];
        }

        return [$currentPath !== '' ? $currentPath : null, null];
    }
}
