<script setup>
import { computed, ref } from 'vue'

/*
 * Weekly stacked bars. Thin marks, hairline grid, a 2px surface gap between
 * stacked segments, rounded ends only on the topmost segment of each column,
 * and a hover tooltip. Identity never rests on colour alone: a legend is always
 * present and the table view carries every number.
 */
const props = defineProps({
    series: { type: Array, required: true },   // ['Property', 'Appraisal', 'General']
    points: { type: Array, required: true },   // [{ label, fullLabel, values: [n, n, n] }]
    height: { type: Number, default: 240 },
    unit: { type: String, default: 'enquiries' },
})

const PAD = { top: 12, right: 4, bottom: 26, left: 30 }
const W = 760
const GAP = 2                                  // surface gap between segments
const hovered = ref(null)
const showTable = ref(false)

const totals = computed(() => props.points.map(p => p.values.reduce((a, b) => a + b, 0)))
const peak = computed(() => Math.max(1, ...totals.value))

/* A tidy axis top: round the peak up to a sensible step. */
const axisTop = computed(() => {
    const p = peak.value
    const step = p <= 5 ? 1 : p <= 20 ? 5 : p <= 50 ? 10 : 25
    return Math.ceil(p / step) * step
})
const ticks = computed(() => {
    const t = axisTop.value
    const count = t <= 5 ? t : 4
    return Array.from({ length: count + 1 }, (_, i) => Math.round((t / count) * i))
})

const plotH = computed(() => props.height - PAD.top - PAD.bottom)
const plotW = W - PAD.left - PAD.right
const band = computed(() => plotW / props.points.length)
const barW = computed(() => Math.min(34, band.value * 0.62))

const y = v => PAD.top + plotH.value - (v / axisTop.value) * plotH.value
const xCentre = i => PAD.left + band.value * (i + 0.5)

/** Segments for one column, bottom-up, with the surface gap already applied. */
function columnSegments(point) {
    const out = []
    let running = 0
    point.values.forEach((v, s) => {
        if (v <= 0) return
        const yTop = y(running + v)
        const yBottom = y(running)
        out.push({ series: s, value: v, yTop, height: Math.max(1, yBottom - yTop - GAP) })
        running += v
    })
    return out.map((seg, i, arr) => ({ ...seg, isTop: i === arr.length - 1 }))
}

const seriesColor = i => `var(--series-${i + 1})`

const tooltip = computed(() => {
    if (hovered.value === null) return null
    const p = props.points[hovered.value]
    return {
        title: p.fullLabel || p.label,
        total: totals.value[hovered.value],
        rows: props.series.map((name, i) => ({ name, value: p.values[i], color: seriesColor(i) })),
        left: (xCentre(hovered.value) / W) * 100,
    }
})
</script>

