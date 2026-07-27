import { computed, reactive } from 'vue'

const emptyItem = () => ({
    good_id: null,
    quantity: 1,
    measure_id: null,
    price: '',
    currency_id: null,
    total: '',
})

export function usePurchaseForm() {
    const form = reactive({
        id: null,
        date: '',
        entity_id: null,
        amount: '',
        items: [emptyItem()],
    })

    const isEdit = computed(() => Boolean(form.id))

    const recalcItem = (item) => {
        const hasQuantity = item.quantity !== '' && item.quantity !== null && item.quantity !== undefined
        const hasPrice = item.price !== '' && item.price !== null && item.price !== undefined

        if (!hasQuantity || !hasPrice) {
            item.total = ''
            return
        }

        const quantity = Number(item.quantity)
        const price = Number(item.price)

        if (!Number.isFinite(quantity) || !Number.isFinite(price)) {
            item.total = ''
            return
        }

        item.total = +(quantity * price).toFixed(2)
    }

    const recalcAmount = () => {
        const totals = form.items
            .map(item => item.total)
            .filter(total => total !== '' && total !== null && total !== undefined)
            .map(Number)
            .filter(Number.isFinite)

        form.amount = totals.length
            ? +totals.reduce((sum, total) => sum + total, 0).toFixed(2)
            : ''
    }

    const resetForm = () => {
        form.id = null
        form.date = ''
        form.entity_id = null
        form.amount = ''
        form.items = [emptyItem()]
    }

    const fillForm = (purchase) => {
        form.id = purchase.id
        form.date = purchase.date
        form.entity_id = purchase.entity?.id ?? null
        form.amount = purchase.amount ?? ''
        form.items = (purchase.items?.length ? purchase.items : [emptyItem()]).map(item => ({
            good_id: item.good_id,
            quantity: Number(item.quantity ?? 1),
            measure_id: item.measure_id ?? null,
            price: item.price === null || item.price === undefined ? '' : Number(item.price),
            currency_id: item.currency_id ?? null,
            total: item.total === null || item.total === undefined ? '' : Number(item.total),
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
