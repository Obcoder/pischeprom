import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

const projectRoot = new URL('../../', import.meta.url)

// Run the actual compiled page loaders with Vue reactivity and deferred HTTP
// responses. Rendering widgets and transport setup are outside these tests.
function pageHarness(filename, { props = {}, purchases = false } = {}) {
    const requests = []
    const disposal = []
    let registration
    let scopeController = new AbortController()
    let resourceState
    const environment = {
        ...Vue,
        onMounted: () => {},
        onBeforeUnmount: callback => disposal.push(callback),
        watch: () => () => {},
        route: name => name,
        axios: {
            get(url, options = {}) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ method: 'GET', url, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            patch(url, body, options = {}) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ method: 'PATCH', url, body, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
        useRealtimeResource: options => {
            registration = options
            resourceState = Vue.reactive(options.initialValue)
            return {
                state: resourceState,
                refreshFailed: Vue.ref(false),
                get signal() { return scopeController.signal },
            }
        },
        useEntityApi: () => ({}),
        useEntityForm: () => ({ form: Vue.reactive({}) }),
        usePurchaseForm: () => ({
            form: Vue.reactive({ items: [{ quantity: 19 }], note: 'draft' }),
            isEdit: Vue.ref(false),
            payload: Vue.ref({}),
        }),
        useDate: () => ({}),
        useForm: initial => Vue.reactive({ ...initial, errors: {}, reset: () => {} }),
        saleRequestId: () => 'test-request-id',
    }

    function stripImports(script) {
        return script.replace(/^import (.+?) from ['"].*['"];?$/gm, (_, imports) => {
            const names = imports.startsWith('{')
                ? imports.slice(1, -1).split(',').map(part => part.trim().split(/\s+as\s+/).at(-1))
                : [imports]
            for (const name of names) {
                if (!(name in environment)) environment[name] = {}
            }
            return ''
        })
    }

    if (purchases) {
        const source = stripImports(readFileSync(new URL('resources/js/Composables/usePurchases.js', projectRoot), 'utf8'))
            .replace('export function usePurchases', 'function usePurchases')
        environment.usePurchases = new Function('env', `with(env){${source};return usePurchases}`)(environment)
    }

    const absoluteFilename = fileURLToPath(new URL(filename, projectRoot))
    const { descriptor, errors } = parse(readFileSync(absoluteFilename, 'utf8'), { filename: absoluteFilename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: filename })
    const template = compileTemplate({
        source: descriptor.template.content,
        filename: absoluteFilename,
        id: filename,
        compilerOptions: { bindingMetadata: compiled.bindings },
    })
    assert.deepEqual(template.errors, [])
    const script = stripImports(compiled.content).replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const api = component.setup(props, { expose: () => {}, emit: () => {} })

    return {
        api,
        requests,
        refresh: () => registration.load({ signal: new AbortController().signal }),
        dispose: () => disposal.forEach(callback => callback()),
        changeActor: () => {
            scopeController.abort()
            scopeController = new AbortController()
        },
    }
}

test('goods catchup preserves newer stock, initial dictionaries, filters and unsaved movement', async () => {
    const { api, requests, refresh, dispose } = pageHarness('resources/js/Components/Warehouses/GoodStockPanel.vue', {
        props: { warehouses: [{ id: 1, code: 'goods' }], measures: [] },
    })
    api.form.quantity = 41
    api.form.note = 'draft'
    api.stockFilters.search = 'selected'

    const initial = api.loadAll()
    assert.equal(requests.length, 4)
    const catchup = refresh()
    assert.equal(requests.length, 6, 'background stock update does not reload the whole goods dictionary')
    requests[4].resolve([{ good_id: 1, quantity: 7, stock_value: 70 }])
    requests[5].resolve([{ id: 20 }])
    await catchup

    requests[0].resolve([{ good_id: 1, quantity: 10 }])
    requests[1].resolve([{ id: 1 }])
    requests[2].resolve([{ id: 11, name: 'Initial dictionary' }])
    requests[3].resolve([{ id: 5 }])
    await initial
    assert.equal(api.stockRows.value[0].quantity, 7)
    assert.equal(api.goods.value[0].name, 'Initial dictionary')
    assert.equal(api.form.quantity, 41)
    assert.equal(api.form.note, 'draft')
    assert.equal(api.stockFilters.search, 'selected')

    const pending = refresh()
    assert.equal(api.loading.value, false)
    dispose()
    requests[6].resolve([{ quantity: 999 }])
    requests[7].resolve([])
    await pending
    assert.equal(api.stockRows.value[0].quantity, 7, 'disposed resource ignores late HTTP responses')
})

test('warehouse catchup keeps initial commodity and measure dictionaries without stale balances', async () => {
    const { api, requests, refresh } = pageHarness('resources/js/Pages/Ameise/Warehouses.vue')
    const initial = api.loadAll()
    const catchup = refresh()
    requests[5].resolve([{ id: 2, code: 'storage' }])
    requests[6].resolve([{ quantity: 8 }])
    requests[7].resolve([{ id: 3 }])
    await catchup
    requests[0].resolve([{ id: 1 }])
    requests[1].resolve([{ quantity: 99 }])
    requests[2].resolve([])
    requests[3].resolve([{ id: 3, name: 'Commodity' }])
    requests[4].resolve([{ id: 4, name: 'kg' }])
    await initial
    assert.equal(api.stockRows.value[0].quantity, 8)
    assert.equal(api.commodities.value[0].name, 'Commodity')
    assert.equal(api.measures.value[0].name, 'kg')
})

test('sales refresh updates totals and details using applied filters without altering draft lines', async () => {
    const { api, requests, refresh } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    api.filters.month = '2026-09'
    api.options.page = 2
    const initial = api.fetchSales()
    requests[0].resolve({ data: [{ id: 5, total: 10 }], meta: { total: 1, total_amount: 10 } })
    await initial
    api.filters.date_from = '2030-01-01'
    api.saleForm.goods = [{ quantity: 37 }]
    api.detailsDialog.value = true
    api.selectedSale.value = { id: 5, total: 10 }
    api.detailsLine.quantity = 12

    const pending = refresh()
    assert.equal(api.loading.value, false)
    assert.equal(requests[1].options.params.page, 2)
    assert.equal(requests[1].options.params.date_from, undefined)
    requests[1].resolve({ data: [{ id: 5, total: 20 }], meta: { total: 1, total_amount: 20 } })
    requests[2].resolve({ id: 5, total: 20 })
    await pending
    assert.equal(api.totalAmount.value, 20)
    assert.equal(api.selectedSale.value.total, 20)
    assert.equal(api.saleForm.goods[0].quantity, 37)
    assert.equal(api.detailsLine.quantity, 12)
    assert.equal(api.filters.date_from, '2030-01-01')
})

test('sale date save updates the open sale and refreshes previous sales using applied filters', async () => {
    const { api, requests } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    const sale = { id: 5, date: '2026-09-20T00:00:00.000000Z', total: 10 }
    const laterSale = { id: 6, date: '2026-09-21', total: 20, previous_sale: { id: 5, total: 10, days: 1 } }
    api.filters.month = '2026-09'
    api.options.page = 2
    const initial = api.fetchSales()
    requests[0].resolve({ data: [laterSale, sale], meta: { total: 2, total_amount: 30 } })
    await initial
    api.openSaleDetails(api.rows.value[1])
    api.openSaleDateEdit(api.rows.value[1])
    assert.equal(api.dateEditDialog.value, true)
    assert.equal(api.dateForm.saleId, 5)
    assert.equal(api.dateForm.date, '2026-09-20')
    api.dateForm.date = '2026-09-10'
    api.filters.month = '2026-10'
    api.filters.date_from = '2030-01-01'

    const pending = api.saveSaleDate()
    assert.equal(api.dateSaving.value, true)
    assert.equal(requests[1].method, 'PATCH')
    assert.equal(requests[1].url, '/api/sales/5')
    assert.deepEqual(requests[1].body, { date: '2026-09-10' })
    await api.saveSaleDate()
    assert.equal(requests.length, 2, 'repeated submission does not send a second mutation')
    const updatedSale = { ...sale, date: '2026-09-10' }
    requests[1].resolve({ data: updatedSale })
    await Promise.resolve()
    assert.equal(api.rows.value[1].date, '2026-09-10')
    assert.equal(api.selectedSale.value.date, '2026-09-10')
    assert.equal(api.dateEditDialog.value, false)
    assert.equal(requests[2].url, '/api/sales')
    assert.equal(requests[2].options.params.month, '2026-09')
    assert.equal(requests[2].options.params.page, 2)
    assert.equal(requests[2].options.params.date_from, undefined)
    assert.equal(requests[3].url, '/api/sales/5')
    requests[2].resolve({
        data: [{ ...laterSale, previous_sale: { id: 5, total: 10, days: 11 } }, updatedSale],
        meta: { total: 2, total_amount: 30 },
    })
    requests[3].resolve({ data: updatedSale })
    await pending
    assert.equal(api.dateSaving.value, false)
    assert.equal(api.rows.value[0].previous_sale.days, 11)
    assert.equal(api.selectedSale.value.date, '2026-09-10')
    assert.equal(api.filters.month, '2026-10')
    assert.equal(api.filters.date_from, '2030-01-01')
})

test('sale date validation keeps the editor and draft open without changing saved sale data', async () => {
    const { api, requests } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    const sale = { id: 5, date: '2026-09-20', total: 10 }
    api.rows.value = [sale]
    api.openSaleDetails(api.rows.value[0])
    api.openSaleDateEdit(api.rows.value[0])
    api.dateForm.date = ''
    await api.saveSaleDate()
    assert.equal(requests.length, 0, 'an empty date does not reach the server')
    api.dateForm.date = '2026-02-30'

    const pending = api.saveSaleDate()
    requests[0].reject({ response: {
        status: 422,
        data: { message: 'Некорректная дата продажи.', errors: { date: ['Некорректная дата продажи.'] } },
    } })
    await pending
    assert.equal(api.dateSaving.value, false)
    assert.equal(api.dateEditDialog.value, true)
    assert.equal(api.dateForm.date, '2026-02-30')
    assert.equal(api.dateErrorMessage.value, 'Некорректная дата продажи.')
    assert.equal(api.rows.value[0].date, '2026-09-20')
    assert.equal(api.selectedSale.value.date, '2026-09-20')
    assert.equal(requests.length, 1, 'failed validation does not refresh or replace saved data')
})

test('sales realtime refresh updates saved dates without overwriting an open date draft', async () => {
    const { api, requests, refresh } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    api.rows.value = [{ id: 5, date: '2026-09-20', total: 10 }]
    api.openSaleDetails(api.rows.value[0])
    api.openSaleDateEdit(api.rows.value[0])
    api.dateForm.date = '2026-09-10'

    const pending = refresh()
    const remotelyUpdatedSale = { id: 5, date: '2026-09-18', total: 10 }
    requests[0].resolve({ data: [remotelyUpdatedSale], meta: { total: 1, total_amount: 10 } })
    requests[1].resolve({ data: remotelyUpdatedSale })
    await pending
    assert.equal(api.rows.value[0].date, '2026-09-18')
    assert.equal(api.selectedSale.value.date, '2026-09-18')
    assert.equal(api.dateEditDialog.value, true)
    assert.equal(api.dateForm.saleId, 5)
    assert.equal(api.dateForm.date, '2026-09-10')
})

test('entity sales ignores a slow response for the previously selected entity', async () => {
    const entity = Vue.reactive({ id: 1 })
    const { api, requests } = pageHarness('resources/js/Components/Dictionaries/Entities/EntitySalesCard.vue', { props: { entity } })
    const first = api.fetchSales()
    entity.id = 2
    const second = api.fetchSales()
    requests[2].resolve({ data: [{ id: 20 }], meta: { total_amount: 20 } })
    requests[3].resolve([])
    await second
    requests[0].resolve({ data: [{ id: 10 }], meta: { total_amount: 10 } })
    requests[1].resolve([])
    await first
    assert.equal(api.rows.value[0].id, 20)
    assert.equal(api.totalAmount.value, 20)
})

test('purchase refresh keeps submitted array filters, pagination and unsaved form; newest page wins', async () => {
    const { api, requests, refresh } = pageHarness('resources/js/Pages/Purchases/Purchases.vue', { purchases: true })
    api.filters.search = 'applied'
    api.filters.entity_ids = [11]
    const initial = api.loadPurchases(3)
    requests[0].resolve({ data: [{ id: 7, total: 10 }], meta: { total: 301, current_page: 3, last_page: 4 } })
    await initial
    api.filters.search = 'draft-search'
    api.filters.entity_ids.push(22)
    api.dialog.value = true

    const pending = refresh()
    assert.equal(api.loading.value, false)
    assert.equal(requests[1].options.params.search, 'applied')
    assert.deepEqual(requests[1].options.params.entity_ids, [11])
    assert.equal(requests[1].options.params.page, 3)
    requests[1].resolve({ data: [{ id: 7, total: 20 }], meta: { total: 302, current_page: 3, last_page: 4 } })
    await pending
    assert.equal(api.items.value[0].total, 20)
    assert.equal(api.form.items[0].quantity, 19)
    assert.equal(api.form.note, 'draft')
    assert.equal(api.dialog.value, true)
    assert.equal(api.page.value, 3)
    assert.equal(api.filters.search, 'draft-search')

    const old = api.loadPurchases(1)
    const latest = api.loadPurchases(2)
    requests[3].resolve({ data: [{ id: 222 }], meta: { current_page: 2 } })
    await latest
    requests[2].resolve({ data: [{ id: 111 }], meta: { current_page: 1 } })
    await old
    assert.equal(api.items.value[0].id, 222)
    assert.equal(api.pagination.value.current_page, 2)
})

test('legacy sale refresh preserves an open add-good draft while its saved sale changes', async () => {
    const { api, requests, refresh } = pageHarness('resources/js/Pages/Ameise/Sales.vue')
    api.showFormAttachGood.value = true
    api.formAttachGood.sale_id = 5
    api.formAttachGood.quantity = 17
    const pending = refresh()
    requests[0].resolve([{ id: 5, total: 20 }])
    await Promise.resolve()
    requests[1].resolve({ id: 5, total: 20 })
    await pending
    assert.equal(api.sales.value[0].total, 20)
    assert.equal(api.sale.value.total, 20)
    assert.equal(api.showFormAttachGood.value, true)
    assert.equal(api.formAttachGood.quantity, 17)
})

test('deleting an open sale still updates the list and totals without resetting its draft line', async () => {
    const { api, requests, refresh } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    api.detailsDialog.value = true
    api.selectedSale.value = { id: 5, total: 10 }
    api.detailsLine.quantity = 12
    const pending = refresh()
    requests[0].resolve({ data: [{ id: 6, total: 30 }], meta: { total: 1, total_amount: 30 } })
    requests[1].reject({ response: { status: 404 } })
    await pending
    assert.equal(api.totalAmount.value, 30)
    assert.equal(api.rows.value[0].id, 6)
    assert.equal(api.selectedSale.value, null)
    assert.equal(api.detailsDialog.value, false)
    assert.equal(api.detailsLine.quantity, 12)
    assert.match(api.errorMessage.value, /удалена/)
})

test('deleting an open purchase updates its list and closes obsolete details without touching the edit form', async () => {
    const { api, requests, refresh } = pageHarness('resources/js/Pages/Purchases/Purchases.vue', { purchases: true })
    api.detailsDialog.value = true
    api.selectedPurchase.value = { id: 5, amount: 10 }
    api.dialog.value = true
    const pending = refresh()
    requests[0].resolve({ data: [{ id: 6, amount: 30 }], meta: { total: 1 } })
    await Promise.resolve()
    await Promise.resolve()
    requests[1].reject({ response: { status: 404 } })
    await pending
    assert.equal(api.items.value[0].id, 6)
    assert.equal(api.selectedPurchase.value, null)
    assert.equal(api.detailsDialog.value, false)
    assert.equal(api.dialog.value, true)
    assert.equal(api.form.items[0].quantity, 19)
    assert.match(api.errorMessage.value, /удалена/)
})

test('an initial goods request from the previous actor cannot repopulate the resource', async () => {
    const { api, requests, changeActor } = pageHarness('resources/js/Components/Warehouses/GoodStockPanel.vue', {
        props: { warehouses: [], measures: [] },
    })
    const pending = api.loadAll()
    const previousSignal = requests[0].options.signal
    changeActor()
    assert.equal(previousSignal.aborted, true)
    requests[0].resolve([{ good_id: 1, quantity: 100 }])
    requests[1].resolve([{ id: 10 }])
    requests[2].resolve([{ id: 1, name: 'Previous account' }])
    requests[3].resolve([{ id: 20 }])
    await pending
    assert.deepEqual(api.stockRows.value, [])
    assert.deepEqual(api.goods.value, [])

    const fresh = api.loadAll()
    assert.notEqual(requests[4].options.signal, previousSignal)
    assert.equal(requests[4].options.signal.aborted, false)
    requests[4].resolve([{ good_id: 2, quantity: 3 }])
    requests[5].resolve([])
    requests[6].resolve([{ id: 2, name: 'Current account' }])
    requests[7].resolve([])
    await fresh
    assert.equal(api.stockRows.value[0].good_id, 2)
})
