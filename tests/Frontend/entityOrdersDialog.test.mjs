import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

function dialogHarness(initialProps = {}) {
    const filename = fileURLToPath(new URL('../../resources/js/Components/Dictionaries/Entities/EntityOrdersDialog.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'entity-orders-dialog' })
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: 'entity-orders-dialog',
        compilerOptions: { bindingMetadata: compiled.bindings },
    })
    assert.deepEqual(template.errors, [])

    const requests = []
    const disposal = []
    const emitted = []
    const environment = {
        ...Vue,
        Link: {},
        route: (name, id) => `/${name}/${id}`,
        onBeforeUnmount: callback => disposal.push(callback),
        axios: {
            get(url, options) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content
        .replace(/^import .+? from ['"].*['"];?$/gm, '')
        .replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: false, entity: null, ...initialProps })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, {
        expose: () => {},
        emit: (...args) => emitted.push(args),
    }))

    return {
        api, props, requests, emitted,
        dispose() {
            disposal.forEach(callback => callback())
            scope.stop()
        },
    }
}

test('entity orders opens lazily, requests all statuses and paginates using the API metadata', async t => {
    const harness = dialogHarness({ entity: { id: 7, name: 'Client' } })
    t.after(() => harness.dispose())
    const { api, props, requests } = harness
    assert.equal(requests.length, 0)
    props.modelValue = true
    await Vue.nextTick()
    assert.equal(requests.length, 1)
    assert.equal(requests[0].url, '/api/orders')
    assert.deepEqual(requests[0].options.params, {
        entity_id: 7, page: 1, per_page: 25, sort_by: 'submitted_at', sort_direction: 'desc',
    })
    requests[0].resolve({ data: [{ id: 11, status: { code: 'cancelled' } }], meta: { current_page: 1, last_page: 2, total: 26 } })
    await Vue.nextTick()
    assert.equal(api.orders.value[0].status.code, 'cancelled')
    assert.equal(api.total.value, 26)
    assert.equal(api.lastPage.value, 2)
    const nextPage = api.loadOrders(2)
    assert.equal(requests[1].options.params.entity_id, 7)
    assert.equal(requests[1].options.params.page, 2)
    requests[1].resolve({ data: [{ id: 12 }], meta: { current_page: 2, last_page: 2, total: 26 } })
    await nextPage
    assert.equal(api.page.value, 2)
    assert.equal(api.orderUrl(api.orders.value[0]), '/Ameise.orders.show/12')
})

test('changing Entity aborts the previous request and ignores its late response', async t => {
    const harness = dialogHarness({ modelValue: true, entity: { id: 1 } })
    t.after(() => harness.dispose())
    const { api, props, requests } = harness
    props.entity = { id: 2 }
    await Vue.nextTick()
    assert.equal(requests[0].options.signal.aborted, true)
    assert.equal(requests[1].options.params.entity_id, 2)
    requests[1].resolve({ data: [{ id: 22 }], meta: { total: 1 } })
    await Vue.nextTick()
    requests[0].resolve({ data: [{ id: 11 }], meta: { total: 99 } })
    await Vue.nextTick()
    assert.deepEqual(api.orders.value, [{ id: 22 }])
    assert.equal(api.total.value, 1)
    assert.equal(api.loading.value, false)
})

test('closing cancels immediately and reopening the same Entity ignores a late failure', async t => {
    const harness = dialogHarness({ modelValue: true, entity: { id: 1 } })
    t.after(() => harness.dispose())
    const { api, props, requests, emitted } = harness
    api.updateDialog(false)
    assert.deepEqual(emitted, [['update:modelValue', false]])
    assert.equal(requests[0].options.signal.aborted, true)
    props.modelValue = false
    await Vue.nextTick()
    props.modelValue = true
    await Vue.nextTick()
    requests[0].reject(new Error('Late failure'))
    await Vue.nextTick()
    assert.equal(api.error.value, '')
    assert.equal(api.loading.value, true)
    requests[1].resolve({ data: [], meta: { total: 0 } })
    await Vue.nextTick()
    assert.equal(api.loading.value, false)
    assert.deepEqual(api.orders.value, [])
})

test('a failed page can be retried and unmount discards the retry response', async t => {
    const harness = dialogHarness({ modelValue: true, entity: { id: 1 } })
    t.after(() => harness.dispose())
    const { api, requests } = harness
    requests[0].resolve({ data: [{ id: 11 }], meta: { current_page: 1, last_page: 3, total: 60 } })
    await Vue.nextTick()
    const secondPage = api.loadOrders(2)
    requests[1].reject(new Error('Network unavailable'))
    await secondPage
    assert.match(api.error.value, /Не удалось загрузить/)
    assert.equal(api.loading.value, false)
    assert.equal(api.requestedPage.value, 2)
    const retry = api.loadOrders(api.requestedPage.value)
    assert.equal(requests[2].options.params.page, 2)
    assert.equal(api.error.value, '')
    harness.dispose()
    assert.equal(requests[2].options.signal.aborted, true)
    requests[2].resolve({ data: [{ id: 99 }], meta: { current_page: 2, total: 999 } })
    await retry
    assert.deepEqual(api.orders.value, [])
    assert.equal(api.total.value, 60)
})
