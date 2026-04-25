import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useRegions() {
    const regions = ref([])
    const loadingRegions = ref(false)

    async function fetchRegions() {
        loadingRegions.value = true

        try {
            const response = await axios.get(route('regions.index'), {
                params: {
                    itemsPerPage: -1,
                },
            })

            regions.value = Array.isArray(response.data)
                ? response.data
                : response.data.items ?? response.data.data ?? []
        } catch (error) {
            console.error('Regions loading error:', error)
        } finally {
            loadingRegions.value = false
        }
    }

    return {
        regions,
        loadingRegions,
        fetchRegions,
    }
}
