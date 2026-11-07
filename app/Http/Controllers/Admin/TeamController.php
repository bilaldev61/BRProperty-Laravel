<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Who can sign in, and as what.
 *
 * Only reachable with the team.manage ability, which is the owner's alone. Two
 * rules are enforced here rather than in the database, because both are about
 * the set of owners as a whole: you cannot remove your own account, and the
 * last owner cannot be demoted or deleted - otherwise the site can end up with
 * nobody able to grant access again.
 */
class TeamController extends Controller
{
    public function index(): View
    {
        $members = User::query()
            ->get()
            ->sortBy(fn(User $user) => array_search($user->role, Role::cases(), true))
            ->values();

        return view('admin.team.index', [
            'members' => $members,
            'roles' => Role::cases(),
            'abilities' => Role::ABILITIES,
            'me' => auth()->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $user = User::create($data);

        return back()->with('status', $user->name . ' can now sign in as ' . $user->role->label() . '.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $role = Role::from($data['role']);

        if ($user->hasRole(Role::Owner) && $role !== Role::Owner) {
            $this->refuseWithoutAnotherOwner('role', 'Promote someone else to owner first.');
        }

        $user->update($data);

        return back()->with('status', $user->name . ' is now ' . $role->label() . '.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'team' => 'You cannot remove the account you are signed in with.',
            ]);
        }

        if ($user->hasRole(Role::Owner)) {
            $this->refuseWithoutAnotherOwner('team', 'Promote someone else to owner first.');
        }

        $name = $user->name;
        $user->delete();

        return back()->with('status', $name . ' no longer has access.');
    }

    /**
     * Fail the request when this account is the only owner left.
     */
    private function refuseWithoutAnotherOwner(string $field, string $hint): void
    {
        $owners = User::where('role', Role::Owner->value)->count();

        if ($owners <= 1) {
            throw ValidationException::withMessages([
                $field => 'There must always be at least one owner. ' . $hint,
            ]);
        }
    }
}
