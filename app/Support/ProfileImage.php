<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profile pictures and department logos (Admin, Department, Student and
 * Faculty accounts).
 *
 * - Every upload is saved under a new, unique file name, so browsers and the
 *   server cache show the new picture right away instead of an old copy.
 * - The previous file is deleted once no record uses it any more (a
 *   department and its account share one logo file).
 */
class ProfileImage
{
    /** Tables and columns that can point to a file in storage/images. */
    private const COLUMNS = [
        ['user_account', 'profile_image'],
        ['admin', 'profile_image'],
        ['department_account', 'profile_image'],
        ['department', 'profile_image'],
        ['equipment', 'image'],
    ];

    /** Saves the upload as images/{prefix}-{time}-{random}.{ext}; returns its /storage/... path. */
    public static function store(UploadedFile $file, string $prefix): string
    {
        $filename = 'images/' . $prefix . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(6))
            . '.' . $file->extension();
        Storage::disk('public')->put($filename, file_get_contents($file->getRealPath()));

        return '/storage/' . $filename;
    }

    /** Deletes the given old picture files that no record points to any more. */
    public static function deleteUnused(?string ...$paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            // Only files EquipTrack saved in storage/images, never anything else.
            if (!preg_match('#^/storage/(images/[A-Za-z0-9._-]+)$#', $path, $m) || self::inUse($path)) {
                continue;
            }

            Storage::disk('public')->delete($m[1]);
        }
    }

    private static function inUse(string $path): bool
    {
        foreach (self::COLUMNS as [$table, $column]) {
            if (DB::table($table)->where($column, $path)->exists()) {
                return true;
            }
        }

        return false;
    }
}
