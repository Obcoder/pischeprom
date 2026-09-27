import test from 'node:test'
import assert from 'node:assert/strict'
import { setTimeout as delay } from 'node:timers/promises'
import axios from 'axios'
import Pusher from 'pusher-js'
import { connectCommerceSocket } from '../../resources/js/Services/commerceSocket.js'

test('closing a subscribed socket does not send unsubscribe after WebSocket starts closing', { timeout: 2000 }, async (t) => {
    const sent = []
    const sentAfterClose = []
    let socket
    t.mock.method(Pusher.Runtime, 'createWebSocket', () => {
        socket = {
            readyState: 0,
            send(value) {
                const event = JSON.parse(value)
                if (this.readyState !== 1) {
                    sentAfterClose.push(event)
                    return
                }
                sent.push(event)
                if (event.event === 'pusher:subscribe') {
                    this.onmessage?.({ data: JSON.stringify({
                        event: 'pusher_internal:subscription_succeeded',
                        channel: event.data.channel,
                        data: {},
                    }) })
                }
            },
            close() {
                this.readyState = 2
                // Browsers finish closing asynchronously; Pusher also defers sends.
                setTimeout(() => {
                    this.readyState = 3
                    this.onclose?.({ code: 1000, wasClean: true })
                }, 10)
            },
        }
        setTimeout(() => {
            socket.readyState = 1
            socket.onopen?.()
            socket.onmessage?.({ data: JSON.stringify({
                event: 'pusher:connection_established',
                data: JSON.stringify({ socket_id: '1.2', activity_timeout: 30 }),
            }) })
        }, 0)
        return socket
    })
    t.mock.method(axios, 'post', async () => ({ data: { auth: 'test-signature' } }))
    let subscribed
    const ready = new Promise((resolve) => { subscribed = resolve })
    const statuses = []
    const close = await connectCommerceSocket({
        key: 'test-key', host: 'app.test', port: 443,
        scheme: 'https', path: '/realtime', channel: 'avito.updates', event: 'avito.changed',
    }, {
        ready: subscribed,
        status: (status) => statuses.push(status),
        event() {},
    })
    t.after(close)
    await ready
    assert.equal(sent[0].event, 'pusher:subscribe')
    assert.equal(sent[0].data.channel, 'private-avito.updates')

    close()
    await delay(25)

    assert.equal(socket.readyState, 3)
    assert.deepEqual(sentAfterClose, [])
    assert.deepEqual(statuses, [])
})
