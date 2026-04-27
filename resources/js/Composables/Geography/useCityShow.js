import { computed, ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useCityShow(initialCityId = null, initialCity = null) {
    const cityId = ref(initialCityId ?? initialCity?.id ?? null)
    const city = ref(initialCity)
    const loading = ref(false)

    const latestPopulation = computed(() => {
        return city.value?.latest_population?.population
            ?? city.value?.latestPopulation?.population
            ?? city.value?.population
            ?? null
    })

    const latestPopulationYear = computed(() => {
        return city.value?.latest_population?.year
            ?? city.value?.latestPopulation?.year
            ?? null
    })

    async function fetchCity(id = cityId.value) {
        if (!id) {
            return
        }

        cityId.value = id
        loading.value = true

        try {
            const response = await axios.get(route('cities.show', id))
            city.value = response.data
        } catch (error) {
            console.error('City show loading error:', error)
        } finally {
            loading.value = false
        }
    }

    async function updateCity(payload) {
        if (!cityId.value) {
            return null
        }

        const response = await axios.patch(route('cities.update', cityId.value), payload)
        city.value = response.data

        return response.data
    }

    return {
        cityId,
        city,
        loading,
        latestPopulation,
        latestPopulationYear,
        fetchCity,
        updateCity,
    }
}
