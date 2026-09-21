import test from 'node:test'
import assert from 'node:assert/strict'
import axios from 'axios'
import { createPinia } from 'pinia'
import { useAvitoStore, collectAvitoChanges, AVITO_TOPICS } from '../../resources/js/Stores/avito.js'
import { createRealtimeCoordinator } from '../../resources/js/Services/realtimeCoordinator.js'

const flush = () => new Promise((resolve) => setImmediate(resolve))
const deferred = () => {
    let resolve
    const promise = new Promise((done) => { resolve = done })
    return { promise, resolve }
}
const chat = (id = 7, extra = {}) => ({ id, account_id: 1, is_unread: true, unread_count: 1, messages_count: 10, ...extra })
const message = (id, extra = {}) => ({ id, direction: 'in', is_read: false, remote_created_at: new Date(id * 1000).toISOString(), ...extra })
const page = (items, extra = {}) => ({ data: items, current_page: 1, last_page: 1, total: items.length, ...extra })
const changed = (chatIds = [7], messageIds = []) => ({ chatIds: new Set(chatIds), messageIds: new Set(messageIds), overview: true, chats: true })
function storeFor(t) {
    const store = useAvitoStore(createPinia())
    t.after(() => store.$dispose())
    store.selectChat(chat())
    store.chats = [chat()]
    store.messages = [message(10)]
    store.overview = { counts: { unread_chats: 1, unread_messages: 1 }, accounts: [{ id: 1, unread_chats_count: 1 }], latest_runs: [] }
    return store
}

test('batched changes make one incremental request and apply counters, list and messages without losing history or draft', async (t) => {
    const calls = []
    t.mock.method(axios, 'get', async (url, options) => {
        calls.push({ url, params: options.params })
        return { data: {
            overview: { counts: { unread_chats: 2, unread_messages: 3 } },
            chats: page([chat(7, { unread_count: 2 }), chat(8)]),
            selected: { chat: chat(7, { unread_count: 2, messages_count: 201 }), messages: [message(20, { text: 'Updated' }), message(21)] },
        } }
    })
    const store = storeFor(t)
    store.filters.unread_only = true
    store.messages = [message(20, { remote_created_at: new Date(0).toISOString() }), message(10)]
    store.messagesMeta = { current_page: 2, last_page: 2, total: 200, per_page: 100 }
    store.composerText = 'Unsaved reply'
    store.composerTemplateId = 19
    const changes = collectAvitoChanges([
        { changes: { chat_ids: [7], message_ids: [20], chats: true } },
        { changes: { chat_ids: [7, 8], message_ids: [20, 21], overview: true } },
    ])
    await store.loadUpdates(changes)
    assert.equal(calls.length, 1)
    assert.equal(calls[0].url, '/api/avito/messenger/updates')
    assert.equal(calls[0].params.after_message_id, 20)
    assert.equal(calls[0].params.unread_only, 1)
    assert.deepEqual(calls[0].params.message_ids, [20, 21])
    assert.deepEqual([calls[0].params.overview, calls[0].params.chats, calls[0].params.selected], [1, 1, 1])
    assert.equal(store.overview.counts.unread_chats, 2)
    assert.equal(store.chats.length, 2)
    assert.equal(store.selectedChat.unread_count, 2)
    assert.deepEqual(store.messages.map((item) => item.id).sort((a, b) => a - b), [10, 20, 21])
    assert.equal(store.messages.find((item) => item.id === 20).text, 'Updated')
    assert.equal(store.messagesMeta.current_page, 2)
    assert.equal(store.messagesMeta.last_page, 3)
    assert.equal(store.composerText, 'Unsaved reply')
    assert.equal(store.composerTemplateId, 19)
})

test('events for another chat do not request selected conversation data and list filtering sends numeric boolean', async (t) => {
    const calls = []
    t.mock.method(axios, 'get', async (url, options) => {
        calls.push({ url, params: options.params })
        return { data: url.endsWith('/updates') ? {} : page([chat()]) }
    })
    const store = storeFor(t)
    store.filters.unread_only = true
    await store.loadUpdates(changed([8], [90]))
    await store.loadChats()
    assert.equal(calls[0].params.selected, 0)
    assert.equal(calls[0].params.selected_chat_id, undefined)
    assert.equal(calls[0].params.after_message_id, undefined)
    assert.equal(calls[0].params.message_ids, undefined)
    assert.equal(calls[1].params.unread_only, 1)
    assert.deepEqual(store.messages.map((item) => item.id), [10])
})

