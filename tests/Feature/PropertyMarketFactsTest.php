<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Property;
use App\Support\Ai\AiConnector;
use App\Support\PropertyFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

/**
 * The market facts a listing is expected to carry: what is next door, what it
 * costs to run, and the identifiers a buyer asks for before they agree to view.
 *
 * The rule layer reads the same columns, so these tests run without a model.
 */
class PropertyMarketFactsTest extends TestCase
{
    use RefreshDatabase;

    private FakeAiConnector $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = new FakeAiConnector('MODEL REPLY');
        $this->app->instance(AiConnector::class, $this->ai);
    }

    private function property(array $attributes = []): Property
    {
        return Property::create(array_merge([
            'title' => 'Golf course villa in Dubai Hills',
            'type' => 'house',
            'status' => 'for_sale',
            'is_published' => true,
            'address' => 'Villa 12, Dubai Hills Estate',
            'suburb' => 'Dubai Hills Estate',
            'state' => 'Dubai',
            'postcode' => '',
            'price' => 7900000,
            'bedrooms' => 4,
            'bathrooms' => 5,
            'carspaces' => 2,
            'floor_size' => 4300,
            'land_size' => 6800,
            'features' => ['Backs onto the 9th fairway'],
            'description' => 'A family villa on the golf course.',
            'nearby' => [
                ['type' => 'metro', 'name' => 'Equiti Metro (Red Line)', 'distance' => '15 min drive'],
                ['type' => 'mall', 'name' => 'Dubai Hills Mall', 'distance' => '6 min drive'],
                ['type' => 'school', 'name' => 'GEMS International School', 'distance' => '7 min drive'],
            ],
            'view_type' => '9th fairway of Dubai Hills Golf Club',
            'furnishing' => 'unfurnished',
            'completion' => 'ready',
            'ownership' => 'freehold',
            'service_charge' => 12,
            'payment_terms' => '10% deposit · 2% agency fee · 4% DLD',
            'permit_number' => '7146119490',
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
        return ChatMessage::where('role', 'assistant')->latest('id')->first();
    }

    /*
    |--------------------------------------------------------------------------
    | On the page
    |--------------------------------------------------------------------------
    */

    public function test_a_listing_prints_what_is_nearby_and_what_it_costs_to_run(): void
    {
        $property = $this->property();

        $response = $this->get('/properties/' . $property->slug)->assertOk();

        $response->assertSee('Listing details');
        $response->assertSee("What's nearby", false);

        // The three highlights under the address, before any scrolling.
        $response->assertSee('Equiti Metro (Red Line)');
        $response->assertSee('15 min drive');
        $response->assertSee('Dubai Hills Mall');
        $response->assertSee('6 min drive');

        // The details table and the landmark groups.
        $response->assertSee('AED 12 per sq ft / year');
        $response->assertSee('7146119490');
        $response->assertSee('10% deposit · 2% agency fee · 4% DLD');
        $response->assertSee('GEMS International School');

        // The details table sits above the landmark groups.
        $html = $response->getContent();
        $this->assertTrue(strpos($html, 'Listing details') < strpos($html, "What's nearby"));
    }

    public function test_the_card_stays_clean_and_leaves_the_surroundings_to_the_listing(): void
    {
        $property = $this->property();

        $this->get('/for-sale')
            ->assertOk()
            ->assertSee($property->title)
            ->assertDontSee('min drive to')
            ->assertDontSee('min walk to');
    }

    public function test_a_listing_without_market_facts_renders_as_before(): void
    {
        $property = $this->property([
            'nearby' => null,
            'view_type' => null,
            'service_charge' => null,
            'permit_number' => null,
            'payment_terms' => null,
        ]);

        $this->get('/properties/' . $property->slug)
            ->assertOk()
            ->assertDontSee("What's nearby", false)
            ->assertDontSee('per sq ft / year');
    }

    /*
    |--------------------------------------------------------------------------
    | What the assistant is allowed to say
    |--------------------------------------------------------------------------
    */

    public function test_the_model_is_told_the_landmarks_and_the_service_charge(): void
    {
        $facts = implode("\n", PropertyFacts::lines($this->property()));

        $this->assertStringContainsString('Service charge: AED 12 per sq ft / year.', $facts);
        $this->assertStringContainsString('Metro & transport: Equiti Metro (Red Line) — 15 min drive.', $facts);
        $this->assertStringContainsString('Shopping: Dubai Hills Mall — 6 min drive.', $facts);
        $this->assertStringContainsString('Furnishing: Unfurnished.', $facts);
        $this->assertStringContainsString('Advertising permit number: 7146119490.', $facts);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function marketQuestions(): array
    {
        return [
            'metro'          => ['Is there a metro nearby?', 'Equiti Metro (Red Line)', 'rule:listing_nearby_metro'],
            'mall'           => ['Which mall is close to it?', 'Dubai Hills Mall', 'rule:listing_nearby_mall'],
            'school'         => ['Are there schools nearby?', 'GEMS International School', 'rule:listing_nearby_school'],
            'nearby broadly' => ['What is nearby?', '15 min drive', 'rule:listing_nearby_all'],
            'service charge' => ['What is the service charge?', 'AED 12 per sq ft / year', 'rule:listing_service_charge'],
            'furnishing'     => ['Is it furnished?', 'It is unfurnished.', 'rule:listing_furnishing'],
            'view'           => ['What is the view?', '9th fairway', 'rule:listing_view'],
            'permit'         => ['What is the permit number?', '7146119490', 'rule:listing_permit'],
            'completion'     => ['Is it ready?', 'Completion: Ready', 'rule:listing_completion'],
        ];
    }

    #[DataProvider('marketQuestions')]
    public function test_market_questions_are_answered_from_the_record_without_the_model(string $question, string $expected, string $rule): void
    {
        $property = $this->property();

        $this->assertStringContainsString($expected, $this->ask($question, $property));
        $this->assertSame(0, $this->ai->calls);
        $this->assertSame($rule, $this->lastReply()->answered_by);
    }

    public function test_a_market_question_is_not_read_as_a_question_about_the_view(): void
    {
        $property = $this->property();

        // "Outlook" used to be one of the words that reached the view rule, so
        // this question was answered with the view from the balcony.
        $reply = $this->ask('What is the market outlook for next year?', $property);

        $this->assertStringNotContainsString('The view is', $reply);
        $this->assertSame('ai', $this->lastReply()->answered_by);
        $this->assertSame(1, $this->ai->calls);
    }

    public function test_a_missing_landmark_is_admitted_rather_than_guessed(): void
    {
        $property = $this->property(['nearby' => []]);

        $reply = $this->ask('Is there a metro nearby?', $property);

        $this->assertStringContainsString('not recorded for this listing', $reply);
        $this->assertStringNotContainsString('min drive', $reply);
        $this->assertSame(0, $this->ai->calls);
    }

    public function test_a_service_charge_falls_back_to_the_agent_when_none_is_recorded(): void
    {
        $property = $this->property(['service_charge' => null]);

        $reply = $this->ask('What are the service charges?', $property);

        $this->assertStringContainsString('will confirm', $reply);
        $this->assertSame(0, $this->ai->calls);
    }
}
