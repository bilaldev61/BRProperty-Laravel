@extends('layouts.app', [
'nav' => 'blog',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Blog', 'url' => null],
],
'title' => 'Dubai Real Estate Blog',
'description' => 'Practical guides on buying, selling and renting in Dubai - Palm Jumeirah, Downtown, Dubai Marina, Business Bay and the rest of the emirate. Written by the team at '.config('agent.agency').' about the paperwork and the numbers that actually decide a deal.',
])

@section('content')

<x-page-hero eyebrow="The journal" title="Straight talk on the Dubai market.">
    <p class="max-w-2xl text-[17px] leading-relaxed text-sand-200">
        No hype, no portal listicles. These are the things we find ourselves explaining on every
        viewing and every appraisal &mdash; written down so you have them before you need them.
    </p>
</x-page-hero>

<section class="container-page py-16 lg:py-20">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($posts as $post)
        <a href="{{ route('blog.show', $post['slug']) }}"
            class="group flex flex-col overflow-hidden rounded-3xl border border-ink-100 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-ink-300 hover:shadow-xl hover:shadow-ink-950/5">
            <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-6 py-4">
                <span class="chip bg-plum-50 text-plum-600">{{ $post['category'] }}</span>
                <span class="text-xs text-ink-400">{{ $post['read'] }}</span>
            </div>
            <div class="flex flex-1 flex-col px-6 py-6">
                <h2 class="font-display text-xl leading-snug text-ink-900 group-hover:text-plum-700">
                    {{ $post['title'] }}
                </h2>
                <p class="mt-3 flex-1 text-sm leading-relaxed text-ink-500">{{ $post['excerpt'] }}</p>
                <div class="mt-6 flex items-center justify-between border-t border-ink-100 pt-4">
                    <time class="text-xs text-ink-400" datetime="{{ $post['date'] }}">
                        {{ \App\Support\Blog::dateLabel($post['date']) }}
                    </time>
                    <span class="text-sm font-semibold text-plum-600 group-hover:text-plum-700">Read &rarr;</span>
                </div>
            </div>
        </a>
        @endforeach
    </div>

    <div class="relative isolate mt-16 overflow-hidden rounded-3xl bg-gradient-to-br from-ink-900 via-ink-800 to-ink-700 p-8 text-center text-sand-100 sm:p-12">
        <div class="absolute -left-16 -bottom-16 -z-10 h-56 w-56 rounded-full bg-brass-500/25 blur-3xl" aria-hidden="true"></div>
        <h2 class="text-2xl text-white sm:text-3xl">Have a question we have not written about?</h2>
        <p class="mx-auto mt-3 max-w-xl text-ink-300">
            If you are weighing a move in Dubai, the answer is usually a phone call away. Call the
            office and we will talk it through - no obligation.
        </p>
        <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ route('contact') }}" class="btn-accent">Ask a question</a>
            <a href="{{ route('for-sale') }}" class="btn-ghost-light">Browse properties</a>
        </div>
    </div>
</section>

@endsection