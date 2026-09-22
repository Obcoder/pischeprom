import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

const record = (id, summary = 'Сведения о компании') => ({ id, url: 'https://supplier.example.test/catalog', researched_at: '2026-09-22T09:00:00Z', saved_at: '2026-09-22T10:00:00Z', result: { summary, products: [{ name: 'Желатин пищевой' }], pages: [], warnings: [], partial: false } })
const response = (data, page = 1, last = 1, total = data.length) => ({ data, meta: { current_page: page, last_page: last, total }, links: { next: 'https://untrusted.example.test/not-used' } })

function harness(t) {
    const filename = 'resources/js/Components/Unit/UnitWebsiteResearchCard.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'unit-website-research-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'unit-website-research-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], disposal = []
    const environment = {
        ...Vue,
        BaseSectionCard: {},
        onBeforeUnmount: callback => disposal.push(callback),
        axios: {
            get(url, options) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            post() { throw Error('Reading saved research must not start a scan or modify data') },
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ unitId: 17 })
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit() {} }))
    t.after(() => { disposal.forEach(callback => callback()); scope.stop() })
    return { state, props, requests, async settle() { await Vue.nextTick(); await Promise.resolve(); await Vue.nextTick() } }
}

test('mount and refresh only request saved snapshots from the current Unit', async t => {
    const { state, requests, settle } = harness(t)
    assert.equal(requests.length, 1)
    assert.equal(requests[0].url, '/api/units/17/website-research')
    assert.deepEqual(requests[0].options.params, { page: 1 })
    requests[0].resolve(response([record(1)]))
    await settle()
    assert.equal(state.records.value[0].id, 1)
    assert.equal(state.loading.value, false)
    const refresh = state.load(1)
    assert.equal(requests.length, 2)
    assert.equal(requests[1].url, requests[0].url)
    requests[1].resolve(response([record(2)]))
    await refresh
    assert.equal(state.records.value[0].id, 2)
})

test('changing Units cancels previous work and a late response cannot expose the old Unit result', async t => {
    const { state, props, requests, settle } = harness(t)
    props.unitId = 28
    await settle()
    assert.equal(requests[0].options.signal.aborted, true)
    assert.equal(requests[1].url, '/api/units/28/website-research')
    requests[0].resolve(response([record(1, 'Private previous Unit')]))
    await settle()
    assert.deepEqual(state.records.value, [])
    assert.equal(state.loading.value, true)
    requests[1].resolve(response([record(2, 'Current Unit')]))
    await settle()
    assert.equal(state.records.value[0].result.summary, 'Current Unit')
    assert.equal(state.loading.value, false)
})

test('pagination uses the scoped API instead of response links and retries the requested page', async t => {
    const { state, requests, settle } = harness(t)
    requests[0].resolve(response([record(1)], 1, 3, 22))
    await settle()
    state.toggle(1)
    const next = state.load(2)
    assert.equal(requests[1].url, '/api/units/17/website-research')
    assert.equal(requests[1].options.params.page, 2)
    requests[1].reject({ response: { data: { message: 'Временная ошибка' } } })
    await next
    assert.equal(state.currentPage.value, 1)
    assert.equal(state.records.value[0].id, 1)
    assert.equal(state.error.value, 'Временная ошибка')
    const retry = state.retry()
    assert.equal(requests[2].options.params.page, 2)
    requests[2].resolve(response([record(11)], 2, 3, 22))
    await retry
    assert.equal(state.currentPage.value, 2)
    assert.equal(state.hasPrevious.value, true)
    assert.equal(state.hasNext.value, true)
    assert.equal(state.expanded.value.size, 0)
})

test('safe links reject active schemes, credentials, control characters and relative URLs', t => {
    const { state } = harness(t)
    assert.equal(state.safeLink('https://example.test/catalog?a=1&b=2'), 'https://example.test/catalog?a=1&b=2')
    assert.equal(state.safeLink('http://example.test'), 'http://example.test/')
    for (const value of ['javascript:alert(1)', 'data:text/html,hello', '//example.test', '/internal', 'https://name:password@example.test', 'https://example.test\\@other.test', 'https://example.test/\nfoo', null]) {
        assert.equal(state.safeLink(value), null, String(value))
    }
    assert.equal(state.siteLabel('https://www.example.test/catalog'), 'example.test')
})

test('empty and forbidden results leave a clear recoverable state', async t => {
    const { state, requests, settle } = harness(t)
    requests[0].resolve(response([]))
    await settle()
    assert.equal(state.records.value.length, 0)
    assert.equal(state.total.value, 0)
    assert.equal(state.error.value, '')
    const refresh = state.load()
    requests[1].reject({ response: { status: 403 } })
    await refresh
    assert.equal(state.error.value, 'Нет доступа к исследованиям этого Unit.')
    assert.equal(state.loading.value, false)
})
