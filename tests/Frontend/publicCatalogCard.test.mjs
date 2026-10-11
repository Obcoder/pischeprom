import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as catalog from '../../resources/js/utils/publicCatalog.js'
import { unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { canSubscribeToGoodStock } from '../../resources/js/Pages/Helpers/goodAvailability.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const cardPath = 'resources/js/Components/Goods/PublicCatalogGoodCard.vue'
const actionsPath = 'resources/js/Components/Goods/PublicCatalogGoodActions.vue'
const stockPath = 'resources/js/Components/Goods/GoodStockAlertButton.vue'
const measurement = { measure_id: 7, unit_label: 'кг', kilograms_per_unit: 1 }
const product = (extra = {}) => ({
    id: 42, name: 'Мука пшеничная', public_url: '/g/muka', denominator: 25,
    ava_thumb: '/images/muka.jpg', country: { name: 'Россия' },
    public_purchase: { price: 150, includes_vat: true, measurement, package_weight: 25, package_price: 3750 },
    availability: { status: 'in_stock' }, ...extra,
})

function harness(t, filename, initial, extras = {}, context = {}) {
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'public-catalog-card' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'public-catalog-card', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const emitted = [], cartCalls = []
    const env = {
        ...Vue, ...catalog, unitLabel, canSubscribeToGoodStock, Link: 'Link',
        PublicCatalogGoodActions: 'PublicCatalogGoodActions', GoodStockAlertButton: 'GoodStockAlertButton',
        usePublicGoodUrl: () => ({ goodPublicUrl: good => `/g/${good.id}` }),
        useOrderCart: () => ({ addGood: (...args) => { cartCalls.push(args); return true }, cartError: Vue.ref('') }),
        useAppRoute: () => ({ route: () => '/alerts' }), useYandexMetrica: () => ({ reachGoal() {} }),
        ...extras,
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '')
        .replaceAll('import.meta.env.VITE_YANDEX_METRICA_COUNTER_ID', 'undefined')
        .replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(env)
    const props = Vue.reactive(initial)
    const scope = Vue.effectScope()
    const emit = (...args) => emitted.push(args)
    const api = scope.run(() => component.setup(props, { expose() {}, emit }))
    t.after(() => scope.stop())
    return { api, props, emitted, cartCalls, render: templateRenderer(template, api, props, { $emit: emit, ...context }) }
}

function nodeText(node) {
    if (typeof node === 'string' || typeof node === 'number') return String(node)
    if (!node || typeof node !== 'object') return ''
    const children = Array.isArray(node) ? node : typeof node.children?.default === 'function' ? node.children.default() : node.children
    return Array.isArray(children) ? children.map(nodeText).join(' ') : nodeText(children)
}

test('product dossier quotes public accounting units and a separate physical pack estimate', t => {
    const h = harness(t, cardPath, { good: product() })
    const tree = h.render()
    assert.match(nodeText(findVNode(tree, node => hasClass(node, 'catalog-card__price'))), /150 ₽ \/ кг с НДС/)
    assert.match(nodeText(findVNode(tree, node => hasClass(node, 'catalog-card__package-price'))), /3\s750 ₽.*25 кг/)
    assert.match(nodeText(findVNode(tree, node => hasClass(node, 'catalog-card__reference'))), /Товар № 42/)
    assert.equal(findVNode(tree, node => hasClass(node, 'catalog-card__title')).props.href, '/g/muka')
    const actions = findVNode(tree, node => node.type === 'PublicCatalogGoodActions')
    assert.equal(actions.props.good.id, 42)
    const inquiry = { kind: 'order', quantity: 2, good: h.props.good }
    actions.props.onInquiry(inquiry)
    assert.deepEqual(h.emitted[0], ['inquiry', inquiry])
})

test('pack estimate respects kilograms per accounting unit without changing its unit price', t => {
    const h = harness(t, cardPath, { good: product({ public_purchase: { price: 600, measurement: { measure_id: 2, unit_label: 'шт', kilograms_per_unit: 2 }, package_weight: 10 } }) })
    assert.equal(h.api.packagePrice.value, '3\u00a0000 ₽')
    assert.match(nodeText(findVNode(h.render(), node => hasClass(node, 'catalog-card__price'))), /600 ₽ \/ шт/)
})

test('unknown weight conversion, invalid packaging and one-unit packs never invent pack estimates', async t => {
    const h = harness(t, cardPath, { good: product({ public_purchase: { price: 150, measurement: { ...measurement, kilograms_per_unit: null }, package_weight: 25, package_price: 3750 } }) })
    assert.equal(h.api.packagePrice.value, null)
    assert.equal(findVNode(h.render(), node => hasClass(node, 'catalog-card__package-price')), null)
    h.props.good = product({ denominator: Infinity, public_purchase: { price: 150, measurement } })
    await Vue.nextTick()
    assert.equal(h.api.packageWeight.value, null)
    assert.equal(h.api.packagePrice.value, null)
    h.props.good = product({ denominator: 1, public_purchase: { price: 150, measurement, package_weight: 1 } })
    await Vue.nextTick()
    assert.equal(h.api.packagePrice.value, null)
})

test('missing public offer, photo and unit still give a readable product with no private quote', t => {
    const h = harness(t, cardPath, { good: { id: 3, name: 'Товар с длинным названием', price_type_values: [{ price_gross: 12345 }], availability: { status: 'on_request' } } })
    const tree = h.render()
    assert.match(nodeText(tree), /Цена по запросу/)
    assert.match(nodeText(tree), /Страна уточняется/)
    assert.equal(findVNode(tree, node => node.type === 'img'), null)
    assert.ok(findVNode(tree, node => hasClass(node, 'catalog-card__placeholder')))
    assert.doesNotMatch(nodeText(tree), /12345|единица не задана/)
    assert.ok(findVNode(tree, node => node.type === 'GoodStockAlertButton'))
})

test('failed image falls back and can recover when the product image changes', async t => {
    const h = harness(t, cardPath, { good: product() })
    findVNode(h.render(), node => node.type === 'img').props.onError()
    assert.ok(findVNode(h.render(), node => hasClass(node, 'catalog-card__placeholder')))
    h.props.good = product({ ava_thumb: '/images/updated.jpg' })
    await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'img').props.src, '/images/updated.jpg')
})

