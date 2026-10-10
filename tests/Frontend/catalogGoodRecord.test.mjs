import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function component(filename, environment) {
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'good-record-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'good-record-test', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    return { definition: new Function('env', `with(env){${code}}`)(environment), template }
}

function recordHarness(t) {
    const requests = []
    const props = Vue.reactive({ modelValue: false, goodId: 42 })
    const environment = {
        ...Vue, CatalogNodeDialog: {}, CatalogSchemaDialog: {}, _mergeModels: Vue.mergeModels,
        _useModel: (props, name) => Vue.computed({ get: () => props[name], set: value => { props[name] = value } }),
        axios: { get(url, options) {
            return new Promise((resolve, reject) => { requests.push({ url, options, resolve: data => resolve({ data }), reject }) })
        } },
    }
    const { definition } = component('resources/js/Components/Catalog/CatalogGoodRecordDialog.vue', environment)
    const scope = Vue.effectScope()
    const state = scope.run(() => definition.setup(props, { expose() {}, emit() {} }))
    t.after(() => scope.stop())
    return { props, state, requests }
}

test('accounting page opens the same record lazily and retains its placement on schema reload', async t => {
    const { props, state, requests } = recordHarness(t)
    assert.equal(requests.length, 0)
    props.modelValue = true
    await Vue.nextTick()
    assert.equal(requests[0].url, '/api/catalog')
    const first = { id: 7, entity_type: 'good', entity_id: 42, name: 'Товар', parent_id: 10 }
    const other = { ...first, id: 8, parent_id: 20 }
    requests[0].resolve({ levels: [{ id: 3 }], nodes: [first, other] })
    await Vue.nextTick()
    assert.equal(state.node.value.id, 7)
    const reload = state.load()
    requests[1].resolve({ levels: [{ id: 3, name: 'Переименованный уровень' }], nodes: [other, first] })
    await reload
    assert.equal(state.node.value.id, 7)
    assert.equal(state.levels.value[0].name, 'Переименованный уровень')
})

test('late catalog response cannot replace a switched good or reopen a dismissed card', async t => {
    const { props, state, requests } = recordHarness(t)
    props.modelValue = true
    await Vue.nextTick()
    props.goodId = 55
    await Vue.nextTick()
    assert.equal(requests[0].options.signal.aborted, true)
    const newest = { id: 9, entity_type: 'good', entity_id: 55, name: 'Другой товар' }
    requests[1].resolve({ levels: [], nodes: [newest] })
    await Vue.nextTick()
    requests[0].resolve({ levels: [], nodes: [{ id: 7, entity_type: 'good', entity_id: 42 }] })
    await Vue.nextTick()
    assert.equal(state.node.value.id, 9)
    const pending = state.load()
    props.modelValue = false
    await Vue.nextTick()
    requests[2].reject(new Error('old response'))
    await pending
    assert.equal(state.error.value, '')
    assert.equal(props.modelValue, false)
})

test('missing catalog record produces a retryable error instead of a new or unrelated good', async t => {
    const { props, state, requests } = recordHarness(t)
    props.modelValue = true
    await Vue.nextTick()
    requests[0].resolve({ levels: [], nodes: [{ id: 1, entity_type: 'product', entity_id: 42 }] })
    await Vue.nextTick()
    assert.equal(state.node.value, null)
    assert.match(state.error.value, /Товар не найден/)
    assert.equal(state.loading.value, false)
})

function pageHarness(t, tab, api = {}) {
    const location = { href: '' }
    const requests = []
    const environment = {
        ...Vue, onMounted() {}, useHead() {}, useDate: () => ({ format: value => value }),
        usePage: () => ({ url: `/Ameise/goods/42?tab=${tab}`, props: { ziggy: { location: 'https://example.test/Ameise/goods/42' } } }),
        useForm: value => Vue.reactive(value), route: (name, id) => `${name}/${id ?? ''}`,
        axios: { get(url, options) {
            if (api.get) return api.get(url, options)
            return new Promise((resolve, reject) => requests.push({ url, options, resolve: data => resolve({ data }), reject }))
        } },
        window: { location, addEventListener() {}, removeEventListener() {} },
        ...Object.fromEntries(['VerwalterLayout', 'CatalogGoodRecordDialog', 'CatalogGoodOperations', 'CatalogGoodWarehouse'].map(name => [name, name])),
    }
    const { definition, template } = component('resources/js/Pages/Ameise/Good.vue', environment)
    const props = Vue.reactive({ good: { id: 42 } })
    const scope = Vue.effectScope()
    const state = scope.run(() => definition.setup(props, { expose() {}, emit() {} }))
    t.after(() => scope.stop())
    state.goodData.value = { id: 42, name: 'Товар', fields: [] }
    return { state, props, location, requests, render: templateRenderer(template, state, props) }
}

