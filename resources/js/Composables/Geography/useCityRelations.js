import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useCityRelations() {
    const units = ref([])
    const entities = ref([])

    const loadingUnits = ref(false)
    const loadingEntities = ref(false)

    async function fetchUnits() {
        loadingUnits.value = true

        try {
            const response = await axios.get(route('units.index'), {
                params: {
                    itemsPerPage: -1,
                },
            })

            units.value = Array.isArray(response.data)
                ? response.data
                : response.data.items ?? response.data.data ?? []
        } catch (error) {
            console.error('Units loading error:', error)
        } finally {
            loadingUnits.value = false
        }
    }

    async function fetchEntities() {
        loadingEntities.value = true

        try {
            const response = await axios.get(route('entities.index'), {
                params: {
                    itemsPerPage: -1,
                },
            })

            entities.value = Array.isArray(response.data)
                ? response.data
                : response.data.items ?? response.data.data ?? []
        } catch (error) {
            console.error('Entities loading error:', error)
        } finally {
            loadingEntities.value = false
        }
    }

    return {
        units,
        entities,
        loadingUnits,
        loadingEntities,
        fetchUnits,
        fetchEntities,
    }
}
