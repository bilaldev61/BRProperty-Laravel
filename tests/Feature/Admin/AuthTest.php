<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function agent(): User
    {
        return User::factory()->create([
            'email'    => 'agent@example.com',
            'password' => Hash::make('secret-password'),
        ]);
    }

    public static function guardedRoutes(): array
    {
        return [
            ['/admin'],
            ['/admin/properties'],
            ['/admin/properties/create'],
            ['/admin/enquiries'],
            ['/admin/testimonials'],
        ];
    }

    /** @param string $path */
    #[\PHPUnit\Framework\Attributes\DataProvider('guardedRoutes')]
    public function test_guests_are_redirected_to_the_admin_login(string $path): void
    {
        $this->get($path)->assertRedirect(route('admin.login'));
    }

    public function test_an_agent_can_log_in(): void
    {
        $agent = $this->agent();

        $this->post('/admin/login', [
            'email'    => 'agent@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($agent);
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->agent();

        $this->from('/admin/login')->post('/admin/login', [
            'email'    => 'agent@example.com',
            'password' => 'wrong',
        ])->assertRedirect('/admin/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $this->agent();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'agent@example.com', 'password' => 'wrong']);
        }

        $this->post('/admin/login', ['email' => 'agent@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_a_signed_in_agent_is_sent_to_the_dashboard_from_the_login_page(): void
    {
        $this->actingAs($this->agent())->get('/admin/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_an_agent_can_log_out(): void
    {
        $this->actingAs($this->agent())->post('/admin/logout')->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_the_admin_area_is_excluded_from_search_engines(): void
    {
        $this->actingAs($this->agent())->get('/admin')
            ->assertOk()
            ->assertSee('noindex, nofollow', false);
    }
}
