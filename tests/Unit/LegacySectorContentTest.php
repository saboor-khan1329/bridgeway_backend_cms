<?php

namespace Tests\Unit;

use App\Support\LegacySectorContent;
use PHPUnit\Framework\TestCase;

class LegacySectorContentTest extends TestCase
{
    public function test_extract_moves_legacy_benefits_into_structured_faq_blocks(): void
    {
        $content = <<<'HTML'
<p>Keep this content.</p>
<p><!-- legacy badge -->Bridgeway - Sectors - #</p>
<p><!-- legacy section 2 button -->Learn More (contact-us)</p>
<div><!-- legacy section 2 benefits -->
[{"question":"How much?","answer":"Call <strong>our team</strong>."},{"question":"Can quotes survive?","answer":"Use &quot;encoded quotes&quot;."}]
</div>
HTML;

        $formatted = LegacySectorContent::extract($content);

        $this->assertSame('<p>Keep this content.</p>', $formatted['description']);
        $this->assertSame('Learn More', $formatted['faq_blocks']['section_2']['button_name']);
        $this->assertSame('/contact-us', $formatted['faq_blocks']['section_2']['button_url']);
        $this->assertSame('How much?', $formatted['faq_blocks']['section_2']['faqs'][0]['question']);
        $this->assertSame(
            'Call <strong>our team</strong>.',
            $formatted['faq_blocks']['section_2']['faqs'][0]['answer']
        );
        $this->assertSame(
            'Use "encoded quotes".',
            $formatted['faq_blocks']['section_2']['faqs'][1]['answer']
        );
    }
}
