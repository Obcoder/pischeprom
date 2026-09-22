import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { emptyMailOffer, mailOfferPayload } from '../../resources/js/Components/Contacts/Emails/mailOfferPayload.js'

const good = { id: 9, name: 'Глицерин', price: 125, currency_code: 'RUB', price_unit_label: 'кг', description: 'Описание', url: 'https://example.test/g/glycerin' }
const selection = () => ({ good_id: 9, good, quantity: '50', price_override: '', include_image: true, include_description: true, include_specifications: false, specifications: [] })

function harness(t, initialProps = {}) {
    const filename = 'resources/js/Components/Contacts/Emails/MailComposerDialog.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'mail-composer-offer' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'mail-composer-offer', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], disposal = [], emitted = []
    const environment = {
        ...Vue,
        _mergeModels: Vue.mergeModels,
        _useModel: (props, name) => Vue.computed({ get: () => props[name], set: value => { props[name] = value } }),
        onBeforeUnmount: callback => disposal.push(callback),
        MailTemplatesDialog: {}, MailOfferPanel: {}, emptyMailOffer, mailOfferPayload,
        axios: {
            get: async () => ({ data: [] }),
            post(url, data, options) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, data, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: false, recipients: [], initialTo: ['customer@example.test'], initialStorageFiles: [], replyContext: null, mailboxes: [{ address: 'sales@example.test' }], entityId: null, unitId: null, endpoint: '/api/mail-messages/send', ...initialProps })
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => { disposal.forEach(fn => fn()); scope.stop() })
    return { state, props, requests, emitted, async open() { props.modelValue = true; await Vue.nextTick(); await Vue.nextTick() } }
}

test('offer payload excludes client catalog facts and normalizes optional prices without losing free delivery', () => {
    const item = selection()
    item.specifications = [{ label: '', value: '' }]
    const payload = mailOfferPayload({ items: [item], logistics: { origin: 'СПб', destination: 'Курск', options: [{ name: 'Самовывоз', price: '0', duration: 'Сегодня' }] } })
    assert.equal(payload.items[0].good_id, 9)
    assert.equal(payload.items[0].quantity, 50)
    assert.equal(payload.items[0].price_override, null)
    assert.ok(!('good' in payload.items[0]))
    assert.ok(!('url' in payload.items[0]))
    assert.ok(!('specifications' in payload.items[0]))
    assert.equal(payload.logistics.options[0].price, 0)
    assert.equal(mailOfferPayload(emptyMailOffer()), null)
})

test('new mail can send a product-only offer and prevents duplicate clicks while sending', async t => {
    const { state, requests, open } = harness(t)
    await open()
    state.subject.value = 'Предложение'
    state.offer.value.items = [selection()]
    assert.equal(state.hasContent.value, true)
    const pending = state.submit()
    await state.submit()
    assert.equal(requests.length, 1)
    assert.equal(requests[0].url, '/api/mail-messages/send')
    const form = requests[0].data
    assert.equal(form.get('body'), '')
    assert.equal(form.get('mailbox'), 'sales@example.test')
    assert.equal(form.get('to[]'), 'customer@example.test')
    assert.equal(JSON.parse(form.get('offer')).items[0].quantity, 50)
    assert.ok(!JSON.parse(form.get('offer')).items[0].good)
    requests[0].resolve({ mail_message: { id: 44 } })
    await pending
})

test('reply keeps thread id and quote separate from the authored text and offer', async t => {
    const { state, requests, open } = harness(t, { replyContext: { id: 17, mailbox: 'sales@example.test', from_address: 'buyer@example.test', subject: 'Запрос', text: 'Нужно 50 кг.' } })
    await open()
    assert.equal(state.body.value, '')
    assert.match(state.quotedBody.value, /Нужно 50 кг/)
    assert.equal(state.subject.value, 'Re: Запрос')
    state.body.value = 'Добрый день! Наше предложение ниже.'
    state.offer.value.items = [selection()]
    const pending = state.submit()
    const form = requests[0].data
    assert.equal(form.get('reply_to_mail_message_id'), '17')
    assert.equal(form.get('to[]'), 'buyer@example.test')
    assert.equal(form.get('body'), 'Добрый день! Наше предложение ниже.')
    assert.match(form.get('quoted_body'), /Нужно 50 кг/)
    requests[0].resolve({ mail_message: { id: 45 } })
    await pending
})

test('preview never sends mail and ignores a slower response or a closed draft', async t => {
    const { state, props, requests, open } = harness(t)
    await open()
    state.body.value = 'Первый вариант'
    await Vue.nextTick()
    const first = state.fetchPreview()
    state.body.value = 'Второй вариант'
    await Vue.nextTick()
    const second = state.fetchPreview()
    assert.equal(requests[0].options.signal.aborted, true)
    requests[1].resolve({ html: '<p>Второй вариант</p>', text: 'Второй вариант' })
    await second
    requests[0].resolve({ html: '<p>Старый вариант</p>' })
    await first
    assert.equal(state.previewHtml.value, '<p>Второй вариант</p>')
    const third = state.fetchPreview()
    props.modelValue = false
    await Vue.nextTick()
    requests[2].resolve({ html: '<p>После закрытия</p>' })
    await third
    assert.equal(state.previewHtml.value, '')
    assert.ok(requests.every(request => request.url === '/api/mail-offers/preview'))
})
