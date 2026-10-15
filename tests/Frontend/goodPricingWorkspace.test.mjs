import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'
import { avitoPriceBlockReason } from '../../resources/js/Services/goodAvito.js'
const flush = () => new Promise(resolve => setImmediate(resolve))
function harness(t, name, initial) {
    const filename = `resources/js/Components/Goods/${name}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename }); assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: name })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: name, compilerOptions: { bindingMetadata: script.bindings } }); assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], reloads = []
    const props = Vue.reactive(initial)
    const env = { ...Vue, avitoPriceBlockReason, GoodAvitoPriceDialog: { name: 'GoodAvitoPriceDialog' }, PriceConstructionChain: { name: 'PriceConstructionChain' }, router: { reload: options => reloads.push(options) }, axios: Object.fromEntries(['get', 'put', 'post', 'patch', 'delete'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])) }
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


test('saving a price immediately adds its immutable calculation snapshot to the archive', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true }); h.requests[0].resolve(dataset()); await flush()
    const row = h.api.rows.value[0]; row.amount = 156; h.api.touch(row)
    const snapshot = { id: 21, scope: 'common', name: 'Розничная 156', currency_id: 1, measurement, input: { method: 'manual', amount: 156 }, result: { price_gross: 156, price_net: 130 }, price_gross: 156, price_net: 130 }
    const save = h.api.run(row, true); h.requests.at(-1).resolve({ ...source.rows[0], price_gross: 156, revision: 2, saved_calculation: snapshot }); await save
    assert.equal(h.api.archive.value.length, 1)
    assert.equal(h.api.archive.value[0].id, 21)
    assert.match(row.success, /добавлен в сохранённые/)
    h.api.tab.value = 'calculations'; await Vue.nextTick()
    assert.ok(findVNode(h.render(), node => node.type === Vue.Text && String(node.children).includes('Розничная 156')))
})

test('a standalone calculation saves without a price assignment and preserves its full ingredient chain', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true }); h.requests[0].resolve(dataset()); await flush()
    h.api.add(); const row = h.api.rows.value.at(-1)
    Object.assign(row, { method: 'margin', source_key: 'manual', purchase_amount: 100, parameter: 20, calculation_name: 'Тест закупки', elements: [{ element_id: 8, element_revision: 2, name: 'Логистика', kind: 'fixed', value: 10, currency_id: 1, measurement, enabled: true }] })
    const save = h.api.saveCalculation(row), request = h.requests.at(-1)
    assert.equal(request.url, '/api/goods/42/pricing/calculations')
    assert.equal(request.body.target_id, null)
    assert.equal(request.body.purchase_amount, 100)
    assert.equal(request.body.elements[0].name, 'Логистика')
    assert.equal(request.body.calculation_name, 'Тест закупки')
    request.resolve({ id: 30, scope: 'common', input: request.body, result: { price_gross: 165, price_net: 137.5 }, measurement }); await save
    assert.equal(row.result.price_gross, 165); assert.equal(row.id, null)
    assert.equal(h.api.archive.value[0].id, 30)
    assert.match(row.success, /Расчёт сохранён/)
})

test('private archives can only be selected for their Entity and repeated copies retain the original unit snapshot', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true })
    const oldUnit = { measure_id: 2, unit_label: 'уп.', kilograms_per_unit: 25 }
    const snapshot = { id: 15, scope: 'entity', entity_id: 9, target_name: 'Клиент А', currency_id: 1, measurement: oldUnit, input: { method: 'markup', source_key: 'manual', purchase_amount: 100, elements: [{ name: 'Упаковка', value: 3 }] }, result: { price_gross: 150 } }
    h.requests[0].resolve({ ...dataset(), saved_calculations: [snapshot] }); await flush()
    assert.deepEqual(h.api.availableCalculations(h.api.rows.value[0]), [])
    h.api.add('entity', { id: 10, name: 'Клиент Б' }); assert.deepEqual(h.api.availableCalculations(h.api.rows.value.at(-1)), [])
    h.api.useCalculation(h.api.archive.value[0], true); const row = h.api.rows.value.at(-1)
    assert.equal(row.scope, 'entity'); assert.equal(row.target_id, 9)
    assert.deepEqual(h.api.payload(row).measurement, oldUnit)
    row.elements[0].value = 99
    assert.equal(h.api.archive.value[0].input.elements[0].value, 3)
    assert.equal(h.api.availableCalculations(row).length, 1)
})

test('library CRUD uses revisions and never changes ingredient instances already copied into a calculation', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true })
    const element = { id: 8, revision: 2, name: 'Хранение', kind: 'fixed', value: 12, currency_id: 1, measurement, includes_vat: false, vat_rate: 0, percentage_base: 'running_cost' }
    h.requests[0].resolve({ ...dataset(), elements: [element] }); await flush()
    const row = h.api.rows.value[0]; row.elements.push({ ...structuredClone(element), element_id: 8, element_revision: 2 })
    h.api.editElement(h.api.data.value.elements[0]); h.api.libraryDraft.value.value = 25
    const save = h.api.saveElement(), request = h.requests.at(-1)
    assert.equal(request.method, 'patch'); assert.equal(request.body.revision, 2)
    request.resolve({ ...element, value: 25, revision: 3 }); await save
    assert.equal(h.api.data.value.elements[0].value, 25); assert.equal(row.elements[0].value, 12)
    const remove = h.api.removeElement(h.api.data.value.elements[0]); assert.equal(h.requests.at(-1).body.data.revision, 3)
    h.requests.at(-1).resolve(null); await remove
    assert.equal(h.api.data.value.elements.length, 0); assert.equal(row.elements[0].value, 12)
})

test('a calculation response from an old product never leaks into a newly opened product archive', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true }); h.requests[0].resolve(dataset()); await flush()
    const save = h.api.saveCalculation(h.api.rows.value[0]), old = h.requests.at(-1)
    h.props.goodId = 44; await Vue.nextTick(); h.requests.at(-1).resolve(dataset()); await flush()
    old.resolve({ id: 99, scope: 'entity', result: { price_gross: 999 } }); await save
    assert.equal(h.api.archive.value.length, 0)
})

test('price cubes copy reusable elements and retain stable source metadata through reorder and disable', async t => {
    const element = { id: 8, revision: 2, name: 'Логистика', kind: 'fixed', value: 10, currency_id: 1, measurement, includes_vat: false }
    const row = { method: 'markup', elements: [], currency_id: 1, rounding_step: 1, busy: false }
    const h = harness(t, 'PriceConstructionChain', { row, elements: [element], sources: [], calculations: [], measurement, currency: 'RUB' })
    h.api.addElement(8)
    assert.equal(row.elements[0].element_revision, 2); assert.deepEqual(row.elements[0].measurement, measurement)
    row.elements[0].value = 20; assert.equal(element.value, 10)
    row.elements.push({ name: 'Реклама', value: 3, enabled: true }); h.api.move(1, -1)
    assert.equal(row.elements[0].name, 'Реклама'); assert.equal(row.elements[1].element_id, 8)
    row.method = 'manual'; h.api.changeMethod(); assert.equal(row.rounding_step, 0)
    const rendered = h.render()
    assert.ok(findVNode(rendered, node => node.type === 'input' && node.props?.['aria-label'] === 'Цена'))
    assert.equal(findVNode(rendered, node => node.type === 'select' && node.props?.['aria-label'] === 'Источник себестоимости'), null)
})


test('the outgoing VAT cube uses its pre-rounding contribution and shows final VAT only in the total', t => {
    const row = { method: 'markup', elements: [], currency_id: 1, parameter: 25, vat_rate: 20, rounding_step: 7, busy: false, result: { price_gross: 154, price_net: 128.3333, cost_net: 100, profit_before_rounding: 25, profit: 28.3333, vat_amount: 25.6667, rounding_gross: 4, breakdown: [{ key: 'purchase', amount: 100 }, { key: 'profit', amount: 25 }, { key: 'sale_vat', amount: 25 }, { key: 'rounding', amount: 4 }] } }
    const h = harness(t, 'PriceConstructionChain', { row, elements: [], sources: [], calculations: [], measurement, currency: 'RUB' })
    assert.equal(h.api.contribution('sale_vat'), 25)
    assert.equal(h.api.contribution('rounding'), 4)
    const text = vnode => typeof vnode === 'string' ? vnode : Array.isArray(vnode) ? vnode.map(text).join(' ') : vnode && text(vnode.children) || ''
    const rendered = h.render()
    const vatCube = findVNode(rendered, node => node.type === 'article' && node.props?.class === 'cube vat-cube')
    const totalCube = findVNode(rendered, node => node.type === 'article' && node.props?.class === 'cube total-cube')
    assert.match(text(vatCube), /25.*до округления/)
    assert.doesNotMatch(text(vatCube), /25,6667/)
    assert.match(text(totalCube), /НДС после округления.*25,6667/)
    assert.equal(100 + row.result.profit_before_rounding + h.api.contribution('sale_vat') + h.api.contribution('rounding'), row.result.price_gross)
})


test('expense cubes show their net contribution and running cost directly in the chain', t => {
    const row = { method: 'markup', elements: [{ name: 'Логистика', kind: 'fixed', value: 12, includes_vat: true, vat_rate: 20, currency_id: 1, measurement, enabled: true }], currency_id: 1, busy: false, result: { breakdown: [{ key: 'element:0', amount: 10, subtotal: 110 }] } }
    const h = harness(t, 'PriceConstructionChain', { row, elements: [], sources: [], calculations: [], measurement, currency: 'RUB' })
    assert.equal(h.api.contribution('element:0'), 10)
    assert.equal(h.api.part('element:0').subtotal, 110)
    const text = vnode => typeof vnode === 'string' ? vnode : Array.isArray(vnode) ? vnode.map(text).join(' ') : vnode && text(vnode.children) || ''
    const expense = findVNode(h.render(), node => node.type === 'article' && String(node.props?.class).includes('extra-cube'))
    assert.match(text(expense), /10.*вклад без НДС.*Затраты → 110/)
})


test('selecting a price type preserves exact manual input and an explicitly chosen manual rounding step', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true })
    const data = dataset(); data.types[0].rounding_step = 5
    h.requests[0].resolve(data); await flush()
    h.api.add(); const row = h.api.rows.value.at(-1)
    row.method = 'manual'; row.amount = 99.9999; row.target_id = 2
    h.api.selectType(row)
    assert.equal(h.api.payload(row).rounding_step, 0)
    assert.equal(h.api.payload(row).amount, 99.9999)
    row.rounding_step = 0.01; h.api.selectType(row)
    assert.equal(h.api.payload(row).rounding_step, 0.01, 'An operator-selected manual step is preserved too')
    row.method = 'markup'; h.api.selectType(row)
    assert.equal(h.api.payload(row).rounding_step, 5, 'Calculated pricing may use the price type default')
})


test('editing a fixed library cost after a unit change requires explicit confirmation without converting its value', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true })
    const current = { measure_id: 2, unit_label: 'уп.', kilograms_per_unit: 25 }
    const element = { id: 8, revision: 3, name: 'Логистика', kind: 'fixed', value: 10, currency_id: 1, measurement, includes_vat: false, vat_rate: 0, percentage_base: 'running_cost' }
    h.requests[0].resolve({ ...dataset(), measurement: current, elements: [element] }); await flush()
    h.api.editElement(h.api.data.value.elements[0]); h.api.libraryDraft.value.value = 15
    assert.equal(h.api.libraryMeasurementChanged.value, true)
    const requestCount = h.requests.length; await h.api.saveElement()
    assert.equal(h.requests.length, requestCount, 'A stale fixed-unit snapshot cannot be silently saved')
    assert.match(h.api.libraryError.value, /Единица или масса товара изменилась/)
    assert.deepEqual(h.api.libraryDraft.value.measurement, measurement)
    h.api.rebaseLibraryMeasurement()
    assert.equal(h.api.libraryMeasurementChanged.value, false)
    assert.equal(h.api.libraryDraft.value.dirty, true)
    assert.equal(h.api.libraryDraft.value.value, 15, 'Only the operator changes the monetary value')
    const save = h.api.saveElement(), request = h.requests.at(-1)
    assert.deepEqual(request.body.measurement, current); assert.equal(request.body.value, 15)
    request.resolve({ ...element, value: 15, measurement: current, revision: 4 }); await save
    assert.deepEqual(element.measurement, measurement, 'The original source object is not mutated')
})

test('percentage library elements use the current unit snapshot on save without modifying their percentage', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true })
    const current = { ...measurement, kilograms_per_unit: 25 }
    const element = { id: 8, revision: 3, name: 'Реклама', kind: 'percent', value: 10, currency_id: 1, measurement, includes_vat: false, vat_rate: 0, percentage_base: 'running_cost' }
    h.requests[0].resolve({ ...dataset(), measurement: current, elements: [element] }); await flush()
    h.api.editElement(h.api.data.value.elements[0])
    assert.equal(h.api.libraryMeasurementChanged.value, true, 'Mass changes are detected even with the same unit label')
    const save = h.api.saveElement(), request = h.requests.at(-1)
    assert.deepEqual(request.body.measurement, current); assert.equal(request.body.value, 10)
    request.resolve({ ...element, measurement: current, revision: 4 }); await save
    assert.equal(h.api.libraryError.value, '')
})

test('quotation selection keeps explicit price currency and applies only known VAT terms', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true })
    const data = dataset()
    data.sources = [{ key: 'quotation:4', kind: 'quotation', currency_id: 2, currency_code: 'USD', price_includes_vat: false, vat_rate: 0 }, { key: 'quotation:5', kind: 'quotation', currency_id: null, price_includes_vat: null, vat_rate: null }]
    h.requests[0].resolve(data); await flush()
    const row = h.api.rows.value[0]
    row.source_key = 'quotation:4'; row.method = 'margin'; row.parameter = 25
    h.api.selectSource(row)
    assert.equal(row.currency_id, 1, 'Source selection does not relabel a RUB price or its existing expenses as USD')
    assert.equal(row.source_includes_vat, false); assert.equal(row.source_vat_rate, 0)
    assert.equal(row.parameter, 25); assert.equal(row.dirty, true)
    row.source_includes_vat = true; row.source_vat_rate = 10; row.source_key = 'quotation:5'; h.api.selectSource(row)
    assert.equal(row.source_includes_vat, true); assert.equal(row.source_vat_rate, 10, 'Unknown legacy VAT terms must not overwrite the operator input')
})

test('source refresh after a Market edit retains drafts and invalidates only changed source previews', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true })
    const data = dataset(); data.sources = [{ key: 'quotation:4', currency_id: null, price: 258 }]
    h.requests[0].resolve(data); await flush()
    const row = h.api.rows.value[0]
    Object.assign(row, { source_key: 'quotation:4', method: 'margin', parameter: 24, dirty: true, result: { price_gross: 322 } })
    h.api.add(); const manual = h.api.rows.value.at(-1); manual.amount = 100; manual.result = { price_gross: 100 }
    const request = h.api.refreshSources()
    h.requests.at(-1).resolve({ ...data, sources: [{ key: 'quotation:4', currency_id: 1, price: 258 }] }); await request
    assert.equal(row.parameter, 24); assert.equal(row.dirty, true); assert.equal(row.result, null)
    assert.equal(manual.amount, 100); assert.equal(manual.result.price_gross, 100); assert.equal(h.api.rows.value.length, 2)
    assert.equal(h.api.data.value.sources[0].currency_id, 1)
    const outdated = h.api.refreshSources(), stale = h.requests.at(-1)
    const latest = h.api.refreshSources(); h.requests.at(-1).resolve({ ...data, sources: [{ key: 'quotation:4', currency_id: 2 }] }); await latest
    stale.resolve({ ...data, sources: [{ key: 'quotation:4', currency_id: null }] }); await outdated
    assert.equal(h.api.data.value.sources[0].currency_id, 2, 'An older response cannot restore incomplete quotation metadata')
})

test('source warning distinguishes missing currency from a real mismatch and opens Market for correction', t => {
    const row = { method: 'markup', source_key: 'quotation:4', elements: [], currency_id: 1, busy: false }
    const source = { key: 'quotation:4', kind: 'quotation', currency_id: null, price: 258, denominator: 1, unit_label: 'кг' }
    const h = harness(t, 'PriceConstructionChain', { row, elements: [], sources: [source], calculations: [], measurement, currency: 'RUB' })
    assert.match(h.api.sourceWarning.value, /не указана валюта/)
    assert.match(h.api.sourceLabel(source), /валюта не указана/)
    const button = findVNode(h.render(), node => node.type === 'button' && String(node.children).includes('Открыть «Маркет»'))
    assert.ok(button); button.props.onClick(); assert.ok(h.emitted.some(([name]) => name === 'market'))
    h.props.sources[0].currency_id = 2; h.props.sources[0].currency_code = 'USD'
    assert.match(h.api.sourceWarning.value, /Источник в USD, расчёт в RUB/)
    assert.equal(findVNode(h.render(), node => node.type === 'button' && String(node.children).includes('Открыть «Маркет»')), null)
    h.props.sources[0].is_current = false
    assert.match(h.api.sourceWarning.value, /срок истёк/)
    assert.equal(findVNode(h.render(), node => node.type === 'option' && node.props?.value === 'quotation:4').props.disabled, true)
    h.props.sources[0].price_includes_vat = true; h.props.sources[0].vat_rate = 10
    h.props.row.source_includes_vat = true; h.props.row.source_vat_rate = 20
    assert.equal(h.api.sourceVatDiffers.value, true)
    assert.equal(h.api.sourceVatLabel.value, 'С НДС · ставка 10%')
    const applyVat = findVNode(h.render(), node => node.type === 'button' && String(node.children).includes('Взять НДС предложения'))
    assert.ok(applyVat); applyVat.props.onClick(); assert.ok(h.emitted.some(([name]) => name === 'source'))
})
