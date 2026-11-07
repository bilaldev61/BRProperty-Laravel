<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
    links: { type: Array, default: () => [] },
    phone: { type: String, default: '' },
    phoneDial: { type: String, default: '' },
    appraisalUrl: { type: String, default: '' },
    adminUrl: { type: String, default: '' },
    current: { type: String, default: '' },
    // The bar is dark glass on every page, so the trigger has to read light.
    light: { type: Boolean, default: false },
})

const open = ref(false)

// Track which dropdown groups are expanded. The group holding the current page
// starts open so the active link is visible without another tap.
const expanded = ref(
    Object.fromEntries(
        (props.links ?? [])
            .filter((l) => l.children)
            .map((l) => [l.key, l.children.some((c) => c.key === props.current)]),
    ),
)

function toggle(key) {
    expanded.value[key] = !expanded.value[key]
}

function groupActive(link) {
    return props.current === link.key || link.children.some((c) => c.key === props.current)
}

watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : ''
})

function onKeydown(event) {
    if (event.key === 'Escape') open.value = false
}

window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
})
</script>

<template>
    <div class="xl:hidden">
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-full transition-colors"
            :class="light ? 'text-sand-50 hover:bg-white/10' : 'text-ink-900'"
            :aria-expanded="open"
            aria-controls="mobile-menu"
            aria-label="Open menu"
            @click="open = true"
        >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" d="M3.5 7h17M3.5 12h17M3.5 17h17" />
            </svg>
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-150"
                leave-to-class="opacity-0"
            >
                <div v-if="open" class="fixed inset-0 z-40 bg-ink-950/50" @click="open = false" />
            </Transition>

            <Transition
                enter-active-class="transition-transform duration-250 ease-out"
                enter-from-class="translate-x-full"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-to-class="translate-x-full"
            >
                <nav
                    v-if="open"
                    id="mobile-menu"
                    class="fixed inset-y-0 right-0 z-50 flex w-[86%] max-w-sm flex-col bg-sand-50 shadow-2xl"
                    aria-label="Mobile"
                >
                    <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
                        <span class="font-display text-xl">Menu</span>
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-lg text-ink-600"
                            aria-label="Close menu"
                            @click="open = false"
                        >
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <ul class="flex-1 overflow-y-auto px-2 py-3">
                        <li v-for="link in links" :key="link.key" class="mb-0.5">
                            <template v-if="link.children">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-lg px-4 py-3 text-lg font-medium transition-colors"
                                    :class="groupActive(link) ? 'text-ink-900' : 'text-ink-700 hover:bg-ink-100'"
                                    :aria-expanded="expanded[link.key]"
                                    @click="toggle(link.key)"
                                >
                                    {{ link.label }}
                                    <svg
                                        class="h-5 w-5 text-ink-400 transition-transform"
                                        :class="expanded[link.key] ? 'rotate-180' : ''"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                                <ul v-show="expanded[link.key]" class="mb-1 ml-3 space-y-0.5 border-l border-ink-100 pl-2">
                                    <li v-for="child in link.children" :key="child.key">
                                        <a
                                            :href="child.url"
                                            class="block rounded-lg px-4 py-2.5 text-base font-medium transition-colors"
                                            :class="current === child.key
                                                ? 'bg-ink-900 text-sand-50'
                                                : 'text-ink-600 hover:bg-ink-100'"
                                        >
                                            {{ child.label }}
                                        </a>
                                    </li>
                                </ul>
                            </template>

                            <a
                                v-else
                                :href="link.url"
                                class="block rounded-lg px-4 py-3 text-lg font-medium transition-colors"
                                :class="current === link.key
                                    ? 'bg-ink-900 text-sand-50'
                                    : 'text-ink-700 hover:bg-ink-100'"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>

                    <div class="space-y-2 border-t border-ink-100 p-4">
                        <a :href="`tel:${phoneDial}`" class="btn-primary w-full">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.3 2.2Z" />
                            </svg>
                            Call {{ phone }}
                        </a>
                        <a :href="appraisalUrl" class="btn-outline w-full">Free appraisal</a>
                        <a v-if="adminUrl" :href="adminUrl" class="btn-outline w-full">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/>
                                <rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>
                            </svg>
                            Admin panel
                        </a>
                    </div>
                </nav>
            </Transition>
        </Teleport>
    </div>
</template>
