<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

/**
 * The same endpoint is called two ways: by n8n with plain fields, and by a
 * Retell custom function, which posts {name, call, args}. This reads either.
 */
trait ReadsToolInput
{
    /** @return array<string, mixed> */
    protected function toolInput(Request $request): array
    {
        $args = $request->input('args');

        // Top-level `name` is the function's name in Retell's format, but the
        // caller's name in a plain n8n request - only drop it for the former.
        $envelope = is_array($args) ? ['args', 'call', 'name', 'token'] : ['call', 'token'];

        return array_merge(
            $request->except($envelope),
            is_array($args) ? $args : [],
        );
    }

    /** The caller's number from a live call, when the tool did not pass one. */
    protected function callerPhone(Request $request): ?string
    {
        $call = (array) $request->input('call', []);

        return ($call['direction'] ?? 'inbound') === 'outbound'
            ? ($call['to_number'] ?? null)
            : ($call['from_number'] ?? null);
    }
}
