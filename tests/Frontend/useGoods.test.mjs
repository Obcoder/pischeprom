import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

const root = new URL('../../', import.meta.url)
const stripImports = source => source.replace(/^import\s+[\s\S]*?\s+from\s+['"].*?['"];?$/gm, '')

function goodsHarness() {
    const requests = []
    const errors = []
    const environment = {
        ...Vue,
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

test('unmounting the real Goods component aborts its list read and ignores a late response', async t => {
    const { state, requests, errors, environment } = goodsHarness()
    Object.assign(environment, {
        useGoods: () => state,
        useForm: initial => Vue.reactive(initial),
        Link: {},
        CatalogToolbar: {},
        GoodTableAvatar: {},
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
    const pending = vnode.component.setupState.reloadGoods()
    assert.equal(requests.length, 1, 'loading the table does not preload editor dictionaries')
    assert.equal(requests[0].url, 'goods.index')
    assert.equal(requests[0].options.params.view, 'table')
    assert.equal(state.loading.value, true)

    renderer.render(null, container)
    assert.equal(requests[0].options.signal.aborted, true)
    assert.equal(state.loading.value, false)
    requests[0].resolve({ data: [{ id: 99 }], total: 99 })
    await pending
    assert.deepEqual(state.goods.value, [])
    assert.equal(state.totalItems.value, 0)
    assert.deepEqual(errors, [])
})
