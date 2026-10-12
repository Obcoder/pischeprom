import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { loadCheckDictionary } from '../../resources/js/Composables/checks/checkDictionaries.js'
import { useCheckEntityLookup } from '../../resources/js/Composables/checks/useCheckEntityLookup.js'

const renderer = Vue.createRenderer({
    createElement: () => ({}), createText: () => ({}), createComment: () => ({}),
    insert() {}, remove() {}, setText() {}, setElementText() {}, patchProp() {},
    parentNode: () => null, nextSibling: () => null,
})

function mount(setup) {
    let api
    const app = renderer.createApp({ setup() { api = setup(); return () => null } })
    app.mount({})
    return { api, dispose: () => app.unmount() }
}

function queue() {
    const requests = []
    function get(url, options) {
        return new Promise((resolve, reject) => requests.push({ url, options, resolve: data => resolve({ data }), reject }))
    }
    return { requests, get }
}

function response(data, page = 1, last = page) {
    return { data, meta: { current_page: page, last_page: last } }
}

function lookup() {
    const requests = queue()
    const selectedId = Vue.ref(null)
    const component = mount(() => useCheckEntityLookup({ get: requests.get, url: () => '/api/checks/entities', selectedId }))
    return { ...component, ...requests, selectedId }
}

function checksPage(filename = 'Checks.vue', props = {}) {
    const requests = queue()
    const mutations = []
    const { descriptor, errors } = parse(readFileSync(new URL(`../../resources/js/Pages/Ameise/${filename}`, import.meta.url), 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'checks-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'checks-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const source = compiled.content.replace(/^import\s+[\s\S]*?\s+from\s+['"].*?['"];?$/gm, '').replace('export default', 'return')
    const environment = {
        ...Vue, useCheckEntityLookup, loadCheckDictionary,
        onMounted() {}, Head: {}, Link: {}, EntityFormDialog: {}, VerwalterLayout: {}, logo: '',
        route: (name, id) => id == null ? name : `${name}/${id}`,
        usePage: () => ({ props: {} }), useDate: () => ({}), useForm: fields => Vue.reactive(fields),
        useEntityApi: () => ({}),
        useEntityForm: () => ({ form: Vue.reactive({ name: '' }), resetForm() {}, toPayload: () => ({}) }),
        window: { setTimeout, clearTimeout },
        axios: {
            get: requests.get,
            patch: (url, body) => new Promise(resolve => mutations.push({ url, body, resolve })),
            post: (url, body) => new Promise(resolve => mutations.push({ url, body, resolve })),
        },
    }
    const component = new Function('env', `with(env){${source}}`)(environment)
    return { ...mount(() => component.setup(props, { expose() {} })), ...requests, mutations }
}

test('dictionary loader includes commodities after item 500 and accepts raw dictionaries', async () => {
    const pages = []
    const commodities = await loadCheckDictionary(async (url, { params }) => {
        pages.push(params.page)
        return { data: response(params.page === 1 ? Array.from({ length: 500 }, (_, index) => ({ id: index + 1 })) : [{ id: 501, name: 'Товар за пределами первой страницы' }], params.page, 2) }
    }, '/commodities', { per_page: 500 })
    assert.deepEqual(pages, [1, 2])
    assert.equal(commodities.length, 501)
    assert.equal(commodities.at(-1).id, 501)
    assert.deepEqual(await loadCheckDictionary(async () => ({ data: [{ id: 7 }] }), '/measures'), [{ id: 7 }])
})

test('dictionary loader surfaces incomplete or malformed pages instead of silently truncating them', async () => {
    await assert.rejects(loadCheckDictionary(async () => ({ data: { error: 'Unavailable' } }), '/commodities'))
    await assert.rejects(loadCheckDictionary(async () => ({ data: response([], 1, 2) }), '/commodities'))
})

test('entity search reaches the server, ignores stale responses, and preserves selected options', async t => {
    const h = lookup(); t.after(h.dispose)
    const original = h.api.refresh()
    h.api.search.value = '  Митекс  '
    assert.equal(h.requests[0].options.signal.aborted, true)
    h.requests[0].resolve(response([{ id: 1, name: 'Старый результат' }]))
    await original
    assert.deepEqual(h.api.items.value, [])
    const searched = h.api.refresh()
    assert.equal(h.requests[1].options.params.search, 'Митекс')
    h.requests[1].resolve(response([{ id: 1501, name: 'Митекс', units: [{ id: 1, name: 'Торговля' }] }]))
    await searched
    h.selectedId.value = 1501
    h.api.search.value = 'Другой'
    const other = h.api.refresh()
    h.requests[2].resolve(response([{ id: 2, name: 'Другой' }]))
    await other
    assert.equal(h.selectedId.value, 1501)
    assert.equal(h.api.items.value.find(item => item.id === 1501).name, 'Митекс')
})

test('entity pagination exposes all matches and failed requests are visible and retryable', async t => {
    const h = lookup(); t.after(h.dispose)
    let pending = h.api.refresh()
    h.requests[0].resolve(response([{ id: 1, name: 'Митекс 1' }], 1, 2))
    await pending
    assert.equal(h.api.hasMore.value, true)
    pending = h.api.loadMore()
    assert.equal(h.requests[1].options.params.page, 2)
    h.requests[1].reject(new Error('offline'))
    await pending
    assert.match(h.api.error.value, /Не удалось/)
    assert.equal(h.api.hasMore.value, true)
    pending = h.api.loadMore()
    h.requests[2].resolve(response([{ id: 2, name: 'Митекс 2' }], 2, 2))
    await pending
    assert.deepEqual(h.api.items.value.map(item => item.id), [1, 2])
    assert.equal(h.api.hasMore.value, false)
    assert.equal(h.api.error.value, '')
})

test('entity hydration keeps existing checks readable and cannot overwrite a newer selection', async t => {
    const h = lookup(); t.after(h.dispose)
    h.selectedId.value = 1501
    assert.deepEqual(h.requests[0].options.params, { ids: [1501] })
    h.selectedId.value = 1502
    assert.equal(h.requests[0].options.signal.aborted, true)
    h.requests[1].resolve(response([{ id: 1502, name: 'Новый' }]))
    await Vue.nextTick()
    h.requests[0].resolve(response([{ id: 1501, name: 'Старый' }]))
    await Vue.nextTick()
    assert.deepEqual(h.api.items.value.map(item => item.id), [1502])
    h.api.remember({ id: 2000, name: 'Только что созданный' })
    h.selectedId.value = 2000
    assert.equal(h.requests.length, 2, 'a new entity is immediately usable without another lookup')
})

test('filter and form entity searches are independent and abort on disposal', async () => {
    const filter = lookup(), form = lookup()
    filter.api.search.value = 'Поставщик'
    form.api.search.value = 'Митекс'
    const filterPending = filter.api.refresh(), formPending = form.api.refresh()
    assert.equal(filter.requests[0].options.params.search, 'Поставщик')
    assert.equal(form.requests[0].options.params.search, 'Митекс')
    filter.dispose(); form.dispose()
    assert.equal(form.requests[0].options.signal.aborted, true)
    filter.requests[0].resolve(response([{ id: 1 }]))
    form.requests[0].resolve(response([{ id: 2 }]))
    await Promise.all([filterPending, formPending])
    assert.deepEqual(filter.api.items.value, [])
    assert.deepEqual(form.api.items.value, [])
})

test('switching line kinds while editing preserves the line id, service, warehouse and expense article', async t => {
    const h = checksPage(); t.after(h.dispose)
    h.api.editServiceLine({ id: 25, service_id: 8, quantity: 2, measure_id: 1, expense_article_id: 9, price: 50 })
    await Vue.nextTick()
    assert.equal(h.api.lineForm.kind, 'service')
    assert.equal(h.api.lineForm.id, 25)
    assert.equal(h.api.lineForm.service_id, 8)
    assert.equal(h.api.lineForm.expense_article_id, 9)
    h.api.editCommodityLine({ id: 31, commodity_id: 501, warehouse_id: 5, quantity: 3, expense_article_id: 12, price: 8 })
    await Vue.nextTick()
    assert.equal(h.api.lineForm.kind, 'commodity')
    assert.equal(h.api.lineForm.id, 31)
    assert.equal(h.api.lineForm.commodity_id, 501)
    assert.equal(h.api.lineForm.warehouse_id, 5)
    assert.equal(h.api.lineForm.expense_article_id, 12)
})

test('late check details cannot replace another opened check or reappear after closing', async t => {
    const h = checksPage(); t.after(h.dispose)
    const first = h.api.openCheck({ id: 1 })
    const second = h.api.openCheck({ id: 2 })
    assert.equal(h.api.selectedCheck.value, null)
    h.requests[1].resolve({ data: { id: 2 } })
    await second
    h.requests[0].resolve({ data: { id: 1 } })
    await first
    assert.equal(h.api.selectedCheck.value.id, 2)
    const closing = h.api.openCheck({ id: 3 })
    h.api.detailDialog.value = false
    h.requests[2].resolve({ data: { id: 3 } })
    await closing
    assert.equal(h.api.selectedCheck.value, null)
})

test('a filters change invalidates the previous request before the debounce runs', async t => {
    const h = checksPage(); t.after(h.dispose)
    const first = h.api.loadChecks()
    h.api.filters.entity_id = 50
    assert.equal(h.requests[0].options.signal.aborted, true)
    h.requests[0].resolve(response([{ id: 1 }]))
    await first
    assert.deepEqual(h.api.checks.value, [])
    const second = h.api.loadChecks()
    const checkRequest = h.requests.findLast(request => request.url === 'checks.index')
    checkRequest.resolve(response([{ id: 2 }]))
    await second
    assert.deepEqual(h.api.checks.value.map(item => item.id), [2])
})

test('one failed dictionary does not hide healthy dictionaries, and can be retried', async t => {
    const h = checksPage(); t.after(h.dispose)
    const pending = h.api.loadDictionaries()
    for (const request of h.requests) {
        if (request.url === 'commodities.index') request.reject(new Error('offline'))
        else request.resolve([{ id: 7, name: request.url, is_active: true }])
    }
    await pending
    assert.deepEqual(h.api.dictionaryErrors.value, ['Товары'])
    assert.equal(h.api.services.value[0].id, 7)
    assert.equal(h.api.lineForm.warehouse_id, 7)
    const retry = h.api.loadDictionaries()
    h.requests.slice(6).forEach(request => request.resolve([{ id: 8 }]))
    await retry
    assert.deepEqual(h.api.dictionaryErrors.value, [])
    assert.equal(h.api.commodities.value[0].id, 8)
})

test('legacy Check form unwraps paginated commodities and loads the second page', async t => {
    const h = checksPage('Check.vue', { check: { id: 1, commodities: [] } }); t.after(h.dispose)
    const pending = h.api.loadDictionaries()
    h.requests[0].resolve(response([{ id: 1 }], 1, 2))
    h.requests[1].resolve([{ id: 5 }])
    await Vue.nextTick()
    h.requests[2].resolve(response([{ id: 501 }], 2, 2))
    await pending
    assert.deepEqual(h.api.commodities.value.map(item => item.id), [1, 501])
    assert.deepEqual(h.api.measures.value.map(item => item.id), [5])
})

test('opening an existing check preserves its entity even when absent from current search results', t => {
    const h = checksPage(); t.after(h.dispose)
    h.api.openEditCheck({ id: 10, entity_id: 1501, entity: { id: 1501, name: 'Митекс' }, date: '2026-10-12', amount: 30 })
    assert.equal(h.api.checkForm.entity_id, 1501)
    assert.equal(h.api.entityItems.value[0].name, 'Митекс')
    assert.equal(h.requests.length, 0)
})

test('saving a line cannot reset or reload a different check opened while the request is in flight', async t => {
    const h = checksPage(); t.after(h.dispose)
    h.api.detailDialog.value = true
    h.api.selectedCheck.value = { id: 10 }
    h.api.editServiceLine({ id: 25, service_id: 8, quantity: 2, price: 50 })
    const save = h.api.saveLine()
    assert.equal(h.mutations[0].url, 'check-services.update/25')
    const other = h.api.openCheck({ id: 11 })
    h.requests[0].resolve({ data: { id: 11 } })
    await other
    h.api.editCommodityLine({ id: 31, commodity_id: 501, quantity: 3, price: 8 })
    h.mutations[0].resolve({})
    await Vue.nextTick()
    assert.equal(h.requests.at(-1).url, 'checks.index')
    h.requests.at(-1).resolve(response([]))
    await save
    assert.equal(h.api.selectedCheck.value.id, 11)
    assert.equal(h.api.lineForm.id, 31)
    assert.equal(h.requests.filter(request => request.url.startsWith('checks.show')).length, 1)
})

test('legacy commodity form saves through JSON API and displays the returned line', async t => {
    const h = checksPage('Check.vue', { check: { id: 10, commodities: [] } }); t.after(h.dispose)
    h.api.formCommodityCheck.commodity_id = 501
    h.api.formCommodityCheck.quantity = 2
    h.api.formCommodityCheck.price = 20
    const pending = h.api.storeCommodityToCheck()
    assert.equal(h.mutations[0].url, 'checks.commodities.store/10')
    assert.equal(h.mutations[0].body.check_id, 10)
    await h.api.storeCommodityToCheck()
    assert.equal(h.mutations.length, 1, 'double submission is blocked')
    h.mutations[0].resolve({ data: { id: 11, commodity_id: 501, commodity: { id: 501, name: 'Товар' }, quantity: 2, price: 20, total_price: 40 } })
    await pending
    assert.equal(h.api.commoditiesInCheck.value[0].name, 'Товар')
    assert.equal(h.api.commoditiesInCheck.value[0].pivot.total_price, 40)
    assert.equal(h.api.formCommodityCheck.commodity_id, null)
    assert.equal(h.api.savingCommodity.value, false)
})
