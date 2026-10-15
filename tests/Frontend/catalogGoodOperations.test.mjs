import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/Catalog/CatalogGoodOperations.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'good-operations' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'good-operations', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], posts = [], emitted = []
    const props = Vue.reactive({ goodId: 42, activeTab: 'market', active: false, ...overrides })
    const environment = {
        ...Vue, useDate: () => ({ format: value => value }), route: (name, id) => `${name}/${id ?? ''}`,
        ...Object.fromEntries(['GoodPricingWorkspace', 'GoodAvitoTab', 'QuotationWorkspace', 'GoodPriceTypesTab', 'GoodMediaTab', 'FindBuyersLauncher'].map(name => [name, name])),
        axios: Object.fromEntries(['get', 'patch', 'post'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])),
        useForm(initial) {
            const data = Vue.reactive({ ...initial, errors: {}, processing: false })
            data.reset = () => Object.assign(data, initial)
            data.clearErrors = () => { data.errors = {} }
            data.transform = transform => { data.transformer = transform; return data }
            data.post = (url, options) => { data.processing = true; posts.push({ url, options, payload: data.transformer(Object.fromEntries(Object.keys(initial).map(key => [key, data[key]]))) }); return data }
            return data
        },
    }
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    const good = { id: 42, name: 'Форель', denominator: 5, vat_rate: { rate: 10 }, fields: [{ id: 1, title: 'Рыба' }], industries: [{ id: 3, code: '10.2', units: [] }], quotations: [{ id: 1 }], purchases: [{ id: 2 }], sales: [{ id: 3 }] }
    async function load(value = good) {
        props.active = true
        await Vue.nextTick()
        const batch = requests.slice(-5)
        assert.equal(batch.length, 5)
        for (const request of batch) request.resolve(request.url.startsWith('good.fetch') ? value : [{ id: 1, name: 'Справочник' }])
        await new Promise(resolve => setImmediate(resolve))
    }
    return { props, api, requests, posts, emitted, good, load, render: templateRenderer(template, api, props) }
}

test('operational tools load lazily, retain visited panels, and expose every existing tab action', async t => {
    const h = harness(t)
    assert.equal(h.requests.length, 0)
    await h.load()
    assert.equal(h.api.goodData.value.id, 42)
    assert.equal(h.api.currencies.value.length, 1)
    for (const key of ['market', 'prices', 'avito', 'price-types', 'collections', 'media', 'sales']) {
        assert.ok(findVNode(h.render(), node => node.type === 'v-window-item' && node.props.value === key), key)
    }
    for (const key of ['quotations', 'recommendations', 'purchases', 'warehouse']) {
        assert.equal(findVNode(h.render(), node => node.type === 'v-window-item' && node.props.value === key), null, key)
    }
    assert.equal(findVNode(h.render(), node => node.type === 'v-tabs'), null)
    assert.equal(findVNode(h.render(), node => node.type === 'GoodSeoTab'), null)
    assert.equal(findVNode(h.render(), node => node.type === 'FindBuyersLauncher'), null)
    const workspace = findVNode(h.render(), node => node.type === 'GoodPricingWorkspace')
    assert.equal(workspace.props['good-id'], 42)
    assert.equal(workspace.props.active, false)
    h.props.activeTab = 'media'; await Vue.nextTick()
    h.props.activeTab = 'prices'; await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'v-window-item' && node.props.value === 'media').props.eager, true)
    const edit = findVNode(h.render(), node => node.type === 'v-btn' && node.props['prepend-icon'] === 'mdi-pencil')
    edit.props.onClick()
    assert.ok(h.emitted.some(event => event[0] === 'request-basics'))
})

test('Avito editor preserves its draft and blocks parent close while sending a price', async t => {
    const h = harness(t); await h.load()
    h.props.activeTab = 'avito'; await Vue.nextTick()
    let child = findVNode(h.render(), node => node.type === 'GoodAvitoTab')
    assert.equal(child.props['good-id'], 42)
    assert.equal(child.props.active, true)
    child.props.onState({ dirty: true, busy: false }); await Vue.nextTick()
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.dirtyTab.value, 'avito')
    h.props.activeTab = 'prices'; await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'v-window-item' && node.props.value === 'avito').props.eager, true)
    child = findVNode(h.render(), node => node.type === 'GoodAvitoTab')
    assert.equal(child.props.active, false)
    child.props.onState({ dirty: true, busy: true }); await Vue.nextTick()
    assert.equal(h.api.busy.value, true)
    let resets = 0
    h.api.avitoWorkspace.value = { reset() { resets++ } }
    h.api.reset(); await Vue.nextTick()
    assert.equal(resets, 1)
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.busy.value, false)
    findVNode(h.render(), node => node.type === 'GoodPricingWorkspace').props.onRequestAvito()
    assert.ok(h.emitted.some(([name]) => name === 'request-avito'))
})

