import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { goodTradeCodeValues } from '../../resources/js/utils/goodTradeCodes.js'

const root = new URL('../../', import.meta.url)
const stripImports = source => source.replace(/^import\s+[\s\S]*?\s+from\s+['"].*?['"];?$/gm, '')

function goodsHarness() {
    const requests = []
    const errors = []
    const environment = {
        ...Vue,
        goodTradeCodeValues,
        route: name => name,
        console: { error: error => errors.push(error) },
        axios: {
            get(url, options = {}) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
    }
    const source = stripImports(readFileSync(new URL('resources/js/Composables/useGoods.js', root), 'utf8'))
        .replace('export function useGoods', 'function useGoods')
    const factory = new Function('env', `with(env){${source};return useGoods}`)(environment)
    const state = factory()
    return { state, requests, errors, environment }
}

test('a later goods query wins even when the aborted response arrives last', async () => {
    const { state, requests } = goodsHarness()
    const first = state.indexGoods({ search: 'old', page: 1 })
    const second = state.indexGoods({ search: 'new', page: 2 })

    assert.equal(requests[0].options.signal.aborted, true)
    assert.equal(requests[1].options.signal.aborted, false)
    assert.deepEqual(requests[1].options.params, { search: 'new', page: 2 })
    requests[1].resolve({ data: [{ id: 2, name: 'New result' }], total: 120 })
    await second
    requests[0].resolve({ data: [{ id: 1, name: 'Old result' }], total: 3 })
    await first

    assert.deepEqual(state.goods.value, [{ id: 2, name: 'New result' }])
    assert.equal(state.totalItems.value, 120)
    assert.equal(state.loading.value, false)
})

for (const settlement of ['response', 'error']) {
    test(`a stale goods ${settlement} cannot clear the next query's loading or existing rows`, async () => {
        const { state, requests, errors } = goodsHarness()
        state.goods.value = [{ id: 7 }]
        state.totalItems.value = 41
        const first = state.indexGoods({ page: 1 })
        const second = state.indexGoods({ page: 2 })

        if (settlement === 'response') requests[0].resolve({ data: [{ id: 1 }], total: 1 })
        else requests[0].reject(new Error('Failure from the superseded request'))
        await first

        assert.equal(state.loading.value, true)
        assert.deepEqual(state.goods.value, [{ id: 7 }])
        assert.equal(state.totalItems.value, 41)
        assert.deepEqual(errors, [])
        requests[1].resolve([{ id: 2 }, { id: 3 }])
        await second
        assert.deepEqual(state.goods.value, [{ id: 2 }, { id: 3 }])
        assert.equal(state.totalItems.value, 2)
        assert.equal(state.loading.value, false)
    })
}

test('the current goods failure is reported and releases loading', async () => {
    const { state, requests, errors } = goodsHarness()
    state.goods.value = [{ id: 7 }]
    state.totalItems.value = 1
    const failure = new Error('Goods endpoint unavailable')
    const pending = state.indexGoods()
    requests[0].reject(failure)

    await assert.rejects(pending, error => error === failure)
    assert.equal(state.loading.value, false)
    assert.deepEqual(state.goods.value, [])
    assert.equal(state.totalItems.value, 0)
    assert.deepEqual(errors, [failure])
})

test('explicit cancellation leaves displayed rows intact and cannot finish a subsequent request', async () => {
    const { state, requests, errors } = goodsHarness()
    state.goods.value = [{ id: 7 }]
    state.totalItems.value = 1
    const cancelled = state.indexGoods()
    state.cancelGoodsRequest()
    state.cancelGoodsRequest()
    assert.equal(requests[0].options.signal.aborted, true)
    assert.equal(state.loading.value, false)

    const next = state.indexGoods({ page: 2 })
    requests[0].reject(Object.assign(new Error('Request aborted'), { code: 'ERR_CANCELED' }))
    await cancelled
    assert.equal(state.loading.value, true)
    assert.deepEqual(state.goods.value, [{ id: 7 }])
    assert.equal(state.totalItems.value, 1)
    assert.deepEqual(errors, [])
    requests[1].resolve({ data: [{ id: 8 }], total: 100 })
    await next
    assert.deepEqual(state.goods.value, [{ id: 8 }])
    assert.equal(state.totalItems.value, 100)
    assert.equal(state.loading.value, false)
})

function mountGoods(environment, state, t) {
    Object.assign(environment, {
        useGoods: () => state,
        useForm: initial => Vue.reactive(initial),
        Link: {},
        CatalogToolbar: {},
        GoodTableAvatar: {},
        GoodTradeCodeFields: {},
        GoodVatCheck: {},
        logo: '',
    })
    const filename = 'resources/js/Components/Dictionaries/Goods.vue'
    const { descriptor, errors: parseErrors } = parse(readFileSync(new URL(filename, root), 'utf8'), { filename })
    assert.deepEqual(parseErrors, [])
    const script = stripImports(compileScript(descriptor, { id: 'goods-lifecycle' }).content)
        .replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    component.render = () => null
    const renderer = Vue.createRenderer({
        createElement: type => ({ type }), createText: text => ({ text }), createComment: () => ({}),
        insert() {}, remove() {}, setText() {}, setElementText() {}, patchProp() {},
        parentNode: () => null, nextSibling: () => null,
    })
    const container = {}
    const vnode = Vue.h(component)
    renderer.render(vnode, container)
    t.after(() => renderer.render(null, container))
    return { component: vnode.component.setupState, unmount: () => renderer.render(null, container) }
}

test('unmounting the real Goods component aborts its list read and ignores a late response', async t => {
    const { state, requests, errors, environment } = goodsHarness()
    const { component, unmount } = mountGoods(environment, state, t)
    const pending = component.reloadGoods()
    assert.equal(requests.length, 1, 'loading the table does not preload editor dictionaries')
    assert.equal(requests[0].url, 'goods.index')
    assert.equal(requests[0].options.params.view, 'table')
    assert.equal(state.loading.value, true)

    unmount()
    assert.equal(requests[0].options.signal.aborted, true)
    assert.equal(state.loading.value, false)
    requests[0].resolve({ data: [{ id: 99 }], total: 99 })
    await pending
    assert.deepEqual(state.goods.value, [])
    assert.equal(state.totalItems.value, 0)
    assert.deepEqual(errors, [])
})


test('search debounces typing, aborts superseded reads immediately and flushes on Enter', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] })
    const { state, requests, environment } = goodsHarness()
    const { component } = mountGoods(environment, state, t)
    const pending = component.reloadGoods()
    component.search = 'п'
    assert.equal(requests[0].options.signal.aborted, true)
    t.mock.timers.tick(200)
    component.search = 'пе'
    t.mock.timers.tick(200)
    component.search = 'печ'
    t.mock.timers.tick(399)
    assert.equal(requests.length, 1)
    t.mock.timers.tick(1)
    assert.equal(requests.length, 2)
    assert.equal(requests[1].options.params.search, 'печ')
    requests[0].resolve({ data: [{ id: 99 }], total: 1 })
    await pending
    assert.deepEqual(state.goods.value, [])
    component.search = 'печень'
    component.applySearch()
    assert.equal(requests.length, 3)
    assert.equal(requests[2].options.params.search, 'печень')
    t.mock.timers.tick(500)
    assert.equal(requests.length, 3, 'Enter cancels the scheduled search')
    component.search = null
    assert.equal(requests.length, 4, 'clearing search is immediate')
    assert.equal(requests[3].options.params.search, null)
})

