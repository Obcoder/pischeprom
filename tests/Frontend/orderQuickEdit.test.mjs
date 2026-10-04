import assert from 'node:assert/strict'
import { test } from 'node:test'
import { reactive } from 'vue'
import { useOrderQuickEdit } from '../../resources/js/Composables/useOrderQuickEdit.js'

const sourceOrder = (changes = {}) => ({
    id: 7,
    number: 'PP-7',
    order_status_id: 3,
    delivery_date: '2026-10-05',
    delivery_version: 'v1',
    permissions: { edit: true, delivery_edit: true },
    ...changes,
})

function harness() {
    const requests = []
    const saved = []
    const client = Object.fromEntries(['get', 'patch'].map(method => [method, (url, data) => {
        let resolve, reject
        const promise = new Promise((success, failure) => { resolve = success; reject = failure })
        requests.push({ method, url, data, resolve: data => resolve({ data }), reject })
        return promise
    }]))
    const api = reactive(useOrderQuickEdit({ client, onSaved: order => saved.push(order) }))
    return { api, requests, saved }
}

test('date and status saves serialize each row but allow other rows, then publish complete confirmed orders', async t => {
    const h = harness()
    t.after(() => h.api.dispose())
    const firstOrder = sourceOrder()
    const secondOrder = sourceOrder({ id: 8, number: 'PP-8' })
    const dateSave = h.api.saveDate(firstOrder, '2026-10-06')
    assert.equal(h.api.saving[7], 'date')
    assert.deepEqual(h.requests[0].data, { delivery_date: '2026-10-06', version: 'v1' })
    assert.equal(h.requests[0].url, '/api/orders/7/delivery-date')
    assert.equal(h.requests[0].method, 'patch')
    assert.equal(firstOrder.delivery_date, '2026-10-05')
    assert.equal(await h.api.saveStatus(firstOrder, 4), false)
    assert.equal(await h.api.saveDate(firstOrder, ''), false)
    assert.equal(await h.api.reload(firstOrder), false)
    assert.equal(h.requests.length, 1)

    const statusSave = h.api.saveStatus(secondOrder, '4')
    assert.equal(h.api.saving[8], 'status')
    assert.deepEqual(h.requests[1].data, { order_status_id: 4, expected_order_status_id: 3 })
    assert.equal(h.requests[1].url, '/api/orders/8/status')
    assert.equal(secondOrder.order_status_id, 3)
    const dateResult = sourceOrder({ delivery_date: '2026-10-06', delivery_version: 'v2' })
    h.requests[0].resolve({ data: dateResult })
    assert.equal(await dateSave, true)
    assert.equal(h.api.saving[7], undefined)
    assert.equal(h.api.saving[8], 'status')
    assert.match(h.api.success, /PP-7.*дата доставки сохранена/)

    const statusResult = sourceOrder({ id: 8, number: 'PP-8', order_status_id: 4, status: { id: 4, name: 'Готов' } })
    h.requests[1].resolve({ data: statusResult })
    assert.equal(await statusSave, true)
    assert.deepEqual(h.saved, [dateResult, statusResult])
    assert.deepEqual(h.api.saving, {})
    assert.match(h.api.success, /PP-8.*статус изменён/)
    assert.equal(firstOrder.delivery_date, '2026-10-05')
    assert.equal(secondOrder.order_status_id, 3)
})

test('clearing the delivery date sends null and further edits use the returned version', async t => {
    const h = harness()
    t.after(() => h.api.dispose())
    const order = sourceOrder()
    assert.equal(await h.api.saveDate(order, order.delivery_date), false)
    assert.equal(await h.api.saveStatus(order, String(order.order_status_id)), false)
    assert.equal(await h.api.saveStatus(order, null), false)
    assert.equal(h.requests.length, 0)
    const clear = h.api.saveDate(order, '')
    assert.deepEqual(h.requests[0].data, { delivery_date: null, version: 'v1' })
    const updated = sourceOrder({ delivery_date: null, delivery_version: 'v2' })
    h.requests[0].resolve({ data: updated })
    await clear
    assert.equal(await h.api.saveDate(h.saved[0], ''), false)
    const next = h.api.saveDate(h.saved[0], '2026-10-07')
    assert.equal(h.requests[1].data.version, 'v2')
    h.requests[1].resolve({ data: sourceOrder({ delivery_date: '2026-10-07', delivery_version: 'v3' }) })
    await next
})

test('permissions must explicitly permit each operation and shipped orders only allow delivery-date edits', async t => {
    const h = harness()
    t.after(() => h.api.dispose())
    for (const permissions of [undefined, {}, { edit: false, delivery_edit: false }, { edit: 1, delivery_edit: 1 }]) {
        const order = sourceOrder({ permissions })
        assert.equal(await h.api.saveDate(order, ''), false)
        assert.equal(await h.api.saveStatus(order, 4), false)
    }
    assert.equal(await h.api.saveDate(sourceOrder({ permissions: { edit: true } }), ''), false)
    assert.equal(await h.api.saveStatus(sourceOrder({ permissions: { delivery_edit: true } }), 4), false)
    assert.equal(await h.api.saveStatus(sourceOrder({ shipped_at: '2026-10-04T10:00:00Z' }), 4), false)
    assert.equal(await h.api.saveStatus(sourceOrder({ shipped_sale_id: 42 }), 4), false)
    assert.equal(await h.api.saveDate(null, ''), false)
    assert.equal(await h.api.saveStatus(null, 4), false)
    assert.equal(await h.api.reload(null), false)
    assert.equal(h.requests.length, 0)

    const shipped = sourceOrder({ shipped_at: '2026-10-04T10:00:00Z', permissions: { edit: false, delivery_edit: true } })
    const pending = h.api.saveDate(shipped, '2026-10-06')
    h.requests[0].resolve({ data: { ...shipped, delivery_date: '2026-10-06' } })
    assert.equal(await pending, true)
})

