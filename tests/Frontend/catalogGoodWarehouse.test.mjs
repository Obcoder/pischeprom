import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const tick = () => new Promise(resolve => setImmediate(resolve))
function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/Catalog/CatalogGoodWarehouse.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'warehouse-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'warehouse-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = []
    let realtimeRefresh
    const props = Vue.reactive({ goodId: 42, active: false, disabled: false, errors: {},
        modelValue: { measure_id: 1 }, overview: { id: 42, name: 'Товар', measure_id: 1 },
        options: { measures: [{ id: 1, name: 'кг' }, { id: 2, name: 'шт.' }] }, ...overrides })
    const axios = Object.fromEntries(['get', 'post', 'patch', 'delete'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))]))
    axios.isCancel = () => false
    const env = { ...Vue, axios, route: (name, params) => `${name}/${typeof params === 'object' ? JSON.stringify(params) : params ?? ''}`,
        CatalogGoodMeasurement: 'CatalogGoodMeasurement', RealtimeStatus: 'RealtimeStatus', window: { confirm: () => true },
        useRealtimeResource: options => { realtimeRefresh = options.load; return { refreshFailed: Vue.ref(false) } },
    }
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(env)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    async function resolveLoad(batch = requests.filter(request => request.method === 'get')) {
        for (const request of batch) {
            const id = request.body.params?.good_id || request.body.params?.good_ids?.[0]
            if (request.url.startsWith('good-warehouse-stock')) request.resolve([{ good_id: id, warehouse_id: 1, measure_id: 1, quantity: 5, measure: { name: 'кг' } }])
            else if (request.url.startsWith('good-stock-movements')) request.resolve({ data: [{ id: 8, good_id: id, warehouse_id: 1, measure_id: 1, type: 'receipt', quantity_delta: 5, unit_price: 10 }], meta: { total: 1, last_page: 1 } })
            else if (request.url.startsWith('purchases')) request.resolve({ data: [{ id: 9, items: [{ good_id: id, measure_id: 1 }, { good_id: 99, measure_id: 2 }] }], meta: { total: 1, last_page: 1 } })
            else if (request.url.startsWith('warehouses')) request.resolve([{ id: 1, code: 'goods', name: 'Основной', is_active: true }])
            else request.resolve([{ id: 1, code: 'RUB' }])
        }
        await tick()
    }
    return { props, api, requests, emitted, resolveLoad, realtimeRefresh: () => realtimeRefresh(), render: templateRenderer(template, api, props) }
}

test('warehouse loads only its good, separates historical units and filters purchase positions', async t => {
    const h = harness(t)
    assert.equal(h.requests.length, 0)
    h.props.active = true; await Vue.nextTick(); await h.resolveLoad()
    const stock = h.requests.find(request => request.url.startsWith('good-warehouse-stock'))
    assert.equal(stock.body.params.good_id, 42)
    assert.equal(h.requests.find(request => request.url.startsWith('good-stock-movements')).body.params.paginate, true)
    assert.deepEqual(h.requests.find(request => request.url.startsWith('purchases')).body.params.good_ids, [42])
    assert.equal(h.api.purchaseRows.value.length, 1)
    h.api.stockRows.value.push({ good_id: 42, warehouse_id: 2, measure_id: 2, quantity: 9, measure: { name: 'шт.' } })
    assert.deepEqual(h.api.balances.value, [{ label: 'кг', quantity: 5 }, { label: 'шт.', quantity: 9 }])
    assert.ok(findVNode(h.render(), node => node.type === 'CatalogGoodMeasurement'))
})

