<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level half of the permission model: `ability:enquiries.manage`.
 *
 * Listing more than one ability means "any of these is enough", which lets a
 * screen be shared by roles that reach it through different permissions.
 * The ability names come from App\Enums\Role::ABILITIES.
 */
class EnsureAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        foreach ($abilities as $ability) {
            if ($user->hasAbility($ability)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
