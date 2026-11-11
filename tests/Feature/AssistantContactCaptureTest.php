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

/**
 * What happens when the assistant runs out of answers: it asks for a name and
 * email, and the reply becomes a real enquiry rather than a dead end.
 *
 * The visitor's details are the one thing the rules must never lose, because
 * without them a question nobody could answer is simply gone.
 */
class AssistantContactCaptureTest extends TestCase
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
            'title'        => 'Canal-facing 2-bed in Business Bay',
            'type'         => 'apartment',
            'status'       => 'for_sale',
            'is_published' => true,
            'address'      => 'Tower 1, Business Bay',
            'suburb'       => 'Business Bay',
            'state'        => 'Dubai',
            'postcode'     => '',
            'price'        => 1750000,
            'bedrooms'     => 2,
            'bathrooms'    => 2,
            'carspaces'    => 1,
            'description'  => 'A quiet apartment over the canal, chiller free.',
        ], $attributes));
    }

    private function ask(string $message, ?Property $property = null): string
    {
        return $this->postJson('/assistant', ['message' => $message, 'property' => $property?->slug])
            ->assertOk()
            ->json('reply');
    }

    private function lastReply(): ChatMessage
    {
        return ChatMessage::query()->where('role', ChatMessage::ASSISTANT)->latest('id')->firstOrFail();
    }

    public function test_a_question_only_the_agent_can_answer_asks_for_a_name_and_email(): void
    {
        $this->fake(FakeAiConnector::unconfigured());

        $reply = $this->ask('Is the price negotiable?', $this->property());

        $this->assertStringContainsString('name and email', $reply);
        $this->assertSame('rule:deferral_price_negotiation', $this->lastReply()->answered_by);
    }

    public function test_details_left_after_that_become_an_enquiry_carrying_the_question(): void
    {
        $this->fake(FakeAiConnector::unconfigured());
        $property = $this->property();

        $this->ask('Is the price negotiable?', $property);
        $reply = $this->ask('Ahmed Khan, ahmed@example.com', $property);

        $enquiry = Enquiry::query()->firstOrFail();

        $this->assertSame('Ahmed Khan', $enquiry->name);
        $this->assertSame('ahmed@example.com', $enquiry->email);
        $this->assertSame(Enquiry::SOURCE_CHAT, $enquiry->source);
        $this->assertSame('property', $enquiry->type);
        $this->assertSame($property->id, $enquiry->property_id);
        $this->assertStringContainsString('price negotiable', (string) $enquiry->message);

        // The chat points at the enquiry, which is what stops it being pruned
        // and puts the transcript behind the enquiry page.
        $this->assertSame($enquiry->id, ChatConversation::query()->firstOrFail()->enquiry_id);
        $this->assertStringContainsString('ahmed@example.com', $reply);
    }

    public function test_the_transcript_keeps_the_assistant_line_as_well_as_the_question(): void
    {
        $this->fake(FakeAiConnector::unconfigured());

        $this->ask('Is the price negotiable?', $this->property());
        $this->ask('Ahmed Khan, ahmed@example.com', $this->property());

        $messages = ChatMessage::query()->orderBy('id')->get();

        // The bug this replaces: the admin transcript showed the visitor's
        // question with no answer under it, because the reply was never saved.
        $this->assertSame(
            [ChatMessage::USER, ChatMessage::ASSISTANT, ChatMessage::USER, ChatMessage::ASSISTANT],
            $messages->pluck('role')->all(),
        );
        $this->assertNotSame('', trim((string) $messages[1]->content));
        $this->assertSame(ChatMessage::BY_LEAD, $messages[3]->answered_by);
    }

    public function test_one_chat_produces_one_lead_however_many_addresses_it_gets(): void
    {
        $this->fake(FakeAiConnector::unconfigured());
        $property = $this->property();

        $this->ask('Is the price final?', $property);
        $this->ask('my name is Ahmed, ahmed@example.com', $property);
        $this->ask('sorry, ahmed.khan@example.com', $property);

        $this->assertSame(1, Enquiry::count());
        $this->assertSame('Ahmed', Enquiry::query()->firstOrFail()->name);
    }

    public function test_an_address_is_captured_even_with_the_model_on(): void
    {
        // The model may be as slow or as broken as it likes: details are read
        // by the same rule either way, so the two paths cannot disagree.
        $connector = $this->fake();

        $this->ask('my name is Sara, sara@example.com');

        $this->assertSame(0, $connector->calls);
        $this->assertSame('sara@example.com', Enquiry::query()->firstOrFail()->email);
    }

    public function test_a_lead_with_no_stated_name_reads_one_off_the_address(): void
    {
        $this->fake(FakeAiConnector::unconfigured());

        $this->ask('Is the price negotiable?', $this->property());
        $this->ask('bilal.raza@example.com', $this->property());

        $this->assertSame('Bilal Raza', Enquiry::query()->firstOrFail()->name);
    }

    public function test_filler_words_are_not_mistaken_for_a_name(): void
    {
        $this->fake(FakeAiConnector::unconfigured());

        $this->ask('please email me at omar@example.com', $this->property());

        $this->assertSame('Omar', Enquiry::query()->firstOrFail()->name);
    }

    public function test_the_lead_is_warm_and_says_why(): void
    {
        $this->fake(FakeAiConnector::unconfigured());

        $this->ask('Is the price negotiable?', $this->property());
        $this->ask('Ahmed Khan, ahmed@example.com', $this->property());

        $enquiry = Enquiry::query()->firstOrFail();

        // Email-only on a form is cold; after a conversation it is not.
        $this->assertSame(EnquiryPriority::WARM, $enquiry->priority);
        $this->assertStringContainsString('assistant chat', $enquiry->priorityReason());
    }

    public function test_an_address_is_a_lead_however_it_is_offered(): void
    {
        // "Email me at ..." is how half of them answer "leave your name and
        // email": refusing it to ask again would be the bot forgetting.
        $connector = $this->fake();

        $this->ask('Email me at marcus@example.com', $this->property());

        $this->assertSame(0, $connector->calls);
        $this->assertSame('Marcus', Enquiry::query()->firstOrFail()->name);
    }

    public function test_a_model_that_defers_still_asks_for_a_name_and_email(): void
    {
        // The model is told to ask, but a model that forgets must not lose the
        // lead: the ask is appended rather than left to its discretion.
        $this->fake(new FakeAiConnector('That is not on the record - our team will confirm it.'));

        $reply = $this->ask('Explain the payment plan for this one', $this->property());

        $this->assertStringContainsString('name and email', $reply);
        $this->assertSame('ai', $this->lastReply()->answered_by);
    }

    public function test_a_model_that_already_asks_is_not_asked_twice(): void
    {
        $this->fake(new FakeAiConnector('Our team will confirm that - what is your name and email?'));

        $reply = $this->ask('Explain when I can move in', $this->property());

        $this->assertSame(1, substr_count(strtolower($reply), 'email'));
    }

    public function test_a_model_that_answers_is_left_alone(): void
    {
        $this->fake(new FakeAiConnector('It is a two-bedroom apartment over the canal.'));

        $reply = $this->ask('How many bedrooms?', $this->property());

        $this->assertStringNotContainsString('name and email', $reply);
    }

    public function test_the_ask_is_dropped_once_the_details_are_in(): void
    {
        $this->fake(FakeAiConnector::unconfigured());
        $property = $this->property();

        $this->ask('Is the price negotiable?', $property);
        $this->ask('Ahmed Khan, ahmed@example.com', $property);

        $reply = $this->ask('Is the price final?', $property);

        // Asking a client for their details again reads as a bot that forgot.
        $this->assertStringNotContainsString('name and email', $reply);
    }
}
