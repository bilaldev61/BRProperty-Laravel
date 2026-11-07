/**
 * Small fetch wrapper that speaks Laravel: sends the CSRF token, asks for JSON
 * and turns a 422 into a plain { field: message } object.
 */
export async function postJson(url, payload) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(payload),
    })

    const body = await response.json().catch(() => ({}))

    if (response.status === 422) {
        const errors = {}
        for (const [field, messages] of Object.entries(body.errors ?? {})) {
            errors[field] = Array.isArray(messages) ? messages[0] : messages
        }
        throw Object.assign(new Error('validation'), { validation: true, errors })
    }

    if (!response.ok) {
        throw Object.assign(new Error(body.message || 'Something went wrong. Please try again.'), {
            validation: false,
        })
    }

    return body
}
