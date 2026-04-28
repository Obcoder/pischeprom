import { ref } from 'vue'
import axios from 'axios'

export function useEmailMeta() {
    const metaLoading = ref(false)
    const units = ref([])
    const entities = ref([])
    const domains = ref([])

    async function fetchMeta() {
        metaLoading.value = true

        try {
            const { data } = await axios.get('/api/emails/meta')

            units.value = data.units ?? []
            entities.value = data.entities ?? []
            domains.value = data.domains ?? []
        } catch (error) {
            console.error('Email meta loading error:', error)
        } finally {
            metaLoading.value = false
        }
    }

    return {
        metaLoading,
        units,
        entities,
        domains,
        fetchMeta,
    }
}
