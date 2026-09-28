import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function panelHarness(t) {
    const filename = 'resources/js/Components/Unit/UnitOrdersPanel.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'unit-orders-panel' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'unit-orders-panel', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const environment = { ...Vue, Link: {}, OrderDetailsDialog: {}, route: (name, id) => `/${name}/${id ?? ''}` }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({
        canViewOrders: true, canCreateOrders: true,
        unit: { id: 1, entities: [{ id: 7, name: 'Client', orders: [
            { id: 12, items: [{ id: 1, good: { id: 3 } }, { id: 2 }, { id: 3 }, { id: 4 }] },
        ] }] },
    })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { api, props, render: templateRenderer(template, api, props) }
}

test('Unit order number and remaining items open the same details while goods and creation retain their routes', t => {
    const { api, render } = panelHarness(t)
    for (const className of ['unit-orders__number', 'unit-orders__more']) {
        const button = findVNode(render(), node => hasClass(node, className))
        assert.equal(button.type, 'button')
        assert.equal(button.props['aria-haspopup'], 'dialog')
        assert.equal(button.props.href, undefined)
        button.props.onClick()
        const dialog = findVNode(render(), node => node.type === api.OrderDetailsDialog)
        assert.equal(dialog.props.modelValue, true)
        assert.equal(dialog.props['order-id'], 12)
        dialog.props['onUpdate:modelValue'](false)
    }
    assert.ok(findVNode(render(), node => node.props?.href === '/Ameise.good.show/3'))
    assert.ok(findVNode(render(), node => node.props?.href === '/Ameise.orders.create/'))
    assert.deepEqual(api.orders.value.map(order => order.id), [12])
})

test('changing Unit or losing permission closes a selected order and prevents reopening without access', async t => {
    const { api, props, render } = panelHarness(t)
    api.openOrder({ id: 12 })
    props.unit = { id: 2, entities: [] }
    await Vue.nextTick()
    assert.equal(api.orderDetailsOpen.value, false)
    assert.equal(api.selectedOrderId.value, null)
    api.openOrder({ id: 12 })
    props.canViewOrders = false
    await Vue.nextTick()
    assert.equal(api.orderDetailsOpen.value, false)
    assert.equal(api.selectedOrderId.value, null)
    api.openOrder({ id: 12 })
    assert.equal(api.orderDetailsOpen.value, false)
    assert.equal(findVNode(render(), node => node.type === api.OrderDetailsDialog), null)
})

test('saved Unit orders update totals and disappear if moved outside linked entities', t => {
    const { api, props, render } = panelHarness(t)
    api.openOrder({ id: 12 })
    const dialog = findVNode(render(), node => node.type === api.OrderDetailsDialog)
    dialog.props.onSaved({ id: 12, entity_id: 7, total_amount: 150, status: { name: 'Отложен' }, items: [] })
    assert.equal(api.orders.value[0].total_amount, 150)
    assert.equal(api.orders.value[0].status.name, 'Отложен')
    assert.equal(api.orderDetailsOpen.value, true)
    assert.equal(props.unit.entities[0].orders[0].total_amount, undefined)
    dialog.props.onSaved({ id: 12, entity_id: 8, total_amount: 200 })
    assert.deepEqual(api.orders.value, [])
    props.unit = { id: 2, entities: [{ id: 8, orders: [{ id: 12, total_amount: 300 }] }] }
    dialog.props.onSaved({ id: 12, entity_id: 8, total_amount: 999 })
    assert.notEqual(api.savedOrders.value[12].total_amount, 999)
})
