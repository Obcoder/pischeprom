import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { buildingApartmentLabel } from '../../resources/js/utils/buildingApartments.js'
import { useOrderDialogEditor } from '../../resources/js/Composables/useOrderDialogEditor.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function dialogHarness(initialProps = {}, slots = {}) {
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
        buildingApartmentLabel,
        useOrderDialogEditor,
        OrderDialogForm: { name: 'OrderDialogForm' },
        window: { confirm: () => false },
        useId: () => 'test-dialog',
        onBeforeUnmount: callback => disposal.push(callback),
        axios: {
            get(url, options) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            put(url, body) { return this.get(url, { method: 'PUT', body }) },
            patch(url, body) { return this.get(url, { method: 'PATCH', body }) },
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: false, orderId: null, editable: true, externalBusy: false, theme: 'light', ...initialProps })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose: () => {}, emit: (...args) => emitted.push(args) }))
    return {
        api, props, requests, emitted, render: templateRenderer(template, api, props, { $slots: slots }),
        dispose() { disposal.forEach(callback => callback()); scope.stop() },
    }
}

test('order addresses include the selected apartment or office number', t => {
    const harness = dialogHarness()
    t.after(() => harness.dispose())
    assert.equal(harness.api.buildingAddress({ city: { name: 'Москва' }, address: 'Ленина, 10', apartment: { type: 'office', number: '12Б' } }), 'Москва, Ленина, 10, офис 12Б')
    assert.equal(harness.api.buildingAddress({ address: 'Ленина, 10' }), 'Ленина, 10')
})

test('floating details retain the dark design and edit inside the existing dialog', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 7, theme: 'dark' })
    t.after(() => harness.dispose())
    harness.requests[0].resolve({ data: { id: 7, number: 'PP-7', buildings: [], items: [] } })
    await Vue.nextTick()
    const card = findVNode(harness.render(), node => hasClass(node, 'order-details'))
    assert.equal(card.props.theme, 'dark')
    assert.ok(hasClass(card, 'order-details--dark'))
    const edit = findVNode(harness.render(), node => hasClass(node, 'order-details__edit'))
    assert.equal(edit.props['aria-label'], 'Редактировать заказ')
    assert.equal(edit.type, 'button')
    assert.equal(edit.props.href, undefined)
    edit.props.onClick()
    assert.equal(harness.api.editor.editing, true)
    assert.equal(harness.requests[1].url, '/api/orders/options')
    assert.ok(findVNode(harness.render(), node => node.type?.name === 'OrderDialogForm'))
    harness.api.editor.cancelEdit()
    harness.props.editable = false
    assert.equal(findVNode(harness.render(), node => hasClass(node, 'order-details__edit')), null)
})

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

test('delivery date is assigned inline and emits the saved order without closing', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 7 })
    t.after(() => harness.dispose())
    const original = { id: 7, delivery_date: null, delivery_version: 'v1', permissions: { edit: true, delivery_edit: true } }
    harness.requests[0].resolve({ data: original })
    await Vue.nextTick()
    const action = findVNode(harness.render(), node => hasClass(node, 'order-details__date-edit'))
    action.props.onClick()
    assert.equal(harness.api.editor.editingDate, true)
    assert.ok(findVNode(harness.render(), node => node.props?.['aria-label'] === 'Назначение даты доставки'))
    harness.api.editor.dateDraft = '2026-10-01'
    const save = harness.api.editor.saveDate()
    assert.equal(harness.requests[1].url, '/api/orders/7/delivery-date')
    assert.deepEqual(harness.requests[1].options, { method: 'PATCH', body: { version: 'v1', delivery_date: '2026-10-01' } })
    harness.api.updateDialog(false)
    assert.deepEqual(harness.emitted, [])
    const saved = { ...original, delivery_date: '2026-10-01', delivery_version: 'v2' }
    harness.requests[1].resolve({ data: saved })
    await save
    assert.equal(harness.api.editor.editingDate, false)
    assert.equal(harness.api.order.value.delivery_date, '2026-10-01')
    assert.deepEqual(harness.emitted, [['saved', saved]])
})

