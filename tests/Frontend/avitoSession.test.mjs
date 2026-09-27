import test from 'node:test'
import assert from 'node:assert/strict'
import axios from 'axios'
import { createPinia } from 'pinia'
import { useAvitoStore } from '../../resources/js/Stores/avito.js'
import { observeAvitoSessionErrors } from '../../resources/js/Services/avitoSession.js'
import { createRealtimeCoordinator } from '../../resources/js/Services/realtimeCoordinator.js'

const flush = () => new Promise((resolve) => setImmediate(resolve))
const failure = (status, category) => ({ response: { status, data: { message: 'Unauthenticated.', ...(category ? { category } : {}) } } })
const storeFor = (t) => {
    const store = useAvitoStore(createPinia())
    store.configure({ enabled: false, reason: 'disabled' }, 7)
    t.after(() => store.$dispose())
    return store
}

test('initial control authentication failures stop live status and cancel reads while preserving the reply', async (t) => {
    for (const status of [401, 419]) await t.test(String(status), async (t) => {
        let pendingSignal
        t.mock.method(axios, 'get', (url, options) => {
            if (url.endsWith('/chats')) {
                pendingSignal = options.signal
                return new Promise(() => {})
            }
            return Promise.reject(failure(status))
        })
        const store = storeFor(t)
        store.selectChat({ id: 12 })
        store.composerText = 'Неотправленный ответ'
        store.composerTemplateId = 9
        store.status = 'live'
        void store.loadChats()
        const release = store.retainControl()
        t.after(release)
        await flush()
        assert.equal(store.sessionExpired, true)
        assert.equal(store.status, 'unauthenticated')
        assert.equal(pendingSignal.aborted, true)
        assert.equal(store.chatsLoading, false)
        assert.equal(store.controlLoading, false)
        assert.equal(store.composerText, 'Неотправленный ответ')
        assert.equal(store.composerTemplateId, 9)
        assert.equal(store.selectedChat.id, 12)
        store.configure({ enabled: true }, 7)
        store.reconnect()
        assert.equal(store.status, 'unauthenticated')
    })
})

test('provider authentication and employee permission failures are not expired Ameise sessions', async (t) => {
    const store = storeFor(t)
    for (const error of [failure(401, 'authentication'), failure(403), failure(403, 'authentication'), failure(500)]) {
        t.mock.method(axios, 'get', async () => { throw error })
        await assert.rejects(store.loadControl(), (caught) => caught === error)
        assert.equal(store.sessionExpired, false)
    }
})

test('same employee can explicitly restore a session using only reads without losing the selected chat or draft', async (t) => {
    const store = storeFor(t)
    store.selectChat({ id: 12 })
    store.composerText = 'Черновик после входа'
    store.handleRequestError(failure(401))
    const urls = []
    t.mock.method(axios, 'get', async (url) => {
        urls.push(url)
        return { data: url === '/api/user' ? { id: 7 } : { settings: { mode: 'shadow' } } }
    })
    t.mock.method(axios, 'post', () => assert.fail('Session recovery must never replay a mutation'))
    assert.equal(await store.checkSession(), true)
    assert.deepEqual(urls, ['/api/user', '/api/avito/messenger/auto-replies/control'])
    assert.equal(store.sessionExpired, false)
    assert.equal(store.sessionChecking, false)
    assert.equal(store.status, 'disabled')
    assert.equal(store.composerText, 'Черновик после входа')
    assert.equal(store.selectedChat.id, 12)
})

test('a login as another employee discards the previous employee archive and draft', async (t) => {
    const store = storeFor(t)
    store.selectChat({ id: 12 })
    store.chats = [{ id: 12 }]
    store.composerText = 'Прежний пользователь'
    store.handleRequestError(failure(419))
    t.mock.method(axios, 'get', async (url) => {
        assert.equal(url, '/api/user')
        return { data: { id: 8 } }
    })
    assert.equal(await store.checkSession(), false)
    assert.equal(store.selectedChat, null)
    assert.deepEqual(store.chats, [])
    assert.equal(store.composerText, '')
})

