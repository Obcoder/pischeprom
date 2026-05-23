function isBrowser() {
    return typeof window !== 'undefined'
}

function hasYandexMetrica() {
    return isBrowser() && typeof window.ym === 'function'
}

function normalizeCounterId(counterId) {
    if (counterId === null || counterId === undefined || counterId === '') {
        return null
    }

    return Number(counterId)
}

export function useYandexMetrica(counterId = null) {
    const id = normalizeCounterId(counterId)

    function callYm(...args) {
        if (!id || !hasYandexMetrica()) {
            return
        }

        try {
            window.ym(id, ...args)
        } catch (error) {
            if (import.meta.env.DEV) {
                console.warn('[YandexMetrica] ym call failed:', error)
            }
        }
    }

    function reachGoal(goalName, params = {}) {
        if (!goalName) {
            return
        }

        callYm('reachGoal', goalName, params)
    }

    function ecommerceViewItem(item = {}) {
        if (!isBrowser()) {
            return
        }

        const safeItem = {
            id: String(item.id ?? ''),
            name: String(item.name ?? ''),
            category: String(item.category ?? ''),
            price: Number(item.price ?? 0),
            currency: String(item.currency ?? 'RUB'),
        }

        try {
            window.dataLayer = window.dataLayer || []

            window.dataLayer.push({
                ecommerce: {
                    currencyCode: safeItem.currency,
                    detail: {
                        products: [
                            {
                                id: safeItem.id,
                                name: safeItem.name,
                                category: safeItem.category,
                                price: safeItem.price,
                            },
                        ],
                    },
                },
            })
        } catch (error) {
            if (import.meta.env.DEV) {
                console.warn('[YandexMetrica] ecommerceViewItem failed:', error)
            }
        }
    }

    function ecommerceAddToCart(item = {}) {
        if (!isBrowser()) {
            return
        }

        const safeItem = {
            id: String(item.id ?? ''),
            name: String(item.name ?? ''),
            category: String(item.category ?? ''),
            price: Number(item.price ?? 0),
            quantity: Number(item.quantity ?? 1),
            currency: String(item.currency ?? 'RUB'),
        }

        try {
            window.dataLayer = window.dataLayer || []

            window.dataLayer.push({
                ecommerce: {
                    currencyCode: safeItem.currency,
                    add: {
                        products: [
                            {
                                id: safeItem.id,
                                name: safeItem.name,
                                category: safeItem.category,
                                price: safeItem.price,
                                quantity: safeItem.quantity,
                            },
                        ],
                    },
                },
            })
        } catch (error) {
            if (import.meta.env.DEV) {
                console.warn('[YandexMetrica] ecommerceAddToCart failed:', error)
            }
        }
    }

    return {
        reachGoal,
        ecommerceViewItem,
        ecommerceAddToCart,
    }
}
