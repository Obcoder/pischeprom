import assert from 'node:assert/strict'
import { test } from 'node:test'
import { effectScope, reactive, ref } from 'vue'
import { useOrderDialogEditor } from '../../resources/js/Composables/useOrderDialogEditor.js'

const sourceOrder = (changes = {}) => ({
    id: 7,
    number: 'PP-7',
    entity_id: 11,
    order_status_id: 3,
    currency_code: 'RUB',
    submitted_at: '2026-09-28T10:00:00Z',
    delivery_date: '2026-09-30',
    delivery_version: 'v1',
    permissions: { edit: true, delivery_edit: true },
    buildings: [
        { id: 2, apartment: { id: 21, number: '12Б', type: 'office' } },
        { id: 3, apartment_id: 32 },
    ],
    items: [{ id: 91, good_id: 4, quantity: '3.000', unit_price: null, price_gross: '120.00', denominator: '2.5', measure_id: 2, measurement: { measure_id: 2, unit_label: 'коробка', kilograms_per_unit: 2.5 } }],
    ...changes,
})

test('quantity uses the explicit unit and preserved order snapshot rather than package size', async t => {
    const kg = { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 }
    const box = { measure_id: 2, unit_label: 'коробка', kilograms_per_unit: 10 }
    const h = harness(sourceOrder({ items: [{ id: 91, good_id: 4, quantity: 10, unit_price: 120, denominator: 10, measure_id: 1, measurement: kg }] }))
    t.after(() => h.dispose())
    await h.edit({ goods: [{ id: 4, denominator: 10, measurement: box }, { id: 5, denominator: 20, measurement: box }] })
    assert.equal(h.api.weight.value, 10)
    assert.equal(h.api.itemUnit(h.api.form.items[0]), 'кг')
    assert.equal(h.api.dirty.value, false)
    h.api.form.items[0].good_id = 5
    h.api.selectItemGood(h.api.form.items[0])
    assert.equal(h.api.form.items[0].unit_price, null)
    assert.equal(h.api.weight.value, 100)
    assert.equal(h.api.itemUnit(h.api.form.items[0]), 'коробка')
    const save = h.api.saveOrder()
    assert.deepEqual(h.requests.at(-1).data.items[0].measurement, box)
    assert.equal(h.requests.at(-1).data.items[0].measure_id, 2)
    assert.equal(h.requests.at(-1).data.items[0].id, null)
    h.requests.at(-1).resolve({ data: sourceOrder() })
    await save
})

test('unconfigured goods and unknown unit mass never infer weight from package size', async t => {
    const h = harness(sourceOrder({ items: [] }))
    t.after(() => h.dispose())
    await h.edit({ goods: [{ id: 4, denominator: 10 }, { id: 5, denominator: 20, measurement: { measure_id: 2, unit_label: 'шт.', kilograms_per_unit: null } }] })
    h.api.addItem()
    h.api.form.items[0].good_id = 4
    h.api.form.items[0].quantity = 10.5
    assert.equal(h.api.weight.value, null)
    assert.equal(h.api.itemUnit(h.api.form.items[0]), 'единица не задана')
    h.api.form.items[0].good_id = 5
    assert.equal(h.api.weight.value, null)
    assert.equal(h.api.itemUnit(h.api.form.items[0]), 'шт.')
})

function harness(initial = sourceOrder(), initialProps = {}) {
    const order = ref(initial)
    const props = reactive({ orderId: initial.id, visible: true, editable: true, ...initialProps })
    const requests = []
    const saved = []
    const client = Object.fromEntries(['get', 'put', 'patch'].map(method => [method, (url, data) => {
        let resolve, reject
        const promise = new Promise((success, failure) => { resolve = success; reject = failure })
        requests.push({ method, url, data, resolve: data => resolve({ data }), reject })
        return promise
    }]))
    const scope = effectScope()
    const api = scope.run(() => useOrderDialogEditor({
        order,
        orderId: () => props.orderId,
        visible: () => props.visible,
        editable: () => props.editable,
        onSaved: data => saved.push(data),
        client,
    }))
    return {
        api, order, props, requests, saved,
        async edit(options = {}) {
            const pending = api.beginEdit()
            requests.at(-1).resolve({ goods: [{ id: 4, name: 'Мука', denominator: '2.5' }], ...options })
            await pending
        },
        dispose() { api.dispose(); scope.stop() },
    }
}

