<?php

namespace Tests\Unit;

use App\Support\HtmlCleaner;
use App\Support\SeoFormatter;
use PHPUnit\Framework\TestCase;

class HtmlCleanerTest extends TestCase
{
    public function test_clean_keeps_rich_text_and_removes_unsafe_link_attributes(): void
    {
        $html = '<p>Hello <strong>team</strong> <a href="javascript:alert(1)" class="bad">bad</a> <a href="/contact-us" class="good">safe</a></p>';

        $this->assertSame(
            '<p>Hello <strong>team</strong> <a>bad</a> <a href="/contact-us">safe</a></p>',
            HtmlCleaner::clean($html)
        );
    }

    public function test_plain_text_removes_html_tags_without_merging_blocks(): void
    {
        $this->assertSame(
            'First paragraph Second paragraph',
            HtmlCleaner::plainText('<p>First paragraph</p><p>Second <strong>paragraph</strong></p>')
        );
    }

    public function test_seo_formatter_returns_plain_meta_title_and_description(): void
    {
        $seo = (object) [
            'meta_title' => '<strong>Clean title</strong>',
            'meta_description' => '<p>Clean <em>description</em></p>',
            'seo_content' => '<p>Rich <strong>content</strong></p>',
            'schema' => null,
            'microdata' => null,
            'enable_schema' => false,
            'enable_microdata' => false,
            'robots_index' => 'index',
            'robots_follow' => 'follow',
            'og_tags' => null,
        ];

        $formatted = SeoFormatter::format($seo);

        $this->assertSame('Clean title', $formatted['meta_title']);
        $this->assertSame('Clean description', $formatted['meta_description']);
        $this->assertSame('<p>Rich <strong>content</strong></p>', $formatted['seo_content']);
    }
}
