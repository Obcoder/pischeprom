import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { collectUnitCommunications, communicationTypes, contactKey, websiteHref } from '../../resources/js/Composables/unitCommunications.js'

function harness(t, { canManage = true, canSend = true, requestFailure = null } = {}) {
    const filename = 'resources/js/Components/Unit/UnitCommunicationsPanel.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'communications-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'communications-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], disposal = []
    const props = Vue.reactive({
        unit: { id: 7, entities: [{ id: 21, name: 'Завод' }] },
        dict: { emails: [{ id: 8, address: 'sales@example.ru' }] }, canManage, canSend,
    })
    const request = async (method, url, payload) => {
        requests.push({ method, url, payload })
        if (method !== 'get' && requestFailure) throw requestFailure
        return { data: { data: [] } }
    }
    const environment = {
        ...Vue, UnitSendingsCard: {}, UnitCallsCard: {}, UnitActivityTabsPanel: {}, MaxContactButton: {}, collectUnitCommunications,
        communicationTypes, contactKey, websiteHref, usePhoneFormatter: () => ({ formatPhone: value => value }),
        onBeforeUnmount: callback => disposal.push(callback),
        axios: Object.fromEntries(['get', 'post', 'put', 'delete'].map(method => [method, (url, payload) => request(method, url, payload)])),
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => { disposal.forEach(callback => callback()); scope.stop() })
    return { state, props, requests, emitted }
}

const emailType = communicationTypes.find(type => type.key === 'emails')
const sharedContact = {
    id: 8, value: 'sales@example.ru', name: 'Отдел продаж',
    sources: [
        { id: 8, owner: { type: 'unit', id: 7, name: 'Unit' } },
        { id: 18, owner: { type: 'entity', id: 21, name: 'Завод' } },
    ],
}

test('Unit and Entity contacts deduplicate by value while retaining every owner and record ID', () => {
    const contacts = collectUnitCommunications({
        id: 7,
        emails: [{ id: 8, address: 'Sales@example.ru' }],
        telephones: [{ id: 5, number: '+7 (900) 123-45-67' }],
        uris: [{ id: 3, address: 'https://www.example.ru/' }],
        entities: [
            { id: 21, name: 'Завод', emails: [{ id: 18, address: 'sales@example.ru' }], telephones: [{ id: 5, number: '79001234567' }], uris: [{ id: 30, address: 'example.ru' }] },
            { id: 22, name: 'Офис', emails: [{ id: 8, address: 'SALES@example.ru' }] },
        ],
    })
    for (const type of communicationTypes) assert.equal(contacts[type.key].length, 1)
    assert.deepEqual(contacts.emails[0].sources.map(source => [source.id, source.owner.type, source.owner.id]), [[8, 'unit', 7], [18, 'entity', 21], [8, 'entity', 22]])
    assert.deepEqual(contacts.uris[0].sources.map(source => source.owner.name), ['Unit', 'Завод'])
    assert.equal(contactKey('telephones', '8 (900) 123-45-67'), contactKey('telephones', '+7 900 123-45-67'))
    assert.equal(contactKey('telephones', '9001234567'), contactKey('telephones', '+7 900 123-45-67'))
    assert.deepEqual(collectUnitCommunications({}), { uris: [], telephones: [], emails: [] })
})

test('website links accept public web addresses and reject active schemes or credentials', () => {
    assert.equal(websiteHref('example.ru/catalog'), 'https://example.ru/catalog')
    assert.equal(websiteHref('https://example.ru'), 'https://example.ru/')
    for (const value of ['javascript:alert(1)', 'data:text/html,test', '//example.ru', 'https://user:password@example.ru', 'https://example.ru\\@evil.ru', 'https://example.ru/\npath', 'ftp://example.ru', null]) {
        assert.equal(websiteHref(value), null, String(value))
    }
    assert.notEqual(contactKey('uris', 'example.ru/Catalog'), contactKey('uris', 'example.ru/catalog'))
})

test('attaching an existing email to an Entity reuses its ID and targets that Entity', async t => {
    const { state, requests, emitted } = harness(t)
    state.openContact(emailType)
    state.form.owner = 'entity:21'
    state.selectedContact.value = 'SALES@example.ru'
    await state.saveContact()
    assert.deepEqual(requests.find(item => item.method === 'post'), { method: 'post', url: '/api/units/7/communications/emails', payload: { entity_id: 21, contact_id: 8 } })
    assert.equal(state.dialog.value, false)
    assert.deepEqual(emitted, [['refresh']])
})

test('creating a new contact preserves entered name and direct Unit ownership', async t => {
    const { state, requests } = harness(t)
    state.openContact(emailType)
    state.selectedContact.value = 'new@example.ru'
    state.form.name = 'Поставки'
    await state.saveContact()
    assert.deepEqual(requests.find(item => item.method === 'post').payload, { entity_id: null, address: 'new@example.ru', name: 'Поставки' })
})

