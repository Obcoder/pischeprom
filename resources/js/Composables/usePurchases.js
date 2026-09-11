import { onBeforeUnmount, ref, toRefs } from 'vue'
import axios from 'axios'
import { useRealtimeResource } from '@/Composables/useRealtimeResource.js'

export function usePurchases({ onRefresh } = {}) {
    const resource = useRealtimeResource({
        key: 'purchases',
        initialValue: {
            items: [],
            item: null,
            pagination: { total: 0, per_page: 100, current_page: 1, last_page: 1 },
        },
        topics: ['purchases'],
        load: ({ signal }) => onRefresh?.({ signal }),
    })
    const { items, item, pagination } = toRefs(resource.state)
    const loading = ref(false)
    const errors = ref({})
    let listRequestId = 0
    let detailRequestId = 0

    const fetchPurchases = async (params = {}, { background = false, signal } = {}) => {
        signal ||= resource.signal
        const requestId = ++listRequestId
        if (!background) loading.value = true
        try {
            const { data } = await axios.get('/api/purchases', { params, signal })
            if (requestId !== listRequestId || signal?.aborted) return false
            items.value = data.data || []
            if (data.meta) {
                pagination.value = {
                    total: data.meta.total || 0,
                    per_page: data.meta.per_page || 100,
                    current_page: data.meta.current_page || 1,
                    last_page: data.meta.last_page || 1,
                }
            }
            return true
        } catch (error) {
            if (requestId !== listRequestId || signal?.aborted || axios.isCancel(error)) return false
            throw error
        } finally {
            if (requestId === listRequestId) loading.value = false
        }
    }

    const fetchPurchase = async (id, { signal } = {}) => {
        signal ||= resource.signal
        const requestId = ++detailRequestId
        const { data } = await axios.get(`/api/purchases/${id}`, { signal })
        if (requestId === detailRequestId && !signal?.aborted) item.value = data.data
        return data.data
    }

    const createPurchase = async (payload) => {
        errors.value = {}
        const { data } = await axios.post('/api/purchases', payload)
        return data.data
    }

    const updatePurchase = async (id, payload) => {
        errors.value = {}
        const { data } = await axios.put(`/api/purchases/${id}`, payload)
        return data.data
    }

    const deletePurchase = async (id) => {
        await axios.delete(`/api/purchases/${id}`)
    }

    onBeforeUnmount(() => {
        listRequestId++
        detailRequestId++
    })

    return {
        resource,
        items,
        item,
        loading,
        errors,
        pagination,
        fetchPurchases,
        fetchPurchase,
        createPurchase,
        updatePurchase,
        deletePurchase,
    }
}
