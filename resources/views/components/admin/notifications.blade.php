@php
/*
* The bell in the admin header. JS-free, like the rest of the admin chrome: the
* disclosure is a native <details>, so it opens and closes without a script and
    * survives the page's own JS failing. A role with nothing to be notified about
    * gets no bell; an empty inbox keeps it, without a number.
    */
    $feed = new \App\Support\AdminNotifications(auth()->user(), match (true) {
    request()->routeIs('admin.enquiries.*') => ['enquiries'],
    request()->routeIs('admin.assistant.*') => ['questions'],
    default => [],
    });
    $all = $feed->groups();
    $groups = array_values(array_filter($all, fn ($group) => $group['count'] > 0));
    $total = (int) array_sum(array_column($groups, 'count'));
    @endphp

    @if ($all)
    <details class="group relative">
        <summary aria-label="Notifications{{ $total ? ', ' . $total . ' waiting' : '' }}"
            class="inline-flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-lg border border-ink-200 text-ink-600 transition hover:bg-sand-100 [&::-webkit-details-marker]:hidden">
            <span class="relative">
                <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 8a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7" />
                    <path d="M10.3 20a2 2 0 0 0 3.4 0" />
                </svg>
                @if ($total)
                <span
                    class="absolute -top-1.5 -right-1.5 inline-flex min-w-4 items-center justify-center rounded-full bg-clay-500 px-1 text-[10px] font-semibold leading-4 text-white tabular-nums">
                    {{ $total > 99 ? '99+' : $total }}
                </span>
                @endif
            </span>
        </summary>

        <div
            class="absolute right-0 z-40 mt-2 w-[21rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-ink-200/80 bg-white shadow-lg shadow-ink-950/10">
            <p class="border-b border-ink-100 px-4 py-3 text-[11px] font-semibold tracking-[0.12em] text-ink-400 uppercase">
                Waiting on you
            </p>

            {{-- Groups come and go, so the panel is capped rather than sized to
                 them: on a bad day every band has work in it, and a bell that
                 runs past the fold buries the links under the page. --}}
            <div class="max-h-[min(60vh,32rem)] overflow-y-auto">
            @forelse ($groups as $group)
            <div class="border-b border-ink-50 last:border-0">
                <p class="flex items-center justify-between gap-2 px-4 pt-3 text-xs font-semibold text-ink-700">
                    {{ $group['label'] }}
                    <span class="rounded-full bg-sand-100 px-1.5 text-[11px] text-ink-500 tabular-nums">{{ $group['count'] }}</span>
                </p>

                <ul>
                    @foreach ($group['items'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="flex items-start gap-2.5 px-4 py-2 hover:bg-sand-50">
                            <x-admin.notification-icon :name="$item['icon']" :tone="$item['tone']" class="mt-0.5" />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="truncate text-sm text-ink-900">{{ $item['title'] }}</span>
                                    <span class="shrink-0 text-[11px] text-ink-400">{{ $item['at'] }}</span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-ink-500">{{ $item['meta'] }}</span>
                            </span>
                        </a>
                    </li>
                    @endforeach
                </ul>

                <p class="px-4 pt-1 pb-3">
                    <a href="{{ $group['url'] }}" class="text-xs font-medium text-brass-700 hover:underline">
                        {{ $group['count'] > count($group['items']) ? 'See all ' . $group['count'] : 'Open' }}
                    </a>
                </p>
            </div>
            @empty
            <p class="px-4 py-6 text-sm text-ink-400">Nothing waiting.</p>
            @endforelse
            </div>
        </div>
    </details>
    @endif