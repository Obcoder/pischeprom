import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(overrides = {}) {
    const filename = fileURLToPath(new URL('../../resources/js/Components/Avito/AvitoPublications.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'avito-publication-scope' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'avito-publication-scope', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], mounted = [], disposal = []
    const request = (method, url, options) => new Promise((resolve, reject) => requests.push({ method, url, options, resolve: data => resolve({ data }), reject }))
    const env = {
        ...Vue,
        onMounted: callback => mounted.push(callback),
        onBeforeUnmount: callback => disposal.push(callback),
        console: { error() {} },
        axios: Object.fromEntries(['get', 'post', 'put'].map(method => [method, (url, options) => request(method, url, options)])),
    }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(env)
    const props = Vue.reactive({ enabled: true, mutationsEnabled: true, workspace: { ready: true, authorization_ready: true, account_id: 321, connection_id: 7 }, goodId: 42, goodName: 'Изюм', linkedListings: [], ...overrides })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    api.applyWorkspace()
    return { api, props, requests, emitted, render: templateRenderer(template, api, props), mount: () => mounted.forEach(callback => callback()), dispose() { disposal.forEach(callback => callback()); scope.stop() } }
}

const flush = async () => { await Vue.nextTick(); await Vue.nextTick(); await Vue.nextTick() }
const draft = (id = 18, goodId = 42) => ({ id, good_id: goodId, good_name: 'Изюм', status: 'draft', draft: { selected_fields: ['title', 'price'] }, good: { prices: [], media: [] } })
const list = (items = []) => ({ items, meta: { page: 1, last_page: 1, total: items.length }, status_counts: { draft: items.length }, feed: null })

test('embedded publications load only the card Good and render a compact light workspace', async t => {
    const h = harness(); t.after(h.dispose)
    const loading = h.api.loadList(true)
    assert.equal(h.requests[0].options.params.good_id, 42)
    assert.equal(h.requests[0].options.params.account_id, 321)
    h.requests[0].resolve(list([draft()])); await flush()
    assert.equal(h.requests[1].url, '/api/avito/publications/18')
    h.requests[1].resolve({ publication: draft() }); await loading
    assert.equal(h.api.publication.value.good_id, 42)
    assert.ok(findVNode(h.render(), node => hasClass(node, 'publisher--embedded')))
    assert.ok(findVNode(h.render(), node => hasClass(node, 'publication-flow')))
})

test('creating from a card locks the Good and stores only a draft without remote publication', async t => {
    const h = harness(); t.after(h.dispose)
    await h.api.openCreate()
    assert.equal(h.api.createGoodId.value, 42)
    assert.equal(h.requests.length, 0, 'The card does not search other goods')
    h.api.createGoodId.value = 99
    const create = h.api.createPublication()
    assert.deepEqual(h.requests[0].options, { account_id: 321, connection_id: 7, good_id: 42 })
    assert.equal(h.requests[0].url, '/api/avito/publications')
    await h.api.createPublication()
    assert.equal(h.requests.length, 1, 'Repeated clicks cannot create another draft')
    h.requests[0].resolve({ publication: draft() }); await flush()
    h.requests[1].resolve(list([draft()])); await flush()
    h.requests[2].resolve({ publication: draft() }); await flush()
    h.requests[3].resolve({ publication: draft() }); await create
    assert.equal(h.api.publication.value.id, 18)
    assert.equal(h.api.createDialog.value, false)
    assert.equal(h.requests.filter(item => item.method === 'post').length, 1)
    assert.ok(h.emitted.some(event => event[0] === 'changed'))
})

test('a linked listing prevents a duplicate draft only in its matching account and product', async t => {
    const h = harness({ linkedListings: [{ avito_account_id: 321, avito_item_id: 9001, good_id: 42 }] }); t.after(h.dispose)
    assert.equal(h.api.canCreate.value, false)
    await h.api.openCreate(); await h.api.createPublication()
    assert.equal(h.api.createDialog.value, false)
    assert.equal(h.requests.length, 0)
    h.props.linkedListings = [{ avito_account_id: 322, avito_item_id: 9001, good_id: 42 }]
    assert.equal(h.api.canCreate.value, true)
    h.props.linkedListings = [{ avito_account_id: 321, avito_item_id: 9001, good_id: 43 }]
    assert.equal(h.api.canCreate.value, true)
})

test('switching Good rejects stale list and detail responses before they can alter the card', async t => {
    const h = harness(); t.after(h.dispose)
    h.mount()
    const originalList = h.requests[0]
    h.props.goodId = 43; await flush()
    const nextList = h.requests[1]
    assert.equal(nextList.options.params.good_id, 43)
    nextList.resolve(list([draft(19, 43)])); await flush()
    const nextDetail = h.requests[2]
    originalList.resolve(list([draft()])); await flush()
    assert.equal(h.requests.length, 3)
    h.props.goodId = 44; await flush()
    h.requests[3].resolve(list()); await flush()
    nextDetail.resolve({ publication: draft(19, 43) }); await flush()
    assert.equal(h.api.publication.value, null)
    assert.deepEqual(h.api.items.value, [])
    assert.equal(h.api.loading.value, false)
})

test('the scoped editor refuses a publication belonging to another Good', async t => {
    const h = harness(); t.after(h.dispose)
    const loading = h.api.loadPublication(18)
    h.requests[0].resolve({ publication: draft(18, 43) }); await loading
    assert.equal(h.api.publication.value, null)
    assert.match(h.api.inlineError.value, /другому товару/)
})

test('unmount discards a pending response and global publication search remains unscoped', async t => {
    const h = harness({ goodId: null }); t.after(h.dispose)
    h.api.search.value = null
    const loading = h.api.loadList()
    assert.equal(h.requests[0].options.params.good_id, undefined)
    assert.equal(h.requests[0].options.params.search, undefined)
    h.dispose()
    h.requests[0].resolve(list([draft()])); await loading
    assert.deepEqual(h.api.items.value, [])
    assert.equal(h.requests.length, 1)
})

test('report synchronization announces the changed link without creating a new publication', async t => {
    const h = harness(); t.after(h.dispose)
    h.api.hydrate({ publication: { ...draft(), current_revision: { id: 2 } } })
    const sync = h.api.syncPublication()
    assert.equal(h.requests[0].url, '/api/avito/publications/18/sync')
    h.requests[0].resolve({ publication: { ...draft(), avito_item_id: 9001 }, avito_item_id: 9001 }); await sync
    assert.equal(h.api.publication.value.avito_item_id, 9001)
    assert.ok(h.emitted.some(event => event[0] === 'changed'))
})

test('the host receives dirty and busy state, and reset restores the last saved draft', async t => {
    const h = harness(); t.after(h.dispose)
    h.api.hydrate({ publication: draft() }); await flush()
    assert.equal(h.api.dirty.value, false)
    h.api.editor.title_override = 'Новая редакция'
    await flush()
    assert.deepEqual(h.emitted.filter(event => event[0] === 'state').at(-1)[1], { dirty: true, busy: false })
    h.api.reset(); await flush()
    assert.equal(h.api.editor.title_override, '')
    assert.equal(h.api.dirty.value, false)
    h.api.editor.title_override = 'Сохраняемая редакция'
    const save = h.api.saveDraft()
    await flush()
    assert.equal(h.api.busy.value, true)
    h.requests[0].resolve({ publication: { ...draft(), draft: { title_override: 'Сохраняемая редакция' } } }); await save; await flush()
    assert.equal(h.api.editor.title_override, 'Сохраняемая редакция')
    assert.deepEqual(h.emitted.filter(event => event[0] === 'state').at(-1)[1], { dirty: false, busy: false })
})
