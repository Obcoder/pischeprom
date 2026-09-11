import test from 'node:test'
import assert from 'node:assert/strict'
import { createPinia } from 'pinia'
import { createRealtimeCoordinator } from '../../resources/js/Services/realtimeCoordinator.js'
import { useCommerceStore } from '../../resources/js/Stores/commerce.js'

const flush = () => new Promise((resolve) => setImmediate(resolve))
const configuration = { enabled: true, key: 'test-key', host: 'app.test', port: 443, scheme: 'https', path: '/realtime', channel: 'commerce.updates' }

function harness() {
    let now = 0
    let timerId = 0
    const timers = new Map()
    const sockets = []
    const states = []
    const refreshed = []
    const failures = []
    let visible = true
    let online = true
    const coordinator = createRealtimeCoordinator({
        connect: async (_config, handlers) => {
            const socket = { handlers, closed: false }
            sockets.push(socket)
            return () => { socket.closed = true }
        },
        onStatus: (status) => states.push(status),
        onRefreshed: (id) => refreshed.push(id),
        onError: (id) => failures.push(id),
        isVisible: () => visible,
        isOnline: () => online,
        setTimer: (callback, ms) => {
            timers.set(++timerId, { callback, at: now + ms })
            return timerId
        },
        clearTimer: (id) => timers.delete(id),
    })
    async function advance(ms = 150) {
        const target = now + ms
        while (true) {
            const next = [...timers].filter(([, timer]) => timer.at <= target).sort((a, b) => a[1].at - b[1].at)[0]
            if (!next) break
            const [id, timer] = next
            now = timer.at
            timers.delete(id)
            timer.callback()
            await flush()
        }
        now = target
    }
    return {
        coordinator, sockets, states, refreshed, failures, timers, advance,
        setVisible(value) { visible = value; coordinator.visibilityChanged() },
        setOnline(value) { online = value; coordinator.onlineChanged() },
    }
}

test('idle app has no polling; subscription catches up exactly once', async () => {
    const h = harness()
    let loads = 0
    h.coordinator.configure(configuration, 1)
    assert.equal(h.sockets.length, 0)
    h.coordinator.subscribe('stock', ['goods_stock'], async () => { loads++ })
    await flush()
    h.sockets[0].handlers.ready()
    await h.advance()
    assert.equal(loads, 1)
    await h.advance(3600000)
    assert.equal(loads, 1)
    assert.equal(h.timers.size, 0)
    h.coordinator.dispose()
})

test('one socket serves several resources; event bursts refresh only matching data once', async () => {
    const h = harness()
    let stockLoads = 0
    let saleLoads = 0
    h.coordinator.configure(configuration, 1)
    h.coordinator.subscribe('stock', ['goods_stock'], async () => { stockLoads++ })
    h.coordinator.subscribe('sales', ['sales'], async () => { saleLoads++ })
    await flush()
    h.sockets[0].handlers.ready()
    await h.advance()
    for (let id = 0; id < 20; id++) h.sockets[0].handlers.event({ event_id: `event-${id}`, topics: ['goods_stock'] })
    await h.advance()
    assert.equal(h.sockets.length, 1)
    assert.equal(stockLoads, 2)
    assert.equal(saleLoads, 1)
    h.sockets[0].handlers.event({ event_id: 'event-19', topics: ['goods_stock'] })
    h.sockets[0].handlers.event({ event_id: 'unknown', topics: ['secret'] })
    await h.advance()
    assert.equal(stockLoads, 2)
    h.coordinator.dispose()
})

test('an event arriving during an axios read queues one further read without overlapping', async () => {
    const h = harness()
    const pending = []
    let loads = 0
    h.coordinator.configure(configuration, 1)
    h.coordinator.subscribe('stock', ['goods_stock'], () => {
        loads++
        return new Promise((resolve) => pending.push(resolve))
    })
    await flush()
    h.sockets[0].handlers.ready()
    await h.advance()
    for (let i = 0; i < 3; i++) h.sockets[0].handlers.event({ event_id: `new-${i}`, topics: ['goods_stock'] })
    await h.advance(1000)
    assert.equal(loads, 1)
    pending.shift()()
    await flush()
    await h.advance()
    assert.equal(loads, 2)
    pending.shift()()
    await flush()
    h.coordinator.dispose()
})

test('hidden tabs defer data reads, then catch up without resetting the resource', async () => {
    const h = harness()
    const reasons = []
    h.coordinator.configure(configuration, 1)
    h.coordinator.subscribe('stock', ['goods_stock'], async ({ reason }) => reasons.push(reason))
    await flush()
    h.setVisible(false)
    h.sockets[0].handlers.ready()
    h.sockets[0].handlers.event({ event_id: 'hidden-update', topics: ['goods_stock'] })
    await h.advance(60000)
    assert.deepEqual(reasons, [])
    h.setVisible(true)
    await h.advance()
    assert.deepEqual(reasons, ['visible'])
    h.coordinator.dispose()
})

