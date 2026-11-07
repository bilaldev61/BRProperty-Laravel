<?php

namespace Tests\Feature\Admin;

use App\Models\Enquiry;
use App\Models\User;
use App\Support\EnquiryPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageEnquiriesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create();
    }

    private function enquiry(array $attributes = []): Enquiry
    {
        return Enquiry::create(array_merge([
            'type' => 'contact', 'name' => 'Pat Buyer', 'email' => 'pat@example.com',
            'message' => 'Just checking in.',
        ], $attributes));
    }

    public function test_opening_an_enquiry_marks_it_read(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)->get("/admin/enquiries/{$enquiry->id}")->assertOk();

        $this->assertNotNull($enquiry->fresh()->read_at);
    }

    public function test_reopening_an_enquiry_keeps_the_original_read_timestamp(): void
    {
        $enquiry = $this->enquiry(['read_at' => now()->subDays(3)]);
        $first = $enquiry->read_at;

        $this->actingAs($this->agent)->get("/admin/enquiries/{$enquiry->id}");

        $this->assertTrue($first->equalTo($enquiry->fresh()->read_at));
    }

    public function test_the_inbox_can_be_filtered_to_unread(): void
    {
        $this->enquiry(['name' => 'Unread Person']);
        $this->enquiry(['name' => 'Read Person', 'read_at' => now()]);

        $this->actingAs($this->agent)->get('/admin/enquiries?unread=1')
            ->assertOk()
            ->assertSee('Unread Person')
            ->assertDontSee('Read Person');
    }

    public function test_the_inbox_can_be_filtered_by_type(): void
    {
        $this->enquiry(['type' => 'appraisal', 'name' => 'Appraisal Person']);
        $this->enquiry(['type' => 'contact', 'name' => 'Contact Person']);

        $this->actingAs($this->agent)->get('/admin/enquiries?type=appraisal')
            ->assertOk()
            ->assertSee('Appraisal Person')
            ->assertDontSee('Contact Person');
    }

    public function test_an_enquiry_can_be_deleted(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)->delete("/admin/enquiries/{$enquiry->id}")->assertRedirect();

        $this->assertSame(0, Enquiry::count());
    }

    public function test_guests_cannot_read_enquiries(): void
    {
        $enquiry = $this->enquiry();

        $this->get("/admin/enquiries/{$enquiry->id}")->assertRedirect(route('admin.login'));
        $this->assertNull($enquiry->fresh()->read_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Workflow
    |--------------------------------------------------------------------------
    */

    public function test_a_new_enquiry_starts_as_new_work(): void
    {
        $enquiry = $this->enquiry();

        $this->assertSame(Enquiry::STATUS_NEW, $enquiry->status);
        $this->assertTrue($enquiry->isOpen());
    }

    public function test_the_status_can_be_moved_along(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)
            ->patch("/admin/enquiries/{$enquiry->id}", ['status' => Enquiry::STATUS_REPLIED])
            ->assertRedirect();

        $enquiry->refresh();
        $this->assertSame(Enquiry::STATUS_REPLIED, $enquiry->status);
        $this->assertFalse($enquiry->isOpen());
    }

    public function test_a_follow_up_date_can_be_set(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)->patch("/admin/enquiries/{$enquiry->id}", [
            'status'       => Enquiry::STATUS_IN_PROGRESS,
            'follow_up_at' => now()->addWeek()->toDateString(),
        ])->assertRedirect();

        $this->assertNotNull($enquiry->fresh()->follow_up_at);
    }

    public function test_closing_an_enquiry_clears_its_follow_up_date(): void
    {
        $enquiry = $this->enquiry([
            'status'       => Enquiry::STATUS_IN_PROGRESS,
            'follow_up_at' => now()->subDay(),
        ]);

        $this->actingAs($this->agent)
            ->patch("/admin/enquiries/{$enquiry->id}", ['status' => Enquiry::STATUS_CLOSED]);

        $this->assertNull($enquiry->fresh()->follow_up_at);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->agent)
            ->patch("/admin/enquiries/{$enquiry->id}", ['status' => 'archived'])
            ->assertSessionHasErrors('status');

        $this->assertSame(Enquiry::STATUS_NEW, $enquiry->fresh()->status);
    }

    public function test_guests_cannot_change_a_status(): void
    {
        $enquiry = $this->enquiry();

        $this->patch("/admin/enquiries/{$enquiry->id}", ['status' => Enquiry::STATUS_CLOSED])
            ->assertRedirect(route('admin.login'));

        $this->assertSame(Enquiry::STATUS_NEW, $enquiry->fresh()->status);
    }

    public function test_the_inbox_can_be_filtered_to_work_still_owing_a_reply(): void
    {
        $this->enquiry(['name' => 'Still Waiting']);
        $this->enquiry(['name' => 'All Done', 'status' => Enquiry::STATUS_REPLIED]);

        $this->actingAs($this->agent)->get('/admin/enquiries?view=needs_reply')
            ->assertOk()
            ->assertSee('Still Waiting')
            ->assertDontSee('All Done');
    }

    public function test_the_inbox_can_be_filtered_to_overdue_chases(): void
    {
        $this->enquiry(['name' => 'Chase Me', 'follow_up_at' => now()->subDays(2)]);
        $this->enquiry(['name' => 'Later Please', 'follow_up_at' => now()->addDays(5)]);
        $this->enquiry(['name' => 'Already Closed', 'status' => Enquiry::STATUS_CLOSED, 'follow_up_at' => now()->subDays(2)]);

        $this->actingAs($this->agent)->get('/admin/enquiries?view=overdue')
            ->assertOk()
            ->assertSee('Chase Me')
            ->assertDontSee('Later Please')
            ->assertDontSee('Already Closed');
    }

    /*
    |--------------------------------------------------------------------------
    | Priority rules
    |--------------------------------------------------------------------------
    */

    public function test_a_seller_moving_soon_is_hot(): void
    {
        $enquiry = $this->enquiry([
            'type' => 'appraisal',
            'details' => ['suburb' => 'Wapda Town', 'timeframe' => '1-3 months'],
        ]);

        $this->assertSame(EnquiryPriority::HOT, $enquiry->priority);
        $this->assertStringContainsString('1-3 months', $enquiry->priorityReason());
    }

    public function test_a_seller_only_researching_is_cold(): void
    {
        $enquiry = $this->enquiry([
            'type' => 'appraisal',
            'details' => ['timeframe' => 'Just researching'],
        ]);

        $this->assertSame(EnquiryPriority::COLD, $enquiry->priority);
    }

    public function test_a_buyer_who_left_a_phone_number_outranks_one_who_did_not(): void
    {
        $withPhone = $this->enquiry(['type' => 'property', 'phone' => '+92 300 1234567']);
        $without = $this->enquiry(['type' => 'property']);

        $this->assertSame(EnquiryPriority::WARM, $withPhone->priority);
        $this->assertSame(EnquiryPriority::COLD, $without->priority);
    }

    public function test_hot_enquiries_are_listed_before_cold_ones(): void
    {
        $this->enquiry(['name' => 'Cold Caller', 'type' => 'contact', 'created_at' => now()]);
        $this->enquiry([
            'name' => 'Hot Seller', 'type' => 'appraisal', 'created_at' => now()->subWeek(),
            'details' => ['timeframe' => 'ASAP'],
        ]);

        $response = $this->actingAs($this->agent)->get('/admin/enquiries')->assertOk();

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, 'Cold Caller'),
            strpos($body, 'Hot Seller'),
            'The hot seller lead should be listed above the older cold enquiry.'
        );
    }
}
