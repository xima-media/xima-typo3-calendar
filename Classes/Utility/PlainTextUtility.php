<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Utility;

/**
 * Turns the RTE markup of the description fields into plain text.
 *
 * Calendar clients render DESCRIPTION as text, so the markup has to go — but block
 * boundaries carry meaning and are kept as line breaks rather than collapsed into spaces.
 */
final class PlainTextUtility
{
    public static function fromHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $text = (string)preg_replace('#<br\s*/?>#i', "\n", $html);
        $text = (string)preg_replace('#</(?:p|div|li|tr|h[1-6])\s*>#i', "\n\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = (string)preg_replace('/[^\S\n]+/u', ' ', $text);
        $text = (string)preg_replace('/ *\n */', "\n", $text);
        $text = (string)preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Shortens $text to at most $limit characters, cutting on a word boundary.
     */
    public static function truncate(string $text, int $limit): string
    {
        if ($limit <= 0 || mb_strlen($text) <= $limit) {
            return $text;
        }

        $shortened = mb_substr($text, 0, $limit);
        $lastSpace = mb_strrpos($shortened, ' ');
        if ($lastSpace !== false && $lastSpace > 0) {
            $shortened = mb_substr($shortened, 0, $lastSpace);
        }

        return rtrim($shortened) . '…';
    }
}
