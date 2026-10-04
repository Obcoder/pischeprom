import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'
import { useOrderQuickEdit } from '../../resources/js/Composables/useOrderQuickEdit.js'
import { buildingApartmentLabel } from '../../resources/js/utils/buildingApartments.js'

function dashboardHarness(t, client = {}, initialProps = {}) {
    const filename = 'resources/js/Pages/Ameise/Verwalter.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'verwalter-orders' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'verwalter-orders', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const unmountHooks = []
    const environment = {
        ...Vue, Link: {}, VerwalterLayout: {}, AvitoWaitingList: {}, OrderDetailsDialog: {},
        useOrderQuickEdit: options => useOrderQuickEdit({ ...options, client }),
        buildingApartmentLabel, onBeforeUnmount: hook => unmountHooks.push(hook), useHead() {}, route: (name, id) => `/${name}/${id ?? ''}`,
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({
        canViewOrders: true, activeLeads: [],
        orderStatuses: [{ id: 1, code: 'open', name: 'Новый' }, { id: 2, code: 'deferred', name: 'В работе' }, { id: 3, code: 'closed', name: 'Закрыт', is_closed: true }],
        ordersByStatus: {
            open: [{
                id: 12, order_status_id: 1, status: { code: 'open', name: 'Новый' }, total_amount: 10,
                delivery_date: '2026-10-05', delivery_version: 'version-1', permissions: { edit: true, delivery_edit: true },
                buildings: [{ id: 6, role: 'delivery', city: { name: 'Москва' }, address: 'Ленина, 1', apartment: { number: '12', type: 'office' } }],
            }],
            deferred: [{ id: 13, order_status_id: 2, status: { code: 'deferred' }, total_amount: 20 }],
        },
        ...initialProps,
    })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    let unmounted = false
    function unmount() {
        if (unmounted) return
        unmounted = true
        for (const hook of unmountHooks) hook()
        scope.stop()
    }
    t.after(unmount)
    return { api, props, render: templateRenderer(template, api, props), unmount }
}

test('orders sort by delivery date with unscheduled orders last and keep submission and ID tie breakers', t => {
    const order = (id, delivery_date, submitted_at, created_at = null) => ({
        id, delivery_date, submitted_at, created_at, order_status_id: 1, status: { code: 'open' },
    })
    const orders = [
        order(21, '2026-10-06', '2026-10-04T12:00:00Z'),
        order(22, '2026-10-04', '2026-10-01T12:00:00Z'),
        order(23, '2026-10-04', '2026-10-02T12:00:00Z'),
        order(24, '2026-10-04', '2026-10-02T12:00:00Z'),
        order(25, null, '2026-10-03T12:00:00Z'),
        order(26, null, '2026-10-01T12:00:00Z'),
        order(27, '', '2026-10-02T12:00:00Z'),
        order(28, '2026-10-04', null, '2026-10-03T12:00:00Z'),
    ]
    const { api, props } = dashboardHarness(t, {}, { ordersByStatus: { open: orders } })
    assert.deepEqual(api.visibleOrders.value.map(order => order.id), [28, 24, 23, 22, 21, 25, 27, 26])
    assert.deepEqual(props.ordersByStatus.open.map(order => order.id), [21, 22, 23, 24, 25, 26, 27, 28])
})

test('inline date updates immediately reorder rows and clearing a date moves the row last', async t => {
    const order = (id, delivery_date) => ({
        id, delivery_date, delivery_version: 'v1', submitted_at: '2026-10-01T12:00:00Z',
        order_status_id: 1, status: { code: 'open' }, permissions: { delivery_edit: true },
    })
    const { api, props, render } = dashboardHarness(t, {
        async patch(url, data) {
            const id = Number(url.match(/orders\/(\d+)/)[1])
            return { data: { data: { ...props.ordersByStatus.open.find(order => order.id === id), delivery_date: data.delivery_date, delivery_version: 'v2' } } }
        },
    }, { ordersByStatus: { open: [order(22, '2026-10-08'), order(21, '2026-10-05'), order(23, null)] } })
    const dateInput = id => findVNode(render(), node => node.type === 'input' && node.props['aria-label'] === `Дата доставки заказа #${id}`)
    assert.deepEqual(api.visibleOrders.value.map(order => order.id), [21, 22, 23])
    await dateInput(22).props.onChange({ target: { value: '2026-10-04', validity: { valid: true } } })
    assert.deepEqual(api.visibleOrders.value.map(order => order.id), [22, 21, 23])
    await dateInput(21).props.onChange({ target: { value: '', validity: { valid: true } } })
    assert.deepEqual(api.visibleOrders.value.map(order => order.id), [22, 23, 21])
    assert.equal(props.ordersByStatus.open[1].delivery_date, '2026-10-05')
})

test('dashboard saves move orders between status tabs and remove closed orders without changing the selected tab', t => {
    const { api, props, render } = dashboardHarness(t)
    api.openOrder({ id: 12 })
    const dialog = findVNode(render(), node => node.type === api.OrderDetailsDialog)
    dialog.props.onSaved({ id: 12, order_status_id: 2, status: { code: 'deferred' }, total_amount: 150 })
    assert.equal(api.orderTab.value, 'open')
    assert.equal(api.orderDetailsOpen.value, true)
    assert.deepEqual(api.visibleOrders.value, [])
    assert.deepEqual(api.displayedOrdersByStatus.value.deferred.map(order => order.id), [13, 12])
    api.orderTab.value = 'deferred'
    assert.equal(api.visibleOrders.value.find(order => order.id === 12).total_amount, 150)
    assert.equal(props.ordersByStatus.open[0].total_amount, 10)
    dialog.props.onSaved({ id: 12, order_status_id: 3, status: { code: 'closed', is_closed: true } })
    assert.deepEqual(api.visibleOrders.value.map(order => order.id), [13])
})

test('dashboard changes delivery date from the table and renders its complete delivery address', async t => {
    const writes = []
    const { api, props, render } = dashboardHarness(t, {
        async patch(url, data) {
            writes.push({ url, data })
            return { data: { data: { ...props.ordersByStatus.open[0], delivery_date: data.delivery_date, delivery_version: 'version-2' } } }
        },
    })
    const address = findVNode(render(), node => hasClass(node, 'order-ledger__address'))
    assert.equal(address.children.trim(), 'Москва, Ленина, 1, офис 12')
    const date = findVNode(render(), node => node.type === 'input' && node.props.type === 'date')
    const target = { value: '2026-10-07', validity: { valid: true } }
    await date.props.onChange({ target })
    assert.deepEqual(writes, [{ url: '/api/orders/12/delivery-date', data: { delivery_date: '2026-10-07', version: 'version-1' } }])
    assert.equal(api.visibleOrders.value[0].delivery_date, '2026-10-07')
    assert.equal(props.ordersByStatus.open[0].delivery_date, '2026-10-05')
    assert.equal(api.orderDetailsOpen.value, false)
})

test('status selector includes closed statuses and moves an order without opening its dialog', async t => {
    const { api, props, render } = dashboardHarness(t, {
        async patch(url, data) {
            assert.equal(url, '/api/orders/12/status')
            assert.deepEqual(data, { order_status_id: 3, expected_order_status_id: 1 })
            return { data: { data: { ...props.ordersByStatus.open[0], order_status_id: 3, status: props.orderStatuses[2] } } }
        },
    })
    const tree = render()
    const heading = findVNode(tree, node => hasClass(node, 'order-ledger__heading'))
    assert.ok(heading)
    assert.ok(findVNode(heading, node => hasClass(node, 'order-ledger__entity')))
    const select = findVNode(heading, node => node.type === 'select')
    assert.ok(select)
    assert.equal(select, findVNode(tree, node => node.type === 'select'))
    assert.ok(findVNode(heading, node => node.props?.icon === 'mdi-flag-outline'))
    const selectors = []
    findVNode(tree, node => {
        if (node.type === 'select') selectors.push(node)
        return false
    })
    assert.deepEqual(selectors, [select])
    const tabs = findVNode(tree, node => node.props?.role === 'tablist')
    assert.equal(findVNode(tabs, node => node.type === 'select'), null)
    assert.ok(findVNode(tabs, node => node.props?.role === 'tab' && node.props['aria-selected'] === true))
    assert.match(select.props['aria-label'], /Статус заказа/)
    assert.ok(findVNode(select, node => node.type === 'option' && node.props.value === 3))
    await select.props.onChange({ target: { value: '3' } })
    assert.equal(api.orderTab.value, 'open')
    assert.equal(api.orderDetailsOpen.value, false)
    assert.deepEqual(api.visibleOrders.value, [])
    assert.ok(findVNode(render(), node => node.props?.role === 'status'))
})

test('failed inline edits restore the saved input and offer refresh after a conflict', async t => {
    const { api, props, render } = dashboardHarness(t, {
        async patch() { throw { response: { status: 409, data: {} } } },
        async get() { return { data: { data: { ...props.ordersByStatus.open[0], delivery_date: '2026-10-08', delivery_version: 'version-2' } } } },
    })
    const target = { value: '2026-10-07', validity: { valid: true } }
    await findVNode(render(), node => node.type === 'input').props.onChange({ target })
    assert.equal(target.value, '2026-10-05')
    assert.equal(findVNode(render(), node => node.type === 'select').props.disabled, true)
    const error = findVNode(render(), node => node.props?.role === 'alert')
    await findVNode(error, node => node.type === 'button').props.onClick()
    assert.equal(api.visibleOrders.value[0].delivery_date, '2026-10-08')
    assert.equal(Boolean(findVNode(render(), node => node.type === 'select').props.disabled), false)
})

test('read-only orders display the date and an accessible status icon in the row heading', t => {
    const { props, render } = dashboardHarness(t)
    props.ordersByStatus.open[0].permissions = { edit: false, delivery_edit: false }
    assert.equal(findVNode(render(), node => node.type === 'select'), null)
    assert.equal(findVNode(render(), node => node.type === 'input'), null)
    assert.equal(findVNode(render(), node => hasClass(node, 'order-ledger__date-label')).children, '05.10.2026')
    const heading = findVNode(render(), node => hasClass(node, 'order-ledger__heading'))
    const status = findVNode(heading, node => node.props?.title?.includes('Новый') && node.props?.['aria-label']?.includes('Новый'))
    assert.ok(status)
    assert.notEqual(status.children, 'Новый')
})

test('save confirmation stays inside the header and disappears four seconds after the latest save', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] })
    const { api, render } = dashboardHarness(t)
    api.quickEdit.success = 'Заказ PP-12: дата доставки сохранена.'
    await Vue.nextTick()
    let tree = render()
    const header = findVNode(tree, node => hasClass(node, 'order-summary__header'))
    const notice = findVNode(header, node => node.props?.role === 'status')
    assert.ok(notice)
    assert.equal(notice, findVNode(tree, node => hasClass(node, 'order-summary__notice')))

    t.mock.timers.tick(3000)
    assert.notEqual(api.quickEdit.success, '')
    api.quickEdit.success = 'Заказ PP-13: статус изменён.'
    await Vue.nextTick()
    t.mock.timers.tick(1000)
    assert.match(api.quickEdit.success, /PP-13/)
    t.mock.timers.tick(2999)
    assert.match(api.quickEdit.success, /PP-13/)
    t.mock.timers.tick(1)
    await Vue.nextTick()
    assert.equal(api.quickEdit.success, '')
    tree = render()
    assert.equal(findVNode(tree, node => hasClass(node, 'order-summary__notice')), null)
})

