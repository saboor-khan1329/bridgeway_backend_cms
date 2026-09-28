<?php

namespace App\Support;

class HtmlCleaner
{
    public static function clean(?string $html): ?string
    {
        if (!$html) return null;

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Remove shortcodes [[...]]
        $html = preg_replace('/\[\[.*?\]\]/s', '', $html);

        $html = preg_replace('/<\/?span[^>]*>/i', '', $html);

        $allowedTags = '<p><br><ul><ol><li><strong><b><em><i><a><h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><td><th>';
        $html = strip_tags($html, $allowedTags);

        $html = preg_replace_callback(
            '/<([a-z][a-z0-9]*)([^>]*)>/i',
            function ($matches) {
                $tag = $matches[1];
                $attrs = $matches[2];

                preg_match_all('/\s+href=("|\')(.*?)\1/i', $attrs, $allowed, PREG_SET_ORDER);

                $cleanAttrs = '';
                foreach ($allowed as $attr) {
                    $href = self::safeUrl($attr[2]);

                    if ($href !== null) {
                        $cleanAttrs .= ' href="' . htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
                    }
                }

                return "<{$tag}{$cleanAttrs}>";
            },
            $html
        );
        $html = str_replace(["\r", "\n"], '', $html);
        $html = str_replace('&nbsp;', ' ', $html);
        $html = preg_replace('/\s{2,}/', ' ', $html);
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $html);
        $html = preg_replace('/<(\w+)(?:\s[^>]*)?>\s*<\/\1>/', '', $html);
        $html = trim($html);

        return $html ?: null;
    }

    public static function safeUrl(?string $href): ?string
    {
        if ($href === null) {
            return null;
        }

        $href = trim($href);

        if ($href === '') {
            return null;
        }

        $normalized = preg_replace('/[\x00-\x20\x7F]+/', '', $href);
        $scheme = parse_url($normalized, PHP_URL_SCHEME);

        if ($scheme !== null && ! in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)) {
            return null;
        }

        // An internal path with no leading slash is browser-relative, and
        // resolves against whatever page it happens to be rendered on:
        // "contact-us" becomes /contact-us on the home page but
        // /locations/contact-us on a location page, and /services/x/contact-us
        // one level deeper again. Content editors type the bare slug — it is
        // the natural thing to type — so the fix belongs here, at the one
        // point every link in the API passes through, rather than in each of
        // the two dozen callers or in the frontend.
        //
        // Only genuine paths are touched. Anything carrying a scheme, a
        // protocol-relative "//host", a fragment or a query is left exactly as
        // written.
        if ($scheme === null
            && ! str_starts_with($href, '/')
            && ! str_starts_with($href, '#')
            && ! str_starts_with($href, '?')) {
            return '/'.ltrim($href, '/');
        }

        return $href;
    }

    public static function plainText(?string $html): ?string
    {
        if (!$html) return null;

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove shortcodes [[...]]
        $html = preg_replace('/\[\[.*?\]\]/s', '', $html);

        // Convert block-level tags to spaces so words don't merge
        $html = preg_replace('/<(p|br|li|h[1-6]|tr|td|th|div|blockquote)(\s[^>]*)?\/?>/i', ' ', $html);

        // Strip ALL remaining tags
        $html = strip_tags($html);

        $html = str_replace('&nbsp;', ' ', $html);
        $html = str_replace(["\r", "\n", "\t"], ' ', $html);
        $html = preg_replace('/\s{2,}/', ' ', $html);
        $html = trim($html);

        return $html ?: null;
    }

    public static function cleanJson(?string $json)
    {
        if (!$json) return null;

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }
    }
}