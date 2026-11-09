<?php

namespace AssistantKit\Tools;

use AssistantKit\Contracts\Catalogue;
use AssistantKit\Contracts\ConversationStore;
use AssistantKit\Contracts\LeadSink;
use AssistantKit\Pack\RulesPack;
use AssistantKit\Value\Conversation;

/**
 * The model's entire menu. It cannot reach the database, the config or the
 * filesystem - only these four calls, each validated here before it runs.
 *
 * `save_lead` and `record_unanswered_question` are the two write tools; pass
 * definitions(readOnly: true) to a non-chat consumer that must not write.
 */
final class ToolBox
{
    private int $recorded = 0;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly LeadSink $leads,
        private readonly ConversationStore $store,
        private readonly RulesPack $pack,
        private readonly array $config,
    ) {}

    /** @return list<array<string, mixed>> */
    public function definitions(bool $readOnly = false): array
    {
        $tools = [
            [
                'name'        => 'search_items',
                'description' => 'Find ' . $this->pack->noun('many') . ' that match what the visitor described. Returns the total and up to a handful of matches.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'area'      => ['type' => 'string', 'description' => 'Area or neighbourhood, written as the visitor said it.'],
                        'beds'      => ['type' => 'integer', 'description' => 'Number of bedrooms. 0 means studio.'],
                        'price_min' => ['type' => 'integer', 'description' => 'Lowest price in the catalogue currency.'],
                        'price_max' => ['type' => 'integer', 'description' => 'Highest price in the catalogue currency.'],
                        'deal'      => ['type' => 'string', 'enum' => ['rent', 'sale'], 'description' => 'Renting or buying.'],
                        'type'      => ['type' => 'string', 'description' => 'Item type, for example Apartment or Villa.'],
                    ],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'get_item',
                'description' => 'Read one item in full by its slug.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'The slug from search_items.'],
                    ],
                    'required'   => ['slug'],
                ],
            ],
        ];

        if ($readOnly) {
            return $tools;
        }

        $tools[] = [
            'name'        => 'save_lead',
            'description' => 'Save the visitor\'s contact details. Call it once the visitor has given a name and an email, or a phone number.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'name'    => ['type' => 'string'],
                    'email'   => ['type' => 'string', 'description' => 'Required unless a phone number was given.'],
                    'phone'   => ['type' => 'string'],
                    'message' => ['type' => 'string', 'description' => 'What they are asking for, in their own words.'],
                ],
                'required'   => [],
            ],
        ];

        $tools[] = [
            'name'        => 'record_unanswered_question',
            'description' => 'File a question you could not answer, so the team can add it. Always say so to the visitor and ask for their name and email in the same reply.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'question' => ['type' => 'string', 'description' => 'The visitor\'s question, close to their own wording.'],
                ],
                'required'   => ['question'],
            ],
        ];

        return $tools;
    }

    /** @param array<string, mixed> $input */
    public function run(string $name, array $input, Conversation $conversation): mixed
    {
        return match ($name) {
            'search_items'               => $this->search($input),
            'get_item'                   => $this->get($input),
            'save_lead'                  => $this->saveLead($input, $conversation),
            'record_unanswered_question' => $this->record($input, $conversation),
            default                      => throw new ToolError('Unknown tool: ' . $name),
        };
    }

    /** True when this turn filed a question, so the reply can be made to offer a way back. */
    public function recordedUnanswered(): bool
    {
        return $this->recorded > 0;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function search(array $input): array
    {
        $criteria = [];

        foreach (['area', 'type', 'deal'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && $input[$key] !== '') {
                $criteria[$key] = $input[$key];
            }
        }

        foreach (['beds', 'price_min', 'price_max'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                $criteria[$key] = (int) $input[$key];
            }
        }

        $result = $this->catalogue->search($criteria, (int) ($this->config['limits']['results'] ?? 6));

        return [
            'total' => (int) ($result['total'] ?? 0),
            'items' => array_map(fn(array $item): array => [
                'slug'    => (string) ($item['slug'] ?? ''),
                'title'   => (string) ($item['title'] ?? ''),
                'summary' => (string) ($item['summary'] ?? $item['price_label'] ?? ''),
                'url'     => (string) ($item['url'] ?? ''),
            ], (array) ($result['items'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function get(array $input): array
    {
        $slug = trim((string) ($input['slug'] ?? ''));

        if ($slug === '') {
            throw new ToolError('A slug is required.');
        }

        $item = $this->catalogue->find($slug);

        if ($item === null) {
            throw new ToolError('No item found with the slug ' . $slug . '.');
        }

        return [
            'slug'  => $slug,
            'title' => (string) ($item['title'] ?? ''),
            'url'   => (string) ($item['url'] ?? ''),
            'facts' => $this->catalogue->facts($item),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function saveLead(array $input, Conversation $conversation): array
    {
        if ($conversation->isLead()) {
            return ['saved' => false, 'note' => 'This visitor is already a lead; do not ask again.'];
        }

        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ToolError('That email address does not look valid; ask the visitor to check it.');
        }

        if ($email === '' && $phone === '') {
            throw new ToolError('An email or a phone number is required.');
        }

        $conversation->leadRef = $this->leads->capture([
            'name'    => trim((string) ($input['name'] ?? '')) ?: null,
            'email'   => $email,
            'phone'   => $phone !== '' ? $phone : null,
            'message' => trim((string) ($input['message'] ?? '')) ?: null,
            'item'    => $conversation->itemSlug,
            'source'  => 'chat',
            'chat'    => $conversation->id,
        ]);

        return ['saved' => true, 'lead_ref' => $conversation->leadRef];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function record(array $input, Conversation $conversation): array
    {
        $question = trim((string) ($input['question'] ?? ''));

        if ($question === '') {
            throw new ToolError('A question is required.');
        }

        $asked = $this->store->questions($conversation);
        $limit = (int) ($this->config['limits']['questions_per_chat'] ?? 5);

        if (count($asked) >= $limit) {
            return ['recorded' => false, 'note' => 'The question log for this chat is full; stop filing and ask for an email instead.'];
        }

        foreach ($asked as $entry) {
            if (mb_strtolower(trim((string) $entry['question'])) === mb_strtolower($question)) {
                return ['recorded' => false, 'note' => 'That question is already filed.'];
            }
        }

        $this->store->recordQuestion($conversation, $question, $conversation->itemSlug);
        $this->recorded++;

        return ['recorded' => true];
    }
}
