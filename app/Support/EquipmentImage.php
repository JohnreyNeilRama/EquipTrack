<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The equipment photo from the Admin and Department equipment forms, checked
 * before it is saved. The form sends one of:
 *
 * - an uploaded photo (a data: URL): kept only if its actual contents are a
 *   JPG, PNG, WEBP or GIF image of 5MB or less, whatever type it claims to
 *   be, and saved with the extension of what it really is;
 * - an image link ("or use an image link"): kept only if it is a proper
 *   https:// or http:// web address;
 * - the photo it already has (/storage/images/...), or nothing.
 *
 * Anything else is refused with a message the form shows.
 */
class EquipmentImage
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Real image type (from the file's contents) => saved extension. */
    private const TYPES = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    /** @return string the value to store in equipment.image ('' for no photo) */
    public static function normalize(?string $image, ?int $equipmentId): string
    {
        $image = trim((string) $image);

        if ($image === '') {
            return '';
        }

        if (str_starts_with(strtolower($image), 'data:')) {
            return self::storeUpload($image, $equipmentId);
        }

        // The photo it already has, saved by this form earlier
        if (preg_match('#^/storage/images/[A-Za-z0-9._-]+$#', $image)) {
            return $image;
        }

        if (self::isWebAddress($image)) {
            return $image;
        }

        self::refuse('The image link must be a web address starting with https:// (or http://).');
    }

    private static function storeUpload(string $dataUrl, ?int $equipmentId): string
    {
        if (!preg_match('#^data:[^;,]*;base64,(.+)$#is', $dataUrl, $m)) {
            self::refuse('The photo could not be read. Choose a JPG, PNG, WEBP or GIF image.');
        }

        $binary = base64_decode($m[1], true);
        if ($binary === false || $binary === '') {
            self::refuse('The photo could not be read. Choose a JPG, PNG, WEBP or GIF image.');
        }

        if (strlen($binary) > self::MAX_BYTES) {
            self::refuse('The photo must be 5MB or smaller.');
        }

        // What the file really is, from its contents (not its name or label)
        $info = @getimagesizefromstring($binary);
        $ext = $info ? (self::TYPES[$info[2]] ?? null) : null;
        if ($ext === null) {
            self::refuse('The photo must be a JPG, PNG, WEBP or GIF image.');
        }

        $filename = 'images/equipment-' . ($equipmentId ?: 'new-' . bin2hex(random_bytes(4))) . '.' . $ext;
        Storage::disk('public')->put($filename, $binary);

        return '/storage/' . $filename;
    }

    private static function isWebAddress(string $url): bool
    {
        if (strlen($url) > 2048 || preg_match('/\s/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true) && !empty($parts['host']);
    }

    private static function refuse(string $message): never
    {
        throw ValidationException::withMessages(['image' => $message]);
    }
}
