<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatUnansweredQuestion;
use App\Models\ChatUsage;
use App\Models\Property;
use App\Services\Assistant\DeferralLog;
use App\Services\Assistant\PropertyAssistant;
use App\Support\Ai\AiConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

/**
 * What a model turn costs, and what it records.
 *
 * Both used to depend on the model cooperating: a deferral needed a tool call,
 * and cache hits were invisible. These tests pin that down to the app instead.
 */
class AssistantCostTest extends TestCase
{
    use RefreshDatabase;

    private function fake(FakeAiConnector $connector = new FakeAiConnector('Happy to help.')): FakeAiConnector
    {
        $this->app->instance(AiConnector::class, $connector);

        return $connector;
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
            'bedrooms' => 4,
            'bathrooms' => 3,
            'carspaces' => 2,
            'land_size' => 7200,
            'description' => 'A well-kept corner home on a quiet street, park facing.',
        ]);
    }

    private function ask(string $message, ?Property $property = null)
    {
        return $this->postJson('/assistant', ['message' => $message, 'property' => $property?->slug]);
    }

    /*
    |--------------------------------------------------------------------------
    | The cacheable prefix
    |--------------------------------------------------------------------------
    */

    public function test_the_prompt_prefix_is_identical_across_conversations(): void
    {
        // The prefix is what the provider caches. One live value above the
        // marker and every request pays for the whole prompt again.
        $fake = $this->fake();
        $listing = $this->property();

        $onListing = ChatConversation::create(['property_id' => $listing->id]);
        $general = ChatConversation::create([]);

        app(PropertyAssistant::class)->reply($onListing, 'Tell me about buying in Dubai');
        $first = (string) $fake->system;

        app(PropertyAssistant::class)->reply($general, 'Tell me about buying in Dubai');
        $second = (string) $fake->system;

        $prefix = static fn(string $prompt) => substr($prompt, 0, (int) strpos($prompt, 'CURRENT DATA'));

        $this->assertStringContainsString('CURRENT DATA', $first);
        $this->assertStringContainsString('SAFETY', $prefix($first));
        $this->assertSame($prefix($first), $prefix($second));
    }

    public function test_the_live_values_sit_below_the_marker(): void
    {
        $fake = $this->fake();
        $this->property();

        $this->ask('Tell me about buying in Dubai')->assertOk();

        $prompt = (string) $fake->system;
        $marker = (int) strpos($prompt, 'CURRENT DATA');

        $this->assertStringNotContainsString('listings for sale right now', substr($prompt, 0, $marker));
        $this->assertStringContainsString('Al Barsha', substr($prompt, $marker));
    }

    public function test_the_no_second_search_rule_sits_in_the_cacheable_prefix(): void
    {
        $fake = $this->fake();

        $this->ask('Tell me about buying in Dubai')->assertOk();

        $prompt = (string) $fake->system;
        $marker = (int) strpos($prompt, 'CURRENT DATA');

        // An empty search answered twice is a whole extra billed round; the
        // instruction that prevents it must be fixed text, not live data.
        $this->assertStringContainsString('Do not run another search on your own', substr($prompt, 0, $marker));
    }

    /*
    |--------------------------------------------------------------------------
    | What each request cost
    |--------------------------------------------------------------------------
    */

    public function test_one_row_is_recorded_per_model_request(): void
    {
        $listing = $this->property();
        $this->fake((new FakeAiConnector('It has four bedrooms.'))->callsTool('get_property', ['slug' => $listing->slug]));

        $this->ask('Compare the bedrooms with a townhouse', $listing)->assertOk();

        $usage = ChatUsage::orderBy('round')->get();

        $this->assertSame([0, 1], $usage->pluck('round')->all());
        $this->assertSame('get_property', $usage[0]->tools);
        $this->assertNull($usage[1]->tools);
        $this->assertSame(500, $usage[0]->prompt_tokens);
        $this->assertSame(
            ChatMessage::where('role', ChatMessage::ASSISTANT)->value('id'),
            $usage[0]->chat_message_id,
        );
    }

    public function test_a_rule_answer_records_no_usage(): void
    {
        $this->fake();

        $this->ask('hlo')->assertOk();

        $this->assertSame(0, ChatUsage::count());
    }

    public function test_the_chat_token_is_sent_as_the_session(): void
    {
        $fake = $this->fake();

        $this->ask('Tell me about buying in Dubai')->assertOk();

        $this->assertSame(ChatConversation::sole()->token, $fake->session);
    }

    /*
    |--------------------------------------------------------------------------
    | Saying the same thing twice
    |--------------------------------------------------------------------------
    */

    private function say(ChatConversation $conversation, string $message): string
    {
        return app(PropertyAssistant::class)->reply($conversation, $message);
    }

    public function test_a_repeated_message_does_not_reach_the_model(): void
    {
        $fake = $this->fake(new FakeAiConnector('It is a two-bedroom apartment over the canal.'));
        $conversation = ChatConversation::create(['property_id' => $this->property()->id]);

        $this->say($conversation, 'Tell me what I should check before buying');
        $second = $this->say($conversation, 'Tell me what I should check before buying');

        $this->assertSame(1, $fake->calls);
        $this->assertSame(1, ChatUsage::count());
        $this->assertStringContainsString('already answered', $second);
        $this->assertSame(
            'rule:repeat',
            $conversation->messages()->where('role', ChatMessage::ASSISTANT)->reorder('id', 'desc')->value('answered_by'),
        );
    }

    public function test_punctuation_and_capitals_do_not_make_it_a_new_question(): void
    {
        $fake = $this->fake(new FakeAiConnector('It is a two-bedroom apartment over the canal.'));
        $conversation = ChatConversation::create([]);

        $this->say($conversation, 'Tell me what I should check before buying');
        $second = $this->say($conversation, 'tell me what I should check before buying!!');

        $this->assertSame(1, $fake->calls);
        $this->assertStringContainsString('already answered', $second);
    }

    public function test_a_repeat_files_no_second_question(): void
    {
        $this->fake(new FakeAiConnector('The service charge is not on the record - our team will confirm it.'));
        $conversation = ChatConversation::create(['property_id' => $this->property()->id]);

        $this->say($conversation, 'Tell me what I should check before buying');
        $this->say($conversation, 'Tell me what I should check before buying');

        // One question asked once, however many times it was typed.
        $this->assertSame(1, ChatUnansweredQuestion::count());
    }

    public function test_a_repeated_hello_is_still_a_hello(): void
    {
        $fake = $this->fake();
        $conversation = ChatConversation::create([]);

        $this->say($conversation, 'hi ge');
        $second = $this->say($conversation, 'hi ge');

        $this->assertSame(0, $fake->calls);
        $this->assertStringNotContainsString('already answered', $second);
        $this->assertSame(
            ['rule:greeting', 'rule:greeting'],
            $conversation->messages()->where('role', ChatMessage::ASSISTANT)->pluck('answered_by')->all(),
        );
    }

    public function test_a_lead_is_not_told_to_leave_details_again(): void
    {
        $this->fake(new FakeAiConnector('It is a two-bedroom apartment over the canal.'));
        $conversation = ChatConversation::create([]);

        $this->say($conversation, 'Tell me what I should check before buying. My email is sam@example.com');
        $this->say($conversation, 'Tell me what I should check before buying');
        $third = $this->say($conversation, 'Tell me what I should check before buying');

        $this->assertTrue($conversation->refresh()->isLead());
        $this->assertStringNotContainsString('leave your name and email', $third);
        $this->assertStringContainsString('already with them', $third);
    }

    /*
    |--------------------------------------------------------------------------
    | Deferrals, read from the reply
    |--------------------------------------------------------------------------
    */

    public function test_a_deferral_files_the_question_without_a_tool_call(): void
    {
        $listing = $this->property();
        $fake = $this->fake(new FakeAiConnector('The service charge is not on the record - our team will confirm it.'));

        $reply = $this->ask('Tell me what I should check before buying', $listing);

        $question = ChatConversation::sole()->unansweredQuestions()->sole();
        $this->assertSame('charges', $question->topic);
        $this->assertSame('Tell me what I should check before buying', $question->question);
        $this->assertSame($listing->id, $question->property_id);
        $this->assertStringContainsString('name and email', (string) $reply->json('reply'));
        $this->assertSame([], $fake->toolRuns);
    }

    public function test_a_vague_deferral_is_filed_as_other(): void
    {
        $this->fake(new FakeAiConnector('That is not on the record - our team will confirm it.'));

        $this->ask('Tell me what I should check before buying')->assertOk();

        $this->assertSame('other', ChatConversation::sole()->unansweredQuestions()->sole()->topic);
    }

    public function test_a_plain_answer_files_nothing(): void
    {
        $this->fake(new FakeAiConnector('It is a two-bedroom apartment over the canal.'));

        $this->ask('Tell me what I should check before buying')->assertOk();

        $this->assertSame(0, ChatUnansweredQuestion::count());
    }

    public function test_one_chat_cannot_file_more_than_five_questions(): void
    {
        $conversation = ChatConversation::create([]);
        $log = app(DeferralLog::class);

        for ($i = 1; $i <= 7; $i++) {
            $log->record($conversation, "Question {$i}", 'Our team will confirm.');
        }

        $this->assertSame(5, $conversation->unansweredQuestions()->count());
    }

    public function test_only_a_deferral_counts_as_one(): void
    {
        $log = app(DeferralLog::class);

        $this->assertTrue($log->defers('Our team will confirm the service charge.'));
        $this->assertTrue($log->defers('I will find out and come back to you.'));
        $this->assertFalse($log->defers('It is a four-bedroom villa with a private pool.'));
        $this->assertFalse($log->defers('Our team can show you the unit on Saturday.'));
    }
}
