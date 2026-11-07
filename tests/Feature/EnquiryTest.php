<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_property_enquiry_is_stored_against_the_listing(): void
    {
        $this->seed(\Database\Seeders\PropertySeeder::class);
        $property = Property::forSale()->first();

        $this->postJson('/enquiries', [
            'type'        => 'property',
            'property_id' => $property->id,
            'name'        => 'Jane Buyer',
            'email'       => 'jane@example.com',
            'phone'       => '0400 000 000',
            'message'     => 'Is the deck north facing?',
        ])->assertCreated()->assertJsonStructure(['message']);

        $enquiry = Enquiry::sole();
        $this->assertSame('property', $enquiry->type);
        $this->assertSame($property->id, $enquiry->property_id);
        $this->assertNull($enquiry->read_at);
    }

    public function test_an_appraisal_request_captures_the_extra_fields(): void
    {
        $this->postJson('/enquiries', [
            'type'          => 'appraisal',
            'name'          => 'Sam Seller',
            'email'         => 'sam@example.com',
            'address'       => 'House 24, Block C',
            'suburb'        => 'Johar Town',
            'property_type' => 'house',
            'bedrooms'      => 4,
            'timeframe'     => '3-6 months',
        ])->assertCreated();

        $details = Enquiry::sole()->details;

        $this->assertSame('House 24, Block C', $details['address']);
        $this->assertSame(4, (int) $details['bedrooms']);
        $this->assertSame('3-6 months', $details['timeframe']);
    }

    public function test_a_contact_enquiry_does_not_store_appraisal_details(): void
    {
        $this->postJson('/enquiries', [
            'type'    => 'contact',
            'name'    => 'Pat',
            'email'   => 'pat@example.com',
            'message' => 'Do you do rentals?',
            'address' => 'ignored',
        ])->assertCreated();

        $this->assertNull(Enquiry::sole()->details);
    }

    public function test_validation_errors_are_returned_per_field(): void
    {
        $this->postJson('/enquiries', ['type' => 'contact', 'name' => '', 'email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);

        $this->assertSame(0, Enquiry::count());
    }

    public function test_an_unknown_enquiry_type_is_rejected(): void
    {
        $this->postJson('/enquiries', ['type' => 'spam', 'name' => 'A', 'email' => 'a@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_a_malformed_phone_number_is_rejected_by_the_backend(): void
    {
        // The Vue form is novalidate, so the server is the source of truth:
        // junk that cannot reduce to a real number must never be stored.
        foreach (['call me please', 'aaaaaaa', '12', 'not-a-number', '+abc def'] as $junk) {
            $this->postJson('/enquiries', [
                'type'  => 'contact',
                'name'  => 'Pat',
                'email' => 'pat@example.com',
                'phone' => $junk,
            ])->assertStatus(422)->assertJsonValidationErrors('phone');
        }

        $this->assertSame(0, Enquiry::count());
    }

    public function test_real_phone_formats_are_accepted(): void
    {
        foreach (['(512) 555-0142', '512-555-0142', '+1 512 555 0142', '0400 000 000'] as $good) {
            $this->postJson('/enquiries', [
                'type'  => 'contact',
                'name'  => 'Pat',
                'email' => 'pat@example.com',
                'phone' => $good,
            ])->assertCreated();
        }
    }

    public function test_an_oversized_message_is_rejected_and_nothing_is_stored(): void
    {
        $this->postJson('/enquiries', [
            'type'    => 'contact',
            'name'    => 'Pat',
            'email'   => 'pat@example.com',
            'message' => str_repeat('a', 4001),
        ])->assertStatus(422)->assertJsonValidationErrors('message');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_the_honeypot_field_blocks_the_submission(): void
    {
        $this->postJson('/enquiries', [
            'type'    => 'contact',
            'name'    => 'Spam Bot',
            'email'   => 'bot@example.com',
            'company' => 'Acme Spam Co',
        ])->assertStatus(422);

        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_browser_form_post_redirects_back_with_a_status_message(): void
    {
        $this->from('/contact')
            ->post('/enquiries', ['type' => 'contact', 'name' => 'Pat', 'email' => 'pat@example.com'])
            ->assertRedirect('/contact')
            ->assertSessionHas('status');
    }

    public function test_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/enquiries', ['type' => 'contact', 'name' => "P{$i}", 'email' => "p{$i}@example.com"])
                ->assertCreated();
        }

        $this->postJson('/enquiries', ['type' => 'contact', 'name' => 'Over', 'email' => 'over@example.com'])
            ->assertStatus(429);
    }
}
