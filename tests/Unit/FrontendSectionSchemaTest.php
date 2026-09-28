<?php

namespace Tests\Unit;

use App\Support\FrontendSectionRegistry;
use App\Support\FrontendSectionSchema;
use App\Support\SectionDocument;
use Tests\TestCase;

class FrontendSectionSchemaTest extends TestCase
{
    public function test_every_renderable_component_has_an_explicit_field_structure(): void
    {
        $types = FrontendSectionRegistry::types();
        $schemas = array_keys(FrontendSectionSchema::FIELDS);
        sort($types);
        sort($schemas);
        $this->assertSame($types, $schemas);
    }

    public function test_new_service_sections_do_not_need_an_example_database_record(): void
    {
        $hero = SectionDocument::blankSection('serviceHero');
        $this->assertSame('serviceHero', $hero['type']);
        $this->assertSame('', $hero['data']['heading']);
        $this->assertArrayHasKey('form', $hero['data']);
        $this->assertArrayHasKey('testimonial', $hero['data']);
    }

    public function test_field_structures_contain_no_authored_page_content(): void
    {
        $check = function ($value) use (&$check): void {
            if (is_array($value)) {
                foreach ($value as $child) {
                    $check($child);
                }
                return;
            }
            $this->assertContains($value, ['', 0, false, null], true);
        };
        $check(FrontendSectionSchema::FIELDS);
    }
}
