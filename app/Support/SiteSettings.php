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

    /**
     * The assistant knobs the settings screen may override.
     *
     * Everything else under `ai.` is left alone on purpose: the driver, the
     * model name and the key are not tuning, and a typo in any of them takes
     * the assistant offline rather than making it cheaper.
     */
    public const ASSISTANT_KEYS = [
        'ai.chat.history',
        'ai.chat.max_messages',
        'ai.chat.retention_days',
        'ai.chat.daily_limit',
        'ai.chat.model_enabled',
        'ai.chat.quick_answers',
    ];

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
            // Overrides are optional by design: on a fresh clone or in CI the
            // app boots before a database exists, so even reading them throws.
            // Fall back to the config file's defaults.
            return [];
        }
    }

    /** Merge the stored overrides onto config('agent') and config('ai'). Called from the provider. */
    public function apply(): void
    {
        foreach ($this->all() as $key => $value) {
            $path = $this->path($key);

            if ($path !== null) {
                config([$path => $value]);
            }
        }
    }

    /** Absolute config path for a stored key, or null when it may not be overridden. */
    private function path(string $key): ?string
    {
        if (str_starts_with($key, 'ai.')) {
            return in_array($key, self::ASSISTANT_KEYS, true) ? $key : null;
        }

        // Every other row is an `agent.*` key written without the prefix.
        return 'agent.' . $key;
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

        // Reload both files so the removed keys revert in this process too, not
        // only on the next boot; then re-apply whatever overrides remain.
        config(['agent' => require config_path('agent.php')]);
        config(['ai' => require config_path('ai.php')]);

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
