import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { emptyProductTranslationForm, productTranslationFields } from '../../resources/js/Pages/Helpers/productLanguages.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const source = (overrides = {}) => ({ id: 42, rus: 'Скумбрия', eng: 'Mackerel', manufacturers: [{ id: 8, name: 'Фабрика' }], components: [{ id: 2, name: 'Рыба' }], units: [{ id: 8, name: 'Фабрика', product_action: { id: 1, name: 'Переработка' } }], consumers: [{ id: 3 }], goods: [{ id: 7, name: 'Скумбрия 300+', quotations: [] }], sales: [], ...overrides })
const flush = async () => { await Promise.resolve(); await Vue.nextTick() }
function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/Catalog/CatalogProductOperations.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'product-operations' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'product-operations', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = []
    const props = Vue.reactive({ productId: 42, activeTab: 'translations', active: false, ...overrides })
    const environment = {
        ...Vue, emptyProductTranslationForm, productTranslationFields,
        usePage: () => ({ props: { auth: { permissions: { ai_sales: { view: true } } } } }),
        route: (name, id) => `${name}/${id}`,
        ...Object.fromEntries(['ProductUnitConsumersCard', 'ProductEntityConsumptionsCard', 'ProductMarketPanel'].map(name => [name, name])),
        axios: Object.fromEntries(['get', 'put'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])),
    }
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    async function load(value = source()) {
        props.active = true
        await Vue.nextTick()
        requests.at(-1).resolve(value)
        await flush()
    }
    return { props, api, requests, emitted, load, render: templateRenderer(template, api, props) }
}

test('product panels load lazily and move buyer services into the separate market tab', async t => {
    const h = harness(t)
    assert.equal(h.requests.length, 0)
    await h.load()
    assert.equal(h.requests[0].url, '/api/products/42')
    assert.equal(h.api.form.eng, 'Mackerel')
    for (const tab of ['manufacturers', 'components', 'units']) {
        h.props.activeTab = tab; await Vue.nextTick()
        const table = findVNode(h.render(), node => node.type === 'v-data-table')
        assert.equal(table.props.items[0].name, source()[tab][0].name)
    }
    h.props.activeTab = 'consumers'; await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'ProductEntityConsumptionsCard').props['product-id'], 42)
    assert.deepEqual(findVNode(h.render(), node => node.type === 'ProductUnitConsumersCard').props.consumers, source().consumers)
    assert.equal(findVNode(h.render(), node => node.type === 'ProductMarketPanel'), null)
    h.props.activeTab = 'sales'; await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'ProductMarketPanel'), null)
    h.props.activeTab = 'market'; await Vue.nextTick()
    const market = findVNode(h.render(), node => node.type === 'ProductMarketPanel')
    assert.equal(market.props['product-id'], 42)
    assert.equal(market.props['product-name'], 'Скумбрия')
    assert.equal(market.props['can-view-ai-sales'], true)
    assert.equal(market.props.active, true)
    h.props.activeTab = 'sales'; await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'ProductMarketPanel').props.active, false)
    h.props.activeTab = 'market'; h.props.active = false; await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'ProductMarketPanel').props.active, false)
    assert.equal(h.requests.length, 1)
})

test('translations remain dirty across tabs and refresh; save changes only language fields', async t => {
    const h = harness(t); await h.load()
    h.api.form.eng = 'Updated name'
    h.api.form.rus = 'Скумбрия атлантическая'
    assert.equal(h.api.dirty.value, true)
    assert.ok(h.emitted.some(event => event[0] === 'state' && event[1].dirtyTab === 'translations'))
    h.props.activeTab = 'goods'; await Vue.nextTick()
    const refresh = h.api.refresh()
    h.requests.at(-1).resolve(source({ eng: 'Other name' })); await refresh
    assert.equal(h.api.form.eng, 'Updated name')
    assert.equal(h.api.dirty.value, true)
    const save = h.api.saveTranslations()
    const request = h.requests.at(-1)
    assert.equal(request.method, 'put')
    assert.equal(request.url, '/api/products/42')
    assert.equal(request.body.eng, 'Updated name')
    assert.equal(request.body.rus, 'Скумбрия атлантическая')
    assert.equal(Object.hasOwn(request.body, 'category_id'), false)
    assert.equal(Object.hasOwn(request.body, 'is_published'), false)
    request.resolve(source(request.body)); await save
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.saving.value, false)
    assert.equal(h.emitted.find(event => event[0] === 'changed')[1].rus, 'Скумбрия атлантическая')
})

