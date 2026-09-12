import test from 'node:test'
import assert from 'node:assert/strict'
import axios from 'axios'
import { createPinia } from 'pinia'
import { useAvitoStore, AVITO_TOPICS } from '../../resources/js/Stores/avito.js'
import { createRealtimeCoordinator } from '../../resources/js/Services/realtimeCoordinator.js'

const flush = () => new Promise((resolve) => setImmediate(resolve))
const deferred = () => {
    let resolve
    let reject
    const promise = new Promise((yes, no) => { resolve = yes; reject = no })
    return { promise, resolve, reject }
}
const storeFor = (t) => {
    const store = useAvitoStore(createPinia())
    t.after(() => store.$dispose())
    return store
}
const message = (id, extra = {}) => ({ id, remote_created_at: new Date(id * 1000).toISOString(), text: `Message ${id}`, ...extra })
const pageOf = (ids, page = 1, lastPage = 1) => ({
    chat: { id: 7, title: 'Selected chat' },
    messages: { data: ids.map((id) => message(id)), current_page: page, last_page: lastPage, total: lastPage * 100 },
})

test('Avito topics coalesce bursts, ignore commerce and duplicates, and never poll while idle', async () => {
    const timers = new Map()
    let timerId = 0
    let handlers
    const loads = { messages: 0, control: 0 }
    const coordinator = createRealtimeCoordinator({
        allowedTopics: AVITO_TOPICS,
        connect: async (_config, callbacks) => { handlers = callbacks; return () => {} },
        setTimer: (callback) => { timers.set(++timerId, callback); return timerId },
        clearTimer: (id) => timers.delete(id),
    })
    async function drain() {
        for (const [id, callback] of [...timers]) { timers.delete(id); callback() }
        await flush()
    }
    coordinator.configure({ enabled: true, event: 'avito.changed' }, 1)
    coordinator.subscribe('messages', ['avito_messages'], async () => { loads.messages++ })
    coordinator.subscribe('control', ['avito_auto_replies'], async () => { loads.control++ })
    await flush()
    handlers.ready()
    await drain()
    assert.deepEqual(loads, { messages: 1, control: 1 })
    for (let index = 0; index < 20; index++) handlers.event({ event_id: `avito-${index}`, topics: ['avito_messages'] })
    handlers.event({ event_id: 'sale', topics: ['sales'] })
    await drain()
    assert.deepEqual(loads, { messages: 2, control: 1 })
    handlers.event({ event_id: 'avito-19', topics: ['avito_messages'] })
    await drain()
    assert.deepEqual(loads, { messages: 2, control: 1 })
    assert.equal(timers.size, 0)
    coordinator.dispose()
})

test('two Pinia instances do not share Avito history or an emergency stop', (t) => {
    const first = storeFor(t)
    const second = storeFor(t)
    first.chats.push({ id: 1 })
    first.applyControl({ mode: 'off', is_emergency_stopped: true })
    first.composerText = 'Draft'
    assert.deepEqual(second.chats, [])
    assert.equal(second.controlSettings, null)
    assert.equal(second.composerText, '')
})

test('an older search response cannot replace the current search results', async (t) => {
    const pending = []
    t.mock.method(axios, 'get', (_url, options) => {
        const response = deferred()
        pending.push({ ...response, options })
        return response.promise
    })
    const store = storeFor(t)
    store.filters.search = 'old'
    const old = store.loadChats(4)
    store.filters.search = 'new'
    store.invalidateChats()
    const current = store.loadChats(1)
    assert.equal(pending[0].options.signal.aborted, true)
    pending[1].resolve({ data: { data: [{ id: 2 }], current_page: 1, last_page: 1 } })
    await current
    pending[0].resolve({ data: { data: [{ id: 1 }], current_page: 4, last_page: 5 } })
    await old
    assert.deepEqual(store.chats, [{ id: 2 }])
    assert.equal(store.chatsMeta.current_page, 1)
    assert.equal(store.chatsLoading, false)
})

test('switching chat aborts its old response and preserves the new draft and selection', async (t) => {
    const pending = deferred()
    let signal
    t.mock.method(axios, 'get', (_url, options) => { signal = options.signal; return pending.promise })
    const store = storeFor(t)
    store.selectChat({ id: 7 })
    const request = store.loadChatPage(1)
    store.selectChat({ id: 8 })
    store.composerText = 'New chat draft'
    assert.equal(signal.aborted, true)
    pending.resolve({ data: pageOf([1, 2, 3]) })
    await request
    assert.equal(store.selectedChat.id, 8)
    assert.deepEqual(store.messages, [])
    assert.equal(store.composerText, 'New chat draft')
    assert.equal(store.chatLoading, false)
})

