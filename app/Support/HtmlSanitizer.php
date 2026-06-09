<?php

namespace App\Support;

use Mews\Purifier\Facades\Purifier;

/**
 * Centralized HTML sanitization.
 *
 * - safeHtml(): for user-authored rich text (TextBlock, HtmlBlock content).
 *   Allows common HTML tags but strips <script>, on* event handlers,
 *   javascript: URIs, and other XSS vectors.
 * - safeUrl(): for href / src in ImageBlock, VideoBlock, ButtonBlock.
 *   Blocks javascript:, data:, vbscript: schemes.
 */
class HtmlSanitizer
{
    public static function safeHtml(string $html): string
    {
        return Purifier::clean($html, 'page_content');
    }

    public static function safeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $lower = strtolower($url);
        foreach (['javascript:', 'data:', 'vbscript:', 'file:'] as $blocked) {
            if (str_starts_with($lower, $blocked)) {
                return '';
            }
        }

        return $url;
    }
}
