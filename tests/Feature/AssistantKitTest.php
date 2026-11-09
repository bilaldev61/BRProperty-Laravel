<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatUnansweredQuestion;
use App\Models\Enquiry;
use App\Models\Property;
use App\Support\Ai\AiConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

/**
 * The chat as AssistantKit answers it. These are the behaviours the site cannot
 * lose, written against the Kit's vocabulary (`rule:fact_*`, `rule:deferral_*`)
 * rather than the rule classes it replaced.
 */
class AssistantKitTest extends TestCase
{
    use RefreshDatabase;

    private FakeAiConnector $ai;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.chat.enabled' => true, 'ai.chat.model_enabled' => true]);
        $this->ai = FakeAiConnector::unconfigured();
        $this->app->instance(AiConnector::class, $this->ai);
    }

    private function property(array $attributes = []): Property
    {
        return Property::create(array_merge([
            'title'         => 'Marina two bedroom with a sea view',
            'type'          => 'apartment',
            'status'        => 'for_sale',
            'is_published'  => true,
            'address'       => 'Marina Tower 1, Dubai Marina',
            'suburb'        => 'Dubai Marina',
            'state'         => 'Dubai',
            'postcode'      => '',
            'price'         => 1750000,
            'bedrooms'      => 2,
            'bathrooms'     => 3,
            'carspaces'     => 1,
            'floor_size'    => 1250,
            'features'      => ['Sea view'],
            'description'   => 'A two bedroom apartment over the marina.',
            'furnishing'    => 'furnished',
            'completion'    => 'ready',
            'ownership'     => 'freehold',
            'permit_number' => '7146119490',
        ], $attributes));
    }

    private function ask(string $message, ?Property $property = null): string
    {
        return $this->postJson('/assistant', [
            'message'  => $message,
            'property' => $property?->slug,
        ])->assertOk()->json('reply');
    }

    private function lastReply(): ChatMessage
    {
        return ChatMessage::query()->where('role', 'assistant')->latest('id')->firstOrFail();
    }

    public function test_a_listing_fact_is_answered_from_the_record_without_the_model(): void
    {
        $reply = $this->ask('How much is it?', $this->property());

        $this->assertStringContainsString('1,750,000', $reply);
        $this->assertSame('rule:fact_price', $this->lastReply()->answered_by);
        $this->assertSame(0, $this->ai->calls);
    }

    public function test_negotiation_is_deferred_and_asks_for_a_way_back(): void
    {
        $reply = $this->ask('Is the price negotiable?', $this->property());

        $this->assertStringContainsString('name and email', $reply);
        $this->assertStringNotContainsString('1,750,000', $reply);
        $this->assertSame('rule:deferral_negotiable', $this->lastReply()->answered_by);
    }

    public function test_an_email_turns_the_chat_into_an_enquiry_carrying_the_question(): void
    {
        $property = $this->property();

        $this->ask('Is the price negotiable?', $property);
        $this->ask('Ahmed Khan, ahmed@example.com', $property);

        $enquiry = Enquiry::query()->firstOrFail();

        $this->assertSame('Ahmed Khan', $enquiry->name);
        $this->assertSame('ahmed@example.com', $enquiry->email);
        $this->assertSame(Enquiry::SOURCE_CHAT, $enquiry->source);
        $this->assertSame($property->id, $enquiry->property_id);
        $this->assertStringContainsString('price negotiable', (string) $enquiry->message);
        $this->assertSame($enquiry->id, ChatConversation::query()->firstOrFail()->enquiry_id);
        $this->assertSame('lead', $this->lastReply()->answered_by);
    }

    public function test_a_search_lists_matching_listings_in_the_transcript(): void
    {
        $property = $this->property();

        $reply = $this->ask('Any apartments in Dubai Marina under 2 million?');

        $this->assertStringContainsString($property->title, $reply);
        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertSame(0, $this->ai->calls);
    }

    public function test_the_process_is_answered_from_the_agent_config(): void
    {
        $reply = $this->ask('How does renting work?');

        // The steps are the ones written in config/agent.php, numbered.
        $this->assertStringContainsString('Ejari', $reply);
        $this->assertSame('rule:process', $this->lastReply()->answered_by);
    }

    public function test_an_unanswered_question_goes_to_the_model_then_to_the_team(): void
    {
        $this->app->instance(AiConnector::class, new FakeAiConnector('It depends on the building.'));

        $reply = $this->ask('What is the neighbourhood like after dark?');

        $this->assertSame('It depends on the building.', $reply);
        $this->assertSame(ChatMessage::BY_AI, $this->lastReply()->answered_by);

        // Nothing left to answer: the Kit files it and hands over the phone.
        $this->app->instance(AiConnector::class, FakeAiConnector::unconfigured());

        $this->flushSession();
        $reply = $this->ask('Can you hold it for my cousin?');

        $this->assertSame(ChatMessage::BY_FALLBACK, $this->lastReply()->answered_by);
        // The Kit's own way through is the email request, not the phone number.
        $this->assertStringContainsString('name and email', $reply);
        $this->assertSame(1, ChatUnansweredQuestion::query()->count());
    }
}
