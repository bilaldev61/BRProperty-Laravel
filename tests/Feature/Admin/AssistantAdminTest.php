<?php

namespace Tests\Feature\Admin;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Support\Ai\AiConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

class AssistantAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create();
    }

    private function property(): Property
    {
        return Property::create([
            'title' => '4-Bed Home in Al Barsha',
            'type' => 'house',
            'status' => 'for_sale',
            'is_published' => true,
            'address' => 'Villa 1408, Al Barsha 2',
            'suburb' => 'Al Barsha',
            'state' => 'Dubai',
            'postcode' => '',
            'price' => 625000,
            'description' => 'A corner home.',
        ]);
    }

    /** A chat that became a phone-only lead, the way the assistant leaves one. */
    private function chatLead(?Property $property = null): Enquiry
    {
        $enquiry = Enquiry::create([
            'type' => $property ? 'property' : 'contact',
            'source' => Enquiry::SOURCE_CHAT,
            'property_id' => $property?->id,
            'name' => 'Marcus Bell',
            'phone' => '512 555 0198',
            'message' => 'Wants a showing. Asked about closing.',
        ]);

        $conversation = ChatConversation::create([
            'property_id' => $property?->id,
            'enquiry_id' => $enquiry->id,
            'visitor_messages' => 1,
            'last_message_at' => now(),
        ]);
        $conversation->messages()->create(['role' => ChatMessage::USER, 'content' => 'When can we close?']);
        $conversation->messages()->create(['role' => ChatMessage::ASSISTANT, 'content' => 'Bilal will confirm the closing date.']);

        return $enquiry;
    }

    public function test_guests_cannot_read_chats(): void
    {
        $conversation = ChatConversation::create(['visitor_messages' => 1]);

        $this->get('/admin/assistant')->assertRedirect('/admin/login');
        $this->get("/admin/assistant/{$conversation->id}")->assertRedirect('/admin/login');
        $this->get('/admin/assistant/questions')->assertRedirect('/admin/login');
    }

    public function test_the_chat_list_shows_chats_and_can_filter_to_leads(): void
    {
        $this->chatLead();
        ChatConversation::create(['visitor_messages' => 2, 'last_message_at' => now()]);
        ChatConversation::create(['visitor_messages' => 0]); // opened, never used

        $this->actingAs($this->agent)->get('/admin/assistant')
            ->assertOk()
            ->assertSee('Marcus Bell')
            ->assertSee('Anonymous visitor')
            ->assertSee('50%'); // one of two real chats became a lead

        $this->actingAs($this->agent)->get('/admin/assistant?leads=1')
            ->assertOk()
            ->assertSee('Marcus Bell')
            ->assertDontSee('Anonymous visitor');
    }

    public function test_a_chat_shows_its_transcript_and_the_lead_it_became(): void
    {
        $enquiry = $this->chatLead();

        $this->actingAs($this->agent)->get("/admin/assistant/{$enquiry->conversation->id}")
            ->assertOk()
            ->assertSee('When can we close?')
            ->assertSee('Bilal will confirm the closing date.')
            ->assertSee(route('admin.enquiries.show', $enquiry));
    }

    public function test_a_chat_lead_in_the_inbox_shows_where_it_came_from_and_the_chat(): void
    {
        $enquiry = $this->chatLead($this->property());

        $this->actingAs($this->agent)->get('/admin/enquiries')
            ->assertOk()
            ->assertSee('Via assistant');

        // No email was given: the page must still work, with call and WhatsApp.
        $this->actingAs($this->agent)->get("/admin/enquiries/{$enquiry->id}")
            ->assertOk()
            ->assertSee('When can we close?')
            ->assertSee('Website assistant')
            ->assertSee('Call 512 555 0198')
            ->assertDontSee('mailto:', false);
    }

    public function test_the_reply_drafter_reads_the_chat_behind_a_lead(): void
    {
        $fake = new FakeAiConnector();
        $this->app->instance(AiConnector::class, $fake);
        $enquiry = $this->chatLead($this->property());

        $this->actingAs($this->agent)->postJson("/admin/enquiries/{$enquiry->id}/draft")->assertOk();

        $this->assertStringContainsString('THEIR CHAT WITH MY WEBSITE ASSISTANT', $fake->prompt);
        $this->assertStringContainsString('Visitor: When can we close?', $fake->prompt);
        $this->assertStringContainsString('Assistant: Bilal will confirm the closing date.', $fake->prompt);
    }

    public function test_a_form_enquiry_draft_has_no_chat_section(): void
    {
        $fake = new FakeAiConnector();
        $this->app->instance(AiConnector::class, $fake);
        $enquiry = Enquiry::create(['type' => 'contact', 'name' => 'Pat', 'email' => 'pat@example.com', 'message' => 'Hi']);

        $this->actingAs($this->agent)->postJson("/admin/enquiries/{$enquiry->id}/draft")->assertOk();

        $this->assertStringNotContainsString('WEBSITE ASSISTANT', $fake->prompt);
    }

    public function test_the_report_groups_unanswered_questions_by_topic_and_listing(): void
    {
        $property = $this->property();
        $conversation = ChatConversation::create(['property_id' => $property->id, 'visitor_messages' => 3]);
        $conversation->unansweredQuestions()->createMany([
            ['topic' => 'possession', 'question' => 'When can we move in?', 'property_id' => $property->id],
            ['topic' => 'possession', 'question' => 'Closing date?', 'property_id' => $property->id],
            ['topic' => 'charges', 'question' => 'What are the service charges?', 'property_id' => $property->id],
        ]);
        // Outside the report window.
        $conversation->unansweredQuestions()->create(['topic' => 'paperwork', 'question' => 'Ancient question'])
            ->forceFill(['created_at' => now()->subDays(120)])->save();

        $this->actingAs($this->agent)->get('/admin/assistant/questions')
            ->assertOk()
            ->assertSeeInOrder(['Closing &amp; possession', 'Service charges &amp; fees'], false)
            ->assertSee('When can we move in?')
            ->assertSee('4-Bed Home in Al Barsha')
            ->assertDontSee('Ancient question');
    }

    public function test_the_report_is_empty_before_anything_is_deferred(): void
    {
        $this->actingAs($this->agent)->get('/admin/assistant/questions')
            ->assertOk()
            ->assertSee('Nothing unanswered');
    }
}
