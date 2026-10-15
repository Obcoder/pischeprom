import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, name) {
    const filename = `resources/js/Components/Unit/${name}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'unit-quotation-integration' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'unit-quotation-integration', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], events = []
    const environment = {
        ...Vue, QuotationWorkspace: 'QuotationWorkspace', UnitManufacturesCard: 'UnitManufacturesCard', Link: 'Link',
        route: (name, id) => `/${name}/${id ?? ''}`,
        axios: { post: async (url, data) => { requests.push({ url, data }); return { data: {} } } },
    }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const props = Vue.reactive({
        unit: { id: 7, quotations: [{ id: 81, good_id: 42, currency_id: 1, price: 258 }], consumptions: [] },
        dict: { currencies: [{ id: 1, code: 'RUB' }], measures: [{ id: 2, name: 'кг' }], goods: [{ id: 42, name: 'Манго' }] },
        goodsLoading: false, searchGoods: async () => {},
    })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => events.push(args) }))
    t.after(() => scope.stop())
    return { api, props, requests, events, render: templateRenderer(template, api, props) }
}

for (const name of ['UnitTradeTabsCard', 'UnitQuotationsCard']) {
    test(`${name} shares the complete quotation editor and refreshes Unit after changes`, async t => {
        const h = harness(t, name)
        if (name === 'UnitTradeTabsCard') assert.equal(findVNode(h.render(), node => node.type === 'v-window-item' && node.props.value === 'quotations').props.eager, '', 'Keep the quotation draft mounted when changing Unit tabs')
        let workspace = findVNode(h.render(), node => node.type === 'QuotationWorkspace')
        assert.equal(workspace.props.unit.id, 7)
        assert.equal(workspace.props.quotations[0].currency_id, 1)
        assert.equal(workspace.props.dict, h.props.dict)
        workspace.props.onChanged({ id: 82 })
        assert.deepEqual(h.events, [['refresh']])
        h.props.unit = { id: 8, quotations: [{ id: 83, currency_id: 2 }] }
        await Vue.nextTick()
        workspace = findVNode(h.render(), node => node.type === 'QuotationWorkspace')
        assert.equal(workspace.key, 8, 'Changing Unit remounts the editor to discard previous Unit data')
        assert.equal(workspace.props.quotations[0].id, 83)
    })
}

test('the shared quotation editor leaves the Unit consumption workflow intact', async t => {
    const h = harness(t, 'UnitTradeTabsCard')
    Object.assign(h.api.formConsumption, { product_id: 9, quantity: '2.5', measure_id: 2 })
    await h.api.storeConsumption()
    assert.deepEqual(h.requests, [{ url: '/api.consumption.store/', data: { unit_id: 7, product_id: 9, quantity: '2.5', measure_id: 2 } }])
    assert.deepEqual(h.events, [['refresh']])
    assert.equal(h.api.formConsumption.product_id, null)
    assert.equal(h.api.savingConsumption.value, false)
})
