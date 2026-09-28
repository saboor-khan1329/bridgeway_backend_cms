<?php

namespace Tests\Unit;

use App\Support\FrontendSectionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the backend's section list in step with the frontend's.
 *
 * The API drops any section whose type the frontend has no component for. That
 * is the right behaviour — a section that cannot render should not be sent —
 * but it fails silently, so a component added to one list and forgotten in the
 * other would simply never appear on a page. This turns that into a test
 * failure instead.
 *
 * Skips when the frontend is not checked out beside this app, which is the
 * normal state on an API-only deploy.
 */
class FrontendSectionRegistryTest extends TestCase
{
    private const FRONTEND_REGISTRY = __DIR__
        .'/../../../frontend/src/components/page-template/sectionRegistry.js';

    public function test_backend_and_frontend_agree_on_the_section_list(): void
    {
        $frontendTypes = $this->frontendTypes();

        $backendTypes = FrontendSectionRegistry::TYPES;
        sort($backendTypes);
        sort($frontendTypes);

        $missingFromBackend = array_diff($frontendTypes, $backendTypes);
        $missingFromFrontend = array_diff($backendTypes, $frontendTypes);

        $this->assertSame(
            [],
            array_values($missingFromBackend),
            'Frontend components with no entry in FrontendSectionRegistry — sections of these types would be dropped by the API.'
        );

        $this->assertSame(
            [],
            array_values($missingFromFrontend),
            'FrontendSectionRegistry lists types the frontend cannot render.'
        );
    }

    public function test_supports_accepts_a_known_type_and_rejects_anything_else(): void
    {
        $this->assertTrue(FrontendSectionRegistry::supports('serviceHero'));
        $this->assertFalse(FrontendSectionRegistry::supports('someRemovedComponent'));
        $this->assertFalse(FrontendSectionRegistry::supports(null));
        $this->assertFalse(FrontendSectionRegistry::supports(''));
        // Types are case-sensitive; the frontend keys them exactly as written.
        $this->assertFalse(FrontendSectionRegistry::supports('servicehero'));
    }

    public function test_the_list_has_no_duplicates(): void
    {
        $types = FrontendSectionRegistry::TYPES;

        $this->assertSame(count($types), count(array_unique($types)));
    }

    /**
     * The keys of the frontend's PAGE_SECTION_REGISTRY.
     *
     * @return array<int, string>
     */
    private function frontendTypes(): array
    {
        if (! is_file(self::FRONTEND_REGISTRY)) {
            $this->markTestSkipped('Frontend registry not present beside this app.');
        }

        $source = (string) file_get_contents(self::FRONTEND_REGISTRY);

        $start = strpos($source, 'PAGE_SECTION_REGISTRY = Object.freeze({');

        $this->assertNotFalse($start, 'PAGE_SECTION_REGISTRY not found in the frontend registry.');

        $body = substr($source, $start);
        $body = substr($body, 0, strpos($body, '});'));

        preg_match_all('/^\s{2}([A-Za-z0-9_]+):/m', $body, $matches);

        $types = $matches[1] ?? [];

        $this->assertNotEmpty($types, 'No section types parsed from the frontend registry.');

        return $types;
    }
}
