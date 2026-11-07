<?php

namespace Tests\Feature\PhoneAgent;

use App\Models\Appointment;
use App\Models\Enquiry;
use App\Services\Leads\Requirements;
use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;

class PhoneAgentApiTest extends PhoneAgentTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    */

    public function test_the_api_is_closed_until_a_token_is_configured(): void
    {
        config(['integrations.automation_token' => null]);

        $this->withToken('anything')->getJson('/api/v1/properties/search')->assertStatus(503);
    }

    public function test_a_wrong_or_missing_token_is_refused(): void
    {
        $this->getJson('/api/v1/properties/search')->assertUnauthorized();
        $this->withToken('wrong')->getJson('/api/v1/properties/search')->assertUnauthorized();
    }

    public function test_the_token_works_as_bearer_header_or_query(): void
    {
        $this->withToken(self::TOKEN)->getJson('/api/v1/properties/search')->assertOk();
        $this->withHeader('X-Api-Key', self::TOKEN)->getJson('/api/v1/properties/search')->assertOk();
        $this->getJson('/api/v1/properties/search?token=' . self::TOKEN)->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Listings during the call
    |--------------------------------------------------------------------------
    */

    public function test_search_works_from_n8n_and_from_a_retell_function(): void
    {
        $house = $this->listing();
        $this->listing(['title' => 'JVC plot, ready to build', 'type' => 'land', 'suburb' => 'Jumeirah Village Circle', 'price' => 385000]);
        $this->listing(['title' => 'Hidden', 'is_published' => false]);

        $this->api('GET', 'properties/search?area=Zilker&max_price=700000')->assertOk()
            ->assertJsonPath('total_matches', 1)
            ->assertJsonPath('properties.0.slug', $house->slug)
            ->assertJsonPath('properties.0.price_pkr', 625000);

        // Retell custom functions post {name, call, args}.
        $this->api('POST', 'properties/search', ['name' => 'search_properties', 'call' => ['call_id' => 'c1'], 'args' => ['type' => 'land']])
            ->assertOk()
            ->assertJsonPath('total_matches', 1)
            ->assertJsonPath('properties.0.title', 'JVC plot, ready to build');

        $this->api('GET', 'properties/search?area=Nowhere')->assertOk()
            ->assertJsonPath('total_matches', 0)
            ->assertJsonPath('note', 'No listing matches those details right now.');
    }

    public function test_details_give_the_facts_and_never_an_unpublished_listing(): void
    {
        $house = $this->listing(['features' => ['Corner plot']]);
        $hidden = $this->listing(['title' => 'Hidden', 'is_published' => false]);

        $this->api('GET', 'properties/details/' . $house->slug)->assertOk()
            ->assertJsonPath('property.bathrooms', 3)
            ->assertJsonPath('property.features.0', 'Corner plot');

        $this->api('POST', 'properties/details', ['args' => ['slug' => $house->slug]])->assertOk()->assertJsonPath('found', true);
        $this->api('GET', 'properties/details/' . $hidden->slug)->assertNotFound();
    }

    public function test_a_returning_caller_is_recognised_with_retell_variables(): void
    {
        $this->api('POST', 'calls', $this->retell())->assertOk();

        $this->api('POST', 'leads/lookup', ['call' => ['from_number' => '+1 512 555 0198', 'direction' => 'inbound']])->assertOk()
            ->assertJsonPath('known', true)
            ->assertJsonPath('lead.name', 'Avery Thompson')
            ->assertJsonPath('dynamic_variables.caller_known', 'yes')
            ->assertJsonPath('dynamic_variables.caller_name', 'Avery Thompson');

        $this->api('GET', 'leads/lookup?phone=7375550163')->assertOk()
            ->assertJsonPath('known', false)
            ->assertJsonPath('dynamic_variables.caller_known', 'no');
    }

    public function test_matches_for_a_lead(): void
    {
        $house = $this->listing();
        $this->api('POST', 'calls', $this->retell())->assertOk();

        $this->api('GET', 'leads/' . Enquiry::sole()->id . '/matches')->assertOk()
            ->assertJsonPath('properties.0.slug', $house->slug)
            ->assertJsonPath('properties.0.fit', 'within budget, bedrooms as asked');
    }

    /*
    |--------------------------------------------------------------------------
    | Viewings
    |--------------------------------------------------------------------------
    */

    private function mondayMorning(): void
    {
        // 8am Monday in Dubai; hours start at 10, with 3 hours' notice.
        $this->travelTo(now('Asia/Dubai')->next('Monday')->setTime(8, 0));
    }

    public function test_availability_follows_hours_notice_and_timezone(): void
    {
        $this->mondayMorning();

        $slots = $this->api('GET', 'appointments/availability?days=7&limit=200')->assertOk()->json('slots');

        $this->assertStringStartsWith(now('Asia/Dubai')->format('Y-m-d') . 'T11:00:00', $slots[0]['starts_at']); // 3h notice
        $this->assertStringStartsWith('Monday', $slots[0]['label']);

        $days = collect($slots)->map(fn($s) => substr($s['label'], 0, strpos($s['label'], ' ')))->unique()->values()->all();
        $this->assertNotContains('Sunday', $days);

        // Friday midday break: nothing between 12:30 and 14:30.
        $friday = collect($slots)->filter(fn($s) => str_starts_with($s['label'], 'Friday'))->pluck('starts_at');
        $this->assertFalse($friday->contains(fn($t) => str_contains($t, 'T13:00')));
        $this->assertTrue($friday->contains(fn($t) => str_contains($t, 'T14:30')));
    }

    public function test_booking_a_free_slot_from_a_live_call_uses_the_callers_number(): void
    {
        $this->mondayMorning();
        $slot = now('Asia/Dubai')->setTime(15, 0)->toIso8601String();

        $this->api('POST', 'appointments', [
            'name' => 'book_viewing',
            'call' => ['call_id' => 'call_live_1', 'from_number' => '+15125550198', 'direction' => 'inbound'],
            'args' => ['starts_at' => $slot, 'name' => 'Avery Thompson'],
        ])->assertOk()
            ->assertJsonPath('booked', true)
            ->assertJsonPath('appointment.status', 'requested')
            ->assertJsonPath('say', fn($say) => str_contains($say, 'will confirm'));

        $appointment = Appointment::sole();
        $this->assertSame('+15125550198', $appointment->enquiry->phone_normalized);
        $this->assertSame('Avery Thompson', $appointment->enquiry->name);
        $this->assertSame('call_live_1', $appointment->call->provider_call_id);

        // The slot is gone now.
        $this->assertNotContains($slot, array_column($this->api('GET', 'appointments/availability?days=1&limit=50')->json('slots'), 'starts_at'));
    }

    public function test_a_taken_or_closed_time_offers_alternatives_instead(): void
    {
        $this->mondayMorning();
        $slot = now('Asia/Dubai')->setTime(15, 0)->toIso8601String();
        $this->api('POST', 'appointments', ['starts_at' => $slot, 'phone' => '5125550198'])->assertJsonPath('booked', true);

        $this->api('POST', 'appointments', ['starts_at' => $slot, 'phone' => '7375550163'])->assertOk()
            ->assertJsonPath('booked', false)
            ->assertJsonPath('reason', 'That time is not available.')
            ->assertJsonCount(3, 'alternatives');

        $sunday = now('Asia/Dubai')->next('Sunday')->setTime(12, 0)->toIso8601String();
        $this->api('POST', 'appointments', ['starts_at' => $sunday, 'phone' => '7375550163'])->assertJsonPath('booked', false);

        $this->api('POST', 'appointments', ['starts_at' => 'whenever suits', 'phone' => '7375550163'])
            ->assertJsonPath('booked', false)
            ->assertJsonPath('reason', 'Could not understand that time.');

        $this->assertSame(1, Appointment::count());
    }

    public function test_viewings_can_be_auto_confirmed(): void
    {
        $this->mondayMorning();
        config(['agent.appointments.auto_confirm' => true]);

        $this->api('POST', 'appointments', ['starts_at' => now('Asia/Dubai')->setTime(16, 0)->toIso8601String(), 'phone' => '5125550198'])
            ->assertJsonPath('appointment.status', 'confirmed')
            ->assertJsonPath('say', fn($say) => str_starts_with($say, "You're booked"));
    }

    /*
    |--------------------------------------------------------------------------
    | Reading what the voice agent extracted
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{0: array<string, mixed>, 1: string, 2: mixed}> */
    public static function spokenRequirements(): array
    {
        return [
            'single figure'       => [['budget' => '$650,000'], 'budget_max', 650000],
            'single figure floor' => [['budget' => '$650,000'], 'budget_min', null],
            'around'              => [['budget' => 'around $600,000'], 'budget_max', 690000],
            'range'               => [['budget' => 'between $400,000 and $600,000'], 'budget_min', 400000],
            'range max'           => [['budget' => '$400K - $600K'], 'budget_max', 600000],
            'k ceiling'           => [['budget' => 'under $800K'], 'budget_max', 800000],
            'numbers'             => [['budget_min' => 400000, 'budget_max' => 650000], 'budget_max', 650000],
            'half acre'           => [['size' => 'half an acre'], 'land_marla_min', 21780],
            'square feet'         => [['size' => '2,400 sq ft'], 'land_marla_min', 2400],
            'townhome'            => [['property_type' => 'townhome'], 'property_types', ['townhouse']],
            'condo or lot'        => [['property_type' => 'condo or lot'], 'property_types', ['apartment', 'land']],
            'areas list'          => [['areas' => 'Zilker, Mueller and Hyde Park'], 'areas', ['Zilker', 'Mueller', 'Hyde Park']],
            'asap'                => [['timeline' => 'urgently, this month'], 'timeline', 'asap'],
            'next month'          => [['timeline' => 'next month'], 'timeline', '1_3_months'],
            'browsing'            => [['timeline' => 'just looking'], 'timeline', 'browsing'],
            'loan'                => [['payment' => 'home mortgage'], 'payment', 'loan'],
            'seller'              => [['intent' => 'wants to sell our house'], 'intent', 'sell'],
            'spoken yes'          => [['appointment_requested' => 'yes'], 'appointment_requested', true],
            'placeholder name'    => [['name' => 'unknown'], 'name', null],
        ];
    }

    #[DataProvider('spokenRequirements')]
    public function test_spoken_requirements_are_read_into_clean_fields(array $analysis, string $field, mixed $expected): void
    {
        $this->assertSame($expected, Requirements::fromAnalysis($analysis)[$field]);
    }

    /** @return array<string, array{0: string, 1: string|null}> */
    public static function phoneNumbers(): array
    {
        return [
            'local us'          => ['512-234-5678', '+15122345678'],
            'us with parens'    => ['(512) 234-5678', '+15122345678'],
            'us with country'   => ['1 512 234 5678', '+15122345678'],
            'us trunk zero'     => ['0 512 234 5678', '+15122345678'],
            'already plus one'  => ['+1-512-234-5678', '+15122345678'],
            'foreign'           => ['+44 20 7123 4567', '+442071234567'],
            'double zero'       => ['0044 20 7123 4567', '+442071234567'],
            'too short'         => ['12345', null],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_phone_numbers_normalise_to_one_form(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }
}
