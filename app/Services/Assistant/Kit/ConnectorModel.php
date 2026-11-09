<?php

namespace App\Services\Assistant\Kit;

use App\Support\Ai\AiConnector;
use AssistantKit\Contracts\LanguageModel;

/**
 * The Kit's model port, plugged into the app's existing connector.
 *
 * The two speak almost the same language: only the tool schema key differs
 * (`parameters` here, `inputSchema` there) and tool results come back as JSON
 * text, which is what the provider wants anyway.
 */
class ConnectorModel implements LanguageModel
{
    public function __construct(private readonly AiConnector $ai) {}

    public function isAvailable(): bool
    {
        return (bool) config('ai.chat.model_enabled') && $this->ai->isConfigured();
    }

    public function converse(string $system, array $messages, array $tools, callable $runTool): string
    {
        $definitions = array_map(fn(array $tool) => [
            'name'        => $tool['name'],
            'description' => $tool['description'],
            'inputSchema' => $tool['parameters'],
        ], $tools);

        return $this->ai->converse($system, $messages, $definitions, function (string $name, array $input) use ($runTool): string {
            try {
                $result = $runTool($name, $input);
            } catch (\RuntimeException $e) {
                // The Kit throws ToolError; the connector only knows ToolFailed.
                return 'Error: ' . $e->getMessage();
            }

            return is_string($result) ? $result : (json_encode($result) ?: '');
        });
    }
}
