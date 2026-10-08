import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, pricing, mode = 'sales') {
    const filename = 'resources/js/Components/Catalog/CatalogGoodPricing.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'CatalogGoodPricing' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'CatalogGoodPricing', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(Vue)
    const props = Vue.reactive({ pricing, mode })
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

test('latest purchase shows a real zero with its currency, measure and document date; missing purchase stays empty', t => {
    const h = harness(t, { purchase: { purchase_id: 42, price: 0, currency_label: 'RUB', unit_label: 'т', date: '2026-10-08' } }, 'purchase')
    let box = findVNode(h.render(), node => hasClass(node, 'catalog-purchase-price'))
    assert.match(textOf(box), /0₽ \/ т08\.10\.2026/)
    assert.match(box.props.title, /№ 42/)
    h.props.pricing = { purchase: null }
    box = findVNode(h.render(), node => hasClass(node, 'catalog-purchase-price'))
    assert.equal(textOf(box), '—Нет закупок')
})

test('compact sales prices preserve zero and negative markup while unavailable markup explains the cause', t => {
    const h = harness(t, { sales: [
        { id: 1, name: 'Оптовая', price: 100, currency_label: 'RUB', includes_vat: true, markup_percent: 0 },
        { id: 2, name: 'Партнёрская', price: 90, currency_label: 'RUB', includes_vat: true, markup_percent: -10 },
        { id: 3, name: 'Экспорт', price: 2, currency_label: 'USD', includes_vat: true, markup_percent: null, markup_unavailable_reason: 'Разные валюты закупки и продажи' },
    ] })
    const tree = h.render()
    const text = textOf(tree)
    assert.match(text, /ТН/)
    assert.match(text, /0%/)
    assert.equal(textOf(findVNode(tree, node => hasClass(node, 'is-negative'))), '-10%')
    assert.equal(textOf(findVNode(tree, node => node.props?.title === 'Разные валюты закупки и продажи')), '—')
    assert.doesNotMatch(text, /NaN|undefined|null/)
    h.props.pricing = { sales: [] }
    assert.equal(textOf(h.render()), 'Нет действующих цен')
})
