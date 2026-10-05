import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { buildingApartmentLabel } from '../../resources/js/utils/buildingApartments.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function pageHarness(permissions = {}) {
    const filename = fileURLToPath(new URL('../../resources/js/Pages/Ameise/Orders/Index.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'orders-index' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'orders-index', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], disposal = []
    const request = (method, url, options) => {
        let resolve, reject
        const promise = new Promise((success, failure) => { resolve = success; reject = failure })
        requests.push({ method, url, options, resolve: data => resolve({ data }), reject })
        return promise
    }
    const props = { permissions }
    const environment = {
        ...Vue, Link: {}, VerwalterLayout: {}, OrderStatusesDialog: {}, OrderDetailsDialog: {},
        buildingApartmentLabel,
        route: () => '', useHead: () => {}, useDebounceFn: callback => callback,
        onMounted: () => {}, onBeforeUnmount: callback => disposal.push(callback),
        axios: {
            get: (url, options) => request('GET', url, options),
            delete: (url, options) => request('DELETE', url, options),
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose: () => {} }))
    return { api, requests, render: templateRenderer(template, api, props), dispose() { disposal.forEach(callback => callback()); scope.stop() } }
}

function vnodeText(node) {
    if (typeof node === 'string') return node
    if (!node || typeof node !== 'object') return ''
    if (typeof node.children === 'string') return node.children
    const children = Array.isArray(node) ? node
        : Array.isArray(node.children) ? node.children
            : typeof node.children?.default === 'function' ? node.children.default() : []
    return children.map(vnodeText).join(' ')
}

test('order number opens details over the filtered list and closing preserves the current page', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests, render } = harness
    api.filters.status_id = 3
    api.filters.page = 2
    const loaded = api.fetchOrders()
    requests[0].resolve({ data: [{ id: 22, number: 'ORDER-22' }], meta: { current_page: 2, last_page: 3, total: 60 } })
    await loaded
    const number = findVNode(render(), node => hasClass(node, 'orders-ledger__number'))
    assert.equal(number.type, 'button')
    assert.equal(number.props['aria-haspopup'], 'dialog')
    assert.equal(number.props.href, undefined)
    let stopped = false
    number.props.onClick({ stopPropagation: () => { stopped = true } })
    assert.equal(stopped, true)
    const dialog = findVNode(render(), node => node.type === api.OrderDetailsDialog)
    assert.equal(dialog.props.modelValue, true)
    assert.equal(dialog.props['order-id'], 22)
    dialog.props['onUpdate:modelValue'](false)
    assert.equal(api.orderDetailsOpen.value, false)
    assert.equal(api.filters.status_id, 3)
    assert.equal(api.filters.page, 2)
    assert.equal(api.meta.current_page, 2)
    assert.deepEqual(api.orders.value.map(order => order.id), [22])
    assert.equal(requests.length, 1)
})

test('order rows support Enter and Space while keyboard events on nested controls remain independent', t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, render } = harness
    api.orders.value = [{ id: 11 }, { id: 22 }]
    const first = findVNode(render(), node => node.type === 'tr' && node.key === 11)
    const second = findVNode(render(), node => node.type === 'tr' && node.key === 22)
    const dispatch = (row, key, nested = false) => {
        let prevented = false
        const event = { key, target: nested ? {} : row, currentTarget: row, preventDefault: () => { prevented = true } }
        for (const handler of Array.isArray(row.props.onKeydown) ? row.props.onKeydown : [row.props.onKeydown]) handler(event)
        return prevented
    }
    assert.equal(dispatch(first, 'Enter', true), false)
    assert.equal(api.orderDetailsOpen.value, false)
    assert.equal(dispatch(first, 'Enter'), true)
    assert.equal(api.selectedOrderId.value, 11)
    assert.equal(dispatch(second, ' '), true)
    assert.equal(api.selectedOrderId.value, 22)
    assert.equal(api.orderDetailsOpen.value, true)
})

test('saving order details refreshes the filtered current page without resetting the filter draft', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests, render } = harness
    api.filters.page = 3
    api.filters.status_id = 2
    api.filters.delivery_unscheduled = true
    api.draftFilters.status_id = 5
    api.openOrder({ id: 22 })
    const dialog = findVNode(render(), node => node.type === api.OrderDetailsDialog)
    const pending = dialog.props.onSaved({ id: 22, delivery_date: '2026-09-30' })
    assert.equal(requests[0].options.params.page, 3)
    assert.equal(requests[0].options.params.status_id, 2)
    assert.equal(requests[0].options.params.delivery_unscheduled, 1)
    requests[0].resolve({ data: [{ id: 23 }], meta: { current_page: 3, last_page: 3, per_page: 100, total: 201 } })
    await pending
    assert.equal(api.orders.value[0].id, 23)
    assert.equal(api.selectedOrderId.value, 22)
    assert.equal(api.orderDetailsOpen.value, true)
    assert.equal(api.draftFilters.status_id, 5)
})

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

