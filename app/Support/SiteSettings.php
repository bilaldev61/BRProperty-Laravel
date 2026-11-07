<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Lets the admin edit what used to be hard-coded in config/agent.php.
 *
 * The config file remains the source of defaults; rows in `settings` override it
 * by dot key at boot. Everything already reading `config('agent.*')` therefore
 * keeps working untouched, and deleting a row restores the file's default.
 */
class SiteSettings
{
    public const CACHE_KEY = 'site-settings';

    /** @return array<string, mixed> dot key => value */
    public function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                // During `migrate` or a fresh clone the table may not exist yet.
                if (! $this->tableExists()) {
                    return [];
                }

                return Setting::pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            // The cache is stored in the database too, so with no database at
            // all - `composer install` on a fresh clone or in CI boots the app
            // before one exists - even reading it throws. Overrides are
            // optional by design: fall back to the config file's defaults.
            return [];
        }
    }

    /** Merge the stored overrides onto config('agent'). Called from the provider. */
    public function apply(): void
    {
        foreach ($this->all() as $key => $value) {
            config(['agent.'.$key => $value]);
        }
    }

    /** @param array<string, mixed> $values dot key => value */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->refresh();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Drop an override so the config file's default applies again. */
    public function reset(string ...$keys): void
    {
        Setting::whereIn('key', $keys)->delete();

        // Reload config from the file so the removed keys revert in this process
        // too, not only on the next boot.
        config(['agent' => require config_path('agent.php')]);

        $this->refresh();
    }

    /** Clear the cache and re-apply, so the running process sees the change. */
    private function refresh(): void
    {
        $this->forget();
        $this->apply();
    }

    /** Current effective value: the override if present, else the config default. */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(config('agent'), $key, $default);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }
}
