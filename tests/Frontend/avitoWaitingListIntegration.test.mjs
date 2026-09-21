import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import { createRenderer, h, nextTick } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'
import { useAvitoStore } from '../../resources/js/Stores/avito.js'

async function component(name) {
    const url = new URL(`../../resources/js/Components/Avito/${name}.vue`, import.meta.url)
    const filename = fileURLToPath(url)
    const { descriptor } = parse(await readFile(url, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: name, genDefaultAs: 'Component' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: name,
        compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    let script = compiled.content
        .replace(/import (AvitoMessages|AvitoCrmPanel) from [^\n]+/g, 'const $1 = {}')
        .replace(/import \{ useAvitoRealtime \} from [^\n]+/, `
const useAvitoRealtime = (options) => {
    globalThis.__waitingHarness.subscriptions.push(options)
    return globalThis.__waitingHarness.store
}`)
        .replace(/from (['"])(vue|axios|pinia)\1/g, (_match, _quote, dependency) => `from '${import.meta.resolve(dependency)}'`)
        .replace(/from (['"])\.\.\/\.\.\/Stores\/avito.js\1/g, `from '${new URL('../../resources/js/Stores/avito.js', import.meta.url)}'`)
    script += '\nComponent.render = () => null\nexport default Component\n'
    return (await import(`data:text/javascript;base64,${Buffer.from(script).toString('base64')}`)).default
}

const WaitingList = await component('AvitoWaitingList')
const Messages = await component('AvitoMessages')
const ChatDialog = await component('AvitoChatDialog')
const renderer = createRenderer({
    createElement: (type) => ({ type }), createText: (text) => ({ text }), createComment: () => ({}),
    insert() {}, remove() {}, setText() {}, setElementText() {}, patchProp() {},
    parentNode: () => null, nextSibling: () => null,
})
const flush = () => new Promise((resolve) => setImmediate(resolve))
const deferred = () => {
    let resolve
    const promise = new Promise((done) => { resolve = done })
    return { promise, resolve }
}
async function until(predicate) {
    const deadline = Date.now() + 3000
    while (!predicate()) {
        if (Date.now() > deadline) assert.fail('Component did not reach the expected state')
        await flush()
    }
}
const chat = (id = 7, extra = {}) => ({ id, external_chat_id: `external-${id}`, title: `Chat ${id}`,
    is_unread: false, unread_count: 0, waiting_since: '2026-09-21T09:00:00Z', waiting_note: 'Уточнить цену', ...extra })
const page = (items) => ({ data: items, current_page: 1, last_page: 1, total: items.length, per_page: 10 })
function mount(t, Component, props = {}) {
    const store = useAvitoStore(createPinia())
    globalThis.__waitingHarness = { store, subscriptions: [] }
    const originalDocument = globalThis.document
    const originalWindow = globalThis.window
    globalThis.document = { visibilityState: 'hidden', addEventListener() {}, removeEventListener() {}, hasFocus: () => false }
    globalThis.window = { addEventListener() {}, removeEventListener() {} }
    const root = {}
    const events = { errors: [], waiting: [], updated: [] }
    const render = (nextProps = props) => {
        const vnode = h(Component, { ...nextProps, onError: (error) => events.errors.push(error),
            onWaitingChange: (value) => events.waiting.push(value), onChatUpdated: (value) => events.updated.push(value) })
        renderer.render(vnode, root)
        return vnode.component.setupState
    }
    const state = render()
    const unmount = () => renderer.render(null, root)
    t.after(() => {
        unmount()
        store.$dispose()
        globalThis.document = originalDocument
        globalThis.window = originalWindow
        delete globalThis.__waitingHarness
    })
    return { state, store, events, render, unmount }
}

test('dashboard adds, edits and removes a waiter without replacing unrelated messenger state', async (t) => {
    let rows = []
    const calls = []
    t.mock.method(axios, 'get', async (url) => ({ data: page(url.endsWith('/waiting-list') ? rows : [chat()]) }))
    const originalAdapter = axios.defaults.adapter
    t.after(() => { axios.defaults.adapter = originalAdapter })
    axios.defaults.adapter = async (config) => {
        const body = config.data ? JSON.parse(config.data) : undefined
        calls.push([config.method.toUpperCase(), config.url, body])
        rows = config.method === 'delete' ? [] : [chat(7, { waiting_note: body.note })]
        return { data: { chat: rows[0] || chat(7, { waiting_since: null, waiting_note: null }) },
            status: 200, statusText: 'OK', headers: {}, config }
    }
    const { state, store } = mount(t, WaitingList)
    store.chats = [chat(99)]
    store.filters.search = 'unrelated messenger search'
    await until(() => !state.loading)
    state.pickerOpen = true
    state.pickerSelected = chat(7, { waiting_since: null })
    state.addNote = 'Запросить стоимость доставки'
    await state.addChat()
    assert.equal(state.waiters[0].waiting_note, 'Запросить стоимость доставки')
    state.openChat(state.waiters[0])
    state.startEditing(state.waiters[0])
    state.noteDraft = 'Ответить после согласования'
    await state.saveNote(state.waiters[0])
    assert.equal(state.waiters[0].waiting_note, 'Ответить после согласования')
    assert.equal(state.selectedChat.waiting_note, 'Ответить после согласования')
    await state.removeChat(state.waiters[0])
    assert.deepEqual(state.waiters, [])
    assert.deepEqual(store.chats.map((item) => item.id), [99])
    assert.equal(store.filters.search, 'unrelated messenger search')
    assert.deepEqual(calls.map((item) => item[0]), ['PUT', 'PATCH', 'DELETE'])
})

test('dashboard ignores obsolete and unmounted list responses and preserves an open note draft', async (t) => {
    const requests = []
    t.mock.method(axios, 'get', (_url, options) => {
        const pending = deferred()
        requests.push({ ...pending, options })
        return pending.promise
    })
    const { state, unmount } = mount(t, WaitingList)
    await until(() => requests.length === 1)
    const current = state.loadWaiters(1)
    requests[1].resolve({ data: page([chat(8)]) })
    await current
    requests[0].resolve({ data: page([chat(7)]) })
    await flush()
    assert.deepEqual(state.waiters.map((item) => item.id), [8])
    state.openChat(state.waiters[0])
    state.startEditing(state.waiters[0])
    state.noteDraft = 'Несохранённая заметка'
    const refresh = state.loadWaiters()
    requests[2].resolve({ data: page([chat(8, { waiting_note: 'Другая заметка' })]) })
    await refresh
    assert.equal(state.selectedChat.id, 8)
    assert.equal(state.noteDraft, 'Несохранённая заметка')
    const late = state.loadWaiters()
    unmount()
    assert.equal(requests[3].options.signal.aborted, true)
    requests[3].resolve({ data: page([chat(9)]) })
    await late
    assert.deepEqual(state.waiters.map((item) => item.id), [8])
})

test('embedded correspondence opens only its requested chat and sends a reply without clearing waiting', async (t) => {
    const calls = []
    t.mock.method(axios, 'get', async (url) => {
        calls.push(url)
        return { data: { chat: chat(), messages: page([{ id: 1, text: 'Вопрос', direction: 'in' }]) } }
    })
    t.mock.method(axios, 'post', async (url, body) => {
        assert.equal(url, '/api/avito/messenger/chats/7/messages')
        assert.equal(body.text, 'Отвечаю прямо из листа ожидания')
        return { data: { item: { id: 2, text: body.text, direction: 'out' } } }
    })
    const { state, store, events } = mount(t, Messages, { embedded: true, chat: chat() })
    await until(() => !store.loading)
    assert.deepEqual(calls, ['/api/avito/messenger/chats/7'])
    store.composerText = 'Отвечаю прямо из листа ожидания'
    await state.sendText()
    assert.equal(store.messages.at(-1).text, 'Отвечаю прямо из листа ожидания')
    assert.equal(store.composerText, '')
    assert.equal(store.selectedChat.waiting_since, chat().waiting_since)
    assert.deepEqual(events.errors, [])
    assert.deepEqual(calls, ['/api/avito/messenger/chats/7'])
})

test('waiting action completing after a chat switch cannot replace the new conversation or draft', async (t) => {
    const pending = deferred()
    t.mock.method(axios, 'get', async (url) => ({ data: { chat: chat(Number(url.split('/').at(-1))), messages: page([]) } }))
    t.mock.method(axios, 'delete', () => pending.promise)
    const { state, store, render, events } = mount(t, Messages, { embedded: true, chat: chat() })
    await until(() => !store.loading)
    const removing = state.toggleWaiting()
    render({ embedded: true, chat: chat(8) })
    await nextTick()
    await until(() => !store.chatLoading)
    store.composerText = 'Черновик для второго чата'
    pending.resolve({ data: { chat: chat(7, { waiting_since: null, waiting_note: null }) } })
    await removing
    assert.equal(store.selectedChat.id, 8)
    assert.equal(store.selectedChat.waiting_since, chat(8).waiting_since)
    assert.equal(store.composerText, 'Черновик для второго чата')
    assert.equal(events.waiting[0].id, 7)
})

test('full embedded chat sends templates, updates its table summary and propagates read receipts without loading all chats', async (t) => {
    let summary = chat(7, { is_unread: true, unread_count: 1, messages_count: 1 })
    const calls = []
    t.mock.method(axios, 'get', async (url, options) => {
        calls.push({ url, params: options.params })
        return { data: url.endsWith('/updates')
            ? { selected: { chat: summary, messages: [] } }
            : { chat: summary, messages: page([{ id: 1, text: 'Вопрос', direction: 'in', is_read: false }]) } }
    })
    t.mock.method(axios, 'post', async (url, body) => {
        if (url.endsWith('/read')) {
            summary = { ...summary, is_unread: false, unread_count: 0 }
            return { data: { chat: summary, read_through_id: 2 } }
        }
        assert.equal(url, '/api/avito/messenger/chats/7/messages')
        assert.equal(body.template_id, 24)
        summary = { ...summary, messages_count: 2, last_message_preview: body.text }
        return { data: { item: { id: 2, text: body.text, direction: 'out' } } }
    })
    const props = { embedded: true, fullFeatured: true, chat: chat() }
    const { state, store, events, render } = mount(t, Messages, props)
    await until(() => !store.loading)
    assert.equal(state.toolsEnabled, true)
    await state.insertMessageTemplate({ text: 'Ответ по шаблону', template_id: 24, template_name: 'Ответ' })
    await state.sendText()
    assert.equal(events.updated.at(-1).last_message_preview, 'Ответ по шаблону')
    assert.equal(events.updated.at(-1).messages_count, 2)
    assert.equal(store.composerTemplateId, null)
    assert.deepEqual(store.messages.map((item) => item.id), [1, 2])
    await state.markRead()
    await nextTick()
    assert.equal(events.updated.at(-1).is_unread, false)
    assert.equal(store.messages[0].is_read, true)
    assert.deepEqual(calls.map((item) => item.url), [
        '/api/avito/messenger/chats/7', '/api/avito/messenger/updates', '/api/avito/messenger/updates',
    ])
    assert.equal(calls[1].params.chats, 0)
    assert.equal(calls[1].params.overview, 0)
    const updateCount = events.updated.length
    render({ ...props, chat: { ...events.updated.at(-1) } })
    await nextTick()
    assert.equal(events.updated.length, updateCount, 'Returning the updated chat prop must not create a feedback loop')
    assert.deepEqual(events.errors, [])
})

test('full embedded realtime only refreshes its own conversation and preserves a reply draft', async (t) => {
    const calls = []
    t.mock.method(axios, 'get', async (url) => {
        calls.push(url)
        return { data: url.endsWith('/updates')
            ? { selected: { chat: chat(7, { is_unread: true, unread_count: 1 }), messages: [{ id: 2, text: 'Новое сообщение' }] } }
            : { chat: chat(), messages: page([{ id: 1, text: 'История' }]) } }
    })
    const { state, store, events } = mount(t, Messages, { embedded: true, fullFeatured: true, chat: chat() })
    await until(() => !store.loading)
    store.composerText = 'Мой черновик'
    const options = { reason: 'change', signal: new AbortController().signal }
    await state.reloadRealtime({ ...options, events: [{ changes: { chat_ids: [99], message_ids: [88] } }] })
    assert.equal(calls.length, 1)
    await state.reloadRealtime({ ...options, events: [{ changes: { chat_ids: [7], message_ids: [2] } }] })
    assert.equal(calls.length, 2)
    assert.equal(store.composerText, 'Мой черновик')
    assert.deepEqual(store.messages.map((item) => item.id), [1, 2])
    assert.equal(events.updated.at(-1).unread_count, 1)
})

test('chat dialog clears previous feedback when reopened for another entity', async (t) => {
    const { state, render } = mount(t, ChatDialog, { modelValue: true, chat: chat(), entityName: 'ООО Ромашка' })
    assert.equal(state.title, 'ООО Ромашка')
    state.showFeedback('Не удалось отправить сообщение', true)
    assert.equal(state.feedbackError, true)
    render({ modelValue: true, chat: chat(8), entityName: 'ООО Вектор' })
    await nextTick()
    assert.equal(state.feedback, '')
    assert.equal(state.title, 'ООО Вектор')
})
