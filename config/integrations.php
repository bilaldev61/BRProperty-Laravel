<?php

/*
|--------------------------------------------------------------------------
| Phone agent integration (Retell voice agent + n8n)
|--------------------------------------------------------------------------
|
| The voice agent runs in Retell and the workflows in n8n; this app is the
| system of record they both call through /api/v1. Nothing here is active
| until AUTOMATION_API_TOKEN is set: with no token every API call is refused,
| so a half-configured setup cannot leak anything.
|
| Send the token as `Authorization: Bearer <token>` (n8n's Header Auth), or
| as `X-Api-Key: <token>`, or - for tools that only let you set a URL, like
| a Retell custom function - as `?token=<token>` on the URL.
|
| See docs/phone-agent.md for the full setup.
|
*/

return [
    'automation_token' => env('AUTOMATION_API_TOKEN'),

    'calls' => [
        /*
         | A repeat caller's new call joins their open lead from the last this
         | many days, instead of starting a new one.
         */
        'merge_window_days' => (int) env('CALLS_MERGE_WINDOW_DAYS', 30),

        /* Shorter than this and there was no real conversation to qualify. */
        'min_qualifying_seconds' => 20,
    ],
];
