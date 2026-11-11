<?php

namespace Tests\Support;

use App\Support\Ai\AiConnector;
use App\Support\Ai\AiUnavailable;
use App\Support\Ai\ToolFailed;
use Closure;

/**
 * Stands in for the model in tests.
 *
 * The suite must never make a real API call - that would make it slow, flaky,
 * dependent on a network and, unusually for a test suite, expensive. This
 * records what it was asked so tests can assert on the prompt the app builds,
 * which is the part actually worth testing.
 *
 * For conversations it can be scripted to call tools first, exactly as the
 * model would, so a test can check what each tool does to the database and
 * what it hands back to the model.
 */
class FakeAiConnector implements AiConnector
{
    public ?string $system = null;

    public ?string $prompt = null;

    public int $calls = 0;

    /** What each call reports it used, for the usage tracking. */
    public int $tokensPerCall = 500;

    /** @var list<array{role: string, content: string}> */
    public array $messages = [];

    /** Session id the app passed, for the provider to route on. */
    public ?string $session = null;

    /** @var list<array<string, mixed>> what the app should be recording per request */
    public array $requests = [];

    /** @var list<array<string, mixed>> */
    public array $tools = [];

    /** @var list<array{name: string, input: array<string, mixed>, result: string, failed: bool}> */
    public array $toolRuns = [];

    /** @var list<array{0: string, 1: array<string, mixed>}> */
    private array $script = [];

    public function __construct(
        private string $reply = 'Thanks for getting in touch. I will confirm and come back to you.',
        private bool $configured = true,
        private bool $shouldFail = false,
    ) {}

    public static function unconfigured(): self
    {
        return new self(configured: false);
    }

    public static function failing(): self
    {
        return new self(shouldFail: true);
    }

    /** Have the "model" call this tool before it replies. Chainable. */
    public function callsTool(string $name, array $input = []): self
    {
        $this->script[] = [$name, $input];

        return $this;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function complete(string $system, string $prompt): string
    {
        $this->calls++;
        $this->system = $system;
        $this->prompt = $prompt;
        $this->requests = [$this->usageRecord(0, [])];

        if ($this->shouldFail) {
            throw AiUnavailable::callFailed('fake outage');
        }

        return $this->reply;
    }

    public function converse(string $system, array $messages, array $tools, Closure $runTool, ?string $session = null): string
    {
        $this->calls++;
        $this->system = $system;
        $this->messages = $messages;
        $this->tools = $tools;
        $this->session = $session;
        $this->requests = [];

        if ($this->shouldFail) {
            throw AiUnavailable::callFailed('fake outage');
        }

        // One request per round, like the real loop: a round that calls a tool
        // costs a request of its own before the answer can be written.
        foreach ($this->script as $round => [$name, $input]) {
            $this->requests[] = $this->usageRecord((int) $round, [$name]);

            try {
                $this->toolRuns[] = ['name' => $name, 'input' => $input, 'result' => $runTool($name, $input), 'failed' => false];
            } catch (ToolFailed $e) {
                $this->toolRuns[] = ['name' => $name, 'input' => $input, 'result' => $e->getMessage(), 'failed' => true];
            }
        }

        $this->requests[] = $this->usageRecord(count($this->script), []);

        return $this->reply;
    }

    public function lastTokens(): int
    {
        return $this->calls > 0 ? $this->tokensPerCall : 0;
    }

    public function lastRequests(): array
    {
        return $this->requests;
    }

    /** @param list<string> $tools */
    private function usageRecord(int $round, array $tools): array
    {
        return [
            'round'              => $round,
            'model'              => 'fake-model',
            'prompt_tokens'      => $this->tokensPerCall,
            'completion_tokens'  => 0,
            'cached_tokens'      => 0,
            'cache_write_tokens' => 0,
            'latency_ms'         => 0,
            'tools'              => $tools,
        ];
    }

    /** The result the app handed back for the first call to this tool. */
    public function resultOf(string $name): ?string
    {
        foreach ($this->toolRuns as $run) {
            if ($run['name'] === $name) {
                return $run['result'];
            }
        }

        return null;
    }
}
