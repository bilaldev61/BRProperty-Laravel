<?php

namespace Tests\Feature;

use App\Jobs\SendEnquiryConfirmation;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Support\Ai\AiConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

class EnquiryConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function property(): Property
    {
        return Property::create([
            'title' => '10 Marla House in DHA Phase 6', 'type' => 'house',
            'status' => 'for_sale', 'is_published' => true,
            'address' => 'House 42, Block J', 'suburb' => 'DHA Phase 6',
            'state' => 'Punjab', 'postcode' => '54000',
            'price' => 32500000, 'bedrooms' => 4, 'bathrooms' => 3, 'carspaces' => 2,
            'land_size' => 10, 'inspection_times' => 'Saturday 11am - 1pm',
            'description' => 'A corner house.',
        ]);
    }

    private function submit(array $fields = [])
    {
        return $this->postJson('/enquiries', array_merge([
            'type' => 'contact', 'name' => 'Ayesha Khan', 'email' => 'ayesha@example.com',
            'phone' => '0300 1234567', 'message' => 'Hello',
        ], $fields));
    }

    public function test_a_property_enquiry_is_confirmed_by_email_with_the_listing(): void
    {
        Mail::fake();
        $property = $this->property();

        $this->submit(['type' => 'property', 'property_id' => $property->id])->assertCreated();

        Mail::assertSent(EnquiryReceived::class, function (EnquiryReceived $mail) use ($property) {
            $html = $mail->render();

            return $mail->hasTo('ayesha@example.com')
                && $mail->hasReplyTo(config('agent.email'))
                && $mail->hasSubject('Your enquiry about 10 Marla House in DHA Phase 6')
                && str_contains($html, 'Thanks, Ayesha Khan')
                && str_contains($html, $property->priceDisplay())
                && str_contains($html, 'Saturday 11am - 1pm')
                && str_contains($html, route('properties.show', $property))
                && str_contains($html, '0300 1234567');
        });

        $this->assertNotNull(Enquiry::sole()->confirmation_sent_at);
    }

    public function test_an_appraisal_request_recaps_the_property_details(): void
    {
        Mail::fake();

        $this->submit(['type' => 'appraisal', 'suburb' => 'Wapda Town', 'timeframe' => '1-3 months'])->assertCreated();

        Mail::assertSent(EnquiryReceived::class, fn (EnquiryReceived $mail) => $mail->hasSubject('Your free appraisal request - '.config('agent.agency'))
            && str_contains($mail->render(), 'Wapda Town')
            && str_contains($mail->render(), '1-3 months'));
    }

    public function test_the_visitors_own_message_is_never_echoed(): void
    {
        Mail::fake();

        // Anyone can type anyone's address into a public form; repeating their
        // text would turn the form into a way to mail strangers.
        $this->submit(['message' => 'Visit http://spam.example for cheap pills'])->assertCreated();

        Mail::assertSent(EnquiryReceived::class, fn (EnquiryReceived $mail) => ! str_contains($mail->render(), 'spam.example'));
    }

    public function test_one_address_cannot_be_flooded(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->submit(['email' => 'victim@example.com'])->assertCreated();
        }

        Mail::assertSentCount(3);
        $this->assertSame(2, Enquiry::whereNull('confirmation_sent_at')->count());
    }

    public function test_a_broken_mail_server_does_not_lose_the_enquiry(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));

        $this->submit()->assertCreated();

        $this->assertSame('Ayesha Khan', Enquiry::sole()->name);
        $this->assertNull(Enquiry::sole()->confirmation_sent_at);
    }

    public function test_nothing_is_marked_sent_while_mail_only_goes_to_the_log(): void
    {
        config(['mail.default' => 'log']);
        $agent = User::factory()->create();

        $this->submit()->assertCreated();
        $enquiry = Enquiry::sole();
        $this->assertNull($enquiry->confirmation_sent_at);

        $this->actingAs($agent)->get("/admin/enquiries/{$enquiry->id}")
            ->assertSee('email is not set up on this server yet');
        $this->actingAs($agent)->post("/admin/enquiries/{$enquiry->id}/confirmation")
            ->assertSessionHas('status', 'Email is not set up on this server yet, so nothing was sent.');
        $this->assertNull($enquiry->fresh()->confirmation_sent_at);
    }

    public function test_confirmations_can_be_switched_off(): void
    {
        Mail::fake();
        config(['mail.enquiry_confirmation' => false]);

        $this->submit()->assertCreated();

        Mail::assertNothingSent();
    }

    public function test_a_chat_lead_with_an_email_is_confirmed_and_one_without_is_not(): void
    {
        Mail::fake();
        $property = $this->property();

        $this->app->instance(AiConnector::class, (new FakeAiConnector())->callsTool('save_lead', [
            'name' => 'Ali Raza', 'phone' => '03001234567', 'email' => 'ali@example.com', 'summary' => 'Wants a call.',
        ]));
        $this->postJson('/assistant', ['message' => 'Call me', 'property' => $property->slug])->assertOk();

        Mail::assertSent(EnquiryReceived::class, fn ($mail) => $mail->hasTo('ali@example.com'));

        $this->flushSession();
        $this->app->instance(AiConnector::class, (new FakeAiConnector())->callsTool('save_lead', [
            'name' => 'Bilal', 'phone' => '03007654321', 'summary' => 'Wants a call.',
        ]));
        $this->postJson('/assistant', ['message' => 'Call me', 'property' => $property->slug])->assertOk();

        Mail::assertSentCount(1);
        $this->assertSame(2, Enquiry::count());
    }

    public function test_the_agent_sees_whether_it_was_sent_and_can_resend(): void
    {
        Mail::fake();
        $agent = User::factory()->create();
        $enquiry = Enquiry::create(['type' => 'contact', 'name' => 'Ayesha', 'email' => 'ayesha@example.com']);

        $this->actingAs($agent)->get("/admin/enquiries/{$enquiry->id}")
            ->assertOk()
            ->assertSee('Not sent')
            ->assertSee('Send confirmation email');

        $this->actingAs($agent)->post("/admin/enquiries/{$enquiry->id}/confirmation")
            ->assertRedirect()
            ->assertSessionHas('status', 'Confirmation email sent to ayesha@example.com.');

        Mail::assertSent(EnquiryReceived::class);
        $this->actingAs($agent)->get("/admin/enquiries/{$enquiry->id}")
            ->assertSee('Resend that email');
    }

    public function test_an_enquiry_without_email_offers_no_resend(): void
    {
        $agent = User::factory()->create();
        $enquiry = Enquiry::create(['type' => 'contact', 'name' => 'Bilal', 'phone' => '03007654321']);

        $this->actingAs($agent)->get("/admin/enquiries/{$enquiry->id}")
            ->assertOk()
            ->assertSee('Not sent - no email address')
            ->assertDontSee('confirmation email');

        $this->actingAs($agent)->post("/admin/enquiries/{$enquiry->id}/confirmation")->assertStatus(422);
    }

    public function test_guests_cannot_trigger_a_resend(): void
    {
        Mail::fake();
        $enquiry = Enquiry::create(['type' => 'contact', 'name' => 'Ayesha', 'email' => 'ayesha@example.com']);

        $this->post("/admin/enquiries/{$enquiry->id}/confirmation")->assertRedirect('/admin/login');

        Mail::assertNothingSent();
    }

    public function test_the_job_sends_once_per_enquiry_unless_resent_on_purpose(): void
    {
        Mail::fake();
        $enquiry = Enquiry::create(['type' => 'contact', 'name' => 'Ayesha', 'email' => 'ayesha@example.com']);

        $this->assertTrue((new SendEnquiryConfirmation($enquiry->id))->handle());
        $this->assertFalse((new SendEnquiryConfirmation($enquiry->id))->handle());
        $this->assertTrue((new SendEnquiryConfirmation($enquiry->id, resend: true))->handle());

        Mail::assertSentCount(2);
    }

    public function test_the_job_skips_an_enquiry_that_no_longer_exists(): void
    {
        Mail::fake();

        $this->assertFalse((new SendEnquiryConfirmation(999))->handle());
        Mail::assertNothingSent();
    }
}
