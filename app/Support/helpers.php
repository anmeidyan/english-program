<?php

declare(strict_types=1);

if (! function_exists('clean_html')) {
    /** Allow-list sanitiser for admin rich text (no scripts, no event handlers, no inline styles). */
    function clean_html(?string $html): string
    {
        $html = strip_tags((string) $html, '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h3><h4><blockquote><code><pre><span>');
        $html = preg_replace('/\s(on\w+|style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

        return preg_replace('/href\s*=\s*("|\')\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? '';
    }
}
