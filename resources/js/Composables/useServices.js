import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useServices() {
    const services = ref([])
    const loadingServices = ref(false)
    const savingService = ref(false)
    const deletingService = ref(false)
    const totalItems = ref(0)

    const options = ref({
        page: 1,
        itemsPerPage: 50,
        sortBy: [
            {
                key: 'name',
                order: 'asc',
            },
        ],
    })

    const filters = ref({
        search: '',
        check_id: null,
        expense_article_id: null,
        project_id: null,
        is_active: 'all',
        created_from: null,
        created_to: null,
    })

    function getSortParams() {
        const sort = options.value.sortBy?.[0]

        return {
            sort_by: sort?.key || 'name',
            sort_desc: (sort?.order || 'asc') === 'desc',
        }
    }

    async function indexServices(newOptions = null) {
        if (newOptions) {
            options.value = {
                ...options.value,
                ...newOptions,
            }
        }

        loadingServices.value = true

        try {
            const response = await axios.get(route('services.index'), {
                params: {
                    page: options.value.page,
                    per_page: options.value.itemsPerPage || 50,

                    search: filters.value.search || null,
                    check_id: filters.value.check_id || null,
                    expense_article_id: filters.value.expense_article_id || null,
                    project_id: filters.value.project_id || null,
                    is_active: activeFilterValue(),
                    created_from: filters.value.created_from || null,
                    created_to: filters.value.created_to || null,

                    ...getSortParams(),
                },
            })

            services.value = response.data.data || []
            totalItems.value = response.data.meta?.total || 0
        } catch (error) {
            console.error('indexServices error:', error)
            throw error
        } finally {
            loadingServices.value = false
        }
    }

    async function storeService(payload) {
        savingService.value = true

        try {
            const response = await axios.post(route('services.store'), payload)

            return response.data.data || response.data
        } catch (error) {
            console.error('storeService error:', error)
            throw error
        } finally {
            savingService.value = false
        }
    }

    async function updateService(id, payload) {
        savingService.value = true

        try {
            const response = await axios.patch(route('services.update', id), payload)

            return response.data.data || response.data
        } catch (error) {
            console.error('updateService error:', error)
            throw error
        } finally {
            savingService.value = false
        }
    }

    async function destroyService(id) {
        deletingService.value = true

        try {
            await axios.delete(route('services.destroy', id))
        } catch (error) {
            console.error('destroyService error:', error)
            throw error
        } finally {
            deletingService.value = false
        }
    }

    function resetServiceFilters() {
        filters.value = {
            search: '',
            check_id: null,
            expense_article_id: null,
            project_id: null,
            is_active: 'all',
            created_from: null,
            created_to: null,
        }

        options.value.page = 1
    }

    function activeFilterValue() {
        return filters.value.is_active === null || filters.value.is_active === ''
            ? 'all'
            : filters.value.is_active
    }

    return {
        services,
        loadingServices,
        savingService,
        deletingService,
        totalItems,
        options,
        filters,

        indexServices,
        storeService,
        updateService,
        destroyService,
        resetServiceFilters,
    }
}