test('changing filters ignores the obsolete list while still accepting the selected chat update', async (t) => {
    const pending = deferred()
    let signal
    t.mock.method(axios, 'get', (_url, options) => { signal = options.signal; return pending.promise })
    const store = storeFor(t)
    const request = store.loadUpdates(changed([7], [11]))
    store.filters.search = 'new search'
    store.invalidateChats()
    store.chats = [chat(9)]
    pending.resolve({ data: { chats: page([chat(8)]), selected: { chat: chat(7, { title: 'Fresh' }), messages: [message(11)] } } })
    await request
    assert.equal(signal.aborted, false)
    assert.deepEqual(store.chats.map((item) => item.id), [9])
    assert.equal(store.selectedChat.title, 'Fresh')
    assert.deepEqual(store.messages.map((item) => item.id), [10, 11])
})

test('a snapshot started before a read receipt cannot restore stale unread state', async (t) => {
    const pending = deferred()
    t.mock.method(axios, 'get', () => pending.promise)
    const store = storeFor(t)
    const request = store.loadUpdates(changed())
    store.applyReadReceipt(7, { chat: chat(7, { is_unread: false, unread_count: 0 }), read_through_id: 10 })
    pending.resolve({ data: {
        overview: { counts: { unread_chats: 1, unread_messages: 1 } },
        chats: page([chat()]), selected: { chat: chat(), messages: [message(10)] },
    } })
    await request
    assert.equal(store.overview.counts.unread_chats, 0)
    assert.equal(store.chats[0].is_unread, false)
    assert.equal(store.selectedChat.is_unread, false)
    assert.equal(store.messages[0].is_read, true)
})

test('a delayed receipt for A does not discard the initial history response for newly opened B', async (t) => {
    const receipt = deferred()
    const history = deferred()
    t.mock.method(axios, 'post', () => receipt.promise)
    t.mock.method(axios, 'get', () => history.promise)
    const store = storeFor(t)
    const marking = store.markRead(7, 10)
    store.selectChat(chat(8))
    store.composerText = 'Draft for B'
    const opening = store.loadChatPage()
    receipt.resolve({ data: { chat: chat(7, { is_unread: false, unread_count: 0 }), read_through_id: 10 } })
    await marking
    history.resolve({ data: { chat: chat(8, { title: 'B loaded' }), messages: page([message(30)]) } })
    await opening
    assert.equal(store.selectedChat.id, 8)
    assert.equal(store.selectedChat.title, 'B loaded')
    assert.deepEqual(store.messages.map((item) => item.id), [30])
    assert.equal(store.composerText, 'Draft for B')
})

test('returning from A to B to A does not let an old snapshot replace the new selection', async (t) => {
    const pending = deferred()
    t.mock.method(axios, 'get', () => pending.promise)
    const store = storeFor(t)
    const request = store.loadUpdates(changed())
    store.selectChat(chat(8))
    store.selectChat(chat(7, { title: 'New selection' }))
    store.messages = [message(50)]
    pending.resolve({ data: { chats: page([chat(7, { title: 'Old list' })]), selected: { chat: chat(7, { title: 'Old selection' }), messages: [message(10)] } } })
    await request
    assert.equal(store.selectedChat.title, 'New selection')
    assert.deepEqual(store.messages.map((item) => item.id), [50])
})

test('read receipt preserves incoming messages newer than its cutoff, outgoing state and draft', (t) => {
    const store = storeFor(t)
    store.chats = [chat(7, { unread_count: 2 })]
    store.overview.counts.unread_messages = 2
    store.messages = [message(9, { direction: 'out' }), message(10), message(12)]
    store.composerText = 'Keep composing'
    store.applyReadReceipt(7, { chat: chat(7, { is_unread: false, unread_count: 0 }), read_through_id: 10 })
    assert.deepEqual(store.messages.map((item) => item.is_read), [false, true, false])
    assert.equal(store.selectedChat.is_unread, true)
    assert.equal(store.selectedChat.unread_count, 1)
    assert.equal(store.overview.counts.unread_chats, 1)
    assert.equal(store.overview.counts.unread_messages, 1)
    assert.equal(store.composerText, 'Keep composing')
})

test('marking the last incoming message read removes a filtered chat and adjusts counts immediately', (t) => {
    const store = storeFor(t)
    store.filters.unread_only = true
    store.chatsMeta = page([chat()])
    store.applyReadReceipt(7, { chat: chat(7, { is_unread: false, unread_count: 0 }), read_through_id: 10 })
    assert.deepEqual(store.chats, [])
    assert.equal(store.chatsMeta.total, 0)
    assert.equal(store.overview.counts.unread_chats, 0)
    assert.equal(store.overview.counts.unread_messages, 0)
    assert.equal(store.overview.accounts[0].unread_chats_count, 0)
    assert.equal(store.selectedChat.is_unread, false)
})

