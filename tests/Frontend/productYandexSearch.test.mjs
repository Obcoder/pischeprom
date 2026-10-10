import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { safeSearchUrl, searchErrorMessage } from '../../resources/js/Components/productYandexSearch.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const flush = async () => { await Promise.resolve(); await Vue.nextTick() }
const result = { id: 1, position: 1, title: 'Поставщик рыбы', domain: 'fish.test', url: 'https://fish.test/', snippet: 'Оптовая поставка скумбрии' }
const response = (status = 'done', extra = {}) => ({ request: { id: 18, status, query: 'Скумбрия добыча', results_count: 1, ...extra }, results: status === 'done' ? [result] : [] })

function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/ProductYandexSearchCard.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const script = compileScript(descriptor, { id: 'yandex-search' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'yandex-search', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const props = Vue.reactive({ productId: 201, productName: 'Скумбрия', active: true, ...overrides })
    const requests = [], timers = new Map(), unmount = []
    let timerId = 0
    const environment = {
        ...Vue, safeSearchUrl, searchErrorMessage,
        onBeforeUnmount: callback => unmount.push(callback),
        setTimeout: callback => { timers.set(++timerId, callback); return timerId },
        clearTimeout: id => timers.delete(id),
        axios: Object.fromEntries(['get', 'post'].map(method => [method, (url, body, options) => new Promise((resolve, reject) => {
            requests.push({ method, url, body: method === 'get' ? null : body, options: method === 'get' ? body : options, resolve: data => resolve({ data }), reject })
        })])),
    }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    const dispose = () => { unmount.forEach(callback => callback()); scope.stop() }
    t.after(dispose)
    return { api, props, requests, timers, dispose, render: templateRenderer(template, api, props),
        async ready(data = response()) { requests.at(-1).resolve(data); await flush() },
        async tick() { const entry = timers.entries().next().value; assert.ok(entry); timers.delete(entry[0]); entry[1](); await flush() },
    }
}

test('inactive market panel loads nothing; activation restores the actual query, counts and results', async t => {
    const h = harness(t, { active: false })
    assert.equal(h.requests.length, 0)
    h.props.active = true
    assert.equal(h.requests[0].url, '/api/products/201/yandex-search/latest')
    await h.ready()
    assert.equal(h.api.query.value, 'Скумбрия добыча')
    assert.equal(h.api.domainCount.value, 1)
    assert.equal(h.api.canSubmit.value, true)
    assert.equal(findVNode(h.render(), node => node.type === 'v-data-table').props.items.length, 1)
    assert.equal(h.timers.size, 0)
})

test('failed search displays a human explanation and permits a corrected retry', async t => {
    const h = harness(t)
    await h.ready(response('failed', { error_code: 'yandex_search_storage_failed' }))
    assert.match(h.api.failure.value, /сохранить/)
    assert.ok(findVNode(h.render(), node => node.type === 'v-alert'))
    assert.equal(h.api.canSubmit.value, true)
    h.api.query.value = '   '
    await h.api.runSearch()
    assert.equal(h.requests.length, 1)
    assert.equal(h.api.canSubmit.value, false)
})

test('submission trims query, blocks duplicate clicks and polls the specific accepted request without overlaps', async t => {
    const h = harness(t)
    await h.ready({ request: null, results: [] })
    h.api.query.value = '  Рыба оптом  '
    h.api.maxResults.value = 30
    const submit = h.api.runSearch()
    assert.equal(h.requests.at(-1).method, 'post')
    assert.deepEqual(h.requests.at(-1).body, { query: 'Рыба оптом', max_results: 30 })
    await h.api.runSearch()
    assert.equal(h.requests.length, 2)
    h.requests.at(-1).resolve({ request_id: 19, status: 'queued', query: 'Рыба оптом' })
    await flush()
    assert.equal(h.requests.at(-1).url, '/api/products/201/yandex-search/19')
    h.requests.at(-1).resolve(response('processing', { id: 19, query: 'Рыба оптом' }))
    await submit
    assert.equal(h.api.isSubmitting.value, false)
    assert.equal(h.timers.size, 1)
    assert.equal(h.api.canSubmit.value, false)
    await h.tick()
    assert.equal(h.timers.size, 0, 'No new timer is scheduled while the previous request is outstanding')
    assert.equal(h.requests.at(-1).url, '/api/products/201/yandex-search/19')
    h.requests.at(-1).resolve(response('done', { id: 19 }))
    await flush()
    assert.equal(h.timers.size, 0)
    assert.equal(h.api.canSubmit.value, true)
})

test('hidden tabs cancel loading and polling; returning preserves unsent query edits', async t => {
    const h = harness(t)
    await h.ready(response('processing'))
    await h.tick()
    const oldPoll = h.requests.at(-1)
    h.props.active = false
    assert.equal(oldPoll.options.signal.aborted, true)
    assert.equal(h.timers.size, 0)
    h.api.query.value = 'Черновик запроса'
    h.api.queryDirty.value = true
    oldPoll.resolve(response('done'))
    await flush()
    assert.equal(h.api.results.value.length, 0)
    h.props.active = true
    await h.ready(response('done'))
    assert.equal(h.api.query.value, 'Черновик запроса')
    assert.equal(h.api.results.value.length, 1)
})

test('entity change rejects late results and unmount cancels remaining work', async t => {
    const h = harness(t)
    const stale = h.requests[0]
    h.props.productId = 202
    assert.equal(stale.options.signal.aborted, true)
    stale.resolve(response())
    await flush()
    assert.equal(h.api.request.value, null)
    assert.equal(h.api.results.value.length, 0)
    assert.equal(h.requests.at(-1).url, '/api/products/202/yandex-search/latest')
    h.dispose()
    assert.equal(h.requests.at(-1).options.signal.aborted, true)
    h.requests.at(-1).resolve(response('processing'))
    await flush()
    assert.equal(h.timers.size, 0)
    assert.equal(h.api.request.value, null)
})

test('network errors keep results visible and can be recovered with refresh', async t => {
    const h = harness(t)
    await h.ready()
    const refresh = h.api.loadResults()
    h.requests.at(-1).reject(new Error('network'))
    await refresh
    assert.match(h.api.error.value, /Обновить/)
    assert.equal(h.api.results.value.length, 1)
    assert.equal(h.api.isLoading.value, false)
    assert.equal(h.timers.size, 0)
    const retry = h.api.loadResults()
    h.requests.at(-1).resolve(response())
    await retry
    assert.equal(h.api.error.value, '')
})

test('failed submission does not erase the last successful search', async t => {
    const h = harness(t)
    await h.ready()
    const submit = h.api.runSearch()
    h.requests.at(-1).reject({ response: { status: 503 } })
    await submit
    assert.equal(h.api.isSubmitting.value, false)
    assert.match(h.api.error.value, /подтвердить запуск/)
    assert.equal(h.api.results.value.length, 1)
})

test('result filter searches all result fields and clearing it restores all rows', async t => {
    const h = harness(t)
    await h.ready()
    h.api.filter.value = 'СКУМБРИИ'
    assert.equal(h.api.filteredResults.value.length, 1)
    h.api.filter.value = 'нет совпадений'
    assert.equal(h.api.filteredResults.value.length, 0)
    h.api.filter.value = null
    assert.equal(h.api.filteredResults.value.length, 1)
    h.api.toggleSnippet(1)
    assert.equal(h.api.expanded.value.has(1), true)
    h.api.toggleSnippet(1)
    assert.equal(h.api.expanded.value.has(1), false)
})

test('known queue failures expose a readable cause and the failed request for retry', async t => {
    const h = harness(t)
    await h.ready()
    const submit = h.api.runSearch()
    h.requests.at(-1).reject({ response: { status: 503, data: { request_id: 19, status: 'failed', error_code: 'yandex_search_queue_unavailable' } } })
    await submit
    assert.equal(h.api.request.value.id, 19)
    assert.match(h.api.failure.value, /очередь задач/)
    assert.equal(h.api.error.value, '')
    assert.equal(h.api.canSubmit.value, true)
})

test('filtering does not hide navigation when results still exceed the selected page size', async t => {
    const h = harness(t)
    await h.ready({ ...response(), results: Array.from({ length: 15 }, (_, i) => ({ ...result, id: i + 1, position: i + 1 })) })
    h.api.itemsPerPage.value = 10
    h.api.filter.value = 'рыбы'
    const table = findVNode(h.render(), node => node.type === 'v-data-table')
    assert.equal(table.props['items-per-page'], 10)
    assert.equal(table.props['hide-default-footer'], false)
    h.api.itemsPerPage.value = 20
    assert.equal(findVNode(h.render(), node => node.type === 'v-data-table').props['hide-default-footer'], true)
})

test('search links accept only HTTP websites and legacy errors never expose provider messages', () => {
    assert.equal(safeSearchUrl('https://fish.test/path'), 'https://fish.test/path')
    for (const url of ['javascript:alert(1)', 'data:text/html,test', 'https://name:pass@fish.test/', '/local', null]) assert.equal(safeSearchUrl(url), null)
    assert.match(searchErrorMessage('yandex_product_search_failed_safely'), /обработать результаты/)
    assert.match(searchErrorMessage('yandex_search_http_429'), /лимит/)
    assert.match(searchErrorMessage('yandex_search_http_503'), /недоступен/)
    assert.equal(searchErrorMessage('raw secret error').includes('raw secret'), false)
})
