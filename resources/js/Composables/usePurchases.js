import { ref } from 'vue'
import axios from 'axios'

export function usePurchases() {
    const items = ref([])
    const item = ref(null)
    const loading = ref(false)
    const errors = ref({})
    const pagination = ref({
        total: 0,
        per_page: 15,
        current_page: 1,
        last_page: 1,
    })

    const fetchPurchases = async (params = {}) => {
        loading.value = true
        try {
            const { data } = await axios.get('/api/purchases', { params })
            items.value = data.data
            if (data.meta) {
                pagination.value = {
                    total: data.meta.total,
                    per_page: data.meta.per_page,
                    current_page: data.meta.current_page,
                    last_page: data.meta.last_page,
                }
            }
        } finally {
            loading.value = false
        }
    }

    const fetchPurchase = async (id) => {
        loading.value = true
        try {
            const { data } = await axios.get(`/api/purchases/${id}`)
            item.value = data.data
            return data.data
        } finally {
            loading.value = false
        }
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

    return {
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
