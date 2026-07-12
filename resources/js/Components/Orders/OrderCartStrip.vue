<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

import { useAppRoute } from '@/Composables/useAppRoute'
import { useOrderCart } from '@/Composables/useOrderCart'

const page = usePage()
const { route } = useAppRoute()

const {
    items,
    totalAmount,
    totalWeight,
    itemsCount,
    currencyCode,
    removeItem,
    increment,
    decrement,
    clearCart,
} = useOrderCart()

const submitting = ref(false)
const errorMessage = ref('')

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))
const hasItems = computed(() => items.value.length > 0)

const orderEndpoint = computed(() => {
    const resolved = route('customer.orders.store')

    return resolved === '#'
        ? '/orders'
        : resolved
})

const loginUrl = computed(() => {
    const resolved = route('login')

    return resolved === '#'
        ? '/login'
        : resolved
})

function money(value, code = 'RUB') {
    const amount = Number(value)

    if (!Number.isFinite(amount) || amount <= 0) {
        return 'по запросу'
    }

    const currency = code === 'RUB' ? '₽' : code

    return `${amount.toLocaleString('ru-RU', {
        maximumFractionDigits: 2,
    })} ${currency}`
}

function weight(value) {
    const amount = Number(value)

    if (!Number.isFinite(amount) || amount <= 0) {
        return 'вес уточняется'
    }

    return `${amount.toLocaleString('ru-RU', {
        maximumFractionDigits: 3,
    })} кг`
}

function submitOrder() {
    errorMessage.value = ''

    if (!hasItems.value || submitting.value) {
        return
    }

    if (!isAuthenticated.value) {
        router.visit(loginUrl.value)
        return
    }

    submitting.value = true

    axios
        .post(orderEndpoint.value, {
            items: items.value.map((item) => ({
                good_id: item.good_id,
                quantity: item.quantity,
            })),
        })
        .then((response) => {
            clearCart()
            router.visit(response.data?.redirect || route('dashboard'))
        })
        .catch((error) => {
            errorMessage.value = error.response?.data?.message
                || error.response?.data?.errors?.items?.[0]
                || 'Не удалось создать заказ.'
        })
        .finally(() => {
            submitting.value = false
        })
}
</script>

<template>
    <section
        class="order-cart-strip"
        :class="{ 'order-cart-strip--empty': !hasItems }"
        aria-label="Корзина заказов"
    >
        <div v-if="hasItems" class="order-cart-strip__rail">
            <article
                v-for="item in items"
                :key="item.good_id"
                class="order-cart-tile"
            >
                <img
                    :src="item.image_url"
                    :alt="item.name"
                    loading="lazy"
                    class="order-cart-tile__image"
                >

                <div class="order-cart-tile__body">
                    <strong>{{ item.name }}</strong>
                    <span>{{ item.quantity }} × {{ money(item.price_gross, item.currency_code) }}</span>
                </div>

                <div class="order-cart-tile__controls">
                    <button
                        type="button"
                        aria-label="Уменьшить количество"
                        @click="decrement(item.good_id)"
                    >
                        <v-icon icon="mdi-minus" size="13" />
                    </button>

                    <button
                        type="button"
                        aria-label="Увеличить количество"
                        @click="increment(item.good_id)"
                    >
                        <v-icon icon="mdi-plus" size="13" />
                    </button>

                    <button
                        type="button"
                        aria-label="Убрать из корзины"
                        @click="removeItem(item.good_id)"
                    >
                        <v-icon icon="mdi-close" size="13" />
                    </button>
                </div>
            </article>
        </div>

        <div v-else class="order-cart-strip__placeholder">
            <v-icon icon="mdi-cart-outline" size="18" />
            <span>Корзина заказов</span>
        </div>

        <div class="order-cart-strip__summary">
            <div class="order-cart-strip__totals">
                <span>{{ itemsCount }} поз.</span>
                <strong>{{ money(totalAmount, currencyCode) }}</strong>
                <small>{{ weight(totalWeight) }}</small>
            </div>

            <button
                type="button"
                class="order-cart-strip__submit"
                :disabled="!hasItems || submitting"
                @click="submitOrder"
            >
                <v-icon
                    :icon="submitting ? 'mdi-loading' : 'mdi-check-circle-outline'"
                    size="15"
                    :class="{ 'order-cart-strip__spinner': submitting }"
                />
                <span>Сделать заказ</span>
            </button>
        </div>

        <div v-if="errorMessage" class="order-cart-strip__error">
            {{ errorMessage }}
        </div>
    </section>
