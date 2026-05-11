import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useGoodPriceCalculations(goodId) {
    const calculations = ref([])
    const loading = ref(false)
    const saving = ref(false)
    const deleting = ref({})

    async function fetchCalculations() {
        loading.value = true

        try {
            const { data } = await axios.get(route('api.goods.price-calculations.index', goodId))
            calculations.value = Array.isArray(data) ? data : []
            return calculations.value
        } finally {
            loading.value = false
        }
    }

    async function storeCalculation(payload) {
        saving.value = true

        try {
            const { data } = await axios.post(
                route('api.goods.price-calculations.store', goodId),
                payload
            )

            calculations.value.unshift(data)

            return data
        } finally {
            saving.value = false
        }
    }

    async function updateCalculation(calculationId, payload) {
        saving.value = true

        try {
            const { data } = await axios.patch(
                route('api.goods.price-calculations.update', {
                    good: goodId,
                    calculation: calculationId,
                }),
                payload
            )

            calculations.value = calculations.value.map((item) =>
                item.id === data.id ? data : item
            )

            return data
        } finally {
            saving.value = false
        }
    }

    async function deleteCalculation(calculationId) {
        deleting.value[calculationId] = true

        try {
            await axios.delete(
                route('api.goods.price-calculations.destroy', {
                    good: goodId,
                    calculation: calculationId,
                })
            )

            calculations.value = calculations.value.filter((item) => item.id !== calculationId)
        } finally {
            deleting.value[calculationId] = false
        }
    }

    return {
        calculations,
        loading,
        saving,
        deleting,
        fetchCalculations,
        storeCalculation,
        updateCalculation,
        deleteCalculation,
    }
}
