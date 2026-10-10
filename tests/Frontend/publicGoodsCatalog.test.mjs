import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as catalog from '../../resources/js/utils/publicCatalog.js'
import { unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, filename, initial, extras = {}) {
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'public-goods-catalog' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'public-goods-catalog', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const calls = [], emitted = [], mounted = [], cartCalls = []
    const cart = { succeeds: true, cartError: Vue.ref('Ошибка корзины'), addGood: (...args) => { cartCalls.push(args); return cart.succeeds } }
    const environment = {
        ...Vue, ...catalog, unitLabel, Head: 'Head', Link: 'Link', LayoutDefault: {},
        PublicCatalogGoodCard: 'PublicCatalogGoodCard', PublicCatalogTree: 'PublicCatalogTree', PublicCatalogGoodActions: 'PublicCatalogGoodActions', GoodInquiryDialog: 'GoodInquiryDialog', GoodStockAlertButton: 'GoodStockAlertButton',
        useAppRoute: () => ({ route: (name, id) => name === 'public.fields.show' ? `/fields/${id}` : '/g' }),
        usePublicGoodUrl: () => ({ goodPublicUrl: good => `/g/${good.slug || good.id}` }),
        router: { get: (...args) => calls.push(args) },
        usePage: () => ({ url: '/g' }), onMounted: callback => mounted.push(callback), onBeforeUnmount: () => {},
        useOrderCart: () => cart, ...extras,
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive(initial)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { props, api, calls, emitted, mounted, cart, cartCalls, render: templateRenderer(template, api, props, { $emit: (...args) => emitted.push(args) }) }
}
const measurement = { measure_id: 7, unit_label: 'кг', kilograms_per_unit: 1 }
const good = { id: 12, name: 'Мука', catalog_node_id: 11, measurement, public_purchase: { price: 150, currency_code: 'RUB', measurement } }
const nodes = [{ id: 10, parent_id: null, name: 'Ингредиенты', goods_count: 25 }, { id: 11, parent_id: 10, name: 'Мука', goods_count: 25 }, { id: 20, parent_id: null, name: 'Рыба', goods_count: 7 }]
const defaults = (extra = {}) => ({ goods: { data: [good], current_page: 2, last_page: 5, total: 101, from: 25, to: 48 }, filters: { ...catalog.catalogDefaults }, field: null, country: null, fields: [], countries: [], catalogTree: nodes, breadcrumbs: [], site: { name: 'Магазин' }, ...extra })
const goodsPage = 'resources/js/Pages/Goods.vue'
const actionsComponent = 'resources/js/Components/Goods/PublicCatalogGoodActions.vue'

test('pagination sends current filters to server and displays total independent of loaded rows', t => {
    const h = harness(t, goodsPage, defaults({ filters: { search: 'мука', country_id: 4, node_id: 11, per_page: 24 } }))
    assert.equal(h.api.total.value, 101)
    assert.equal(h.api.productList.value.length, 1)
    const next = findVNode(h.render(), vnode => vnode.props?.['aria-label'] === 'Следующая страница')
    next.props.onClick()
    assert.equal(h.calls[0][0], '/g')
    assert.equal(h.calls[0][1].page, 3)
    assert.equal(h.calls[0][1].country_id, 4)
    assert.equal(h.calls[0][1].node_id, 11)
    assert.equal(h.calls[0][1].search, 'мука')
})

test('mode and filter visibility preserve the current page while page size resets it', t => {
    const h = harness(t, goodsPage, defaults())
    h.api.setView('tree')
    assert.equal(h.calls.at(-1)[1].view, 'tree')
    assert.equal(h.calls.at(-1)[1].page, 2)
    assert.ok(findVNode(h.render(), vnode => vnode.type === 'PublicCatalogTree'))
    h.api.toggleFilters()
    assert.equal(h.calls.at(-1)[1].show_filters, 0)
    assert.equal(h.calls.at(-1)[1].page, 2)
    assert.equal(findVNode(h.render(), vnode => vnode.props?.id === 'catalog-filters'), null)
    h.api.form.per_page = 100
    const selector = findVNode(findVNode(h.render(), vnode => hasClass(vnode, 'catalog-page-size')), vnode => vnode.type === 'select')
    selector.props.onChange()
    assert.equal(h.calls.at(-1)[1].page, 1)
    assert.equal(h.calls.at(-1)[1].per_page, 100)
})

test('back navigation synchronizes controls with authoritative server filters', async t => {
    const h = harness(t, goodsPage, defaults())
    h.api.form.search = 'unsent'
    h.props.filters = { search: 'исторический запрос', view: 'tree', show_filters: false, per_page: 50, node_id: 10 }
    await Vue.nextTick()
    assert.equal(h.api.form.search, 'исторический запрос')
    assert.equal(h.api.form.view, 'tree')
    assert.equal(h.api.form.show_filters, false)
    assert.equal(h.api.form.per_page, 50)
    assert.equal(h.calls.length, 0)
})

test('saved preferences restore after mount but explicit URL parameters take precedence', t => {
    const storage = { getItem: () => JSON.stringify({ view: 'tree', per_page: 100, show_filters: false }), setItem: () => {} }
    const browser = { location: { href: 'https://food.test/g?view=cards&per_page=10', origin: 'https://food.test' }, localStorage: storage }
    const h = harness(t, goodsPage, defaults({ filters: { view: 'cards', per_page: 10 } }), { window: browser, usePage: () => ({ url: '/g?view=cards&per_page=10' }) })
    assert.equal(h.api.form.show_filters, true, 'SSR uses server values before browser storage is read')
    h.mounted[0]()
    assert.equal(h.api.form.view, 'cards')
    assert.equal(h.api.form.per_page, 10)
    assert.equal(h.api.form.show_filters, false)
    assert.equal(h.calls.length, 1)
})

test('reset removes query filters and category while keeping display preferences', t => {
    const h = harness(t, goodsPage, defaults({ filters: { search: 'мука', country_id: 1, field_id: 2, node_id: 11, price_min: 5, availability: 'in_stock', view: 'tree', per_page: 100, show_filters: false } }))
    h.api.resetFilters()
    const query = h.calls.at(-1)[1]
    for (const key of ['search', 'country_id', 'field_id', 'node_id', 'price_min']) assert.equal(query[key], undefined)
    assert.equal(query.availability, 'all')
    assert.equal(query.view, 'tree')
    assert.equal(query.show_filters, 0)
    assert.equal(query.per_page, 100)
})

test('tree expands actual product ancestors and collapse hides descendants without fetching all goods', t => {
    const h = harness(t, 'resources/js/Components/Goods/PublicCatalogTree.vue', { nodes, goods: [good], selectedId: 11 })
    assert.deepEqual([...h.api.expanded.value], ['11', '10'])
    assert.deepEqual(h.api.rows.value.map(row => row.key), ['node-10', 'node-11', 'good-12', 'node-20'])
    h.api.toggle(10)
    assert.deepEqual(h.api.rows.value.map(row => row.key), ['node-10', 'node-20'])
    findVNode(h.render(), vnode => hasClass(vnode, 'catalog-tree__category')).props.onClick()
    assert.deepEqual(h.emitted[0], ['select', 10])
})

test('cart and immediate order use entered fractional units and exact public offer', t => {
    const h = harness(t, actionsComponent, { good, compact: true })
    h.api.quantity.value = '2.375'
    findVNode(h.render(), vnode => hasClass(vnode, 'catalog-actions__cart')).props.onClick()
    assert.equal(h.cartCalls.length, 1)
    assert.equal(h.cartCalls[0][1], 2.375)
    assert.equal(h.cartCalls[0][2].price, 150)
    assert.deepEqual(h.cartCalls[0][2].measurement, measurement)
    assert.equal(h.emitted[0][0], 'notice')
    assert.equal(h.emitted[0][1].success, true)
    h.api.inquire('order')
    assert.equal(h.emitted[1][0], 'inquiry')
    assert.equal(h.emitted[1][1].quantity, 2.375)
    assert.equal(h.emitted[1][1].purchase.price, 150)
})

test('invalid quantities do not silently create cart items and cart errors stay visible', t => {
    const h = harness(t, actionsComponent, { good, compact: false })
    h.api.quantity.value = 0
    h.api.addToCart()
    assert.equal(h.cartCalls.length, 0)
    assert.match(h.api.error.value, /0,001/)
    h.api.quantity.value = 1
    h.cart.succeeds = false
    h.api.addToCart()
    assert.equal(h.emitted[0][1].success, false)
    assert.equal(h.emitted[0][1].message, 'Ошибка корзины')
})

test('unconfigured units prevent cart/order and private price relations never become a public quote', t => {
    const unconfigured = { id: 3, price_type_values: [{ price_gross: 13, price_type: { is_public: false } }] }
    const h = harness(t, actionsComponent, { good: unconfigured, compact: false })
    assert.equal(h.api.offer.value.price, null)
    assert.equal(h.api.canOrder.value, false)
    h.api.addToCart()
    h.api.inquire('order')
    assert.equal(h.cartCalls.length, 0)
    assert.equal(h.emitted.length, 0)
    assert.equal(findVNode(h.render(), vnode => hasClass(vnode, 'catalog-actions__cart')), null)
    assert.equal(catalog.catalogMoney(unconfigured), 'Цена по запросу')
})

test('pagination windows stay bounded and page size is restricted to 10–100', () => {
    assert.deepEqual(catalog.catalogPages(50, 100), [1, '…', 49, 50, 51, '…', 100])
    assert.deepEqual(catalog.catalogPages(1, 2), [1, 2])
    assert.equal(catalog.normalizeCatalogFilters({ per_page: 10000 }).per_page, 100)
    assert.equal(catalog.normalizeCatalogFilters({ per_page: 1 }).per_page, 10)
    assert.equal(catalog.normalizeCatalogFilters({ show_filters: '0' }).show_filters, false)
})


test('catalog metadata renders through the shared Inertia head on server and client', t => {
    const h = harness(t, goodsPage, defaults({ filters: { node_id: 11 } }))
    const tree = h.render()
    assert.equal(findVNode(tree, vnode => vnode.type === 'Head').props.title, 'Мука — Магазин')
    assert.equal(findVNode(tree, vnode => vnode.props?.name === 'description').props.content, h.api.pageDescription.value)
})
