import { createApp } from 'vue'
import { confirmAction, toast } from './lib/alert.js'

// Enquiry forms are server HTML; this layer only adds the background submit.
import './enquiry-form.js'

import MobileNav from './components/MobileNav.vue'
import PropertyGallery from './components/PropertyGallery.vue'
import TestimonialCarousel from './components/TestimonialCarousel.vue'
import StackedBarChart from './components/StackedBarChart.vue'
import HBarChart from './components/HBarChart.vue'
import ReplyDrafter from './components/ReplyDrafter.vue'
import PropertyAssistant from './components/PropertyAssistant.vue'

/*
 * Vue islands.
 *
 * Every page is server-rendered Blade so Google gets real HTML. Vue only takes
 * over the pieces that are genuinely interactive. Drop this on any element:
 *
 *   <div data-vue="PropertyGallery" data-props='@json([...])'></div>
 *
 * Enquiry forms are not islands: Laravel prints their fields directly and
 * ./enquiry-form.js only takes over the submit event.
 */
const components = {
    MobileNav,
    PropertyGallery,
    TestimonialCarousel,
    StackedBarChart,
    HBarChart,
    ReplyDrafter,
    PropertyAssistant,
}

document.querySelectorAll('[data-vue]').forEach((el) => {
    const name = el.dataset.vue
    const component = components[name]

    if (!component) {
        console.warn(`[islands] Unknown component "${name}"`)
        return
    }

    let props = {}
    try {
        props = el.dataset.props ? JSON.parse(el.dataset.props) : {}
    } catch (error) {
        console.error(`[islands] Bad props JSON on "${name}"`, error)
    }

    createApp(component, props).mount(el)
})


/*
 * Flash messages arrive as a data attribute on <body> and surface as a toast,
 * so a redirect after saving still tells you what happened.
 */
const flash = document.body.dataset.flash
if (flash) {
    toast(flash, document.body.dataset.flashType || 'success')
}

/*
 * Any form carrying data-confirm asks first. Progressive: without JS the form
 * still submits, which is the correct fallback for a destructive action guarded
 * by an authenticated POST.
 */
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        if (form.dataset.confirmed === 'yes') return

        event.preventDefault()

        const ok = await confirmAction({
            title: form.dataset.confirmTitle || 'Are you sure?',
            text: form.dataset.confirm,
            confirmText: form.dataset.confirmButton || 'Yes, delete it',
            danger: form.dataset.confirmDanger !== 'false',
        })

        if (ok) {
            form.dataset.confirmed = 'yes'
            form.requestSubmit()
        }
    })
})

/*
 * Admin menu: a static column from lg up, a slide-over drawer below it.
 * The panel is hidden by translating it out of view rather than by removing it,
 * so the links stay in the DOM for search engines (noindex anyway) and for
 * assistive tech that reads the menu before it is opened. Escape closes it and
 * the page behind stops scrolling, because a panel covering the screen must not
 * be dismissable only by tapping outside it.
 */
document.querySelectorAll('[data-drawer-toggle]').forEach((button) => {
    const panel = document.getElementById(button.getAttribute('aria-controls'))
    const backdrop = document.querySelector('[data-drawer-backdrop]')
    if (!panel) return

    const setOpen = (open) => {
        panel.classList.toggle('-translate-x-full', !open)
        backdrop?.classList.toggle('hidden', !open)
        button.setAttribute('aria-expanded', String(open))
        document.documentElement.classList.toggle('overflow-hidden', open)
    }

    button.addEventListener('click', () => setOpen(panel.classList.contains('-translate-x-full')))
    backdrop?.addEventListener('click', () => setOpen(false))

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false)
    })

    // Following a link navigates; leaving the drawer open behind the new page
    // would show it already open on arrival.
    panel.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)))
})