<template>
    <div class="viz-root">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <!-- Legend: always present for two or more series -->
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                <li v-for="(name, i) in series" :key="name" class="flex items-center gap-1.5 text-xs text-ink-600">
                    <span class="h-2.5 w-2.5 rounded-[2px]" :style="{ background: seriesColor(i) }" aria-hidden="true"></span>
                    {{ name }}
                </li>
            </ul>
            <button
                type="button"
                class="text-xs font-semibold text-ink-500 underline underline-offset-2 hover:text-ink-900"
                :aria-pressed="showTable"
                @click="showTable = !showTable"
            >{{ showTable ? 'Show chart' : 'Show table' }}</button>
        </div>

        <div v-if="!showTable" class="relative">
            <svg :viewBox="`0 0 ${W} ${height}`" class="w-full" :style="{ height: height + 'px' }" role="img"
                 :aria-label="`Weekly ${unit}, stacked by type. Peak ${peak} in a week.`">
                <!-- Hairline grid, solid, one shade off the surface -->
                <g>
                    <line v-for="t in ticks" :key="`g${t}`"
                          :x1="PAD.left" :x2="W - PAD.right" :y1="y(t)" :y2="y(t)"
                          stroke="var(--viz-grid)" stroke-width="1" shape-rendering="crispEdges"/>
                    <text v-for="t in ticks" :key="`l${t}`"
                          :x="PAD.left - 8" :y="y(t) + 3.5" text-anchor="end"
                          font-size="10" fill="var(--viz-muted)">{{ t }}</text>
                </g>

                <!-- Columns -->
                <g v-for="(point, i) in points" :key="point.label">
                    <rect
                        :x="PAD.left + band * i" :y="PAD.top" :width="band" :height="plotH"
                        :fill="hovered === i ? 'var(--viz-grid)' : 'transparent'"
                        opacity="0.55"
                        @mouseenter="hovered = i" @mouseleave="hovered = null"
                    />
                    <g v-for="seg in columnSegments(point)" :key="`${i}-${seg.series}`" class="pointer-events-none">
                        <rect
                            :x="xCentre(i) - barW / 2" :y="seg.yTop" :width="barW" :height="seg.height"
                            :fill="seriesColor(seg.series)"
                            :rx="seg.isTop ? 4 : 0"
                        />
                        <!-- square off the bottom corners of a rounded top segment -->
                        <rect v-if="seg.isTop && seg.height > 4"
                              :x="xCentre(i) - barW / 2" :y="seg.yTop + seg.height - 4" :width="barW" height="4"
                              :fill="seriesColor(seg.series)"/>
                    </g>
                </g>

                <!-- Baseline -->
                <line :x1="PAD.left" :x2="W - PAD.right" :y1="y(0)" :y2="y(0)"
                      stroke="var(--viz-baseline)" stroke-width="1" shape-rendering="crispEdges"/>

                <!-- x labels: every other one, so they never collide -->
                <text v-for="(point, i) in points" :key="`x${point.label}`"
                      :x="xCentre(i)" :y="height - 8" text-anchor="middle" font-size="10"
                      :fill="hovered === i ? 'var(--viz-ink)' : 'var(--viz-muted)'"
                      v-show="i % 2 === 0 || hovered === i">{{ point.label }}</text>
            </svg>

            <!-- Tooltip -->
            <div v-if="tooltip"
                 class="pointer-events-none absolute top-0 z-10 w-44 -translate-x-1/2 rounded-lg border border-ink-100 bg-white p-3 shadow-lg"
                 :style="{ left: `clamp(5.5rem, ${tooltip.left}%, calc(100% - 5.5rem))` }"
                 role="status">
                <p class="text-xs font-semibold text-ink-900">{{ tooltip.title }}</p>
                <ul class="mt-2 space-y-1">
                    <li v-for="row in tooltip.rows" :key="row.name" class="flex items-center justify-between gap-3 text-xs">
                        <span class="flex items-center gap-1.5 text-ink-600">
                            <span class="h-2 w-2 rounded-[2px]" :style="{ background: row.color }"></span>{{ row.name }}
                        </span>
                        <span class="font-semibold text-ink-900">{{ row.value }}</span>
                    </li>
                </ul>
                <p class="mt-2 flex justify-between border-t border-ink-100 pt-1.5 text-xs">
                    <span class="text-ink-500">Total</span>
                    <span class="font-semibold text-ink-900">{{ tooltip.total }}</span>
                </p>
            </div>
        </div>

        <!-- Table view: the same numbers, no colour required -->
        <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 text-xs tracking-wide text-ink-500 uppercase">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Week</th>
                        <th v-for="name in series" :key="name" class="py-2 pr-4 text-right font-semibold">{{ name }}</th>
                        <th class="py-2 text-right font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-50">
                    <tr v-for="(point, i) in points" :key="point.label">
                        <td class="py-1.5 pr-4 text-ink-700">{{ point.fullLabel || point.label }}</td>
                        <td v-for="(v, s) in point.values" :key="s" class="py-1.5 pr-4 text-right text-ink-700">{{ v }}</td>
                        <td class="py-1.5 text-right font-semibold text-ink-900">{{ totals[i] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