test('editing preserves selected premises, explicit null prices and stable item keys; save replaces details locally', async t => {
    const h = harness()
    t.after(() => h.dispose())
    assert.equal(h.requests.length, 0)
    await h.edit()
    assert.equal(h.requests[0].url, '/api/orders/options')
    assert.equal(h.api.dirty.value, false)
    assert.deepEqual(h.api.form.building_apartments, { 2: 21, 3: 32 })
    assert.equal(h.api.form.items[0].unit_price, null)
    const firstKey = h.api.form.items[0]._key
    h.api.addItem()
    const extraKey = h.api.form.items[1]._key
    assert.notEqual(extraKey, firstKey)
    h.api.removeItem(1)
    assert.equal(h.api.form.items[0]._key, firstKey)
    h.api.form.number = 'PP-7/2'
    h.api.form.building_ids = [2]
    h.api.form.internal_comment = 'Служебный вход'
    h.api.form.items[0].unit_price = ''
    assert.equal(h.api.weight.value, 7.5)
    assert.equal(h.api.total.value, 0)
    assert.equal(h.api.dirty.value, true)

    const pending = h.api.saveOrder()
    const write = h.requests[1]
    assert.equal(write.method, 'put')
    assert.equal(write.url, '/api/orders/7')
    assert.deepEqual(write.data.building_ids, [2])
    assert.deepEqual(write.data.building_apartments, { 2: 21 })
    assert.deepEqual(write.data.items, [{ id: 91, good_id: 4, measure_id: 2, measurement: { measure_id: 2, unit_label: 'коробка', kilograms_per_unit: 2.5 }, quantity: '3.000', unit_price: null }])
    assert.equal(Object.hasOwn(write.data, 'contact_telephone_id'), false)
    assert.equal(write.data.delivery_version, 'v1')
    assert.equal(h.api.cancelEdit(), false)
    await h.api.saveOrder()
    await h.api.saveDate()
    assert.equal(h.requests.length, 2)

    const updated = sourceOrder({ number: 'PP-7/2', internal_comment: 'Служебный вход', delivery_version: 'v2' })
    write.resolve({ data: updated })
    await pending
    assert.deepEqual(h.order.value, updated)
    assert.deepEqual(h.saved, [updated])
    assert.equal(h.api.editing.value, false)
    assert.equal(h.api.saving.value, false)
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.success.value, 'Заказ сохранён.')
})

test('full form saves a date-only change using the versioned PATCH endpoint', async t => {
    const h = harness()
    t.after(() => h.dispose())
    await h.edit()
    h.api.form.delivery_date = '2026-10-01'
    const pending = h.api.saveOrder()
    assert.deepEqual(h.requests[1].data, { version: 'v1', delivery_date: '2026-10-01' })
    assert.equal(h.requests[1].method, 'patch')
    assert.equal(h.requests[1].url, '/api/orders/7/delivery-date')
    assert.equal(h.api.savingDate.value, true)
    assert.equal(h.api.saving.value, false)
    h.requests[1].resolve({ data: sourceOrder({ delivery_date: '2026-10-01', delivery_version: 'v2', status: { code: 'prepared' } }) })
    await pending
    assert.equal(h.order.value.status.code, 'prepared')
    assert.equal(h.api.editing.value, false)
    assert.equal(h.api.dateDirty.value, false)
    assert.equal(h.api.success.value, 'Дата доставки сохранена.')
})

