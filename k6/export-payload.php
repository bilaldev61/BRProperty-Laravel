<?php

/**
 * Captures the exact chat body the app sends to the model so k6 replays the real
 * prompt + tools instead of a guess: php k6/export-payload.php
 * Writes k6/payload.json (no secrets - the API key is an auth header).
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChatConversation;
use App\Services\Assistant\AssistantTools;
use App\Services\Assistant\PropertyAssistant;

$assistant = app(PropertyAssistant::class);

// General-page conversation (no listing), as a visitor gets off a property page.
$conversation = new ChatConversation();

$system = new ReflectionMethod($assistant, 'system');
$system->setAccessible(true);
$system = $system->invoke($assistant, $conversation);

// Judgement question (NEEDS_JUDGEMENT) so the tool path is exercised.
$question = 'compare 2 bed in Marina with JVC 3 bed';

// Map the tool definitions exactly as OpenRouterConnector::converse() does.
$tools = array_map(fn(array $t) => [
    'type'     => 'function',
    'function' => [
        'name'        => $t['name'],
        'description' => $t['description'],
        'parameters'  => $t['inputSchema'],
    ],
], AssistantTools::definitions());

$payload = array_filter([
    'model'            => config('ai.openrouter.chat_model'),
    'max_tokens'       => config('ai.openrouter.chat_max_tokens'),
    'messages'         => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $question],
    ],
    'tools'            => $tools ?: null,
    'reasoning_effort' => config('ai.chat.effort') ?: null,
]);

file_put_contents(
    __DIR__ . '/payload.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo 'model=' . $payload['model']
    . ' tools=' . count($tools)
    . ' system_chars=' . strlen($system)
    . ' max_tokens=' . $payload['max_tokens']
    . ' effort=' . ($payload['reasoning_effort'] ?? 'null') . PHP_EOL;
echo 'wrote k6/payload.json' . PHP_EOL;
