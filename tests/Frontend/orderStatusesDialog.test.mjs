import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

const status = (overrides = {}) => ({
    id: 8, name: 'На проверке', code: 'review', color: '#abcdef',
    sort_order: 40, is_closed: false, is_system: false, orders_count: 0,
    ...overrides,
})

function dialogHarness(initialProps = {}) {
    const filename = fileURLToPath(new URL('../../resources/js/Components/Orders/OrderStatusesDialog.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'order-statuses-dialog' })
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: 'order-statuses-dialog',
        compilerOptions: { bindingMetadata: compiled.bindings },
    })
    assert.deepEqual(template.errors, [])

    const requests = []
    const disposal = []
    const emitted = []
    const request = (method, url, payload, options) => new Promise((resolve, reject) => {
        requests.push({ method, url, payload, options, resolve: data => resolve({ data }), reject })
    })
    const environment = {
        ...Vue,
        onBeforeUnmount: callback => disposal.push(callback),
        axios: {
            get: (url, options) => request('get', url, undefined, options),
            post: (url, payload) => request('post', url, payload),
            put: (url, payload) => request('put', url, payload),
            delete: url => request('delete', url),
            isCancel: error => error?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content
        .replace(/^import .+? from ['"].*['"];?$/gm, '')
        .replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: false, permissions: { create: true, edit: true, delete: true }, ...initialProps })
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

test('statuses load lazily; closing aborts reads and reopening ignores stale responses', async t => {
    const harness = dialogHarness()
    t.after(() => harness.dispose())
    const { api, props, requests, emitted } = harness
    assert.equal(requests.length, 0)
    props.modelValue = true
    await Vue.nextTick()
    assert.equal(requests[0].url, '/api/order-statuses')
    api.updateDialog(false)
    assert.equal(requests[0].options.signal.aborted, true)
    assert.deepEqual(emitted, [['update:modelValue', false]])
    props.modelValue = false
    await Vue.nextTick()
    props.modelValue = true
    await Vue.nextTick()
    requests[1].resolve({ data: [status()] })
    await Vue.nextTick()
    requests[0].resolve({ data: [status({ id: 99 })] })
    await Vue.nextTick()
    assert.equal(api.statuses.value[0].id, 8)
    assert.equal(api.loading.value, false)
})

test('creation validates fields, normalizes payload and emits the refreshed list only after success', async t => {
    const harness = dialogHarness({ modelValue: true })
    t.after(() => harness.dispose())
    const { api, requests, emitted } = harness
    requests[0].resolve({ data: [status()] })
    await Vue.nextTick()
    api.createStatus()
    assert.equal(api.form.value.sort_order, 50)
    api.form.value.code = 'Недопустимый код'
    api.form.value.color = 'red'
    api.form.value.sort_order = -1
    await api.saveStatus()
    assert.equal(requests.length, 1)
    assert.deepEqual(Object.keys(api.fieldErrors.value).sort(), ['code', 'color', 'name', 'sort_order'])

    api.form.value = { name: ' Оплачен ', code: ' paid ', color: ' #123 ', sort_order: '60', is_closed: true }
    const saving = api.saveStatus()
    assert.equal(requests[1].method, 'post')
    assert.equal(requests[1].url, '/api/order-statuses')
    assert.deepEqual(requests[1].payload, { name: 'Оплачен', code: 'paid', color: '#123', sort_order: 60, is_closed: true })
    api.updateDialog(false)
    await api.saveStatus()
    assert.equal(requests.length, 2, 'a pending save cannot be submitted twice')
    assert.deepEqual(emitted, [], 'a pending mutation keeps the dialog open')
    requests[1].resolve({ data: status({ id: 9 }) })
    await Vue.nextTick()
    assert.equal(requests[2].method, 'get')
    assert.equal(api.formVisible.value, false)
    const refreshed = [status(), status({ id: 9, code: 'paid', name: 'Оплачен', is_closed: true })]
    requests[2].resolve({ data: refreshed })
    await saving
    assert.deepEqual(emitted, [['changed', refreshed]])
    assert.equal(api.saving.value, false)
})

test('editing system or in-use statuses omits immutable fields from the update', async t => {
    const harness = dialogHarness({ modelValue: true })
    t.after(() => harness.dispose())
    const { api, requests } = harness
    const system = status({ id: 1, code: 'open', name: 'Новый', is_system: true, orders_count: 10 })
    requests[0].resolve({ data: [system] })
    await Vue.nextTick()
    api.editStatus(system)
    assert.equal(api.isSystem.value, true)
    assert.equal(api.closingFlagLocked.value, true)
    api.form.value.name = 'В обработке'
    const saving = api.saveStatus()
    assert.equal(requests[1].method, 'put')
    assert.equal(requests[1].url, '/api/order-statuses/1')
    assert.deepEqual(requests[1].payload, { name: 'В обработке', color: '#abcdef', sort_order: 40 })
    requests[1].resolve({ data: system })
    await Vue.nextTick()
    requests[2].resolve({ data: [system] })
    await saving

    api.editStatus(status({ orders_count: 2 }))
    assert.equal(api.isSystem.value, false)
    assert.equal(api.closingFlagLocked.value, true)
    const usedSave = api.saveStatus()
    assert.equal(requests[3].payload.code, 'review')
    assert.equal('is_closed' in requests[3].payload, false)
    requests[3].resolve({ data: status() })
    await Vue.nextTick()
    requests[4].resolve({ data: [status()] })
    await usedSave
})

test('a server validation failure preserves the form and supports correction and retry', async t => {
    const harness = dialogHarness({ modelValue: true })
    t.after(() => harness.dispose())
    const { api, requests, emitted } = harness
    requests[0].resolve({ data: [status()] })
    await Vue.nextTick()
    api.editStatus(status())
    const firstSave = api.saveStatus()
    requests[1].reject({ response: { status: 422, data: { errors: { code: ['Такой код уже существует.'] } } } })
    await firstSave
    assert.deepEqual(api.fieldErrors.value.code, ['Такой код уже существует.'])
    assert.equal(api.form.value.name, 'На проверке')
    assert.equal(api.formVisible.value, true)
    assert.equal(api.saving.value, false)
    assert.deepEqual(emitted, [])
    api.form.value.code = 'review_new'
    const retry = api.saveStatus()
    assert.equal(api.formError.value, '')
    assert.deepEqual(api.fieldErrors.value, {})
    requests[2].resolve({ data: status({ code: 'review_new' }) })
    await Vue.nextTick()
    requests[3].resolve({ data: [status({ code: 'review_new' })] })
    await retry
    assert.equal(emitted[0][1][0].code, 'review_new')
})

test('a successful mutation followed by a read failure retries only the read and emits fresh data', async t => {
    const harness = dialogHarness({ modelValue: true })
    t.after(() => harness.dispose())
    const { api, requests, emitted } = harness
    requests[0].resolve({ data: [status()] })
    await Vue.nextTick()
    api.editStatus(status())
    const saving = api.saveStatus()
    requests[1].resolve({ data: status() })
    await Vue.nextTick()
    requests[2].reject(new Error('Network unavailable'))
    await saving
    assert.match(api.listError.value, /Изменения сохранены/)
    assert.equal(api.formVisible.value, false)
    assert.equal(api.saving.value, false)
    assert.deepEqual(emitted, [])
    const retry = api.loadStatuses()
    assert.equal(requests[3].method, 'get')
    requests[3].resolve({ data: [status({ name: 'Обновлён' })] })
    await retry
    assert.equal(api.listError.value, '')
    assert.equal(emitted[0][1][0].name, 'Обновлён')
})

test('deletion requires confirmation, protects used/system statuses and handles a server usage race', async t => {
    const harness = dialogHarness({ modelValue: true })
    t.after(() => harness.dispose())
    const { api, requests, emitted } = harness
    requests[0].resolve({ data: [status()] })
    await Vue.nextTick()
    api.requestDelete(status({ is_system: true }))
    assert.equal(api.deleteTarget.value, null)
    api.requestDelete(status({ orders_count: 1 }))
    assert.equal(api.deleteTarget.value, null)
    api.requestDelete(status())
    assert.equal(requests.length, 1, 'opening confirmation does not delete')
    api.cancelDelete()
    assert.equal(api.deleteTarget.value, null)
    api.requestDelete(status())
    const failedDelete = api.deleteStatus()
    assert.equal(requests[1].method, 'delete')
    assert.equal(requests[1].url, '/api/order-statuses/8')
    requests[1].reject({ response: { status: 422, data: { errors: { status: ['Этот статус уже назначен заказу.'] } } } })
    await failedDelete
    assert.equal(api.deleteError.value, 'Этот статус уже назначен заказу.')
    assert.equal(api.deleteTarget.value.id, 8)
    assert.deepEqual(emitted, [])
    const retry = api.deleteStatus()
    api.cancelDelete()
    assert.equal(api.deleteTarget.value.id, 8, 'pending deletion keeps its confirmation visible')
    requests[2].resolve(undefined)
    await Vue.nextTick()
    requests[3].resolve({ data: [] })
    await retry
    assert.equal(api.deleteTarget.value, null)
    assert.equal(api.deleting.value, false)
    assert.deepEqual(emitted, [['changed', []]])
})

test('a load failure can be retried and unmount cancels and ignores the result', async t => {
    const harness = dialogHarness({ modelValue: true })
    t.after(() => harness.dispose())
    const { api, requests } = harness
    requests[0].reject(new Error('Offline'))
    await Vue.nextTick()
    assert.match(api.listError.value, /Не удалось загрузить/)
    assert.equal(api.loading.value, false)
    const retry = api.loadStatuses()
    assert.equal(api.listError.value, '')
    harness.dispose()
    assert.equal(requests[1].options.signal.aborted, true)
    requests[1].resolve({ data: [status()] })
    await retry
    assert.deepEqual(api.statuses.value, [])
})

test('read-only permissions prevent mutations even when actions are called directly', async t => {
    const harness = dialogHarness({ modelValue: true, permissions: { create: false, edit: false, delete: false } })
    t.after(() => harness.dispose())
    const { api, requests } = harness
    requests[0].resolve({ data: [status()] })
    await Vue.nextTick()
    api.createStatus()
    assert.equal(api.formVisible.value, false)
    api.editStatus(status())
    assert.equal(api.formVisible.value, false)
    api.requestDelete(status())
    assert.equal(api.deleteTarget.value, null)
    await api.saveStatus()
    await api.deleteStatus()
    assert.equal(requests.length, 1)
})