test('editing and detaching a duplicate uses the selected owner record, with deletion an explicit choice', async t => {
    const { state, requests } = harness(t)
    state.openContact(emailType, 'edit', sharedContact)
    state.form.source = 1
    state.form.value = 'updated@example.ru'
    await state.saveContact()
    assert.deepEqual(requests.find(item => item.method === 'put'), { method: 'put', url: '/api/units/7/communications/emails/18', payload: { entity_id: 21, address: 'updated@example.ru', name: 'Отдел продаж' } })
    state.openContact(emailType, 'remove', sharedContact)
    state.form.source = 1
    await state.saveContact()
    assert.deepEqual(requests.filter(item => item.method === 'delete')[0], { method: 'delete', url: '/api/units/7/communications/emails/18', payload: { params: { entity_id: 21, delete_record: 0 } } })
    state.openContact(emailType, 'remove', sharedContact)
    state.form.deleteRecord = true
    await state.saveContact()
    assert.deepEqual(requests.filter(item => item.method === 'delete')[1].payload, { params: { entity_id: null, delete_record: 1 } })
})

test('failed mutations preserve dialog input and expose server validation for recovery', async t => {
    const failure = { response: { data: { message: 'Контакт используется другими владельцами.', errors: { address: ['Некорректный адрес'] } } } }
    const { state, emitted } = harness(t, { requestFailure: failure })
    state.openContact(emailType, 'edit', sharedContact)
    state.form.value = 'invalid'
    await state.saveContact()
    assert.equal(state.dialog.value, true)
    assert.equal(state.form.value, 'invalid')
    assert.equal(state.feedback.value.text, failure.response.data.message)
    assert.deepEqual(state.fieldErrors.value, ['Некорректный адрес'])
    assert.deepEqual(emitted, [])
})

test('compose opens with the clicked email; management and send permissions are enforced', async t => {
    const { state, props, requests } = harness(t)
    const recipients = []
    state.mailCard.value = { openNewMessage: address => recipients.push(address) }
    await state.openNewMessage('sales@example.ru')
    assert.deepEqual(recipients, ['sales@example.ru'])
    props.canManage = false
    props.canSend = false
    state.openContact(emailType)
    await state.saveContact()
    await state.openNewMessage('blocked@example.ru')
    assert.equal(state.dialog.value, false)
    assert.deepEqual(recipients, ['sales@example.ru'])
    assert.equal(requests.length, 0)
})

function mailHarness(t) {
    const requests = []
    const environment = {
        ...Vue,
        axios: { get(url, options) {
            let resolve, reject
            const promise = new Promise((success, failure) => { resolve = success; reject = failure })
            requests.push({ url, options, resolve: data => resolve({ data }), reject })
            return promise
        } },
    }
    const script = readFileSync('resources/js/Composables/useUnitMail.js', 'utf8')
        .replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export function', 'function')
    const useUnitMail = new Function('env', `with(env){${script}; return useUnitMail}`)(environment)
    const scope = Vue.effectScope()
    const state = scope.run(() => useUnitMail(7))
    t.after(() => scope.stop())
    return { state, requests }
}

test('opening another message cancels a prior read and never substitutes the wrong reply context', async t => {
    const { state, requests } = mailHarness(t)
    const firstRead = state.readMessage({ id: 1 })
    const secondRead = state.readMessage({ id: 2 })
    assert.equal(requests[0].options.signal.aborted, true)
    requests[1].resolve({ id: 2, from_address: 'current@example.ru' })
    assert.equal((await secondRead).id, 2)
    requests[0].resolve({ id: 1, from_address: 'old@example.ru' })
    assert.equal(await firstRead, null)
    assert.equal(state.selectedMessage.value.id, 2)
    const failedRead = state.readMessage({ id: 3 })
    assert.equal(state.selectedMessage.value, null)
    requests[2].reject({ response: { data: { message: 'Письмо недоступно' } } })
    assert.equal(await failedRead, null)
    assert.equal(state.selectedMessage.value, null)
    assert.equal(state.error.value, 'Письмо недоступно')
})

test('mail history ignores stale results after refreshing and keeps pagination metadata', async t => {
    const { state, requests } = mailHarness(t)
    const firstLoad = state.fetchMessages()
    const refreshedLoad = state.fetchMessages()
    assert.equal(requests[0].options.signal.aborted, true)
    requests[1].resolve({ data: [{ id: 2 }], meta: { total: 18 }, related_emails: [{ address: 'entity@example.ru' }] })
    await refreshedLoad
    requests[0].resolve({ data: [{ id: 1 }], meta: { total: 1 } })
    await firstLoad
    assert.deepEqual(state.messages.value, [{ id: 2 }])
    assert.equal(state.totalItems.value, 18)
    assert.equal(state.relatedEmails.value[0].address, 'entity@example.ru')
    assert.equal(state.loading.value, false)
})
