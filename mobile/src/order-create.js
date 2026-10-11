export function orderNumber(value) {
    if (typeof value === 'string' && !/^\d+(?:[.,]\d+)?$/.test(value.trim())) return NaN
    if (value === null || value === undefined || value === '') return NaN
    return Number(typeof value === 'string' ? value.trim().replace(',', '.') : value)
}

export function orderLineTotal(item) {
    const unitPrice = Math.round((orderNumber(item.unit_price) + Number.EPSILON) * 10000) / 10000
    const total = orderNumber(item.quantity) * unitPrice
    return Number.isFinite(total) ? Math.round((total + Number.EPSILON) * 10000) / 10000 : null
}

export function orderTotals(items) {
    return items.reduce((totals, item) => {
        const total = orderLineTotal(item)
        if (total === null) return totals
        const currency = item.currency_code || 'RUB'
        totals[currency] = Math.round(((totals[currency] || 0) + total + Number.EPSILON) * 10000) / 10000
        return totals
    }, {})
}

export function validateNewOrder(form) {
    const errors = {}
    if (!form.entity_id) errors.entity_id = 'Выберите покупателя.'
    if (!form.order_status_id) errors.order_status_id = 'Выберите статус заказа.'
    if (!form.delivery_date) errors.delivery_date = 'Укажите дату доставки.'
    if (!form.items.length) errors.items = 'Добавьте хотя бы один товар.'
    form.items.forEach((item, index) => {
        const quantity = orderNumber(item.quantity)
        if (!Number.isFinite(quantity) || quantity < 0.001 || quantity > 999999999) {
            errors[`items.${index}.quantity`] = 'Укажите количество от 0,001 до 999 999 999.'
        } else if ((String(item.quantity).split(/[.,]/)[1] || '').length > 3) {
            errors[`items.${index}.quantity`] = 'В количестве допустимо до трёх знаков после запятой.'
        }
        if (!Number.isFinite(orderNumber(item.unit_price)) || orderNumber(item.unit_price) < 0) {
            errors[`items.${index}.unit_price`] = 'Укажите цену не меньше нуля.'
        }
        if (!item.currency_code) errors[`items.${index}.currency_code`] = 'Выберите валюту.'
    })
    return errors
}

export function newOrderPayload(form) {
    return {
        entity_id: form.entity_id,
        order_status_id: form.order_status_id,
        delivery_date: form.delivery_date || null,
        contact_telephone_id: form.contact_telephone_id || null,
        building_ids: [...form.building_ids],
        building_apartments: Object.fromEntries(form.building_ids.map(id => [id, form.building_apartments[id] || null])),
        preferred_delivery_time: form.preferred_delivery_time.trim() || null,
        internal_comment: form.internal_comment.trim() || null,
        items: form.items.map(item => ({
            good_id: item.good_id,
            quantity: orderNumber(item.quantity),
            unit_price: orderNumber(item.unit_price),
            currency_code: item.currency_code,
            measure_id: item.measure_id,
            measurement: item.measurement,
        })),
    }
}

/** Retry an uncertain creation with the same operation and immutable payload. */
export function createOrderOperation(api, uuid = () => crypto.randomUUID()) {
    let pending = null
    let inFlight = null

    function send(payload) {
        if (inFlight) return inFlight
        if (!pending) pending = { ...JSON.parse(JSON.stringify(payload)), request_id: uuid() }
        inFlight = Promise.resolve()
            .then(() => api.createOrder(JSON.parse(JSON.stringify(pending))))
            .then(result => {
                if (!result?.data?.id || !Array.isArray(result.data.items)) {
                    throw new Error('Сервер не подтвердил создание заказа. Повторите подтверждение.')
                }
                pending = null
                return result
            })
            .catch(error => {
                if (error.status >= 400 && error.status < 500 && ![408, 425, 429].includes(error.status)) pending = null
                throw error
            })
            .finally(() => { inFlight = null })
        return inFlight
    }

    return { send, hasPending: () => pending !== null }
}