test('good page replaces Overview with the shared record action and supports statistic deep links', t => {
    for (const [query, expected] of [['overview', 'market'], ['prices', 'prices'], ['media', 'media'], ['unknown', 'market'], ['market', 'market'], ['warehouse', 'warehouse'], ['quotations', 'market'], ['recommendations', 'sales'], ['purchases', 'warehouse']]) {
        const { state, render } = pageHarness(t, query)
        assert.equal(state.activeTab.value, expected)
        assert.equal(findVNode(render(), node => node.type === 'v-tab' && node.props.value === 'overview'), null)
        assert.equal(findVNode(render(), node => node.type === 'v-tab' && node.props.value === 'seo'), null)
        const button = findVNode(render(), node => node.type === 'v-btn' && node.props['prepend-icon'] === 'mdi-card-text-outline')
        assert.ok(button)
        button.props.onClick()
        assert.equal(state.recordOpen.value, true)
    }
})

test('the legacy SEO deep link opens the shared SEO card and collections return to its base fields', t => {
    const { state, render } = pageHarness(t, 'seo')
    assert.equal(state.activeTab.value, 'market')
    assert.equal(state.recordOpen.value, true)
    assert.equal(state.recordInitialTab.value, 'seo')
    assert.equal(findVNode(render(), node => node.type === 'CatalogGoodRecordDialog').props['initial-tab'], 'seo')
    const operations = findVNode(render(), node => node.type === 'CatalogGoodOperations')
    assert.equal(operations.props.active, false)
    state.recordOpen.value = false
    operations.props.onRequestBasics()
    assert.equal(state.recordOpen.value, true)
    assert.equal(state.recordInitialTab.value, 'overview')
})

test('standalone warehouse loads saved units lazily and delegates their editing while keeping stock actions enabled', async t => {
    const h = pageHarness(t, 'market')
    assert.equal(h.requests.length, 0)
    const tabs = findVNode(h.render(), node => node.type === 'v-tabs')
    tabs.props['onUpdate:modelValue']('warehouse')
    await Vue.nextTick()
    assert.deepEqual(h.requests.map(request => request.url), ['good.fetch/42', 'measures.index/'])
    assert.equal(findVNode(h.render(), node => node.type === 'CatalogGoodWarehouse'), null)
    h.requests[0].resolve({ id: 42, name: 'Складской товар', denominator: 10, measurement: { measure_id: 7, unit_label: 'кг' } })
    h.requests[1].resolve([{ id: 7, name: 'кг' }])
    await new Promise(resolve => setImmediate(resolve))
    const warehouse = findVNode(h.render(), node => node.type === 'CatalogGoodWarehouse')
    assert.equal(warehouse.props.active, true)
    assert.equal(warehouse.props.disabled, false, 'Stock movement actions remain available on the standalone page')
    assert.equal(warehouse.props['good-id'], 42)
    assert.equal(warehouse.props['model-value'].measure_id, 7, 'Saved measurement id must not be mistaken for an unsaved unit change')
    assert.equal(warehouse.props.overview.name, 'Складской товар')
    assert.deepEqual(warehouse.props.options.measures, [{ id: 7, name: 'кг' }])
    assert.equal(findVNode(h.render(), node => node.type === 'CatalogGoodOperations').props.active, false)
    const edit = findVNode(warehouse.children.measurement(), node => node.type === 'v-btn' && node.props['prepend-icon'] === 'mdi-pencil-outline')
    edit.props.onClick()
    assert.equal(h.state.recordInitialTab.value, 'warehouse')
    assert.equal(h.state.recordOpen.value, true)
    assert.equal(findVNode(h.render(), node => node.type === 'CatalogGoodWarehouse').props.active, false)
    h.state.recordOpen.value = false
    h.state.selectTab('sales'); await Vue.nextTick()
    h.state.selectTab('warehouse'); await Vue.nextTick()
    assert.equal(h.requests.length, 2, 'Saved measure dictionary is reused when revisiting the warehouse')
})