test('dirty date draft survives rejected close and cancel, and permission hides the date action', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 7 })
    t.after(() => harness.dispose())
    harness.requests[0].resolve({ data: { id: 7, delivery_date: null, permissions: { edit: true, delivery_edit: false } } })
    await Vue.nextTick()
    assert.equal(findVNode(harness.render(), node => hasClass(node, 'order-details__date-edit')), null)
    harness.api.order.value.permissions.delivery_edit = true
    harness.api.editor.beginDateEdit()
    harness.api.editor.dateDraft = '2026-10-02'
    harness.api.updateDialog(false)
    harness.api.cancelEdit()
    assert.deepEqual(harness.emitted, [])
    assert.equal(harness.api.editor.dateDraft, '2026-10-02')
    assert.equal(harness.api.editor.editingDate, true)
    assert.equal(findVNode(harness.render(), node => node.type === 'v-dialog').props.persistent, true)
})

test('save failure brings validation feedback into view while preserving the draft', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 7 })
    t.after(() => harness.dispose())
    harness.requests[0].resolve({ data: { id: 7, delivery_date: null, permissions: { delivery_edit: true } } })
    await Vue.nextTick()
    let focusOptions
    harness.api.bodyElement.value = { scrollTop: 1185 }
    harness.api.saveErrorElement.value = { focus: options => { focusOptions = options } }
    harness.api.editor.beginDateEdit()
    harness.api.editor.dateDraft = '2026-10-03'
    const save = harness.api.editor.saveDate()
    harness.requests[1].reject({ response: { status: 422, data: { message: 'Проверьте дату', errors: { delivery_date: ['Дата недоступна'] } } } })
    await save
    await Vue.nextTick()
    assert.equal(harness.api.bodyElement.value.scrollTop, 0)
    assert.deepEqual(focusOptions, { preventScroll: true })
    assert.deepEqual(harness.api.validationMessages.value, ['Дата недоступна'])
    assert.equal(harness.api.editor.dateDraft, '2026-10-03')
    assert.equal(harness.api.editor.editingDate, true)
})

test('order actions receive the saved order and stay disabled during editing and sending', async t => {
    const harness = dialogHarness({ modelValue: true, orderId: 7 }, {
        actions: ({ order, disabled, editing }) => [Vue.h('button', { class: 'test-order-action', disabled, 'data-editing': editing, 'data-total': order.total_amount })],
    })
    t.after(() => harness.dispose())
    const { api, props, requests, render, emitted } = harness
    requests[0].resolve({ data: { id: 7, total_amount: 150, permissions: { edit: true, delivery_edit: true } } })
    await Vue.nextTick()
    const action = () => findVNode(render(), node => hasClass(node, 'test-order-action'))
    assert.equal(action().props.disabled, false)
    assert.equal(action().props['data-total'], 150)
    api.editor.beginDateEdit()
    assert.equal(action().props.disabled, true)
    assert.equal(action().props['data-editing'], true)
    api.editor.cancelDateEdit()
    api.editor.beginEdit()
    assert.equal(action().props.disabled, true)
    api.editor.cancelEdit()
    props.externalBusy = true
    assert.equal(action().props.disabled, true)
    assert.equal(findVNode(render(), node => hasClass(node, 'order-details__edit')).props.disabled, true)
    assert.equal(findVNode(render(), node => hasClass(node, 'order-details__date-edit')).props.disabled, true)
    api.updateDialog(false)
    assert.deepEqual(emitted, [])
    props.externalBusy = false
    api.order.value = { ...api.order.value, total_amount: 250 }
    assert.equal(action().props.disabled, false)
    assert.equal(action().props['data-total'], 250)
    api.editor.stale = true
    assert.equal(action().props.disabled, true)
})
