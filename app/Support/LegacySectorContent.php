<?php

namespace App\Support;

class LegacySectorContent
{
    public static function extract(?string $description): array
    {
        $remaining = $description ?? '';
        $faqBlocks = [];
        $buttons = [];

        $remaining = preg_replace_callback(
            '/<div>\s*<!--\s*legacy section ([23]) benefits\s*-->\s*(.*?)\s*<\/div>/is',
            function (array $matches) use (&$faqBlocks): string {
                $faqs = self::decodeFaqs($matches[2]);

                if ($faqs !== []) {
                    $faqBlocks['section_'.$matches[1]] = ['faqs' => $faqs];
                }

                return '';
            },
            $remaining
        );

        $remaining = preg_replace_callback(
            '/<p>\s*<!--\s*legacy section ([123]) button\s*-->\s*(.*?)\s*<\/p>/is',
            function (array $matches) use (&$buttons): string {
                $buttons['section_'.$matches[1]] = self::decodeButton($matches[2]);

                return '';
            },
            $remaining
        );

        $remaining = preg_replace('/<p>\s*<!--\s*legacy badge\s*-->.*?<\/p>/is', '', $remaining);
        $remaining = trim((string) preg_replace('/(?:\r?\n\s*){3,}/', "\n\n", $remaining));
        $remaining = preg_replace('/^([^<]+)<\/p>/is', '<p>$1</p>', $remaining, 1);

        foreach ($faqBlocks as $section => &$block) {
            $block = array_merge($block, $buttons[$section] ?? []);
        }

        return [
            'description' => $remaining !== '' ? $remaining : null,
            'faq_blocks' => $faqBlocks,
        ];
    }

    private static function decodeFaqs(string $json): array
    {
        $json = trim($json);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            $decoded = json_decode(
                html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                true
            );
        }

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn ($faq) => [
                'question' => HtmlCleaner::plainText(data_get($faq, 'question')),
                'answer' => HtmlCleaner::clean(data_get($faq, 'answer')),
            ])
            ->filter(fn ($faq) => $faq['question'] && $faq['answer'])
            ->values()
            ->all();
    }

    private static function decodeButton(string $text): array
    {
        $text = HtmlCleaner::plainText($text);

        if (! $text) {
            return [];
        }

        if (! preg_match('/^(.*?)\s*\(([^()]*)\)\s*$/s', $text, $matches)) {
            return ['button_name' => $text];
        }

        return [
            'button_name' => trim($matches[1]) ?: null,
            'button_url' => HtmlCleaner::safeUrl($matches[2]),
        ];
    }
}
