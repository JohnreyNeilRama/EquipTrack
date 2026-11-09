<?php

namespace App\Support;

/**
 * The initials picture shown when an account (or a department) has no
 * uploaded picture, e.g. "JR" on a blue circle.
 *
 * Drawn by EquipTrack itself (GET /avatar), so nobody's name is sent to an
 * outside website and the pictures work offline. The address takes the same
 * details as before: name, background, color, size and bold.
 */
class Avatar
{
    public const BACKGROUND = '385585';

    public const COLOR = 'ffffff';

    /** The /avatar address for a name (used by the server-side pages). */
    public static function url(string $name, int $size = 128, bool $bold = false): string
    {
        return url('/avatar') . '?' . http_build_query(array_filter([
            'name' => $name !== '' ? $name : 'User',
            'background' => self::BACKGROUND,
            'color' => 'fff',
            'size' => $size,
            'bold' => $bold ? 'true' : null,
        ]));
    }

    /** Up to two letters: first and last word ("Dr. Jane Doe" → "DD"), or the first two of one word. */
    public static function initials(string $name): string
    {
        $words = preg_split('/[\s]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_values(array_filter(array_map(
            fn (string $word) => self::firstLetter($word),
            $words,
        )));

        if (count($letters) >= 2) {
            return mb_strtoupper($letters[0] . end($letters));
        }

        if (count($words) === 1) {
            $chars = preg_replace('/[^\p{L}\p{N}]/u', '', $words[0]);

            return mb_strtoupper(mb_substr((string) $chars, 0, 2)) ?: '?';
        }

        return '?';
    }

    /** The SVG picture: initials centred on a square of the background colour. */
    public static function svg(string $name, ?string $background = null, ?string $color = null, ?int $size = null, bool $bold = false): string
    {
        $size = max(16, min(512, $size ?: 64));
        $background = self::hex($background) ?? self::BACKGROUND;
        $color = self::hex($color) ?? self::COLOR;
        $text = htmlspecialchars(self::initials($name), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $fontSize = round($size * 0.42, 1);
        $weight = $bold ? 700 : 600;

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
            . '<rect width="100%" height="100%" fill="#' . $background . '"/>'
            . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#' . $color . '"'
            . ' font-family="Inter, \'Segoe UI\', Roboto, Arial, sans-serif" font-size="' . $fontSize . '" font-weight="' . $weight . '">'
            . $text . '</text></svg>';
    }

    private static function firstLetter(string $word): string
    {
        return preg_match('/[\p{L}\p{N}]/u', $word, $m) ? $m[0] : '';
    }

    /** A 3- or 6-digit hex colour without "#", or null. */
    private static function hex(?string $value): ?string
    {
        $value = ltrim(trim((string) $value), '#');

        return preg_match('/^([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) ? strtolower($value) : null;
    }
}
