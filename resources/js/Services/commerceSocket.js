import axios from 'axios'

export async function connectCommerceSocket(config, handlers) {
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
        import('laravel-echo'),
        import('pusher-js'),
    ])
    const requests = new Set()
    let closed = false
    const client = new Pusher(config.key, {
        cluster: 'mt1',
        wsHost: config.host,
        wsPort: Number(config.port),
        wssPort: Number(config.port),
        wsPath: config.path,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        channelAuthorization: {
            customHandler({ socketId, channelName }, callback) {
                const controller = new AbortController()
                requests.add(controller)
                axios.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channelName,
                }, { signal: controller.signal, timeout: 10000 })
                    .then(({ data }) => { if (!closed) callback(null, data) })
                    .catch((error) => {
                        if (closed || controller.signal.aborted) return
                        if ([401, 403, 419].includes(error?.response?.status)) handlers.status('forbidden')
                        callback(new Error('Private channel authorization failed'), null)
                    })
                    .finally(() => requests.delete(controller))
            },
        },
    })
    const echo = new Echo({ broadcaster: 'reverb', client, key: config.key })
    const stateChanged = ({ current }) => {
        if (closed) return
        if (current === 'connecting') handlers.status('connecting')
        if (['disconnected', 'unavailable', 'failed'].includes(current)) handlers.status('offline')
        // Connected is not yet authorized/subscribed; only channel.subscribed marks it live.
    }
    client.connection.bind('state_change', stateChanged)
    echo.private(config.channel)
        .listen('.commerce.changed', (event) => { if (!closed) handlers.event(event) })
        .subscribed(() => { if (!closed) handlers.ready() })
        .error((error) => {
            if (closed) return
            handlers.status([401, 403, 419].includes(Number(error?.status)) ? 'forbidden' : 'error')
        })

    return () => {
        closed = true
        for (const controller of requests) controller.abort()
        requests.clear()
        client.connection.unbind('state_change', stateChanged)
        echo.leave(config.channel)
        echo.disconnect()
    }
}
