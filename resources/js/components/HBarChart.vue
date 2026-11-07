<script setup>
import { computed, ref } from 'vue'

/*
 * Horizontal bars for a single measure across named categories.
 *
 * One series, so one colour for every bar - a value ramp here would double-encode
 * length as hue. Values are direct-labelled at the bar end, which is the whole
 * point of the horizontal form: long category names get room to breathe.
 */
const props = defineProps({
    rows: { type: Array, required: true },  // [{ label, subLabel?, value, formatted?, url? }]
    slot: { type: Number, default: 1 },     // categorical slot to use
    valueSuffix: { type: String, default: '' },
    emptyMessage: { type: String, default: 'Nothing to show yet.' },
})

const hovered = ref(null)
const peak = computed(() => Math.max(1, ...props.rows.map(r => r.value)))
const color = computed(() => `var(--series-${props.slot})`)
const width = row => Math.max(2, (row.value / peak.value) * 100)
const display = row => (row.formatted ?? row.value) + props.valueSuffix
</script>

<template>
    <div class="viz-root">
        <p v-if="!rows.length" class="py-10 text-center text-sm text-ink-400">{{ emptyMessage }}</p>

        <ul v-else class="space-y-3.5">
            <li v-for="(row, i) in rows" :key="row.label"
                @mouseenter="hovered = i" @mouseleave="hovered = null">
                <component
                    :is="row.url ? 'a' : 'div'"
                    :href="row.url"
                    class="block rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass-600"
                >
                    <div class="flex items-baseline justify-between gap-4">
                        <p class="truncate text-sm font-medium text-ink-800">
                            {{ row.label }}
                            <span v-if="row.subLabel" class="font-normal text-ink-400">&middot; {{ row.subLabel }}</span>
                        </p>
                        <p class="shrink-0 text-sm font-semibold text-ink-900 tabular-nums">{{ display(row) }}</p>
                    </div>

                    <!-- Track is a hairline wash; the mark itself is thin and rounded at the data end -->
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-ink-50">
                        <div
                            class="h-full rounded-full transition-[width,opacity] duration-300"
                            :style="{ width: width(row) + '%', background: color, opacity: hovered === null || hovered === i ? 1 : 0.55 }"
                        ></div>
                    </div>
                </component>
            </li>
        </ul>
    </div>
</template>
