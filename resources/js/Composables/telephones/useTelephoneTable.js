import { ref, reactive, watch } from 'vue'
import { useTelephonesApi } from './useTelephonesApi'

export function useTelephoneTable() {
    const api = useTelephonesApi()

    const loading = ref(false)
    const items = ref([])
    const totalItems = ref(0)

    const options = reactive({
        page: 1,
        itemsPerPage: 10,
        sortBy: [{ key: 'id', order: 'desc' }],
    })

    const filters = reactive({
        search: '',
        entity_ids: [],
        unit_ids: [],
    })

    const fetchItems = async () => {
        loading.value = true

        try {
            const sort = options.sortBy?.[0] || { key: 'id', order: 'desc' }

            const response = await api.getList({
                page: options.page,
                per_page: options.itemsPerPage,
                sort_by: sort.key || 'id',
                sort_order: sort.order || 'desc',
                search: filters.search,
                entity_ids: filters.entity_ids,
                unit_ids: filters.unit_ids,
            })

            items.value = response.data
            totalItems.value = response.total
        } finally {
            loading.value = false
        }
    }

    watch(
        () => [
            options.page,
            options.itemsPerPage,
            JSON.stringify(options.sortBy),
            filters.search,
            JSON.stringify(filters.entity_ids),
            JSON.stringify(filters.unit_ids),
        ],
        () => {
            fetchItems()
        },
        { immediate: true }
    )

    const resetPage = () => {
        options.page = 1
    }

    return {
        loading,
        items,
        totalItems,
        options,
        filters,
        fetchItems,
        resetPage,
    }
}