test('concurrent read actions for one chat share one POST', async (t) => {
    const pending = deferred()
    const calls = []
    t.mock.method(axios, 'post', (url, data) => { calls.push({ url, data }); return pending.promise })
    const store = storeFor(t)
    const first = store.markRead(7, 10)
    const second = store.markRead(7, 10)
    assert.deepEqual(calls, [{ url: '/api/avito/messenger/chats/7/read', data: { through_message_id: 10 } }])
    pending.resolve({ data: { chat: chat(7, { is_unread: false, unread_count: 0 }), read_through_id: 10 } })
    await Promise.all([first, second])
    assert.equal(store.messages[0].is_read, true)
})

test('an emptied final filtered page moves back to the last valid page', async (t) => {
    const pages = []
    t.mock.method(axios, 'get', async (_url, options) => {
        pages.push(options.params.page)
        return { data: options.params.page === 3 ? page([], { current_page: 3, last_page: 2, total: 51 }) : page([chat(8)], { current_page: 2, last_page: 2, total: 51 }) }
    })
    const store = storeFor(t)
    store.filters.unread_only = true
    await store.loadChats(3)
    assert.deepEqual(pages, [3, 2])
    assert.equal(store.chatsMeta.current_page, 2)
    assert.deepEqual(store.chats.map((item) => item.id), [8])
})

function coordinatorFor(t, load) {
    let clock = 0
    let nextId = 0
    let handlers
    const timers = new Map()
    const coordinator = createRealtimeCoordinator({
        allowedTopics: AVITO_TOPICS, debounceMs: 250, minRefreshIntervalMs: 2000,
        now: () => clock,
        connect: async (_config, callbacks) => { handlers = callbacks; return () => {} },
        setTimer: (callback, delay) => { timers.set(++nextId, { callback, at: clock + delay }); return nextId },
        clearTimer: (id) => timers.delete(id),
    })
    t.after(() => coordinator.dispose())
    coordinator.configure({ enabled: true }, 1)
    coordinator.subscribe('messages', ['avito_messages'], load)
    async function advance(ms) {
        const target = clock + ms
        while (true) {
            const next = [...timers].filter(([, timer]) => timer.at <= target).sort((a, b) => a[1].at - b[1].at)[0]
            if (!next) break
            const [id, timer] = next
            clock = timer.at
            timers.delete(id)
            timer.callback()
            await flush()
        }
        clock = target
    }
    return { timers, advance, ready: () => handlers.ready(), event: (id) => handlers.event({ event_id: id, topics: ['avito_messages'], changes: { chat_ids: [7] } }) }
}

test('events arriving during a request merge into one followup with a two second floor and no idle polling', async (t) => {
    const initial = deferred()
    const calls = []
    const h = coordinatorFor(t, (options) => {
        calls.push(options)
        return calls.length === 1 ? initial.promise : Promise.resolve()
    })
    await flush()
    h.ready()
    await h.advance(250)
    h.event('one')
    h.event('two')
    h.event('two')
    await h.advance(1000)
    assert.equal(calls.length, 1)
    initial.resolve()
    await flush()
    await h.advance(999)
    assert.equal(calls.length, 1)
    await h.advance(1)
    assert.equal(calls.length, 2)
    assert.deepEqual(calls[1].events.map((item) => item.event_id), ['one', 'two'])
    assert.equal(calls[1].overflow, false)
    await h.advance(3600000)
    assert.equal(calls.length, 2)
    assert.equal(h.timers.size, 0)
})

test('429 Retry-After delays the next event refresh without starting a polling loop', async (t) => {
    const calls = []
    const h = coordinatorFor(t, async (options) => {
        calls.push(options)
        if (calls.length === 1) throw { response: { status: 429, headers: { 'retry-after': '5' } } }
    })
    await flush()
    h.ready()
    await h.advance(250)
    assert.equal(h.timers.size, 0)
    h.event('after-limit')
    await h.advance(4999)
    assert.equal(calls.length, 1)
    await h.advance(1)
    assert.equal(calls.length, 2)
    assert.equal(calls[1].overflow, true)
    await h.advance(60000)
    assert.equal(calls.length, 2)
    assert.equal(h.timers.size, 0)
})
