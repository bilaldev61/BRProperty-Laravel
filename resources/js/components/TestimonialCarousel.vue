<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    items: { type: Array, default: () => [] }, // [{ body, author, location, role, rating }]
})

const index = ref(0)
const active = computed(() => props.items[index.value] ?? null)

const counter = computed(() => {
    const pad = (n) => String(n).padStart(2, '0')
    return `${pad(index.value + 1)} / ${pad(props.items.length)}`
})

/*
 * "Jordan Smith" -> "JS". Two letters is enough to read as a person without
 * having to add photographs we do not have.
 */
function initialsFor(item) {
    const words = (item?.author ?? '').trim().split(/\s+/).filter(Boolean)
    return words.slice(0, 2).map((word) => word[0].toUpperCase()).join('')
}

function metaFor(item) {
    return [item?.role, item?.location].filter(Boolean).join(' · ')
}

/*
 * Rotation is manual only. An auto-advancing quote changes the card height
 * between reviews, which counts as a layout shift on every tick and is also the
 * thing WCAG 2.2.2 asks you to let people stop. The reviews below are stacked in
 * one grid cell instead, so the panel is always as tall as the longest quote and
 * moving between them never moves the page.
 */
function go(i) {
    index.value = (i + props.items.length) % props.items.length
}
</script>

<template>
    <div v-if="active" class="relative overflow-hidden rounded-3xl border border-ink-100 bg-sand-50 p-8 sm:p-10">
        <!-- Oversized quote mark, printed as texture rather than punctuation -->
        <svg
            class="pointer-events-none absolute -top-5 right-5 h-28 w-28 text-brass-200"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
        >
            <path d="M9.6 5.4C6 7.2 3.6 10.4 3.6 14.2c0 2.9 1.7 4.8 4.2 4.8 2.2 0 3.9-1.6 3.9-3.7 0-2-1.4-3.5-3.3-3.5-.4 0-.9.1-1.1.2.4-1.6 1.9-3.3 3.9-4.3L9.6 5.4Zm9.2 0c-3.6 1.8-6 5-6 8.8 0 2.9 1.7 4.8 4.2 4.8 2.2 0 3.9-1.6 3.9-3.7 0-2-1.4-3.5-3.3-3.5-.4 0-.9.1-1.1.2.4-1.6 1.9-3.3 3.9-4.3L18.8 5.4Z" />
        </svg>

        <div class="relative flex items-center justify-between gap-4">
            <div class="flex items-center gap-0.5" role="img" :aria-label="`${active.rating} out of 5`">
                <svg
                    v-for="star in 5"
                    :key="star"
                    class="h-4 w-4"
                    :class="star <= active.rating ? 'text-brass-500' : 'text-ink-200'"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9 4.8 17.6l1-5.8L1.5 7.7l5.9-.9L10 1.5z" />
                </svg>
            </div>
            <p v-if="items.length > 1" class="text-[11px] font-semibold tracking-[0.18em] text-ink-400 tabular-nums">
                {{ counter }}
            </p>
        </div>

        <!-- Every review occupies the same grid cell; only the active one is
             painted. The cell is as tall as the longest review, so the card
             never changes height and nothing below it ever moves. -->
        <div class="relative mt-6 grid">
            <blockquote
                v-for="(item, i) in items"
                :key="i"
                class="col-start-1 row-start-1 transition-opacity duration-300"
                :class="i === index ? 'opacity-100' : 'invisible opacity-0'"
                :aria-hidden="i !== index"
            >
                <p class="font-display text-lg leading-[1.6] text-ink-900 sm:text-xl">
                    &ldquo;{{ item.body }}&rdquo;
                </p>

                <footer class="mt-8 flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-ink-900 font-display text-sm text-sand-50" aria-hidden="true">
                        {{ initialsFor(item) }}
                    </span>
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-ink-900">{{ item.author }}</p>
                        <p v-if="metaFor(item)" class="truncate text-ink-500">{{ metaFor(item) }}</p>
                    </div>
                </footer>
            </blockquote>
        </div>

        <div
            v-if="items.length > 1"
            class="relative mt-8 flex items-center justify-between gap-4 border-t border-ink-200/70 pt-5"
        >
            <div class="flex items-center gap-1.5">
                <button
                    v-for="(item, i) in items"
                    :key="i"
                    type="button"
                    class="h-1.5 rounded-full transition-all"
                    :class="i === index ? 'w-6 bg-brass-500' : 'w-1.5 bg-ink-300 hover:bg-ink-400'"
                    :aria-label="`Show review ${i + 1}`"
                    :aria-current="i === index"
                    @click="go(i)"
                />
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-ink-200 text-ink-600 transition hover:border-ink-900 hover:text-ink-900"
                    aria-label="Previous review"
                    @click="go(index - 1)"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-ink-200 text-ink-600 transition hover:border-ink-900 hover:text-ink-900"
                    aria-label="Next review"
                    @click="go(index + 1)"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
