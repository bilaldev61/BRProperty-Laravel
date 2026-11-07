<?php

namespace Tests\Feature\PhoneAgent;

use App\Models\Appointment;
use App\Models\Call;
use App\Models\Enquiry;
use App\Models\User;

class PhoneAgentAdminTest extends PhoneAgentTestCase
{
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create();
    }

    public function test_guests_cannot_see_calls_or_appointments(): void
    {
        $this->get('/admin/calls')->assertRedirect('/admin/login');
        $this->get('/admin/appointments')->assertRedirect('/admin/login');
    }

    public function test_the_calls_page_lists_calls_with_their_lead(): void
    {
        $this->api('POST', 'calls', $this->retell())->assertOk();
        $call = Call::sole();

        $this->actingAs($this->agent)->get('/admin/calls')
            ->assertOk()
            ->assertSee('Avery Thompson')
            ->assertSee('Caller wants a 4 bedroom house')
            ->assertSee('3:00');

        $this->actingAs($this->agent)->get('/admin/calls/' . $call->id)
            ->assertOk()
            ->assertSee('I want a house in Zilker')   // transcript
            ->assertSee('$0.42')                     // cost
            ->assertSee('Open the lead');
    }

    public function test_a_phone_lead_shows_its_qualification_requirements_and_matches(): void
    {
        $house = $this->listing();
        $this->api('POST', 'calls', $this->retell())->assertOk();
        $lead = Enquiry::sole();

        $this->actingAs($this->agent)->get('/admin/enquiries')
            ->assertOk()
            ->assertSee('Phone call');

        $this->actingAs($this->agent)->get('/admin/enquiries/' . $lead->id)
            ->assertOk()
            ->assertSee('Lead qualification')
            ->assertSee('80')
            ->assertSee('Wants to move within 1-3 months')
            ->assertSee('What they are looking for')
            ->assertSee('$650,000')
            ->assertSee($house->title)
            ->assertSee('within budget')
            ->assertSee('Phone agent');
    }

    public function test_viewings_can_be_confirmed_from_the_appointments_page(): void
    {
        $this->travelTo(now('Asia/Dubai')->next('Monday')->setTime(8, 0));
        $this->api('POST', 'appointments', ['starts_at' => now('Asia/Dubai')->setTime(15, 0)->toIso8601String(), 'phone' => '5125550198', 'name' => 'Avery Thompson'])
            ->assertJsonPath('booked', true);
        $appointment = Appointment::sole();

        $this->actingAs($this->agent)->get('/admin/appointments')
            ->assertOk()
            ->assertSee('Avery Thompson')
            ->assertSee('1 viewing waiting for you to confirm');

        $this->actingAs($this->agent)->patch('/admin/appointments/' . $appointment->id, ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(Appointment::CONFIRMED, $appointment->fresh()->status);
    }
}
