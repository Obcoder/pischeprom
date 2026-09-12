import { ref, toValue } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useGoodSeo(goodId) {
    const seo = ref(null)
    const loading = ref(false)
    const saving = ref(false)
    const generating = ref(false)
    const aiGenerating = ref(false)

    async function fetchSeo() {
        loading.value = true

        try {
            const { data } = await axios.get(route('api.goods.seo.show', toValue(goodId)))
            seo.value = data
            return data
        } finally {
            loading.value = false
        }
    }

    async function saveSeo(payload) {
        saving.value = true

        try {
            const { data } = await axios.put(route('api.goods.seo.upsert', toValue(goodId)), payload)
            seo.value = data
            return data
        } finally {
            saving.value = false
        }
    }

    async function generateStructuredData() {
        generating.value = true

        try {
            const { data } = await axios.post(route('api.goods.seo.generate-structured-data', toValue(goodId)))
            seo.value = data
            return data
        } finally {
            generating.value = false
        }
    }

    async function generateAi(field, context) {
        aiGenerating.value = true

        try {
            const { data } = await axios.post(
                `/api/goods/${toValue(goodId)}/seo/generate-ai`,
                { field, context },
                { timeout: 90000 },
            )
            if (data.field !== field || typeof data.value !== 'string' || !data.value.trim()) {
                throw new Error('AI вернул пустой или некорректный ответ. Попробуйте ещё раз.')
            }
            return data.value
        } finally {
            aiGenerating.value = false
        }
    }

    return {
        seo,
        loading,
        saving,
        generating,
        aiGenerating,
        fetchSeo,
        saveSeo,
        generateStructuredData,
        generateAi,
    }
}
