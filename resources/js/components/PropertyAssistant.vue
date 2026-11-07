<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { postJson } from '../lib/api.js'

const props = defineProps({
    endpoint: { type: String, required: true },
    property: { type: String, default: null }, // listing slug when opened on a listing page
    propertyTitle: { type: String, default: null },
    agentName: { type: String, required: true },
    agentTitle: { type: String, default: '' },
    agentPhoto: { type: String, default: null },
    phone: { type: String, required: true },
    phoneDial: { type: String, required: true },
    whatsapp: { type: String, required: true },
    privacyUrl: { type: String, default: null },
    aiPowered: { type: Boolean, default: true }, // false = rules-only (no API key)
})

const open = ref(false)
const loaded = ref(false)
const sending = ref(false)
const lead = ref(false)
const draft = ref('')
const messages = ref([]) // { role: 'user' | 'assistant' | 'error', content }

const scroller = ref(null)
const input = ref(null)

const suggestions = computed(() => props.property
    ? ['Is it still available?', "What size is the lot?", 'Can I arrange a showing?', 'Is the price negotiable?']
    : ['Homes in Steiner Ranch under $750K', 'Condos downtown under $500K', 'What areas do you cover?', '3-bedroom houses for rent'])

const greeting = computed(() => props.property
    ? `Hi! Ask me anything about ${props.propertyTitle} - size, price, features, or scheduling a showing.`
    : 'Hi! I can help you find a property from our listings. What are you looking for?')

async function toggle() {
    open.value = !open.value
    if (!open.value) return

    if (!loaded.value) await restore()
    await scrollToEnd()
    input.value?.focus()
}

/** Pick the chat back up after a page reload - it is kept server-side per listing. */
async function restore() {
    try {
        const url = new URL(props.endpoint, window.location.origin)
        if (props.property) url.searchParams.set('property', props.property)

        const response = await fetch(url, { headers: { Accept: 'application/json' } })
        if (response.ok) {
            const body = await response.json()
            messages.value = body.messages ?? []
            lead.value = !!body.lead
        }
    } catch {
        // Nothing to restore is the same as a fresh chat.
    }
    loaded.value = true
}

async function send(text = draft.value) {
    const message = text.trim()
    if (!message || sending.value) return

    messages.value.push({ role: 'user', content: message })
    draft.value = ''
    sending.value = true
    await scrollToEnd()

    try {
        const body = await postJson(props.endpoint, { message, property: props.property })
        messages.value.push({ role: 'assistant', content: body.reply })
        lead.value = !!body.lead
    } catch (error) {
        messages.value.push({
            role: 'error',
            content: error.validation
                ? Object.values(error.errors)[0]
                : error.message,
        })
    } finally {
        sending.value = false
        await scrollToEnd()
        input.value?.focus()
    }
}

function onEnter(event) {
    // Enter sends, Shift+Enter is a new line - the convention every chat uses.
    if (event.shiftKey || event.isComposing) return
    event.preventDefault()
    send()
}

async function scrollToEnd() {
    await nextTick()
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
}

/**
 * Turn the URLs the assistant cites into links, without ever rendering its
 * text as HTML - the reply is model output and is treated as untrusted.
 *
 * Models are told to write plain text but do not always listen, so markdown
 * links become real links and **bold** markers are dropped rather than shown.
 */
const LINK = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s)]+)/g

