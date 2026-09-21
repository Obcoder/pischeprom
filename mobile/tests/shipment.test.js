import test from 'node:test'
import assert from 'node:assert/strict'
import { ApiError } from '../src/api.js'
import { createShipmentOperation, validatePreparation } from '../src/shipment.js'

const order = { id: 12, version: 'version-1' }
const receipt = { data: { id: 12, workflow_status: 'shipped', sale: { id: 39 } } }

test('double tap shares one request and one sale confirmation', async () => {
    const calls = []
    let resolveShipment
    const operation = createShipmentOperation({ ship: (id, payload) => {
        calls.push({ id, ...payload })
        return new Promise(resolve => { resolveShipment = resolve })
    } }, () => 'unique-key')
    const first = operation.send(order)
    const second = operation.send(order)
    assert.equal(first, second)
    await Promise.resolve()
    assert.equal(calls.length, 1)
    resolveShipment(receipt)
    assert.deepEqual(await first, receipt)
    assert.equal(operation.hasPending(order), false)
})

test('lost response, proxy failure and timeout reuse the original idempotency key on manual retry', async () => {
    for (const status of [0, 500, 503, 408, 429]) {
        const keys = []
        let counter = 0
        const operation = createShipmentOperation({ ship: async (_, payload) => {
            keys.push(payload.request_id)
            if (keys.length === 1) throw new ApiError('Response lost', { status })
            return receipt
        } }, () => `key-${++counter}`)
        await assert.rejects(() => operation.send(order))
        assert.equal(operation.hasPending(order), true)
        await operation.send(order)
        assert.deepEqual(keys, ['key-1', 'key-1'])
        assert.equal(counter, 1)
    }
})

test('malformed success never reports shipment complete and preserves the key', async () => {
    const keys = []
    const operation = createShipmentOperation({ ship: async (_, payload) => {
        keys.push(payload.request_id)
        return keys.length === 1 ? { data: { id: 12 } } : receipt
    } }, () => 'same-key')
    await assert.rejects(() => operation.send(order), /не подтвердил отгрузку/)
    assert.equal(operation.hasPending(order), true)
    await operation.send(order)
    assert.deepEqual(keys, ['same-key', 'same-key'])
})

test('a deterministic validation rejection allows a fresh attempt', async () => {
    const keys = []
    let counter = 0
    const operation = createShipmentOperation({ ship: async (_, payload) => {
        keys.push(payload.request_id)
        if (keys.length === 1) throw new ApiError('Changed order', { status: 409 })
        return receipt
    } }, () => `key-${++counter}`)
    await assert.rejects(() => operation.send(order))
    assert.equal(operation.hasPending(order), false)
    await operation.send({ ...order, version: 'version-2' })
    assert.deepEqual(keys, ['key-1', 'key-2'])
})

test('uncertain attempt cannot reuse the key for a different order version', async () => {
    const keys = []
    let counter = 0
    const operation = createShipmentOperation({ ship: async (_, payload) => {
        keys.push(payload.request_id)
        if (keys.length === 1) throw new ApiError('Network failure')
        return receipt
    } }, () => `key-${++counter}`)
    await assert.rejects(() => operation.send(order))
    await operation.send({ ...order, version: 'version-2' })
    assert.deepEqual(keys, ['key-1', 'key-2'])
})

test('two orders keep independent uncertain shipment attempts', async () => {
    const keys = []
    let counter = 0
    const operation = createShipmentOperation({ ship: async (id, payload) => {
        keys.push([id, payload.request_id])
        throw new ApiError('Network failure')
    } }, () => `key-${++counter}`)
    await assert.rejects(() => operation.send(order))
    await assert.rejects(() => operation.send({ id: 13, version: 'version-1' }))
    await assert.rejects(() => operation.send(order))
    assert.deepEqual(keys, [[12, 'key-1'], [13, 'key-2'], [12, 'key-1']])
})

const preparation = {
    items: [{ id: 9, name: 'Мука', quantity: '2.5', measure_options: [{ id: 1, name: 'кг', available_quantity: 4 }, { id: 2, name: 'шт.', available_quantity: 1 }] }],
}
const row = { id: 9, quantity: '2,5', measure_id: 1, checked: true }

test('verified full quantity permits localized decimal input', () => {
    assert.equal(validatePreparation(preparation, [row]), null)
})

test('preparation requires explicit quantity and measurement verification', () => {
    assert.match(validatePreparation(preparation, [{ ...row, checked: false }]), /Сверьте/)
    assert.match(validatePreparation(preparation, [{ ...row, measure_id: null }]), /единицу/)
    assert.match(validatePreparation(preparation, [{ ...row, measure_id: 2 }]), /Недостаточный остаток/)
})

test('partial, excessive, negative and non-finite quantities cannot be confirmed', () => {
    for (const value of ['2', '3', '-2.5', '', 'Infinity', 'NaN']) {
        assert.match(validatePreparation(preparation, [{ ...row, quantity: value }]), /полной отгрузки/)
    }
    assert.ok(validatePreparation(preparation, []))
    assert.ok(validatePreparation({ items: [] }, []))
})