test('manual and realtime refresh update warehouse names, activation and newly added warehouses without resetting a movement draft', async t => {
    const h = harness(t)
    h.props.active = true; await Vue.nextTick(); await h.resolveLoad()
    h.api.openMovement()
    h.api.form.note = 'Несохранённое основание'
    const initialCount = h.requests.length
    const manual = h.api.refresh()
    const manualBatch = h.requests.slice(initialCount)
    const dictionary = manualBatch.find(request => request.url.startsWith('warehouses'))
    assert.ok(dictionary)
    dictionary.resolve([
        { id: 1, code: 'goods', name: 'Переименованный', is_active: false },
        { id: 2, name: 'Новый склад', is_active: true },
    ])
    await h.resolveLoad(manualBatch.filter(request => request !== dictionary)); await manual
    assert.deepEqual(h.api.warehouseOptions.value.map(item => item.title), ['Переименованный · неактивен', 'Новый склад'])
    assert.equal(h.api.form.note, 'Несохранённое основание')
    assert.equal(h.api.form.warehouse_id, 1)

    const manualCount = h.requests.length
    const realtime = h.realtimeRefresh()
    const realtimeBatch = h.requests.slice(manualCount)
    const realtimeDictionary = realtimeBatch.find(request => request.url.startsWith('warehouses'))
    assert.ok(realtimeDictionary)
    realtimeDictionary.resolve([{ id: 1, code: 'goods', name: 'Основной снова открыт', is_active: true }])
    await h.resolveLoad(realtimeBatch.filter(request => request !== realtimeDictionary)); await realtime
    assert.deepEqual(h.api.warehouseOptions.value.map(item => item.title), ['Основной снова открыт'])
    assert.equal(h.api.form.note, 'Несохранённое основание')
    assert.equal(h.requests.filter(request => request.url.startsWith('currencies')).length, 1)
})

test('unsaved measurement cannot create movements or purchases; manual historical edits retain their unit', async t => {
    const h = harness(t)
    h.props.modelValue.measure_id = 2
    h.api.openMovement()
    assert.equal(h.api.movementDialog.value, false)
    assert.equal(h.api.canCreateMovement.value, false)
    const old = { id: 4, warehouse_id: 1, measure_id: null, type: 'adjustment', quantity_delta: -2, unit_price: 5, moved_at: '2026-10-10' }
    h.api.editMovement({ ...old, source_type: 'good_purchase' })
    assert.equal(h.api.movementDialog.value, false)
    h.api.editMovement(old)
    assert.equal(h.api.form.measure_id, null)
    h.api.form.quantity = -1
    await Vue.nextTick()
    assert.ok(h.emitted.some(event => event[0] === 'state' && event[1].dirty))
    const saving = h.api.saveMovement()
    await Vue.nextTick()
    assert.ok(h.emitted.some(event => event[0] === 'state' && event[1].busy))
    const request = h.requests.at(-1)
    assert.equal(request.method, 'patch')
    assert.equal(request.body.good_id, 42)
    assert.equal(request.body.measure_id, null)
    assert.equal(request.body.quantity, -1)
    request.resolve({}); await saving
    assert.ok(h.emitted.some(event => event[0] === 'changed'))
})

test('creating a movement fixes the good and unit; stock errors preserve the draft', async t => {
    const h = harness(t)
    h.api.warehouses.value = [{ id: 1, code: 'goods' }]
    h.api.openMovement('write_off')
    h.api.form.quantity = 100
    const saving = h.api.saveMovement()
    const request = h.requests.at(-1)
    assert.equal(request.method, 'post')
    assert.equal(request.body.good_id, 42)
    assert.equal(request.body.measure_id, 1)
    assert.equal(request.body.type, 'write_off')
    request.reject({ response: { data: { errors: { goods: ['Недостаточно товара'] } } } })
    await saving
    assert.equal(h.api.formError.value, 'Недостаточно товара')
    assert.equal(h.api.form.quantity, 100)
    assert.equal(h.api.movementDialog.value, true)
    assert.equal(h.emitted.some(event => event[0] === 'changed'), false)
})

test('late responses cannot populate another good and leaving the panel cancels requests', async t => {
    const h = harness(t)
    h.props.active = true; await Vue.nextTick()
    const old = [...h.requests]
    h.props.goodId = 43; await Vue.nextTick()
    assert.equal(old[0].body.signal.aborted, true)
    await h.resolveLoad(h.requests.slice(5))
    await h.resolveLoad(old)
    assert.equal(h.api.stockRows.value[0].good_id, 43)
    assert.equal(h.api.purchaseRows.value[0].good_id, 43)
    h.api.pageMovements(2)
    const current = h.requests.at(-1)
    h.props.active = false; await Vue.nextTick()
    assert.equal(current.body.signal.aborted, true)
})
