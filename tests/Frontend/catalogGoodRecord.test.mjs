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
    const environment = {
        ...Vue, onMounted() {}, useHead() {}, useDate: () => ({ format: value => value }),
        usePage: () => ({ url: `/Ameise/goods/42?tab=${tab}`, props: { ziggy: { location: 'https://example.test/Ameise/goods/42' } } }),
        useForm: value => Vue.reactive(value), route: (name, id) => `${name}/${id ?? ''}`, axios: api,
        ...Object.fromEntries(['VerwalterLayout', 'GoodQuotationCalculator', 'GoodSeoTab', 'GoodPriceCalculationsTab', 'GoodPriceTypesTab', 'GoodPriceTypeValuesTab', 'GoodMediaTab', 'CatalogGoodRecordDialog', 'FindBuyersLauncher'].map(name => [name, {}])),
    }
    const { definition, template } = component('resources/js/Pages/Ameise/Good.vue', environment)
    const props = { good: { id: 42 } }
    const scope = Vue.effectScope()
    const state = scope.run(() => definition.setup(props, { expose() {}, emit() {} }))
    t.after(() => scope.stop())
    state.goodData.value = { id: 42, name: 'Товар', fields: [] }
    state.pageLoading.value = false
    return { state, render: templateRenderer(template, state, props) }
}

test('good page replaces Overview with the shared record action and supports statistic deep links', t => {
    for (const [query, expected] of [['overview', 'quotations'], ['prices', 'prices'], ['media', 'media'], ['unknown', 'quotations']]) {
        const { state, render } = pageHarness(t, query)
        assert.equal(state.activeTab.value, expected)
        assert.equal(findVNode(render(), node => node.type === 'v-tab' && node.props.value === 'overview'), null)
        const button = findVNode(render(), node => node.type === 'v-btn' && node.props['prepend-icon'] === 'mdi-card-text-outline')
        assert.ok(button)
        button.props.onClick()
        assert.equal(state.recordOpen.value, true)
    }
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