test('orders use server pagination with 100 rows and navigation requests the next page', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests, render } = harness
    assert.equal(api.filters.per_page, 100)
    assert.equal(api.meta.per_page, 100)
    const first = api.fetchOrders()
    assert.equal(requests[0].options.params.per_page, 100)
    requests[0].resolve({
        data: Array.from({ length: 100 }, (_, index) => ({ id: index + 1 })),
        meta: { current_page: 1, last_page: 3, per_page: 100, total: 250 },
    })
    await first
    assert.equal(api.pageRange.value, '1–100 из 250')
    const footer = findVNode(render(), node => hasClass(node, 'orders-pagination'))
    assert.match(vnodeText(footer), /100/)
    assert.equal(findVNode(footer, node => node.type === 'select'), null)
    const next = findVNode(footer, node => node.props?.['aria-label'] === 'Следующая страница')
    assert.equal(next.props.disabled, false)
    next.props.onClick()
    assert.equal(requests[1].options.params.page, 2)
    assert.equal(requests[1].options.params.per_page, 100)
    requests[1].resolve({
        data: Array.from({ length: 100 }, (_, index) => ({ id: index + 101 })),
        meta: { current_page: 2, last_page: 3, per_page: 100, total: 250 },
    })
    await Vue.nextTick()
    assert.equal(api.pageRange.value, '101–200 из 250')
    assert.equal(api.orders.value[0].id, 101)
})

test('order composition renders every position and product links keep row actions independent', t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, render } = harness
    api.orders.value = [{ id: 11, items: [
        { id: 1, good: { id: 101 }, good_name: 'Какао', quantity: 2 },
        { id: 2, good: { id: 102 }, good_name: 'Мука', quantity: 3 },
        { id: 3, good: null, good_name: 'Сахар', quantity: 4 },
        { id: 4, good: { id: 104 }, good_name: 'Масло', quantity: 5 },
    ] }]
    const goods = findVNode(render(), node => hasClass(node, 'orders-ledger__goods'))
    for (const name of ['Какао', 'Мука', 'Сахар', 'Масло']) assert.match(vnodeText(goods), new RegExp(name))
    assert.doesNotMatch(vnodeText(goods), /ещё.*поз\./)
    const link = findVNode(goods, node => node.type === api.Link)
    let stopped = false
    link.props.onClick({ stopPropagation: () => { stopped = true } })
    assert.equal(stopped, true)
    assert.equal(api.orderDetailsOpen.value, false)
})

test('delete controls require permission and cannot delete shipped or explicitly restricted orders', t => {
    const denied = pageHarness({ delete: false })
    const allowed = pageHarness({ delete: true })
    t.after(() => { denied.dispose(); allowed.dispose() })
    const permittedOrder = { id: 11, number: 'ORDER-11', permissions: { delete: true } }
    denied.api.orders.value = [permittedOrder]
    assert.equal(findVNode(denied.render(), node => hasClass(node, 'orders-ledger__delete')), null)
    denied.api.requestDeleteOrder(permittedOrder)
    assert.equal(denied.api.orderToDelete.value, null)
    allowed.api.orders.value = [
        { id: 22, permissions: { delete: false } },
        { id: 33, shipped_sale_id: 12, permissions: { delete: true } },
    ]
    assert.equal(findVNode(allowed.render(), node => hasClass(node, 'orders-ledger__delete')), null)
    for (const order of allowed.api.orders.value) {
        allowed.api.requestDeleteOrder(order)
        assert.equal(allowed.api.orderToDelete.value, null)
    }
    allowed.api.orders.value = [permittedOrder]
    const button = findVNode(allowed.render(), node => hasClass(node, 'orders-ledger__delete'))
    assert.equal(button.props['aria-label'], 'Удалить заказ ORDER-11')
    let stopped = false
    button.props.onClick({ stopPropagation: () => { stopped = true } })
    assert.equal(stopped, true)
    assert.equal(allowed.api.orderToDelete.value.id, 11)
    assert.equal(allowed.api.orderDetailsOpen.value, false)
    assert.equal(allowed.requests.length, 0)
    const dialog = findVNode(allowed.render(), node => node.type === 'v-dialog')
    assert.equal(dialog.props['model-value'], true)
    dialog.props['onUpdate:modelValue'](false)
    assert.equal(allowed.api.orderToDelete.value, null)
})

test('order details provide the same guarded deletion action and respect busy editor state', t => {
    const harness = pageHarness({ delete: true, edit: true })
    t.after(() => harness.dispose())
    const { api, render, requests } = harness
    const order = { id: 22, number: 'ORDER-22' }
    api.openOrder(order)
    const details = findVNode(render(), node => node.type === api.OrderDetailsDialog)
    const actions = props => findVNode(details.children.actions(props), node => hasClass(node, 'orders-details-delete'))
    assert.equal(actions({ order: { ...order, shipped_sale_id: 12 }, disabled: false }), null)
    assert.equal(actions({ order: { ...order, permissions: { delete: false } }, disabled: false }), null)
    assert.equal(actions({ order, disabled: true }).props.disabled, true)
    const button = actions({ order, disabled: false })
    assert.equal(button.props.disabled, false)
    button.props.onClick()
    assert.equal(api.orderToDelete.value.id, 22)
    assert.equal(api.orderDetailsOpen.value, true)
    assert.equal(requests.length, 0)
})

