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
    const props = Vue.reactive({ goodId: 42, activeTab: 'quotations', active: false, ...overrides })
    const environment = {
        ...Vue, useDate: () => ({ format: value => value }), route: (name, id) => `${name}/${id ?? ''}`,
        ...Object.fromEntries(['GoodQuotationCalculator', 'GoodPriceCalculationsTab', 'GoodPriceTypesTab', 'GoodPriceTypeValuesTab', 'GoodMediaTab', 'FindBuyersLauncher'].map(name => [name, name])),
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
    assert.equal(h.api.defaultVatRate.value, 10)
    assert.equal(h.api.goodBoxWeight.value, 5)
    assert.equal(h.api.currencies.value.length, 1)
    for (const key of ['quotations', 'prices', 'price-types', 'recommendations', 'collections', 'media', 'sales']) {
        assert.ok(findVNode(h.render(), node => node.type === 'v-window-item' && node.props.value === key), key)
    }
    assert.equal(findVNode(h.render(), node => node.type === 'v-tabs'), null)
    assert.equal(findVNode(h.render(), node => node.type === 'GoodSeoTab'), null)
    assert.ok(findVNode(h.render(), node => node.type === 'FindBuyersLauncher' && node.props['source-id'] === 42))
    const calculator = findVNode(h.render(), node => node.type === 'GoodQuotationCalculator')
    assert.deepEqual(calculator.props.quotations, [{ id: 1 }])
    assert.deepEqual(calculator.props.purchases, [{ id: 2 }])
    h.props.activeTab = 'media'; await Vue.nextTick()
    h.props.activeTab = 'prices'; await Vue.nextTick()
    assert.equal(findVNode(h.render(), node => node.type === 'v-window-item' && node.props.value === 'media').props.eager, true)
    const edit = findVNode(h.render(), node => node.type === 'v-btn' && node.props['prepend-icon'] === 'mdi-pencil')
    edit.props.onClick()
    assert.ok(h.emitted.some(event => event[0] === 'request-basics'))
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

test('recommendation drafts survive refresh and only successful saving clears their dirty state', async t => {
    const h = harness(t); await h.load()
    h.api.recommendationIndustryIds.value = [3, 7]
    await Vue.nextTick()
    assert.equal(h.api.dirty.value, true)
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
})

test('quotation creation preserves supplier, measure and package values and reports busy/dirty state', async t => {
    const h = harness(t); await h.load()
    h.api.dialogFormQuotation.value = true
    Object.assign(h.api.formQuotation, { unit_id: 7, measure_id: 2, price: '123.45', denominator: '2.5' })
    await Vue.nextTick()
    assert.equal(h.api.dirty.value, true)
    const saved = h.api.storeQuotation()
    await Vue.nextTick()
    assert.equal(h.requests.at(-1).url, 'web.quotation.store/')
    assert.deepEqual(h.requests.at(-1).body, { good_id: 42, unit_id: 7, measure_id: 2, price: 123.45, denominator: 2.5 })
    assert.equal(h.api.busy.value, true)
    assert.ok(h.emitted.some(event => event[0] === 'state' && event[1].busy && event[1].dirty))
    h.requests.at(-1).resolve({ id: 2 })
    await Vue.nextTick()
    h.requests.at(-1).resolve({ ...h.good, quotations: [{ id: 1 }, { id: 2 }] })
    await saved; await Vue.nextTick()
    assert.equal(h.api.dialogFormQuotation.value, false)
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.goodData.value.quotations.length, 2)
    h.api.recommendationIndustryIds.value = []
    h.api.formQuotation.price = 20
    h.api.reset()
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.formQuotation.good_id, 42)
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
    h.api.handleCalculationApplied()
    assert.equal(h.api.priceValuesRefreshKey.value, 1)
    h.requests.at(-1).resolve(h.good)
    await Vue.nextTick()
})

test('quotation validation keeps the draft and renders server errors without triggering Inertia navigation', async t => {
    const h = harness(t); await h.load()
    h.api.dialogFormQuotation.value = true
    h.api.formQuotation.price = ''
    const save = h.api.storeQuotation()
    assert.equal(h.requests.at(-1).body.price, null)
    h.requests.at(-1).reject({ response: { status: 422, data: { message: 'Выберите поставщика', errors: { unit_id: ['Поле обязательно'] } } } })
    await save
    assert.equal(h.api.dialogFormQuotation.value, true)
    assert.deepEqual(h.api.formQuotation.errors.unit_id, ['Поле обязательно'])
    assert.equal(h.api.quotationError.value, 'Выберите поставщика')
    assert.equal(h.api.busy.value, false)
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
