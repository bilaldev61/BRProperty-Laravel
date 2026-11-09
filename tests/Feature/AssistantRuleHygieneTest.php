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
 * The free layers: spellings, two-part questions, prices and abbreviations.
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


    public function test_a_two_part_question_is_answered_in_full_not_half(): void
    {
        $property = $this->property();

        $reply = $this->ask('What is the price and is it negotiable?', $property);

        $this->assertStringContainsString('advertised price', $reply);
        $this->assertStringContainsString('will confirm whether there is room on the price', $reply);
        $this->assertStringStartsWith('rule:multi_', $this->lastAnsweredBy());

        // The price answer already says it - it must not be said twice.
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

        // "JVT" has no listings; the search must not widen to everything.
        $reply = $this->ask('2 bed in JVT');

        $this->assertStringContainsString('I do not have any listings', $reply);
    }


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


    public function test_the_model_can_be_switched_off_without_touching_the_key(): void
    {
        config(['ai.chat.model_enabled' => false]);

        // Configured connector, idle: the switch must win over it.
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

    public function test_a_comparison_is_not_answered_with_an_unrelated_listing(): void
    {
        config(['ai.chat.model_enabled' => false]);

        // Parses as area=Marina + type=flat, which ignores the comparison.
        $reply = $this->ask('Compare this with the Marina flat', $this->property());

        $this->assertSame('rule:guide', $this->lastAnsweredBy());
        $this->assertStringNotContainsString('I found', $reply);
        $this->assertStringContainsString(config('agent.name'), $reply);
    }

    /*
    |--------------------------------------------------------------------------
    | How the transaction itself works
    |--------------------------------------------------------------------------
    */

    public function test_how_renting_works_is_answered_by_rule(): void
    {
        $this->withoutModel();

        $reply = $this->ask('how the rent works');

        $this->assertSame('rule:process', $this->lastAnsweredBy());
        $this->assertStringContainsString('How renting works in Dubai', $reply);
        $this->assertStringContainsString('Ejari', $reply);
        $this->assertStringNotContainsString('just tell me the area', $reply);
    }

    public function test_a_typo_in_rent_still_reaches_the_rental_steps(): void
    {
        $this->withoutModel();

        $this->ask('how does the rennt work');

        $this->assertSame('rule:process', $this->lastAnsweredBy());
    }

    public function test_how_buying_works_names_the_land_department(): void
    {
        $this->withoutModel();

        $reply = $this->ask('how does buying work in Dubai?');

        $this->assertSame('rule:process', $this->lastAnsweredBy());
        $this->assertStringContainsString('Dubai Land Department', $reply);
    }

    public function test_a_budget_turns_a_rental_question_into_a_search(): void
    {
        $this->withoutModel();

        $this->ask('how much to rent a 2 bed in Al Barsha');

        $this->assertStringStartsWith('rule:search', (string) $this->lastAnsweredBy());
    }

    public function test_a_question_about_a_price_is_still_a_listing_fact(): void
    {
        $this->withoutModel();

        $this->ask('how does the price work?', $this->property());

        $this->assertSame('rule:listing_price', $this->lastAnsweredBy());
    }
}
