<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Property;
use App\Services\Assistant\PropertyAssistant;
use App\Support\Ai\AiConnector;
use App\Support\AssistantSuggestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

/**
 * The starter chips are a promise: click one and the assistant has something
 * to say. That promise is kept by building them from stock that exists and by
 * answering them from the rules, so no chip depends on the model being up.
 */
class AssistantSuggestionsTest extends TestCase
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

    /** One of everything the chips can point at. */
    private function stock(): void
    {
        $this->property(['suburb' => 'Downtown Dubai', 'price' => 2350000]);
        $this->property(['suburb' => 'Downtown Dubai', 'price' => 2400000, 'title' => 'Second Downtown flat']);
        $this->property([
            'type'     => 'townhouse',
            'suburb'   => 'Arabian Ranches',
            'price'    => 4200000,
            'bedrooms' => 3,
            'title'    => 'Three-bed townhouse in Arabian Ranches',
        ]);
        $this->property(['type' => 'house', 'suburb' => 'Dubai Hills Estate', 'price' => 7900000, 'title' => 'Four-bed in Dubai Hills']);
        $this->property([
            'type'          => 'townhouse',
            'status'        => 'rental',
            'is_for_rent'   => true,
            'rent_price'    => 180000,
            'rent_period'   => 'year',
            'suburb'        => 'Jumeirah Lake Towers',
            'price'         => null,
            'title'         => 'Rental in JLT',
        ]);
    }

    /** The last thing the assistant said. */
    private function lastReply(): ChatMessage
    {
        return ChatMessage::query()->where('role', ChatMessage::ASSISTANT)->latest('id')->firstOrFail();
    }

    public function test_the_chips_point_at_stock_we_actually_have(): void
    {
        $this->stock();

        $chips = app(AssistantSuggestions::class)->for(null);

        $this->assertContains('Apartments in Downtown Dubai for sale', $chips);
        $this->assertContains('Townhouses in Arabian Ranches for sale', $chips);
        $this->assertContains('Anything to rent in Jumeirah Lake Towers?', $chips);
        $this->assertContains('What areas do you cover?', $chips);
    }

    public function test_a_townhouse_area_is_asked_about_as_townhouses(): void
    {
        // The word the chip uses is the word the rules search on: "homes" here
        // would look for houses and report an area empty that is not empty.
        $this->property(['type' => 'townhouse', 'suburb' => 'Arabian Ranches', 'price' => 4200000]);

        $chips = app(AssistantSuggestions::class)->for(null);

        $this->assertContains('Townhouses in Arabian Ranches for sale', $chips);
        $this->assertNotContains('Homes in Arabian Ranches for sale', $chips);
    }

    public function test_the_chips_come_with_the_chat_not_the_page(): void
    {
        $this->fake(FakeAiConnector::unconfigured());
        $this->stock();

        // Rendering a page must not query the listings for decoration; the
        // chips arrive when the bubble is opened.
        $this->getJson('/assistant')
            ->assertOk()
            ->assertJsonPath('suggestions.0', 'Apartments in Downtown Dubai for sale');
    }

    public function test_every_chip_is_answered_with_the_model_switched_off(): void
    {
        $this->fake(FakeAiConnector::unconfigured());
        $this->stock();

        $chips = app(AssistantSuggestions::class)->for(null);
        $this->assertNotEmpty($chips);

        foreach ($chips as $chip) {
            $this->postJson('/assistant', ['message' => $chip])->assertOk();
            $reply = $this->lastReply();

            $answered = (string) $reply->answered_by;

            $this->assertTrue(str_starts_with($answered, ChatMessage::BY_RULE_PREFIX), $chip . ' -> ' . $answered);
            $this->assertMatchesRegularExpression(
                '/^rule:(search|process|agency_|deferral_)/',
                $answered,
                $chip . ' should be answered by a rule, not the fallback',
            );
            $this->assertNotSame(app(PropertyAssistant::class)->fallback(), $reply->content, $chip);
        }
    }

    public function test_every_chip_on_a_listing_is_answered_from_the_record(): void
    {
        $this->fake(FakeAiConnector::unconfigured());
        $property = $this->property();

        $chips = app(AssistantSuggestions::class)->for($property);
        $this->assertNotEmpty($chips);

        foreach ($chips as $chip) {
            $this->postJson('/assistant', ['message' => $chip, 'property' => $property->slug])->assertOk();
            $reply = $this->lastReply();

            $this->assertMatchesRegularExpression(
                '/^rule:(fact|deferral)_/',
                (string) $reply->answered_by,
                $chip . ' -> ' . $reply->answered_by,
            );
            $this->assertNotSame(app(PropertyAssistant::class)->fallback(), $reply->content, $chip);
        }
    }

    public function test_a_dead_model_answers_from_the_rules_rather_than_apologising(): void
    {
        $connector = $this->fake(FakeAiConnector::failing());
        $this->stock();

        $this->postJson('/assistant', ['message' => 'Apartments in Downtown Dubai for sale'])
            ->assertOk()
            ->assertJsonPath('reply', fn($reply) => ! str_contains($reply, "Sorry, I can't answer"));

        // The Kit answers a search from the rules before it ever asks the model,
        // so a broken model cannot turn a listing question into an apology.
        $this->assertSame(0, $connector->calls);
        $this->assertMatchesRegularExpression('/^rule:search/', (string) $this->lastReply()->answered_by);
    }

    public function test_an_outage_leaves_a_whole_conversation_behind(): void
    {
        $this->fake(FakeAiConnector::failing());
        $this->stock();

        $this->postJson('/assistant', ['message' => 'Anything to rent in Jumeirah Lake Towers?'])->assertOk();

        // Reopening the bubble must not show a question with nothing under it:
        // the outage used to answer without saving, so visitors clicked again
        // and got the same apology twice.
        $this->getJson('/assistant')
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.1.role', ChatMessage::ASSISTANT);
    }
}
