import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { emptyUnitForm, unitCreationPayload, unitCreationErrorSection, unitRelationIds } from '../../resources/js/utils/unitCreation.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function harness(t) {
    const filename = 'resources/js/Components/Unit/UnitCreateDialog.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'unit-creation-test' })
    const template = compileTemplate({
        source: descriptor.template.content, filename, id: 'unit-creation-test',
        compilerOptions: { bindingMetadata: compiled.bindings },
    })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], visits = []
    const environment = {
        ...Vue,
        UnitCreateRelationField: {}, ApartmentSelector: {},
        emptyUnitForm, unitCreationPayload, unitCreationErrorSection, unitRelationIds,
        router: { visit: url => visits.push(url) },
        axios: {
            post(url, payload) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, payload, resolve: data => resolve({ data }), reject })
                return promise
            },
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: true })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { api, props, requests, emitted, visits, render: templateRenderer(template, api, props) }
}

test('unit creation separates saved relations from typed contacts, including numeric phone numbers', () => {
    const form = emptyUnitForm()
    Object.assign(form, {
        name: '  Северная ягода  ', is_customer: true, is_supplier: true,
        entities: [{ id: 11 }, { id: '11' }, { id: 12 }],
        cities: [{ id: 20 }], fields: [{ id: 30 }], labels: [{ id: 40 }],
        industries: [{ id: 50 }], classifications: [{ id: 60 }], primary_industry_id: '50',
        emails: [{ id: 71, email: 'saved@example.ru' }, '  new@example.ru ', 'new@example.ru', ' '],
        telephones: [{ id: 81, telephone: '+7 921 000-00-00' }, '79211234567', ' 79211234567 ', '+7 921 555-44-33'],
        uris: [{ id: 91, uri: 'saved.example.ru' }, ' company.ru ', 'company.ru'],
    })
    assert.deepEqual(unitCreationPayload(form), {
        name: 'Северная ягода', is_customer: true, is_supplier: true,
        entities: [11, 12], cities: [20], buildings: [], fields: [30], labels: [40], industries: [50], classifications: [60],
        emails: [71], new_emails: ['new@example.ru'], telephones: [81], new_telephones: ['79211234567', '+7 921 555-44-33'],
        uris: [91], new_uris: ['company.ru'], building_apartments: {}, primary_industry_id: 50,
    })
    assert.equal(form.name, '  Северная ягода  ', 'building a request must not rewrite the draft')
})

test('only apartments of selected buildings and a selected primary industry are submitted', () => {
    const form = emptyUnitForm()
    Object.assign(form, {
        buildings: [{ id: 10 }, { id: 20 }], building_apartments: { 10: 101, 20: null, 30: 301 },
        industries: [{ id: 5 }], primary_industry_id: 6,
    })
    const payload = unitCreationPayload(form)
    assert.deepEqual(payload.building_apartments, { 10: 101, 20: null })
    assert.equal(payload.primary_industry_id, null)
    assert.deepEqual(form.building_apartments, { 10: 101, 20: null, 30: 301 })
})

test('new forms have independent arrays and apartment maps', () => {
    const first = emptyUnitForm(), second = emptyUnitForm()
    first.entities.push({ id: 1 })
    first.building_apartments[2] = 3
    assert.deepEqual(second.entities, [])
    assert.deepEqual(second.building_apartments, {})
})

test('validation paths are routed to the section that can correct them', () => {
    for (const [section, keys] of Object.entries({
        profile: ['name', 'entities.0'],
        contacts: ['emails.0', 'new_emails.1', 'telephones.0', 'new_telephones.0', 'uris.0', 'new_uris.0'],
        geography: ['cities.0', 'buildings.0', 'building_apartments.12'],
        classification: ['fields.0', 'labels.0', 'industries.0', 'classifications.0', 'primary_industry_id'],
    })) {
        for (const key of keys) assert.equal(unitCreationErrorSection(key), section, key)
    }
})