test('reconnect catches more than 100 new messages and refreshes all previously loaded history', async (t) => {
    const requestedPages = []
    t.mock.method(axios, 'get', async (_url, options) => {
        const page = options.params.page
        requestedPages.push(page)
        const top = 350 - (page - 1) * 100
        const data = pageOf(Array.from({ length: 100 }, (_, index) => top - 99 + index), page, 4)
        data.messages.data = data.messages.data.map((item) => item.id === 80 ? { ...item, remote_type: 'deleted' } : item)
        return { data }
    })
    const store = storeFor(t)
    store.selectChat({ id: 7 })
    store.messages = Array.from({ length: 100 }, (_, index) => message(index + 51))
    store.composerText = 'Unsaved reply'
    store.composerTemplateId = 19
    await store.refreshChat()
    assert.deepEqual(requestedPages, [1, 2, 3])
    assert.equal(store.messages.length, 300)
    assert.deepEqual(store.messages.map((item) => item.id), Array.from({ length: 300 }, (_, index) => index + 51))
    assert.equal(store.messages.find((item) => item.id === 80).remote_type, 'deleted')
    assert.equal(store.messagesMeta.current_page, 3)
    assert.equal(store.composerText, 'Unsaved reply')
    assert.equal(store.composerTemplateId, 19)
    assert.equal(store.selectedChat.id, 7)
})

test('older pagination merges overlapping records without losing current messages', async (t) => {
    t.mock.method(axios, 'get', async () => ({ data: pageOf([1, 2, 3], 2, 2) }))
    const store = storeFor(t)
    store.selectChat({ id: 7 })
    store.messages = [message(3), message(4)]
    await store.loadChatPage(2, { prepend: true })
    assert.deepEqual(store.messages.map((item) => item.id), [1, 2, 3, 4])
    assert.equal(store.messagesMeta.current_page, 2)
})

test('disposing the messenger cancels reads and ignores responses even if transport cancellation loses a race', async (t) => {
    const pending = deferred()
    let signal
    t.mock.method(axios, 'get', (_url, options) => { signal = options.signal; return pending.promise })
    const store = storeFor(t)
    const request = store.loadChats(1)
    store.releaseMessenger()
    assert.equal(signal.aborted, true)
    pending.resolve({ data: { data: [{ id: 1 }], current_page: 1 } })
    await request
    assert.deepEqual(store.chats, [])
})

test('emergency stop defeats both a stale control GET and a stale settings form response', async (t) => {
    const read = deferred()
    t.mock.method(axios, 'get', () => read.promise)
    t.mock.method(axios, 'post', async (url) => {
        assert.equal(url, '/api/avito/messenger/auto-replies/emergency-stop')
        return { data: { settings: { mode: 'off', is_emergency_stopped: true }, message: 'Stopped' } }
    })
    const store = storeFor(t)
    store.applyControl({ mode: 'active', is_emergency_stopped: false })
    const staleVersion = store.controlVersion
    const request = store.loadControl()
    await store.emergencyStop()
    read.resolve({ data: { settings: { mode: 'active', is_emergency_stopped: false } } })
    await request
    assert.equal(store.applyControl({ mode: 'active', is_emergency_stopped: false }, staleVersion), false)
    assert.deepEqual(store.controlSettings, { mode: 'off', is_emergency_stopped: true })
    assert.equal(store.stopLoading, false)
})

test('a stop received through realtime invalidates older settings snapshots from the same browser', (t) => {
    const store = storeFor(t)
    store.applyControl({ mode: 'active', is_emergency_stopped: false })
    const oldVersion = store.controlVersion
    store.applyControl({ mode: 'off', is_emergency_stopped: true })
    assert.equal(store.applyControl({ mode: 'active', is_emergency_stopped: false }, oldVersion), false)
    assert.equal(store.controlSettings.is_emergency_stopped, true)
})

test('resuming is an explicit separate action and takes the authoritative shadow response', async (t) => {
    t.mock.method(axios, 'post', async (url) => {
        assert.equal(url, '/api/avito/messenger/auto-replies/resume')
        return { data: { settings: { mode: 'shadow', is_emergency_stopped: false } } }
    })
    const store = storeFor(t)
    store.applyControl({ mode: 'off', is_emergency_stopped: true })
    await store.resumeAutomation()
    assert.deepEqual(store.controlSettings, { mode: 'shadow', is_emergency_stopped: false })
})

test('stop failures remain visible to the caller and do not display a false success', async (t) => {
    t.mock.method(axios, 'post', async () => { throw new Error('network error') })
    const store = storeFor(t)
    store.applyControl({ mode: 'active', is_emergency_stopped: false })
    await assert.rejects(store.emergencyStop(), /network error/)
    assert.equal(store.controlSettings.is_emergency_stopped, false)
    assert.equal(store.stopLoading, false)
})

test('a control response for a previous signed-in user cannot enter the new user state', async (t) => {
    const response = deferred()
    t.mock.method(axios, 'post', () => response.promise)
    const store = storeFor(t)
    store.configure({ enabled: false }, 1)
    store.applyControl({ mode: 'active', is_emergency_stopped: false })
    const request = store.emergencyStop()
    store.configure({ enabled: false }, 2)
    response.resolve({ data: { settings: { mode: 'off', is_emergency_stopped: true } } })
    assert.equal(await request, null)
    assert.equal(store.controlSettings, null)
    assert.equal(store.stopLoading, false)
})

test('a recovered control read clears the previous refresh warning', async (t) => {
    t.mock.method(axios, 'get', async () => ({ data: { settings: { mode: 'shadow', is_emergency_stopped: false } } }))
    const store = storeFor(t)
    store.refreshErrors.control = true
    await store.loadControl()
    assert.equal(store.refreshErrors.control, undefined)
})
