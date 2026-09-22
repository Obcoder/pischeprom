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

function harness(t, name, initialProps = {}, responses = {}) {
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
                if (url.endsWith('/research')) return { data: { data: [], availability: { website: { available: true }, company: { available: true } }, ...structuredClone(responses.research || {}) } }
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

test('website research preselects only a linked Unit and includes it on explicit research', async t => {
    const units = [{ id: 21, name: 'Поставщик' }, { id: 22, name: 'Склад' }]
    const { state, posts, settle } = harness(t, 'MailMessageCrmTools', { defaultUnitId: 22 }, { research: { linked_units: units } })
    state.openAction('ai-website')
    await settle()
    assert.equal(state.selectedResearchUnitId.value, 22)
    assert.equal(posts.length, 0)
    const pending = state.submit()
    assert.deepEqual(posts[0].data, { url: 'https://supplier.example.test', unit_id: 22 })
    posts[0].resolve({ data: { id: 10, kind: 'website', saved_units: [units[1]], result: {} }, saved_to_unit: units[1], unit_research_id: 7 })
    await pending
    assert.equal(state.savedResearchUnits.value[0].id, 22)
    assert.match(state.notice.value, /Склад/)
})

test('a single linked Unit is selected but an unrelated default cannot become a target', async t => {
    const responses = { research: { linked_units: [{ id: 21, name: 'Поставщик' }] } }
    const { state, props, posts, settle } = harness(t, 'MailMessageCrmTools', { defaultUnitId: 999 }, responses)
    state.openAction('ai-website')
    await settle()
    assert.equal(state.selectedResearchUnitId.value, 21)
    responses.research.linked_units = []
    props.message = message(2)
    await settle()
    state.openAction('ai-website')
    await settle()
    assert.equal(state.selectedResearchUnitId.value, null)
    state.selectedResearchUnitId.value = 999
    assert.equal(state.selectedResearchUnit.value, null)
    assert.equal(Boolean(state.canSaveResearchToUnit.value), false)
    const pending = state.submit()
    assert.deepEqual(posts[0].data, { url: 'https://supplier.example.test' })
    posts[0].resolve({ data: { id: 10, kind: 'website', saved_units: [], result: {} } })
    await pending
})

test('an existing result can be saved and explicitly updated in Unit without AI or concurrent duplicate saves', async t => {
    const unit = { id: 21, name: 'Поставщик', url: '/Ameise/unit/21?section=overview#website-research', unit_research_id: 7 }
    const record = { id: 10, kind: 'website', query: 'https://supplier.example.test', result: { products: [{ name: 'Треска' }] }, saved_units: [] }
    const { state, posts, settle } = harness(t, 'MailMessageCrmTools', {}, { research: { data: [record], linked_units: [unit], availability: { website: { available: false } } } })
    state.openAction('ai-website')
    await settle()
    assert.equal(state.ready.value, false)
    assert.equal(Boolean(state.canSaveResearchToUnit.value), true)
    assert.equal(posts.length, 0)
    const pending = state.saveResearchToUnit()
    await state.saveResearchToUnit()
    assert.equal(posts.length, 1)
    assert.equal(posts[0].url, '/api/mail-messages/1/research/10/unit')
    assert.deepEqual(posts[0].data, { unit_id: 21 })
    posts[0].resolve({ data: { ...record, saved_units: [unit] }, saved_to_unit: unit, unit_research_id: 7 })
    await pending
    assert.equal(state.researchSavedToSelectedUnit.value, true)
    assert.equal(state.unitResearchLink(state.savedResearchUnits.value[0]), unit.url)
    assert.equal(Boolean(state.canSaveResearchToUnit.value), true)
    state.researchRecords.value[0].result.products.push({ name: 'Минтай' })
    const updatedRecord = { ...record, result: { products: [{ name: 'Треска' }, { name: 'Минтай' }] }, saved_units: [unit] }
    const update = state.saveResearchToUnit()
    await state.saveResearchToUnit()
    assert.equal(posts.length, 2)
    assert.equal(posts[1].url, '/api/mail-messages/1/research/10/unit')
    posts[1].resolve({ data: updatedRecord, saved_to_unit: unit, unit_research_id: 7 })
    await update
    assert.equal(state.researchRecords.value[0].result.products.length, 2)
    assert.equal(state.savedResearchUnits.value.length, 1)
})

test('late Unit save results do not attach notices or records to another message', async t => {
    const unit = { id: 21, name: 'Поставщик' }
    const record = { id: 10, kind: 'website', result: {}, saved_units: [] }
    const { state, props, posts, emitted, settle } = harness(t, 'MailMessageCrmTools', {}, { research: { data: [record], linked_units: [unit] } })
    state.openAction('ai-website')
    await settle()
    const pending = state.saveResearchToUnit()
    props.message = message(2)
    await settle()
    posts[0].resolve({ data: { ...record, saved_units: [unit] }, saved_to_unit: unit })
    await pending
    assert.deepEqual(state.researchRecords.value, [])
    assert.deepEqual(state.researchUnits.value, [])
    assert.equal(state.savingResearchToUnit.value, false)
    assert.ok(!emitted.some(([event]) => event === 'notice'))
})
