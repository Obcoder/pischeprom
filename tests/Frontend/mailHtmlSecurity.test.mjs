import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { parse } from '@vue/compiler-sfc'
import { mailHtmlDocument } from '../../resources/js/Components/Contacts/Emails/mailHtmlDocument.js'

const component = name => parse(readFileSync(`resources/js/Components/Contacts/Emails/${name}.vue`, 'utf8')).descriptor
const elements = node => [node, ...(node.children || []).flatMap(elements)]

test('email HTML keeps its own origin and cannot acquire script, form or parent navigation privileges', () => {
    const frame = elements(component('MailHtmlBody').template.ast).find(node => node.tag === 'iframe')
    assert.ok(frame)
    const attribute = name => frame.props.find(prop => prop.type === 6 && prop.name === name)?.value?.content
    assert.notEqual(attribute('sandbox'), undefined, 'sandbox must remain present even when empty')
    for (const capability of attribute('sandbox').split(/\s+/).filter(Boolean)) {
        assert.ok(['allow-popups', 'allow-popups-to-escape-sandbox'].includes(capability), `unsafe capability: ${capability}`)
    }
    assert.equal(attribute('referrerpolicy'), 'no-referrer')
    assert.ok(frame.props.some(prop => prop.type === 7 && prop.name === 'bind' && prop.arg?.content === 'srcdoc'))

    const reader = elements(component('MailMessageReaderDialog').template.ast)
    assert.ok(reader.some(node => node.tag === 'MailHtmlBody'))
    assert.ok(!reader.some(node => node.props?.some(prop => prop.type === 7 && prop.name === 'html')), 'mail must never render via v-html in the application origin')
})

test('the immutable first CSP blocks active content and remote tracking before any sender HTML', () => {
    const hostile = '<meta http-equiv="Content-Security-Policy" content="default-src *"><script>parent.attack()</script><img src="https://tracker.example.test/read"><p>Письмо</p>'
    const document = mailHtmlDocument(hostile)
    const policy = document.match(/<meta http-equiv="Content-Security-Policy" content="([^"]+)"/)
    assert.ok(policy)
    assert.ok(policy.index < document.indexOf(hostile))
    const directives = Object.fromEntries(policy[1].split(';').map(value => value.trim().split(/\s+/)).filter(parts => parts[0]).map(([name, ...values]) => [name, values]))
    for (const directive of ['default-src', 'script-src', 'font-src', 'base-uri', 'form-action']) {
        assert.deepEqual(directives[directive], ["'none'"])
    }
    assert.deepEqual(directives['img-src'], ['data:'])
    assert.deepEqual(directives['style-src'], ["'unsafe-inline'"])
    assert.match(document, /<meta name="referrer" content="no-referrer">/)
    assert.ok(document.includes('<p>Письмо</p>'))
})
