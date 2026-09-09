import { computed, reactive } from 'vue'

const emptyItem = () => ({
    good_id: null,
    quantity: 1,
    measure_id: null,
    price: '',
    currency_id: null,
    total: '',
    calculationSource: 'price',
})

const decimalValue = (value) => {
    if (value === '' || value === null || value === undefined) {
        return null
    }

    if (typeof value === 'string' && value.trim() === '') {
        return null
    }

    const number = Number(typeof value === 'string' ? value.replace(',', '.') : value)

    return Number.isFinite(number) ? number : null
}

const rounded = (value, precision) => Number(value.toFixed(precision))

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
        const quantity = decimalValue(item.quantity)
        const price = decimalValue(item.price)

        if (quantity === null || quantity <= 0 || price === null || price < 0) {
            item.total = ''
            return
        }

        item.total = rounded(quantity * price, 2)
    }

    const recalcPrice = (item) => {
        const quantity = decimalValue(item.quantity)
        const total = decimalValue(item.total)

        if (quantity === null || quantity <= 0 || total === null || total < 0) {
            item.price = ''
            return
        }

        item.price = rounded(total / quantity, 6)
    }

    const recalcAmount = () => {
        const totals = form.items
            .map(item => decimalValue(item.total))
            .filter(total => total !== null)

        form.amount = totals.length
            ? rounded(totals.reduce((sum, total) => sum + total, 0), 2)
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
            calculationSource: 'price',
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
                quantity: decimalValue(item.quantity) ?? 0,
                measure_id: item.measure_id || null,
                price: decimalValue(item.price) ?? 0,
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
        recalcPrice,
        recalcAmount,
        emptyItem,
    }
}
