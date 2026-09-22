import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

const message = (id = 1) => ({ id, subject: `Письмо ${id}`, direction: 'incoming' })

function harness(t, { confirm = true, selected = message() } = {}) {
    const filename = 'resources/js/Components/Contacts/Emails/MailMessagesPage.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'mail-message-deletion-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'mail-message-deletion-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])

    const requests = [], confirmations = [], refreshed = [], cleared = [], disposal = []
    const mail = {
        messages: Vue.ref([message(), message(2)]),
        totalItems: Vue.ref(2),
        selectedMessage: Vue.ref(selected),
        loading: Vue.ref(false),
        reading: Vue.ref(false),
        markingReadIds: Vue.ref([]),
        markReadError: Vue.ref(''),
        markReadStatus: Vue.ref(''),
        fetchError: Vue.ref(''),
        mailboxes: Vue.ref([]),
        search: Vue.ref(''),
        filters: Vue.ref({ direction: null, folder: null, mailbox: null, today: false, date_from: null, date_to: null }),
        options: Vue.ref({ page: 1, itemsPerPage: 100 }),
        async fetchMailboxes() {},
        async fetchMessages() { refreshed.push(mail.messages.value.map(item => item.id)) },
        async readMessage() {},
        async markMessageRead() {},
        clearSelectedMessage() { cleared.push(mail.selectedMessage.value?.id); mail.selectedMessage.value = null },
    }
    const environment = {
        ...Vue,
        onMounted() {},
        onUnmounted: callback => disposal.push(callback),
        useMailMessages: () => mail,
        mailDateRangeLabel: () => '',
        MailMessagesToolbar: {}, MailDateFilter: {}, MailMessagesTable: {}, MailMessageReaderDialog: {},
        MailComposerDialog: {}, MailTemplatesDialog: {}, MailboxesManagerDialog: {},
        window: { confirm(text) { confirmations.push(text); return confirm } },
        axios: {
            delete(url) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, resolve: data => resolve({ data }), reject })
                return promise
            },
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup({ standalone: true, timezone: 'Europe/Moscow' }, { expose() {} }))
    state.readerDialog.value = Boolean(selected)
    t.after(() => { disposal.forEach(callback => callback()); scope.stop() })
    return { state, mail, requests, confirmations, refreshed, cleared }
}

test('canceling deletion explains both locations and leaves the message untouched', async t => {
    const { state, mail, requests, confirmations, refreshed, cleared } = harness(t, { confirm: false })
    await state.deleteMessage(message())

    assert.equal(confirmations.length, 1)
    assert.match(confirmations[0], /приложени/i)
    assert.match(confirmations[0], /почтов.{0,20}сервер/i)
    assert.equal(requests.length, 0)
    assert.deepEqual(mail.messages.value.map(item => item.id), [1, 2])
    assert.equal(mail.totalItems.value, 2)
    assert.equal(state.readerDialog.value, true)
    assert.equal(mail.selectedMessage.value.id, 1)
    assert.deepEqual(state.deletingIds.value, [])
    assert.equal(refreshed.length, 0)
    assert.equal(cleared.length, 0)
})

test('repeated deletion clicks while pending show one confirmation and send one DELETE', async t => {
    const { state, mail, requests, confirmations, refreshed } = harness(t)
    const pending = state.deleteMessage(message())
    await state.deleteMessage(message())

    assert.equal(confirmations.length, 1)
    assert.equal(requests.length, 1)
    assert.equal(requests[0].url, '/api/mail-messages/1')
    assert.deepEqual(state.deletingIds.value, [1])
    assert.deepEqual(mail.messages.value.map(item => item.id), [1, 2])
    assert.equal(mail.totalItems.value, 2)
    assert.equal(state.readerDialog.value, true)
    assert.equal(refreshed.length, 0)

    requests[0].resolve({ message: 'Письмо удалено.' })
    await pending
    assert.deepEqual(state.deletingIds.value, [])
})

test('a server failure keeps the row and reader and allows an explicit retry', async t => {
    const { state, mail, requests, confirmations, refreshed, cleared } = harness(t)
    const pending = state.deleteMessage(message())
    requests[0].reject({ response: { data: { message: 'Почтовый сервер недоступен. Письмо сохранено.' } } })
    await pending

    assert.equal(state.deleteError.value, 'Почтовый сервер недоступен. Письмо сохранено.')
    assert.equal(state.deleteStatus.value, '')
    assert.deepEqual(mail.messages.value.map(item => item.id), [1, 2])
    assert.equal(mail.totalItems.value, 2)
    assert.equal(mail.selectedMessage.value.id, 1)
    assert.equal(state.readerDialog.value, true)
    assert.deepEqual(state.deletingIds.value, [])
    assert.equal(refreshed.length, 0)
    assert.equal(cleared.length, 0)

    const retry = state.deleteMessage(message())
    assert.equal(confirmations.length, 2)
    assert.equal(requests.length, 2)
    assert.equal(state.deleteError.value, '')
    requests[1].resolve({ message: 'Письмо удалено после повторной попытки.' })
    await retry
    assert.deepEqual(mail.messages.value.map(item => item.id), [2])
    assert.deepEqual(state.deletingIds.value, [])
    assert.equal(refreshed.length, 1)
})

test('confirmed deletion uses the server notice, removes the row, closes its reader and refreshes', async t => {
    const { state, mail, requests, refreshed, cleared } = harness(t)
    const pending = state.deleteMessage(message())
    requests[0].resolve({ message: 'Письмо удалено из приложения и с почтового сервера.' })
    await pending

    assert.deepEqual(mail.messages.value.map(item => item.id), [2])
    assert.equal(mail.totalItems.value, 1)
    assert.equal(mail.selectedMessage.value, null)
    assert.equal(state.readerDialog.value, false)
    assert.deepEqual(cleared, [1])
    assert.equal(state.deleteStatus.value, 'Письмо удалено из приложения и с почтового сервера.')
    assert.equal(state.deleteError.value, '')
    assert.deepEqual(refreshed, [[2]])
    assert.deepEqual(state.deletingIds.value, [])
})

test('deleting another message preserves the open reader and provides a fallback notice', async t => {
    const { state, mail, requests, refreshed, cleared } = harness(t, { selected: message(2) })
    const pending = state.deleteMessage(message())
    requests[0].resolve({})
    await pending

    assert.deepEqual(mail.messages.value.map(item => item.id), [2])
    assert.equal(mail.totalItems.value, 1)
    assert.equal(mail.selectedMessage.value.id, 2)
    assert.equal(state.readerDialog.value, true)
    assert.deepEqual(cleared, [])
    assert.ok(state.deleteStatus.value.length > 0)
    assert.deepEqual(refreshed, [[2]])
})
