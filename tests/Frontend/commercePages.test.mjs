import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

const projectRoot = new URL('../../', import.meta.url)

// Run the actual compiled page loaders with Vue reactivity and deferred HTTP
// responses. Rendering widgets and transport setup are outside these tests.
function pageHarness(filename, { props = {}, purchases = false, manageSales = false } = {}) {
    const requests = []
    const disposal = []
    const page = Vue.reactive({ props: { auth: { permissions: { sales: { manage: manageSales } } } } })
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
            post(url, body, options = {}) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ method: 'POST', url, body, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            delete(url, options = {}) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ method: 'DELETE', url, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
        usePage: () => page,
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
        page,
        requests,
        refresh: () => registration.load({ signal: new AbortController().signal }),
        dispose: () => disposal.forEach(callback => callback()),
        changeActor: () => {
            scopeController.abort()
            scopeController = new AbortController()
        },
    }
}

function editableSale() {
    return {
        id: 5,
        date: '2026-09-20T00:00:00.000000Z',
        entity_id: 452,
        entity: { id: 452, name: 'Покупатель' },
        payment_reference: 'Счёт 15',
        total: '250.00',
        goods: [
            { id: 132, name: 'Мука', pivot: { id: 71, measure_id: 1, quantity: '2.000000', price: '50.000000', total: '100.00' } },
            { id: 132, name: 'Мука', pivot: { id: 72, measure_id: 1, quantity: '3.000000', price: '30.000000', total: '90.00' } },
        ],
    }
}