test('quantity controls respect both boundaries and fractional quantities reach the real cart action', t => {
    const h = harness(t, actionsPath, { good: product(), compact: false })
    const increase = findVNode(h.render(), node => node.props?.['aria-label'] === 'Увеличить количество Мука пшеничная')
    h.api.quantity.value = '2.375'
    increase.props.onClick()
    assert.equal(h.api.quantity.value, 3.375)
    findVNode(h.render(), node => hasClass(node, 'catalog-actions__cart')).props.onClick()
    assert.equal(h.cartCalls[0][1], 3.375)
    assert.equal(h.cartCalls[0][2].measurement.unit_label, 'кг')
    h.api.quantity.value = 9998.5
    h.api.adjustQuantity(1)
    assert.equal(h.api.quantity.value, 9999)
    assert.equal(findVNode(h.render(), node => node.props?.['aria-label'] === 'Увеличить количество Мука пшеничная').props.disabled, true)
    h.api.quantity.value = 0.2
    h.api.adjustQuantity(-1)
    assert.equal(h.api.quantity.value, 0.001)
    assert.equal(findVNode(h.render(), node => node.props?.['aria-label'] === 'Уменьшить количество Мука пшеничная').props.disabled, true)
})

test('customer can ask a question even when the product has no configured order unit', t => {
    const h = harness(t, actionsPath, { good: { id: 3, name: 'Товар' }, compact: true })
    const tree = h.render()
    assert.equal(findVNode(tree, node => hasClass(node, 'catalog-actions__cart')), null)
    findVNode(tree, node => node.type === 'button' && nodeText(node) === 'Вопрос').props.onClick()
    assert.equal(h.emitted[0][1].kind, 'email')
    assert.equal(h.emitted[0][1].quantity, 1)
})

test('custom stock trigger receives the same real subscription lifecycle and errors', async t => {
    const requests = []
    let trigger
    const h = harness(t, stockPath, { good: product({ availability: { status: 'out_of_stock' } }), compact: true, label: 'Оповестить' }, {
        axios: { post: async (...args) => { requests.push(args); throw new Error('Связь недоступна') } },
    }, { $slots: { trigger: payload => { trigger = payload; return Vue.h('button', { onClick: payload.subscribe }, payload.label) } } })
    const tree = h.render()
    assert.equal(trigger.loading, false)
    assert.equal(nodeText(findVNode(tree, node => node.type === 'button')), 'Оповестить')
    await findVNode(tree, node => node.type === 'button').props.onClick()
    assert.deepEqual(requests, [['/alerts']])
    assert.equal(h.api.loading.value, false)
    assert.match(nodeText(findVNode(h.render(), node => node.props?.role === 'alert')), /Связь недоступна/)
})
