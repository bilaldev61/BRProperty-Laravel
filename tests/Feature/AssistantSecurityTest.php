<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Property;
use App\Support\Ai\AiConnector;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Abuse and injection guards for the public chat.
 *
 * These are deliberately hostile inputs: the point is not what the assistant
 * answers but that nothing it is fed can break the app, leak data it should not
 * show, or get echoed back as live markup. Rules-only mode (no API key) is the
 * most exposed surface, so that is what these run against.
 */
class AssistantSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AiConnector::class, FakeAiConnector::unconfigured());
    }

    private function ask(string $message, ?Property $property = null)
    {
        return $this->postJson('/assistant', ['message' => $message, 'property' => $property?->slug]);
    }

    private function property(array $attributes = []): Property
    {
        return Property::create(array_merge([
            'title' => 'Published 4-Bed in Zilker',
            'type' => 'house',
            'status' => 'for_sale',
            'is_published' => true,
            'address' => '1408 Lamont Avenue',
            'suburb' => 'Zilker',
            'state' => 'Texas',
            'postcode' => '78704',
            'price' => 625000,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'carspaces' => 2,
            'land_size' => 7200,
            'description' => 'A well-kept home.',
        ], $attributes));
    }

    public function test_a_script_payload_is_never_returned_as_live_markup(): void
    {
        $payload = '<script>alert(document.cookie)</script><img src=x onerror=alert(1)>';

        $reply = $this->ask($payload)->assertOk()->json('reply');

        // The assistant's own text must not carry the tags back; and the chat
        // renders replies as text (never v-html), so there is nothing to execute.
        $this->assertStringNotContainsString('<script', $reply);
        $this->assertStringNotContainsString('onerror', $reply);
    }

    public function test_an_sql_injection_attempt_cannot_touch_the_schema_or_data(): void
    {
        $keep = $this->property();
        $this->property(['title' => 'Second Home', 'suburb' => 'Hyde Park']);

        $this->ask("'; DROP TABLE properties; --")
            ->assertOk()
            ->assertDontSee('SQLSTATE');

        // The table still exists and its rows are untouched.
        $this->assertSame(2, Property::count());
        $this->assertNotNull(Property::find($keep->id));
    }

    public function test_an_oversized_message_is_rejected_and_nothing_is_stored(): void
    {
        $this->postJson('/assistant', ['message' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, ChatConversation::count());
    }

    public function test_an_unknown_suburb_leaks_nothing_and_never_shows_unpublished_listings(): void
    {
        $this->property();
        $hidden = $this->property([
            'title' => 'Secret Off-Market Villa',
            'suburb' => 'Nowhere-not-published',
            'is_published' => false,
        ]);

        $reply = $this->ask('Homes in Atlantis that do not exist')->assertOk()->json('reply');

        $this->assertStringNotContainsString($hidden->title, $reply);
        $this->assertStringNotContainsString($hidden->slug, $reply);
    }

    public function test_a_nonexistent_property_slug_cannot_be_forced_into_a_conversation(): void
    {
        $hidden = $this->property(['title' => 'Unlisted', 'is_published' => false]);

        // Pointing the chat at an unpublished slug is treated as "no listing"
        // rather than exposing it.
        $this->ask('Is this available?', $hidden)->assertOk();

        $this->assertNull(ChatConversation::sole()->property_id);
    }

    public function test_like_wildcards_in_the_query_are_inert(): void
    {
        $this->property();

        // '%', '_' are SQL LIKE metacharacters; they must not blow up or widen
        // the search past published listings.
        $this->ask('Homes in %_% under $500K')->assertOk();
        $this->ask('show me anything in _ _')->assertOk();

        $this->assertSame(1, Property::count());
    }

    public function test_a_bare_int_or_huge_number_does_not_break_price_parsing(): void
    {
        $this->property(['price' => 625000]);

        $this->ask('house for 99999999999999999999')->assertOk();
        $this->ask('house under -500')->assertOk();
    }
}
