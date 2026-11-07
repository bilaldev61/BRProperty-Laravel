<script setup>
import { computed, reactive, ref } from 'vue'
import { postJson } from '../lib/api.js'
import { successPopup, toast } from '../lib/alert.js'

/*
 * Enquiry form.
 *
 * Vue owns the fields: the island container is empty (skeleton only) in the
 * server HTML and this component prints the inputs after mount. That is the
 * layout-shift half of the trade-off - see the fallback markup on each page.
 */
const props = defineProps({
    type: { type: String, default: 'contact' },
    endpoint: { type: String, default: '/enquiries' },
    propertyId: { type: [Number, String], default: null },
    propertyTitle: { type: String, default: '' },
    heading: { type: String, default: '' },
    subheading: { type: String, default: '' },
    submitLabel: { type: String, default: 'Send enquiry' },
    compact: { type: Boolean, default: false },
    types: { type: Object, default: () => ({}) },
    tone: { type: String, default: 'glass' },
    message: { type: String, default: '' },
})

const isAppraisal = computed(() => props.type === 'appraisal')
const onGlass = computed(() => props.tone === 'glass')
const labelClass = computed(() => (onGlass.value ? 'label-on-dark' : 'label'))
const hintClass = computed(() => (onGlass.value ? 'font-normal text-sand-300' : 'font-normal text-ink-400'))
const errorClass = computed(() => (onGlass.value ? 'mt-1 text-sm text-red-200' : 'mt-1 text-sm text-red-600'))
const mutedClass = computed(() => (onGlass.value ? 'text-sand-300' : 'text-ink-400'))
const successTitle = computed(() => (isAppraisal.value ? 'Appraisal requested' : 'Message sent'))
const typeOptions = computed(() => Object.entries(props.types || {}))

const defaultMessage = computed(() =>
    props.message ||
    (props.type === 'property' && props.propertyTitle
        ? `Hi, I'd like more information about ${props.propertyTitle}.`
        : ''),
)

const form = reactive({
    name: '',
    email: '',
    phone: '',
    address: '',
    suburb: '',
    property_type: '',
    bedrooms: '',
    timeframe: '',
    message: defaultMessage.value,
    company: '',
})

const errors = ref({})
const banner = ref('')
const sending = ref(false)
const sent = ref(false)

const appraisalFields = ['address', 'suburb', 'property_type', 'bedrooms', 'timeframe']
const knownFields = ['name', 'email', 'phone', 'address', 'suburb', 'property_type', 'bedrooms', 'timeframe', 'message']

function errorFor(field) {
    return errors.value[field] || ''
}

function buildPayload() {
    const payload = { type: props.type }

    if (props.propertyId) {
        payload.property_id = props.propertyId
    }

    for (const [key, value] of Object.entries(form)) {
        if (value === '' || value === null || value === undefined) {
            continue
        }

        if (!isAppraisal.value && appraisalFields.includes(key)) {
            continue
        }

        payload[key] = value
    }

    return payload
}

async function submit() {
    if (sending.value) {
        return
    }

    sending.value = true
    errors.value = {}
    banner.value = ''

    try {
        const body = await postJson(props.endpoint, buildPayload())

        sent.value = true
        successPopup({ title: successTitle.value, text: body.message || '' })
    } catch (error) {
        if (error.validation) {
            errors.value = error.errors || {}
            const unknown = Object.entries(errors.value).filter(([field]) => !knownFields.includes(field))
            banner.value = unknown.length ? unknown.map(([, message]) => message).join(' ') : ''
        } else {
            banner.value = error.message
            toast(error.message, 'error')
        }
    } finally {
        sending.value = false
    }
}

function sendAnother() {
    for (const key of Object.keys(form)) {
        form[key] = ''
    }

    errors.value = {}
    banner.value = ''
    sent.value = false
}
</script>

