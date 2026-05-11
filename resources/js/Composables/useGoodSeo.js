import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useGoodSeo(goodId) {
    const seo = ref(null)
    const loading = ref(false)
    const saving = ref(false)

    async function fetchSeo() {
        loading.value = true

        try {
            const { data } = await axios.get(route('api.goods.seo.show', goodId))
            seo.value = data
            return data
        } finally {
            loading.value = false
        }
    }

    async function saveSeo(payload) {
        saving.value = true

        try {
            const { data } = await axios.put(route('api.goods.seo.upsert', goodId), payload)
            seo.value = data
            return data
        } finally {
            saving.value = false
        }
    }

    return {
        seo,
        loading,
        saving,
        fetchSeo,
        saveSeo,
    }
}
