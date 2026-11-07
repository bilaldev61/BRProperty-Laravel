@extends('layouts.app', ['title' => 'Privacy Policy', 'description' => 'How '.config('agent.agency').' collects, uses and stores your personal information.'])

@section('content')
<section class="container-page py-16 lg:py-24">
    <div class="max-w-2xl">
        <h1 class="text-4xl sm:text-5xl">Privacy Policy</h1>
        <p class="mt-3 text-sm text-ink-400">Last updated {{ date('F Y') }}</p>

        <div class="prose-page mt-10 space-y-8">
            <div>
                <h2 class="font-sans text-lg font-semibold text-ink-900">What we collect</h2>
                <p class="mt-2">When you submit an enquiry or appraisal request we collect the name, email address, phone number and any message or property details you provide. We do not collect anything else about you.</p>
            </div>
            <div>
                <h2 class="font-sans text-lg font-semibold text-ink-900">How we use it</h2>
                <p class="mt-2">Your details are used only to respond to your enquiry and, where relevant, to keep you informed about the property or appraisal you asked about. We do not sell or share your information with third parties for marketing.</p>
            </div>
            <div>
                <h2 class="font-sans text-lg font-semibold text-ink-900">The chat assistant</h2>
                <p class="mt-2">If you use the chat assistant, what you type is stored with the conversation and sent to {{ config('ai.driver') === 'openrouter' ? 'our AI provider, OpenRouter, and the AI model it routes to,' : 'our AI provider, Anthropic,' }} to generate the replies. Do not share anything sensitive in it. If you choose to leave your name and number, the conversation is attached to your enquiry so {{ config('agent.name') }} can read it. Conversations that do not become an enquiry are deleted after {{ config('ai.chat.retention_days') }} days.</p>
            </div>
            <div>
                <h2 class="font-sans text-lg font-semibold text-ink-900">Third-party embeds</h2>
                <p class="mt-2">The service-area map on the home and contact pages loads a Google Maps embed only after you choose to load it. Individual property pages show a map of the listing's location, which loads with the page.</p>
            </div>
            <div>
                <h2 class="font-sans text-lg font-semibold text-ink-900">Access and removal</h2>
                <p class="mt-2">You can ask for a copy of what we hold about you, or ask us to delete it, at any time. Email <a href="mailto:{{ config('agent.email') }}" class="text-brass-600 underline">{{ config('agent.email') }}</a> and we will action it within 30 days.</p>
            </div>
        </div>
    </div>
</section>
@endsection
