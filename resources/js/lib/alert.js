import Swal from 'sweetalert2'

/*
 * SweetAlert2, themed to the site palette in one place so every popup and toast
 * looks like it belongs here rather than like a library default.
 */
const INK = '#16211f'
const BRASS = '#b8894a'
const TEAL = '#0d7a68'
const CLAY = '#a4482d'

const base = {
    buttonsStyling: false,
    customClass: {
        popup: 'rounded-card border border-ink-100 shadow-xl',
        title: 'font-display text-2xl text-ink-900',
        htmlContainer: 'text-ink-600 text-[15px]',
        confirmButton: 'btn-primary mx-1',
        denyButton: 'btn-outline mx-1',
        cancelButton: 'btn-outline mx-1',
    },
}

export const swal = Swal.mixin(base)

/** Big centred confirmation. Resolves true when the user goes ahead. */
export async function confirmAction({
    title = 'Are you sure?',
    text = '',
    confirmText = 'Yes, continue',
    cancelText = 'Cancel',
    danger = false,
} = {}) {
    const result = await swal.fire({
        title,
        text,
        icon: danger ? 'warning' : 'question',
        iconColor: danger ? CLAY : BRASS,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
        focusCancel: danger,
        customClass: {
            ...base.customClass,
            confirmButton: danger ? 'btn mx-1 bg-clay-600 text-white hover:bg-clay-700' : 'btn-primary mx-1',
        },
    })

    return result.isConfirmed
}

export function successPopup({ title = 'Done', text = '' } = {}) {
    return swal.fire({
        title,
        text,
        icon: 'success',
        iconColor: TEAL,
        confirmButtonText: 'Close',
    })
}

const toastMixin = Swal.mixin({
    ...base,
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    customClass: {
        popup: 'rounded-xl border border-ink-100 shadow-lg',
        title: 'font-sans text-sm font-medium text-ink-900',
    },
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer)
        el.addEventListener('mouseleave', Swal.resumeTimer)
    },
})

export function toast(title, icon = 'success') {
    return toastMixin.fire({ title, icon, iconColor: icon === 'error' ? CLAY : TEAL })
}

export { INK, BRASS, TEAL, CLAY }
