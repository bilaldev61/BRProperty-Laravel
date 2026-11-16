<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Property;
use App\Support\Ai\AiConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

/**
 * The generic keyword browse: a visitor word the search does not know ("near
 * canal", "cnanal ones") becomes a keyword query over the listing text instead
 * of needing a rule of its own. Known vocabulary keeps its own path, and
 * questions about the listing on screen stay with the model.
 */
class AssistantKeywordSearchTest extends TestCase
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

    private function canalBook(): Property
    {
        $page = $this->property([
            'title' => 'Canal-facing 1-bed in Business Bay, chiller free',
            'type' => 'apartment',
            'suburb' => 'Business Bay',
            'address' => 'Bay Square 3, Business Bay',
            'price' => 1150000,
            'bedrooms' => 1,
        ]);
        $this->property([
            'title' => 'Canal studio in Business Bay, sale or let',
            'type' => 'apartment',
            'suburb' => 'Business Bay',
            'address' => 'Bay Square 7, Business Bay',
            'price' => 890000,
            'bedrooms' => 0,
            'is_for_rent' => true,
            'rent_price' => 68000,
            'rent_period' => 'year',
            'rent_status' => 'available',
        ]);
        $this->property([
            'title' => 'Marina 3-bed with a corner terrace and marina berth',
            'type' => 'apartment',
            'suburb' => 'Dubai Marina',
            'address' => 'Marina Gate 2, Dubai Marina',
            'price' => 3650000,
            'bedrooms' => 3,
        ]);

        return $page;
    }

    private function ask(string $message, ?Property $property = null)
    {
        return $this->postJson('/assistant', ['message' => $message, 'property' => $property?->slug]);
    }

    private function lastReply(): ChatMessage
    {
        return ChatMessage::where('role', ChatMessage::ASSISTANT)->latest('id')->firstOrFail();
    }

    public function test_near_canal_for_sale_on_a_listing_page_searches_instead_of_answering_a_page_fact(): void
    {
        // Live failure: this was grabbed as a listing_status fact ("Yes, it is
        // currently for sale at...") about the open listing.
        $page = $this->canalBook();

        $reply = $this->ask('near canal for sale', $page)->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertSame(0, $this->ai->calls);
        $this->assertStringContainsString('Canal studio', $reply);
        $this->assertStringNotContainsString('Marina 3-bed', $reply);
        $this->assertStringNotContainsString('currently for sale', $reply);
    }

    public function test_a_typo_in_the_keyword_is_corrected_against_the_listing_words(): void
    {
        $page = $this->canalBook();

        $reply = $this->ask('near cnanal ones', $page)->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertStringContainsString('Canal-facing', $reply);
        $this->assertStringContainsString('Canal studio', $reply);
    }

    public function test_a_keyword_browse_works_on_a_general_page_too(): void
    {
        $this->canalBook();

        $reply = $this->ask('near canal')->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertStringContainsString('matching "canal"', $reply);
        $this->assertStringContainsString('Canal studio', $reply);
    }

    public function test_a_find_verb_introduces_the_keyword(): void
    {
        $this->canalBook();

        $reply = $this->ask('show me canal listings')->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertStringContainsString('Canal-facing', $reply);
        $this->assertStringNotContainsString('Marina 3-bed', $reply);
    }

    public function test_a_landmark_keeps_its_proximity_path(): void
    {
        $page = $this->canalBook();
        $this->property([
            'title' => 'Metro-side 2-bed in Al Barsha',
            'suburb' => 'Al Barsha',
            'nearby' => [['type' => 'metro', 'name' => 'Mall of the Emirates', 'distance' => '5 min drive']],
        ]);

        $reply = $this->ask('find homes near metro', $page)->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertStringContainsString('Metro-side', $reply);
        $this->assertStringNotContainsString('matching', $reply);
    }

    public function test_an_area_alias_keeps_its_area_path(): void
    {
        $page = $this->canalBook();

        $reply = $this->ask('any near marina', $page)->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertStringContainsString('Marina 3-bed', $reply);
        $this->assertStringNotContainsString('Canal studio', $reply);
    }

    public function test_a_question_about_the_open_listing_stays_with_the_model(): void
    {
        $page = $this->canalBook();

        $this->ask('is this near the canal?', $page)->assertOk();

        $this->assertSame('ai', $this->lastReply()->answered_by);
        $this->assertSame(1, $this->ai->calls);
    }

    public function test_an_unmatchable_keyword_gets_an_honest_no_results(): void
    {
        $this->canalBook();

        $reply = $this->ask('near zeppelin')->assertOk()->json('reply');

        $this->assertSame('rule:search_none', $this->lastReply()->answered_by);
        $this->assertStringContainsString('matching "zeppelin"', $reply);
        $this->assertStringNotContainsString('Canal', $reply);
    }

    public function test_anyother_drops_the_listing_on_screen(): void
    {
        $page = $this->canalBook();

        $reply = $this->ask('anyother', $page)->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertStringNotContainsString(route('properties.show', $page), $reply);
        $this->assertStringContainsString('Marina 3-bed', $reply);
    }

    public function test_the_keyword_combines_with_a_stated_area(): void
    {
        $this->canalBook();
        $this->property([
            'title' => 'Canal view 3-bed with private terrace',
            'type' => 'apartment',
            'suburb' => 'Dubai Marina',
            'address' => 'Marina Promenade, Dubai Marina',
            'price' => 2500000,
            'bedrooms' => 3,
        ]);

        $reply = $this->ask('near canal in business bay')->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertStringContainsString('Canal studio', $reply);
        $this->assertStringNotContainsString('Canal view 3-bed', $reply);
    }

    public function test_a_rent_keyword_browse_searches_the_rental_book(): void
    {
        $this->canalBook();

        $reply = $this->ask('near canal for rent')->assertOk()->json('reply');

        $this->assertSame('rule:search', $this->lastReply()->answered_by);
        $this->assertStringContainsString('Canal studio', $reply);
        $this->assertStringNotContainsString('Canal-facing', $reply);
    }

    public function test_a_price_question_is_not_turned_into_a_keyword_search(): void
    {
        $page = $this->canalBook();

        $this->ask('What is the price?', $page)->assertOk();

        $this->assertSame(0, $this->ai->calls);
        $this->assertStringStartsWith('rule:', $this->lastReply()->answered_by);
        $this->assertStringNotContainsString('matching', $this->lastReply()->content);
    }

    public function test_a_judgement_between_two_listings_stays_with_the_model(): void
    {
        $page = $this->canalBook();

        $this->ask('which is better, the canal one or the marina one?', $page)->assertOk();

        $this->assertSame('ai', $this->lastReply()->answered_by);
    }
}
