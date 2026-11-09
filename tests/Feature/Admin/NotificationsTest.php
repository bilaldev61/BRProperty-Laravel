<?php

namespace Tests\Feature\Admin;

use App\Models\ChatConversation;
use App\Models\ChatUnansweredQuestion;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\AdminNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function feed(User $user): AdminNotifications
    {
        return new AdminNotifications($user);
    }

    private function enquiry(string $name, array $attributes = []): Enquiry
    {
        return Enquiry::create(array_merge(
            ['type' => 'contact', 'name' => $name, 'email' => strtolower($name) . '@example.com'],
            $attributes,
        ));
    }

    private function question(string $text, ?string $askedAt = null): ChatUnansweredQuestion
    {
        $conversation = ChatConversation::create(['token' => uniqid('bell', true), 'visitor_messages' => 1]);

        $question = $conversation->unansweredQuestions()->create([
            'topic' => 'possession',
            'question' => $text,
        ]);

        if ($askedAt) {
            $question->forceFill(['created_at' => $askedAt])->save();
        }

        return $question;
    }

    public function test_the_bell_counts_enquiries_still_owed_a_reply(): void
    {
        $this->enquiry('Nadia');
        $this->enquiry('Omar');

        $group = $this->feed(User::factory()->agent()->create())->groups()[0];

        $this->assertSame('enquiries', $group['key']);
        $this->assertSame(2, $group['count']);
        $this->assertEqualsCanonicalizing(['Nadia', 'Omar'], array_column($group['items'], 'title'));
    }

    public function test_a_closed_or_replied_enquiry_drops_off_the_bell(): void
    {
        $this->enquiry('Closed', ['status' => Enquiry::STATUS_CLOSED]);
        $this->enquiry('Replied', ['status' => Enquiry::STATUS_REPLIED]);
        $this->enquiry('Open');

        $group = $this->feed(User::factory()->agent()->create())->groups()[0];

        // Read but unanswered still counts: the bell tracks what is owed, not
        // what has been glanced at - the same rule as the dashboard tile.
        $this->assertSame(1, $group['count']);
        $this->assertSame(['Open'], array_column($group['items'], 'title'));
    }

    public function test_questions_the_assistant_could_not_answer_show_for_a_week(): void
    {
        $this->question('Is the price negotiable?');
        $this->question('Can I have the NOC copy?', now()->subDays(10)->toDateTimeString());

        $groups = collect($this->feed(User::factory()->agent()->create())->groups());
        $questions = $groups->firstWhere('key', 'questions');

        $this->assertSame(1, $questions['count']);
        $this->assertStringContainsString('price negotiable', $questions['items'][0]['title']);
        $this->assertSame('Closing & possession', $questions['items'][0]['meta']);
    }

    public function test_the_total_adds_up_across_groups(): void
    {
        $this->enquiry('Nadia');
        $this->question('Is the price negotiable?');

        $this->assertSame(2, $this->feed(User::factory()->owner()->create())->total());
    }

    public function test_an_empty_inbox_keeps_the_bell_but_shows_no_number(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Waiting on you')
            ->assertSee('Nothing waiting.')
            ->assertDontSee('Notifications, 1');
    }

    public function test_an_editor_never_gets_a_bell(): void
    {
        $this->enquiry('Nadia');
        $this->question('Is the price negotiable?');

        // An editor keeps listings tidy and has no business reading either.
        $this->assertSame([], $this->feed(User::factory()->editor()->create())->groups());

        $this->actingAs(User::factory()->editor()->create())->get('/admin')
            ->assertOk()
            ->assertDontSee('Waiting on you')
            ->assertDontSee('Nadia');
    }

    public function test_the_header_bell_announces_the_work_and_links_to_the_inbox(): void
    {
        $enquiry = $this->enquiry('Nadia');
        $this->question('Is the price negotiable?');

        $this->actingAs(User::factory()->agent()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Notifications, 2 waiting')
            ->assertSee('Enquiries to answer')
            ->assertSee('Assistant could not answer')
            ->assertSee(route('admin.enquiries.show', $enquiry), false)
            ->assertSee(route('admin.assistant.questions'), false);
    }

    public function test_the_bell_follows_the_reader_onto_other_screens(): void
    {
        $this->enquiry('Nadia');

        $this->actingAs(User::factory()->agent()->create())->get('/admin/enquiries')
            ->assertOk()
            ->assertSee('Waiting on you');
    }

    public function test_the_bell_does_not_repeat_the_screen_being_read(): void
    {
        $this->enquiry('Nadia');

        $skipped = new AdminNotifications(User::factory()->agent()->create(), ['enquiries']);
        $this->assertSame(['questions'], array_column($skipped->groups(), 'key'));

        // On the inbox itself there is nothing to announce about enquiries, so
        // the bell carries no number and the filtered list stays the only place
        // a name appears.
        $this->actingAs(User::factory()->agent()->create())->get('/admin/enquiries?unread=1')
            ->assertOk()
            ->assertDontSee('Notifications, 1 waiting');
    }

    public function test_the_dashboard_no_longer_carries_the_new_property_button(): void
    {
        $owner = User::factory()->owner()->create();

        // Adding a listing belongs to the listings screen; the dashboard header
        // now holds the bell instead.
        $this->actingAs($owner)->get('/admin')
            ->assertOk()
            ->assertDontSee('+ New property');

        $this->actingAs($owner)->get('/admin/properties')
            ->assertOk()
            ->assertSee('+ New property');
    }
}
