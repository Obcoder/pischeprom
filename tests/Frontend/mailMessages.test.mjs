import test from 'node:test'
import assert from 'node:assert/strict'
import { effectScope } from 'vue'
import axios from 'axios'
import { useMailMessages } from '../../resources/js/Composables/useMailMessages.js'

function mailbox(t) {
    const scope = effectScope()
    const state = scope.run(useMailMessages)
    t.after(() => scope.stop())
    return state
}

function deferred() {
    let resolve
    const promise = new Promise((done) => { resolve = done })
    return { promise, resolve }
}

test('opening an email does not mark it read on the server', async (t) => {
    const message = { id: 7, is_seen: false, subject: 'Прайс' }
    const post = t.mock.method(axios, 'post', async () => assert.fail('No mutation is allowed while opening mail'))
    const get = t.mock.method(axios, 'get', async () => ({ data: message }))
    const state = mailbox(t)
    state.messages.value = [message]

    await state.readMessage(message)

    assert.equal(state.selectedMessage.value.is_seen, false)
    assert.equal(state.messages.value[0].is_seen, false)
    assert.equal(post.mock.callCount(), 0)

    const loadedAt = '2026-09-22T09:00:00Z'
    get.mock.mockImplementation(async () => ({ data: { ...message, is_seen: true, body_loaded_at: loadedAt } }))
    await state.readMessage(message)

    assert.equal(state.selectedMessage.value.is_seen, true)
    assert.equal(state.messages.value[0].is_seen, true)
    assert.equal(state.messages.value[0].body_loaded_at, loadedAt)
    assert.equal(post.mock.callCount(), 0)
})

test('a manual read action waits for the server, prevents duplicate requests, and updates both views', async (t) => {
    const pending = deferred()
    const post = t.mock.method(axios, 'post', () => pending.promise)
    const state = mailbox(t)
    const message = { id: 7, is_seen: false, subject: 'Прайс' }
    state.messages.value = [message]
    state.selectedMessage.value = { ...message, body_text: 'Содержание' }

    const marking = state.markMessageRead(message)
    await state.markMessageRead(message)
    assert.equal(post.mock.callCount(), 1)
    assert.deepEqual(post.mock.calls[0].arguments, ['/api/mail-messages/7/mark-read'])
    assert.deepEqual(state.markingReadIds.value, [7])
    assert.equal(state.messages.value[0].is_seen, false)

    pending.resolve({ data: { id: 7, is_seen: true } })
    await marking

    assert.equal(state.messages.value[0].is_seen, true)
    assert.equal(state.messages.value[0].subject, 'Прайс')
    assert.equal(state.selectedMessage.value.is_seen, true)
    assert.equal(state.selectedMessage.value.body_text, 'Содержание')
    assert.deepEqual(state.markingReadIds.value, [])
    assert.equal(state.markReadError.value, '')
    assert.match(state.markReadStatus.value, /на сервере/)
})

test('a server error keeps unread state and allows retry', async (t) => {
    t.mock.method(axios, 'post', async () => { throw { response: { data: { message: 'IMAP недоступен' } } } })
    const state = mailbox(t)
    const message = { id: 7, is_seen: false }
    state.messages.value = [message]

    await state.markMessageRead(message)

    assert.equal(state.messages.value[0].is_seen, false)
    assert.deepEqual(state.markingReadIds.value, [])
    assert.equal(state.markReadError.value, 'IMAP недоступен')
    assert.equal(state.markReadStatus.value, '')
})

test('a list request started before marking mail cannot overwrite the confirmed read flag', async (t) => {
    const stale = deferred()
    const get = t.mock.method(axios, 'get', () => stale.promise)
    t.mock.method(axios, 'post', async () => ({ data: { id: 7, is_seen: true } }))
    const state = mailbox(t)
    state.messages.value = [{ id: 7, is_seen: false }]

    const fetching = state.fetchMessages()
    await state.markMessageRead(state.messages.value[0])
    get.mock.mockImplementation(async () => ({ data: { data: [{ id: 7, is_seen: true }], total: 1 } }))
    stale.resolve({ data: { data: [{ id: 7, is_seen: false }], total: 1 } })
    await fetching

    assert.equal(get.mock.callCount(), 2)
    assert.equal(state.messages.value[0].is_seen, true)
    assert.equal(state.loading.value, false)
})

test('the latest list request wins when earlier responses arrive later', async (t) => {
    const first = deferred()
    const second = deferred()
    const get = t.mock.method(axios, 'get', () => first.promise)
    const state = mailbox(t)
    const older = state.fetchMessages()
    get.mock.mockImplementation(() => second.promise)
    const newer = state.fetchMessages()

    second.resolve({ data: { data: [{ id: 2 }], total: 1 } })
    await newer
    first.resolve({ data: { data: [{ id: 1 }], total: 40 } })
    await older

    assert.deepEqual(state.messages.value, [{ id: 2 }])
    assert.equal(state.totalItems.value, 1)
    assert.equal(state.loading.value, false)
})
