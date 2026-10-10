import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { measurementForGood, quantityWeight, unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const kilogram = { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 }
const box = { measure_id: 2, unit_label: 'кор.', kilograms_per_unit: 10 }

function harness(t, filename, initial) {
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'good-measurement' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'good-measurement', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = []
    const cartCalls = []
    const emitted = []
    const cart = { succeeds: true, cartError: Vue.ref(''), addGood: (...args) => { cartCalls.push(args); return cart.succeeds } }
    const env = {
        ...Vue, measurementForGood, quantityWeight, unitLabel,
        Head: {}, Link: {}, LayoutProduct: {}, GoodInquiryDialog: {}, PublicGoodGallery: {}, MobileGoodPurchase: {}, GoodStockAlertButton: {}, DeliveryApartmentFields: {},
        useOrderCart: () => cart,
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
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { props, api, requests, cartCalls, cart, emitted, render: templateRenderer(template, api, props) }
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


test('product cart button adds the chosen fractional quantity and displayed offer without opening an inquiry', t => {
    const purchase = offer(kilogram)
    const h = harness(t, 'resources/js/Pages/Goods/Show.vue', { good: { id: 7, name: 'Мука', measurement: kilogram }, publicPurchase: purchase, seo: {}, availability: {}, relatedGoods: [] })
    h.api.quantity.value = '2.375'
    const button = findVNode(h.render(), node => hasClass(node, 'order-button'))
    button.props.onClick()
    assert.equal(h.cartCalls.length, 1)
    assert.equal(h.cartCalls[0][0].id, 7)
    assert.equal(h.cartCalls[0][1], 2.375)
    assert.equal(h.cartCalls[0][2].price, 100)
    assert.deepEqual(h.cartCalls[0][2].measurement, kilogram)
    assert.equal(h.api.dialogOpen.value, false)
    assert.equal(h.api.cartNoticeOpen.value, true)
    assert.match(h.api.cartNotice.value, /2,375 кг/)
    const immediate = findVNode(h.render(), node => hasClass(node, 'buy-now-button'))
    immediate.props.onClick()
    assert.equal(h.api.dialogOpen.value, true)
    assert.equal(h.api.dialogKind.value, 'order')
    assert.equal(h.cartCalls.length, 1)
})

test('cart failure displays the error instead of a success notice and missing units disable adding', t => {
    const h = harness(t, 'resources/js/Pages/Goods/Show.vue', { good: { id: 7 }, publicPurchase: offer(kilogram), seo: {}, availability: {}, relatedGoods: [] })
    h.cart.succeeds = false
    h.cart.cartError.value = 'Единица измерения товара изменилась.'
    h.api.addToCart()
    assert.equal(h.api.cartAdded.value, false)
    assert.equal(h.api.cartNotice.value, h.cart.cartError.value)
    assert.equal(h.api.cartNoticeOpen.value, true)
    h.cartCalls.length = 0
    h.api.cartNoticeOpen.value = false
    h.props.publicPurchase.measurement = { measure_id: null, unit_label: null, kilograms_per_unit: null }
    const button = findVNode(h.render(), node => hasClass(node, 'order-button'))
    assert.equal(button.props.disabled, true)
    h.api.addToCart()
    assert.equal(h.cartCalls.length, 0)
    assert.equal(h.api.cartNoticeOpen.value, false)
})

test('mobile purchase and fixed action use the cart while the immediate action still opens the form', t => {
    const mobile = harness(t, 'resources/js/Components/Goods/MobileGoodPurchase.vue', { purchase: offer(kilogram), quantity: 2.375 })
    findVNode(mobile.render(), node => hasClass(node, 'mobile-good-buy__order')).props.onClick()
    findVNode(mobile.render(), node => hasClass(node, 'mobile-good-buy__immediate')).props.onClick()
    assert.deepEqual(mobile.emitted, [['add-to-cart'], ['inquiry', 'order']])
    const page = harness(t, 'resources/js/Pages/Goods/Show.vue', { good: { id: 7 }, publicPurchase: offer(kilogram), seo: {}, availability: {}, relatedGoods: [] })
    const sticky = findVNode(page.render(), node => hasClass(node, 'mobile-purchase'))
    findVNode(sticky, node => node.type === 'button').props.onClick()
    assert.equal(page.cartCalls.length, 1)
    assert.equal(page.api.dialogOpen.value, false)
})

test('the customer product code block contains only TN VED EAEU, OKPD2 and HS', t => {
    const good = { id: 7, tn_ved_code: '0303541000', okpd2_code: '10.20.13.122', hs_code: '030354', gtin_code: 'private-gtin', incoming_code: 'private-incoming' }
    const page = harness(t, 'resources/js/Pages/Goods/Show.vue', { good, publicPurchase: offer(kilogram), seo: {}, availability: {}, relatedGoods: [] })
    assert.deepEqual(page.api.tradeCodes.value, [
        { label: 'ТН ВЭД ЕАЭС', value: '0303541000' },
        { label: 'ОКПД 2', value: '10.20.13.122' },
        { label: 'HS', value: '030354' },
    ])
    const codes = findVNode(page.render(), node => hasClass(node, 'trade-codes-panel'))
    assert.ok(findVNode(codes, node => node.type === 'dd' && node.children === '0303541000'))
    page.props.good.hs_code = null
    assert.ok(findVNode(page.render(), node => node.type === 'dd' && node.children === 'Не указан'))
})
