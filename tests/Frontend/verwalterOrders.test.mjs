import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function dashboardHarness(t) {
    const filename = 'resources/js/Pages/Ameise/Verwalter.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'verwalter-orders' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'verwalter-orders', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const environment = { ...Vue, Link: {}, VerwalterLayout: {}, AvitoWaitingList: {}, OrderDetailsDialog: {}, useHead() {}, route: (name, id) => `/${name}/${id ?? ''}` }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({
        canViewOrders: true, activeLeads: [],
        orderStatuses: [{ id: 1, code: 'open' }, { id: 2, code: 'deferred' }, { id: 3, code: 'closed', is_closed: true }],
        ordersByStatus: {
            open: [{ id: 12, order_status_id: 1, status: { code: 'open' }, total_amount: 10 }],
            deferred: [{ id: 13, order_status_id: 2, status: { code: 'deferred' }, total_amount: 20 }],
        },
    })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { api, props, render: templateRenderer(template, api, props) }
}

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
