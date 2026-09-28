<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Support\FrontendSeoPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendSeoPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_seo_uses_record_context_over_site_defaults(): void
    {
        SiteSetting::bulkPut([
            'default_meta_title' => 'Bridgeway Digital',
            'default_meta_description' => 'Digital growth services.',
            'template_service_meta_title' => '{title} | Bridgeway Digital',
        ], 'seo');
        $seo = FrontendSeoPresenter::present('service', null, [
            'title' => 'Laravel Web Development',
            'description' => 'Existing CMS service description.',
            'canonical' => '/service/laravel-development-company',
        ]);
        $this->assertSame('Laravel Web Development', $seo['title']);
        $this->assertSame('Existing CMS service description.', $seo['description']);
    }

    public function test_explicit_editor_seo_and_disabled_schema_are_preserved(): void
    {
        $seo = FrontendSeoPresenter::present('blog', [
            'meta_title' => 'Editor title', 'meta_description' => 'Editor description',
            'enable_schema' => false, 'robots' => ['index' => 'noindex', 'follow' => 'nofollow'],
        ], ['title' => 'Post title'], ['schemaGraph' => [['@type' => 'BlogPosting']]]);
        $this->assertSame('Editor title', $seo['title']);
        $this->assertSame('Editor description', $seo['description']);
        $this->assertSame(['index' => false, 'follow' => false], $seo['robots']);
        $this->assertArrayNotHasKey('schemaGraph', $seo);
    }

    public function test_page_level_schema_overrides_template_and_global_configurator_schema(): void
    {
        SiteSetting::bulkPut([
            'default_schema' => json_encode(['@type' => 'GlobalOrganization', 'name' => 'Global Org']),
            'template_service_schema' => json_encode(['@type' => 'TemplateService', 'name' => '{title} Template']),
        ], 'seo');

        $seo = FrontendSeoPresenter::present('service', [
            'schema' => json_encode(['@type' => 'CustomPageService', 'name' => 'Custom Dedicated Service']),
            'enable_schema' => true,
        ], [
            'title' => 'Custom Dedicated Service',
            'url' => 'https://bridgewaydigital.com/service/custom',
        ]);

        $this->assertArrayHasKey('schemaGraph', $seo);
        $this->assertSame('CustomPageService', $seo['schemaGraph'][0]['@type']);
        $this->assertSame('Custom Dedicated Service', $seo['schemaGraph'][0]['name']);
    }

    public function test_page_inherits_template_schema_when_page_schema_is_empty(): void
    {
        SiteSetting::bulkPut([
            'default_schema' => json_encode(['@type' => 'GlobalOrganization', 'name' => 'Global Org']),
            'template_amazon_service_schema' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => '{title} - Amazon Optimization',
                'description' => '{description}',
            ]),
        ], 'seo');

        $seo = FrontendSeoPresenter::present('amazon_service', [
            'schema' => null, // empty page-level schema
            'enable_schema' => true,
        ], [
            'title' => 'Amazon PPC Management',
            'description' => 'Scale your Amazon store with PPC.',
            'url' => 'https://bridgewaydigital.com/service/amazon-ppc',
        ]);

        $this->assertArrayHasKey('schemaGraph', $seo);
        $this->assertSame('Service', $seo['schemaGraph'][0]['@type']);
        $this->assertSame('Amazon PPC Management - Amazon Optimization', $seo['schemaGraph'][0]['name']);
        $this->assertSame('Scale your Amazon store with PPC.', $seo['schemaGraph'][0]['description']);
    }

    public function test_page_inherits_global_schema_when_template_schema_is_empty(): void
    {
        SiteSetting::bulkPut([
            'default_schema' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Bridgeway Digital',
                'url' => 'https://bridgewaydigital.com',
            ]),
            'template_static_schema' => '', // empty template schema
        ], 'seo');

        $seo = FrontendSeoPresenter::present('static', [
            'schema' => null,
            'enable_schema' => true,
        ], [
            'title' => 'About Us',
            'url' => 'https://bridgewaydigital.com/about-us',
        ]);

        $this->assertArrayHasKey('schemaGraph', $seo);
        $this->assertSame('Organization', $seo['schemaGraph'][0]['@type']);
        $this->assertSame('Bridgeway Digital', $seo['schemaGraph'][0]['name']);
    }

    public function test_page_falls_back_to_builtin_generator_when_no_configurator_or_page_schema_is_set(): void
    {
        SiteSetting::bulkPut([
            'default_schema' => '',
            'template_service_schema' => '',
        ], 'seo');

        $builtinGraph = [
            ['@type' => 'Service', 'name' => 'Builtin Generator Service'],
            ['@type' => 'BreadcrumbList', 'itemListElement' => []],
        ];

        $seo = FrontendSeoPresenter::present('service', [
            'schema' => null,
            'enable_schema' => true,
        ], [
            'title' => 'Builtin Generator Service',
        ], [
            'schemaGraph' => $builtinGraph,
        ]);

        $this->assertArrayHasKey('schemaGraph', $seo);
        $this->assertSame($builtinGraph, $seo['schemaGraph']);
    }
}
