<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves a branding image path to a URL.
 *
 * Files shipped with the repo live in public/ ("images/agent.webp"); anything
 * uploaded through the admin lives on the public disk. Same rule as
 * PropertyImage::url(), kept here for the config-driven branding images.
 */
class Media
{
    public static function url(?string $path, ?string $fallback = null): ?string
    {
        $path = $path ?: $fallback;

        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, ['images/', '/images/'])) {
            return asset(ltrim($path, '/'));
        }

        // The facade is typed to the contract, which does not declare url().
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }

    /** Convenience for config('agent.*') image keys. */
    public static function agent(string $key, ?string $fallback = null): ?string
    {
        return static::url(config('agent.' . $key), $fallback);
    }

    public static function isUpload(?string $path): bool
    {
        return filled($path) && ! Str::startsWith($path, ['http://', 'https://', 'images/', '/images/']);
    }
}
