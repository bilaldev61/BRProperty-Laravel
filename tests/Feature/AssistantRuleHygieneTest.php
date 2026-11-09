<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Property;
use App\Support\Ai\AiConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

/**
 * Rule hygiene: the four ways the free layers used to answer wrongly or not at
 * all - a British spelling nobody typed, a two-part question answered by one
 * half, a price written with commas, and a suburb typed as an abbreviation.
 */
class AssistantRuleHygieneTest extends TestCase
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
            'title' => '4-bed villa in Al Barsha',
            'type' => 'house',
            'status' => 'for_sale',
            'is_published' => true,
            'address' => 'Villa 14, Al Barsha 2',
            'suburb' => 'Al Barsha',
            'state' => 'Dubai',
            'postcode' => '',
            'price' => 625000,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'carspaces' => 2,
            'land_size' => 7200,
            'floor_size' => 2450,
            'features' => ['Corner plot', 'Park facing'],
            'inspection_times' => 'Saturday 11am - 1pm',
            'description' => 'A corner home.',
        ], $attributes));
    }

    private function ask(string $message, ?Property $property = null): string
    {
        return $this->postJson('/assistant', ['message' => $message, 'property' => $property?->slug])
            ->assertOk()
            ->json('reply');
    }

    private function lastAnsweredBy(): ?string
    {
        return ChatMessage::where('role', 'assistant')->latest('id')->value('answered_by');
    }

    /** The keyless path: no model, so only the two rule layers can answer. */
    private function withoutModel(): void
    {
        $this->app->instance(AiConnector::class, FakeAiConnector::unconfigured());
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Both spellings of "instalment"
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{0: string}> */
    public static function instalmentQuestions(): array
    {
        return [
            'british singular' => ['Can I pay in instalment?'],
            'british plural'   => ['Can I pay in instalments?'],
            'american'         => ['Can I pay in installments?'],
            'payment plan'     => ['Is there a payment plan?'],
        ];
    }

    #[DataProvider('instalmentQuestions')]
    public function test_financing_questions_are_deferred_in_every_spelling(string $question): void
    {
        $this->ask($question, $this->property());

        $this->assertSame('rule:deferral_financing', $this->lastAnsweredBy());
    }

    /*
    |--------------------------------------------------------------------------
    | 2. A two-part question gets both answers, or neither
    |--------------------------------------------------------------------------
    */

    public function test_a_two_part_question_is_answered_in_full_not_half(): void
    {
        $property = $this->property();

        $reply = $this->ask('What is the price and is it negotiable?', $property);

        $this->assertStringContainsString('advertised price', $reply);
        $this->assertStringContainsString('will confirm whether there is room on the price', $reply);
        $this->assertStringStartsWith('rule:multi_', $this->lastAnsweredBy());

        // The price answer's own "can confirm whether there is any room on it"
        // is the same sentence again - it must not be said twice.
        $this->assertSame(1, substr_count($reply, 'room on'));
    }

    public function test_two_listing_facts_are_joined_into_one_reply(): void
    {
        $property = $this->property(['furnishing' => 'unfurnished', 'completion' => 'ready']);

        $reply = $this->ask('Is it furnished and ready?', $property);

        $this->assertStringContainsString('unfurnished', $reply);
        $this->assertStringContainsString('Ready', $reply);
        $this->assertSame('rule:multi_furnishing+completion', $this->lastAnsweredBy());
    }

    public function test_a_size_and_a_negotiation_question_are_both_answered(): void
    {
        $reply = $this->ask('What size is it and is the price final?', $this->property());

        $this->assertStringContainsString('land size', $reply);
        $this->assertStringContainsString('will confirm whether there is room on the price', $reply);
        $this->assertSame('rule:multi_size+price_negotiation', $this->lastAnsweredBy());
    }

    public function test_two_questions_marked_with_question_marks_are_both_answered(): void
    {
        $reply = $this->ask('Price? Bedrooms?', $this->property());

        $this->assertStringContainsString('advertised price', $reply);
        $this->assertStringContainsString('bedrooms', $reply);
        $this->assertSame('rule:multi_price+rooms', $this->lastAnsweredBy());
    }

    public function test_the_same_question_twice_is_answered_once(): void
    {
        $reply = $this->ask('How many bedrooms and bathrooms?', $this->property());

        $this->assertSame('rule:listing_rooms', $this->lastAnsweredBy());
        $this->assertSame(1, substr_count($reply, 'bedrooms'));
    }

    public function test_one_unanswerable_part_sends_the_whole_message_to_the_model(): void
    {
        // No furnishing on the record, so the first half cannot be answered.
        $reply = $this->ask('Is it furnished and what is the price?', $this->property());

        $this->assertSame('MODEL REPLY', $reply);
        $this->assertSame('ai', $this->lastAnsweredBy());
    }

    public function test_four_questions_at_once_belong_to_the_model(): void
    {
        $reply = $this->ask('What is the price and size and rooms and status?', $this->property());

        $this->assertSame('MODEL REPLY', $reply);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Numbers written the way people write them
    |--------------------------------------------------------------------------
    */

    public function test_a_price_with_thousands_separators_is_one_number(): void
    {
        $this->withoutModel();

        $cheap = $this->property(['title' => 'Garden villa under budget', 'price' => 1200000]);
        $dear = $this->property(['title' => 'Palm villa above budget', 'price' => 1800000, 'suburb' => 'Palm Jumeirah']);

        $reply = $this->ask('under 1,500,000');

        $this->assertStringContainsString('under AED 1,500,000', $reply);
        $this->assertStringContainsString($cheap->title, $reply);
        $this->assertStringNotContainsString($dear->title, $reply);
    }

    public function test_a_budget_in_lakh_is_read_as_money(): void
    {
        $this->withoutModel();

        $cheap = $this->property(['title' => 'Garden villa under budget', 'price' => 6500000]);

        $reply = $this->ask('under 80 lakh');

        $this->assertStringContainsString('under AED 8,000,000', $reply);
        $this->assertStringContainsString($cheap->title, $reply);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Suburbs typed as everybody types them
    |--------------------------------------------------------------------------
    */

    public function test_a_suburb_abbreviation_pins_the_search(): void
    {
        $this->withoutModel();

        $jvc = $this->property(['title' => 'Villa in the circle', 'suburb' => 'Jumeirah Village Circle']);
        $barsha = $this->property(['title' => 'Villa in Barsha', 'suburb' => 'Al Barsha']);

        $reply = $this->ask('2 bed in JVC');

        $this->assertStringContainsString('in Jumeirah Village Circle', $reply);
        $this->assertStringContainsString($jvc->title, $reply);
        $this->assertStringNotContainsString($barsha->title, $reply);
    }

    public function test_an_alias_never_points_at_a_suburb_we_do_not_have(): void
    {
        $this->withoutModel();

        $this->property(['title' => 'Villa in the circle', 'suburb' => 'Jumeirah Village Circle']);

        // "JVT" maps to a suburb with no listings, so the search must not
        // silently widen to everything - it looks for JVT and finds nothing.
        $reply = $this->ask('2 bed in JVT');

        $this->assertStringContainsString('I do not have any listings', $reply);
    }

    /*
    |--------------------------------------------------------------------------
    | Unchanged guarantees
    |--------------------------------------------------------------------------
    */

    public function test_a_comparison_still_belongs_to_the_model(): void
    {
        $reply = $this->ask('Compare this with the Marina flat', $this->property());

        $this->assertSame('MODEL REPLY', $reply);
    }

    public function test_a_plain_single_question_is_still_one_rule_answer(): void
    {
        $reply = $this->ask('How big is it?', $this->property());

        $this->assertSame('rule:listing_size', $this->lastAnsweredBy());
        $this->assertSame(1, ChatConversation::count());
    }

    /*
    |--------------------------------------------------------------------------
    | The model switch
    |--------------------------------------------------------------------------
    */

    public function test_the_model_can_be_switched_off_without_touching_the_key(): void
    {
        config(['ai.chat.model_enabled' => false]);

        // The connector is configured and idle: the switch must win over it.
        $reply = $this->ask('Is this a good area for families?', $this->property());

        $this->assertSame(0, $this->ai->calls);
        $this->assertSame('rule:guide', $this->lastAnsweredBy());
        $this->assertStringContainsString(config('agent.name'), $reply);
    }

    public function test_the_rules_still_answer_with_the_model_switched_off(): void
    {
        config(['ai.chat.model_enabled' => false]);

        $this->ask('What is the service charge?', $this->property(['service_charge' => 14]));

        $this->assertSame('rule:listing_service_charge', $this->lastAnsweredBy());
        $this->assertSame(0, $this->ai->calls);
    }

    public function test_the_switch_back_on_returns_the_hard_questions_to_the_model(): void
    {
        config(['ai.chat.model_enabled' => true]);

        $this->assertSame('MODEL REPLY', $this->ask('Is this a good area for families?', $this->property()));
        $this->assertSame(1, $this->ai->calls);
    }
}
