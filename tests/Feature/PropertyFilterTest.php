<?php

namespace Tests\Feature;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PropertySeeder::class);
    }

    public function test_filters_by_suburb(): void
    {
        $response = $this->get('/for-sale?' . http_build_query(['suburb' => 'Dubai Marina']))->assertOk();

        $this->assertNotEmpty($response->viewData('properties'));

        foreach ($response->viewData('properties') as $property) {
            $this->assertSame('Dubai Marina', $property->suburb);
        }
    }

    public function test_filters_by_minimum_bedrooms(): void
    {
        $response = $this->get('/for-sale?beds=4')->assertOk();

        $this->assertNotEmpty($response->viewData('properties'));
        foreach ($response->viewData('properties') as $property) {
            $this->assertGreaterThanOrEqual(4, $property->bedrooms);
        }
    }

    public function test_filters_by_price_range(): void
    {
        $response = $this->get('/for-sale?min=400000&max=800000')->assertOk();

        foreach ($response->viewData('properties') as $property) {
            $this->assertGreaterThanOrEqual(400000, $property->price);
            $this->assertLessThanOrEqual(800000, $property->price);
        }
    }

    public function test_search_matches_suburb_and_street(): void
    {
        $query = http_build_query(['q' => 'Dubai Marina']);

        $this->assertNotEmpty($this->get('/for-sale?' . $query)->viewData('properties'));
    }

    public function test_sorts_by_price_ascending(): void
    {
        $prices = collect($this->get('/for-sale?sort=price_asc')->viewData('properties')->items())
            ->pluck('price')
            ->filter()
            ->values();

        $this->assertEquals($prices->sort()->values()->all(), $prices->all());
    }

    public function test_no_matches_returns_an_empty_page_not_an_error(): void
    {
        $this->get('/for-sale?suburb=Nowhere Town')
            ->assertOk()
            ->assertSee('No properties match those filters');
    }

    public function test_filters_are_preserved_across_pagination_links(): void
    {
        $query = http_build_query(['suburb' => 'Palm Jumeirah']);
        $properties = $this->get('/for-sale?' . $query)->viewData('properties');

        // Spaces come back percent-encoded in the paginator's own links.
        $this->assertMatchesRegularExpression('/suburb=Palm(\+|%20)Jumeirah/', $properties->url(1));
    }

    public function test_rentals_page_lists_only_active_rentals(): void
    {
        $response = $this->get('/for-rent')->assertOk();

        $listings = collect($response->viewData('properties')->items());

        $this->assertNotEmpty($listings);
        foreach ($listings as $property) {
            $this->assertTrue($property->is_for_rent);
            $this->assertContains($property->rent_status, ['available', 'let_agreed']);
        }
    }

    public function test_a_tenanted_rental_is_hidden_from_the_active_list(): void
    {
        $this->get('/for-rent')
            ->assertOk()
            ->assertDontSee('Two-bed in The Greens, currently leased');
    }

    public function test_pure_rentals_do_not_appear_on_the_for_sale_list(): void
    {
        foreach ($this->get('/for-sale')->viewData('properties') as $property) {
            $this->assertNotSame('rental', $property->status);
        }
    }

    public function test_a_dual_listing_appears_on_both_lists(): void
    {
        // The Business Bay studio is listed for sale and to rent at once.
        $query = http_build_query(['suburb' => 'Business Bay']);

        $this->get('/for-sale?' . $query)->assertSee('Canal studio in Business Bay, sale or let');
        $this->get('/for-rent?' . $query)->assertSee('Canal studio in Business Bay, sale or let');
    }

    public function test_filters_by_rent_range(): void
    {
        $query = http_build_query(['rent_min' => 60000, 'rent_max' => 100000]);
        $response = $this->get('/for-rent?' . $query)->assertOk();

        $listings = collect($response->viewData('properties')->items());
        $this->assertNotEmpty($listings);

        foreach ($listings as $property) {
            $this->assertGreaterThanOrEqual(60000, $property->rent_price);
            $this->assertLessThanOrEqual(100000, $property->rent_price);
        }
    }
}