async function loadEditableSale(harness, sale = editableSale()) {
    const pending = harness.api.openSaleEdit({ id: sale.id, total: -1 })
    harness.requests.at(-1).resolve({ data: sale })
    await pending
    harness.requests.at(-1).resolve([])
    await Promise.resolve()
    return sale
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

test('new sale shows stock from the goods warehouse in the selected measure, including zero and negative balances', async () => {
    const { api, requests } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    api.measures.value = [{ id: 1, name: 'кг' }, { id: 2, name: 'шт' }]
    api.openCreate()
    const line = api.saleForm.goods[0]
    Object.assign(line, { good_id: 132, measure_id: 1, quantity: 20, price: 152, total: 3040 })
    api.saleForm.entity_id = 452
    assert.equal(api.lineStockText(line), '…')
    assert.equal(requests[0].url, 'good-warehouse-stock.index')
    requests[0].resolve([
        { good_id: 132, measure_id: 1, quantity: -10, warehouse: { code: 'goods', is_active: true } },
        { good_id: 132, measure_id: 2, quantity: 7.123456, warehouse: { code: 'goods', is_active: true } },
        { good_id: 132, measure_id: 1, quantity: 100, warehouse: { code: 'storage', is_active: true } },
        { good_id: 133, measure_id: 1, quantity: 99, warehouse: { code: 'goods', is_active: false } },
    ])
    await Promise.resolve()
    assert.equal(api.lineStockText(line), '-10 кг')
    assert.equal(api.canSubmitSale.value, true, 'negative stock is informational and does not block saving')
    line.measure_id = '2'
    assert.equal(api.lineStockText(line), '7,123456 шт')
    line.good_id = 133
    line.measure_id = 1
    assert.equal(api.lineStockText(line), '0 кг')
    line.good_id = 134
    assert.equal(api.lineStockText(line), '0 кг', 'goods without stock movements have a zero balance')
    line.measure_id = null
    assert.equal(api.lineStockText(line), '—')
})

test('sales stock refresh preserves the draft and ignores an older stock response', async () => {
    const { api, requests, refresh } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    api.openCreate()
    const line = api.saleForm.goods[0]
    Object.assign(line, { good_id: 132, measure_id: 1, quantity: 30, price: 152, total: 4560 })
    const pending = refresh()
    requests[1].resolve({ data: [], meta: {} })
    requests[2].resolve([{ good_id: 132, measure_id: 1, quantity: -20, warehouse: { code: 'goods', is_active: true } }])
    await pending
    requests[0].resolve([{ good_id: 132, measure_id: 1, quantity: 10, warehouse: { code: 'goods', is_active: true } }])
    await Promise.resolve()
    assert.equal(api.lineStock(line), -20)
    assert.equal(line.quantity, 30)
    assert.equal(line.total, 4560)
    assert.equal(api.dialog.value, true)
})

test('stock load failure clears stale values without blocking a sale and can be retried', async () => {
    const { api, requests } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    api.openCreate()
    const line = api.saleForm.goods[0]
    Object.assign(line, { good_id: 132, measure_id: 1, quantity: 30, price: 152, total: 4560 })
    api.saleForm.entity_id = 452
    requests[0].resolve([{ good_id: 132, measure_id: 1, quantity: 10, warehouse: { code: 'goods', is_active: true } }])
    await Promise.resolve()
    const failed = api.fetchSaleStock()
    requests[1].reject(new Error('Stock service unavailable'))
    await failed
    assert.equal(api.stockError.value, true)
    assert.equal(api.lineStockText(line), '—', 'an unknown balance must not be displayed as zero or stale stock')
    assert.equal(api.canSubmitSale.value, true)
    const retry = api.fetchSaleStock()
    requests[2].resolve([])
    await retry
    assert.equal(api.stockError.value, false)
    assert.equal(api.lineStock(line), 0)
})

test('stock responses from a disposed sale form or previous actor are ignored', async () => {
    for (const action of ['dispose', 'changeActor']) {
        const harness = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
        const pending = harness.api.fetchSaleStock()
        harness[action]()
        harness.requests[0].resolve([{ good_id: 132, measure_id: 1, quantity: 10, warehouse: { code: 'goods', is_active: true } }])
        await pending
        assert.equal(harness.api.stockRows.value, null)
    }
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

test('full sale editing and deletion require the Admin permission', async () => {
    const { api, requests, page } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue')
    assert.equal(api.canManageSales.value, false)
    assert.equal(api.headers.value.some(header => header.key === 'actions'), false)
    await api.openSaleEdit({ id: 5 })
    api.openSaleDelete({ id: 5 })
    await api.deleteSale()
    assert.equal(requests.length, 0)
    assert.equal(api.dialog.value, false)
    assert.equal(api.deleteDialog.value, false)

    page.props.auth.permissions.sales.manage = true
    assert.equal(api.canManageSales.value, true)
    assert.equal(api.headers.value.some(header => header.key === 'actions'), true)
    page.props.auth.permissions = {}
    assert.equal(api.canManageSales.value, false, 'missing permissions fail closed')
})

test('Admin sale edit loads current sale fields and keeps distinct line identifiers for duplicate goods', async () => {
    const { api, requests } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const sale = editableSale()
    const pending = api.openSaleEdit({ id: 5, total: -1 })
    assert.equal(api.editLoading.value, true)
    assert.equal(requests[0].method, 'GET')
    assert.equal(requests[0].url, '/api/sales/5')
    await api.openSaleEdit({ id: 6 })
    assert.equal(requests.length, 1, 'repeated edit clicks do not race sale responses')
    requests[0].resolve({ data: sale })
    await pending
    assert.equal(api.editLoading.value, false)
    assert.equal(api.editingSaleId.value, 5)
    assert.equal(api.dialog.value, true)
    assert.equal(api.saleForm.date, '2026-09-20')
    assert.equal(api.saleForm.entity_id, 452)
    assert.equal(api.saleForm.payment_reference, 'Счёт 15')
    assert.equal(api.saleForm.total, '250.00')
    assert.equal(api.saleForm.manualTotal, true, 'an existing total different from the line sum remains manual')
    assert.deepEqual(api.saleForm.goods.map(line => line.id), [71, 72])
    assert.deepEqual(api.saleForm.goods.map(line => line.good_id), [132, 132])
    assert.equal(api.goods.value.length, 1, 'missing goods are added once to the selector dictionary')
    assert.equal(api.selectedEntityOption.value.name, 'Покупатель')
    api.saleForm.goods[0].quantity = 17
    assert.equal(sale.goods[0].pivot.quantity, '2.000000', 'editing a line does not mutate saved sale data')
    assert.equal(requests[1].url, 'good-warehouse-stock.index')
    requests[1].resolve([])
    await Promise.resolve()
})

test('Admin sale save updates headers, retained lines and new goods in one PATCH', async () => {
    const harness = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const { api, requests } = harness
    await loadEditableSale(harness)
    api.saleForm.date = '2026-10-01'
    api.saleForm.entity_id = 453
    api.saleForm.payment_reference = 'Счёт 16'
    api.saleForm.manualTotal = false
    api.removeLine(0)
    api.saleForm.goods[0].quantity = '4,5'
    api.recalcLine(api.saleForm.goods[0], 'quantity')
    api.addLine()
    Object.assign(api.saleForm.goods[1], { good_id: 133, measure_id: 2, quantity: 2, price: 7.5, total: 15 })
    const pending = api.submitSale()
    assert.equal(api.saving.value, true)
    assert.equal(requests[2].method, 'PATCH')
    assert.equal(requests[2].url, '/api/sales/5')
    assert.deepEqual(requests[2].body, {
        request_id: 'test-request-id',
        date: '2026-10-01',
        entity_id: 453,
        payment_reference: 'Счёт 16',
        total: null,
        goods: [
            { id: 72, good_id: 132, measure_id: 1, quantity: 4.5, price: 30, total: 135 },
            { good_id: 133, measure_id: 2, quantity: 2, price: 7.5, total: 15 },
        ],
    })
    await api.submitSale()
    assert.equal(requests.length, 3, 'repeated submission does not send another PATCH')
    requests[2].resolve({ data: { id: 5, total: 150 } })
    await Promise.resolve()
    assert.equal(api.dialog.value, false)
    assert.equal(requests[3].url, '/api/sales')
    requests[3].resolve({ data: [{ id: 5, total: 150 }], meta: { total: 1, total_amount: 150 } })
    await pending
    assert.equal(api.saving.value, false)
    assert.equal(api.totalAmount.value, 150)
})

test('sale form validates every selected good and allows removing all lines when editing', async () => {
    const harness = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const { api, requests } = harness
    await loadEditableSale(harness, { ...editableSale(), total: '190.00' })
    assert.equal(api.saleForm.manualTotal, false)
    api.addLine()
    api.saleForm.goods[2].good_id = 133
    assert.equal(api.canSubmitSale.value, false, 'one valid line does not hide an incomplete selected line')
    await api.submitSale()
    assert.equal(requests.length, 2)
    Object.assign(api.saleForm.goods[2], { measure_id: 1, quantity: 0, price: 20, total: 0 })
    assert.equal(api.canSubmitSale.value, false, 'selected lines require a positive quantity')
    api.saleForm.goods[2].quantity = 1
    api.saleForm.goods[2].price = -1
    assert.equal(api.canSubmitSale.value, false)
    api.saleForm.goods[2].price = 20
    assert.equal(api.canSubmitSale.value, true)
    api.saleForm.manualTotal = true
    api.saleForm.total = -1
    assert.equal(api.canSubmitSale.value, false, 'manual total cannot be negative')
    api.saleForm.manualTotal = false
    while (api.saleForm.goods.length) api.removeLine(0)
    assert.equal(api.canSubmitSale.value, true)
    assert.equal(api.effectiveSaleTotal.value, 0)
    const pending = api.submitSale()
    assert.deepEqual(requests[2].body.goods, [])
    assert.equal(requests[2].body.total, null)
    requests[2].resolve({ data: { id: 5, total: 0 } })
    await Promise.resolve()
    requests[3].resolve({ data: [{ id: 5, total: 0 }], meta: { total: 1, total_amount: 0 } })
    await pending
})

test('moving the last sale out of a filtered page reloads the last remaining page', async () => {
    const harness = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const { api, requests } = harness
    await loadEditableSale(harness)
    api.filters.month = '2026-09'
    api.options.page = 2
    api.rows.value = [editableSale()]
    api.totalItems.value = 201
    api.saleForm.date = '2026-10-01'
    const pending = api.submitSale()
    requests[2].resolve({ data: { ...editableSale(), date: '2026-10-01' } })
    await Promise.resolve()
    assert.equal(requests[3].options.params.page, 2)
    requests[3].resolve({ data: [], meta: { total: 200, total_amount: 750 } })
    await new Promise(setImmediate)
    assert.equal(api.options.page, 1)
    assert.equal(requests[4].url, '/api/sales')
    assert.equal(requests[4].options.params.page, 1)
    assert.equal(requests[4].options.params.month, '2026-09')
    requests[4].resolve({ data: [{ id: 4, total: 25 }], meta: { total: 200, total_amount: 750 } })
    await pending
    assert.equal(api.rows.value[0].id, 4)
    assert.equal(api.pageCount.value, 1)
    assert.equal(api.dialog.value, false)
})

test('failed sale validation preserves the full Admin draft and reports its field error', async () => {
    const harness = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const { api, requests, page } = harness
    await loadEditableSale(harness)
    api.saleForm.payment_reference = 'Черновик'
    api.saleForm.goods[0].price = 13
    const pending = api.submitSale()
    requests[2].reject({ response: { status: 422, data: {
        message: 'The given data was invalid.',
        errors: { 'goods.0.price': ['Цена товара некорректна.'] },
    } } })
    await pending
    assert.equal(api.saving.value, false)
    assert.equal(api.dialog.value, true)
    assert.equal(api.editingSaleId.value, 5)
    assert.equal(api.saleForm.payment_reference, 'Черновик')
    assert.equal(api.saleForm.goods[0].price, 13)
    assert.equal(api.formErrorMessage.value, 'Цена товара некорректна.')
    assert.equal(requests.length, 3, 'failed saves do not reload or replace the draft')
    page.props.auth.permissions.sales.manage = false
    await api.submitSale()
    assert.equal(requests.length, 3, 'a revoked Admin permission blocks resubmitting an existing edit')
})

test('starting a new sale after editing clears persisted identifiers and uses POST', async () => {
    const harness = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const { api, requests } = harness
    await loadEditableSale(harness)
    api.dialog.value = false
    api.openCreate()
    requests[2].resolve([])
    await Promise.resolve()
    assert.equal(api.editingSaleId.value, null)
    assert.equal(api.saleForm.payment_reference, '')
    api.saleForm.entity_id = 452
    api.saleForm.manualTotal = true
    api.saleForm.total = 50
    api.saleForm.payment_reference = 'Новый счёт'
    const pending = api.submitSale()
    assert.equal(requests[3].method, 'POST')
    assert.equal(requests[3].url, '/api/sales')
    assert.deepEqual(requests[3].body.goods, [])
    assert.equal(requests[3].body.total, 50)
    assert.equal(requests[3].body.payment_reference, 'Новый счёт')
    requests[3].resolve({ data: { id: 6, total: 50 } })
    await Promise.resolve()
    requests[4].resolve({ data: [{ id: 6, total: 50 }], meta: { total: 1, total_amount: 50 } })
    await pending
})

test('sale edit handles a deleted sale and ignores responses after disposal or actor change', async () => {
    const missing = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const pending = missing.api.openSaleEdit({ id: 5 })
    missing.requests[0].reject({ response: { status: 404 } })
    await pending
    assert.equal(missing.api.editLoading.value, false)
    assert.equal(missing.api.dialog.value, false)
    assert.match(missing.api.errorMessage.value, /удалена/)

    for (const action of ['dispose', 'changeActor']) {
        const harness = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
        const pending = harness.api.openSaleEdit({ id: 5 })
        harness[action]()
        harness.requests[0].resolve({ data: editableSale() })
        await pending
        assert.equal(harness.api.dialog.value, false)
        assert.equal(harness.api.editingSaleId.value, null)
        assert.equal(harness.requests.length, 1, 'an obsolete response must not open a draft or fetch its stock')
    }
})

test('sales realtime refresh preserves every field in an open Admin draft', async () => {
    const harness = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const { api, requests, refresh } = harness
    await loadEditableSale(harness)
    api.saleForm.date = '2026-10-01'
    api.saleForm.entity_id = 453
    api.saleForm.payment_reference = 'Черновик'
    api.saleForm.total = 300
    api.saleForm.goods[0].quantity = 7
    api.saleForm.goods[0].price = 9
    api.saleForm.goods[0].total = 63
    const pending = refresh()
    requests[2].resolve({ data: [{ id: 5, total: 500 }], meta: { total: 1, total_amount: 500 } })
    requests[3].resolve([])
    await pending
    assert.equal(api.totalAmount.value, 500)
    assert.equal(api.dialog.value, true)
    assert.equal(api.editingSaleId.value, 5)
    assert.equal(api.saleForm.date, '2026-10-01')
    assert.equal(api.saleForm.entity_id, 453)
    assert.equal(api.saleForm.payment_reference, 'Черновик')
    assert.equal(api.saleForm.total, 300)
    assert.equal(api.saleForm.manualTotal, true)
    assert.deepEqual({ ...api.saleForm.goods[0] }, { id: 71, good_id: 132, measure_id: 1, quantity: 7, price: 9, total: 63 })
})

test('sale deletion waits for confirmation, preserves errors and supports retry', async () => {
    const { api, requests } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const sale = { id: 5, total: 250 }
    api.rows.value = [sale]
    api.totalItems.value = 1
    api.totalAmount.value = 250
    api.openSaleDelete(sale)
    assert.equal(api.deleteDialog.value, true)
    assert.equal(requests.length, 0, 'opening confirmation does not delete the sale')
    api.deleteDialog.value = false
    assert.equal(requests.length, 0, 'cancelling confirmation does not send a mutation')
    assert.equal(api.rows.value.length, 1)
    api.openSaleDelete(sale)
    const failed = api.deleteSale()
    assert.equal(api.deleting.value, true)
    assert.equal(requests[0].method, 'DELETE')
    assert.equal(requests[0].url, '/api/sales/5')
    await api.deleteSale()
    assert.equal(requests.length, 1, 'repeated confirmation does not send another DELETE')
    requests[0].reject({ response: { status: 422, data: { message: 'У продажи есть активные оплаты.' } } })
    await failed
    assert.equal(api.deleting.value, false)
    assert.equal(api.deleteDialog.value, true)
    assert.equal(api.deletingSale.value.id, 5)
    assert.equal(api.deleteErrorMessage.value, 'У продажи есть активные оплаты.')
    assert.equal(api.rows.value[0].id, 5)
    assert.equal(api.totalAmount.value, 250)
    const retried = api.deleteSale()
    assert.equal(api.deleteErrorMessage.value, '')
    requests[1].resolve({ message: 'Продажа удалена' })
    await Promise.resolve()
    assert.equal(api.deleteDialog.value, false)
    assert.equal(api.deletingSale.value, null)
    requests[2].resolve({ data: [], meta: { total: 0, total_amount: 0 } })
    await retried
    assert.equal(api.rows.value.length, 0)
    assert.equal(api.totalAmount.value, 0)
})

test('deleting the last sale on a page goes back and closes its obsolete dialogs', async () => {
    const { api, requests } = pageHarness('resources/js/Components/Grossbuch/GrossbuchSales.vue', { manageSales: true })
    const sale = { id: 5, date: '2026-09-20', total: 250 }
    api.rows.value = [sale]
    api.totalItems.value = 201
    api.totalAmount.value = 1000
    api.options.page = 2
    api.filters.month = '2026-09'
    api.openSaleDetails(sale)
    api.openSaleDateEdit(sale)
    api.editingSaleId.value = 5
    api.dialog.value = true
    api.openSaleDelete(sale)
    const pending = api.deleteSale()
    requests[0].resolve({ message: 'Продажа удалена' })
    await Promise.resolve()
    assert.equal(api.selectedSale.value, null)
    assert.equal(api.detailsDialog.value, false)
    assert.equal(api.dateEditDialog.value, false)
    assert.equal(api.dialog.value, false)
    assert.equal(api.totalItems.value, 200)
    assert.equal(api.totalAmount.value, 750)
    assert.equal(api.options.page, 1)
    assert.equal(requests[1].url, '/api/sales')
    assert.equal(requests[1].options.params.page, 1)
    assert.equal(requests[1].options.params.month, '2026-09')
    requests[1].resolve({ data: [{ id: 4, total: 25 }], meta: { total: 200, total_amount: 750 } })
    await pending
    assert.equal(requests.length, 2, 'deletion does not refetch obsolete sale details')
    assert.equal(api.rows.value[0].id, 4)
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