test('market owns supplier proposals while sales combines history, recommendations and buyer search', async t => {
    const h = harness(t); await h.load()
    const pane = key => findVNode(h.render(), node => node.type === 'v-window-item' && node.props.value === key)
    const market = findVNode(pane('market'), node => node.type === 'QuotationWorkspace')
    assert.deepEqual(market.props.quotations, h.good.quotations)
    assert.equal(market.props.good.id, 42)
    assert.equal(market.props.dict.currencies, h.api.currencies.value)
    assert.equal(market.props.dict.measures, h.api.measures.value)
    assert.equal(findVNode(h.render(), node => node.type === 'v-data-table' && node.props.items === h.api.goodData.value.purchases), null, 'Purchases belong to the warehouse; only the calculator retains purchase data')
    h.props.activeTab = 'sales'; await Vue.nextTick()
    const sales = pane('sales')
    assert.deepEqual(findVNode(sales, node => node.type === 'v-data-table').props.items, h.good.sales)
    const recommendations = findVNode(sales, node => node.type === 'v-autocomplete' && node.props.multiple === '')
    assert.deepEqual(recommendations.props.modelValue, [3])
    recommendations.props['onUpdate:modelValue']([3, 7])
    assert.deepEqual(h.api.recommendationIndustryIds.value, [3, 7])
    assert.ok(findVNode(sales, node => node.type === 'FindBuyersLauncher' && node.props['source-id'] === 42))
    assert.equal(findVNode(sales, node => node.type === 'v-btn' && node.props['prepend-icon'] === 'mdi-plus'), null)
    assert.equal(findVNode(pane('market'), node => node.type === 'FindBuyersLauncher'), null)

    for (const tab of ['market', 'prices', 'media']) {
        h.props.activeTab = tab; await Vue.nextTick()
        assert.equal(findVNode(h.render(), node => node.type === 'FindBuyersLauncher'), null, `Buyer search must stay within active sales: ${tab}`)
    }
    assert.equal(pane('sales').props.eager, true)
    h.props.activeTab = 'sales'; h.props.active = false; await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'FindBuyersLauncher'), null)
})

test('outdated initial loads cannot replace a switched good and do not refetch dictionaries on tab changes', async t => {
    const h = harness(t)
    h.props.active = true; await Vue.nextTick()
    const old = h.requests.slice()
    h.props.goodId = 55; await Vue.nextTick()
    assert.equal(old[0].body.signal.aborted, true)
    for (const request of h.requests.slice(5)) request.resolve(request.url.startsWith('good.fetch') ? { ...h.good, id: 55 } : [])
    await new Promise(resolve => setImmediate(resolve))
    for (const request of old) request.resolve(request.url.startsWith('good.fetch') ? h.good : [])
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(h.api.goodData.value.id, 55)
    h.props.activeTab = 'prices'; await Vue.nextTick()
    assert.equal(h.requests.length, 10)
})

test('leaving operations cancels loading and revisiting reloads without letting stale responses overwrite the good', async t => {
    const h = harness(t, { activeTab: 'sales' })
    h.props.active = true; await Vue.nextTick()
    const abandoned = h.requests.slice()
    h.props.active = false; await Vue.nextTick()
    assert.ok(abandoned.every(request => request.body.signal.aborted))
    assert.equal(h.api.pageLoading.value, false)
    for (const request of abandoned) request.resolve(request.url.startsWith('good.fetch') ? { ...h.good, name: 'Старое имя' } : [])
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(h.api.goodData.value, null)
    await h.load()
    assert.equal(h.api.goodData.value.name, 'Форель')
    assert.ok(h.api.visitedTabs.value.has('sales'))
})

