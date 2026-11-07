<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function agent(): User
    {
        return User::factory()->create([
            'name'     => 'Bilal Raza',
            'email'    => 'agent@example.com',
            'password' => Hash::make('correct-horse'),
        ]);
    }

    public function test_the_profile_page_renders(): void
    {
        $this->actingAs($this->agent())->get('/admin/profile')
            ->assertOk()
            ->assertSee('Bilal Raza')
            ->assertSee('Change password');
    }

    public function test_name_and_email_can_be_changed(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put('/admin/profile', [
            'name' => 'B. Raza',
            'email' => 'newmail@example.com',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame('B. Raza', $agent->fresh()->name);
        $this->assertSame('newmail@example.com', $agent->fresh()->email);
    }

    public function test_the_email_must_be_unique_but_may_stay_the_same(): void
    {
        $agent = $this->agent();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($agent)->put('/admin/profile', ['name' => 'Z', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');

        $this->actingAs($agent)->put('/admin/profile', ['name' => 'Z', 'email' => 'agent@example.com'])
            ->assertSessionHasNoErrors();
    }

    public function test_the_password_changes_when_the_current_one_is_right(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put('/admin/profile/password', [
            'current_password'      => 'correct-horse',
            'password'              => 'battery-staple-9',
            'password_confirmation' => 'battery-staple-9',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertTrue(Hash::check('battery-staple-9', $agent->fresh()->password));
    }

    public function test_a_wrong_current_password_changes_nothing(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put('/admin/profile/password', [
            'current_password'      => 'wrong',
            'password'              => 'battery-staple-9',
            'password_confirmation' => 'battery-staple-9',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('correct-horse', $agent->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed_and_long_enough(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put('/admin/profile/password', [
            'current_password' => 'correct-horse',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('correct-horse', $agent->fresh()->password));
    }

    public function test_uploading_a_portrait_updates_the_public_site(): void
    {
        Storage::fake('public');

        $this->actingAs($this->agent())
            ->post('/admin/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 800, 800)])
            ->assertRedirect();

        $stored = Setting::find('photo')->value;

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $disk->assertExists($stored);
        $this->assertSame($stored, Setting::find('avatar')->value);

        app(SiteSettings::class)->apply();
        $this->get('/about')->assertOk()->assertSee($stored, false);
    }

    public function test_a_non_image_portrait_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->agent())
            ->post('/admin/profile/photo', ['photo' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])
            ->assertSessionHasErrors('photo');

        $this->assertNull(Setting::find('photo'));
    }

    public function test_guests_cannot_touch_the_profile(): void
    {
        $agent = $this->agent();

        $this->get('/admin/profile')->assertRedirect(route('admin.login'));
        $this->put('/admin/profile', ['name' => 'Hacker', 'email' => 'h@example.com'])->assertRedirect(route('admin.login'));
        $this->put('/admin/profile/password', ['current_password' => 'correct-horse', 'password' => 'aaaaaaaa1', 'password_confirmation' => 'aaaaaaaa1'])
            ->assertRedirect(route('admin.login'));

        $this->assertSame('Bilal Raza', $agent->fresh()->name);
        $this->assertTrue(Hash::check('correct-horse', $agent->fresh()->password));
    }
}
