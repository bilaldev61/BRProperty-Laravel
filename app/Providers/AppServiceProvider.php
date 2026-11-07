<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Ai\AiConnector;
use App\Support\Ai\ClaudeConnector;
use App\Support\Ai\NullConnector;
use App\Support\Ai\OpenRouterConnector;
use App\Support\SiteSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SiteSettings::class);

        // No API key means no AI, and the app says so honestly rather than
        // failing at the point of use. Tests bind their own fake over this.
        $this->app->singleton(AiConnector::class, fn() => match (true) {
            config('ai.driver') === 'openrouter' && filled(config('ai.openrouter.key')) => new OpenRouterConnector(
                apiKey: (string) config('ai.openrouter.key'),
                model: (string) config('ai.openrouter.model'),
                chatModel: (string) config('ai.openrouter.chat_model'),
                maxTokens: (int) config('ai.max_tokens'),
                chatMaxTokens: (int) config('ai.openrouter.chat_max_tokens'),
                timeout: (int) config('ai.timeout'),
                maxToolRounds: (int) config('ai.chat.max_tool_rounds'),
                baseUrl: (string) config('ai.openrouter.base_url'),
            ),
            config('ai.driver') === 'anthropic' && filled(config('ai.key')) => new ClaudeConnector(
                apiKey: (string) config('ai.key'),
                model: (string) config('ai.model'),
                maxTokens: (int) config('ai.max_tokens'),
                timeout: (int) config('ai.timeout'),
                chatModel: (string) config('ai.chat.model'),
                chatEffort: (string) config('ai.chat.effort'),
                chatMaxTokens: (int) config('ai.chat.max_tokens'),
                maxToolRounds: (int) config('ai.chat.max_tool_rounds'),
            ),
            default => new NullConnector(),
        });
    }

    public function boot(): void
    {
        /*
         * One gate per ability, named exactly like the ability, so Blade can say
         * @can('settings.manage') and a route can say ability:settings.manage and
         * both mean the same thing. Defining them from Role::ABILITIES means a new
         * ability cannot be added to the enum and then be silently ungated.
         */
        foreach (Role::ABILITIES as $ability => $description) {
            Gate::define($ability, fn(User $user) => $user->hasAbility($ability));
        }

        // Overlay the admin-editable settings onto config/agent.php so every
        // existing config('agent.*') call picks them up without changes.
        $this->app->make(SiteSettings::class)->apply();

        // Every assistant message is a paid model call, from an anonymous
        // visitor. Per-visitor limits stop one person running up the bill; the
        // site-wide limit caps the worst day.
        RateLimiter::for('assistant', function (Request $request) {
            $busy = fn() => response()->json([
                'message' => 'The assistant is busy right now. Please call or WhatsApp ' . config('agent.phone') . '.',
            ], 429);

            return [
                Limit::perMinute(8)->by('assistant:' . $request->ip())->response($busy),
                Limit::perDay(150)->by('assistant-day:' . $request->ip())->response($busy),
                Limit::perDay((int) config('ai.chat.daily_limit'))->by('assistant-site')->response($busy),
            ];
        });
    }
}
