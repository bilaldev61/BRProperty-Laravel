import { postJson } from './lib/api.js'
import { successPopup, toast } from './lib/alert.js'

/*
 * Progressive enhancement for <x-enquiry-form>.
 *
 * The fields are server HTML and stay that way - this file never creates or
 * replaces them. It only takes over the submit event so the message goes out in
 * the background instead of reloading the page. With JavaScript off, nothing
 * here runs and the browser submits the same form normally.
 */
document.querySelectorAll('form[data-enquiry-form]').forEach((form) => {
    // Native validation is the right answer for a no-JS visitor; we check the
    // values ourselves once this script is in charge.
    form.noValidate = true

    const root = form.closest('[data-enquiry]')
    const banner = form.querySelector('[data-form-error]')
    const submit = form.querySelector('[data-submit]')
    const submitText = form.querySelector('[data-submit-text]')
    const spinner = form.querySelector('[data-submit-spinner]')
    const success = root?.querySelector('[data-success]')
    const successText = root?.querySelector('[data-success-text]')
    const resetButton = root?.querySelector('[data-success-reset]')

    const fieldErrors = {}
    form.querySelectorAll('[data-error]').forEach((node) => {
        fieldErrors[node.dataset.error] = node
    })

    const label = submitText?.textContent ?? 'Send'
    let busy = false

    function clearErrors() {
        banner.hidden = true
        banner.textContent = ''

        Object.entries(fieldErrors).forEach(([field, node]) => {
            node.textContent = ''
            node.hidden = true
            form.elements[field]?.classList.remove('input-error')
            form.elements[field]?.removeAttribute('aria-invalid')
        })
    }

    function showErrors(errors) {
        let firstField = null

        Object.entries(errors).forEach(([field, message]) => {
            const input = form.elements[field]

            input?.classList.add('input-error')
            input?.setAttribute('aria-invalid', 'true')

            const node = fieldErrors[field]

            if (node) {
                node.textContent = message
                node.hidden = false
                firstField = firstField ?? field
            } else if (banner.hidden) {
                // No slot on screen for this field - say it at the top instead
                // of leaving a failed rule invisible.
                banner.textContent = message
                banner.hidden = false
                firstField = firstField ?? field
            }
        })

        if (firstField) {
            form.elements[firstField]?.focus({ preventScroll: true })
            form.scrollIntoView({ behavior: 'smooth', block: 'center' })
        }
    }

    function setBusy(state) {
        busy = state
        submit.disabled = state
        submitText.textContent = state ? 'Sending...' : label
        spinner.classList.toggle('hidden', !state)
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault()

        if (busy) return

        clearErrors()
        setBusy(true)

        const payload = Object.fromEntries(new FormData(form).entries())
        delete payload._token

        Object.entries(payload).forEach(([key, value]) => {
            if (value === '') payload[key] = null
        })

        // Honeypot stays out of the request unless a bot filled it in.
        if (!payload.company) delete payload.company

        try {
            const body = await postJson(form.action, payload)

            if (successText) successText.textContent = body.message
            form.hidden = true
            success.hidden = false
            success.scrollIntoView({ behavior: 'smooth', block: 'center' })

            successPopup({
                title: success.dataset.successTitle || 'Message sent',
                text: body.message,
            })
        } catch (error) {
            if (error.validation) {
                showErrors(error.errors)
            } else {
                banner.textContent = error.message
                banner.hidden = false
                toast(error.message, 'error')
            }
        } finally {
            setBusy(false)
        }
    })

    resetButton?.addEventListener('click', () => {
        form.reset()
        clearErrors()
        success.hidden = true
        form.hidden = false
        setBusy(false)
        form.querySelector('input:not([type="hidden"]), select, textarea')?.focus()
    })
})
