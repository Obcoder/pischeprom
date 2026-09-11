export const COMMERCE_TOPICS = ['sales', 'goods_stock', 'purchases', 'warehouses', 'commodity_stock']

// Only incoming events or browser lifecycle changes schedule reads. There is no polling timer.
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

    function stop() {
        generation++
        started = false
        subscribed = false
        disconnect?.()
        disconnect = null
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

    function schedule(resource, reason) {
        resource.pending = true
        resource.reason = reason
        if (resource.running || resource.timer !== null || !isVisible() || !isOnline() || forbidden) return
        resource.timer = setTimer(() => {
            resource.timer = null
            void refresh(resource)
        }, debounceMs)
    }

    async function refresh(resource) {
        if (!resource.active || !resource.pending || !isVisible() || !isOnline() || forbidden) return
        resource.pending = false
        resource.running = true
        const controller = new AbortController()
        resource.controller = controller
        try {
            await resource.load({ signal: controller.signal, reason: resource.reason })
            if (resource.active && !controller.signal.aborted) onRefreshed(resource.id)
        } catch (error) {
            if (resource.active && !controller.signal.aborted && error?.code !== 'ERR_CANCELED') {
                onError(resource.id)
                if ([401, 403, 419].includes(error?.response?.status)) denyAccess()
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
        const topics = event.topics.filter((topic) => COMMERCE_TOPICS.includes(topic))
        if (!topics.length || seenEvents.has(event.event_id)) return
        seenEvents.add(event.event_id)
        if (seenEvents.size > 256) seenEvents.delete(seenEvents.values().next().value)
        for (const resource of resources.values()) {
            if (topics.some((topic) => resource.topics.has(topic))) schedule(resource, 'change')
        }
    }

    async function start() {
        if (started || forbidden || !configuration?.enabled || !resources.size) return
        if (!isOnline()) {
            onStatus('offline')
            return
        }
        started = true
        const currentGeneration = ++generation
        onStatus('connecting')
        try {
            const close = await connect(configuration, {
                ready() {
                    if (generation !== currentGeneration) return
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
                    }
                },
            })
            if (generation !== currentGeneration) close?.()
            else disconnect = close
        } catch {
            if (generation === currentGeneration) {
                started = false
                onStatus('error')
            }
        }
    }

    function configure(settings, actorId) {
        const next = settings?.enabled && actorId ? { ...settings, actorId: String(actorId) } : null
        const key = JSON.stringify(next)
        if (key === configurationKey) return
        stop()
        for (const resource of resources.values()) {
            resource.controller?.abort()
            if (resource.timer !== null) clearTimer(resource.timer)
            resource.timer = null
            resource.pending = false
        }
        configuration = next
        configurationKey = key
        forbidden = false
        seenEvents.clear()
        if (!next) onStatus('disabled')
        else void start()
    }

    function subscribe(id, topics, load) {
        const resource = { id, topics: new Set(topics), load, active: true, pending: false, running: false, timer: null, controller: null }
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