test('saving unrelated fields preserves the exact timestamp; edited local time is sent with its UTC offset', async t => {
    const originalTimezone = process.env.TZ
    process.env.TZ = 'Europe/Moscow'
    t.after(() => {
        if (originalTimezone === undefined) delete process.env.TZ
        else process.env.TZ = originalTimezone
    })
    const submittedAt = '2026-09-28T10:00:37.123Z'
    const h = harness(sourceOrder({ submitted_at: submittedAt }))
    t.after(() => h.dispose())
    await h.edit()
    assert.equal(h.api.form.submitted_at, '2026-09-28T13:00')
    assert.equal(h.api.dirty.value, false)
    h.api.form.submitted_at = '2026-09-28T13:45'
    assert.equal(h.api.dirty.value, true)
    h.api.form.submitted_at = '2026-09-28T13:00'
    assert.equal(h.api.dirty.value, false)
    h.api.form.internal_comment = 'Изменился только комментарий'
    const pending = h.api.saveOrder()
    assert.equal(h.requests[1].data.submitted_at, submittedAt)
    h.requests[1].resolve({ data: sourceOrder({ submitted_at: submittedAt, internal_comment: 'Изменился только комментарий' }) })
    await pending

    await h.edit()
    h.api.form.submitted_at = '2026-09-28T13:45'
    const changed = h.api.saveOrder()
    assert.equal(h.requests[3].data.submitted_at, '2026-09-28T10:45:00.000Z')
    h.requests[3].resolve({ data: sourceOrder({ submitted_at: '2026-09-28T10:45:00.000Z' }) })
    await changed
    assert.equal(h.api.form.submitted_at, '2026-09-28T13:45')
    assert.equal(h.api.dirty.value, false)
})

test('an absent or cleared timestamp remains null in the order payload', async t => {
    for (const submittedAt of [null, '2026-09-28T10:00:00Z']) {
        const h = harness(sourceOrder({ submitted_at: submittedAt }))
        t.after(() => h.dispose())
        await h.edit()
        h.api.form.submitted_at = ''
        h.api.form.number = 'Changed'
        const pending = h.api.saveOrder()
        assert.equal(h.requests[1].data.submitted_at, null)
        h.requests[1].resolve({ data: sourceOrder({ number: 'Changed', submitted_at: submittedAt }) })
        await pending
    }
})

test('quick date editing works for shipped orders, clears the date and uses the fresh version for another edit', async t => {
    const h = harness(sourceOrder({ shipped_at: '2026-09-28T10:00:00Z', permissions: { edit: false, delivery_edit: true } }))
    t.after(() => h.dispose())
    assert.equal(h.api.canEdit.value, false)
    assert.equal(h.api.canEditDelivery.value, true)
    await h.api.beginEdit()
    assert.equal(h.requests.length, 0)
    h.api.beginDateEdit()
    assert.equal(h.api.dateDraft.value, '2026-09-30')
    h.api.dateDraft.value = ''
    const pending = h.api.saveDate()
    assert.deepEqual(h.requests[0].data, { version: 'v1', delivery_date: null })
    assert.equal(h.api.cancelDateEdit(), false)
    h.requests[0].resolve({ data: sourceOrder({ delivery_date: null, delivery_version: 'v2', shipped_at: '2026-09-28T10:00:00Z' }) })
    await pending
    assert.equal(h.api.editingDate.value, false)
    assert.equal(h.api.savingDate.value, false)
    h.api.beginDateEdit()
    h.api.dateDraft.value = '2026-10-02'
    const another = h.api.saveDate()
    assert.equal(h.requests[1].data.version, 'v2')
    h.requests[1].resolve({ data: sourceOrder({ delivery_date: '2026-10-02', delivery_version: 'v3' }) })
    await another
})

test('permission restrictions hide editors and never send delivery fields without server permission', async t => {
    const h = harness(sourceOrder({ permissions: { edit: true, delivery_edit: false } }))
    t.after(() => h.dispose())
    h.api.beginDateEdit()
    assert.equal(h.api.editingDate.value, false)
    await h.edit()
    h.api.form.number = 'Changed'
    h.api.form.delivery_date = '2027-01-01'
    const pending = h.api.saveOrder()
    assert.equal(Object.hasOwn(h.requests[1].data, 'delivery_date'), false)
    assert.equal(Object.hasOwn(h.requests[1].data, 'delivery_version'), false)
    h.requests[1].resolve({ data: sourceOrder({ number: 'Changed', permissions: { edit: false, delivery_edit: true } }) })
    await pending
    assert.equal(h.api.canEdit.value, false)
    h.props.editable = false
    assert.equal(h.api.canEditDelivery.value, false)
    h.order.value = sourceOrder({ permissions: undefined })
    h.props.editable = true
    assert.equal(h.api.canEdit.value, true)
    assert.equal(h.api.canEditDelivery.value, false)
})

