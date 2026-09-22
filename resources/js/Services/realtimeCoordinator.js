export const COMMERCE_TOPICS = ['sales', 'goods_stock', 'purchases', 'warehouses', 'commodity_stock']

// Reads follow events, subscriptions, and browser lifecycle changes. Recovery timers only reconnect WebSocket.
export function createRealtimeCoordinator({
    connect,
    onStatus = () => {},
    onRefreshed = () => {},
    onError = () => {},
    isVisible = () => true,
    isOnline = () => true,
    setTimer = setTimeout,
    clearTimer = clearTimeout,
    debounceMs = 150,
    minRefreshIntervalMs = 0,
    reconnectDelayMs = 1000,
    maxReconnectDelayMs = 30000,
    subscriptionTimeoutMs = 30000,
    now = Date.now,
    allowedTopics = COMMERCE_TOPICS,
}) {
    const resources = new Map()
    const seenEvents = new Set()
    let configuration = null
    let configurationKey = ''
    let disconnect = null
    let generation = 0
    let started = false
    let subscribed = false
    let forbidden = false
    let retryTimer = null
    let subscriptionTimer = null
    let retryDelay = reconnectDelayMs

    function clearSubscriptionTimer() {
        if (subscriptionTimer !== null) clearTimer(subscriptionTimer)
        subscriptionTimer = null
    }

    function stop() {
        generation++
        started = false
        subscribed = false
        if (retryTimer !== null) clearTimer(retryTimer)
        retryTimer = null
        clearSubscriptionTimer()
        disconnect?.()
        disconnect = null
    }

    function retryConnection() {
        stop()
        if (forbidden || !configuration?.enabled || !resources.size || !isOnline()) return
        retryTimer = setTimer(() => {
            retryTimer = null
            void start()
        }, retryDelay)
        retryDelay = Math.min(retryDelay * 2, maxReconnectDelayMs)
    }

    function awaitSubscription(currentGeneration) {
        if (subscriptionTimer !== null) return
        subscriptionTimer = setTimer(() => {
            subscriptionTimer = null
            if (generation !== currentGeneration || subscribed) return
            onStatus('error')
            retryConnection()
        }, subscriptionTimeoutMs)
    }

    function denyAccess() {
        forbidden = true
        onStatus('forbidden')
        for (const resource of resources.values()) {
            resource.controller?.abort()
            if (resource.timer !== null) clearTimer(resource.timer)
            resource.timer = null
            resource.pending = false
        }
        stop()
    }

    function schedule(resource, reason, event = null) {
        resource.pending = true
        resource.reason = reason
        if (reason !== 'change') resource.overflow = true
        if (event) {
            if (resource.events.size < 200) resource.events.set(event.event_id, event)
            else resource.overflow = true
        }
        if (resource.running || resource.timer !== null || !isVisible() || !isOnline() || forbidden) return
        resource.timer = setTimer(() => {
            resource.timer = null
            void refresh(resource)
        }, Math.max(debounceMs, resource.nextRefreshAt - now()))
    }

    async function refresh(resource) {
        if (!resource.active || !resource.pending || !isVisible() || !isOnline() || forbidden) return
        resource.pending = false
        resource.running = true
        resource.nextRefreshAt = now() + minRefreshIntervalMs
        const events = [...resource.events.values()]
        const overflow = resource.overflow
        resource.events.clear()
        resource.overflow = false
        const controller = new AbortController()
        resource.controller = controller
        try {
            await resource.load({ signal: controller.signal, reason: resource.reason, events, overflow })
            if (resource.active && !controller.signal.aborted) onRefreshed(resource.id)
        } catch (error) {
            if (resource.active && !controller.signal.aborted && error?.code !== 'ERR_CANCELED') {
                // A later event must also recover changes from this failed batch.
                resource.overflow = true
                onError(resource.id)
                if ([401, 403, 419].includes(error?.response?.status)) denyAccess()
                if (error?.response?.status === 429) {
                    const retryAfter = Number(error.response.headers?.['retry-after'] || error.response.data?.retry_after || 60)
                    resource.nextRefreshAt = now() + Math.max(1, Number.isFinite(retryAfter) ? retryAfter : 60) * 1000
                }
            }
        } finally {
            resource.running = false
            resource.controller = null
            if (resource.active && resource.pending) schedule(resource, resource.reason)
        }
    }

    function refreshAll(reason) {
        for (const resource of resources.values()) schedule(resource, reason)
    }

    function receive(event) {
        if (!event || typeof event.event_id !== 'string' || event.event_id.length > 64 || !Array.isArray(event.topics)) return
        const topics = event.topics.filter((topic) => allowedTopics.includes(topic))
        if (!topics.length || seenEvents.has(event.event_id)) return
        seenEvents.add(event.event_id)
        if (seenEvents.size > 256) seenEvents.delete(seenEvents.values().next().value)
        for (const resource of resources.values()) {
            if (topics.some((topic) => resource.topics.has(topic))) schedule(resource, 'change', event)
        }
    }

    async function start() {
        if (started || retryTimer !== null || forbidden || !configuration?.enabled || !resources.size) return
        if (!isOnline()) {
            onStatus('offline')
            return
        }
        started = true
        const currentGeneration = ++generation
        onStatus('connecting')
        awaitSubscription(currentGeneration)
        try {
            const close = await connect(configuration, {
                ready() {
                    if (generation !== currentGeneration) return
                    clearSubscriptionTimer()
                    retryDelay = reconnectDelayMs
                    subscribed = true
                    onStatus('live')
                    // Includes first subscription: covers a change between the initial GET and subscribing.
                    refreshAll('connected')
                },
                event(event) {
                    if (generation === currentGeneration && subscribed) receive(event)
                },
                status(status) {
                    if (generation !== currentGeneration) return
                    if (status !== 'live') subscribed = false
                    onStatus(status)
                    if (status === 'forbidden') {
                        denyAccess()
                    } else if (status === 'error' || status === 'offline') {
                        retryConnection()
                    } else if (status === 'connecting') {
                        awaitSubscription(currentGeneration)
                    }
                },
            })
            if (generation !== currentGeneration) close?.()
            else disconnect = close
        } catch (error) {
            if (generation === currentGeneration) {
                if ([401, 403, 419].includes(error?.response?.status)) denyAccess()
                else {
                    onStatus('error')
                    retryConnection()
                }
            }
        }
    }

    function configure(settings, actorId) {
        const next = settings?.enabled && actorId ? { ...settings, actorId: String(actorId) } : null
        const key = JSON.stringify(next)
        if (key === configurationKey) return
        stop()
        retryDelay = reconnectDelayMs
        for (const resource of resources.values()) {
            resource.controller?.abort()
            if (resource.timer !== null) clearTimer(resource.timer)
            resource.timer = null
            resource.pending = false
            resource.events.clear()
            resource.overflow = false
            resource.nextRefreshAt = 0
        }
        configuration = next
        configurationKey = key
        forbidden = false
        seenEvents.clear()
        if (!next) onStatus('disabled')
        else void start()
    }

    function subscribe(id, topics, load) {
        const resource = { id, topics: new Set(topics), load, active: true, pending: false, running: false,
            timer: null, controller: null, events: new Map(), overflow: false, nextRefreshAt: 0 }
        resources.set(id, resource)
        if (subscribed) schedule(resource, 'connected')
        else void start()
        return () => {
            resource.active = false
            resource.controller?.abort()
            if (resource.timer !== null) clearTimer(resource.timer)
            resources.delete(id)
            if (!resources.size) stop()
        }
    }

    function visibilityChanged() {
        if (!isVisible()) return
        if (subscribed) refreshAll('visible')
        else void start()
    }

    function onlineChanged() {
        if (!isOnline()) {
            stop()
            onStatus('offline')
            for (const resource of resources.values()) resource.controller?.abort()
        } else void start()
    }

    function reconnect() {
        stop()
        retryDelay = reconnectDelayMs
        forbidden = false
        void start()
    }

    function dispose() {
        stop()
        for (const resource of resources.values()) {
            resource.active = false
            resource.controller?.abort()
            if (resource.timer !== null) clearTimer(resource.timer)
        }
        resources.clear()
        seenEvents.clear()
    }

    return { configure, subscribe, visibilityChanged, onlineChanged, reconnect, dispose }
}
