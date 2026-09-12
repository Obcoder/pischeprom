import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { compileScript, parse } from '@vue/compiler-sfc'
import { createRenderer, h, nextTick } from 'vue'
import axios from 'axios'

const sourceUrl = new URL('../../resources/js/Components/Avito/AvitoListings.vue', import.meta.url)
const { descriptor } = parse(await readFile(sourceUrl, 'utf8'), { filename: fileURLToPath(sourceUrl) })
let script = compileScript(descriptor, { id: 'avito-listings-integration', genDefaultAs: 'Listings' }).content
script = script.replace(/import AvitoListingGoodLink from [^\n]+/, 'const AvitoListingGoodLink = {}')
script = script.replace(/import \{ loadAllAvitoListings \} from [^\n]+/, `
import { loadAllAvitoListings as traverseListings } from '${new URL('../../resources/js/Services/avitoListingsLoader.js', import.meta.url)}'
// The loader's own tests cover rate limiting; component tests skip only its delays.
const loadAllAvitoListings = (options) => traverseListings({ ...options, wait: async () => {} })`)
script = script.replace(/from (['"])(vue|axios)\1/g, (_match, _quote, dependency) => `from '${import.meta.resolve(dependency)}'`)
script += '\nListings.render = () => null\nexport default Listings\n'
const { default: Listings } = await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)

// Mount the actual component setup and lifecycle without adding a DOM dependency.
const renderer = createRenderer({
    createElement: (type) => ({ type }), createText: (text) => ({ text }), createComment: () => ({}),
    insert() {}, remove() {}, setText() {}, setElementText() {}, patchProp() {},
    parentNode: () => null, nextSibling: () => null,
})
const flush = () => new Promise((resolve) => setImmediate(resolve))
const deferred = () => {
    let resolve
    const promise = new Promise((yes) => { resolve = yes })
    return { promise, resolve }
}
async function until(predicate) {
    const deadline = Date.now() + 3000
    while (!predicate()) {
        if (Date.now() > deadline) assert.fail('Component did not reach the expected state')
        await flush()
    }
}
const listings = (count, first = 1) => Array.from({ length: count }, (_, index) => ({
    id: first + index, title: index === 248 ? 'Unique listing beyond the first hundred' : `Listing ${first + index}`,
    status: 'active', price: 100, category: { id: 9, name: 'Food' }, address: 'Moscow',
}))
const ownStatistics = (ids) => ({ data: { statistics: { result: ids.map((itemId) => ({
    itemId, stats: [{ uniqViews: 2, uniqContacts: 1, uniqFavorites: 1 }],
})) } } })
function fixture(t, { own = listings(250), other = listings(3, 10001), interceptGet, interceptPost } = {}) {
    const calls = []
    t.mock.method(axios, 'get', async (url, options = {}) => {
        const call = { method: 'GET', url, ...options }
        calls.push(call)
        const intercepted = interceptGet?.(call)
        if (intercepted !== undefined) return intercepted
        if (url.endsWith('/context')) return { data: { account: { id: 1 } } }
        const rows = Number(options.params.account_id) === 1 ? own : other
        if (url === '/api/avito/listings') {
            const { page, per_page: size } = options.params
            return { data: { items: rows.slice((page - 1) * size, page * size), meta: { page, per_page: size } } }
        }
        return { data: { item: rows.find((item) => String(item.id) === url.split('/').at(-1)) || {} } }
    })
    t.mock.method(axios, 'post', async (url, body, options = {}) => {
        const call = { method: 'POST', url, body, ...options }
        calls.push(call)
        const intercepted = interceptPost?.(call)
        if (intercepted !== undefined) return intercepted
        if (url.endsWith('/statistics/items')) return ownStatistics(body.item_ids)
        assert.equal(url, '/api/avito/listings/statistics', 'Read-only statistics are the only allowed POST')
        const rows = Number(body.account_id) === 1 ? own : other
        return { data: { statistics: { result: {
            dataTotalCount: rows.length,
            groupings: rows.slice(body.offset, body.offset + body.limit).map((item) => ({
                id: item.id, metrics: [{ slug: 'views', value: 2 }],
            })),
        } } } }
    })
    return calls
}
function mount(t, account = 1, props = {}) {
    const errors = []
    const root = {}
    const vnode = h(Listings, {
        configured: true,
        connections: [{ id: account, external_user_id: account, is_active: true, name: `Account ${account}` }],
        onError: (message) => errors.push(message),
        ...props,
    })
    renderer.render(vnode, root)
    t.after(() => renderer.render(null, root))
    return { state: vnode.component.setupState, errors }
}

test('all 250 listings, statistics, local pagination, search and CSV use the complete collection', async (t) => {
    const calls = fixture(t)
    const { state, errors } = mount(t)
    await until(() => state.listComplete && !state.loading)
    assert.deepEqual(errors, [])
    assert.equal(state.items.length, 250)
    const listCalls = () => calls.filter((call) => call.url === '/api/avito/listings')
    assert.deepEqual(listCalls().map((call) => [call.params.page, call.params.per_page]), [[1, 100], [2, 100], [3, 100]])
    const statistics = calls.filter((call) => call.url.endsWith('/statistics/items'))
    assert.deepEqual(statistics.map((call) => call.body.item_ids.length), [200, 50])
    assert.equal(new Set(statistics.flatMap((call) => call.body.item_ids)).size, 250)
    assert.equal(state.summary.views, 500)
    assert.equal(state.visibleItems.length, 100)
    state.nextPage()
    assert.equal(state.visibleItems[0].id, 101)
    state.nextPage()
    assert.equal(state.visibleItems.length, 50)
    assert.equal(state.visibleItems[0].id, 201)
    assert.equal(listCalls().length, 3)
    state.filters.search = 'Unique listing'
    await nextTick()
    assert.deepEqual(state.visibleItems.map((item) => item.id), [249])
    assert.equal(state.page, 1)
    state.filters.search = ''
    await nextTick()

    let csvBlob
    t.mock.method(URL, 'createObjectURL', (blob) => { csvBlob = blob; return 'blob:test' })
    t.mock.method(URL, 'revokeObjectURL', () => {})
    const oldDocument = globalThis.document
    globalThis.document = { createElement: () => ({ click() {} }) }
    t.after(() => { if (oldDocument === undefined) delete globalThis.document; else globalThis.document = oldDocument })
    state.exportCsv(false)
    const csv = await csvBlob.text()
    assert.equal(csv.split('\n').length, 251)
    assert.match(csv, /"250";"Listing 250"/)

    state.dateFrom = '2026-01-01'
    await state.loadListings(false)
    assert.equal(listCalls().length, 3, 'Changing the statistics period reuses the complete list')
    assert.equal(calls.filter((call) => call.url.endsWith('/statistics/items')).at(-1).body.date_from, '2026-01-01')
})

test('a later page failure preserves the loaded prefix and reports that the list is incomplete', async (t) => {
    fixture(t, { interceptGet: (call) => {
        if (call.url === '/api/avito/listings' && call.params.page === 2) {
            return Promise.reject(Object.assign(new Error('Unavailable'), { response: { status: 503, data: { message: 'Avito unavailable' } } }))
        }
    } })
    const { state, errors } = mount(t)
    await until(() => errors.length > 0 && !state.loading)
    assert.equal(state.items.length, 100)
    assert.equal(state.listComplete, false)
    assert.match(state.inlineError, /Загружены не все объявления \(100\)/)
    assert.equal(Object.keys(state.statsByItem).length, 100)
})

test('a late old-account listing response cannot overwrite the newly selected account', async (t) => {
    const oldPage = deferred()
    const calls = fixture(t, { interceptGet: (call) => {
        if (call.url === '/api/avito/listings' && call.params.account_id === 1 && call.params.page === 2) return oldPage.promise
    } })
    const { state, errors } = mount(t)
    await until(() => calls.some((call) => call.url === '/api/avito/listings' && call.params.page === 2))
    const pendingCall = calls.find((call) => call.url === '/api/avito/listings' && call.params.page === 2)
    state.accountId = 2
    await nextTick()
    assert.equal(pendingCall.signal.aborted, true)
    await until(() => state.listComplete && !state.loading)
    assert.deepEqual(state.items.map((item) => item.id), [10001, 10002, 10003])
    oldPage.resolve({ data: { items: listings(100, 101), meta: { page: 2, per_page: 100 } } })
    await flush()
    assert.deepEqual(state.items.map((item) => item.id), [10001, 10002, 10003])
    assert.deepEqual(Object.keys(state.statsByItem), ['10001', '10002', '10003'])
    assert.deepEqual(errors, [])
})

test('a late old-account statistics response cannot replace current statistics', async (t) => {
    const oldStats = deferred()
    const calls = fixture(t, { interceptPost: (call) => {
        if (call.url.endsWith('/statistics/items') && call.body.account_id === 1) return oldStats.promise
    } })
    const { state } = mount(t)
    await until(() => calls.some((call) => call.url.endsWith('/statistics/items')))
    state.accountId = 2
    await nextTick()
    await until(() => state.listComplete && !state.loading)
    oldStats.resolve(ownStatistics(Array.from({ length: 200 }, (_, index) => index + 1)))
    await flush()
    assert.deepEqual(Object.keys(state.statsByItem), ['10001', '10002', '10003'])
    assert.equal(state.summary.views, 6)
})

test('agency statistics traverse all offsets beyond the first thousand listings', async (t) => {
    const calls = fixture(t, { other: listings(1205, 10001) })
    const { state, errors } = mount(t, 2)
    await until(() => state.listComplete && !state.loading)
    assert.equal(state.items.length, 1205)
    assert.deepEqual(calls.filter((call) => call.url === '/api/avito/listings/statistics').map((call) => call.body.offset), [0, 1000])
    assert.equal(Object.keys(state.statsByItem).length, 1205)
    assert.equal(state.summary.views, 2410)
    assert.deepEqual(errors, [])
})

test('short agency statistics pages advance by their actual row count without skipping listings', async (t) => {
    const other = listings(1205, 10001)
    const calls = fixture(t, { other, interceptPost: (call) => {
        if (call.url !== '/api/avito/listings/statistics') return
        return { data: { statistics: { result: {
            dataTotalCount: other.length,
            groupings: other.slice(call.body.offset, call.body.offset + 700).map((item) => ({
                id: item.id, metrics: [{ slug: 'views', value: 2 }],
            })),
        } } } }
    } })
    const { state, errors } = mount(t, 2)
    await until(() => state.listComplete && !state.loading)
    assert.deepEqual(calls.filter((call) => call.url === '/api/avito/listings/statistics').map((call) => call.body.offset), [0, 700])
    assert.equal(Object.keys(state.statsByItem).length, 1205)
    assert.equal(state.summary.views, 2410)
    assert.deepEqual(errors, [])
})

test('refresh updates the already selected listing detail', async (t) => {
    let revision = 1
    const calls = fixture(t, { own: listings(3), interceptGet: (call) => {
        if (call.url === '/api/avito/listings/1') return { data: { item: { id: 1, title: `Revision ${revision}`, price: revision * 100 } } }
    } })
    const { state } = mount(t)
    await until(() => state.listComplete && !state.loading && !state.detailLoading)
    assert.equal(state.detail.title, 'Revision 1')
    revision = 2
    await state.loadListings(true)
    await until(() => !state.detailLoading)
    assert.equal(state.selectedItemId, 1)
    assert.equal(state.detail.title, 'Revision 2')
    assert.equal(state.actionForm.price, 200)
    assert.equal(calls.filter((call) => call.url === '/api/avito/listings/1').length, 2)
})

test('a mocked price action updates its original listing when selection changes before its response', async (t) => {
    const action = deferred()
    const calls = fixture(t, { own: listings(3), interceptPost: (call) => {
        if (call.url === '/api/avito/listings/1/action') return action.promise
    } })
    const { state, errors } = mount(t, 1, { mutationsEnabled: true })
    await until(() => state.listComplete && !state.loading && !state.detailLoading)
    state.confirmed = true
    const pending = state.performAction('update_price', { price: 777 })
    state.selectItem(state.items[1])
    action.resolve({ data: { remote: { request_id: 'mock-only' } } })
    await pending
    assert.equal(state.items.find((item) => item.id === 1).price, 777)
    assert.equal(state.items.find((item) => item.id === 2).price, 100)
    assert.equal(state.selectedItemId, 2)
    assert.equal(state.actionResult, null)
    const actionCall = calls.find((call) => call.url.endsWith('/action'))
    assert.equal(actionCall.url, '/api/avito/listings/1/action')
    assert.equal(actionCall.body.price, 777)
    assert.deepEqual(errors, [])
})
