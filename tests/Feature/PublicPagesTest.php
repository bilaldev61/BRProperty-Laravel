<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PropertySeeder::class);
        $this->seed(\Database\Seeders\TestimonialSeeder::class);
    }

    public static function pageProvider(): array
    {
        return [
            'home'         => ['/'],
            'about'        => ['/about'],
            'properties'   => ['/for-sale'],
            'sold'         => ['/recently-sold'],
            'buying'       => ['/buying'],
            'selling'      => ['/selling'],
            'testimonials' => ['/testimonials'],
            'contact'      => ['/contact'],
            'privacy'      => ['/privacy'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_every_page_renders(string $path): void
    {
        $this->get($path)->assertOk();
    }

    public function test_home_shows_listings_and_agent_details(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('agent.name'))
            ->assertSee('tel:' . config('agent.phone_dial'), false)
            ->assertSee('wa.me/' . config('agent.whatsapp'), false)
            ->assertSee(Property::published()->forSale()->first()->title);
    }

    public function test_for_sale_page_excludes_sold_listings(): void
    {
        $sold = Property::sold()->first();

        $this->get('/for-sale')
            ->assertOk()
            ->assertDontSee($sold->title);
    }

    public function test_sold_page_only_lists_sold_properties(): void
    {
        $forSale = Property::forSale()->first();

        $this->get('/recently-sold')
            ->assertOk()
            ->assertSee(Property::sold()->first()->title)
            ->assertDontSee($forSale->title);
    }

    public function test_listing_page_renders_with_schema_markup(): void
    {
        $property = Property::published()->forSale()->first();

        $this->get("/properties/{$property->slug}")
            ->assertOk()
            ->assertSee($property->address)
            ->assertSee('"@type":"SingleFamilyResidence"', false)
            ->assertSee('BreadcrumbList', false);
    }

    public function test_unpublished_listings_are_hidden(): void
    {
        $property = Property::published()->forSale()->first();
        $property->update(['is_published' => false]);

        $this->get("/properties/{$property->slug}")->assertNotFound();
        $this->get('/for-sale')->assertDontSee($property->title);
    }

    public function test_unpublished_testimonials_are_hidden(): void
    {
        $testimonial = Testimonial::first();
        $testimonial->update(['is_published' => false]);

        $this->get('/testimonials')
            ->assertOk()
            ->assertDontSee($testimonial->body);
    }

    public function test_sitemap_and_robots_are_served(): void
    {
        $property = Property::published()->first();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee($property->slug);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('sitemap.xml');
    }
}
