/** Keeps an uncertain shipment's key until the server gives a definite answer. */
export function createShipmentOperation(api, uuid = () => crypto.randomUUID()) {
    const attempts = new Map()
    const inFlight = new Map()

    function send(order) {
        const id = String(order.id)
        if (inFlight.has(id)) return inFlight.get(id)
        const version = String(order.version)
        const previous = attempts.get(id)
        const attempt = previous?.version === version ? previous : { version, requestId: uuid() }
        attempts.set(id, attempt)

        const promise = Promise.resolve()
            .then(() => api.ship(order.id, { version: order.version, request_id: attempt.requestId }))
            .then(result => {
                if (!result?.data?.id || !result.data.sale || result.data.workflow_status !== 'shipped') {
                    throw new Error('Сервер не подтвердил отгрузку. Обновите заказ или повторите запрос.')
                }
                attempts.delete(id)
                return result
            })
            .catch(error => {
                // Network failures, invalid responses and 5xx can follow a committed transaction.
                if (error.status >= 400 && error.status < 500 && ![408, 425, 429].includes(error.status)) {
                    attempts.delete(id)
                }
                throw error
            })
            .finally(() => inFlight.delete(id))

        inFlight.set(id, promise)
        return promise
    }

    return {
        send,
        hasPending(order) { return attempts.get(String(order.id))?.version === String(order.version) },
        clear() { attempts.clear() },
    }
}

export function validatePreparation(order, rows) {
    if (!order?.items?.length || rows.length !== order.items.length) return 'В заказе нет позиций для сборки.'
    for (const item of order.items) {
        const row = rows.find(candidate => candidate.id === item.id)
        if (!row?.checked) return 'Сверьте количество по каждой позиции.'
        const quantity = Number(String(row.quantity).replace(',', '.'))
        if (!Number.isFinite(quantity) || quantity <= 0 || Math.abs(quantity - Number(item.quantity)) > 0.0000001) {
            return 'Для полной отгрузки количество должно совпадать с заказом.'
        }
        const measure = item.measure_options?.find(option => String(option.id) === String(row.measure_id))
        if (!measure) return 'Выберите единицу измерения для каждой позиции.'
        if (Number(measure.available_quantity) + 0.0000001 < quantity) return `Недостаточный остаток: ${item.name}.`
    }
    return null
}
