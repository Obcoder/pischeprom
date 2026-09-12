import { onMounted, onScopeDispose, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useAvitoStore } from '../Stores/avito.js'

export function useAvitoRealtime({ key = 'avito', topics = [], load } = {}) {
    const page = usePage()
    const store = useAvitoStore()
    let stopWatching = () => {}
    let unsubscribe = () => {}
    let releaseControl = () => {}
    onMounted(() => {
        stopWatching = watch(() => [page.props.avitoRealtime, page.props.auth?.user?.id],
            ([settings, actorId]) => store.configure(settings, actorId),
            { immediate: true, deep: true, flush: 'sync' })
        releaseControl = store.retainControl()
        if (load) unsubscribe = store.subscribe(key, topics, load)
    })
    onScopeDispose(() => {
        stopWatching()
        unsubscribe()
        releaseControl()
    })
    return store
}
