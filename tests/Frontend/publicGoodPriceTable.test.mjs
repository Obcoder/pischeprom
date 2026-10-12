import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import postcss from 'postcss'
import { unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, initial) {
    const filename = 'resources/js/Components/Goods/PublicGoodPriceTable.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'public-good-price-table' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'public-good-price-table', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const source = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${source}}`)({ ...Vue, unitLabel })
    const props = Vue.reactive(Object.fromEntries(Object.entries(component.props).map(([key, definition]) => [
        key,
        key in initial ? initial[key] : typeof definition.default === 'function' ? definition.default() : definition.default,
    ])))
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { props, render: templateRenderer(template, api, props) }
}

function textOf(node) {
    if (typeof node === 'string') return node
    if (Array.isArray(node)) return node.map(textOf).join('')
    return node?.type === Vue.Comment ? '' : textOf(node?.children || '')
}

const retail = { price_type_id: 1, price_type_name: 'Розничная', price: 190, currency_code: 'RUB', includes_vat: true, is_retail: true }
const purchase = { price: 190, currency_code: 'RUB', includes_vat: true, measurement: { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 } }

test('the retail-only response renders one accessible price row with its currency, unit and VAT', t => {
    const h = harness(t, { prices: [retail], purchase })
    const tree = h.render()
    assert.equal(tree.type, 'table')
    assert.equal(tree.props['aria-label'], 'Цены товара')
    assert.equal(textOf(findVNode(tree, node => node.type === 'thead')), 'Тип ценыЦена / кг')
    const row = findVNode(tree, node => hasClass(node, 'good-price-table__retail'))
    assert.ok(row)
    assert.equal(textOf(row), 'Розничная190 ₽С НДС')
    assert.equal(findVNode(row, node => node.type === 'th').props.scope, 'row')
    assert.equal(textOf(findVNode(tree, node => node.type === 'tbody')), textOf(row))
    assert.doesNotMatch(textOf(tree), /Оптовая|Партнёрская|Дилерская|NaN|undefined|null/)
})

test('multiple approved rows keep their own currency and VAT while using the configured product unit', t => {
    const h = harness(t, {
        prices: [retail, { price_type_id: 2, price_type_name: 'Оптовая', price: 1234.56, currency_code: 'USD', includes_vat: false, is_retail: false }],
        purchase: { ...purchase, measurement: { measure_id: 2, unit_label: 'кор.', kilograms_per_unit: 10 } },
    })
    const tree = h.render()
    assert.equal(textOf(findVNode(tree, node => node.type === 'thead')), 'Тип ценыЦена / кор.')
    const wholesale = findVNode(tree, node => node.type === 'tr' && textOf(node).includes('Оптовая'))
    assert.equal(textOf(wholesale).replaceAll('\u00a0', ' '), 'Оптовая1 234,56 USDНДС уточняется')
    assert.equal(hasClass(wholesale, 'good-price-table__retail'), false)
    assert.equal(textOf(findVNode(tree, node => hasClass(node, 'good-price-table__retail'))), 'Розничная190 ₽С НДС')
})

test('an empty or omitted price list falls back to the purchase offer and missing price requests a quote', t => {
    const h = harness(t, { purchase })
    assert.equal(textOf(findVNode(h.render(), node => node.type === 'tbody')), 'Розничная190 ₽С НДС')
    h.props.prices = []
    h.props.purchase = { ...purchase, price: null }
    const body = findVNode(h.render(), node => node.type === 'tbody')
    assert.equal(textOf(body), 'РозничнаяПо запросу')
    assert.equal(findVNode(body, node => node.type === 'small'), null)
    assert.doesNotMatch(textOf(body), /0 ₽|НДС|NaN|undefined|null/)
})

test('a supplied unavailable price row requests a quote without claiming a VAT amount', t => {
    const h = harness(t, { prices: [{ ...retail, price: null }], purchase })
    const body = findVNode(h.render(), node => node.type === 'tbody')
    assert.equal(textOf(body), 'РозничнаяПо запросу')
    assert.equal(findVNode(body, node => node.type === 'small'), null)
})

test('description styling permits all lines, preserves paragraphs and wraps unbroken text on every viewport', () => {
    const filename = 'resources/js/Pages/Goods/Show.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const declarations = []
    for (const style of descriptor.styles) {
        postcss.parse(style.content).walkRules(rule => {
            if (!rule.selectors.some(selector => selector.trim() === '.hero-description')) return
            rule.walkDecls(declaration => declarations.push([declaration.prop, declaration.value]))
        })
    }
    const properties = Object.fromEntries(declarations)
    assert.ok(['pre-line', 'pre-wrap', 'break-spaces'].includes(properties['white-space']))
    assert.ok(['anywhere', 'break-word'].includes(properties['overflow-wrap']))
    for (const [property, value] of declarations) {
        if (['line-clamp', '-webkit-line-clamp', 'max-height', 'max-block-size'].includes(property)) assert.equal(value, 'none')
        if (['height', 'block-size'].includes(property)) assert.equal(value, 'auto')
        if (['overflow', 'overflow-y'].includes(property)) assert.equal(value, 'visible')
        if (property === 'text-overflow') assert.notEqual(value, 'ellipsis')
    }
})
