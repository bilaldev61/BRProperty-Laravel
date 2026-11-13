/**
 * Turn the URLs the assistant cites into links, without ever rendering its
 * text as HTML - the reply is model output and is treated as untrusted.
 *
 * Only the site's own pages and WhatsApp may become clickable. A link the
 * model is tricked into writing must not turn into a clickable trap pointing
 * at an arbitrary site, so anything off the allowlist stays plain text.
 */

const LINK = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s)]+)/g

const ALLOWED_HOSTS = new Set(['wa.me', 'www.wa.me'])

/**
 * Resolve a matched URL to a safe link target, or null when the destination is
 * not trusted. The origin is compared exactly, never by prefix, so a look-alike
 * host such as "site.com.evil.com" cannot pass for the site itself.
 *
 * @returns {{href: string, internal: boolean}|null}
 */
export function linkTarget(href, origin) {
    try {
        const url = new URL(href, origin)
        if (url.origin === origin) return { href: url.href, internal: true }
        if (ALLOWED_HOSTS.has(url.hostname)) return { href: url.href, internal: false }
    } catch {
        // Not a parseable URL - treat it as untrusted.
    }

    return null
}

/**
 * Split a reply into plain-text and link parts. Models are told to write plain
 * text but do not always listen, so markdown links become real links and
 * **bold** markers are dropped rather than shown.
 *
 * @returns {Array<{text: string, href?: string, internal?: boolean}>}
 */
export function segments(text, origin) {
    const plain = text.replace(/\*\*(.+?)\*\*/g, '$1').replace(/^#+\s+/gm, '')
    const parts = []
    let last = 0

    for (const match of plain.matchAll(LINK)) {
        if (match.index > last) parts.push({ text: plain.slice(last, match.index) })

        const target = linkTarget(match[2] ?? match[3], origin)
        if (target) {
            parts.push({ text: match[1] ?? target.href, href: target.href, internal: target.internal })
        } else {
            // Untrusted destination: show what was written, never a link.
            parts.push({ text: match[0] })
        }

        last = match.index + match[0].length
    }

    if (last < plain.length) parts.push({ text: plain.slice(last) })

    return parts
}