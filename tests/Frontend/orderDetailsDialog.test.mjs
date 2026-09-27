import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

function dialogHarness(initialProps = {}) {
    const filename = fileURLToPath(new URL('../../resources/js/Components/Orders/OrderDetailsDialog.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'order-details-dialog' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'order-details-dialog', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])

    const requests = []
    const disposal = []
    const emitted = []
    const environment = {
        ...Vue,
        Link: {},
        route: (name, id) => `/${name}/${id}`,
        useId: () => 'test-dialog',
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
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: false, orderId: null, ...initialProps })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose: () => {}, emit: (...args) => emitted.push(args) }))
    return {
        api, props, requests, emitted,
        dispose() { disposal.forEach(callback => callback()); scope.stop() },
    }
}

test('order details loads lazily and refreshes the same order on each opening', async t => {
    const harness = dialogHarness({ orderId: 7 })
    t.after(() => harness.dispose())
    const { api, props, requests } = harness
    assert.equal(requests.length, 0)
    props.modelValue = true
    await Vue.nextTick()
    assert.equal(requests[0].url, '/api/orders/7')
    const fullOrder = { id: 7, number: 'ORDER-7', status: { name: 'В работе' }, items: [{ id: 20, good_name: 'Мука' }], internal_comment: 'После обеда' }
    requests[0].resolve({ data: fullOrder })
    await Vue.nextTick()
    assert.deepEqual(api.order.value, fullOrder)
    assert.equal(api.orderNumber.value, 'ORDER-7')
    assert.equal(api.orderUrl(), '/Ameise.orders.show/7')
    props.modelValue = false
    await Vue.nextTick()
    assert.equal(api.order.value, null)
    props.modelValue = true
    await Vue.nextTick()
    assert.equal(requests.length, 2)
    requests[1].resolve({ data: { ...fullOrder, status: { name: 'Закрыт' } } })
    await Vue.nextTick()
    assert.equal(api.order.value.status.name, 'Закрыт')
})

test('switching orders aborts the previous request and discards its late response', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 1 })
    t.after(() => harness.dispose())
    const { api, props, requests } = harness
    props.orderId = 2
    await Vue.nextTick()
    assert.equal(requests[0].options.signal.aborted, true)
    assert.equal(requests[1].url, '/api/orders/2')
    assert.equal(api.orderNumber.value, '#2')
    requests[1].resolve({ data: { id: 2, number: 'Second' } })
    await Vue.nextTick()
    requests[0].resolve({ data: { id: 1, number: 'First' } })
    await Vue.nextTick()
    assert.equal(api.order.value.id, 2)
    assert.equal(api.loading.value, false)
})

test('closing cancels immediately and a late failure cannot overwrite a reopened dialog', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 1 })
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
    requests[1].resolve({ data: { id: 1 } })
    await Vue.nextTick()
    assert.equal(api.order.value.id, 1)
    assert.equal(api.loading.value, false)
})

test('failed loading can be retried and unmount discards a late retry response', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 1 })
    t.after(() => harness.dispose())
    const { api, requests } = harness
    requests[0].reject(new Error('Network failure'))
    await Vue.nextTick()
    assert.match(api.error.value, /Не удалось загрузить/)
    assert.equal(api.loading.value, false)
    const retry = api.loadOrder()
    assert.equal(requests[1].url, '/api/orders/1')
    assert.equal(api.error.value, '')
    harness.dispose()
    assert.equal(requests[1].options.signal.aborted, true)
    requests[1].resolve({ data: { id: 1 } })
    await retry
    assert.equal(api.order.value, null)
})

test('a missing order reports an actionable error and an invalid payload is never displayed', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 1 })
    t.after(() => harness.dispose())
    const { api, requests } = harness
    requests[0].reject({ response: { status: 404 } })
    await Vue.nextTick()
    assert.match(api.error.value, /Заказ не найден/)
    const retry = api.loadOrder()
    requests[1].resolve({ data: null })
    await retry
    assert.match(api.error.value, /Не удалось загрузить/)
    assert.equal(api.order.value, null)
    assert.equal(api.loading.value, false)
})
