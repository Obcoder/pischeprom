import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useCountries() {
    const countries = ref([])
    const loadingCountries = ref(false)

    async function fetchCountries() {
        loadingCountries.value = true

        try {
            const response = await axios.get(route('countries.index'), {
                params: {
                    itemsPerPage: -1,
                },
            })

            countries.value = Array.isArray(response.data)
                ? response.data
                : response.data.items ?? response.data.data ?? []
        } catch (error) {
            console.error('Countries loading error:', error)
        } finally {
            loadingCountries.value = false
        }
    }

    return {
        countries,
        loadingCountries,
        fetchCountries,
    }
}