test('reconnect reloads once to catch events missed while disconnected', async () => {
    const h = harness()
    let loads = 0
    h.coordinator.configure(configuration, 1)
    h.coordinator.subscribe('sales', ['sales'], async () => { loads++ })
    await flush()
    h.sockets[0].handlers.ready()
    await h.advance()
    h.setOnline(false)
    assert.equal(h.sockets[0].closed, true)
    h.setOnline(true)
    await flush()
    h.sockets[1].handlers.ready()
    await h.advance()
    assert.equal(loads, 2)
    assert.equal(h.states.at(-1), 'live')
    h.coordinator.dispose()
})

test('unmount aborts the request and disconnects the last subscription', async () => {
    const h = harness()
    let signal
    let resolve
    h.coordinator.configure(configuration, 1)
    const unsubscribe = h.coordinator.subscribe('stock', ['goods_stock'], (request) => {
        signal = request.signal
        return new Promise((done) => { resolve = done })
    })
    await flush()
    h.sockets[0].handlers.ready()
    await h.advance()
    unsubscribe()
    assert.equal(signal.aborted, true)
    assert.equal(h.sockets[0].closed, true)
    resolve()
    await flush()
    assert.deepEqual(h.refreshed, [])
    assert.equal(h.timers.size, 0)
    h.coordinator.dispose()
})

test('logout prevents old socket events and disabled clients never connect', async () => {
    const h = harness()
    let loads = 0
    h.coordinator.configure({ enabled: false }, null)
    h.coordinator.subscribe('sales', ['sales'], async () => { loads++ })
    assert.equal(h.sockets.length, 0)
    h.coordinator.configure(configuration, 1)
    await flush()
    const oldSocket = h.sockets[0]
    oldSocket.handlers.ready()
    h.coordinator.configure({ enabled: false }, null)
    oldSocket.handlers.event({ event_id: 'old-user', topics: ['sales'] })
    await h.advance()
    assert.equal(loads, 0)
    assert.equal(oldSocket.closed, true)
    h.coordinator.dispose()
})

test('authorization failure stops automatic reconnect until explicitly retried', async () => {
    const h = harness()
    h.coordinator.configure(configuration, 1)
    h.coordinator.subscribe('stock', ['goods_stock'], async () => {})
    await flush()
    h.sockets[0].handlers.status('forbidden')
    h.setVisible(true)
    await h.advance(60000)
    assert.equal(h.states.at(-1), 'forbidden')
    assert.equal(h.sockets.length, 1)
    assert.equal(h.sockets[0].closed, true)
    h.coordinator.reconnect()
    await flush()
    assert.equal(h.sockets.length, 2)
    h.coordinator.dispose()
})

test('two browser clients receive the same committed change and refetch authoritative values', async () => {
    const tabs = [harness(), harness()]
    let databaseBalance = 10
    const displayed = [0, 0]
    for (const [index, tab] of tabs.entries()) {
        tab.coordinator.configure(configuration, 1)
        tab.coordinator.subscribe('stock', ['goods_stock'], async () => { displayed[index] = databaseBalance })
        await flush()
        tab.sockets[0].handlers.ready()
        await tab.advance()
    }
    assert.deepEqual(displayed, [10, 10])
    databaseBalance = 7
    for (const tab of tabs) tab.sockets[0].handlers.event({ event_id: 'committed-sale', topics: ['sales', 'goods_stock'] })
    for (const tab of tabs) await tab.advance()
    assert.deepEqual(displayed, [7, 7])
    for (const tab of tabs) tab.coordinator.dispose()
})

test('revoked API access closes an existing subscription and stops further reads', async () => {
    const h = harness()
    let loads = 0
    h.coordinator.configure(configuration, 1)
    h.coordinator.subscribe('stock', ['goods_stock'], async () => {
        loads++
        throw { response: { status: 403 } }
    })
    await flush()
    h.sockets[0].handlers.ready()
    await h.advance()
    assert.equal(h.states.at(-1), 'forbidden')
    assert.equal(h.sockets[0].closed, true)
    h.sockets[0].handlers.event({ event_id: 'after-revocation', topics: ['goods_stock'] })
    h.setVisible(true)
    await h.advance(60000)
    assert.equal(loads, 1)
    assert.deepEqual(h.failures, ['stock'])
    h.coordinator.dispose()
})

test('SSR Pinia stores never share business data between requests', () => {
    const first = useCommerceStore(createPinia())
    const second = useCommerceStore(createPinia())
    const firstId = first.createResource('stock', { rows: [] })
    const secondId = second.createResource('stock', { rows: [] })
    first.resources[firstId].rows.push({ good_id: 1, quantity: 7 })
    assert.deepEqual(second.resources[secondId].rows, [])
    first.$dispose()
    second.$dispose()
})
