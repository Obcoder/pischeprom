import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { buildingApartmentLabel } from '../../resources/js/utils/buildingApartments.js'

function pageHarness() {
    const filename = fileURLToPath(new URL('../../resources/js/Pages/Ameise/Orders/Index.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'orders-index' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'orders-index', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], disposal = []
    const environment = {
        ...Vue, Link: {}, router: {}, VerwalterLayout: {}, OrderStatusesDialog: {},
        buildingApartmentLabel,
        route: () => '', useHead: () => {}, useDebounceFn: callback => callback,
        onMounted: () => {}, onBeforeUnmount: callback => disposal.push(callback),
        axios: {
            get(url, options) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup({ permissions: {} }, { expose: () => {} }))
    return { api, requests, dispose() { disposal.forEach(callback => callback()); scope.stop() } }
}

test('order rows retain apartment numbers for each delivery building', t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    assert.equal(harness.api.buildingsLabel({ buildings: [
        { address: 'Ленина, 10', apartment: { number: '7', type: 'apartment' } },
        { address: 'Мира, 5', apartment: { number: '2', type: 'premise' } },
    ] }), 'Ленина, 10, кв. 7 · Мира, 5, пом. 2')
})

test('filter menu edits stay in a draft until applied, and reopening discards cancelled edits', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests } = harness
    api.filters.status_id = 1
    api.filterMenuOpen.value = true
    await Vue.nextTick()
    assert.equal(api.draftFilters.status_id, 1)
    api.draftFilters.status_id = 2
    api.chooseDeliveryDate('2026-09-30')
    assert.equal(api.filters.status_id, 1)
    assert.equal(requests.length, 0)
    api.filterMenuOpen.value = false
    await Vue.nextTick()
    api.filterMenuOpen.value = true
    await Vue.nextTick()
    assert.equal(api.draftFilters.status_id, 1)
    api.draftFilters.status_id = 2
    api.draftFilters.total_from = 0
    api.applyFilters()
    assert.equal(api.filterMenuOpen.value, false)
    assert.equal(requests[0].options.params.status_id, 2)
    assert.equal(requests[0].options.params.total_from, 0)
    assert.equal(requests[0].options.params.page, 1)
})

test('delivery filters are mutually exclusive and filter removal retains search and sorting', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests } = harness
    api.chooseDeliveryDate('2026-09-30')
    api.toggleUnscheduled(true)
    assert.equal(api.draftFilters.delivery_date, '')
    api.chooseDeliveryDate('2026-10-01')
    assert.equal(api.draftFilters.delivery_unscheduled, false)
    api.filters.sort_by = 'total_amount'
    api.filters.status_id = 2
    api.filters.search = 'Какао'
    await Vue.nextTick()
    api.removeFilter('status_id')
    const params = requests.at(-1).options.params
    assert.equal(params.status_id, undefined)
    assert.equal(params.search, 'Какао')
    assert.equal(params.sort_by, 'total_amount')
    assert.equal(api.hasActiveFilters.value, true)
})

test('newer order requests win and late failures cannot hide results or report errors', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests } = harness
    const first = api.fetchOrders()
    api.filters.page = 2
    const second = api.fetchOrders()
    assert.equal(requests[0].options.signal.aborted, true)
    requests[1].resolve({ data: [{ id: 22 }], meta: { current_page: 2, last_page: 3, total: 60 } })
    await second
    requests[0].reject(new Error('Late failure'))
    await first
    assert.deepEqual(api.orders.value, [{ id: 22 }])
    assert.equal(api.meta.current_page, 2)
    assert.equal(api.loading.value, false)
    assert.equal(api.errorMessage.value, '')
})

test('deleting a selected status clears its filter, and totals keep currencies separate', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests } = harness
    api.filters.status_id = 99
    api.filters.page = 3
    api.statusesChanged([{ id: 1, name: 'Открыт' }])
    assert.equal(api.filters.status_id, null)
    assert.equal(requests[0].options.params.status_id, undefined)
    assert.equal(requests[0].options.params.page, 1)
    requests[0].resolve({ data: [
        { total_amount: '100.50', currency_code: 'RUB' },
        { total_amount: '200', currency_code: 'RUB' },
        { total_amount: '5', currency_code: 'USD' },
    ], meta: { current_page: 1, total: 3 } })
    await Vue.nextTick()
    assert.equal(api.pageTotals.value, '300,5 ₽ · 5 USD')
    assert.equal(api.pageRange.value, '1–3 из 3')
})

test('dictionary errors survive successful order loads and unmount cancels pending requests', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests } = harness
    const options = api.fetchOptions()
    requests[0].reject(new Error('No dictionaries'))
    await options
    const orders = api.fetchOrders()
    requests[1].resolve({ data: [] })
    await orders
    assert.match(api.optionsError.value, /справочники/)
    const pending = api.fetchOrders()
    harness.dispose()
    assert.equal(requests[2].options.signal.aborted, true)
    requests[2].resolve({ data: [{ id: 99 }] })
    await pending
    assert.deepEqual(api.orders.value, [])
})