test('unmount clears the pending confirmation timer', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] })
    const clear = t.mock.method(globalThis, 'clearTimeout')
    const { api, unmount } = dashboardHarness(t)
    api.quickEdit.success = 'Заказ PP-12: статус изменён.'
    await Vue.nextTick()
    const clearsBeforeUnmount = clear.mock.callCount()
    unmount()
    assert.equal(clear.mock.callCount(), clearsBeforeUnmount + 1)
    t.mock.timers.tick(4000)
    assert.equal(api.quickEdit.success, '')
})

test('inline controls stop row clicks and prevent opening stale details while saving', async t => {
    let finish
    const { api, props, render } = dashboardHarness(t, {
        patch() { return new Promise(resolve => { finish = resolve }) },
    })
    const select = findVNode(render(), node => node.type === 'select')
    let stopped = false
    select.props.onClick({ stopPropagation() { stopped = true } })
    assert.equal(stopped, true)
    const pending = select.props.onChange({ target: { value: '2' } })
    const number = findVNode(render(), node => hasClass(node, 'order-ledger__number'))
    number.props.onClick({ stopPropagation() {} })
    assert.equal(api.orderDetailsOpen.value, false)
    assert.equal(findVNode(render(), node => node.type === 'input').props.disabled, true)
    finish({ data: { data: { ...props.ordersByStatus.open[0], order_status_id: 2, status: props.orderStatuses[1] } } })
    await pending
    assert.equal(api.orderDetailsOpen.value, false)
    assert.deepEqual(api.visibleOrders.value, [])
})