test('a failed explicit login check keeps the persistent status and reports a Russian explanation', async (t) => {
    const store = storeFor(t)
    store.handleRequestError(failure(401))
    t.mock.method(axios, 'get', async () => { throw failure(401) })
    await assert.rejects(store.checkSession())
    assert.equal(store.sessionExpired, true)
    assert.equal(store.status, 'unauthenticated')
    assert.match(store.sessionCheckError, /Вход пока не подтверждён/)
    assert.equal(store.sessionChecking, false)
})

test('the scoped session observer covers Avito and broadcast authentication only and never retries', async () => {
    const seen = []
    let requests = 0
    const client = axios.create({ adapter: async (config) => {
        requests++
        throw { ...failure(401), config }
    } })
    const stop = observeAvitoSessionErrors(client, (error) => seen.push(error.config.url), 'https://ameise.test')
    for (const url of ['/api/avito/status', '/broadcasting/auth', 'https://ameise.test/api/avito/connections',
        '/api/goods', 'https://other.test/api/avito/status']) {
        await assert.rejects(client.post(url))
    }
    assert.deepEqual(seen, ['/api/avito/status', '/broadcasting/auth', 'https://ameise.test/api/avito/connections'])
    assert.equal(requests, 5)
    stop()
    await assert.rejects(client.get('/api/avito/status'))
    assert.equal(seen.length, 3)
})

test('an in-flight response cannot expire a session after the observer has been removed', async () => {
    let reject
    const client = axios.create({ adapter: (config) => new Promise((_resolve, no) => { reject = () => no({ ...failure(401), config }) }) })
    const seen = []
    const stop = observeAvitoSessionErrors(client, (error) => seen.push(error))
    const request = client.get('/api/avito/status')
    stop()
    reject()
    await assert.rejects(request)
    assert.deepEqual(seen, [])
})

test('all mounted Avito consumers share one observer and release it with the last consumer', async (t) => {
    t.mock.method(axios, 'get', async () => ({ data: { settings: { mode: 'off' } } }))
    const store = storeFor(t)
    const count = () => axios.interceptors.response.handlers.filter(Boolean).length
    const before = count()
    const first = store.retainControl()
    const second = store.retainControl()
    assert.equal(count(), before + 1)
    first()
    assert.equal(count(), before + 1)
    second()
    assert.equal(count(), before)
})

test('upstream Avito credential failures do not disconnect realtime or prevent later event recovery', async () => {
    let callbacks
    let disconnected = 0
    let loads = 0
    const statuses = []
    const errors = []
    const timers = new Map()
    let nextTimer = 0
    const error = failure(401, 'authentication')
    const coordinator = createRealtimeCoordinator({
        allowedTopics: ['avito_messages'],
        connect: async (_settings, handlers) => { callbacks = handlers; return () => { disconnected++ } },
        onStatus: (status) => statuses.push(status),
        onError: (id, caught) => errors.push([id, caught]),
        setTimer: (fn) => { timers.set(++nextTimer, fn); return nextTimer },
        clearTimer: (id) => timers.delete(id),
    })
    coordinator.configure({ enabled: true }, 7)
    coordinator.subscribe('messages', ['avito_messages'], async () => { if (++loads === 1) throw error })
    await flush()
    callbacks.ready()
    async function drain() {
        for (const [id, fn] of [...timers]) { timers.delete(id); fn() }
        await flush()
    }
    await drain()
    assert.deepEqual(errors, [['messages', error]])
    assert.equal(disconnected, 0)
    assert.equal(statuses.at(-1), 'live')
    callbacks.event({ event_id: 'after-provider-recovery', topics: ['avito_messages'] })
    await drain()
    assert.equal(loads, 2)
    coordinator.dispose()
})
