import { ref } from 'vue'
import { useTelephonesApi } from './useTelephonesApi'

export function useTelephoneMeta() {
    const api = useTelephonesApi()

    const loadingMeta = ref(false)
    const entities = ref([])
    const units = ref([])

    const fetchMeta = async () => {
        loadingMeta.value = true

        try {
            const data = await api.getMeta()
            entities.value = data.entities || []
            units.value = data.units || []
        } finally {
            loadingMeta.value = false
        }
    }

    return {
        loadingMeta,
        entities,
        units,
        fetchMeta,
    }
}
