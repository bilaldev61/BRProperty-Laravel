<?php

namespace Tests\Feature\Admin;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Support\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function metrics(): DashboardMetrics
    {
        return app(DashboardMetrics::class);
    }

    public function test_an_agent_sees_the_work_but_not_what_the_portfolio_is_worth(): void
    {
        $this->seed(\Database\Seeders\PropertySeeder::class);
        $this->seed(\Database\Seeders\EnquirySeeder::class);

        // An agent answers enquiries, which is what most of this page is about.
        $this->actingAs(User::factory()->agent()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Enquiries per week')
            ->assertSee('Most enquired listings')
            ->assertDontSee('Portfolio value')
            ->assertDontSee('Sold, last 12 months');
    }

    public function test_a_manager_sees_what_the_portfolio_is_worth(): void
    {
        $this->actingAs(User::factory()->manager()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Portfolio value')
            ->assertSee('Sold, last 12 months');
    }

    public function test_an_editor_never_receives_customer_enquiries(): void
    {
        $this->seed(\Database\Seeders\PropertySeeder::class);
        $this->seed(\Database\Seeders\EnquirySeeder::class);

        $enquiry = Enquiry::firstOrFail();

        $this->actingAs(User::factory()->editor()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Recently added listings')
            ->assertDontSee('Enquiries per week')
            ->assertDontSee('Latest enquiries')
            ->assertDontSee($enquiry->email)
            ->assertDontSee('Portfolio value');
    }

    public function test_the_weekly_series_always_spans_the_full_window(): void
    {
        $series = $this->metrics()->enquiriesByWeek();

        $this->assertCount(DashboardMetrics::WEEKS, $series['points']);
        $this->assertSame(['Property', 'Appraisal', 'General'], $series['series']);

        // Empty weeks are present as zeroes rather than missing, so the axis is continuous.
        foreach ($series['points'] as $point) {
            $this->assertCount(3, $point['values']);
        }
    }

    public function test_enquiries_land_in_the_week_they_were_created(): void
    {
        Enquiry::create([
            'type' => 'appraisal',
            'name' => 'A',
            'email' => 'a@example.com',
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeeks(2),
        ]);

        $points = $this->metrics()->enquiriesByWeek()['points'];
        $totals = array_map(fn($p) => array_sum($p['values']), $points);

        $this->assertSame(1, array_sum($totals));
        // Twelve buckets, newest last: two weeks ago is the third from the end.
        $this->assertSame(1, $totals[count($totals) - 3]);
    }

    public function test_enquiries_older_than_the_window_are_excluded(): void
    {
        Enquiry::create([
            'type' => 'contact',
            'name' => 'Old',
            'email' => 'old@example.com',
            'created_at' => now()->subWeeks(30),
            'updated_at' => now()->subWeeks(30),
        ]);

        $totals = array_map(
            fn($p) => array_sum($p['values']),
            $this->metrics()->enquiriesByWeek()['points'],
        );

        $this->assertSame(0, array_sum($totals));
    }

    public function test_listings_without_enquiries_are_left_off_the_ranking(): void
    {
        $this->seed(\Database\Seeders\PropertySeeder::class);
        $wanted = Property::forSale()->first();
        $ignored = Property::forSale()->skip(1)->first();

        Enquiry::create([
            'type' => 'property',
            'property_id' => $wanted->id,
            'name' => 'B',
            'email' => 'b@example.com',
        ]);

        $rows = $this->metrics()->enquiriesByListing();

        $this->assertCount(1, $rows);
        $this->assertSame($wanted->title, $rows[0]['label']);
        $this->assertNotContains($ignored->title, array_column($rows, 'label'));
    }

    public function test_area_totals_only_count_live_priced_listings(): void
    {
        $this->seed(\Database\Seeders\PropertySeeder::class);

        $areas = collect($this->metrics()->valueByArea());
        $expected = (int) Property::published()->forSale()->whereNotNull('price')->sum('price');

        $this->assertSame($expected, $areas->sum('value'));
        $this->assertSame(
            $areas->sortByDesc('value')->pluck('label')->all(),
            $areas->pluck('label')->all(),
            'areas should come back highest value first',
        );
    }

    public function test_tiles_report_how_much_work_is_still_outstanding(): void
    {
        // Opened but never answered still counts as outstanding - the tile
        // tracks what is owed a reply, not what has been glanced at.
        Enquiry::create(['type' => 'contact', 'name' => 'C', 'email' => 'c@example.com', 'read_at' => now()]);
        Enquiry::create([
            'type' => 'contact',
            'name' => 'D',
            'email' => 'd@example.com',
            'status' => Enquiry::STATUS_REPLIED
        ]);

        $tiles = collect($this->metrics()->tiles());
        $enquiryTile = $tiles->firstWhere('label', 'Enquiries, last 30 days');

        $this->assertSame('2', $enquiryTile['value']);
        $this->assertStringContainsString('1 still need a reply', $enquiryTile['meta']);
    }

    public function test_guests_cannot_reach_the_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }
}
