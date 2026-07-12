<script setup>
import { computed, reactive, ref, watch } from 'vue'
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
const checkoutOpen = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const fieldErrors = ref({})

const form = reactive({
    delivery_address: '',
    preferred_delivery_time: '',
    customer_phone: '',
    customer_phone_source: 'manual',
})

const user = computed(() => page.props.auth?.user ?? null)
const hasItems = computed(() => items.value.length > 0)
const savedPhone = computed(() => user.value?.phone || '')
const savedDeliveryAddress = computed(() => {
    return user.value?.delivery_address
        || page.props.location?.city?.label
        || ''
})
const hasSavedPhone = computed(() => Boolean(savedPhone.value))

const orderEndpoint = computed(() => {
    const resolved = route('customer.orders.store')

    return resolved === '#'
        ? '/orders'
        : resolved
})

watch(
    () => form.customer_phone_source,
    (source) => {
        if (source === 'profile') {
            form.customer_phone = savedPhone.value
        } else if (form.customer_phone === savedPhone.value) {
            form.customer_phone = ''
        }
    },
)

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

function openCheckout() {
    if (!hasItems.value) {
        return
    }

    fieldErrors.value = {}
    errorMessage.value = ''
    successMessage.value = ''
    form.delivery_address = savedDeliveryAddress.value
    form.preferred_delivery_time = ''
    form.customer_phone_source = hasSavedPhone.value ? 'profile' : 'manual'
    form.customer_phone = hasSavedPhone.value ? savedPhone.value : ''
    checkoutOpen.value = true
}

function closeCheckout() {
    if (submitting.value) {
        return
    }

    checkoutOpen.value = false
}

function localValidate() {
    const errors = {}

    if (!String(form.delivery_address || '').trim()) {
        errors.delivery_address = ['Укажите адрес доставки.']
    }

    if (!String(form.preferred_delivery_time || '').trim()) {
        errors.preferred_delivery_time = ['Укажите удобное время.']
    }

    if (!String(form.customer_phone || '').trim()) {
        errors.customer_phone = ['Укажите контактный телефон.']
    }

    fieldErrors.value = errors

    return Object.keys(errors).length === 0
}

function firstError(field) {
    return fieldErrors.value?.[field]?.[0] || ''
}

