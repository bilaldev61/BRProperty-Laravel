<?php

/*
|--------------------------------------------------------------------------
| AI assistance
|--------------------------------------------------------------------------
|
| Powers the reply drafts in the enquiry inbox. Everything here is optional:
| with no API key the app binds a connector that reports itself unconfigured,
| the "Draft reply" button never appears, and the rest of the admin behaves
| exactly as it did before. AI is an enhancement layer, never a dependency of
| the core flow.
|
| Nothing written by the model is ever sent to a client automatically - drafts
| land in a box the agent edits first.
|
*/

return [
    /*
     | Which provider answers: 'anthropic' (Claude directly) or 'openrouter'
     | (one key, many vendors' models). Left unset, it uses whichever key is
     | filled in, preferring OpenRouter.
     */
    'driver' => env('AI_DRIVER', filled(env('OPENROUTER_API_KEY')) ? 'openrouter' : 'anthropic'),

    'key' => env('ANTHROPIC_API_KEY'),

    'model' => env('AI_MODEL', 'claude-opus-5'),

    /*
     | A reply to a property question is a few short paragraphs. This is a
     | deliberate ceiling on a deliberately short output, not a guess - it also
     | caps what a single click can cost.
     */
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),

    /* Seconds to wait before giving up and letting the agent write it himself. */
    'timeout' => (int) env('AI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Automatic enquiry replies
    |--------------------------------------------------------------------------
    |
    | Answer each website enquiry by email, written by the model from the
    | listing records and checked against them before sending. A reply that
    | fails the check, or no model, sends the template confirmation instead.
    | See App\Services\AutoReply\EnquiryAutoReply.
    |
    */
    'enquiry_reply' => [
        'enabled' => (bool) env('AI_ENQUIRY_REPLY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenRouter
    |--------------------------------------------------------------------------
    |
    | An OpenAI-style API in front of many vendors' models. Model names carry
    | the vendor: "openai/gpt-4o", "anthropic/claude-sonnet-5.5", and so on -
    | see openrouter.ai/models. The drafts and the chat can use different ones.
    |
    */
    'openrouter' => [
        'key'        => env('OPENROUTER_API_KEY'),
        'base_url'   => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model'      => env('OPENROUTER_MODEL', 'openai/gpt-4o'),
        'chat_model' => env('OPENROUTER_CHAT_MODEL', env('OPENROUTER_MODEL', 'openai/gpt-4o')),

        /*
         | Reply ceiling for the chat. Lower than the Claude setting above, which
         | leaves room for thinking; OpenRouter also refuses any request whose
         | ceiling the key's remaining credit could not cover.
         */
        'chat_max_tokens' => (int) env('OPENROUTER_CHAT_MAX_TOKENS', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public property assistant
    |--------------------------------------------------------------------------
    |
    | The chat bubble visitors use to ask about listings. It shares the API key
    | above but is tuned separately: it answers the public, so it runs on every
    | visitor's question rather than on an agent's click, and every limit below
    | is a cost ceiling as much as a behaviour.
    |
    */
    'chat' => [
        /* Switch the bubble off without removing the key the reply drafts use. */
        'enabled' => (bool) env('AI_CHAT_ENABLED', true),

        'model' => env('AI_CHAT_MODEL', 'claude-opus-5-5'),

        /*
         | Chat is short answers over data we hand it - `low` effort is plenty and
         | keeps replies quick. Raise to `medium` if answers feel shallow.
         */
        'effort' => env('AI_CHAT_EFFORT', 'low'),

        /* Covers the model's thinking as well as the reply itself. */
        'max_tokens' => (int) env('AI_CHAT_MAX_TOKENS', 4096),

        /*
         | Answer simple questions - price, size, hours, "is it available?" -
         | straight from the database without calling the model at all. See
         | App\Services\Assistant\QuickAnswers. Only switch off to debug.
         */
        'quick_answers' => (bool) env('AI_CHAT_QUICK_ANSWERS', true),

        /* Search, look up, save a lead - more than this in one turn is a loop. */
        'max_tool_rounds' => 4,

        /* Visitor messages per conversation before we hand them to a human. */
        'max_messages' => (int) env('AI_CHAT_MAX_MESSAGES', 30),

        /*
         | Visitor messages the whole site may send in a day. A hard ceiling on
         | the bill if the bubble is ever hammered; past it, visitors get the
         | agent's number instead of an answer.
         */
        'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 1500),

        /*
         | How much of the conversation is resent to the model each turn - the
         | biggest part of every call's input. Twelve is six exchanges.
         */
        'history' => (int) env('AI_CHAT_HISTORY', 12),

        /* Conversations that never became an enquiry are deleted after this. */
        'retention_days' => (int) env('AI_CHAT_RETENTION_DAYS', 90),
    ],
];
