<?php

namespace App\Services\Assistant;

use App\Models\ChatConversation;
use App\Services\Assistant\Kit\ConnectorModel;
use App\Services\Assistant\Kit\EloquentChatStore;
use App\Services\Assistant\Kit\EnquiryLeadSink;
use App\Services\Assistant\Kit\PropertyCatalogue;
use App\Support\Ai\AiConnector;
use AssistantKit\Assistant;
use AssistantKit\Pack\RulesPack;

/**
 * Testing branch: the public chat is powered by AssistantKit instead of the
 * in-app rule engine. This class is only the seam - it hands the Kit the four
 * ports it needs and translates its answer back into the site's vocabulary.
 *
 * The Kit decides nothing about the site: listings come through
 * PropertyCatalogue, chat rows through EloquentChatStore, leads through
 * EnquiryLeadSink, and the model through the same AiConnector as before.
 */
class PropertyAssistant
{
  public function __construct(private readonly AiConnector $ai) {}

  /** On when the bubble is enabled; rules-only counts as on. */
  public function isAvailable(): bool
  {
    return (bool) config('ai.chat.enabled');
  }

  /** Whether the model, not just the free rules, can answer. */
  public function modelAvailable(): bool
  {
    return (bool) config('ai.chat.model_enabled') && $this->ai->isConfigured();
  }

  /**
   * One turn through the Kit. It records the visitor message, answers it and
   * saves the reply; the store adapter mirrors both onto chat_messages, so the
   * transcript, the admin report and the lead link are unchanged.
   */
  public function reply(ChatConversation $conversation, string $message): string
  {
    $assistant = new Assistant(
      new PropertyCatalogue(),
      new EloquentChatStore($conversation),
      new EnquiryLeadSink($conversation),
      RulesPack::load(resource_path('assistantkit/packs/property.php')),
      $this->config(),
      new ConnectorModel($this->ai),
    );

    return $assistant->reply((string) $conversation->id, $message)->text;
  }

  /** Shown when the assistant cannot help - the visitor always has a way through. */
  public function fallback(): string
  {
    return sprintf(
      "Sorry, I can't answer right now. Please call or WhatsApp %s on %s - or leave your name and email here and they will come back to you.",
      config('agent.name'),
      config('agent.phone'),
    );
  }

  /**
   * The Kit's config with the site's words in it. Brand, contacts and process
   * steps come from config/agent.php, so there is still one place to edit them.
   *
   * @return array<string, mixed>
   */
  private function config(): array
  {
    $config = require config_path('assistantkit.php');

    $config['brand'] = [
      'name'     => (string) config('agent.agency'),
      'agent'    => (string) config('agent.name'),
      'email'    => (string) config('agent.email'),
      'phone'    => (string) config('agent.phone'),
      'whatsapp' => config('agent.whatsapp'),
      'hours'    => $this->line(config('agent.hours')),
      'office'   => $this->line(config('agent.office')),
      'areas'    => array_values((array) config('agent.service_areas', [])),
    ];

    $config['process'] = $this->process();

    // The bubble lists at most this many listings in one answer.
    $config['limits']['results'] = 6;

    // The per-chat cap stays where the admin sets it.
    $config['limits']['max_messages'] = (int) config('ai.chat.max_messages', 20);

    return $config;
  }

  /**
   * config/agent.php stores process steps as label + steps + note; the Kit
   * wants title + steps + note, and the note carries a :agent placeholder.
   *
   * @return array<string, array{title: string, steps: list<string>, note: string}>
   */
  private function process(): array
  {
    $process = [];

    foreach ((array) config('agent.process', []) as $topic => $steps) {
      $process[(string) $topic] = [
        'title' => (string) ($steps['label'] ?? ucfirst((string) $topic)),
        'steps' => array_values((array) ($steps['steps'] ?? [])),
        'note'  => str_replace(':agent', (string) config('agent.name'), (string) ($steps['note'] ?? '')),
      ];
    }

    return $process;
  }

  /** Some brand values are a line, some are a set of labelled lines. */
  private function line(mixed $value): string
  {
    if (is_array($value)) {
      return implode(', ', array_filter($value, 'is_string'));
    }

    return (string) $value;
  }
}