function submitOrder() {
    errorMessage.value = ''
    successMessage.value = ''

    if (!hasItems.value || submitting.value || !localValidate()) {
        return
    }

    submitting.value = true

    axios
        .post(orderEndpoint.value, {
            items: items.value.map((item) => ({
                good_id: item.good_id,
                quantity: item.quantity,
            })),
            delivery_address: form.delivery_address,
            preferred_delivery_time: form.preferred_delivery_time,
            customer_phone: form.customer_phone,
            customer_phone_source: form.customer_phone_source,
        })
        .then((response) => {
            const orderNumber = response.data?.order?.number
            const redirect = response.data?.redirect

            clearCart()
            checkoutOpen.value = false
            successMessage.value = orderNumber
                ? `Заказ ${orderNumber} создан.`
                : 'Заказ создан.'

            if (redirect) {
                router.visit(redirect)
            }
        })
        .catch((error) => {
            fieldErrors.value = error.response?.data?.errors || {}
            errorMessage.value = error.response?.data?.message
                || fieldErrors.value?.items?.[0]
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
                @click="openCheckout"
            >
                <v-icon icon="mdi-check-circle-outline" size="15" />
                <span>Сделать заказ</span>
            </button>
        </div>

        <div v-if="successMessage" class="order-cart-strip__success">
            {{ successMessage }}
        </div>

        <div v-if="errorMessage && !checkoutOpen" class="order-cart-strip__error">
            {{ errorMessage }}
        </div>

        <Teleport to="body">
            <Transition name="order-checkout">
                <div
                    v-if="checkoutOpen"
                    class="order-checkout"
                    @click.self="closeCheckout"
                >
                    <form class="order-checkout__panel" @submit.prevent="submitOrder">
                        <div class="order-checkout__head">
                            <div>
                                <strong>Оформление заказа</strong>
                                <span>{{ itemsCount }} поз. · {{ money(totalAmount, currencyCode) }}</span>
                            </div>

                            <button
                                type="button"
                                class="order-checkout__close"
                                aria-label="Закрыть форму"
                                @click="closeCheckout"
                            >
                                <v-icon icon="mdi-close" size="18" />
                            </button>
                        </div>

                        <div class="order-checkout__body">
                            <label class="order-checkout__field">
                                <span>Адрес доставки</span>
                                <textarea
                                    v-model="form.delivery_address"
                                    rows="3"
                                    autocomplete="street-address"
                                />
                                <small v-if="firstError('delivery_address')">
                                    {{ firstError('delivery_address') }}
                                </small>
                            </label>

                            <label class="order-checkout__field">
                                <span>Удобное время</span>
                                <input
                                    v-model="form.preferred_delivery_time"
                                    type="text"
                                    placeholder="Например: завтра с 10:00 до 14:00"
                                >
                                <small v-if="firstError('preferred_delivery_time')">
                                    {{ firstError('preferred_delivery_time') }}
                                </small>
                            </label>

                            <div v-if="hasSavedPhone" class="order-checkout__phone-choice">
                                <span>Контактный телефон</span>

                                <div>
                                    <button
                                        type="button"
                                        :class="{ 'order-checkout__choice--active': form.customer_phone_source === 'profile' }"
                                        @click="form.customer_phone_source = 'profile'"
                                    >
                                        {{ savedPhone }}
                                    </button>

                                    <button
                                        type="button"
                                        :class="{ 'order-checkout__choice--active': form.customer_phone_source === 'manual' }"
                                        @click="form.customer_phone_source = 'manual'"
                                    >
                                        Другой номер
                                    </button>
                                </div>
                            </div>

                            <label
                                v-if="!hasSavedPhone || form.customer_phone_source === 'manual'"
                                class="order-checkout__field"
                            >
                                <span>Номер телефона</span>
                                <input
                                    v-model="form.customer_phone"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="+7"
                                >
                                <small v-if="firstError('customer_phone')">
                                    {{ firstError('customer_phone') }}
                                </small>
                            </label>

                            <div v-if="errorMessage" class="order-checkout__error">
                                {{ errorMessage }}
                            </div>
                        </div>

                        <div class="order-checkout__footer">
                            <button
                                type="button"
                                class="order-checkout__secondary"
                                @click="closeCheckout"
                            >
                                Вернуться
                            </button>

                            <button
                                type="submit"
                                class="order-checkout__primary"
                                :disabled="submitting"
                            >
                                <v-icon
                                    :icon="submitting ? 'mdi-loading' : 'mdi-send-outline'"
                                    size="16"
                                    :class="{ 'order-cart-strip__spinner': submitting }"
                                />
                                <span>Отправить заказ</span>
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>

<style scoped>
.order-cart-strip {
    display: grid;
    width: 100%;
    min-width: 0;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border: 1px solid rgba(128, 0, 0, 0.11);
    border-radius: 8px;
    background:
        linear-gradient(90deg, rgba(71, 118, 90, 0.08), transparent 48%),
        #fff;
}

.order-cart-strip__rail {
    display: flex;
    min-width: 0;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: thin;
}

.order-cart-tile {
    display: grid;
    width: 204px;
    min-width: 204px;
    grid-template-columns: 46px minmax(0, 1fr) 24px;
    align-items: center;
    gap: 8px;
    padding: 6px;
    border: 1px solid rgba(71, 118, 90, 0.14);
    border-radius: 7px;
    background: #fffdf8;
}

.order-cart-tile__image {
    width: 46px;
    height: 46px;
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
    min-width: 104px;
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
    min-height: 36px;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 0 12px;
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

.order-cart-strip__success,
.order-cart-strip__error {
    grid-column: 1 / -1;
    font-size: 11px;
    font-weight: 850;
}

.order-cart-strip__success {
    color: #2f684a;
}

.order-cart-strip__error {
    color: #8a100c;
}

.order-cart-strip__spinner {
    animation: order-cart-spin 0.8s linear infinite;
}

.order-checkout {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: grid;
    place-items: center;
    padding: 18px;
    background: rgba(17, 24, 39, 0.48);
}

.order-checkout__panel {
    display: grid;
    width: min(680px, 100%);
    max-height: min(720px, calc(100vh - 36px));
    overflow: auto;
    border-radius: 8px;
    background: #fffdf9;
    box-shadow: 0 24px 72px rgba(17, 24, 39, 0.28);
}

.order-checkout__head,
.order-checkout__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
}

.order-checkout__head {
    border-bottom: 1px solid rgba(128, 0, 0, 0.10);
}

.order-checkout__head div {
    display: grid;
    gap: 2px;
}

.order-checkout__head strong {
    color: #2d201d;
    font-size: 16px;
    font-weight: 950;
}

.order-checkout__head span {
    color: #74665f;
    font-size: 12px;
    font-weight: 850;
}

.order-checkout__close {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border: 1px solid rgba(128, 0, 0, 0.12);
    border-radius: 6px;
    background: #fff;
    color: #6b1b18;
    cursor: pointer;
}

.order-checkout__body {
    display: grid;
    gap: 12px;
    padding: 16px;
}

.order-checkout__field {
    display: grid;
    gap: 6px;
}

.order-checkout__field span,
.order-checkout__phone-choice > span {
    color: rgba(128, 0, 0, 0.72);
    font-size: 10px;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.order-checkout__field input,
.order-checkout__field textarea {
    width: 100%;
    border: 1px solid rgba(128, 0, 0, 0.16);
    border-radius: 7px;
    background: #fff;
    color: #2d201d;
    font: inherit;
    font-size: 14px;
    line-height: 1.4;
    padding: 10px 11px;
}

.order-checkout__field textarea {
    resize: vertical;
}

.order-checkout__field small {
    color: #8a100c;
    font-size: 11px;
    font-weight: 850;
}

.order-checkout__phone-choice {
    display: grid;
    gap: 8px;
}

.order-checkout__phone-choice div {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.order-checkout__phone-choice button,
.order-checkout__secondary,
.order-checkout__primary {
    min-height: 36px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 950;
    cursor: pointer;
}

.order-checkout__phone-choice button {
    border: 1px solid rgba(128, 0, 0, 0.18);
    background: #fff;
    color: #5f0a08;
    padding: 0 11px;
}

.order-checkout__choice--active {
    background: rgba(128, 0, 0, 0.08) !important;
    border-color: rgba(128, 0, 0, 0.34) !important;
}

.order-checkout__error {
    padding: 10px 11px;
    border: 1px solid rgba(138, 16, 12, 0.18);
    border-radius: 7px;
    background: rgba(138, 16, 12, 0.06);
    color: #8a100c;
    font-size: 12px;
    font-weight: 850;
}

.order-checkout__footer {
    border-top: 1px solid rgba(128, 0, 0, 0.10);
}

.order-checkout__secondary {
    border: 1px solid rgba(128, 0, 0, 0.18);
    background: #fff;
    color: #5f0a08;
    padding: 0 12px;
}

.order-checkout__primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 0;
    background: #8a100c;
    color: #fffaf6;
    padding: 0 14px;
}

.order-checkout__primary:disabled {
    background: #c7bab3;
    cursor: default;
}

.order-checkout-enter-active,
.order-checkout-leave-active {
    transition: opacity 0.18s ease;
}

.order-checkout-enter-from,
.order-checkout-leave-to {
    opacity: 0;
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

    .order-checkout__footer {
        align-items: stretch;
        flex-direction: column-reverse;
    }
}
</style>
