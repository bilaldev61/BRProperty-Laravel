<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Support\Ai\AiConnector;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The chat with no API key: it must still be useful. The old behaviour dead-
 * ended on "Sorry, I can't answer" for the assistant's own browse questions;
 * these cover the free database search that replaced it.
 */
class AssistantRulesOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No key: the connector reports itself unconfigured, so the chat falls
        // back to the rules-only search instead of calling a model.
        $this->app->instance(AiConnector::class, FakeAiConnector::unconfigured());
    }

    private function ask(string $message, ?Property $property = null)
    {
        return $this->postJson('/assistant', ['message' => $message, 'property' => $property?->slug]);
    }

    private function house(array $attributes = []): Property
    {
        return Property::create(array_merge([
            'title' => '4-Bed Home in Business Bay',
            'type' => 'house',
            'status' => 'for_sale',
            'is_published' => true,
            'address' => 'Villa 11809, Business Bay',
            'suburb' => 'Business Bay',
            'state' => 'Dubai',
            'postcode' => '',
            'price' => 715000,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'carspaces' => 2,
            'land_size' => 6500,
            'description' => 'A well-kept home on a quiet street.',
        ], $attributes));
    }

    public function test_a_browse_question_returns_real_listings_instead_of_the_apology(): void
    {
        $match = $this->house();
        $this->house(['title' => 'Too Expensive', 'price' => 900000]);

        $reply = $this->ask('Villas in Business Bay under AED 750K')->assertOk()->json('reply');

        $this->assertStringContainsString('1 listing', $reply);
        $this->assertStringContainsString('Business Bay', $reply);
        $this->assertStringContainsString($match->slug, $reply);
        $this->assertStringNotContainsString("can't answer", $reply);
    }

    public function test_a_rent_question_returns_rentals(): void
    {
        $this->house([
            'title' => '4-Bed House to Rent in The Springs',
            'suburb' => 'The Springs',
            'status' => 'rental',
            'is_for_rent' => true,
            'rent_price' => 105000,
            'rent_period' => 'year',
            'rent_status' => 'available',
            'price' => null,
        ]);

        $reply = $this->ask('3-bedroom houses for rent')->assertOk()->json('reply');

        $this->assertStringContainsString('The Springs', $reply);
        $this->assertStringNotContainsString("can't answer", $reply);
    }

    public function test_an_area_with_nothing_listed_says_so_without_apologising(): void
    {
        $this->house();

        $reply = $this->ask('Homes in Al Furjan under AED 200K')->assertOk()->json('reply');

        $this->assertStringContainsString('do not have any listings', $reply);
        $this->assertStringNotContainsString("can't answer", $reply);
    }

    public function test_small_talk_is_pointed_somewhere_useful(): void
    {
        $this->house();

        $reply = $this->ask('sup')->assertOk()->json('reply');

        // Not a search, so the guide reply offers a way forward rather than a dead end.
        $this->assertStringContainsString('all our listings', $reply);
        $this->assertStringNotContainsString("can't answer", $reply);
    }

    public function test_a_hello_typed_without_its_vowels_is_answered_as_a_hello(): void
    {
        $this->house();

        // "hlooo" used to fall through to the guide paragraph; it is a greeting
        // and the visitor should be greeted back.
        $reply = $this->ask('hlooo')->assertOk()->json('reply');

        $this->assertStringContainsString('Hi! Tell me the area', $reply);
        $this->assertStringNotContainsString("can't answer", $reply);
    }

    public function test_quick_answers_still_handle_agency_questions_for_free(): void
    {
        $reply = $this->ask('What areas do you cover?')->assertOk()->json('reply');

        $this->assertStringContainsString(config('agent.name'), $reply);
        $this->assertStringContainsString('Business Bay', $reply);
        $this->assertStringNotContainsString("can't answer", $reply);
    }

    public function test_a_listing_page_question_without_a_search_is_directed_to_the_agent(): void
    {
        $property = $this->house();

        $reply = $this->ask('Can I arrange a showing?', $property)->assertOk()->json('reply');

        $this->assertStringContainsString($property->title, $reply);
        $this->assertStringContainsString(config('agent.phone'), $reply);
        $this->assertStringNotContainsString("can't answer", $reply);
    }

    public function test_a_broad_city_search_is_not_pinned_to_one_suburb(): void
    {
        // "Dubai" names the whole city; it must not be read as one suburb and
        // hide everything else.
        $barsha = $this->house(['suburb' => 'Al Barsha', 'title' => '5-Bed in Al Barsha', 'price' => 550000, 'bedrooms' => 5]);
        $this->house(['suburb' => 'Downtown Dubai', 'title' => 'Downtown Apartment', 'type' => 'apartment', 'price' => 500000, 'bedrooms' => 2]);

        $reply = $this->ask('Homes in Dubai under AED 600K')->assertOk()->json('reply');

        $this->assertStringContainsString('I found', $reply);
        $this->assertStringContainsString($barsha->slug, $reply);
        $this->assertStringNotContainsString('do not have any listings', $reply);
    }

    public function test_a_budget_range_filters_both_ends(): void
    {
        $in = $this->house(['title' => 'Mid Price Home', 'price' => 500000]);
        $this->house(['title' => 'Over Budget Home', 'price' => 900000]);

        $reply = $this->ask('Homes in Dubai between AED 400K and AED 600K')->assertOk()->json('reply');

        $this->assertStringContainsString('from AED 400,000 to AED 600,000', $reply);
        $this->assertStringContainsString($in->slug, $reply);
        $this->assertStringNotContainsString('Over Budget Home', $reply);
    }
}
