<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Property;
use App\Models\Testimonial;
use App\Support\Ai\AiConnector;
use App\Support\CatalogueCache;
use App\Support\PublishedProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeAiConnector;
use Tests\TestCase;

/**
 * The catalogue cache is only allowed to exist because it cannot go stale:
 * every write flushes it, and the reads hydrate back into the same models
 * Blade always received. These tests hold both halves of that promise, and
 * gate the query counts the cache was built to remove.
 */
class CatalogueCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PropertySeeder::class);
        $this->seed(\Database\Seeders\TestimonialSeeder::class);
    }

    private function cache(): CatalogueCache
    {
        return app(CatalogueCache::class);
    }

    /** @return list<array{query: string, bindings: array, time: float}> */
    private function queriesDuring(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();

            return DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
    }

    /** Selects on the three tables the cached payloads are built from. */
    private function catalogueSelects(array $log): array
    {
        return array_values(array_filter(
            $log,
            fn(array $q) => preg_match('/from [`"]?(properties|property_images|testimonials)[`"]?/i', $q['query']),
        ));
    }

    private function property(array $attributes = []): Property
    {
        return Property::create(array_merge([
            'title'        => 'Canal-facing 2-bed in Business Bay',
            'type'         => 'apartment',
            'status'       => 'for_sale',
            'is_published' => true,
            'address'      => 'Tower 1, Business Bay',
            'suburb'       => 'Business Bay',
            'state'        => 'Dubai',
            'postcode'     => '',
            'price'        => 1750000,
            'bedrooms'     => 2,
            'bathrooms'    => 2,
            'carspaces'    => 1,
            'description'  => 'A quiet apartment over the canal, chiller free.',
        ], $attributes));
    }

    public function test_a_warm_home_request_reads_no_catalogue_tables(): void
    {
        $this->get('/')->assertOk();

        $log = $this->queriesDuring(fn() => $this->get('/')->assertOk());

        $this->assertSame([], $this->catalogueSelects($log));
    }

    public function test_a_warm_chat_open_reads_no_catalogue_tables(): void
    {
        $this->getJson('/assistant')->assertOk();

        $log = $this->queriesDuring(fn() => $this->getJson('/assistant')->assertOk());

        $this->assertSame([], $this->catalogueSelects($log));
    }

    public function test_a_rules_turn_does_not_re_pluck_suburbs_or_re_read_the_thread(): void
    {
        // The first turn warms the suburb list; the second is the measured one.
        $this->postJson('/assistant', ['message' => 'apartments in Business Bay'])->assertOk();

        $log = $this->queriesDuring(
            fn() => $this->postJson('/assistant', ['message' => 'apartments in Dubai Marina'])->assertOk(),
        );

        foreach ($log as $q) {
            $this->assertDoesNotMatchRegularExpression('/select\s+distinct/i', $q['query'], 'The suburb list was re-plucked.');
        }

        // The thread is read whole, not re-selected as a windowed repeat guard,
        // a history cut or a content-only distress scan.
        $reads = array_filter($log, fn(array $q) => preg_match('/^select .* from [`"]?chat_messages/i', $q['query']));
        $this->assertNotEmpty($reads);
        $this->assertLessThanOrEqual(3, count($reads));

        foreach ($reads as $q) {
            $this->assertDoesNotMatchRegularExpression('/\blimit\b/i', $q['query']);
            $this->assertDoesNotMatchRegularExpression('/^select [`"]?content/i', $q['query']);
        }

        $this->assertSame('rule:search', ChatMessage::query()->latest('id')->value('answered_by'));
    }

    public function test_a_model_turn_takes_the_prompt_suburbs_from_the_cache(): void
    {
        $ai = new FakeAiConnector('It has four bedrooms.');
        $this->app->instance(AiConnector::class, $ai);
        config(['ai.chat.model_enabled' => true]);

        $listed = $this->cache()->saleSuburbs();
        $slug = Property::published()->value('slug');

        $log = $this->queriesDuring(
            fn() => $this->postJson('/assistant', ['message' => 'Compare the bedrooms with a townhouse', 'property' => $slug])->assertOk(),
        );

        foreach ($log as $q) {
            $this->assertDoesNotMatchRegularExpression('/select\s+distinct/i', $q['query'], 'The prompt suburb list was re-plucked.');

            if (preg_match('/^select .* from [`"]?chat_messages/i', $q['query'])) {
                $this->assertDoesNotMatchRegularExpression('/\blimit\b/i', $q['query'], 'The history window was re-selected.');
            }
        }

        $this->assertSame(1, $ai->calls);
        $this->assertStringContainsString('Areas with listings for sale right now: ' . $listed, (string) $ai->system);
    }

    public function test_the_slug_memo_resolves_each_slug_once_per_request(): void
    {
        $resolver = app(PublishedProperty::class);
        $property = Property::published()->first();

        $log = $this->queriesDuring(function () use ($resolver, $property) {
            $this->assertSame($property->id, $resolver->bySlug($property->slug)?->id);
            $this->assertSame($property->id, $resolver->bySlug($property->slug)?->id);

            // A miss is memoised too, and a blank slug never reaches the database.
            $this->assertNull($resolver->bySlug('no-such-listing'));
            $this->assertNull($resolver->bySlug('no-such-listing'));
            $this->assertNull($resolver->bySlug(null));
            $this->assertNull($resolver->bySlug(''));
        });

        $this->assertCount(2, $log);
    }

    public function test_a_listing_page_turn_resolves_its_slug_once(): void
    {
        $property = Property::published()->first();
        $ai = (new FakeAiConnector('It has four bedrooms.'))->callsTool('get_property', ['slug' => $property->slug]);
        $this->app->instance(AiConnector::class, $ai);
        config(['ai.chat.model_enabled' => true]);

        $log = $this->queriesDuring(
            fn() => $this->postJson('/assistant', ['message' => 'Compare the bedrooms with a townhouse', 'property' => $property->slug])->assertOk(),
        );

        // The controller and the get_property tool asked for the same slug.
        $slugLookups = array_filter($log, fn(array $q) => preg_match('/[`"]slug[`"] =/i', $q['query']));
        $this->assertCount(1, $slugLookups);
        $this->assertNotNull($ai->resultOf('get_property'));
    }

    public function test_a_new_listing_reaches_the_home_page_and_area_lists_immediately(): void
    {
        $this->get('/')->assertOk();
        $this->cache()->suburbs();

        $this->property([
            'title'       => 'Fresh listing in Brand New Area',
            'suburb'      => 'Brand New Area',
            'address'     => 'Tower 9, Brand New Area',
            'is_featured' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Fresh listing in Brand New Area');
        $this->assertContains('Brand New Area', $this->cache()->suburbs()->all());
    }

    public function test_marking_a_listing_sold_moves_it_between_the_home_lists_at_once(): void
    {
        $home = $this->cache()->home();
        $property = $home['featured']->first();
        $soldBefore = $home['soldCount'];

        $property->update(['status' => 'sold', 'sold_price' => $property->price, 'sold_at' => '2027-01-01']);

        $after = $this->cache()->home();
        $this->assertFalse($after['featured']->contains('id', $property->id));
        $this->assertSame($property->id, $after['recentlySold']->first()->id);
        $this->assertSame($soldBefore + 1, $after['soldCount']);
    }

    public function test_deleting_a_listing_removes_it_from_the_cached_reads(): void
    {
        $property = $this->property([
            'title'       => 'Doomed listing in Delete Me District',
            'suburb'      => 'Delete Me District',
            'address'     => 'Tower 3, Delete Me District',
            'is_featured' => true,
        ]);

        $this->cache()->home();
        $this->assertContains('Delete Me District', $this->cache()->suburbs()->all());

        $property->delete();

        $this->assertFalse($this->cache()->home()['featured']->contains('id', $property->id));
        $this->assertNotContains('Delete Me District', $this->cache()->suburbs()->all());
    }

    public function test_adding_and_removing_a_photo_refreshes_the_cached_cards(): void
    {
        $property = $this->cache()->home()['featured']->first();
        $before = $property->images->count();

        $image = $property->images()->create([
            'path'       => 'images/properties/p01-1.webp',
            'alt'        => 'A newly uploaded shot',
            'sort_order' => 99,
        ]);

        $card = $this->cache()->home()['featured']->firstWhere('id', $property->id);
        $this->assertCount($before + 1, $card->images);
        $this->assertTrue($card->images->contains('id', $image->id));

        $image->delete();

        $this->assertCount($before, $this->cache()->home()['featured']->firstWhere('id', $property->id)->images);
    }

    public function test_the_chips_follow_the_rental_book(): void
    {
        $this->cache()->chips();

        foreach (['Rental one', 'Rental two'] as $title) {
            $this->property([
                'title'        => $title,
                'status'       => 'rental',
                'is_for_rent'  => true,
                'rent_price'   => 90000,
                'rent_period'  => 'year',
                'rent_status'  => 'available',
                'suburb'       => 'Test Rental District',
                'address'      => 'Tower 5, Test Rental District',
                'price'        => null,
            ]);
        }

        $this->assertContains('Anything to rent in Test Rental District?', $this->cache()->chips());
    }

    public function test_editing_a_review_updates_the_pages_that_show_it(): void
    {
        $review = Testimonial::published()->first();
        $this->get('/testimonials')->assertOk();
        $this->get('/about')->assertOk();

        $review->update(['body' => 'A freshly edited review body.']);

        $this->get('/testimonials')->assertOk()->assertSee('A freshly edited review body.');
        $this->get('/about')->assertOk()->assertSee('A freshly edited review body.');
    }

    public function test_unfeaturing_a_review_removes_it_from_the_home_page(): void
    {
        $review = Testimonial::published()->where('is_featured', true)->first();
        $this->assertNotNull($review);
        $this->cache()->featuredTestimonials();

        $review->update(['is_featured' => false]);

        $this->assertFalse($this->cache()->featuredTestimonials()->contains('id', $review->id));
    }

    public function test_unpublished_listings_never_enter_the_cached_reads(): void
    {
        $this->property([
            'title'        => 'Unpublished listing in Secret Area',
            'suburb'       => 'Secret Area',
            'address'      => 'Tower 2, Secret Area',
            'is_published' => false,
        ]);

        $this->assertNotContains('Secret Area', $this->cache()->suburbs()->all());
        $this->get('/')->assertOk()->assertDontSee('Unpublished listing in Secret Area');
    }

    public function test_cached_cards_hydrate_into_models_identical_to_live_ones(): void
    {
        $this->cache()->home();

        $live = Property::published()->forSale()->with('images')
            ->orderByDesc('is_featured')->orderByDesc('created_at')->take(6)->get();
        $liveSold = Property::published()->sold()->with('images')->orderByDesc('sold_at')->take(3)->get();

        $home = null;
        $log = $this->queriesDuring(function () use (&$home) {
            $home = $this->cache()->home();
        });

        // Hydration is array work, not database work.
        $this->assertSame([], $log);

        $this->assertSame($live->pluck('id')->all(), $home['featured']->pluck('id')->all());
        $this->assertSame($liveSold->pluck('id')->all(), $home['recentlySold']->pluck('id')->all());

        foreach ($home['featured'] as $index => $card) {
            $this->assertInstanceOf(Property::class, $card);
            $this->assertTrue($card->relationLoaded('images'));
            $this->assertSame($live[$index]->heroImageUrl(), $card->heroImageUrl());
            $this->assertSame(route('properties.show', $live[$index]), route('properties.show', $card));
            $this->assertSame($live[$index]->priceDisplay(), $card->priceDisplay());
            $this->assertSame($live[$index]->images->pluck('id')->all(), $card->images->pluck('id')->all());
            $this->assertSame($live[$index]->features, $card->features);
            $this->assertIsBool($card->is_featured);
        }

        foreach ($home['recentlySold'] as $card) {
            $this->assertInstanceOf(Carbon::class, $card->sold_at);
        }
    }

    public function test_cached_reviews_hydrate_with_casts_and_display_order(): void
    {
        $this->cache()->testimonials();
        $live = Testimonial::published()->get();

        $reviews = null;
        $log = $this->queriesDuring(function () use (&$reviews) {
            $reviews = $this->cache()->testimonials();
        });

        $this->assertSame([], $log);
        $this->assertSame($live->pluck('id')->all(), $reviews->pluck('id')->all());

        foreach ($reviews as $index => $review) {
            $this->assertInstanceOf(Testimonial::class, $review);
            $this->assertSame($live[$index]->body, $review->body);
            $this->assertIsBool($review->is_featured);
            $this->assertIsBool($review->is_published);
        }
    }

    public function test_the_seeder_flush_leaves_the_cache_reflecting_the_seeded_rows(): void
    {
        $cache = $this->cache();
        $cache->home();
        $cache->chips();
        $cache->suburbs();
        $cache->saleSuburbs();
        $cache->testimonials();

        // A mass write that skips model events, exactly like the seeders use.
        $before = $cache->home()['featured']->first()->price;
        Property::query()->where('status', 'for_sale')->update(['price' => DB::raw('price + 1')]);
        $this->assertSame($before, $cache->home()['featured']->first()->price, 'The mass write should not have flushed by itself.');

        $this->seed();

        $live = Property::published()->forSale()->with('images')
            ->orderByDesc('is_featured')->orderByDesc('created_at')->take(6)->get();
        $home = $cache->home();

        $this->assertSame($live->pluck('id')->all(), $home['featured']->pluck('id')->all());
        $this->assertSame($live->pluck('price')->all(), $home['featured']->pluck('price')->all());
        $this->assertSame(Property::published()->sold()->count(), $home['soldCount']);
        $this->assertSame(
            Property::published()->distinct()->pluck('suburb')->filter()->values()->all(),
            $cache->suburbs()->all(),
        );
        $this->assertSame(
            Property::published()->forSale()->distinct()->orderBy('suburb')->pluck('suburb')->implode(', '),
            $cache->saleSuburbs(),
        );
        $this->assertSame(Testimonial::published()->pluck('id')->all(), $cache->testimonials()->pluck('id')->all());
        $this->assertContains('What areas do you cover?', $cache->chips());
    }
}