test('blank and too-long names focus the required field and do not make requests', async t => {
    const { api, requests } = harness(t)
    let focused = 0
    api.nameInput.value = { focus: () => focused++ }
    for (const name of ['   ', 'А'.repeat(256)]) {
        api.form.name = name
        api.activeSection.value = 'contacts'
        await api.save()
        assert.equal(api.activeSection.value, 'profile')
        assert.ok(api.fieldErrors('name').length)
        assert.equal(api.saving.value, false)
    }
    assert.equal(focused, 2)
    assert.equal(requests.length, 0)
})

test('pending contact input is committed before the creation request is built', async t => {
    const { api, requests, emitted } = harness(t)
    api.form.name = 'Поставщик'
    api.contactInputs.value = [
        { flushPending: () => api.form.telephones.push('79215554433') },
        { flushPending: () => api.form.emails.push('sales@supplier.ru') },
    ]
    const pending = api.save()
    await Vue.nextTick()
    assert.equal(requests[0].url, '/api/units')
    assert.deepEqual(requests[0].payload.new_telephones, ['79215554433'])
    assert.deepEqual(requests[0].payload.new_emails, ['sales@supplier.ru'])
    requests[0].resolve({ data: { id: 25, name: 'Поставщик' } })
    await pending
    assert.deepEqual(emitted, [['created', { id: 25, name: 'Поставщик' }], ['update:modelValue', false]])
})

test('concurrent submissions create only one Unit and a pending save cannot be closed', async t => {
    const { api, requests, emitted } = harness(t)
    api.form.name = 'Единственный Unit'
    let flushes = 0
    api.contactInputs.value = [{ flushPending: () => flushes++ }]
    const first = api.save(), second = api.save()
    await Vue.nextTick()
    api.close()
    assert.equal(requests.length, 1)
    assert.equal(flushes, 1)
    assert.equal(api.confirmDiscard.value, false)
    assert.deepEqual(emitted, [])
    requests[0].resolve({ id: 26, name: 'Единственный Unit' })
    await Promise.all([first, second])
    assert.equal(api.saving.value, false)
    assert.equal(emitted.filter(([event]) => event === 'created').length, 1)
})

test('server validation preserves the draft and opens the invalid section with field messages', async t => {
    const { api, requests, emitted } = harness(t)
    Object.assign(api.form, { name: 'Северная ягода', is_supplier: true, emails: ['bad-address'], entities: [{ id: 8 }] })
    const snapshot = structuredClone(Vue.toRaw(api.form))
    const pending = api.save()
    await Vue.nextTick()
    requests[0].reject({ response: { status: 422, data: { errors: {
        'new_emails.0': ['Некорректная почта.'], 'building_apartments.10': ['Помещение не принадлежит зданию.'],
    } } } })
    await pending
    assert.deepEqual(Vue.toRaw(api.form), snapshot)
    assert.equal(api.activeSection.value, 'contacts')
    assert.deepEqual(api.fieldErrors('emails'), ['Некорректная почта.'])
    assert.deepEqual(api.fieldErrors('building_apartments.10'), ['Помещение не принадлежит зданию.'])
    assert.deepEqual([...api.sectionErrors.value], ['contacts', 'geography'])
    assert.ok(api.error.value)
    assert.equal(api.saving.value, false)
    assert.deepEqual(emitted, [])
})

test('network failure is retryable without clearing the draft or navigating', async t => {
    const { api, requests, visits, emitted } = harness(t)
    api.form.name = 'Повторная попытка'
    const first = api.save()
    await Vue.nextTick()
    requests[0].reject(new Error('offline'))
    await first
    assert.equal(api.form.name, 'Повторная попытка')
    assert.ok(api.error.value)
    assert.equal(api.saving.value, false)
    assert.deepEqual(visits, [])
    assert.deepEqual(emitted, [])
    const second = api.save()
    await Vue.nextTick()
    assert.equal(api.error.value, '')
    requests[1].resolve({ data: { id: 27, name: 'Повторная попытка' } })
    await second
    assert.equal(emitted[0][0], 'created')
    assert.deepEqual(visits, [], 'opening the card must remain opt-in')
})

