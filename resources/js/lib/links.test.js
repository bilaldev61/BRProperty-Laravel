import { test } from 'node:test'
import assert from 'node:assert/strict'
import { linkTarget, segments } from './links.js'

const ORIGIN = 'https://brproperty.test'

const links = (parts) => parts.filter((p) => p.href)

test('a bare listing URL on our own origin becomes an internal link', () => {
    const parts = segments('See https://brproperty.test/properties/villa-1', ORIGIN)

    assert.deepEqual(links(parts), [
        { text: 'https://brproperty.test/properties/villa-1', href: 'https://brproperty.test/properties/villa-1', internal: true },
    ])
})

test('a markdown link to our own origin keeps its label and stays internal', () => {
    const parts = segments('[This villa](https://brproperty.test/properties/villa-1)', ORIGIN)

    assert.deepEqual(links(parts), [
        { text: 'This villa', href: 'https://brproperty.test/properties/villa-1', internal: true },
    ])
})

test('an external bare URL is shown as plain text, never a link', () => {
    const parts = segments('Go to https://evil.com now', ORIGIN)

    assert.deepEqual(links(parts), [])
    assert.equal(parts.map((p) => p.text).join(''), 'Go to https://evil.com now')
})

test('a deceptive markdown link to an external site cannot become a clickable trap', () => {
    // The phishing case: a trusted-looking label hiding an attacker destination.
    const parts = segments('[View listing →](https://evil.com)', ORIGIN)

    assert.deepEqual(links(parts), [])
    assert.equal(parts[0].text, '[View listing →](https://evil.com)')
})

test('a look-alike host that merely prefixes our origin is not trusted', () => {
    // "brproperty.test.evil.com".startsWith("brproperty.test") is true - the old
    // prefix check let this through. Exact origin comparison must reject it.
    const parts = segments('https://brproperty.test.evil.com/properties/villa-1', ORIGIN)

    assert.deepEqual(links(parts), [])
})

test('a WhatsApp link is allowed but treated as external', () => {
    const parts = segments('Chat https://wa.me/971500000000', ORIGIN)

    assert.deepEqual(links(parts), [
        { text: 'https://wa.me/971500000000', href: 'https://wa.me/971500000000', internal: false },
    ])
})

test('a javascript: URI is never linkified', () => {
    const parts = segments('javascript:alert(document.domain)', ORIGIN)

    assert.deepEqual(links(parts), [])
})

test('bold markers are dropped and surrounding text is preserved', () => {
    const parts = segments('It is **bright** and airy', ORIGIN)

    assert.deepEqual(parts, [{ text: 'It is bright and airy' }])
})

test('text around a trusted link is kept in order', () => {
    const parts = segments('See https://brproperty.test/properties/villa-1 for details', ORIGIN)

    assert.deepEqual(parts, [
        { text: 'See ' },
        { text: 'https://brproperty.test/properties/villa-1', href: 'https://brproperty.test/properties/villa-1', internal: true },
        { text: ' for details' },
    ])
})

test('plain text with no URL is a single part', () => {
    assert.deepEqual(segments('Just a normal reply.', ORIGIN), [{ text: 'Just a normal reply.' }])
})

test('linkTarget resolves trusted origins and rejects everything else', () => {
    assert.deepEqual(linkTarget('https://brproperty.test/x', ORIGIN), { href: 'https://brproperty.test/x', internal: true })
    assert.deepEqual(linkTarget('https://wa.me/971500000000', ORIGIN), { href: 'https://wa.me/971500000000', internal: false })
    assert.equal(linkTarget('https://evil.com', ORIGIN), null)
    assert.equal(linkTarget('https://', ORIGIN), null)
})