<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
    images: { type: Array, default: () => [] }, // [{ url, alt }]
    title: { type: String, default: '' },
})

const index = ref(0)
const lightbox = ref(false)

const active = computed(() => props.images[index.value] ?? null)
const hasMany = computed(() => props.images.length > 1)

function step(delta) {
    if (!props.images.length) return
    index.value = (index.value + delta + props.images.length) % props.images.length
}

function onKeydown(event) {
    if (!lightbox.value) return
    if (event.key === 'Escape') lightbox.value = false
    if (event.key === 'ArrowRight') step(1)
    if (event.key === 'ArrowLeft') step(-1)
}

watch(lightbox, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
})

window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
})

/* Touch swipe on the main image */
let startX = 0
function onTouchStart(e) {
    startX = e.changedTouches[0].clientX
}
function onTouchEnd(e) {
    const dx = e.changedTouches[0].clientX - startX
    if (Math.abs(dx) > 45) step(dx < 0 ? 1 : -1)
}
</script>

<template>
    <div>
        <div class="relative overflow-hidden rounded-card bg-ink-100">
            <img
                v-if="active"
                :src="active.url"
                :alt="active.alt || title"
                class="aspect-4/3 w-full cursor-zoom-in object-cover sm:aspect-16/9"
                loading="eager"
                @click="lightbox = true"
                @touchstart.passive="onTouchStart"
                @touchend.passive="onTouchEnd"
            />

            <template v-if="hasMany">
                <button
                    type="button"
                    class="absolute top-1/2 left-3 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink-900 shadow-md transition hover:bg-white"
                    aria-label="Previous image"
                    @click="step(-1)"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 5-7 7 7 7" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="absolute top-1/2 right-3 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink-900 shadow-md transition hover:bg-white"
                    aria-label="Next image"
                    @click="step(1)"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                    </svg>
                </button>

                <div class="absolute right-3 bottom-3 rounded-full bg-ink-950/70 px-3 py-1 text-xs font-medium text-white">
                    {{ index + 1 }} / {{ images.length }}
                </div>
            </template>
        </div>

        <div v-if="hasMany" class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6">
            <button
                v-for="(image, i) in images"
                :key="image.url"
                type="button"
                class="overflow-hidden rounded-lg border-2 transition"
                :class="i === index ? 'border-brass-500' : 'border-transparent opacity-70 hover:opacity-100'"
                :aria-label="`View image ${i + 1}`"
                @click="index = i"
            >
                <img :src="image.url" :alt="image.alt || title" class="aspect-4/3 w-full object-cover" loading="lazy" />
            </button>
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-150"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="lightbox"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/98 p-4"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="`${title} photo gallery`"
                    @click.self="lightbox = false"
                >
                    <button
                        type="button"
                        class="absolute top-4 right-4 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                        aria-label="Close gallery"
                        @click="lightbox = false"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>

                    <img
                        v-if="active"
                        :src="active.url"
                        :alt="active.alt || title"
                        class="max-h-[85vh] max-w-full rounded-lg object-contain"
                        @touchstart.passive="onTouchStart"
                        @touchend.passive="onTouchEnd"
                    />

                    <template v-if="hasMany">
                        <button
                            type="button"
                            class="absolute top-1/2 left-4 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                            aria-label="Previous image"
                            @click.stop="step(-1)"
                        >
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15 5-7 7 7 7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="absolute top-1/2 right-4 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                            aria-label="Next image"
                            @click.stop="step(1)"
                        >
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                            </svg>
                        </button>
                        <p class="absolute bottom-5 left-1/2 -translate-x-1/2 text-sm text-white/70">
                            {{ index + 1 }} of {{ images.length }}
                        </p>
                    </template>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