test('validation errors retain full editor values and allow corrected retry', async t => {
    const h = harness()
    t.after(() => h.dispose())
    await h.edit()
    h.api.form.number = 'Duplicate'
    h.api.form.items[0].quantity = 0
    const key = h.api.form.items[0]._key
    const pending = h.api.saveOrder()
    const errors = { number: ['Номер уже занят.'], 'items.0.quantity': ['Количество должно быть больше нуля.'] }
    h.requests[1].reject({ response: { status: 422, data: { message: 'Проверьте поля.', errors } } })
    await pending
    assert.equal(h.api.form.number, 'Duplicate')
    assert.equal(h.api.form.items[0]._key, key)
    assert.equal(h.api.editing.value, true)
    assert.equal(h.api.stale.value, false)
    assert.deepEqual(h.api.errors.value, errors)
    h.api.form.number = 'Valid'
    h.api.form.items[0].quantity = 2
    const retry = h.api.saveOrder()
    assert.equal(h.requests[2].data.number, 'Valid')
    h.requests[2].resolve({ data: sourceOrder({ number: 'Valid' }) })
    await retry
    assert.deepEqual(h.api.errors.value, {})
    assert.equal(h.api.error.value, '')
})

test('date validation retains the date draft and successful retry exits its editor', async t => {
    const h = harness()
    t.after(() => h.dispose())
    h.api.beginDateEdit()
    h.api.dateDraft.value = '2026-10-04'
    const pending = h.api.saveDate()
    h.requests[0].reject({ response: { status: 422, data: { errors: { delivery_date: ['Недопустимая дата.'] } } } })
    await pending
    assert.equal(h.api.dateDraft.value, '2026-10-04')
    assert.equal(h.api.editingDate.value, true)
    assert.equal(h.api.stale.value, false)
    const retry = h.api.saveDate()
    h.requests[1].resolve({ data: sourceOrder({ delivery_date: '2026-10-04' }) })
    await retry
    assert.equal(h.api.editingDate.value, false)
})

test('conflicts and uncertain writes block both editors until explicitly reloaded', async t => {
    for (const failure of [
        { response: { status: 409, data: { message: 'Conflict' } } },
        { response: { status: 503 } },
        new Error('Connection lost'),
    ]) {
        const h = harness()
        t.after(() => h.dispose())
        await h.edit()
        h.api.form.number = 'Draft'
        const pending = h.api.saveOrder()
        h.requests[1].reject(failure)
        await pending
        assert.equal(h.api.stale.value, true)
        assert.equal(h.api.form.number, 'Draft')
        assert.equal(h.api.saving.value, false)
        await h.api.saveOrder()
        h.api.cancelEdit()
        h.api.beginDateEdit()
        await h.api.beginEdit()
        assert.equal(h.requests.length, 2)
        assert.equal(h.api.editingDate.value, false)
        assert.match(h.api.error.value, /Обновите карточку/)
        h.api.reset()
        h.order.value = sourceOrder({ delivery_version: 'reloaded' })
        h.api.beginDateEdit()
        assert.equal(h.api.stale.value, false)
        assert.equal(h.api.editingDate.value, true)
    }
})

test('a date conflict keeps its draft and cannot be bypassed with a full order save', async t => {
    const h = harness()
    t.after(() => h.dispose())
    h.api.beginDateEdit()
    h.api.dateDraft.value = '2026-10-05'
    const pending = h.api.saveDate()
    h.requests[0].reject({ response: { status: 409, data: { message: 'Conflict' } } })
    await pending
    assert.equal(h.api.dateDraft.value, '2026-10-05')
    assert.equal(h.api.stale.value, true)
    assert.equal(h.api.savingDate.value, false)
    await h.api.saveDate()
    h.api.cancelDateEdit()
    await h.api.beginEdit()
    assert.equal(h.requests.length, 1)
    assert.equal(h.api.editing.value, false)
    assert.equal(h.saved.length, 0)
})

