import { defineStore } from 'pinia'
import { onScopeDispose, reactive, ref } from 'vue'
import { createRealtimeCoordinator } from '../Services/realtimeCoordinator.js'
import { connectCommerceSocket } from '../Services/commerceSocket.js'

export const useCommerceStore = defineStore('commerce', () => {
    // State belongs to this Pinia instance, including a separate instance for every SSR request.
    const resources = reactive({})
    const updatedAt = reactive({})
    const refreshErrors = reactive({})
    const status = ref('disabled')
    let nextResourceId = 0
    let actor = null
    const initialStates = new Map()
    const browser = typeof window !== 'undefined' && typeof document !== 'undefined'
    const coordinator = createRealtimeCoordinator({
        connect: connectCommerceSocket,
        isVisible: () => browser && document.visibilityState !== 'hidden',
        isOnline: () => browser && navigator.onLine !== false,
        onStatus: (value) => { status.value = value },
        onRefreshed: (id) => {
            updatedAt[id] = Date.now()
            delete refreshErrors[id]
        },
        onError: (id) => { refreshErrors[id] = true },
    })

    function configure(settings, actorId) {
        const nextActor = actorId ? String(actorId) : null
        if (actor !== null && actor !== nextActor) {
            for (const [id, initial] of initialStates) {
                for (const key of Object.keys(resources[id] || {})) delete resources[id][key]
                Object.assign(resources[id], structuredClone(initial))
                delete updatedAt[id]
                delete refreshErrors[id]
            }
        }
        actor = nextActor
        if (browser) coordinator.configure(settings, actorId)
    }

    function createResource(key, initialValue) {
        const id = `${key}:${++nextResourceId}`
        initialStates.set(id, structuredClone(initialValue))
        resources[id] = structuredClone(initialValue)
        return id
    }

    function subscribe(id, topics, load) {
        if (!browser) return () => {}
        return coordinator.subscribe(id, topics, load)
    }

    function removeResource(id) {
        delete resources[id]
        delete updatedAt[id]
        delete refreshErrors[id]
        initialStates.delete(id)
    }

    function reconnect() {
        if (browser) coordinator.reconnect()
    }

    if (browser) {
        document.addEventListener('visibilitychange', coordinator.visibilityChanged)
        window.addEventListener('online', coordinator.onlineChanged)
        window.addEventListener('offline', coordinator.onlineChanged)
    }
    onScopeDispose(() => {
        coordinator.dispose()
        if (browser) {
            document.removeEventListener('visibilitychange', coordinator.visibilityChanged)
            window.removeEventListener('online', coordinator.onlineChanged)
            window.removeEventListener('offline', coordinator.onlineChanged)
        }
    })

    return { resources, updatedAt, refreshErrors, status, configure, createResource, subscribe, removeResource, reconnect }
})