test('translation failures retain the draft and show validation; reset restores confirmed values', async t => {
    const h = harness(t); await h.load()
    h.api.form.rus = ' '
    await h.api.saveTranslations()
    assert.ok(h.api.fieldErrors.value.rus)
    assert.equal(h.requests.length, 1)
    h.api.form.rus = 'Новое'
    const save = h.api.saveTranslations()
    h.requests.at(-1).reject({ response: { data: { message: 'Ошибка сохранения', errors: { rus: ['Ошибка'] } } } })
    await save
    assert.equal(h.api.form.rus, 'Новое')
    assert.equal(h.api.error.value, 'Ошибка сохранения')
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.saving.value, false)
    h.api.reset()
    assert.equal(h.api.form.rus, 'Скумбрия')
    assert.equal(h.api.dirty.value, false)
})

test('switching entity cancels stale loads, resets drafts and rejects mismatched responses', async t => {
    const h = harness(t, { active: true })
    const first = h.requests[0]
    h.props.productId = 87
    assert.equal(first.body.signal.aborted, true)
    h.requests.at(-1).resolve(source({ id: 87, rus: 'Треска' })); await flush()
    first.resolve(source()); await flush()
    assert.equal(h.api.product.value.id, 87)
    assert.equal(h.api.form.rus, 'Треска')
    h.props.activeTab = 'consumers'; await Vue.nextTick()
    h.api.form.eng = 'Cod'
    h.props.productId = 88
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.visitedTabs.value.has('consumers'), true)
    h.requests.at(-1).resolve(source({ id: 87 })); await flush()
    assert.equal(h.api.product.value, null)
    assert.match(h.api.error.value, /Не удалось загрузить/)
})

test('saving invalidates an older refresh so its result cannot undo confirmed translations', async t => {
    const h = harness(t); await h.load()
    const refresh = h.api.refresh()
    const obsolete = h.requests.at(-1)
    h.api.form.eng = 'Saved translation'
    const save = h.api.saveTranslations()
    assert.equal(obsolete.body.signal.aborted, true)
    h.requests.at(-1).resolve(source({ eng: 'Saved translation' })); await save
    obsolete.resolve(source()); await refresh
    assert.equal(h.api.product.value.eng, 'Saved translation')
    assert.equal(h.api.form.eng, 'Saved translation')
})

test('sales distinguish the current product amount from a mixed document total', async t => {
    const h = harness(t, { activeTab: 'sales' })
    await h.load(source({ sales: [{ id: 9, total: '900.00', goods: [
        { id: 7, pivot: { quantity: 2, price: 100, total: '200.00' } },
        { id: 8, pivot: { quantity: 7, price: 100, total: '700.00' } },
    ] }] }))
    assert.equal(h.api.sales.value[0].productTotal, 200)
    assert.equal(h.api.sales.value[0].total, '900.00')
    assert.deepEqual(h.api.sales.value[0].productGoods.map(good => good.id), [7])
    const table = findVNode(h.render(), node => node.type === 'v-data-table')
    assert.equal(table.props.items[0].productTotal, 200)
})

test('confirmed catalogue renames synchronize clean translations while inactive without replacing language drafts', async t => {
    const h = harness(t); await h.load()
    h.props.active = false; await Vue.nextTick()
    h.api.form.eng = 'English draft'
    h.api.syncName('Новое название из карточки')
    assert.equal(h.requests.length, 1)
    assert.equal(h.api.product.value.rus, 'Новое название из карточки')
    assert.equal(h.api.form.rus, 'Новое название из карточки')
    assert.equal(JSON.parse(h.api.baseline.value).rus, 'Новое название из карточки')
    assert.equal(h.api.form.eng, 'English draft')
    assert.equal(h.api.dirty.value, true)
    const save = h.api.saveTranslations()
    assert.equal(h.requests.at(-1).body.rus, 'Новое название из карточки')
    h.requests.at(-1).resolve(source(h.requests.at(-1).body)); await save
    assert.equal(h.api.dirty.value, false)
    h.api.syncName('Ещё одно подтверждённое название')
    assert.equal(h.api.dirty.value, false)
})

test('confirmed catalogue renames preserve explicit Russian drafts and reset to the new confirmed name', async t => {
    const h = harness(t); await h.load()
    h.api.form.rus = 'Черновик перевода'
    h.api.syncName('Подтверждённое название')
    assert.equal(h.api.form.rus, 'Черновик перевода')
    assert.equal(h.api.product.value.rus, 'Подтверждённое название')
    assert.equal(JSON.parse(h.api.baseline.value).rus, 'Подтверждённое название')
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.requests.length, 1)
    h.api.reset()
    assert.equal(h.api.form.rus, 'Подтверждённое название')
    assert.equal(h.api.dirty.value, false)
})
