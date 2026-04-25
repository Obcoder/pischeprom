import { ref, reactive } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useCities() {
    const cities = ref([])
    const totalItems = ref(0)
    const loading = ref(false)

    const options = ref({
        page: 1,
        itemsPerPage: 25,
        sortBy: [
            {
                key: 'population',
                order: 'desc',
            },
        ],
    })

    const filters = reactive({
        search: '',
        country_id: null,
        region_id: null,
        unit_id: null,
        entity_id: null,
        has_buildings: null,
    })

    async function fetchCities(newOptions = null) {
        if (newOptions) {
            options.value = {
                ...options.value,
                ...newOptions,
            }
        }

        loading.value = true

        try {
            const response = await axios.get(route('cities.index'), {
                params: {
                    page: options.value.page,
                    itemsPerPage: options.value.itemsPerPage,
                    sortBy: options.value.sortBy,
                    search: filters.search,
                    country_id: filters.country_id,
                    region_id: filters.region_id,
                    unit_id: filters.unit_id,
                    entity_id: filters.entity_id,
                    has_buildings: filters.has_buildings,
                },
            })

            cities.value = response.data.items
            totalItems.value = response.data.total
        } catch (error) {
            console.error('Cities loading error:', error)
        } finally {
            loading.value = false
        }
    }

    async function storeCity(payload) {
        const response = await axios.post(route('cities.store'), payload)
        await fetchCities()
        return response.data
    }

    async function updateCity(cityId, payload) {
        const response = await axios.patch(route('cities.update', cityId), payload)
        await fetchCities()
        return response.data
    }

    async function deleteCity(cityId) {
        await axios.delete(route('cities.destroy', cityId))
        await fetchCities()
    }

    function resetFilters() {
        filters.search = ''
        filters.country_id = null
        filters.region_id = null
        filters.unit_id = null
        filters.entity_id = null
        filters.has_buildings = null

        options.value.page = 1
    }

    return {
        cities,
        totalItems,
        loading,
        options,
        filters,
        fetchCities,
        storeCity,
        updateCity,
        deleteCity,
        resetFilters,
    }
}
