import { reactive, computed } from 'vue'

export function usePurchaseForm() {
    const form = reactive({
        id: null,
        date: '',
        entity_id: null,
        amount: 0,
        goods: [],
    })

    const isEdit = computed(() => !!form.id)

    const resetForm = () => {
        form.id = null
        form.date = ''
        form.entity_id = null
        form.amount = 0
        form.goods = []
    }

    const fillForm = (purchase) => {
        form.id = purchase.id
        form.date = purchase.date
        form.entity_id = purchase.entity?.id ?? null
        form.amount = purchase.amount ?? 0
        form.goods = (purchase.goods || []).map(good => ({
            id: good.id,
            name: good.name,
        }))
    }

    const payload = computed(() => ({
        date: form.date,
        entity_id: form.entity_id,
        amount: Number(form.amount || 0),
        goods: form.goods.map(g => ({ id: g.id })),
    }))

    return {
        form,
        isEdit,
        resetForm,
        fillForm,
        payload,
    }
}
