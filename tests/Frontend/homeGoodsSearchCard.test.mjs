import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { measurementForGood, unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, initial) {
    const filename = 'resources/js/Components/Home/HomeGoodsSearchCard.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'home-goods-search' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'home-goods-search', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const calls = []
    const environment = {
        ...Vue, Link: 'Link', CatalogNodeAvatar: 'CatalogNodeAvatar', logo: '/logo.png', measurementForGood, unitLabel,
        useAppRoute: () => ({ route: () => '/g' }),
        usePublicGoodUrl: () => ({ goodPublicUrl: good => `/g/${good.slug}` }),
        usePhoneCallRegistration: () => ({ registerPhoneCallClick: context => calls.push(context) }),
    }
    const source = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${source}}`)(environment)
    const props = Vue.reactive({ goods: [], loading: false, limit: 18, ...initial })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { api, calls, render: templateRenderer(template, api, props) }
}

const goods = [
    { id: 1, name: 'Брокколи замороженная, 10 кг, Египет', slug: 'broccoli', created_at: '2026-10-10', products: [{ id: 11, rus: 'Брокколи', eng: 'Broccoli', category: { name: 'Овощи' } }] },
    { id: 2, name: 'Какао-масло', slug: 'cocoa-butter', description: 'Для шоколада', created_at: '2026-10-12', products: [] },
    { id: 3, name: 'Лецитин', slug: 'lecithin', created_at: '2026-10-11', products: [] },
]

test('search matches product, description and category, and clearing restores recent goods without an error', t => {
    const h = harness(t, { goods, limit: 2 })
    const input = findVNode(h.render(), node => node.type === 'v-text-field')
    const search = query => input.props['onUpdate:modelValue'](query)

    assert.deepEqual(h.api.visibleGoods.value.map(good => good.id), [2, 3])
    for (const query of [' БРОККОЛИ ', 'broccoli', 'овощи']) {
        search(query)
        assert.deepEqual(h.api.visibleGoods.value.map(good => good.id), [1])
    }
    search('шоколада')
    assert.deepEqual(h.api.visibleGoods.value.map(good => good.id), [2])
    search('несуществующий товар')
    assert.deepEqual(h.api.visibleGoods.value, [])
    assert.ok(findVNode(h.render(), node => node.type === 'v-alert'))
    search(null)
    assert.equal(h.api.hasSearch.value, false)
    assert.deepEqual(h.api.visibleGoods.value.map(good => good.id), [2, 3])
    assert.ok(findVNode(h.render(), node => hasClass(node, 'good-row')))
})

test('result cards preserve complete names, product links, group labels and phone search context', t => {
    const h = harness(t, { goods })
    h.api.search.value = '  брокколи  '
    const tree = h.render()
    const row = findVNode(tree, node => hasClass(node, 'good-row'))
    assert.equal(row.props.href, '/g/broccoli')
    assert.equal(findVNode(row, node => hasClass(node, 'good-row__name')).children.trim(), goods[0].name)
    assert.equal(findVNode(row, node => hasClass(node, 'good-row__price')).children.trim(), 'Цена по запросу')
    assert.equal(h.api.groupedGoods.value[0].title, 'Брокколи')
    assert.equal(h.api.groupedGoods.value[0].category, 'Овощи')
    assert.equal(findVNode(tree, node => hasClass(node, 'goods-search-card__action-link')).props.href, '/g')
    const phone = findVNode(tree, node => hasClass(node, 'goods-search-card__phone'))
    assert.equal(phone.props.href, 'tel:+79650160001')
    phone.props.onClick()
    assert.deepEqual(h.calls, [{ source: 'home_goods_search_card', search: 'брокколи', good_id: 1, good_name: goods[0].name }])
    h.api.search.value = null
    phone.props.onClick()
    assert.equal(h.calls[1].search, null)
    assert.equal(h.calls[1].good_id, 2)
})