<template>
    <div>
        <div v-if="heading || subheading" class="mb-6">
            <h2 v-if="heading" :class="onGlass ? 'text-2xl font-semibold text-white sm:text-3xl' : 'text-2xl'">{{ heading }}</h2>
            <p v-if="subheading" class="mt-2" :class="onGlass ? 'text-sand-200' : 'text-ink-500'">{{ subheading }}</p>
        </div>

        <form v-if="!sent" class="space-y-4" novalidate @submit.prevent="submit">
            <p
                v-if="banner"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert">{{ banner }}</p>

            <div :class="compact ? 'space-y-4' : 'grid gap-4 sm:grid-cols-2'">
                <div>
                    <label :class="labelClass" :for="`${type}-name`">Your name</label>
                    <input
                        :id="`${type}-name`"
                        v-model="form.name"
                        name="name"
                        type="text"
                        autocomplete="name"
                        class="input"
                        placeholder="Jordan Smith"
                        maxlength="120">
                    <p v-if="errorFor('name')" :class="errorClass">{{ errorFor('name') }}</p>
                </div>

                <div>
                    <label :class="labelClass" :for="`${type}-email`">Email</label>
                    <input
                        :id="`${type}-email`"
                        v-model="form.email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        class="input"
                        placeholder="jordan@example.com"
                        maxlength="180">
                    <p v-if="errorFor('email')" :class="errorClass">{{ errorFor('email') }}</p>
                </div>
            </div>

            <div>
                <label :class="labelClass" :for="`${type}-phone`">
                    Phone <span :class="hintClass">(optional)</span>
                </label>
                <input
                    :id="`${type}-phone`"
                    v-model="form.phone"
                    name="phone"
                    type="tel"
                    autocomplete="tel"
                    class="input"
                    placeholder="+971 50 123 4567"
                    maxlength="40">
                <p v-if="errorFor('phone')" :class="errorClass">{{ errorFor('phone') }}</p>
            </div>

            <template v-if="isAppraisal">
                <div>
                    <label :class="labelClass" for="appraisal-address">Property address</label>
                    <input
                        id="appraisal-address"
                        v-model="form.address"
                        name="address"
                        type="text"
                        autocomplete="street-address"
                        class="input"
                        placeholder="Villa 14, Dubai Hills Estate"
                        maxlength="200">
                    <p v-if="errorFor('address')" :class="errorClass">{{ errorFor('address') }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="appraisal-suburb">Area</label>
                        <input
                            id="appraisal-suburb"
                            v-model="form.suburb"
                            name="suburb"
                            type="text"
                            class="input"
                            placeholder="Dubai Hills"
                            maxlength="120">
                    </div>
                    <div>
                        <label :class="labelClass" for="appraisal-type">Property type</label>
                        <select id="appraisal-type" v-model="form.property_type" name="property_type" class="input">
                            <option v-for="[key, label] in typeOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="appraisal-beds">Bedrooms</label>
                        <input
                            id="appraisal-beds"
                            v-model="form.bedrooms"
                            name="bedrooms"
                            type="number"
                            min="0"
                            max="20"
                            class="input"
                            placeholder="4">
                        <p v-if="errorFor('bedrooms')" :class="errorClass">{{ errorFor('bedrooms') }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="appraisal-timeframe">When are you looking to sell?</label>
                        <select id="appraisal-timeframe" v-model="form.timeframe" name="timeframe" class="input">
                            <option value="">Please choose</option>
                            <option>ASAP</option>
                            <option>1-3 months</option>
                            <option>3-6 months</option>
                            <option>6-12 months</option>
                            <option>Just researching</option>
                        </select>
                    </div>
                </div>
            </template>

            <div>
                <label :class="labelClass" :for="`${type}-message`">
                    Message <span v-if="isAppraisal" :class="hintClass">(optional)</span>
                </label>
                <textarea
                    :id="`${type}-message`"
                    v-model="form.message"
                    name="message"
                    rows="4"
                    class="input"
                    placeholder="Tell us a little about what you need..."
                    maxlength="4000"></textarea>
                <p v-if="errorFor('message')" :class="errorClass">{{ errorFor('message') }}</p>
            </div>

            <div class="absolute h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>Company<input v-model="form.company" name="company" type="text" tabindex="-1" autocomplete="off"></label>
            </div>

            <button type="submit" class="btn-accent w-full" :disabled="sending">
                <svg v-if="sending" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
                </svg>
                <span>{{ sending ? 'Sending...' : submitLabel }}</span>
            </button>

            <p class="text-center text-xs" :class="mutedClass">
                Your details stay private and are never shared with third parties.
            </p>
        </form>

        <div v-else class="rounded-card border border-emerald-200 bg-emerald-50 p-6 text-center" role="status" aria-live="polite">
            <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-emerald-600">
                <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                </svg>
            </div>
            <p class="font-semibold text-emerald-900">{{ successTitle }}</p>
            <p class="mt-1 text-sm text-emerald-800">Thanks - we have your details and will reply within one business day.</p>
            <button type="button" class="mt-4 text-sm font-semibold text-emerald-900 underline" @click="sendAnother">
                Send another
            </button>
        </div>
    </div>
</template>
