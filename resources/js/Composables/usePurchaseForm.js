import { computed, reactive } from 'vue'

const emptyItem = () => ({
    good_id: null,
    quantity: 1,
    measure_id: null,
    price: 0,
    currency_id: null,
    total: 0,
})

export function usePurchaseForm() {
    const form = reactive({
        id: null,
        date: '',
        entity_id: null,
        amount: 0,
        items: [emptyItem()],
    })

    const isEdit = computed(() => Boolean(form.id))

    const recalcItem = (item) => {
        const quantity = Number(item.quantity || 0)
        const price = Number(item.price || 0)
        item.total = +(quantity * price).toFixed(2)
    }

    const recalcAmount = () => {
        form.amount = +form.items
            .reduce((sum, item) => sum + Number(item.total || 0), 0)
            .toFixed(2)
    }

    const resetForm = () => {
        form.id = null
        form.date = ''
        form.entity_id = null
        form.amount = 0
        form.items = [emptyItem()]
    }

    const fillForm = (purchase) => {
        form.id = purchase.id
        form.date = purchase.date
        form.entity_id = purchase.entity?.id ?? null
        form.amount = purchase.amount ?? 0
        form.items = (purchase.items?.length ? purchase.items : [emptyItem()]).map(item => ({
            good_id: item.good_id,
            quantity: Number(item.quantity ?? 1),
            measure_id: item.measure_id ?? null,
            price: Number(item.price ?? 0),
            currency_id: item.currency_id ?? null,
            total: Number(item.total ?? 0),
        }))
    }

    const payload = computed(() => ({
        date: form.date,
        entity_id: form.entity_id,
        amount: Number(form.amount || 0),
        items: form.items
            .filter(item => item.good_id)
            .map(item => ({
                good_id: item.good_id,
                quantity: Number(item.quantity || 0),
                measure_id: item.measure_id || null,
                price: Number(item.price || 0),
                currency_id: item.currency_id || null,
            })),
    }))

    return {
        form,
        isEdit,
        resetForm,
        fillForm,
        payload,
        recalcItem,
        recalcAmount,
        emptyItem,
    }
}
