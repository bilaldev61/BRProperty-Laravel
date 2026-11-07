<?php

namespace Tests\Feature\Admin;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagePropertiesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->agent = User::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Sunny 10 Marla with a big lawn',
            'description'  => "First paragraph.\n\nSecond paragraph.",
            'status'       => 'for_sale',
            'type'         => 'house',
            'price'        => 21000000,
            'address'      => 'House 5, Block T',
            'suburb'       => 'Johar Town',
            'state'        => 'Punjab',
            'postcode'     => '54782',
            'bedrooms'     => 3,
            'bathrooms'    => 2,
            'carspaces'    => 1,
            'land_size'    => 10,
            'features'     => 'Solar panels, Clean transfer,  Standby generator ',
            'is_published' => '1',
        ], $overrides);
    }

    private function publicDisk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk;
    }

    public function test_an_agent_can_create_a_property_with_photos(): void
    {
        $this->actingAs($this->agent)
            ->post('/admin/properties', $this->validPayload([
                'images' => [
                    UploadedFile::fake()->image('front.jpg'),
                    UploadedFile::fake()->image('kitchen.jpg'),
                ],
            ]))
            ->assertRedirect();

        $property = Property::sole();

        $this->assertSame('sunny-10-marla-with-a-big-lawn-johar-town', $property->slug);
        $this->assertSame(['Solar panels', 'Clean transfer', 'Standby generator'], $property->features);
        $this->assertCount(2, $property->images);

        foreach ($property->images as $image) {
            $this->publicDisk()->assertExists($image->path);
        }
    }

    public function test_photos_keep_the_order_they_were_uploaded_in(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload([
            'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ]));

        $property = Property::sole();

        $this->actingAs($this->agent)->put("/admin/properties/{$property->slug}", $this->validPayload([
            'images' => [UploadedFile::fake()->image('three.jpg')],
        ]));

        $this->assertSame([0, 1, 2], $property->fresh()->images->pluck('sort_order')->all());
    }

    public function test_creating_a_property_requires_the_core_fields(): void
    {
        $this->actingAs($this->agent)
            ->post('/admin/properties', [])
            ->assertSessionHasErrors(['title', 'description', 'address', 'suburb', 'bedrooms']);

        $this->assertSame(0, Property::count());
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $this->actingAs($this->agent)
            ->post('/admin/properties', $this->validPayload([
                'images' => [UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf')],
            ]))
            ->assertSessionHasErrors('images.0');

        $this->assertSame(0, Property::count());
    }

    public function test_the_slug_does_not_change_when_the_title_is_edited(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload());
        $property = Property::sole();
        $original = $property->slug;

        $this->actingAs($this->agent)
            ->put("/admin/properties/{$property->slug}", $this->validPayload(['title' => 'A completely new headline']))
            ->assertRedirect();

        $this->assertSame($original, $property->fresh()->slug);
        $this->assertSame('A completely new headline', $property->fresh()->title);
    }

    public function test_two_properties_with_the_same_title_get_distinct_slugs(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload());
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload());

        $this->assertSame(
            ['sunny-10-marla-with-a-big-lawn-johar-town', 'sunny-10-marla-with-a-big-lawn-johar-town-2'],
            Property::orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_deleting_a_property_removes_its_uploaded_files(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload([
            'images' => [UploadedFile::fake()->image('front.jpg')],
        ]));

        $property = Property::sole();
        $path = $property->images->first()->path;

        $this->actingAs($this->agent)->delete("/admin/properties/{$property->slug}")->assertRedirect();

        $this->publicDisk()->assertMissing($path);
        $this->assertSame(0, Property::count());
        $this->assertDatabaseCount('property_images', 0);
    }

    public function test_a_single_photo_can_be_removed(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload([
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]));

        $property = Property::sole();
        $image = $property->images->first();

        $this->actingAs($this->agent)
            ->delete("/admin/properties/{$property->slug}/images/{$image->id}")
            ->assertRedirect();

        $this->publicDisk()->assertMissing($image->path);
        $this->assertCount(1, $property->fresh()->images);
    }

    public function test_a_photo_cannot_be_deleted_through_another_property(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload([
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ]));
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload(['suburb' => 'Gulberg']));

        [$first, $second] = Property::orderBy('id')->get()->all();
        $image = $first->images->first();

        $this->actingAs($this->agent)
            ->delete("/admin/properties/{$second->slug}/images/{$image->id}")
            ->assertNotFound();

        $this->publicDisk()->assertExists($image->path);
    }

    public function test_an_unpublished_property_still_appears_in_the_admin_list(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload(['is_published' => '0']));

        $this->assertFalse(Property::sole()->is_published);

        $this->actingAs($this->agent)->get('/admin/properties')
            ->assertOk()
            ->assertSee('Sunny 10 Marla with a big lawn');
    }

    public function test_guests_cannot_create_or_delete_properties(): void
    {
        $this->post('/admin/properties', $this->validPayload())->assertRedirect(route('admin.login'));
        $this->assertSame(0, Property::count());
    }

    public function test_an_agent_can_list_a_property_for_sale_and_to_rent_at_once(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload([
            'is_for_rent' => '1',
            'rent_price'  => 95000,
            'rent_period' => 'month',
            'rent_status' => 'available',
        ]))->assertRedirect();

        $property = Property::sole();

        $this->assertTrue($property->is_for_rent);
        $this->assertSame(95000, $property->rent_price);
        $this->assertSame('for_sale', $property->status);
        $this->assertStringContainsString('95,000', $property->rentDisplay());
        // Still a for-sale listing, so it belongs on the sale list too.
        $this->assertNotNull($property->priceDisplay());
    }

    public function test_turning_the_rental_off_clears_its_stale_numbers(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload([
            'is_for_rent' => '1',
            'rent_price'  => 95000,
        ]));

        $property = Property::sole();

        $this->actingAs($this->agent)->put("/admin/properties/{$property->slug}", $this->validPayload())
            ->assertRedirect();

        $this->assertFalse($property->fresh()->is_for_rent);
        $this->assertNull($property->fresh()->rent_price);
        $this->assertNull($property->fresh()->rentDisplay());
    }

    public function test_a_rent_only_listing_is_labelled_to_rent(): void
    {
        $this->actingAs($this->agent)->post('/admin/properties', $this->validPayload([
            'status'      => 'rental',
            'price'       => null,
            'is_for_rent' => '1',
            'rent_price'  => 150000,
        ]));

        $property = Property::sole();

        $this->assertSame('To Rent', $property->statusLabel());
        $this->assertTrue(Property::forRent()->whereKey($property->id)->exists());
        $this->assertFalse(Property::forSale()->whereKey($property->id)->exists());
    }
}
