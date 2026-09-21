/** Delivery days are calendar values, never instants that need timezone conversion. */
export function formatDeliveryDate(value, empty = 'Не назначена') {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''))
    return match ? `${match[3]}.${match[2]}.${match[1]}` : empty
}

export function calendarDay(offset = 0, now = new Date()) {
    const day = new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset, 12)
    return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`
}

export function deliveryDateQuery(date, unscheduled = false) {
    if (unscheduled) return { delivery_unscheduled: 1 }
    return date ? { delivery_date: date } : {}
}
