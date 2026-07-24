export function goodAvailabilityStatus(good = {}, availability = null) {
    const payload = availability && Object.keys(availability).length
        ? availability
        : good?.availability || {}

    if (payload.status) {
        return payload.status
    }

    const stockState = good?.stock_availability || good?.stockAvailability

    if (typeof stockState?.is_in_stock === 'boolean') {
        return stockState.is_in_stock ? 'in_stock' : 'out_of_stock'
    }

    return good?.seo?.availability_status || 'on_request'
}

export function canSubscribeToGoodStock(good = {}, availability = null) {
    const payload = availability && Object.keys(availability).length
        ? availability
        : good?.availability || {}

    if (typeof payload.can_subscribe === 'boolean') {
        return payload.can_subscribe
    }

    return ['out_of_stock', 'on_request'].includes(
        goodAvailabilityStatus(good, availability),
    )
}
