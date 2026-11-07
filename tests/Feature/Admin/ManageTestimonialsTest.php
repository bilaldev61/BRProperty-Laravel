<?php

namespace Tests\Feature\Admin;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageTestimonialsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create();
    }

    public function test_an_agent_can_add_a_testimonial(): void
    {
        $this->actingAs($this->agent)->post('/admin/testimonials', [
            'author' => 'Jo Client', 'location' => 'Gulberg', 'role' => 'Seller',
            'rating' => 5, 'body' => 'Excellent from start to finish.',
            'is_published' => '1', 'is_featured' => '1',
        ])->assertRedirect(route('admin.testimonials.index'));

        $testimonial = Testimonial::sole();
        $this->assertTrue($testimonial->is_featured);
        $this->assertTrue($testimonial->is_published);
    }

    public function test_a_testimonial_requires_an_author_body_and_rating(): void
    {
        $this->actingAs($this->agent)->post('/admin/testimonials', ['rating' => 9])
            ->assertSessionHasErrors(['author', 'body', 'rating']);
    }

    public function test_unchecking_published_hides_it_from_the_public_page(): void
    {
        $testimonial = Testimonial::create([
            'author' => 'Jo Client', 'body' => 'Great agent.', 'rating' => 5, 'is_published' => true,
        ]);

        $this->actingAs($this->agent)->put("/admin/testimonials/{$testimonial->id}", [
            'author' => 'Jo Client', 'body' => 'Great agent.', 'rating' => 5,
        ])->assertRedirect();

        $this->assertFalse($testimonial->fresh()->is_published);
        $this->get('/testimonials')->assertDontSee('Great agent.');
    }

    public function test_a_testimonial_can_be_deleted(): void
    {
        $testimonial = Testimonial::create(['author' => 'Jo', 'body' => 'Good.', 'rating' => 4]);

        $this->actingAs($this->agent)->delete("/admin/testimonials/{$testimonial->id}")->assertRedirect();

        $this->assertSame(0, Testimonial::count());
    }
}
