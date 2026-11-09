<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Property;
use App\Models\User;
use App\Support\Ai\AiConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

/**
 * The rules in front of the model: what they answer for free, and - just as
 * important - what they must leave to the model.
 */
class AssistantQuickAnswersTest extends TestCase
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

    private function lastReply(): ChatMessage
    {
        return ChatMessage::where('role', 'assistant')->latest('id')->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Answered from the database - the model is never called
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{0: string, 1: string, 2: string}> question, expected text, rule */
    public static function listingQuestions(): array
    {
        return [
            'price'            => ['What is the price?', 'AED 625,000', 'rule:listing_price'],
            'price roman urdu' => ['ye ghar kitne ka hai', 'AED 625,000', 'rule:listing_price'],
            'size'             => ['How big is it?', '7,200 sq ft', 'rule:listing_size'],
            'covered area'     => ['What is the covered area', '2,450 sq ft', 'rule:listing_size'],
            'rooms'            => ['How many bedrooms?', '4 bedrooms, 3 bathrooms', 'rule:listing_rooms'],
            'status'           => ['Is it still available?', 'currently for sale', 'rule:listing_status'],
            'location'         => ['Where is it located?', 'Villa 14, Al Barsha 2', 'rule:listing_location'],
            'features'         => ['What features does it have?', 'Park facing', 'rule:listing_features'],
            'inspection'       => ['Inspection times?', 'Saturday 11am - 1pm', 'rule:listing_times'],
        ];
    }

    #[DataProvider('listingQuestions')]
    public function test_simple_listing_questions_are_answered_from_the_record(string $question, string $expected, string $rule): void
    {
        $property = $this->property();

        $this->assertStringContainsString($expected, $this->ask($question, $property));
        $this->assertSame(0, $this->ai->calls);
        $this->assertSame($rule, $this->lastReply()->answered_by);
        $this->assertNull($this->lastReply()->tokens);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function agencyQuestions(): array
    {
        return [
            'greeting'     => ['Hello!', 'Hi!'],
            'salam'        => ['Assalam o alaikum', 'Hi!'],
            'thanks'       => ['Thank you', "You're welcome"],
            'contact'      => ['What is your phone number?', '+971 50 123 4567'],
            'hours'        => ['What are your office hours', 'Saturday: 10:00am - 4:00pm'],
            'office'       => ['Where is your office?', 'Sheikh Zayed Road'],
            'areas'        => ['Which areas do you cover?', 'Business Bay'],
        ];
    }

    #[DataProvider('agencyQuestions')]
    public function test_agency_questions_are_answered_on_any_page(string $question, string $expected): void
    {
        $this->assertStringContainsString($expected, $this->ask($question));
        $this->assertSame(0, $this->ai->calls);
    }

    public function test_off_topic_questions_are_declined_without_the_model(): void
    {
        $reply = $this->ask('Write me a poem about the weather');

        $this->assertStringContainsString('only help with property', $reply);
        $this->assertSame(0, $this->ai->calls);
        $this->assertSame('rule:off_topic', $this->lastReply()->answered_by);
    }

    public function test_questions_only_the_agent_can_answer_are_deferred_and_logged_without_the_model(): void
    {
        $property = $this->property();

        $reply = $this->ask('Is the price negotiable?', $property);

        $this->assertStringContainsString('will confirm whether there is room on the price', $reply);
        $this->assertSame(0, $this->ai->calls);

        $question = ChatConversation::sole()->unansweredQuestions()->sole();
        $this->assertSame('price_negotiation', $question->topic);
        $this->assertSame($property->id, $question->property_id);
    }

    public function test_each_deferral_topic_is_recognised(): void
    {
        $property = $this->property();

        foreach (
            [
                'When is possession?'             => 'possession',
                'Is the NOC clear?'               => 'paperwork',
                'What are the maintenance charges' => 'charges',
                'Is there an installment plan'    => 'financing',
            ] as $question => $topic
        ) {
            $this->ask($question, $property);
            $this->assertSame("rule:deferral_{$topic}", $this->lastReply()->answered_by, $question);
        }

        $this->assertSame(0, $this->ai->calls);
    }

    /*
    |--------------------------------------------------------------------------
    | Left to the model
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{0: string}> */
    public static function modelQuestions(): array
    {
        return [
            // Two questions in one used to land here. They are answered now,
            // both halves together - see AssistantRuleHygieneTest.
            'gives a phone number'   => ['Call me on 512 555 0198'],
            // An address is no longer a model question: it becomes a lead with
            // the name read off it - see AssistantContactCaptureTest.
            'asks for a reason'      => ['Explain the payment terms to me'],
            'wants to visit'         => ['Can I visit on Saturday?'],
            'roman urdu visit'       => ['Main ye ghar dekhna chahta hoon'],
            'a search'               => ['Any houses for sale under AED 700,000?'],
            'a comparison'           => ['Is this better than the one in Al Barsha?'],
            'a bare yes'             => ['ok'],
            'open question'          => ['Is this a good area for families?'],
            'very long'              => [str_repeat('I am looking for a home near good schools ', 5)],
        ];
    }

    #[DataProvider('modelQuestions')]
    public function test_anything_needing_judgement_goes_to_the_model(string $question): void
    {
        $this->assertSame('MODEL REPLY', $this->ask($question, $this->property()));
        $this->assertSame(1, $this->ai->calls);
        $this->assertSame('ai', $this->lastReply()->answered_by);
        $this->assertSame(500, $this->lastReply()->tokens);
    }

    public function test_listing_questions_off_a_listing_page_go_to_the_model(): void
    {
        $this->property();

        // "How much?" - for which property? Only the model can work that out.
        $this->assertSame('MODEL REPLY', $this->ask('What is the price?'));
        $this->assertSame(1, $this->ai->calls);
    }

    public function test_a_listing_question_after_the_model_points_elsewhere_goes_to_the_model(): void
    {
        $property = $this->property();
        $other = $this->property(['title' => '5-bed villa in Dubai Hills', 'suburb' => 'Dubai Hills Estate']);
        $this->ai = new FakeAiConnector('Try this one: ' . route('properties.show', $other));
        $this->app->instance(AiConnector::class, $this->ai);

        $this->ask('Show me something in Dubai Hills instead', $property);

        // "How big is it?" now means the Dubai Hills house, not this page's.
        $this->ask('How big is it?', $property);
        $this->assertSame(2, $this->ai->calls);
    }

    public function test_a_listing_with_nothing_recorded_goes_to_the_model(): void
    {
        $property = $this->property(['features' => null]);

        $this->assertSame('MODEL REPLY', $this->ask('What features does it have?', $property));
    }

    public function test_quick_answers_can_be_switched_off(): void
    {
        config(['ai.chat.quick_answers' => false]);

        $this->assertSame('MODEL REPLY', $this->ask('Hello'));
        $this->assertSame(1, $this->ai->calls);
    }

    public function test_a_sold_listing_says_so(): void
    {
        $property = $this->property(['status' => 'sold', 'sold_price' => 615000]);

        $this->assertStringContainsString('has sold', $this->ask('Is it still available?', $property));
        $this->assertStringContainsString('Sold for AED 615,000', $this->ask('What was the price?', $property));
    }

    /*
    |--------------------------------------------------------------------------
    | What the agent sees
    |--------------------------------------------------------------------------
    */

    public function test_the_admin_shows_how_much_the_rules_saved_and_what_the_model_used(): void
    {
        $property = $this->property();
        $this->ask('What is the price?', $property);        // rule
        $this->ask('How many bedrooms?', $property);        // rule
        $this->ask('Is this a good area for families?', $property); // model, 500 tokens

        $conversation = ChatConversation::sole();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/assistant')
            ->assertOk()
            ->assertSee('Answered without AI')
            ->assertSee('67%')
            ->assertSee('500 per AI reply');

        $this->actingAs($admin)->get("/admin/assistant/{$conversation->id}")
            ->assertOk()
            ->assertSee('from listing data, no AI')
            ->assertSee('AI, 500 tokens');
    }
}
