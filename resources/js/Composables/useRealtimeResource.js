import { computed, onMounted, onScopeDispose, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useCommerceStore } from '../Stores/commerce.js'

export function useRealtimeResource({ key, initialValue, topics, load }) {
    const page = usePage()
    const store = useCommerceStore()
    const id = store.createResource(key, initialValue)
    const state = store.resources[id]
    let actor = page.props.auth?.user?.id
    let controller = new AbortController()
    let unsubscribe = () => {}
    let stopWatching = () => {}
    onMounted(() => {
        stopWatching = watch(
            () => [page.props.realtime, page.props.auth?.user?.id],
            ([settings, actorId]) => {
                if (actor !== actorId) {
                    controller.abort()
                    controller = new AbortController()
                    actor = actorId
                }
                store.configure(settings, actorId)
            },
            { immediate: true, deep: true, flush: 'sync' },
        )
        unsubscribe = store.subscribe(id, topics, load)
    })
    onScopeDispose(() => {
        controller.abort()
        stopWatching()
        unsubscribe()
        store.removeResource(id)
    })
    return {
        state,
        get signal() { return controller.signal },
        status: computed(() => store.status),
        lastUpdatedAt: computed(() => store.updatedAt[id] || null),
        refreshFailed: computed(() => Boolean(store.refreshErrors[id])),
    }
}
