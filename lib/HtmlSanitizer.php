<?php
declare(strict_types=1);

/**
 * Whitelist-basierter HTML-Sanitizer für Admin-Body-Texte.
 * sanitize(): b, i, u, span style="font-size" (14–24 px).
 * sanitizeLegal(): Zusätzlich h2, h3, p, ul, ol, li, a (href), br – für Impressum/Datenschutz.
 */
class HtmlSanitizer {
    private const MIN_FONT_SIZE = 14;
    private const MAX_FONT_SIZE = 24;
    private const LEGAL_TAGS = '<b><i><u><span><h2><h3><p><ul><ol><li><a><br><br/><strong><em>';

    /**
     * Sanitiert HTML auf erlaubte Formatierung (B, I, U, Schriftgröße).
     */
    public static function sanitize(string $html): string {
        $html = trim($html);
        if ($html === '') return '';

        $html = self::blockToBr($html);
        $html = self::stripDangerousContent($html);
        $html = strip_tags($html, '<b><i><u><span><br><br/>');
        $html = self::sanitizeSpanStyles($html);
        return trim($html);
    }

    private static function blockToBr(string $html): string {
        $html = preg_replace('/<\/div>\s*/i', "\n", $html);
        $html = preg_replace('/<div[^>]*>/i', '', $html);
        $html = preg_replace('/<\/p>\s*/i', "\n", $html);
        $html = preg_replace('/<p[^>]*>/i', '', $html);
        return $html;
    }

    private static function stripDangerousContent(string $html): string {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        return $html;
    }

    private static function sanitizeSpanStyles(string $html): string {
        return preg_replace_callback(
            '/<span\s+([^>]*)>/i',
            function ($m) {
                $attrs = $m[1];
                if (preg_match('/style\s*=\s*["\']([^"\']*)["\']/', $attrs, $s)) {
                    $style = $s[1];
                    if (preg_match('/font-size\s*:\s*(\d+)\s*px/i', $style, $fs)) {
                        $px = (int) $fs[1];
                        if ($px >= self::MIN_FONT_SIZE && $px <= self::MAX_FONT_SIZE) {
                            return '<span style="font-size: ' . $px . 'px">';
                        }
                    }
                }
                return '<span>';
            },
            $html
        );
    }

    /**
     * Sanitiert Legal-Seiten (Impressum/Datenschutz) – erlaubt mehr strukturierte Tags.
     */
    public static function sanitizeLegal(string $html): string {
        $html = trim($html);
        if ($html === '') return '';
        $html = self::blockToBr($html);
        $html = self::stripDangerousContent($html);
        $html = strip_tags($html, self::LEGAL_TAGS);
        $html = self::sanitizeSpanStyles($html);
        $html = self::sanitizeLinks($html);
        return trim($html);
    }

    private static function sanitizeLinks(string $html): string {
        return preg_replace_callback(
            '/<a\s+([^>]*)>/i',
            function ($m) {
                if (preg_match('/href\s*=\s*["\']([^"\']*)["\']/', $m[1], $h)) {
                    $href = $h[1];
                    if (preg_match('#^(https?://|mailto:|tel:)#i', $href)) {
                        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
                    }
                }
                return '';
            },
            $html
        );
    }

    /**
     * Prüft, ob der String HTML-Tags enthält (für Rückwärtskompatibilität).
     */
    public static function containsHtml(string $s): bool {
        return (bool) preg_match('/<[a-z][a-z0-9]*\b/i', $s);
    }
}