test('recommendation drafts survive refresh and only successful saving clears their dirty state', async t => {
    const h = harness(t); await h.load()
    h.api.recommendationIndustryIds.value = [3, 7]
    await Vue.nextTick()
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.dirtyTab.value, 'sales')
    assert.ok(h.emitted.some(event => event[0] === 'state' && event[1].dirtyTab === 'sales'))
    const reload = h.api.refresh()
    h.requests.at(-1).resolve({ ...h.good, name: 'Новое имя' }); await reload
    assert.deepEqual(h.api.recommendationIndustryIds.value, [3, 7])
    const save = h.api.saveRecommendationClassifications()
    assert.deepEqual(h.requests.at(-1).body, { industry_ids: [3, 7] })
    assert.equal(h.api.busy.value, true)
    h.requests.at(-1).resolve({})
    await Vue.nextTick()
    h.requests.at(-1).resolve({ ...h.good, industries: [{ id: 3 }, { id: 7 }] })
    await save; await Vue.nextTick()
    assert.equal(h.api.dirty.value, false)
    assert.ok(h.emitted.some(event => event[0] === 'changed'))
    h.api.recommendationIndustryIds.value = [7]
    const failure = h.api.saveRecommendationClassifications()
    h.requests.at(-1).reject({ response: { data: { message: 'Запись не сохранена' } } })
    await failure
    assert.equal(h.api.pageError.value, 'Запись не сохранена')
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.dirtyTab.value, 'sales')
})

test('quotation workspace changes refresh supplier sources without clearing pricing and recommendation drafts', async t => {
    const h = harness(t); await h.load()
    let sourceRefreshes = 0
    h.api.pricingWorkspace.value = { refreshSources: async () => { sourceRefreshes++ } }
    h.api.recommendationIndustryIds.value = [7]
    h.api.pricingState.value = { dirty: true, busy: false }
    const market = findVNode(h.render(), node => node.type === 'QuotationWorkspace')
    market.props.onState({ dirty: true, busy: true })
    await Vue.nextTick()
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.busy.value, true)
    assert.equal(h.api.dirtyTab.value, 'market')
    assert.ok(h.emitted.some(event => event[0] === 'state' && event[1].busy && event[1].dirtyTab === 'market'))
    market.props.onState({ dirty: false, busy: false })
    const saved = market.props.onChanged({ id: 2 })
    h.requests.at(-1).resolve({ ...h.good, quotations: [{ id: 1 }, { id: 2 }] })
    await saved; await Vue.nextTick()
    assert.equal(h.api.goodData.value.quotations.length, 2)
    assert.equal(sourceRefreshes, 1)
    assert.deepEqual(h.api.recommendationIndustryIds.value, [7])
    assert.equal(h.api.pricingState.value.dirty, true)
    assert.ok(h.emitted.some(event => event[0] === 'changed'))
    findVNode(h.render(), node => node.type === 'GoodPricingWorkspace').props.onRequestMarket()
    assert.ok(h.emitted.some(event => event[0] === 'request-market'))
})

test('media and calculation changes refresh the source without resetting another tab draft', async t => {
    const h = harness(t); await h.load()
    h.api.recommendationIndustryIds.value = [7]
    const media = findVNode(h.render(), node => node.type === 'GoodMediaTab')
    const update = media.props.onChanged()
    h.requests.at(-1).resolve({ ...h.good, ava_image: '/new.jpg' })
    await update
    assert.equal(h.api.goodData.value.ava_image, '/new.jpg')
    assert.deepEqual(h.api.recommendationIndustryIds.value, [7])
    const pricing = findVNode(h.render(), node => node.type === 'GoodPricingWorkspace')
    const change = pricing.props.onChanged()
    h.requests.at(-1).resolve(h.good)
    await change
})

test('parent reset clears quotation draft state through the shared workspace', async t => {
    const h = harness(t); await h.load()
    let quotationResets = 0, pricingResets = 0
    h.api.quotationWorkspace.value = { reset() { quotationResets++ } }
    h.api.pricingWorkspace.value = { reset() { pricingResets++ } }
    h.api.quotationState.value = { dirty: true, busy: false }
    h.api.pricingState.value = { dirty: true, busy: false }
    h.api.recommendationIndustryIds.value = [7]
    h.api.reset()
    await Vue.nextTick()
    assert.equal(h.api.dirty.value, false)
    assert.equal(quotationResets, 1)
    assert.equal(pricingResets, 1)
    assert.deepEqual(h.api.recommendationIndustryIds.value, [3])
})

test('parent-triggered refresh failures remain visible without rejecting or losing unsaved operations', async t => {
    const h = harness(t); await h.load()
    h.api.recommendationIndustryIds.value = [7]
    const refresh = h.api.refresh()
    h.requests.at(-1).reject({ response: { status: 503, data: { message: 'Сервис временно недоступен' } } })
    assert.equal(await refresh, null)
    assert.equal(h.api.pageError.value, 'Сервис временно недоступен')
    assert.equal(h.api.goodData.value.id, 42)
    assert.deepEqual(h.api.recommendationIndustryIds.value, [7])
})
