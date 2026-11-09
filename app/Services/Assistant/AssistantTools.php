<?php

namespace App\Services\Assistant;

use App\Jobs\SendEnquiryConfirmation;
use App\Models\ChatConversation;
use App\Models\ChatUnansweredQuestion;
use App\Models\Enquiry;
use App\Models\Property;
use App\Services\Listings\PropertySearch;
use App\Support\Ai\ToolFailed;
use App\Support\PropertyFacts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * What the public assistant is allowed to do, and the code that does it.
 *
 * Four tools, deliberately narrow. Two only read, and only ever see published
 * listings - an unpublished or draft property does not exist as far as the
 * assistant is concerned. Two write, and each can only add a row the agent will
 * review: a lead in the inbox, or a question for the report. Nothing here can
 * change or reveal anything else, whatever a visitor types.
 *
 * Every input is validated here rather than trusted to the schema: the model
 * writes these arguments, and the model is talking to the public.
 */
class AssistantTools
{
    /** Enough to choose from in a chat window; more is a list, not an answer. */
    private const SEARCH_LIMIT = 6;

    /** One chat cannot fill the questions report on its own. */
    public const MAX_QUESTIONS_PER_CHAT = 5;

    /** The tools that only read published listings - safe without a chat. */
    public const READ_ONLY = ['search_properties', 'get_property'];

    /**
     * @param  ChatConversation|null  $conversation  null for read-only use outside a chat
     *                                               (the enquiry auto-reply); the writing tools then refuse
     */
    public function __construct(private readonly ?ChatConversation $conversation = null) {}

    /** Questions filed in this turn, so the reply can be made to offer a way back. */
    private int $recorded = 0;

    public function recordedUnanswered(): bool
    {
        return $this->recorded > 0;
    }

    /** @return list<array{name: string, description: string, inputSchema: array<string, mixed>}> */
    public static function readOnlyDefinitions(): array
    {
        return array_values(array_filter(
            static::definitions(),
            fn(array $tool) => in_array($tool['name'], self::READ_ONLY, true),
        ));
    }

