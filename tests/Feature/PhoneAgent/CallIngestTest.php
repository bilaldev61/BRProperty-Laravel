<?php

namespace Tests\Feature\PhoneAgent;

use App\Models\Appointment;
use App\Models\Call;
use App\Models\Enquiry;
use App\Support\EnquiryPriority;

class CallIngestTest extends PhoneAgentTestCase
{
    public function test_an_analysed_call_becomes_a_qualified_lead_with_next_actions(): void
    {
        $house = $this->listing();

        $response = $this->api('POST', 'calls', $this->retell())->assertOk();

        $lead = Enquiry::sole();
        $this->assertSame('Avery Thompson', $lead->name);
        $this->assertSame(Enquiry::SOURCE_VOICE, $lead->source);
        $this->assertSame('+15125550198', $lead->phone_normalized);
        $this->assertSame('buy', $lead->requirements['intent']);
        $this->assertSame(650000, $lead->requirements['budget_max']);
        $this->assertSame(['Zilker'], $lead->requirements['areas']);
        $this->assertSame('1_3_months', $lead->requirements['timeline']);

        // 10 reachable + 5 name + 10 buy + 18 timeline + 12 budget + 5 areas + 10 fits + 10 cash
        $this->assertSame(80, $lead->qualification['score']);
        $this->assertSame(EnquiryPriority::HOT, $lead->priority);
        $this->assertStringContainsString('Phone lead scored 80/100', $lead->priorityReason());
        $this->assertNotNull($lead->follow_up_at);

        $response->assertJsonPath('processed', true)
            ->assertJsonPath('lead.id', $lead->id)
            ->assertJsonPath('lead.is_new', true)
            ->assertJsonPath('qualification.grade', 'hot')
            ->assertJsonPath('matches.exact', true)
            ->assertJsonPath('matches.properties.0.slug', $house->slug);

        $actions = array_column($response->json('next_actions'), 'action');
        $this->assertContains('notify_agent', $actions);
        $this->assertContains('send_matches', $actions);

        $send = collect($response->json('next_actions'))->firstWhere('action', 'send_matches');
        $this->assertStringContainsString('Hi Avery Thompson', $send['message']);
        $this->assertStringContainsString(route('properties.show', $house), $send['message']);

        $call = Call::sole();
        $this->assertSame($lead->id, $call->enquiry_id);
        $this->assertSame(180, $call->duration_seconds);
        $this->assertSame(42, $call->cost_cents);
        $this->assertSame('Positive', $call->sentiment);
        $this->assertNotNull($call->processed_at);
    }

    public function test_a_repeat_delivery_does_not_duplicate_anything(): void
    {
        $this->listing();
        $payload = $this->retell();

        $first = $this->api('POST', 'calls', $payload)->assertOk()->json();
        $second = $this->api('POST', 'calls', $payload)->assertOk();

        $second->assertJsonPath('already_processed', true)
            ->assertJsonPath('lead.id', $first['lead']['id']);
        $this->assertSame(1, Enquiry::count());
        $this->assertSame(1, Call::count());
    }

    public function test_call_ended_is_stored_and_call_analyzed_then_qualifies_the_same_row(): void
    {
        $payload = $this->retell();
        $ended = $payload;
        $ended['event'] = 'call_ended';
        unset($ended['call']['call_analysis']);

        $this->api('POST', 'calls', $ended)->assertOk()->assertJsonPath('processed', false);
        $this->assertSame(0, Enquiry::count());
        $this->assertNotNull(Call::sole()->transcript);

        $this->api('POST', 'calls', $payload)->assertOk()->assertJsonPath('processed', true);
        $this->assertSame(1, Call::count());
        $this->assertSame(1, Enquiry::count());
    }

    public function test_a_bare_call_object_is_accepted_too(): void
    {
        $this->api('POST', 'calls', $this->retell()['call'])->assertOk()->assertJsonPath('processed', true);
    }

    public function test_a_repeat_caller_joins_their_open_lead(): void
    {
        $this->api('POST', 'calls', $this->retell(['budget' => '$600,000', 'payment' => '']))->assertOk();

        // Same person, written locally this time, now with cash ready.
        $second = $this->api('POST', 'calls', $this->retell(
            ['name' => '', 'budget' => '', 'payment' => 'cash'],
            ['from_number' => '512 555 0198'],
        ))->assertOk();

        $second->assertJsonPath('lead.is_new', false);
        $lead = Enquiry::sole();
        $this->assertSame(2, $lead->calls()->count());
        $this->assertSame('Avery Thompson', $lead->name);               // kept from call one
        $this->assertSame(600000, $lead->requirements['budget_max']); // kept from call one
        $this->assertSame('cash', $lead->requirements['payment']);     // learned on call two
    }

