<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the phone agent's tools (n8n, Retell) into /api/v1 with one shared
 * token, and nobody else.
 *
 * With no token configured the API is closed entirely - an integration that
 * is half set up must not be an open door. The comparison is constant-time.
 */
class AuthenticateAutomation
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('integrations.automation_token');

        if ($expected === '') {
            return response()->json(['message' => 'The phone agent API is not configured on this server.'], 503);
        }

        $given = (string) ($request->bearerToken() ?? $request->header('X-Api-Key') ?? $request->query('token', ''));

        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Invalid or missing API token.'], 401);
        }

        return $next($request);
    }
}