test('confirmed deletion runs once, closes deleted details, and reloads the filtered current page', async t => {
    const harness = pageHarness({ delete: true })
    t.after(() => harness.dispose())
    const { api, requests, render } = harness
    const order = { id: 22, number: 'ORDER-22' }
    api.filters.page = 3
    api.filters.status_id = 2
    api.filters.delivery_unscheduled = true
    api.draftFilters.status_id = 5
    api.openOrder(order)
    api.requestDeleteOrder(order)
    const confirm = findVNode(render(), node => node.type === 'v-btn' && vnodeText(node).trim() === 'Удалить')
    const pending = confirm.props.onClick()
    assert.equal(api.deleting.value, true)
    assert.equal(requests[0].method, 'DELETE')
    assert.equal(requests[0].url, '/api/orders/22')
    await api.deleteOrder()
    api.cancelDeleteOrder()
    assert.equal(requests.length, 1)
    assert.equal(api.orderToDelete.value.id, 22)
    requests[0].resolve({ message: 'Заказ удалён.' })
    await Vue.nextTick()
    assert.equal(requests[1].method, 'GET')
    assert.equal(requests[1].options.params.page, 3)
    assert.equal(requests[1].options.params.status_id, 2)
    assert.equal(requests[1].options.params.delivery_unscheduled, 1)
    assert.equal(requests[1].options.params.per_page, 100)
    requests[1].resolve({ data: [{ id: 23 }], meta: { current_page: 3, last_page: 3, per_page: 100, total: 201 } })
    await pending
    assert.equal(api.deleting.value, false)
    assert.equal(api.orderToDelete.value, null)
    assert.equal(api.orderDetailsOpen.value, false)
    assert.equal(api.selectedOrderId.value, null)
    assert.equal(api.orders.value[0].id, 23)
    assert.equal(api.draftFilters.status_id, 5)
})

test('failed deletion keeps confirmation open for retry and does not reload orders', async t => {
    const harness = pageHarness({ delete: true })
    t.after(() => harness.dispose())
    const { api, requests } = harness
    api.openOrder({ id: 33 })
    api.requestDeleteOrder({ id: 22 })
    const first = api.deleteOrder()
    requests[0].reject({ response: { data: { message: 'Заказ уже отгружен.' } } })
    await first
    assert.equal(api.orderToDelete.value.id, 22)
    assert.equal(api.deleting.value, false)
    assert.equal(api.deleteError.value, 'Заказ уже отгружен.')
    assert.equal(requests.length, 1)
    assert.equal(api.orderDetailsOpen.value, true)
    assert.equal(api.selectedOrderId.value, 33)
    const retry = api.deleteOrder()
    assert.equal(requests[1].method, 'DELETE')
    assert.equal(api.deleteError.value, '')
    requests[1].resolve({})
    await Vue.nextTick()
    requests[2].resolve({ data: [{ id: 33 }], meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 } })
    await retry
    assert.equal(api.orderDetailsOpen.value, true)
    assert.equal(api.selectedOrderId.value, 33)
    assert.equal(api.orderToDelete.value, null)
    api.requestDeleteOrder({ id: 44 })
    api.deleteError.value = 'Ошибка удаления'
    api.cancelDeleteOrder()
    assert.equal(api.orderToDelete.value, null)
    assert.equal(api.deleteError.value, '')
})

test('an empty page beyond the last page refetches the last page and resets table scrolling', async t => {
    const harness = pageHarness()
    t.after(() => harness.dispose())
    const { api, requests } = harness
    api.filters.page = 3
    api.filters.status_id = 2
    api.ledgerScroll.value = { scrollTop: 450 }
    const pending = api.fetchOrders()
    requests[0].resolve({ data: [], meta: { current_page: 3, last_page: 2, per_page: 100, total: 200 } })
    await Vue.nextTick()
    assert.equal(requests.length, 2)
    assert.equal(requests[1].options.params.page, 2)
    assert.equal(requests[1].options.params.status_id, 2)
    assert.equal(api.loading.value, true)
    requests[1].resolve({ data: [{ id: 199 }, { id: 200 }], meta: { current_page: 2, last_page: 2, per_page: 100, total: 200 } })
    await pending
    assert.equal(api.filters.page, 2)
    assert.equal(api.meta.current_page, 2)
    assert.deepEqual(api.orders.value.map(order => order.id), [199, 200])
    assert.equal(api.ledgerScroll.value.scrollTop, 0)
    assert.equal(api.loading.value, false)
})
