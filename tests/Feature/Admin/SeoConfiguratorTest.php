<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_configurator_page_loads_with_expected_templates(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.seo.configurator.edit'));

        $response->assertOk();
        $response->assertSee('SEO Configurator');
        $response->assertSee('Amazon Services');
        $response->assertSee('Development Services');
        $response->assertSee('Static & Content Pages');
        $response->assertSee('Sitewide Fallbacks');
    }

    public function test_seo_configurator_saves_valid_configuration(): void
    {
        $this->actingAsAdmin();

        $validSchema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => '{title}',
            'description' => '{description}',
        ]);

        $validOg = json_encode([
            'og:title' => '{title}',
            'og:type' => 'website',
        ]);

        $response = $this->put(route('admin.seo.configurator.update'), [
            'default_meta_title' => 'Bridgeway Digital Growth Agency',
            'default_meta_description' => 'Global digital solutions.',
            'default_schema' => $validSchema,
            'template_amazon_service_meta_title' => '{title} | Amazon Agency',
            'template_amazon_service_schema' => $validSchema,
            'template_amazon_service_og_tags' => $validOg,
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('Bridgeway Digital Growth Agency', SiteSetting::get('default_meta_title'));
        $this->assertSame('{title} | Amazon Agency', SiteSetting::get('template_amazon_service_meta_title'));
        $this->assertStringContainsString('Amazon Agency', SiteSetting::get('template_amazon_service_meta_title'));
    }

    public function test_seo_configurator_rejects_malformed_json_schema(): void
    {
        $this->actingAsAdmin();

        $response = $this->put(route('admin.seo.configurator.update'), [
            'default_schema' => '{ malformed json: not valid }',
        ]);

        $response->assertSessionHasErrors('default_schema');
    }
}
