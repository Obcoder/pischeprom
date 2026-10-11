import test from 'node:test'
import assert from 'node:assert/strict'
import { ApiError } from '../src/api.js'
import { createOrderOperation, newOrderPayload, orderLineTotal, orderNumber, orderTotals, validateNewOrder } from '../src/order-create.js'

const receipt = { data: { id: 81, items: [{ id: 4 }] } }
const draft = () => ({
    entity_id: 2, order_status_id: 1, delivery_date: '2026-10-11', contact_telephone_id: 3,
    building_ids: [5], building_apartments: { 5: 8, 6: 9 }, preferred_delivery_time: ' 10:00–12:00 ', internal_comment: '',
    items: [{ good_id: 6, quantity: '2,5', unit_price: '140,2', currency_code: 'RUB', measure_id: 1, measurement: { measure_id: 1 } }],
})

test('double tap creates one order and shares the pending request', async () => {
    const calls = []
    let finish
    const operation = createOrderOperation({ createOrder: payload => {
        calls.push(payload)
        return new Promise(resolve => { finish = resolve })
    } }, () => 'unique-id')
    const first = operation.send(newOrderPayload(draft()))
    const second = operation.send(newOrderPayload(draft()))
    assert.equal(first, second)
    await Promise.resolve()
    assert.equal(calls.length, 1)
    finish(receipt)
    assert.equal(await first, receipt)
    assert.equal(operation.hasPending(), false)
})

test('uncertain creation retries preserve original key and contents even if the draft changes', async () => {
    for (const status of [0, 408, 425, 429, 500, 503]) {
        const calls = []
        const operation = createOrderOperation({ createOrder: async payload => {
            calls.push(payload)
            if (calls.length === 1) throw new ApiError('Response lost', { status })
            return receipt
        } }, () => 'same-id')
        const form = draft()
        await assert.rejects(operation.send(newOrderPayload(form)))
        assert.equal(operation.hasPending(), true)
        form.items[0].quantity = '10'
        await operation.send(newOrderPayload(form))
        assert.deepEqual(calls[0], calls[1])
        assert.equal(calls[1].items[0].quantity, 2.5)
        assert.equal(operation.hasPending(), false)
    }
})

test('malformed success keeps operation pending; validation failure allows an edited attempt', async () => {
    let counter = 0
    const calls = []
    const operation = createOrderOperation({ createOrder: async payload => {
        calls.push(payload)
        if (calls.length === 1) return { data: { id: 81 } }
        if (calls.length === 2) throw new ApiError('Invalid quantity', { status: 422 })
        return receipt
    } }, () => `id-${++counter}`)
    await assert.rejects(operation.send(newOrderPayload(draft())), /не подтвердил/)
    assert.equal(operation.hasPending(), true)
    await assert.rejects(operation.send(newOrderPayload(draft())))
    assert.equal(operation.hasPending(), false)
    await operation.send(newOrderPayload(draft()))
    assert.deepEqual(calls.map(call => call.request_id), ['id-1', 'id-1', 'id-2'])
})

test('localized quantities and prices are normalized and deselected apartments are omitted', () => {
    const payload = newOrderPayload(draft())
    assert.equal(payload.items[0].quantity, 2.5)
    assert.equal(payload.items[0].unit_price, 140.2)
    assert.deepEqual(payload.building_apartments, { 5: 8 })
    assert.equal(payload.preferred_delivery_time, '10:00–12:00')
    assert.equal(payload.internal_comment, null)
    assert.deepEqual(validateNewOrder(draft()), {})
})

test('empty, negative, non-finite and malformed numeric inputs cannot be submitted', () => {
    for (const value of ['', ' ', '-1', 'Infinity', 'NaN', '1,2,3', '0x10', '1e3', null]) {
        const form = draft()
        form.items[0].quantity = value
        form.items[0].unit_price = value
        const errors = validateNewOrder(form)
        assert.ok(errors['items.0.quantity'], String(value))
        assert.ok(errors['items.0.unit_price'], String(value))
    }
    const form = draft()
    form.items[0].unit_price = '0'
    assert.equal(validateNewOrder(form)['items.0.unit_price'], undefined)
    form.items[0].quantity = '0'
    assert.ok(validateNewOrder(form)['items.0.quantity'])
    assert.ok(Number.isNaN(orderNumber(' ')))
})

test('totals retain the writer’s four-decimal line precision and keep currencies separate', () => {
    assert.equal(orderLineTotal({ quantity: '3', unit_price: '0.335' }), 1.005)
    assert.deepEqual(orderTotals([
        { quantity: '2,5', unit_price: '140,2', currency_code: 'RUB' },
        { quantity: '3', unit_price: '0.335', currency_code: 'RUB' },
        { quantity: '2', unit_price: '10', currency_code: 'USD' },
        { quantity: '', unit_price: '10', currency_code: 'RUB' },
    ]), { RUB: 351.505, USD: 20 })
    assert.deepEqual(orderTotals([
        { quantity: '1', unit_price: '0.005', currency_code: 'RUB' },
        { quantity: '1', unit_price: '0.005', currency_code: 'RUB' },
    ]), { RUB: 0.01 })
    assert.equal(orderLineTotal({ quantity: '2', unit_price: '0.12345' }), 0.247)
})

test('quantity validation preserves the server’s supported precision and bounds', () => {
    for (const value of ['0.0001', '1,2345', '1000000000']) {
        const form = draft()
        form.items[0].quantity = value
        assert.ok(validateNewOrder(form)['items.0.quantity'])
    }
    const form = draft()
    form.items[0].quantity = '0,001'
    assert.deepEqual(validateNewOrder(form), {})
})
