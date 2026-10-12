import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'
const flush = () => new Promise(resolve => setImmediate(resolve))
function harness(t, name, initial) {
    const filename = `resources/js/Components/Goods/${name}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename }); assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: name })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: name, compilerOptions: { bindingMetadata: script.bindings } }); assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], reloads = []
    const props = Vue.reactive(initial)
    const env = { ...Vue, router: { reload: options => reloads.push(options) }, axios: Object.fromEntries(['get', 'put', 'post', 'delete'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])) }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(env), scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    return { props, api, requests, emitted, reloads, render: templateRenderer(template, api, props) }
}
const measurement = { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 }
const source = { rows: [{ id: 7, revision: 1, scope: 'common', target_id: 2, target_name: 'Розничная', price_gross: 120, price_net: 100, currency_id: 1, vat_rate: 20, active: true }], measurement, vat_rate: 20, currencies: [{ id: 1, code: 'RUB' }], types: [{ id: 2, name: 'Розничная', is_active: true, is_public: true }], sources: [], partners: [], calculations: [] }
function dataset() { return structuredClone(source) }

test('pricing loads only when visited, keeps drafts across tabs, and protects switched products from old responses', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: false })
    assert.equal(h.requests.length, 0)
    h.props.active = true; await Vue.nextTick(); const old = h.requests.at(-1)
    h.props.goodId = 55; await Vue.nextTick()
    h.requests.at(-1).resolve(dataset()); await flush()
    old.resolve({ ...dataset(), rows: [] }); await flush()
    assert.equal(h.api.rows.value.length, 1)
    h.api.rows.value[0].amount = 144; h.api.touch(h.api.rows.value[0]); await Vue.nextTick()
    h.props.active = false; await Vue.nextTick(); h.props.active = true; await Vue.nextTick()
    assert.equal(h.requests.length, 2); assert.equal(h.api.rows.value[0].amount, 144)
    assert.ok(h.emitted.some(([name, state]) => name === 'state' && state.dirty))
    h.api.reset(); assert.equal(h.api.rows.value[0].amount, 120)
})

test('calculation and saving use the same server inputs and revision; conflicts retain the draft', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true }); h.requests[0].resolve(dataset()); await flush()
    const row = h.api.rows.value[0]; row.amount = 144; h.api.touch(row)
    const preview = h.api.run(row); assert.equal(h.requests.at(-1).url, '/api/goods/42/pricing/preview')
    assert.deepEqual(h.requests.at(-1).body.measurement, measurement)
    h.requests.at(-1).resolve({ price_gross: 144, price_net: 120 }); await preview
    assert.equal(row.result.price_gross, 144)
    const failedSave = h.api.run(row, true); assert.equal(h.requests.at(-1).body.revision, 1)
    h.requests.at(-1).reject({ response: { status: 409, data: { message: 'Цена уже изменена' } } }); await failedSave
    assert.equal(row.amount, 144); assert.equal(row.dirty, true); assert.match(row.error, /уже изменена/)
    const saved = h.api.run(row, true); h.requests.at(-1).resolve({ ...source.rows[0], price_gross: 144, revision: 2 }); await saved
    assert.equal(row.dirty, false); assert.equal(row.revision, 2); assert.ok(h.emitted.some(event => event[0] === 'changed'))
})

test('private row restricts audience to the cabinet and history is fetched with pagination and scope', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true }); h.requests[0].resolve(dataset()); await flush()
    h.api.add('entity', { id: 9, name: 'Организация' }); const row = h.api.rows.value.at(-1)
    assert.equal(h.api.audience(row), 'Только личный кабинет Entity')
    assert.equal(h.api.payload(row).scope, 'entity'); assert.equal(h.api.payload(row).target_id, 9)
    assert.equal('is_public' in h.api.payload(row), false)
    h.api.tab.value = 'history'; await Vue.nextTick()
    assert.equal(h.requests.at(-1).url, '/api/goods/42/pricing/history')
    h.requests.at(-1).resolve({ data: [], total: 0, current_page: 1, last_page: 1 }); await flush()
    h.api.historyScope.value = 'entity'; const load = h.api.loadHistory(2)
    assert.deepEqual(h.requests.at(-1).body.params, { scope: 'entity', page: 2 })
    h.requests.at(-1).resolve({ data: [], total: 31, current_page: 2, last_page: 2 }); await load
    assert.ok(findVNode(h.render(), node => node.type === 'table'))
})

test('cabinet checkout never uses the storefront cart and submits price identity rather than trusted amounts', async t => {
    const price = { id: 3, revision: 4, entity_id: 9, entity_name: 'Клиент', good_id: 42, good_name: 'Рыба', price_gross: 120, currency_code: 'RUB', measurement }
    const h = harness(t, 'CustomerEntityPrices', { prices: [price], profile: { phone: '+79991234567', delivery_address: 'Адрес' } })
    h.api.toggle(price); h.api.selected.value[3] = 2; h.api.time.value = 'Завтра'
    assert.equal(h.api.totals.value.RUB, 240)
    const submit = h.api.submit(); const request = h.requests.at(-1)
    assert.equal(request.url, '/dashboard/entity-orders'); assert.equal(request.body.entity_id, 9)
    assert.deepEqual(request.body.items[0], { good_id: 42, price_id: 3, price_revision: 4, quantity: 2, measure_id: 1, measurement })
    h.api.submit(); assert.equal(h.requests.length, 1, 'Prevent duplicate clicks while submitting')
    request.resolve({ order: { number: 'PP-123' } }); await submit
    assert.equal(h.api.chosen.value.length, 0); assert.match(h.api.success.value, /PP-123/)
    assert.deepEqual(h.reloads[0].only, ['orders', 'privatePrices'])
})

test('cabinet validation leaves selected goods available for correction without losing delivery data', async t => {
    const price = { id: 3, revision: 4, entity_id: 9, entity_name: 'Клиент', good_id: 42, price_gross: 120, currency_code: 'RUB', measurement }
    const h = harness(t, 'CustomerEntityPrices', { prices: [price], profile: { phone: '123', delivery_address: 'Адрес' } })
    h.api.toggle(price); const submit = h.api.submit()
    h.requests.at(-1).reject({ response: { status: 422, data: { errors: { 'items.0.price_id': ['Цена изменилась'] } } } }); await submit
    assert.equal(h.api.error.value, 'Цена изменилась'); assert.equal(h.api.chosen.value.length, 1); assert.equal(h.api.address.value, 'Адрес')
})