    /** @return list<array{name: string, description: string, inputSchema: array<string, mixed>}> */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'search_properties',
                'description' => 'Search the agency\'s published listings. Use this whenever the visitor describes what they '
                    . 'want (area, budget, size, type) or asks what is available. Prices are whole UAE dirhams: convert '
                    . '"AED 1.5 million" to 1500000 and "AED 850K" to 850000. Returns up to 6 matches and the total count. '
                    . 'Only recommend properties this tool returns.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'area' => ['type' => 'string', 'description' => 'Area or community name, e.g. "Dubai Marina", "Business Bay", "Palm Jumeirah". Partial names match.'],
                        'type' => ['type' => 'string', 'enum' => array_keys(config('agent.property_types')), 'description' => 'Property type.'],
                        'min_bedrooms' => ['type' => 'integer', 'minimum' => 0],
                        'min_price' => ['type' => 'integer', 'minimum' => 0, 'description' => 'UAE dirhams.'],
                        'max_price' => ['type' => 'integer', 'minimum' => 0, 'description' => 'UAE dirhams.'],
                        'keywords' => ['type' => 'string', 'description' => 'Words to match in the title or address, e.g. "corner" or "garage".'],
                        'sold' => ['type' => 'boolean', 'description' => 'True to search recent sales instead of what is for sale - only for questions about past sale prices.'],
                        'rent' => ['type' => 'boolean', 'description' => 'True to search what is available to rent. When true, min_price/max_price are read as annual rent in dirhams.'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_property',
                'description' => 'Get the full recorded facts for one listing by its slug (from search results or the page context). '
                    . 'Use this before answering detailed questions about a specific property.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                    ],
                    'required' => ['slug'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'save_lead',
                'description' => 'Pass the visitor\'s contact details to the agent so they call back. Call this ONLY after the '
                    . 'visitor has typed their name and phone number in this chat and agreed to be contacted. Never fill '
                    . 'in details they did not give. Call it once per chat.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'description' => 'Only if they gave one.'],
                        'summary' => ['type' => 'string', 'description' => 'One or two sentences for the agent: what they want and anything they asked that you could not answer.'],
                        'property_slug' => ['type' => 'string', 'description' => 'The listing they are interested in, if there is one.'],
                    ],
                    'required' => ['name', 'phone', 'summary'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'record_unanswered_question',
                'description' => 'REQUIRED before you tell a visitor the agent will confirm something. Logs each question '
                    . 'the listing facts could not answer (price flexibility, possession, paperwork, dues, anything not '
                    . 'recorded) so the agent can add it to the listing. Call it once per unanswered question - if they '
                    . 'asked two things you cannot answer, call it twice - then write your reply.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string', 'enum' => array_keys(ChatUnansweredQuestion::TOPICS)],
                        'question' => ['type' => 'string', 'description' => 'The question in a few words, in English.'],
                        'property_slug' => ['type' => 'string', 'description' => 'The listing it was about, if any.'],
                    ],
                    'required' => ['topic', 'question'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** @throws ToolFailed */
    public function run(string $name, array $input): string
    {
        if ($this->conversation === null && ! in_array($name, self::READ_ONLY, true)) {
            throw new ToolFailed("{$name} is not available here.");
        }

        return match ($name) {
            'search_properties'          => $this->searchProperties($input),
            'get_property'               => $this->getProperty($input),
            'save_lead'                  => $this->saveLead($input),
            'record_unanswered_question' => $this->recordQuestion($input),
            default                      => throw new ToolFailed("There is no tool called {$name}."),
        };
    }

    private function searchProperties(array $input): string
    {
        $result = app(PropertySearch::class)->search($this->validate($input, PropertySearch::rules()), self::SEARCH_LIMIT);

        return $this->json([
            'total_matches' => $result['total'],
            'showing'       => count($result['properties']),
            'properties'    => array_map(fn($p) => array_diff_key($p, ['price_pkr' => 0]), $result['properties']),
            'note'          => $result['total'] === 0 ? 'Nothing matches. Say so plainly and suggest widening the search or leaving details with the agent.' : null,
        ]);
    }

    private function getProperty(array $input): string
    {
        $property = $this->findProperty($input['slug'] ?? null)
            ?? throw new ToolFailed('No published listing has that slug. Use search_properties to find the right one.');

        return $this->json([
            'slug'  => $property->slug,
            'title' => $property->title,
            'url'   => route('properties.show', $property),
            'facts' => PropertyFacts::lines($property),
        ]);
    }

    private function saveLead(array $input): string
    {
        if ($this->conversation->isLead()) {
            return 'Already saved earlier in this chat - the agent has these details. Do not ask for them again.';
        }

        $data = $this->validate($input, [
            'name'          => ['required', 'string', 'min:2', 'max:120'],
            // At least seven digits, whatever the visitor's formatting.
            'phone'         => ['required', 'string', 'max:40', 'regex:/^(?:\D*\d){7,}\D*$/'],
            'email'         => ['nullable', 'email:rfc', 'max:180'],
            'summary'       => ['required', 'string', 'max:2000'],
            'property_slug' => ['nullable', 'string', 'max:255'],
        ]);

        $property = $this->findProperty($data['property_slug'] ?? null) ?? $this->conversation->property;

        $enquiry = DB::transaction(function () use ($data, $property) {
            $enquiry = Enquiry::create([
                'type'        => $property ? 'property' : 'contact',
                'source'      => Enquiry::SOURCE_CHAT,
                'property_id' => $property?->id,
                'name'        => $data['name'],
                'email'       => $data['email'] ?? null,
                'phone'       => $data['phone'],
                'message'     => $data['summary'],
            ]);

            $this->conversation->update(['enquiry_id' => $enquiry->id]);

            return $enquiry;
        });

        // Only if they gave an email - chat leads often leave just a number.
        SendEnquiryConfirmation::for($enquiry);

        return 'Saved. The agent now has their details and this chat. Tell the visitor that '
            . config('agent.name') . ' will be in touch, without promising a time.';
    }

    private function recordQuestion(array $input): string
    {
        $data = $this->validate($input, [
            'topic'         => ['required', Rule::in(array_keys(ChatUnansweredQuestion::TOPICS))],
            'question'      => ['required', 'string', 'max:500'],
            'property_slug' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->conversation->unansweredQuestions()->count() < self::MAX_QUESTIONS_PER_CHAT) {
            $this->conversation->unansweredQuestions()->create([
                'topic'       => $data['topic'],
                'question'    => $data['question'],
                'property_id' => ($this->findProperty($data['property_slug'] ?? null) ?? $this->conversation->property)?->id,
            ]);

            $this->recorded++;
        }

        return 'Noted for the agent. Tell the visitor the agent will confirm this, and ask for their name and email so there is somewhere to reply.';
    }

    private function findProperty(?string $slug): ?Property
    {
        return filled($slug) ? Property::published()->where('slug', $slug)->first() : null;
    }

    /** @throws ToolFailed */
    private function validate(array $input, array $rules): array
    {
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            throw new ToolFailed('Invalid input: ' . implode(' ', $validator->errors()->all()));
        }

        return $validator->validated();
    }

    private function json(array $data): string
    {
        return json_encode(array_filter($data, fn($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
