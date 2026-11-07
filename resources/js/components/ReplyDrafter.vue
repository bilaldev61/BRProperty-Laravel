<script setup>
import { ref } from 'vue'
import { postJson } from '../lib/api.js'
import { toast } from '../lib/alert.js'

const props = defineProps({
    endpoint: { type: String, required: true },
})

const draft = ref('')
const status = ref('idle') // idle | loading | ready | error

async function generate() {
    status.value = 'loading'

    try {
        const body = await postJson(props.endpoint, {})
        draft.value = body.draft
        status.value = 'ready'
    } catch (error) {
        status.value = 'idle'
        toast(error.message, 'error')
    }
}

async function copy() {
    try {
        await navigator.clipboard.writeText(draft.value)
        toast('Draft copied.', 'success')
    } catch {
        toast('Could not copy - select the text and copy it manually.', 'error')
    }
}
</script>

<template>
    <div>
        <button
            type="button"
            class="btn-outline w-full"
            :disabled="status === 'loading'"
            @click="generate"
        >
            <svg v-if="status === 'loading'" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
            </svg>
            {{ status === 'loading' ? 'Drafting...' : (draft ? 'Draft again' : 'Draft a reply') }}
        </button>

        <div v-if="draft" class="mt-3">
            <!--
                Editable on purpose. This is a first draft written from the
                listing record - the agent reads it, fixes it, and sends it
                themselves. Nothing here goes to the client on its own.
            -->
            <label class="label" for="reply-draft">Draft reply</label>
            <textarea id="reply-draft" v-model="draft" rows="10" class="input font-sans text-sm"></textarea>

            <p class="mt-1.5 text-xs text-ink-400">
                Written by AI from this listing's record. Read it before you send it - it is told to
                defer anything it cannot verify, but it is still a draft.
            </p>

            <button type="button" class="btn-primary mt-3 w-full" @click="copy">Copy draft</button>
        </div>
    </div>
</template>
