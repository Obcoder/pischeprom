export function orderTotals(order) {
    if (order?.totals_by_currency && Object.keys(order.totals_by_currency).length) return order.totals_by_currency
    if (order?.total_amount != null) return { [order.currency_code || 'RUB']: order.total_amount }
    if (order?.items?.length) {
        return order.items.reduce((totals, item) => {
            const currency = item.currency_code || order.currency_code || 'RUB'
            totals[currency] = (totals[currency] || 0) + (Number(item.line_total) || 0)
            return totals
        }, {})
    }
    return { [order?.currency_code || 'RUB']: order?.total_amount ?? 0 }
}
