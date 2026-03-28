import { ref } from 'vue'
import axios from 'axios'

export function useUnits() {
    const units = ref([])
    const loadingUnits = ref(false)
    const unitsError = ref(null)

    const extractItems = (response) => {
        if (Array.isArray(response?.data)) {
            return response.data
        }

        if (Array.isArray(response?.data?.data)) {
            return response.data.data
        }

        return []
    }

    const indexUnits = async () => {
        loadingUnits.value = true
        unitsError.value = null

        try {
            const response = await axios.get('/api/units')

            const rawUnits = extractItems(response)

            // нормализуем под v-select
            units.value = rawUnits.map(unit => ({
                id: unit.id,
                name: unit.name,
            }))

        } catch (error) {
            console.error('Ошибка загрузки units:', error)
            unitsError.value = error
            units.value = []
        } finally {
            loadingUnits.value = false
        }
    }

    return {
        units,
        loadingUnits,
        unitsError,
        indexUnits,
    }
}
