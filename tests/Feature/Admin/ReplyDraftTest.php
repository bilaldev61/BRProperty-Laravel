<?php

namespace Tests\Feature\Admin;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Support\Ai\AiConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

class ReplyDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create();
    }

    private function fake(FakeAiConnector $connector = new FakeAiConnector()): FakeAiConnector
    {
        $this->app->instance(AiConnector::class, $connector);

        return $connector;
    }

    private function enquiry(array $attributes = []): Enquiry
    {
        return Enquiry::create(array_merge([
            'type' => 'contact', 'name' => 'Pat Buyer', 'email' => 'pat@example.com',
            'message' => 'Is the price negotiable?',
        ], $attributes));
    }

    private function property(): Property
    {
        return Property::create([
            'title' => '10 Marla House in DHA Phase 6', 'type' => 'house',
            'status' => 'for_sale', 'is_published' => true,
            'address' => 'House 42, Block J', 'suburb' => 'DHA Phase 6',
            'state' => 'Punjab', 'postcode' => '54000',
            'price' => 32500000, 'bedrooms' => 4, 'bathrooms' => 3, 'carspaces' => 2,
            'description' => 'A well-kept corner house on a quiet street, park facing.',
        ]);
    }

    public function test_it_returns_a_draft_for_the_agent_to_edit(): void
    {
        $this->fake(new FakeAiConnector('Thanks Pat, I will check and revert.'));
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)
            ->postJson("/admin/enquiries/{$enquiry->id}/draft")
            ->assertOk()
            ->assertJson(['draft' => 'Thanks Pat, I will check and revert.']);
    }

    public function test_the_draft_is_never_saved_to_the_enquiry(): void
    {
        $this->fake();
        $enquiry = $this->enquiry();
        $before = $enquiry->updated_at;

        $this->actingAs($this->agent)->postJson("/admin/enquiries/{$enquiry->id}/draft")->assertOk();

        $enquiry->refresh();
        $this->assertSame('Is the price negotiable?', $enquiry->message);
        $this->assertTrue($before->equalTo($enquiry->updated_at));
    }

    public function test_the_prompt_is_grounded_in_the_real_listing(): void
    {
        $fake = $this->fake();
        $property = $this->property();
        $enquiry = $this->enquiry(['type' => 'property', 'property_id' => $property->id]);

        $this->actingAs($this->agent)->postJson("/admin/enquiries/{$enquiry->id}/draft")->assertOk();

        // The facts the model is allowed to state come from the record, not its
        // imagination - so they must actually be in the prompt.
        $this->assertStringContainsString('10 Marla House in DHA Phase 6', $fake->prompt);
        $this->assertStringContainsString('House 42, Block J', $fake->prompt);
        $this->assertStringContainsString($property->priceDisplay(), $fake->prompt);
        $this->assertStringContainsString('For Sale', $fake->prompt);
        $this->assertStringContainsString('Is the price negotiable?', $fake->prompt);
    }

    public function test_the_model_is_told_not_to_guess(): void
    {
        $fake = $this->fake();
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)->postJson("/admin/enquiries/{$enquiry->id}/draft")->assertOk();

        $this->assertStringContainsString('do NOT guess', $fake->system);
        $this->assertStringContainsString('Never invent', $fake->system);
    }

    public function test_a_seller_enquiry_is_framed_as_a_valuation_not_a_quote(): void
    {
        $fake = $this->fake();
        $enquiry = $this->enquiry([
            'type' => 'appraisal',
            'details' => ['suburb' => 'Wapda Town', 'timeframe' => '1-3 months'],
        ]);

        $this->actingAs($this->agent)->postJson("/admin/enquiries/{$enquiry->id}/draft")->assertOk();

        $this->assertStringContainsString('this is a seller, not a buyer', $fake->prompt);
        $this->assertStringContainsString('do not quote any number', $fake->prompt);
        $this->assertStringContainsString('Wapda Town', $fake->prompt);
    }

    public function test_it_degrades_when_no_credentials_are_configured(): void
    {
        $this->fake(FakeAiConnector::unconfigured());
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)
            ->postJson("/admin/enquiries/{$enquiry->id}/draft")
            ->assertStatus(503);
    }

    public function test_a_model_outage_does_not_break_the_page(): void
    {
        $this->fake(FakeAiConnector::failing());
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)
            ->postJson("/admin/enquiries/{$enquiry->id}/draft")
            ->assertStatus(503)
            ->assertJsonStructure(['message']);

        // The enquiry page itself must still open and still be workable.
        $this->actingAs($this->agent)->get("/admin/enquiries/{$enquiry->id}")->assertOk();
    }

    public function test_the_draft_button_is_hidden_when_ai_is_not_configured(): void
    {
        $this->fake(FakeAiConnector::unconfigured());
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)->get("/admin/enquiries/{$enquiry->id}")
            ->assertOk()
            ->assertDontSee('ReplyDrafter');
    }

    public function test_the_draft_button_appears_when_ai_is_configured(): void
    {
        $this->fake();
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)->get("/admin/enquiries/{$enquiry->id}")
            ->assertOk()
            ->assertSee('ReplyDrafter');
    }

    public function test_guests_cannot_draft_replies(): void
    {
        $fake = $this->fake();
        $enquiry = $this->enquiry();

        $this->postJson("/admin/enquiries/{$enquiry->id}/draft")->assertUnauthorized();

        $this->assertSame(0, $fake->calls);
    }
}
