<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Enquiry;
use App\Models\Property;
use App\Support\Ai\AiConnector;
use App\Support\EnquiryPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private function fake(FakeAiConnector $connector = new FakeAiConnector('Happy to help.')): FakeAiConnector
    {
        $this->app->instance(AiConnector::class, $connector);

        return $connector;
    }

    private function property(array $attributes = []): Property
    {
        return Property::create(array_merge([
            'title' => '4-Bed Home in Al Barsha',
            'type' => 'house',
            'status' => 'for_sale',
            'is_published' => true,
            'address' => 'Villa 1408, Al Barsha 2',
            'suburb' => 'Al Barsha',
            'state' => 'Dubai',
            'postcode' => '',
            'price' => 625000,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'carspaces' => 2,
            'land_size' => 7200,
            'description' => 'A well-kept corner home on a quiet street, park facing.',
        ], $attributes));
    }

    private function ask(string $message, ?Property $property = null)
    {
        return $this->postJson('/assistant', ['message' => $message, 'property' => $property?->slug]);
    }

    /*
    |--------------------------------------------------------------------------
    | The bubble
    |--------------------------------------------------------------------------
    */

    public function test_the_bubble_appears_without_an_api_key_and_still_answers(): void
    {
        // No key: the bubble is present in rules-only mode, and the endpoint
        // serves it rather than 404ing.
        $this->fake(FakeAiConnector::unconfigured());

        $this->get('/')->assertOk()->assertSee('PropertyAssistant');
        $this->ask('Hello')->assertOk();
    }

    public function test_the_bubble_can_be_switched_off_while_keeping_the_key(): void
    {
        $this->fake();
        config(['ai.chat.enabled' => false]);

        $this->get('/')->assertOk()->assertDontSee('PropertyAssistant');
        $this->ask('Hello')->assertNotFound();
    }

    public function test_the_bubble_appears_site_wide_when_configured(): void
    {
        $this->fake();

        $this->get('/')->assertOk()->assertSee('PropertyAssistant');
    }

    public function test_on_a_listing_page_the_bubble_is_scoped_to_that_listing(): void
    {
        $this->fake();
        $property = $this->property();

        $this->get("/properties/{$property->slug}")
            ->assertOk()
            ->assertSee('&quot;property&quot;:&quot;' . $property->slug . '&quot;', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Conversation
    |--------------------------------------------------------------------------
    */

    public function test_it_replies_and_keeps_both_sides_of_the_chat(): void
    {
        $this->fake(new FakeAiConnector('It has 4 bedrooms.'));

        $this->ask('How many bedrooms?')
            ->assertOk()
            ->assertJson(['reply' => 'It has 4 bedrooms.', 'lead' => false]);

        $conversation = ChatConversation::sole();
        $this->assertSame(1, $conversation->visitor_messages);
        $this->assertSame(
            [['user', 'How many bedrooms?'], ['assistant', 'It has 4 bedrooms.']],
            $conversation->messages->map(fn($m) => [$m->role, $m->content])->all(),
        );
    }

    public function test_the_next_message_continues_the_same_chat_with_its_history(): void
    {
        $fake = $this->fake(new FakeAiConnector('Noted.'));

        // Both turns need the model: a stated filter would be answered from the
        // records and never reach here.
        $this->ask('Is this a good area for families?')->assertOk();
        $this->ask('Why is the price so high?')->assertOk();

        $this->assertSame(1, ChatConversation::count());
        $this->assertSame([
            ['role' => 'user', 'content' => 'Is this a good area for families?'],
            ['role' => 'assistant', 'content' => 'Noted.'],
            ['role' => 'user', 'content' => 'Why is the price so high?'],
        ], $fake->messages);
    }

    public function test_only_the_newest_history_is_resent_and_it_starts_with_the_visitor(): void
    {
        $fake = $this->fake(new FakeAiConnector('Ok.'));

        config(['ai.chat.history' => 3]);
        $this->ask('First')->assertOk();
        $this->ask('Second')->assertOk();
        $this->ask('Third')->assertOk();
        $this->assertSame(['Second', 'Ok.', 'Third'], array_column($fake->messages, 'content'));

        // A window that would open on a reply drops it - the API wants the user first.
        config(['ai.chat.history' => 2]);
        $this->ask('Fourth')->assertOk();
        $this->assertSame([['role' => 'user', 'content' => 'Fourth']], $fake->messages);
    }

    public function test_each_listing_gets_its_own_chat(): void
    {
        $this->fake();
        $a = $this->property();
        $b = $this->property(['title' => '5-Bed Home in Steiner Ranch', 'suburb' => 'Steiner Ranch']);

        $this->ask('Is it available?', $a)->assertOk();
        $this->ask('Is it available?', $b)->assertOk();
        $this->ask('Any plots?')->assertOk();

        $this->assertSame(3, ChatConversation::count());
        $this->assertEqualsCanonicalizing([$a->id, $b->id, null], ChatConversation::pluck('property_id')->all());
    }

    public function test_a_reload_restores_the_chat_for_that_visitor_only(): void
    {
        $this->fake(new FakeAiConnector('It is a quiet street.'));
        $property = $this->property();

        $this->ask('Tell me about the street', $property)->assertOk();

        $this->getJson("/assistant?property={$property->slug}")
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'Tell me about the street')
            ->assertJsonPath('messages.1.content', 'It is a quiet street.');

        // A different visitor (fresh session) sees nothing.
        $this->flushSession();
        $this->getJson("/assistant?property={$property->slug}")->assertOk()->assertJsonCount(0, 'messages');
    }

    public function test_messages_are_validated(): void
    {
        $this->fake();

        $this->ask('')->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->ask(str_repeat('a', 1001))->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertSame(0, ChatConversation::count());
    }

    /*
    |--------------------------------------------------------------------------
    | Grounding
    |--------------------------------------------------------------------------
    */

    public function test_on_a_listing_the_model_is_given_that_listings_facts(): void
    {
        $fake = $this->fake();
        $property = $this->property();

        $this->ask('Is it good for a family with kids?', $property)->assertOk();

        $this->assertStringContainsString('opened this chat on the listing page', $fake->system);
        $this->assertStringContainsString('4-Bed Home in Al Barsha', $fake->system);
        $this->assertStringContainsString($property->slug, $fake->system);
        $this->assertStringContainsString($property->priceDisplay(), $fake->system);
        $this->assertStringContainsString('7,200 sq ft', $fake->system);
    }

    public function test_the_model_is_told_not_to_guess_and_to_reply_in_english(): void
    {
        $fake = $this->fake();

        $this->ask('Tell me about buying in Dubai')->assertOk();

        $this->assertStringContainsString('do NOT guess', $fake->system);
        $this->assertStringContainsString('Never invent', $fake->system);
        $this->assertStringContainsString('Always reply in English', $fake->system);
        $this->assertStringContainsString('general page of the website', $fake->system);
    }

    public function test_an_unpublished_listing_never_reaches_the_model(): void
    {
        $fake = $this->fake();
        $hidden = $this->property(['title' => 'Secret Off-Market Villa', 'is_published' => false]);

        $this->ask('Tell me about it', $hidden)->assertOk();

        $this->assertStringNotContainsString('Secret Off-Market Villa', $fake->system);
        $this->assertNull(ChatConversation::sole()->property_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Tools
    |--------------------------------------------------------------------------
    */

    public function test_the_model_is_offered_exactly_the_three_tools(): void
    {
        $fake = $this->fake();

        $this->ask('Tell me about buying in Dubai')->assertOk();

        $this->assertSame(
            ['search_properties', 'get_property', 'save_lead'],
            array_column($fake->tools, 'name'),
        );
    }

    public function test_search_finds_published_listings_by_partial_area_and_budget(): void
    {
        $match = $this->property();
        $this->property(['title' => 'Too Expensive', 'price' => 900000]);
        $this->property(['title' => 'Wrong Area', 'suburb' => 'Downtown Dubai']);
        $this->property(['title' => 'Unpublished', 'is_published' => false]);
        $this->property(['title' => 'Already Sold', 'status' => 'sold']);

        $fake = $this->fake((new FakeAiConnector())->callsTool('search_properties', ['area' => 'Al Barsha', 'max_price' => 700000]));

        // Phrased as a comparison on purpose: a plain "villas in Al Barsha under
        // 700K" is answered by the rules before the model, so this is how the
        // tool's own filtering gets driven through the real pipeline.
        $this->ask('Compare villas in Al Barsha under AED 700K')->assertOk();

        $result = json_decode($fake->resultOf('search_properties'), true);
        $this->assertSame(1, $result['total_matches']);
        $this->assertSame($match->slug, $result['properties'][0]['slug']);
        $this->assertSame(route('properties.show', $match), $result['properties'][0]['url']);
        $this->assertSame('AED 625,000', $result['properties'][0]['price']);
    }

    public function test_a_plain_search_is_answered_from_the_records_without_the_model(): void
    {
        $match = $this->property();
        $this->property(['title' => 'Too Expensive', 'price' => 900000]);
        $this->property(['title' => 'Wrong Area', 'suburb' => 'Downtown Dubai']);

        $fake = $this->fake();

        $reply = $this->ask('Villas in Al Barsha under AED 700K')->assertOk()->json('reply');

        // The filter is stated, so the database answers it: free, instant and
        // unable to invent a listing.
        $this->assertSame('rule:search', ChatMessage::where('role', 'assistant')->latest('id')->value('answered_by'));
        $this->assertSame(0, $fake->calls);
        $this->assertStringContainsString($match->title, $reply);
        $this->assertStringNotContainsString('Too Expensive', $reply);
    }

    public function test_a_search_with_nothing_to_filter_on_still_reaches_the_model(): void
    {
        $this->property();

        $fake = $this->fake();

        $this->ask('Show me something nice')->assertOk();

        // No area, no budget, no type: filtering on nothing would list the whole
        // catalogue at the visitor, so this stays the model's to interpret.
        $this->assertSame('ai', ChatMessage::where('role', 'assistant')->latest('id')->value('answered_by'));
        $this->assertSame(1, $fake->calls);
    }

    public function test_a_plural_type_word_still_filters_by_that_type(): void
    {
        $this->property(['type' => 'townhouse', 'title' => 'Townhouse with a courtyard']);
        $this->property(['type' => 'house', 'title' => 'Villa next door']);

        $fake = $this->fake();

        $reply = $this->ask('Townhouses in Al Barsha for sale')->assertOk()->json('reply');

        // "townhouses" is the word visitors use, and the plural has to read as
        // the same type or the villa gets listed at them.
        $this->assertStringContainsString('Townhouse with a courtyard', $reply);
        $this->assertStringNotContainsString('Villa next door', $reply);
        $this->assertSame('rule:search', ChatMessage::where('role', 'assistant')->latest('id')->value('answered_by'));
        $this->assertSame(0, $fake->calls);
    }

    public function test_search_says_so_when_nothing_matches(): void
    {
        $fake = $this->fake((new FakeAiConnector())->callsTool('search_properties', ['area' => 'Nowhere']));

        $this->ask('Anything in Nowhere?')->assertOk();

        $result = json_decode($fake->resultOf('search_properties'), true);
        $this->assertSame(0, $result['total_matches']);
        $this->assertArrayHasKey('note', $result);
    }

    public function test_get_property_returns_facts_and_refuses_unpublished_listings(): void
    {
        $property = $this->property();
        $hidden = $this->property(['title' => 'Hidden', 'is_published' => false]);

        $fake = $this->fake((new FakeAiConnector())
            ->callsTool('get_property', ['slug' => $property->slug])
            ->callsTool('get_property', ['slug' => $hidden->slug]));
        $this->ask('Tell me more')->assertOk();

        [$found, $refused] = $fake->toolRuns;
        $this->assertFalse($found['failed']);
        $this->assertStringContainsString('Villa 1408, Al Barsha 2', $found['result']);
        $this->assertTrue($refused['failed']);
    }

    public function test_save_lead_puts_a_phone_only_lead_in_the_inbox(): void
    {
        $property = $this->property();
        $fake = $this->fake((new FakeAiConnector('Done - Bilal will call you.'))->callsTool('save_lead', [
            'name' => 'Marcus Bell',
            'phone' => '512 555 0198',
            'summary' => 'Wants a showing this weekend. Asked if the price is negotiable.',
        ]));

        $this->ask('Marcus Bell, 512 555 0198, please call me', $property)
            ->assertOk()
            ->assertJson(['lead' => true]);

        $enquiry = Enquiry::sole();
        $this->assertSame('Marcus Bell', $enquiry->name);
        $this->assertNull($enquiry->email);
        $this->assertSame(Enquiry::SOURCE_CHAT, $enquiry->source);
        $this->assertSame('property', $enquiry->type);
        $this->assertSame($property->id, $enquiry->property_id);
        $this->assertSame(EnquiryPriority::WARM, $enquiry->priority);
        $this->assertSame(Enquiry::STATUS_NEW, $enquiry->status);
        $this->assertSame($enquiry->id, ChatConversation::sole()->enquiry_id);
        $this->assertFalse($fake->toolRuns[0]['failed']);
    }

    public function test_save_lead_happens_once_per_chat(): void
    {
        $lead = ['name' => 'Marcus Bell', 'phone' => '5125550198', 'summary' => 'Wants a call.'];
        $fake = $this->fake((new FakeAiConnector())->callsTool('save_lead', $lead)->callsTool('save_lead', $lead));

        $this->ask('Call me')->assertOk();

        $this->assertSame(1, Enquiry::count());
        $this->assertStringContainsString('Already saved', $fake->toolRuns[1]['result']);
    }

    public function test_save_lead_rejects_made_up_or_broken_details(): void
    {
        $fake = $this->fake((new FakeAiConnector())->callsTool('save_lead', [
            'name' => 'Marcus',
            'phone' => 'call me',
            'summary' => 'Wants a call.',
        ]));

        $this->ask('Call me')->assertOk();

        $this->assertSame(0, Enquiry::count());
        $this->assertTrue($fake->toolRuns[0]['failed']);
        $this->assertStringContainsString('phone', $fake->toolRuns[0]['result']);
    }

    public function test_unanswered_questions_are_recorded_against_the_listing(): void
    {
        $property = $this->property();
        $this->fake(new FakeAiConnector('When you can close is not on the record - our team will confirm it.'));

        $this->ask('Tell me what I should check before buying', $property)->assertOk();

        $question = ChatConversation::sole()->unansweredQuestions()->sole();
        $this->assertSame('possession', $question->topic);
        $this->assertSame($property->id, $question->property_id);
    }

    public function test_an_unknown_tool_is_reported_back_to_the_model(): void
    {
        $fake = $this->fake((new FakeAiConnector())->callsTool('delete_everything'));

        $this->ask('Tell me about buying in Dubai')->assertOk();

        $this->assertTrue($fake->toolRuns[0]['failed']);
    }

    /*
    |--------------------------------------------------------------------------
    | Failure and cost limits
    |--------------------------------------------------------------------------
    */

    public function test_an_outage_answers_from_the_rules_instead_of_apologising(): void
    {
        $connector = $this->fake(FakeAiConnector::failing());
        $this->property();

        $this->ask('Is it available?')
            ->assertOk()
            ->assertJsonPath('reply', fn($reply) => ! str_contains($reply, "Sorry, I can't answer"));

        // The model was tried and failed; the rules answered in its place, and
        // both turns were saved so the transcript stays whole.
        $this->assertSame(1, $connector->calls);
        $this->assertSame('rule:search', ChatMessage::where('role', ChatMessage::ASSISTANT)->value('answered_by'));
        $this->assertSame(2, ChatMessage::count());
    }

    public function test_a_long_chat_is_handed_to_the_agent_without_calling_the_model(): void
    {
        $fake = $this->fake();
        config(['ai.chat.max_messages' => 2]);

        $this->ask('One')->assertOk();
        $reply = $this->ask('Two')->assertOk()->json('reply');

        $this->assertSame(1, $fake->calls);
        $this->assertStringContainsString(config('agent.phone'), $reply);
    }

    public function test_visitors_are_rate_limited(): void
    {
        $this->fake();

        for ($i = 0; $i < 8; $i++) {
            $this->ask("Message {$i}")->assertOk();
        }

        $this->ask('One too many')
            ->assertStatus(429)
            ->assertJsonPath('message', fn($m) => str_contains($m, config('agent.phone')));
    }

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */

    public function test_old_anonymous_chats_are_pruned_but_leads_are_kept(): void
    {
        $old = ChatConversation::create();
        $lead = ChatConversation::create([
            'enquiry_id' => Enquiry::create(['type' => 'contact', 'name' => 'A', 'phone' => '512 555 0198'])->id,
        ]);
        $recent = ChatConversation::create();
        ChatConversation::whereKey([$old->id, $lead->id])->update(['updated_at' => now()->subDays(91)]);

        $this->artisan('model:prune', ['--model' => [ChatConversation::class]])->assertSuccessful();

        $this->assertEqualsCanonicalizing([$lead->id, $recent->id], ChatConversation::pluck('id')->all());
    }
}