test('filters retain false values and metadata loads only once on demand', async t => {
    const { state, requests, environment } = goodsHarness()
    const { component } = mountGoods(environment, state, t)
    component.filters.has_avatar = false
    component.filters.country_id = 'none'
    await Vue.nextTick()
    assert.equal(requests[0].options.params.has_avatar, false)
    assert.equal(requests[0].options.params.country_id, 'none')
    assert.equal(component.activeFiltersCount, 2)
    const loading = component.loadDictionaries()
    assert.deepEqual(requests[1].options.params, { view: 'filters' })
    requests[1].resolve({ categories: [{ id: 1, name: 'Рыба' }], products: [], countries: [], fields: [], vat_rates: [] })
    await loading
    await component.loadDictionaries()
    assert.equal(requests.length, 2)
    assert.equal(state.categories.value[0].name, 'Рыба')
})

test('CRUD transmits nullable trade codes and CDN URLs without converting identifiers to numbers', async () => {
    const { state, environment } = goodsHarness()
    const writes = []
    environment.axios.post = async (url, body) => writes.push({ url, body })
    environment.axios.put = async (url, body) => writes.push({ url, body })
    await state.saveGood({ name: 'Печень трески', incoming_code: '001-АБ / 09', tn_ved_code: '0305200000', hs_code: '030520',
        avatar_source_url: 'https://cdn.example.com/good.jpg', avatar_thumb_source_url: 'https://cdn.example.com/good-small.jpg',
        is_published: true, products: [], fields: [] })
    assert.equal(writes[0].body.tn_ved_code, '0305200000')
    assert.equal(writes[0].body.incoming_code, '001-АБ / 09')
    assert.equal(writes[0].body.gtin, null)
    assert.equal(writes[0].body.avatar_source_url, 'https://cdn.example.com/good.jpg')
    await state.saveGood({ id: 1, name: 'Updated', ava_image: new File(['avatar'], 'avatar.jpg', { type: 'image/jpeg' }),
        incoming_code: '0000002', gtin: '00012345600012', products: [], fields: [] })
    assert.equal(writes[1].body.get('gtin'), '00012345600012')
    assert.equal(writes[1].body.get('incoming_code'), '0000002')
    assert.equal(writes[1].body.get('hs_code'), '')
    assert.equal(writes[1].body.get('products'), '')
    assert.equal(writes[1].body.get('fields'), '')
    assert.equal(writes[1].body.get('_method'), 'PUT')
    assert.equal(state.saving.value, false)
})
