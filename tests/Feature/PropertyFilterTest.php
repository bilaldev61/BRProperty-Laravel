<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Services\Listings\PropertySearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
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

    private function listing(array $attributes = []): Property
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

    public function test_search_filters_by_a_recorded_metro_landmark(): void
    {
        $byMetro = $this->listing([
            'title' => 'Canal flat by the metro',
            'suburb' => 'Business Bay',
            'type' => 'apartment',
            'nearby' => [['type' => 'metro', 'name' => 'Business Bay Metro', 'distance' => '5 min walk']],
        ]);
        $this->listing([
            'title' => 'Flat by the mall only',
            'suburb' => 'Business Bay',
            'type' => 'apartment',
            'nearby' => [['type' => 'mall', 'name' => 'Dubai Mall', 'distance' => '10 min drive']],
        ]);
        $this->listing(['title' => 'Flat with no landmarks', 'suburb' => 'Business Bay', 'type' => 'apartment']);

        $result = app(PropertySearch::class)->search(['nearby' => 'metro']);

        // The seeded listings carry no nearby record, so only the metro flat qualifies.
        $this->assertSame(1, $result['total']);
        $this->assertSame($byMetro->slug, $result['properties'][0]['slug']);
    }

    public function test_search_filters_by_a_recorded_mall_landmark(): void
    {
        $byMall = $this->listing([
            'title' => 'Villa by the mall',
            'nearby' => [['type' => 'mall', 'name' => 'Dubai Hills Mall', 'distance' => '6 min drive']],
        ]);
        $this->listing([
            'title' => 'Villa by the metro only',
            'nearby' => [['type' => 'metro', 'name' => 'Equiti Metro', 'distance' => '15 min drive']],
        ]);

        $result = app(PropertySearch::class)->search(['nearby' => 'mall']);

        $this->assertSame(1, $result['total']);
        $this->assertSame($byMall->slug, $result['properties'][0]['slug']);
    }

    public function test_the_nearby_criterion_only_accepts_recorded_landmark_types(): void
    {
        $rules = PropertySearch::rules();

        $this->assertTrue(Validator::make(['nearby' => 'metro'], $rules)->passes());
        $this->assertTrue(Validator::make(['nearby' => 'cinema'], $rules)->fails());
    }
}