test('switching orders or closing discards late saves without notifying or replacing a new order', async t => {
    for (const action of ['switch', 'close', 'dispose']) {
        const h = harness()
        t.after(() => h.dispose())
        await h.edit()
        h.api.form.number = 'Saved late'
        const pending = h.api.saveOrder()
        if (action === 'switch') {
            h.props.orderId = 8
            h.order.value = sourceOrder({ id: 8, number: 'Other' })
            await h.edit()
            h.api.form.number = 'New draft'
        } else if (action === 'close') {
            h.props.visible = false
        } else h.dispose()
        h.requests[1].resolve({ data: sourceOrder({ number: 'Saved late' }) })
        await pending
        assert.equal(h.saved.length, 0)
        assert.notEqual(h.order.value.number, 'Saved late')
        assert.equal(h.api.error.value, '')
        if (action === 'switch') {
            assert.equal(h.api.form.number, 'New draft')
            assert.equal(h.api.editing.value, true)
        }
    }
})

test('late options are aborted and ignored after cancellation; failed options retry preserves the draft', async t => {
    const h = harness()
    t.after(() => h.dispose())
    const pending = h.api.beginEdit()
    h.api.cancelEdit()
    assert.equal(h.requests[0].data.signal.aborted, true)
    const second = h.api.beginEdit()
    h.requests[0].resolve({ goods: [{ id: 99, name: 'Old' }] })
    await pending
    assert.equal(h.api.loadingOptions.value, true)
    assert.deepEqual(h.api.options.goods, [])
    h.requests[1].reject(new Error('Options unavailable'))
    await second
    assert.equal(h.api.loadingOptions.value, false)
    assert.match(h.api.error.value, /Не удалось загрузить/)
    h.api.form.number = 'Keep draft'
    await h.api.saveOrder()
    assert.equal(h.requests.length, 2)
    await h.edit()
    assert.equal(h.api.form.number, 'Keep draft')
    assert.equal(h.api.options.goods[0].id, 4)
    assert.equal(h.api.error.value, '')
})

test('a late failed save cannot clear a newer save flag or add an error to another order', async t => {
    const h = harness()
    t.after(() => h.dispose())
    h.api.beginDateEdit()
    h.api.dateDraft.value = '2026-10-05'
    const first = h.api.saveDate()
    h.props.orderId = 8
    h.order.value = sourceOrder({ id: 8 })
    h.api.beginDateEdit()
    h.api.dateDraft.value = '2026-10-06'
    const second = h.api.saveDate()
    h.requests[0].reject(new Error('Late network failure'))
    await first
    assert.equal(h.api.savingDate.value, true)
    assert.equal(h.api.stale.value, false)
    assert.equal(h.api.error.value, '')
    h.requests[1].resolve({ data: sourceOrder({ id: 8, delivery_date: '2026-10-06' }) })
    await second
    assert.equal(h.api.savingDate.value, false)
    assert.equal(h.saved.length, 1)
    assert.equal(h.saved[0].id, 8)
})

test('closing aborts options immediately and malformed write responses require reloading', async t => {
    const h = harness()
    t.after(() => h.dispose())
    const pending = h.api.beginEdit()
    h.props.visible = false
    assert.equal(h.requests[0].data.signal.aborted, true)
    h.requests[0].resolve({ goods: [{ id: 1 }] })
    await pending
    assert.deepEqual(h.api.options.goods, [])
    h.props.visible = true
    await h.edit()
    h.api.form.number = 'Changed'
    const save = h.api.saveOrder()
    h.requests[2].resolve({ data: sourceOrder({ id: 99 }) })
    await save
    assert.equal(h.api.stale.value, true)
    assert.equal(h.order.value.id, 7)
    assert.deepEqual(h.saved, [])
})