test('warehouse context requests abort on leaving and stale data cannot replace another good', async t => {
    const h = pageHarness(t, 'purchases')
    assert.equal(h.requests.length, 2)
    h.state.selectTab('market'); await Vue.nextTick()
    assert.ok(h.requests.every(request => request.options.signal.aborted))
    h.requests[0].resolve({ id: 42, name: 'Устаревшее имя' })
    h.requests[1].resolve([{ id: 1 }])
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(h.state.goodData.value.name, 'Товар')
    assert.equal(h.state.warehouseReady.value, false)

    h.state.selectTab('warehouse'); await Vue.nextTick()
    h.props.good = { id: 55, name: 'Другой товар' }; await Vue.nextTick()
    assert.equal(h.requests[2].options.signal.aborted, true)
    h.requests[4].resolve({ id: 55, name: 'Другой товар', measure_id: 2 })
    h.requests[5].resolve({ data: [{ id: 2, name: 'кор.' }] })
    await new Promise(resolve => setImmediate(resolve))
    h.requests[2].resolve({ id: 42, name: 'Ещё один старый ответ' })
    h.requests[3].resolve([])
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(h.state.goodData.value.id, 55)
    assert.equal(h.state.warehouseReady.value, true)
    assert.deepEqual(h.state.warehouseOptions.value.measures, [{ id: 2, name: 'кор.' }])
})

test('navigation keeps unsaved operation and warehouse drafts until discarded, and blocks while saving', t => {
    const h = pageHarness(t, 'market')
    let operationResets = 0, warehouseResets = 0
    h.state.operations.value = { reset: () => operationResets++ }
    h.state.warehouse.value = { reset: () => warehouseResets++ }
    h.state.operationsState.value = { dirty: true, busy: false }
    h.state.selectTab('sales')
    assert.equal(h.state.activeTab.value, 'market')
    assert.equal(h.state.pendingNavigation.value.tab, 'sales')
    h.state.discardAndNavigate()
    assert.equal(h.state.activeTab.value, 'sales')
    assert.equal(operationResets, 1)
    assert.equal(warehouseResets, 1)
    h.state.warehouseState.value = { dirty: true, busy: true }
    h.state.openRecord('warehouse')
    assert.equal(h.state.recordOpen.value, false)
    assert.equal(h.state.pendingNavigation.value, null)
    let prevented = false
    const unload = { preventDefault: () => { prevented = true } }
    h.state.beforeUnload(unload)
    assert.equal(prevented, true)
    assert.equal(unload.returnValue, '')
    h.state.warehouseState.value.busy = false
    h.state.openRecord('warehouse')
    assert.equal(h.state.pendingNavigation.value.recordTab, 'warehouse')
    assert.equal(h.state.recordOpen.value, false)
    h.state.discardAndNavigate()
    assert.equal(h.state.recordOpen.value, true)
    assert.equal(h.state.recordInitialTab.value, 'warehouse')
})

test('record refresh updates the accounting page and reports a failed refresh without losing saved data', async t => {
    let failed = true
    const api = { async get() {
        if (failed) throw { response: { status: 503, data: { message: 'Не удалось обновить товар' } } }
        return { data: { id: 42, name: 'Сохранённый товар', fields: [{ id: 5 }], industries: [] } }
    } }
    const { state } = pageHarness(t, 'prices', api)
    await state.refreshAfterRecord()
    assert.equal(state.pageError.value, 'Не удалось обновить товар')
    assert.equal(state.goodData.value.id, 42)
    failed = false
    await state.refreshAfterRecord()
    assert.equal(state.pageError.value, null)
    assert.equal(state.goodData.value.name, 'Сохранённый товар')
    assert.deepEqual(state.currentFields.value.map(field => field.id), [5])
})

test('accounting page redirects to the catalog after its shared record deletes the source good', async t => {
    const { state, location } = pageHarness(t, 'prices')
    state.operations.value = { async refresh(options) {
        assert.equal(options.throwOnError, true)
        throw { response: { status: 404 } }
    } }
    await state.refreshAfterRecord()
    assert.equal(location.href, 'Ameise.products/')
})
