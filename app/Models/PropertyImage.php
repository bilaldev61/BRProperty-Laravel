<?php

namespace App\Models;

use App\Support\CatalogueCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyImage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        // A new or removed photo changes the cached home cards.
        static::saved(fn() => app(CatalogueCache::class)->flush());
        static::deleted(fn() => app(CatalogueCache::class)->flush());
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function url(): string
    {
        // Seeded demo images ship in public/, uploaded images live on the public disk.
        if (Str::startsWith($this->path, ['http://', 'https://', 'images/'])) {
            return Str::startsWith($this->path, 'http') ? $this->path : asset($this->path);
        }

        // The facade is typed to the contract, which does not declare url().
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->path);
    }

    public function isUpload(): bool
    {
        return ! Str::startsWith($this->path, ['http://', 'https://', 'images/']);
    }
}
