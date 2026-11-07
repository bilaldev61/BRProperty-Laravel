@extends('layouts.app', [
'nav' => 'blog',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Blog', 'url' => route('blog')],
['name' => $post['title'], 'url' => null],
],
'title' => $post['title'],
'description' => $post['excerpt'],
'ogType' => 'article',
])

@section('content')

<article>
    {{-- Header band --}}
    <section class="relative isolate overflow-hidden bg-ink-900 text-sand-100">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -top-32 -right-20 h-72 w-72 rounded-full bg-brass-500/25 blur-3xl"></div>
            <div class="absolute -bottom-44 left-1/3 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
        </div>
        <div class="container-page max-w-3xl py-14 lg:py-20">
            <a href="{{ route('blog') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brass-300 hover:text-brass-200">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                </svg>
                Back to the blog
            </a>

            <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-sand-300">
                <span class="chip bg-white/10 text-sand-100">{{ $post['category'] }}</span>
                <time datetime="{{ $post['date'] }}">{{ \App\Support\Blog::dateLabel($post['date']) }}</time>
                <span aria-hidden="true">&middot;</span>
                <span>{{ $post['read'] }}</span>
            </div>

            <h1 class="mt-4 text-4xl leading-tight text-white sm:text-5xl">{{ $post['title'] }}</h1>
        </div>
    </section>

    {{-- Body --}}
    <section class="container-page py-14 lg:py-16">
        <div class="mx-auto max-w-3xl">
            <div class="prose-page">
                @foreach ($post['body'] as $paragraph)
                <p>{{ $paragraph }}</p>
                @endforeach
            </div>

            <div class="mt-10 flex items-center gap-3 border-t border-ink-100 pt-8">
                <img src="{{ \App\Support\Media::agent('avatar') }}" alt="" width="48" height="48" loading="lazy" decoding="async" class="h-12 w-12 rounded-full object-cover" aria-hidden="true">
                <div>
                    <p class="text-sm font-semibold text-ink-900">{{ config('agent.name') }} &middot; {{ config('agent.agency') }}</p>
                    <p class="text-sm text-ink-500">{{ config('agent.title') }} in Dubai, UAE</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Related --}}
    @if (! empty($latest))
    <section class="border-y border-ink-100 bg-sand-100/60">
        <div class="container-page py-16">
            <p class="eyebrow">Keep reading</p>
            <h2 class="mt-3 text-3xl">More from the journal</h2>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latest as $related)
                <a href="{{ route('blog.show', $related['slug']) }}"
                    class="group flex flex-col rounded-3xl border border-ink-100 bg-white p-6 transition duration-200 hover:-translate-y-0.5 hover:border-ink-300 hover:shadow-xl hover:shadow-ink-950/5">
                    <span class="chip self-start bg-plum-50 text-plum-600">{{ $related['category'] }}</span>
                    <h3 class="mt-4 font-display text-lg leading-snug text-ink-900 group-hover:text-plum-700">{{ $related['title'] }}</h3>
                    <p class="mt-3 flex-1 text-sm leading-relaxed text-ink-500">{{ $related['excerpt'] }}</p>
                    <span class="mt-5 text-sm font-semibold text-plum-600 group-hover:text-plum-700">Read &rarr;</span>
                </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif
</article>

@endsection