function segments(text) {
    const plain = text.replace(/\*\*(.+?)\*\*/g, '$1').replace(/^#+\s+/gm, '')
    const parts = []
    let last = 0

    for (const match of plain.matchAll(LINK)) {
        if (match.index > last) parts.push({ text: plain.slice(last, match.index) })
        const href = match[2] ?? match[3]
        parts.push({ text: match[1] ?? href, href, internal: href.startsWith(window.location.origin) })
        last = match.index + match[0].length
    }
    if (last < plain.length) parts.push({ text: plain.slice(last) })

    return parts
}

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) open.value = false
}
window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
    <div>
        <!-- Launcher: above the mobile call bar, and stacked above the WhatsApp bubble on desktop. -->
        <button
            type="button"
            class="fixed right-4 bottom-[calc(5.25rem_+_env(safe-area-inset-bottom))] z-40 flex h-14 items-center gap-2 rounded-full bg-ink-900 pr-5 pl-4 text-sm font-semibold text-sand-50 shadow-lg shadow-ink-900/25 transition hover:scale-[1.03] xl:right-6 xl:bottom-[5.75rem]"
            :class="{ 'max-sm:hidden': open }"
            :aria-expanded="open"
            aria-controls="property-assistant"
            @click="toggle"
        >
            <svg v-if="!open" class="h-6 w-6 text-brass-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6 2.6-2.6A2 2 0 0 1 8 17h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v14Z" />
            </svg>
            <svg v-else class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
            </svg>
            {{ open ? 'Close' : (property ? 'Ask about this home' : 'Ask our assistant') }}
        </button>

        <section
            v-show="open"
            id="property-assistant"
            role="dialog"
            aria-label="Property assistant"
            class="fixed inset-x-0 bottom-0 z-50 flex h-[88dvh] flex-col overflow-hidden rounded-t-2xl border border-ink-100 bg-white shadow-2xl shadow-ink-900/30 sm:inset-x-auto sm:right-6 sm:bottom-24 sm:h-[min(600px,calc(100dvh-8rem))] sm:w-[390px] sm:rounded-2xl xl:bottom-[11rem] xl:h-[min(600px,calc(100dvh-12.5rem))]"
        >
            <header class="flex items-center gap-3 bg-ink-900 px-4 py-3 text-sand-50">
                <img v-if="agentPhoto" :src="agentPhoto" alt="" class="h-9 w-9 rounded-full object-cover" aria-hidden="true">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ agentTitle ? `${agentName} · ${agentTitle}` : agentName }}</p>
                    <p class="truncate text-xs text-ink-300">{{ aiPowered ? 'Automated assistant · answers from our listings' : 'Automated · answers straight from our listings' }}</p>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-ink-300 hover:bg-white/10 hover:text-white" aria-label="Close assistant" @click="open = false">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </header>

            <div ref="scroller" class="flex-1 space-y-3 overflow-y-auto bg-sand-50 px-4 py-4" aria-live="polite">
                <div class="max-w-[85%] rounded-2xl rounded-bl-md bg-white px-4 py-2.5 text-[15px] leading-relaxed text-ink-800 shadow-sm">
                    {{ greeting }}
                </div>

                <div v-if="loaded && messages.length === 0" class="flex flex-wrap gap-2 pt-1">
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        type="button"
                        class="rounded-full border border-teal-200 bg-white px-3 py-1.5 text-left text-sm text-teal-700 hover:border-teal-400 hover:bg-teal-50"
                        @click="send(suggestion)"
                    >
                        {{ suggestion }}
                    </button>
                </div>

                <div
                    v-for="(message, i) in messages"
                    :key="i"
                    class="flex"
                    :class="{ 'justify-end': message.role === 'user' }"
                >
                    <div
                        class="max-w-[85%] rounded-2xl px-4 py-2.5 text-[15px] leading-relaxed break-words whitespace-pre-line"
                        :class="{
                            'rounded-br-md bg-ink-900 text-sand-50': message.role === 'user',
                            'rounded-bl-md bg-white text-ink-800 shadow-sm': message.role === 'assistant',
                            'rounded-bl-md border border-red-200 bg-red-50 text-red-800': message.role === 'error',
                        }"
                    >
                        <template v-for="(part, j) in segments(message.content)" :key="j">
                            <a
                                v-if="part.href"
                                :href="part.href"
                                class="font-medium text-teal-600 underline underline-offset-2"
                                :target="part.internal ? null : '_blank'"
                                :rel="part.internal ? null : 'noopener noreferrer'"
                            >{{ part.internal && part.text === part.href ? 'View listing →' : part.text }}</a>
                            <template v-else>{{ part.text }}</template>
                        </template>
                    </div>
                </div>

                <div v-if="sending" class="flex" aria-label="Assistant is typing">
                    <div class="flex gap-1 rounded-2xl rounded-bl-md bg-white px-4 py-3.5 shadow-sm">
                        <span class="h-2 w-2 animate-bounce rounded-full bg-ink-300" />
                        <span class="h-2 w-2 animate-bounce rounded-full bg-ink-300 [animation-delay:150ms]" />
                        <span class="h-2 w-2 animate-bounce rounded-full bg-ink-300 [animation-delay:300ms]" />
                    </div>
                </div>

                <p v-if="lead" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-xs text-emerald-800">
                    Your details are with our team - {{ agentName }} will be in touch.
                </p>
            </div>

            <div class="border-t border-ink-100 bg-white px-3 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                <form class="flex items-end gap-2" @submit.prevent="send()">
                    <label for="assistant-input" class="sr-only">Your question</label>
                    <textarea
                        id="assistant-input"
                        ref="input"
                        v-model="draft"
                        rows="1"
                        maxlength="1000"
                        placeholder="Ask about price, size, areas..."
                        class="input max-h-28 min-h-11 flex-1 resize-none !py-2.5 text-[15px]"
                        @keydown.enter="onEnter"
                    />
                    <button type="submit" class="btn-primary h-11 w-11 shrink-0 !p-0" :disabled="sending || !draft.trim()" aria-label="Send">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </button>
                </form>
                <p class="mt-2 text-center text-[11px] leading-snug text-ink-400">
                    {{ aiPowered ? 'Automated answers from our listing records' : 'Answers from our listing records' }} - our team confirms the details.
                    <a :href="`tel:${phoneDial}`" class="underline">Call</a> or
                    <a :href="`https://wa.me/${whatsapp}`" target="_blank" rel="noopener noreferrer" class="underline">WhatsApp</a>
                    anytime.
                    <a v-if="privacyUrl" :href="privacyUrl" class="underline">Privacy</a>
                </p>
            </div>
        </section>
    </div>
</template>
