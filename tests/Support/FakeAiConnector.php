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
    ) {
    }

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

        if ($this->shouldFail) {
            throw AiUnavailable::callFailed('fake outage');
        }

        return $this->reply;
    }

    public function converse(string $system, array $messages, array $tools, Closure $runTool): string
    {
        $this->calls++;
        $this->system = $system;
        $this->messages = $messages;
        $this->tools = $tools;

        if ($this->shouldFail) {
            throw AiUnavailable::callFailed('fake outage');
        }

        foreach ($this->script as [$name, $input]) {
            try {
                $this->toolRuns[] = ['name' => $name, 'input' => $input, 'result' => $runTool($name, $input), 'failed' => false];
            } catch (ToolFailed $e) {
                $this->toolRuns[] = ['name' => $name, 'input' => $input, 'result' => $e->getMessage(), 'failed' => true];
            }
        }

        return $this->reply;
    }

    public function lastTokens(): int
    {
        return $this->calls > 0 ? $this->tokensPerCall : 0;
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
