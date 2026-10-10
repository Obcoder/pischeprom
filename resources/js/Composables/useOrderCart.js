import { computed, effectScope, onMounted, ref, watch } from 'vue'

import { logo } from '@/Pages/Helpers/consts.js'
import { measurementForGood, quantityWeight } from '@/utils/goodMeasurement'

// Old carts counted packages implicitly; they cannot be reinterpreted as product units.
const CART_KEY = 'pps-order-cart-v2'

const items = ref([])
const cartError = ref('')
// Persistence belongs to the shared cart, independent of the active layout.
const storageScope = effectScope(true)
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

    storageScope.run(() => {
        watch(items, writeCart, {
            deep: true,
        })
    })
}

function normalizeQuantity(value) {
    const quantity = Number(value)

    return Number.isFinite(quantity) && quantity > 0
        ? Math.min(9999, Math.max(0.001, Math.round(quantity * 1000) / 1000))
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
    const measurement = measurementForGood(item)

    if (!Number.isFinite(goodId) || goodId <= 0 || !measurement.measure_id || !measurement.unit_label) {
        return null
    }

    return {
        good_id: goodId,
        name: String(item.name || item.good_name || 'Товар').trim(),
        slug: item.slug || item.good_slug || null,
        image_url: item.image_url || item.image || logo,
        quantity: normalizeQuantity(item.quantity),
        measurement: {
            measure_id: Number(measurement.measure_id),
            unit_label: String(measurement.unit_label),
            kilograms_per_unit: normalizeNumber(measurement.kilograms_per_unit),
        },
        denominator: normalizeNumber(item.denominator),
        country_name: item.country_name || item.country?.name || null,
        price_gross: normalizeNumber(item.price_gross),
        currency_code: item.currency_code || 'RUB',
        pricing_context: item.pricing_context === 'public' ? 'public' : 'catalog',
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

function cartItemFromGood(good, quantity, offer) {
    const price = offer ? null : selectedPrice(good)

    return normalizeCartItem({
        good_id: good?.id,
        name: good?.name,
        slug: good?.slug,
        image_url: primaryImage(good),
        denominator: good?.denominator,
        measurement: offer?.measurement || measurementForGood(good),
        country_name: good?.country?.name,
        price_gross: offer ? offer.price : priceValue(price),
        currency_code: offer ? offer.currency_code : currencyCode(price),
        pricing_context: offer ? 'public' : 'catalog',
        quantity,
    })
}

export function useOrderCart() {
    // Match the server's empty cart during hydration, then restore browser data.
    onMounted(initializeCart)

    const totalAmount = computed(() => items.value.reduce((sum, item) => {
        return sum + (Number(item.price_gross || 0) * normalizeQuantity(item.quantity))
    }, 0))

    const totalWeight = computed(() => {
        const weights = items.value.map(item => quantityWeight(normalizeQuantity(item.quantity), item.measurement))
        return weights.some(weight => weight === null) ? null : weights.reduce((sum, weight) => sum + weight, 0)
    })

    const itemsCount = computed(() => items.value.length)

    const currencyCodeValue = computed(() => items.value.find((item) => item.currency_code)?.currency_code || 'RUB')

    function addGood(good, quantity = 1, offer = null) {
        cartError.value = ''
        const requestedQuantity = Number(quantity)
        if (!Number.isFinite(requestedQuantity) || requestedQuantity < 0.001 || requestedQuantity > 9999) {
            cartError.value = 'Укажите количество от 0,001 до 9999.'
            return false
        }
        const cartItem = cartItemFromGood(good, quantity, offer)

        if (!cartItem) {
            cartError.value = 'Единица измерения товара не задана. Для заказа её должен указать менеджер.'
            return false
        }

        const existing = items.value.find((item) => Number(item.good_id) === Number(cartItem.good_id))

        if (existing) {
            if (existing.measurement.measure_id !== cartItem.measurement.measure_id
                || existing.measurement.kilograms_per_unit !== cartItem.measurement.kilograms_per_unit) {
                cartError.value = 'Единица измерения товара изменилась. Удалите его из корзины и добавьте заново.'
                return false
            }
            const nextQuantity = Math.round((existing.quantity + cartItem.quantity) * 1000) / 1000
            if (nextQuantity > 9999) {
                cartError.value = 'В корзине может быть не более 9999 единиц одного товара. Измените количество.'
                return false
            }
            existing.quantity = nextQuantity
            existing.price_gross = cartItem.price_gross
            existing.currency_code = cartItem.currency_code
            existing.pricing_context = cartItem.pricing_context
            existing.denominator = cartItem.denominator
            existing.image_url = cartItem.image_url
            return true
        }

        items.value = [...items.value, cartItem]
        return true
    }

    function removeItem(goodId) {
        cartError.value = ''
        items.value = items.value.filter((item) => Number(item.good_id) !== Number(goodId))
    }

    function setQuantity(goodId, quantity) {
        const item = items.value.find(candidate => Number(candidate.good_id) === Number(goodId))
        if (item) item.quantity = normalizeQuantity(quantity)
    }

    function increment(goodId) {
        const item = items.value.find((candidate) => Number(candidate.good_id) === Number(goodId))

        if (item) {
            item.quantity = normalizeQuantity(item.quantity + 1)
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

        item.quantity = normalizeQuantity(nextQuantity)
    }

    function clearCart() {
        items.value = []
        cartError.value = ''
    }

    return {
        items,
        totalAmount,
        totalWeight,
        itemsCount,
        cartError,
        currencyCode: currencyCodeValue,
        addGood,
        removeItem,
        increment,
        decrement,
        setQuantity,
        clearCart,
    }
}
