export function emptyMailOffer() {
    return { items: [], logistics: null }
}

const optionalNumber = (value) => value === '' || value === null || value === undefined ? null : Number(value)

// Catalog metadata is for the editor only; the server resolves product facts again.
export function mailOfferPayload(offer) {
    const items = (offer?.items || []).map((item) => ({
        good_id: item.good_id,
        quantity: optionalNumber(item.quantity),
        price_override: optionalNumber(item.price_override),
        include_description: Boolean(item.include_description),
        include_specifications: Boolean(item.include_specifications),
        include_image: item.include_image !== false,
        ...(item.include_specifications && Array.isArray(item.specifications) ? {
            specifications: item.specifications
                .filter(({ label, value }) => String(label || '').trim() || String(value || '').trim())
                .map(({ label, value }) => ({ label, value })),
        } : {}),
    }))
    const logistics = offer?.logistics ? {
        origin: offer.logistics.origin || '',
        destination: offer.logistics.destination || '',
        note: offer.logistics.note || '',
        options: (offer.logistics.options || []).map((option) => ({
            name: option.name || '',
            price: optionalNumber(option.price),
            currency_code: option.currency_code || 'RUB',
            duration: option.duration || '',
            note: option.note || '',
        })),
    } : null

    return items.length || logistics ? { items, logistics } : null
}