test('validation and permission failures display server messages and remain retryable', async t => {
    for (const [status, data, expected] of [
        [422, { message: 'Проверьте поля.', errors: { delivery_date: ['Недопустимая дата.'] } }, 'Недопустимая дата.'],
        [403, { message: 'Недостаточно прав.' }, 'Недостаточно прав.'],
    ]) {
        const h = harness()
        t.after(() => h.api.dispose())
        const order = sourceOrder()
        const pending = h.api.saveDate(order, 'invalid')
        h.requests[0].reject({ response: { status, data } })
        assert.equal(await pending, false)
        assert.equal(h.api.errors[7], expected)
        assert.equal(h.api.stale[7], undefined)
        assert.equal(h.api.saving[7], undefined)
        assert.deepEqual(h.saved, [])

        const retry = h.api.saveDate(order, '2026-10-07')
        assert.equal(h.api.errors[7], undefined)
        h.requests[1].resolve({ data: sourceOrder({ delivery_date: '2026-10-07' }) })
        assert.equal(await retry, true)
    }
})

test('conflicts and uncertain writes block both actions until a successful reload provides fresh data', async t => {
    for (const failure of [
        { response: { status: 409 } },
        { response: { status: 503 } },
        { response: { status: 408 } },
        new Error('Connection lost'),
    ]) {
        const h = harness()
        t.after(() => h.api.dispose())
        const order = sourceOrder()
        const pending = h.api.saveStatus(order, 4)
        h.requests[0].reject(failure)
        assert.equal(await pending, false)
        assert.equal(h.api.stale[7], true)
        assert.match(h.api.errors[7], /Обновите строку/)
        assert.equal(await h.api.saveDate(order, ''), false)
        assert.equal(await h.api.saveStatus(order, 5), false)
        assert.equal(h.requests.length, 1)

        const failedReload = h.api.reload(order)
        assert.equal(h.api.saving[7], 'reload')
        assert.equal(h.requests[1].method, 'get')
        assert.equal(h.requests[1].url, '/api/orders/7')
        assert.equal(await h.api.reload(order), false)
        h.requests[1].reject(new Error('Still offline'))
        assert.equal(await failedReload, false)
        assert.equal(h.api.stale[7], true)
        assert.match(h.api.errors[7], /Не удалось обновить заказ/)
        assert.equal(await h.api.saveStatus(order, 5), false)

        const reload = h.api.reload(order)
        const fresh = sourceOrder({ order_status_id: 4, delivery_version: 'v2' })
        h.requests[2].resolve({ data: fresh })
        assert.equal(await reload, true)
        assert.equal(h.api.stale[7], undefined)
        assert.equal(h.api.errors[7], undefined)
        assert.deepEqual(h.saved, [fresh])
        assert.match(h.api.success, /PP-7.*данные обновлены/)

        const retry = h.api.saveStatus(h.saved[0], 5)
        assert.equal(h.requests[3].data.expected_order_status_id, 4)
        h.requests[3].resolve({ data: sourceOrder({ order_status_id: 5 }) })
        assert.equal(await retry, true)
    }
})

test('a malformed or wrong-order response never replaces a row and requires reloading', async t => {
    for (const data of [{}, { data: {} }, { data: sourceOrder({ id: 99 }) }]) {
        const h = harness()
        t.after(() => h.api.dispose())
        const order = sourceOrder()
        const pending = h.api.saveDate(order, '')
        h.requests[0].resolve(data)
        assert.equal(await pending, false)
        assert.equal(h.api.stale[7], true)
        assert.deepEqual(h.saved, [])

        const reload = h.api.reload(order)
        h.requests[1].resolve({ data: sourceOrder({ id: 99 }) })
        assert.equal(await reload, false)
        assert.equal(h.api.stale[7], true)
        assert.deepEqual(h.saved, [])
        assert.equal(await h.api.saveDate(order, ''), false)
    }
})

test('disposing ignores all late successes and failures and prevents further requests', async () => {
    const h = harness()
    const order = sourceOrder()
    const dateSave = h.api.saveDate(order, '')
    const statusSave = h.api.saveStatus(sourceOrder({ id: 8 }), 4)
    const reload = h.api.reload(sourceOrder({ id: 9 }))
    h.api.dispose()
    h.requests[0].resolve({ data: sourceOrder({ delivery_date: null }) })
    h.requests[1].reject(new Error('Late failure'))
    h.requests[2].resolve({ data: sourceOrder({ id: 9 }) })
    assert.deepEqual(await Promise.all([dateSave, statusSave, reload]), [false, false, false])
    assert.deepEqual(h.saved, [])
    assert.deepEqual(h.api.saving, {})
    assert.deepEqual(h.api.errors, {})
    assert.deepEqual(h.api.stale, {})
    assert.equal(h.api.success, '')
    assert.equal(await h.api.saveDate(order, ''), false)
    assert.equal(await h.api.saveStatus(order, 4), false)
    assert.equal(await h.api.reload(order), false)
    assert.equal(h.requests.length, 3)
})