</template>

<style scoped>
.order-cart-strip {
    display: grid;
    min-width: 0;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 7px;
    border: 1px solid rgba(128, 0, 0, 0.11);
    border-radius: 8px;
    background:
        linear-gradient(90deg, rgba(71, 118, 90, 0.08), transparent 48%),
        #fff;
}

.order-cart-strip__rail {
    display: flex;
    min-width: 0;
    gap: 7px;
    overflow-x: auto;
    scrollbar-width: thin;
}

.order-cart-tile {
    display: grid;
    width: 178px;
    min-width: 178px;
    grid-template-columns: 42px minmax(0, 1fr) 24px;
    align-items: center;
    gap: 7px;
    padding: 6px;
    border: 1px solid rgba(71, 118, 90, 0.14);
    border-radius: 7px;
    background: #fffdf8;
}

.order-cart-tile__image {
    width: 42px;
    height: 42px;
    border-radius: 6px;
    object-fit: cover;
    background: #f7f1e8;
}

.order-cart-tile__body {
    display: grid;
    min-width: 0;
    gap: 2px;
}

.order-cart-tile__body strong {
    overflow: hidden;
    color: #2d201d;
    font-size: 11px;
    font-weight: 950;
    line-height: 1.15;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.order-cart-tile__body span {
    color: #7a120f;
    font-size: 10px;
    font-weight: 900;
    white-space: nowrap;
}

.order-cart-tile__controls {
    display: grid;
    gap: 2px;
}

.order-cart-tile__controls button {
    display: grid;
    width: 22px;
    height: 18px;
    place-items: center;
    border: 1px solid rgba(128, 0, 0, 0.13);
    border-radius: 5px;
    background: #fff;
    color: #6b1b18;
    cursor: pointer;
}

.order-cart-strip__placeholder {
    display: inline-flex;
    min-height: 52px;
    align-items: center;
    gap: 8px;
    padding: 0 6px;
    color: #7c6f6a;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
}

.order-cart-strip__summary {
    display: flex;
    align-items: center;
    gap: 8px;
}

.order-cart-strip__totals {
    display: grid;
    min-width: 96px;
    gap: 1px;
}

.order-cart-strip__totals span,
.order-cart-strip__totals small {
    color: #74665f;
    font-size: 10px;
    font-weight: 850;
    line-height: 1.1;
    white-space: nowrap;
}

.order-cart-strip__totals strong {
    color: #273c2e;
    font-size: 13px;
    font-weight: 950;
    line-height: 1.2;
    white-space: nowrap;
}

.order-cart-strip__submit {
    display: inline-flex;
    min-height: 34px;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 0 10px;
    border: 0;
    border-radius: 6px;
    background: #8a100c;
    color: #fffaf6;
    font-size: 11px;
    font-weight: 950;
    cursor: pointer;
    white-space: nowrap;
}

.order-cart-strip__submit:disabled {
    background: #c7bab3;
    cursor: default;
}

.order-cart-strip__error {
    grid-column: 1 / -1;
    color: #8a100c;
    font-size: 11px;
    font-weight: 850;
}

.order-cart-strip__spinner {
    animation: order-cart-spin 0.8s linear infinite;
}

@keyframes order-cart-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 720px) {
    .order-cart-strip {
        grid-template-columns: 1fr;
    }

    .order-cart-strip__summary {
        justify-content: space-between;
    }
}
</style>
