<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_give_someone_access(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('admin.team.store'), [
            'name' => 'Amina Haddad',
            'email' => 'amina@example.com',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
            'role' => Role::Agent->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $member = User::where('email', 'amina@example.com')->firstOrFail();

        $this->assertSame(Role::Agent, $member->role);
        $this->assertTrue($member->hasAbility('enquiries.manage'));
        $this->assertFalse($member->hasAbility('settings.manage'));
    }

    public function test_a_new_account_needs_a_unique_email_and_a_confirmed_password(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($owner)->post(route('admin.team.store'), [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'a-long-password',
            'password_confirmation' => 'different-password',
            'role' => Role::Agent->value,
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('admin.team.store'), [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
            'role' => 'superuser',
        ])->assertSessionHasErrors('role');
    }

    public function test_a_role_can_be_changed(): void
    {
        $owner = User::factory()->owner()->create();
        $member = User::factory()->agent()->create(['name' => 'Amina Haddad']);

        $this->actingAs($owner)->patch(route('admin.team.update', $member), [
            'name' => 'Amina Haddad',
            'role' => Role::Manager->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(Role::Manager, $member->fresh()->role);
    }

    public function test_the_last_owner_cannot_be_demoted(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->patch(route('admin.team.update', $owner), [
            'name' => $owner->name,
            'role' => Role::Manager->value,
        ])->assertSessionHasErrors('role');

        $this->assertSame(Role::Owner, $owner->fresh()->role);
    }

    public function test_an_owner_can_step_down_when_another_owner_remains(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->owner()->create();

        $this->actingAs($owner)->patch(route('admin.team.update', $owner), [
            'name' => $owner->name,
            'role' => Role::Manager->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Role::Manager, $owner->fresh()->role);
    }

    public function test_you_cannot_remove_the_account_you_are_signed_in_with(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->delete(route('admin.team.destroy', $owner))
            ->assertSessionHasErrors('team');

        $this->assertModelExists($owner);
    }

    public function test_another_account_can_be_removed(): void
    {
        $owner = User::factory()->owner()->create();
        $member = User::factory()->agent()->create();

        $this->actingAs($owner)->delete(route('admin.team.destroy', $member))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($member);
    }

    public function test_one_owner_can_remove_another_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $second = User::factory()->owner()->create();

        $this->actingAs($owner)->delete(route('admin.team.destroy', $second))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($second);
        // One owner remains, so the site is still administrable.
        $this->assertSame(1, User::where('role', Role::Owner->value)->count());
    }

    public function test_only_the_owner_reaches_the_team_screen(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('admin.team.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.team.store'), [])->assertForbidden();
    }
}
