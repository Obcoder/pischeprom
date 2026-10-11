import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { useOrderDialogEditor } from '../../resources/js/Composables/useOrderDialogEditor.js'

function harness() {
    const filename = fileURLToPath(new URL('../../resources/js/Components/Orders/OrderCreateDialog.vue', import.meta.url))
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'order-create-dialog' })
    const requests = [], emitted = [], disposal = []
    const request = (method, url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve, reject }))
    const client = {
        get: (url, options) => request('GET', url, options),
        post: (url, body) => request('POST', url, body),
    }
    const environment = {
        ...Vue,
        OrderDialogForm: {},
        useOrderDialogEditor: options => useOrderDialogEditor({ ...options, client }),
        useId: () => 'create-test',
        window: { confirm: () => false },
        onBeforeUnmount: callback => disposal.push(callback),
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: false, canCreateDelivery: true })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose: () => {}, emit: (...args) => emitted.push(args) }))
    return { api, props, requests, emitted, dispose() { disposal.forEach(callback => callback()); scope.stop() } }
}

const options = {
    statuses: [{ id: 1, code: 'open', name: 'Новый' }],
    entities: [{ id: 4, name: 'Покупатель' }],
    goods: [{ id: 7, name: 'Товар', measurement: { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 } }],
    currency_codes: ['RUB', 'USD'],
}

test('creation opens lazily, saves through POST once and closes after the confirmed response', async t => {
    const context = harness()
    t.after(() => context.dispose())
    const { api, props, requests, emitted } = context
    assert.equal(requests.length, 0)
    props.modelValue = true
    await Vue.nextTick()
    assert.equal(requests[0].url, '/api/orders/options')
    requests[0].resolve({ data: options })
    await Vue.nextTick()
    api.editor.form.entity_id = 4
    const item = api.editor.form.items[0]
    item.good_id = 7
    api.editor.selectItemGood(item)
    item.unit_price = 25
    const pending = api.editor.saveOrder()
    api.editor.saveOrder()
    api.close()
    assert.equal(requests.length, 2)
    assert.equal(requests[1].method, 'POST')
    assert.equal(requests[1].url, '/api/orders')
    assert.equal(requests[1].body.items[0].currency_code, 'RUB')
    assert.deepEqual(emitted, [])
    requests[1].resolve({ data: { data: { id: 88, number: 'TEST-88', items: [] } } })
    await pending
    assert.deepEqual(emitted.map(event => event[0]), ['saved', 'update:modelValue'])
    assert.equal(emitted[1][1], false)
    props.modelValue = false
    await Vue.nextTick()
    props.modelValue = true
    await Vue.nextTick()
    assert.equal(api.editor.form.entity_id, null)
    assert.equal(api.editor.form.items.length, 1)
    assert.equal(api.editor.form.items[0].currency_code, 'RUB')
})

test('failed creation keeps the draft and focuses server validation feedback', async t => {
    const context = harness()
    t.after(() => context.dispose())
    const { api, props, requests, emitted } = context
    props.modelValue = true
    await Vue.nextTick()
    requests[0].resolve({ data: options })
    await Vue.nextTick()
    api.editor.form.entity_id = 4
    let focused = false
    api.bodyElement.value = { scrollTop: 600 }
    api.errorElement.value = { focus: () => { focused = true } }
    const pending = api.editor.saveOrder()
    requests[1].reject({ response: { status: 422, data: { message: 'Проверьте заказ', errors: { 'items.0.good_id': ['Выберите товар'] } } } })
    await pending
    await Vue.nextTick()
    assert.equal(api.editor.form.entity_id, 4)
    assert.equal(api.bodyElement.value.scrollTop, 0)
    assert.equal(focused, true)
    assert.deepEqual(api.validationMessages.value, ['Выберите товар'])
    api.close()
    assert.deepEqual(emitted, [])
})
