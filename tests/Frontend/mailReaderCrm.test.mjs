import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

const message = (id = 1, leads = []) => ({
    id, direction: 'incoming', subject: `Письмо ${id}`, from_address: 'supplier@example.test',
    from_name: 'Поставщик', text: 'Телефон +7 999 123-45-67', emails: [], notes: [], leads,
})
const context = {
    candidates: { phones: ['+7 999 123-45-67'], websites: ['https://supplier.example.test'], tax_ids: ['7701234567'] },
    linked: { entities: [], units: [] },
}

function harness(t, name, initialProps = {}) {
    const filename = `resources/js/Components/Contacts/Emails/${name}.vue`
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'reader-crm-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'reader-crm-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const posts = [], reads = [], emitted = [], disposal = []
    const environment = {
        ...Vue,
        _mergeModels: Vue.mergeModels,
        _useModel: (props, key) => Vue.computed({ get: () => props[key], set: value => { props[key] = value } }),
        onBeforeUnmount: callback => disposal.push(callback),
        route: (name, id) => `/test/${name}/${id || ''}`,
        MailInvoiceDetails: {}, MailPdfViewer: {}, MailMessageReaderHeader: {}, MailMessageCrmTools: {}, WordAttachmentPreview: {},
        axios: {
            async get(url, options) {
                reads.push({ url, options })
                if (url.endsWith('/crm')) return { data: structuredClone(context) }
                if (url.endsWith('/research')) return { data: { data: [], availability: { website: { available: true }, company: { available: true } } } }
                return { data: { items: [], folders: [], building_types: [] } }
            },
            post(url, data, options) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                posts.push({ url, data, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            isCancel: exception => exception?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: true, message: message(), loading: false, disabled: false, defaultEntityId: null, defaultUnitId: null, ...initialProps })
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => { disposal.forEach(callback => callback()); scope.stop() })
    const settle = async () => { await Vue.nextTick(); await Promise.resolve(); await Vue.nextTick(); await Promise.resolve() }
    return { state, props, posts, reads, emitted, settle }
}

test('opening a mail tool only loads saved data; research starts after explicit submit', async t => {
    const { state, posts, reads, settle } = harness(t, 'MailMessageCrmTools')
    assert.equal(posts.length, 0)
    state.openAction('ai-website')
    await settle()
    assert.ok(reads.some(request => request.url.endsWith('/research')))
    assert.equal(posts.length, 0)
    const pending = state.submit()
    await state.submit()
    assert.equal(posts.length, 1)
    assert.equal(posts[0].url, '/api/mail-messages/1/research/website')
    assert.deepEqual(posts[0].data, { url: 'https://supplier.example.test' })
    posts[0].resolve({ data: { id: 10, kind: 'website', result: { products: [] } }, cached: false })
    await pending
    state.openAction('ai-company')
    await settle()
    assert.equal(posts.length, 1)
})

test('a stale research response cannot replace results or end a newer message request', async t => {
    const { state, props, posts, settle } = harness(t, 'MailMessageCrmTools')
    state.openAction('ai-website')
    await settle()
    const first = state.submit()
    props.message = message(2)
    await settle()
    state.openAction('ai-website')
    await settle()
    const second = state.submit()
    assert.equal(posts[1].url, '/api/mail-messages/2/research/website')
    posts[0].resolve({ data: { id: 10, kind: 'website', result: { summary: 'Старое письмо' } } })
    await first
    assert.equal(state.researchRecords.value.length, 0)
    assert.equal(state.saving.value, true)
    posts[1].resolve({ data: { id: 20, kind: 'website', result: { summary: 'Текущее письмо' } } })
    await second
    assert.equal(state.researchRecords.value[0].id, 20)
    assert.equal(state.saving.value, false)
})

test('a CRM mutation finishing after a message switch does not update another message', async t => {
    const { state, props, posts, emitted, settle } = harness(t, 'MailMessageCrmTools')
    state.openAction('phone')
    await settle()
    const saving = state.submit()
    assert.equal(posts[0].url, '/api/mail-messages/1/crm/telephones')
    props.message = message(2)
    await settle()
    posts[0].resolve({ created: true, record: { id: 77 }, crm: { linked: { entities: [{ id: 123 }] } } })
    await saving
    assert.deepEqual(state.crm.value.linked, {})
    assert.ok(!emitted.some(([event]) => event === 'changed' || event === 'notice'))
})

test('selecting a researched company fills Entity data without creating it', async t => {
    const { state, posts } = harness(t, 'MailMessageCrmTools')
    const raw = { data: { inn: '7701234567' } }
    state.useCompany({ entity: { name: 'ООО Пример', INN: '7701234567', legal_address: 'Москва' }, raw })
    assert.equal(state.action.value, 'entity')
    assert.equal(state.form.INN, '7701234567')
    assert.deepEqual(state.form.dadata_raw, raw)
    assert.equal(posts.length, 0)
})

test('existing leads and repeated clicks cannot create a duplicate lead', async t => {
    const { state, props, posts, settle } = harness(t, 'MailMessageReaderDialog', { message: message(1, [{ id: 77, title: 'Уже создан' }]) })
    state.leadTitle.value = 'Повторный лид'
    await state.createLead()
    assert.equal(posts.length, 0)
    props.message = message()
    await settle()
    const first = state.createLead()
    await state.createLead()
    assert.equal(posts.length, 1)
    posts[0].resolve({ created: false, lead: { id: 77 }, mail_message: message(1, [{ id: 77, title: 'Уже создан' }]) })
    await first
    assert.match(state.feedback.value.text, /уже существует/)
    await state.createLead()
    assert.equal(posts.length, 1)
})

test('a late lead response cannot reopen the previous message', async t => {
    const { state, props, posts, emitted, settle } = harness(t, 'MailMessageReaderDialog')
    state.leadTitle.value = 'Лид'
    const pending = state.createLead()
    props.message = message(2)
    await settle()
    posts[0].resolve({ created: true, lead: { id: 77 }, mail_message: message(1, [{ id: 77 }]) })
    await pending
    assert.equal(state.currentMessage.value.id, 2)
    assert.ok(!emitted.some(([event]) => event === 'updated'))
})
