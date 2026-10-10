import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { measurementForGood, quantityWeight, unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const kilogram = { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 }
const box = { measure_id: 2, unit_label: 'кор.', kilograms_per_unit: 10 }

function harness(t, filename, initial) {
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'good-measurement' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'good-measurement', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = []
    const env = {
        ...Vue, measurementForGood, quantityWeight, unitLabel,
        Head: {}, Link: {}, LayoutProduct: {}, GoodInquiryDialog: {}, GoodProductReel: {}, MobileGoodPurchase: {}, GoodStockAlertButton: {}, DeliveryApartmentFields: {},
        usePage: () => ({ props: {} }), onMounted: () => {},
        usePublicGoodUrl: () => ({ goodPublicUrl: good => `/g/${good.id}` }),
        useYandexMetrica: () => ({ reachGoal: () => {}, ecommerceViewItem: () => {} }),
        goodAvailabilityStatus: () => 'in_stock', canSubscribeToGoodStock: () => false,
        axios: { post: async (url, data) => { requests.push({ url, data }); return { data: { inquiry: { number: 'TEST-1' } } } } },
    }
    const source = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replaceAll('import.meta.env', '({})').replace('export default', 'return')
    const component = new Function('env', `with(env){${source}}`)(env)
    const scope = Vue.effectScope()
    const props = Vue.reactive(initial)
    const api = scope.run(() => component.setup(props, { expose() {}, emit() {} }))
    t.after(() => scope.stop())
    return { props, api, requests, render: templateRenderer(template, api, props) }
}

function offer(measurement) {
    return { price: 100, package_price: 1000, package_weight: 10, currency_code: 'RUB', measurement }
}

for (const [name, measurement, expectedWeight] of [['kilograms', kilogram, 10.125], ['boxes', box, 101.25]]) {
    test(`mobile product quantity uses ${name} for price and mass without implicit packaging conversion`, t => {
        const h = harness(t, 'resources/js/Components/Goods/MobileGoodPurchase.vue', { purchase: offer(measurement), quantity: 10.125 })
        assert.equal(h.api.total.value, 1012.5)
        assert.equal(h.api.totalWeight.value, expectedWeight)
        assert.equal(h.api.unit.value, measurement.unit_label)
        const input = findVNode(h.render(), node => node.props?.id === 'mobile-product-quantity')
        assert.equal(input.props.step, '0.001')
        assert.equal(input.props.disabled, false)
    })

    test(`desktop product quantity uses ${name} consistently`, t => {
        const h = harness(t, 'resources/js/Pages/Goods/Show.vue', { good: { id: 1, denominator: 10, measurement }, publicPurchase: offer(measurement), seo: {}, availability: {}, relatedGoods: [] })
        h.api.quantity.value = 10.125
        assert.equal(h.api.safeQuantity.value, 10.125)
        assert.equal(h.api.total.value, 1012.5)
        assert.equal(h.api.totalWeight.value, expectedWeight)
        assert.equal(h.api.purchase.value.unit, measurement.unit_label)
    })
}

test('inquiry price and counteroffer use product units and send the displayed snapshot', async t => {
    const h = harness(t, 'resources/js/Components/Goods/GoodInquiryDialog.vue', { modelValue: false, kind: 'bargain', quantity: 1, good: { id: 1, denominator: 10, measurement: kilogram }, purchase: offer(kilogram) })
    h.api.form.quantity = 10.125
    assert.equal(h.api.total.value, 1012.5)
    assert.equal(h.api.totalWeight.value, 10.125)
    h.api.activeKind.value = 'bargain'
    h.api.form.proposed_price = 80
    assert.equal(h.api.total.value, 810)
    await h.api.submit()
    assert.equal(h.requests.length, 1)
    assert.equal(h.requests[0].data.quantity, 10.125)
    assert.equal(h.requests[0].data.measure_id, kilogram.measure_id)
    assert.deepEqual(h.requests[0].data.measurement, kilogram)
})

test('unconfigured product cannot be ordered or assigned an implicit package unit', async t => {
    const purchase = offer({ measure_id: null, unit_label: null, kilograms_per_unit: null })
    const mobile = harness(t, 'resources/js/Components/Goods/MobileGoodPurchase.vue', { purchase, quantity: 1 })
    assert.equal(mobile.api.canOrder.value, false)
    assert.equal(mobile.api.total.value, null)
    assert.equal(mobile.api.unit.value, 'единица не задана')
    const inquiry = harness(t, 'resources/js/Components/Goods/GoodInquiryDialog.vue', { modelValue: false, kind: 'order', quantity: 1, good: { id: 1, denominator: 10 }, purchase })
    await inquiry.api.submit()
    assert.equal(inquiry.requests.length, 0)
    assert.match(inquiry.api.errorMessage.value, /Единица измерения товара не задана/)
})

test('a fractional gram keeps its positive kg mass visible', t => {
    const gram = { measure_id: 3, unit_label: 'г', kilograms_per_unit: 0.001 }
    const mobile = harness(t, 'resources/js/Components/Goods/MobileGoodPurchase.vue', { purchase: offer(gram), quantity: 0.001 })
    assert.equal(mobile.api.totalWeight.value, 0.000001)
    assert.equal(mobile.api.number(mobile.api.totalWeight.value), '0,000001')
    const desktop = harness(t, 'resources/js/Pages/Goods/Show.vue', { good: { id: 1, measurement: gram }, publicPurchase: offer(gram), seo: {}, availability: {}, relatedGoods: [] })
    desktop.api.quantity.value = 0.001
    assert.equal(desktop.api.number(desktop.api.totalWeight.value), '0,000001')
    const inquiry = harness(t, 'resources/js/Components/Goods/GoodInquiryDialog.vue', { modelValue: false, kind: 'order', quantity: 0.001, good: { id: 1, measurement: gram }, purchase: offer(gram) })
    inquiry.api.form.quantity = 0.001
    assert.equal(inquiry.api.number(inquiry.api.totalWeight.value), '0,000001')
})
