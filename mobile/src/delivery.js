export function telephoneHref(telephone) {
    const number = String(telephone?.dial_number || '').trim()
    return /^\+?\d{3,20}$/.test(number) ? `tel:${number}` : null
}

export function deliveryBuildingAddress(address) {
    return String(address?.building_address || address?.full_address || address?.address || '').trim()
}

export function deliveryMapUrl(address) {
    if (address?.yandex_maps_url) {
        try {
            const url = new URL(address.yandex_maps_url)
            if (url.protocol === 'https:' && ['yandex.ru', 'maps.yandex.ru'].includes(url.hostname)
                && !url.username && !url.password) return url.href
        } catch { /* A saved address can still be opened as a search. */ }
    }
    const query = deliveryBuildingAddress(address)
    return query ? `https://yandex.ru/maps/?text=${encodeURIComponent(query)}` : null
}