    public function test_a_closed_lead_is_not_reopened_by_a_new_call(): void
    {
        $this->api('POST', 'calls', $this->retell())->assertOk();
        Enquiry::sole()->update(['status' => Enquiry::STATUS_CLOSED]);

        $this->api('POST', 'calls', $this->retell())->assertOk()->assertJsonPath('lead.is_new', true);

        $this->assertSame(2, Enquiry::count());
    }

    public function test_voicemail_is_not_qualified_and_asks_for_a_call_back(): void
    {
        $response = $this->api('POST', 'calls', $this->retell([], ['call_analysis' => ['in_voicemail' => true]]))->assertOk();

        $response->assertJsonPath('qualification.score', 0)
            ->assertJsonPath('qualification.grade', 'cold')
            ->assertJsonPath('next_actions.0.action', 'call_back');
    }

    public function test_a_seller_becomes_an_appraisal_lead_with_a_valuation_to_book(): void
    {
        $response = $this->api('POST', 'calls', $this->retell([
            'intent' => 'I want to sell my house',
            'timeline' => 'asap',
            'selling_property' => 'Craftsman home in Hyde Park',
            'budget' => '',
            'areas' => '',
        ]))->assertOk();

        $lead = Enquiry::sole();
        $this->assertSame('appraisal', $lead->type);
        $this->assertSame('Craftsman home in Hyde Park', $lead->detail('address'));
        $response->assertJsonPath('qualification.grade', 'hot')
            ->assertJsonPath('matches.properties', []);
        $this->assertContains('book_valuation', array_column($response->json('next_actions'), 'action'));
    }

    public function test_nothing_fitting_means_close_matches_and_an_agent_follow_up(): void
    {
        $this->listing(['price' => 780000]); // over a $650,000 budget, inside the looser stretch

        $response = $this->api('POST', 'calls', $this->retell())->assertOk();

        $response->assertJsonPath('matches.exact', false)
            ->assertJsonPath('matches.properties.0.close', true);
        $this->assertStringContainsString('Nothing matches exactly', collect($response->json('next_actions'))->firstWhere('action', 'send_matches')['message']);
    }

    public function test_a_viewing_booked_during_the_call_is_linked_and_confirmed(): void
    {
        $this->travelTo(now('Asia/Dubai')->next('Monday')->setTime(8, 0));
        $house = $this->listing();
        $payload = $this->retell();
        $slot = now('Asia/Dubai')->setTime(15, 0)->toIso8601String();

        $this->api('POST', 'appointments', [
            'call' => ['call_id' => $payload['call']['call_id'], 'from_number' => '+15125550198', 'direction' => 'inbound'],
            'args' => ['starts_at' => $slot, 'name' => 'Avery Thompson', 'property_slug' => $house->slug],
        ])->assertOk()->assertJsonPath('booked', true);

        $response = $this->api('POST', 'calls', $payload)->assertOk();

        $appointment = Appointment::sole();
        $this->assertSame(Enquiry::sole()->id, $appointment->enquiry_id);
        $this->assertSame(Call::sole()->id, $appointment->call_id);
        $response->assertJsonPath('appointment.status', 'requested');

        $actions = array_column($response->json('next_actions'), 'action');
        $this->assertContains('confirm_appointment', $actions);
        $this->assertContains('approve_appointment', $actions);
        $this->assertContains('booked a viewing', array_column(Enquiry::sole()->qualification['reasons'], 0));
    }

    public function test_an_outbound_call_uses_the_number_that_was_called(): void
    {
        $this->api('POST', 'calls', $this->retell([], ['direction' => 'outbound', 'from_number' => '+15125550142', 'to_number' => '+17375550163']))->assertOk();

        $this->assertSame('+17375550163', Enquiry::sole()->phone_normalized);
    }

    public function test_a_payload_without_a_call_id_is_rejected(): void
    {
        $this->api('POST', 'calls', ['event' => 'call_analyzed', 'call' => ['from_number' => '+15125550198']])->assertStatus(422);
    }
}