test('successful save can open the newly created card, after emitting the saved Unit', async t => {
    const { api, requests, emitted, visits } = harness(t)
    api.form.name = 'Открыть карточку'
    api.openAfterCreate.value = true
    const pending = api.save()
    await Vue.nextTick()
    requests[0].resolve({ data: { id: 28, name: 'Открыть карточку' } })
    await pending
    assert.deepEqual(emitted, [['created', { id: 28, name: 'Открыть карточку' }], ['update:modelValue', false]])
    assert.deepEqual(visits, ['/Ameise/unit/28'])
})

test('removing the primary industry clears it without disturbing other selected industries', async t => {
    const { api } = harness(t)
    api.form.industries = [{ id: 3, code: '10.51', title: 'Молоко' }, { id: 4, code: '46.38', title: 'Оптовая торговля' }]
    api.form.primary_industry_id = 3
    await Vue.nextTick()
    assert.equal(api.form.primary_industry_id, 3)
    api.form.industries.splice(0, 1)
    await Vue.nextTick()
    assert.equal(api.form.primary_industry_id, null)
    assert.deepEqual(unitCreationPayload(api.form).industries, [4])
})

test('closing an empty form is immediate; pending typed contacts trigger discard confirmation', async t => {
    const { api, emitted } = harness(t)
    api.close()
    assert.deepEqual(emitted, [['update:modelValue', false]])
    emitted.length = 0
    api.contactInputs.value = [{ flushPending: () => api.form.uris.push('supplier.ru') }]
    api.close()
    assert.equal(api.confirmDiscard.value, true)
    assert.deepEqual(api.form.uris, ['supplier.ru'])
    assert.deepEqual(emitted, [])
    api.discard()
    assert.equal(api.confirmDiscard.value, false)
    assert.deepEqual(emitted, [['update:modelValue', false]])
})

test('reopening starts a fresh draft, clears errors and returns to the name field', async t => {
    const { api, props } = harness(t)
    Object.assign(api.form, {
        name: 'Предыдущий Unit', entities: [{ id: 4 }], emails: ['old@example.ru'],
        buildings: [{ id: 5 }], building_apartments: { 5: 6 }, industries: [{ id: 7 }], primary_industry_id: 7,
    })
    api.activeSection.value = 'geography'
    api.errors.value = { 'buildings.0': ['Ошибка'] }
    api.error.value = 'Ошибка сохранения'
    api.confirmDiscard.value = true
    let focused = false
    api.nameInput.value = { focus: () => { focused = true } }
    props.modelValue = false
    await Vue.nextTick()
    props.modelValue = true
    await Vue.nextTick()
    await Vue.nextTick()
    assert.deepEqual(Vue.toRaw(api.form), emptyUnitForm())
    assert.equal(api.activeSection.value, 'profile')
    assert.deepEqual(api.errors.value, {})
    assert.equal(api.error.value, '')
    assert.equal(api.confirmDiscard.value, false)
    assert.equal(api.dirty.value, false)
    assert.equal(focused, true)
})

test('the form submit binding saves and cancellation uses the dirty-draft guard', async t => {
    const { api, render, requests, emitted } = harness(t)
    api.form.name = 'Отправка из формы'
    const form = findVNode(render(), node => node.type === 'form')
    let prevented = false
    const pending = form.props.onSubmit({ preventDefault: () => { prevented = true } })
    await Vue.nextTick()
    assert.equal(prevented, true)
    assert.equal(requests.length, 1)
    requests[0].reject({ response: { status: 422, data: { errors: { name: ['Название занято.'] } } } })
    await pending
    const cancel = findVNode(render(), node => node.type === 'v-btn' && node.props.onClick === api.close && node.props.variant === 'text' && !node.props.icon)
    assert.ok(cancel)
    cancel.props.onClick()
    assert.equal(api.confirmDiscard.value, true)
    assert.deepEqual(emitted, [])
})
