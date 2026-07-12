import { computed, ref, watch } from 'vue'

import { logo } from '@/Pages/Helpers/consts.js'

const CART_KEY = 'pps-order-cart-v1'

const items = ref([])
let initialized = false

function canUseStorage() {
    return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
}

function readCart() {
    if (!canUseStorage()) {
        return []
    }

    try {
        const stored = JSON.parse(window.localStorage.getItem(CART_KEY) || '[]')

        return Array.isArray(stored)
            ? stored.map(normalizeCartItem).filter(Boolean)
            : []
    } catch {
        return []
    }
}

function writeCart() {
    if (!canUseStorage()) {
        return
    }

    window.localStorage.setItem(CART_KEY, JSON.stringify(items.value))
}

function initializeCart() {
    if (initialized) {
        return
    }

    initialized = true
    items.value = readCart()

    if (typeof window !== 'undefined') {
        window.addEventListener('storage', (event) => {
            if (event.key === CART_KEY) {
                items.value = readCart()
            }
        })
    }

    watch(items, writeCart, {
        deep: true,
    })
}

function normalizeQuantity(value) {
    const quantity = Number(value)

    return Number.isFinite(quantity) && quantity > 0
        ? Math.max(1, Math.round(quantity))
        : 1
}

function normalizeNumber(value) {
    const number = Number(value)

    return Number.isFinite(number) && number > 0
        ? number
        : null
}

function normalizeCartItem(item) {
    const goodId = Number(item?.good_id || item?.id)

    if (!Number.isFinite(goodId) || goodId <= 0) {
        return null
    }

    return {
        good_id: goodId,
        name: String(item.name || item.good_name || 'Товар').trim(),
        slug: item.slug || item.good_slug || null,
        image_url: item.image_url || item.image || logo,
        quantity: normalizeQuantity(item.quantity),
        denominator: normalizeNumber(item.denominator),
        country_name: item.country_name || item.country?.name || null,
        price_gross: normalizeNumber(item.price_gross),
        currency_code: item.currency_code || 'RUB',
    }
}

function priceType(price) {
    return price?.price_type || price?.priceType || null
}

function priceTypeText(price) {
    const type = priceType(price)

    return `${type?.code || ''} ${type?.name || ''}`.toLowerCase()
}

function isPartnerPrice(price) {
    const text = priceTypeText(price)

    return text.includes('partner')
        || text.includes('партн')
        || text.includes('дилер')
        || text.includes('dealer')
        || text.includes('diler')
}

function isRetailPrice(price) {
    const text = priceTypeText(price)

    return text.includes('retail')
        || text.includes('rozn')
        || text.includes('рознич')
        || text.includes('розница')
}

function priceValue(price) {
    return normalizeNumber(price?.price_gross ?? price?.price_net ?? price?.price)
}

function currencyCode(price) {
    return price?.currency?.code
        || priceType(price)?.currency?.code
        || 'RUB'
}

function selectedPrice(good) {
    const values = good?.price_type_values || good?.priceTypeValues || []

    const publishedValues = values
        .filter((item) => item?.is_published !== false)
        .sort((a, b) => {
            const orderA = Number(priceType(a)?.sort_order || 100)
            const orderB = Number(priceType(b)?.sort_order || 100)

            if (orderA !== orderB) {
                return orderA - orderB
            }

            return String(priceType(a)?.name || '').localeCompare(String(priceType(b)?.name || ''), 'ru')
        })

    return publishedValues.find(isPartnerPrice)
        || publishedValues.find(isRetailPrice)
        || publishedValues.find((item) => Boolean(priceType(item)?.is_public))
        || publishedValues[0]
        || null
}

function primaryImage(good) {
    const media = good?.published_media || good?.publishedMedia || []
    const mediaImage = media
        .filter((item) => item?.type === 'image')
        .sort((a, b) => {
            if (Number(b.is_ava) !== Number(a.is_ava)) {
                return Number(b.is_ava) - Number(a.is_ava)
            }

            const orderA = Number(a.sort_order || 100)
            const orderB = Number(b.sort_order || 100)

            if (orderA !== orderB) {
                return orderA - orderB
            }

            return Number(a.id || 0) - Number(b.id || 0)
        })[0]

    return mediaImage?.thumb_url
        || mediaImage?.url
        || good?.ava_thumb
        || good?.ava_image
        || logo
}

function cartItemFromGood(good) {
    const price = selectedPrice(good)

    return normalizeCartItem({
        good_id: good?.id,
        name: good?.name,
        slug: good?.slug,
        image_url: primaryImage(good),
        denominator: good?.denominator,
        country_name: good?.country?.name,
        price_gross: priceValue(price),
        currency_code: currencyCode(price),
        quantity: 1,
    })
}

export function useOrderCart() {
    initializeCart()

    const totalAmount = computed(() => items.value.reduce((sum, item) => {
        return sum + (Number(item.price_gross || 0) * normalizeQuantity(item.quantity))
    }, 0))

    const totalWeight = computed(() => items.value.reduce((sum, item) => {
        return sum + (Number(item.denominator || 0) * normalizeQuantity(item.quantity))
    }, 0))

    const itemsCount = computed(() => items.value.reduce((sum, item) => sum + normalizeQuantity(item.quantity), 0))

    const currencyCodeValue = computed(() => items.value.find((item) => item.currency_code)?.currency_code || 'RUB')

    function addGood(good) {
        const cartItem = cartItemFromGood(good)

        if (!cartItem) {
            return
        }

        const existing = items.value.find((item) => Number(item.good_id) === Number(cartItem.good_id))

        if (existing) {
            existing.quantity = normalizeQuantity(existing.quantity) + 1
            existing.price_gross = cartItem.price_gross
            existing.currency_code = cartItem.currency_code
            existing.denominator = cartItem.denominator
            existing.image_url = cartItem.image_url
            return
        }

        items.value = [...items.value, cartItem]
    }

    function removeItem(goodId) {
        items.value = items.value.filter((item) => Number(item.good_id) !== Number(goodId))
    }

    function increment(goodId) {
        const item = items.value.find((candidate) => Number(candidate.good_id) === Number(goodId))

        if (item) {
            item.quantity = normalizeQuantity(item.quantity) + 1
        }
    }

    function decrement(goodId) {
        const item = items.value.find((candidate) => Number(candidate.good_id) === Number(goodId))

        if (!item) {
            return
        }

        const nextQuantity = normalizeQuantity(item.quantity) - 1

        if (nextQuantity <= 0) {
            removeItem(goodId)
            return
        }

        item.quantity = nextQuantity
    }

    function clearCart() {
        items.value = []
    }

    return {
        items,
        totalAmount,
        totalWeight,
        itemsCount,
        currencyCode: currencyCodeValue,
        addGood,
        removeItem,
        increment,
        decrement,
        clearCart,
    }
}
