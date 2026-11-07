<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may open what. Every route in the admin area answers to an ability, so
 * these are the tests that would fail if a permission were dropped by accident.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_ability_is_granted_to_someone(): void
    {
        // A misspelt ability in the enum would silently belong to nobody, and the
        // screen it guards would then be unreachable for every role.
        foreach (array_keys(Role::ABILITIES) as $ability) {
            $this->assertTrue(
                collect(Role::cases())->contains(fn(Role $role) => $role->can($ability)),
                "No role holds the ability [{$ability}].",
            );
        }
    }

    public function test_the_owner_holds_every_ability(): void
    {
        $this->assertSame(array_keys(Role::ABILITIES), Role::Owner->abilities());
    }

    public function test_money_figures_stay_with_the_owner_and_manager(): void
    {
        // Portfolio value, sold prices and call spend are the agency's numbers,
        // not the shop floor's: an agent and an editor get operational figures.
        $holders = collect(Role::cases())
            ->filter(fn(Role $role) => $role->can('finance.view'))
            ->map(fn(Role $role) => $role->value)
            ->values()
            ->all();

        $this->assertSame(['owner', 'manager'], $holders);
    }

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        foreach (['admin.dashboard', 'admin.properties.index', 'admin.team.index'] as $name) {
            $this->get(route($name))->assertRedirect(route('admin.login'));
        }
    }

    public function test_the_owner_can_open_settings_and_the_team_screen(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('admin.settings.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.team.index'))->assertOk();
    }

    public function test_a_manager_runs_the_workspace_but_not_the_site_or_the_team(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('admin.properties.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.properties.create'))->assertOk();
        $this->actingAs($manager)->get(route('admin.enquiries.index'))->assertOk();

        $this->actingAs($manager)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.team.index'))->assertForbidden();
    }

    public function test_an_agent_answers_enquiries_but_cannot_change_listings(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get(route('admin.enquiries.index'))->assertOk();
        $this->actingAs($agent)->get(route('admin.properties.index'))->assertOk();

        $this->actingAs($agent)->get(route('admin.properties.create'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.testimonials.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_an_agent_can_move_an_enquiry_along(): void
    {
        $agent = User::factory()->agent()->create();
        $enquiry = Enquiry::create([
            'type' => 'contact',
            'name' => 'Pat Buyer',
            'email' => 'pat@example.com',
            'message' => 'Just checking in.',
        ]);

        $this->actingAs($agent)
            ->patch(route('admin.enquiries.update', $enquiry), ['status' => Enquiry::STATUS_CLOSED])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(Enquiry::STATUS_CLOSED, $enquiry->fresh()->status);
    }

    public function test_an_editor_keeps_listings_and_reviews_up_to_date_and_sees_nothing_else(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.properties.index'))->assertOk();
        $this->actingAs($editor)->get(route('admin.properties.create'))->assertOk();
        $this->actingAs($editor)->get(route('admin.testimonials.index'))->assertOk();

        $this->actingAs($editor)->get(route('admin.enquiries.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('admin.team.index'))->assertForbidden();
    }

    public function test_the_menu_hides_what_the_role_cannot_open(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.properties.index'))
            ->assertDontSee(route('admin.team.index'))
            ->assertDontSee(route('admin.settings.index'));
    }

    public function test_the_forbidden_page_says_which_role_you_are(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get(route('admin.settings.index'))
            ->assertForbidden()
            ->assertSee('Your role does not include this section.')
            ->assertSee('Agent');
    }

    public function test_a_role_changes_what_is_reachable_without_touching_routes(): void
    {
        $user = User::factory()->agent()->create();

        $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();

        $user->update(['role' => Role::Manager]);

        $this->actingAs($user)->get(route('admin.enquiries.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.properties.create'))->assertOk();
    }
}
