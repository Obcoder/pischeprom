<script setup>
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
    modelValue: Boolean,
    orderId: { type: [Number, String], default: null },
})
const emit = defineEmits(['update:modelValue'])
const titleId = `order-details-${useId()}`
const order = ref(null)
const loading = ref(false)
const error = ref('')
const orderNumber = computed(() => order.value?.number || (props.orderId ? `#${props.orderId}` : ''))
let controller = null
let requestId = 0
let disposed = false

function cancelRequest() {
    requestId += 1
    controller?.abort()
    controller = null
    loading.value = false
}

function updateDialog(value) {
    if (!value) cancelRequest()
    emit('update:modelValue', value)
}

async function loadOrder() {
    if (disposed || !props.modelValue || !props.orderId) return

    cancelRequest()
    const currentRequest = requestId
    const orderId = props.orderId
    const requestController = new AbortController()
    controller = requestController
    loading.value = true
    error.value = ''
    order.value = null

    const isCurrent = () => !disposed
        && currentRequest === requestId
        && !requestController.signal.aborted
        && props.modelValue
        && props.orderId === orderId

    try {
        const { data } = await axios.get(`/api/orders/${orderId}`, { signal: requestController.signal })
        if (!isCurrent()) return
        if (!data.data?.id) throw new Error('Order details are missing')
        order.value = data.data
    } catch (failure) {
        if (!isCurrent() || axios.isCancel(failure)) return
        error.value = failure.response?.status === 404
            ? 'Заказ не найден. Возможно, он был удалён.'
            : 'Не удалось загрузить заказ. Попробуйте ещё раз.'
    } finally {
        if (isCurrent()) {
            loading.value = false
            controller = null
        }
    }
}

function orderUrl() {
    try {
        return route('Ameise.orders.show', props.orderId)
    } catch {
        return `/Ameise/orders/${props.orderId}`
    }
}

function formatNumber(value, digits = 2) {
    if (value == null || value === '' || !Number.isFinite(Number(value))) return '—'
    return Number(value).toLocaleString('ru-RU', { maximumFractionDigits: digits })
}

function formatMoney(value, currency = order.value?.currency_code || 'RUB') {
    const amount = formatNumber(value)
    return amount === '—' ? amount : `${amount} ${!currency || currency === 'RUB' ? '₽' : currency}`
}

function formatDate(value, withTime = false) {
    if (!value) return '—'
    const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value)
    if (Number.isNaN(date.getTime())) return '—'
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(date)
}

function formatPhone(value) {
    if (!value) return '—'
    return String(value).startsWith('+') ? String(value) : `+${value}`
}

function buildingAddress(building) {
    return [building.postcode, building.city?.region, building.city?.name, building.address].filter(Boolean).join(', ') || 'Адрес не указан'
}

watch(
    () => [props.modelValue, props.orderId],
    () => {
        cancelRequest()
        order.value = null
        error.value = ''
        loadOrder()
    },
    { immediate: true },
)

onBeforeUnmount(() => {
    disposed = true
    cancelRequest()
})
</script>

<template>
    <v-dialog :model-value="modelValue" :aria-labelledby="titleId" max-width="900" scrollable @update:model-value="updateDialog">
        <v-card class="order-details" theme="light">
            <header class="order-details__header">
                <v-icon class="order-details__symbol" icon="mdi-receipt-text-outline" size="23" />
                <div class="order-details__heading">
                    <h2 :id="titleId">Заказ {{ orderNumber }}</h2>
                    <span v-if="order">{{ formatDate(order.submitted_at || order.created_at, true) }}</span>
                    <span v-else>Детали заказа</span>
                </div>
                <span v-if="order?.status" class="order-details__status">
                    <i :style="{ backgroundColor: order.status.color || '#64748b' }" />{{ order.status.name }}
                </span>
                <Link v-if="orderId" :href="orderUrl()" class="order-details__page-link" title="Открыть страницу заказа" aria-label="Открыть страницу заказа">
                    <v-icon icon="mdi-open-in-new" size="18" />
                </Link>
                <v-btn icon="mdi-close" size="small" variant="text" aria-label="Закрыть детали заказа" @click="updateDialog(false)" />
            </header>

            <v-progress-linear v-if="loading" indeterminate color="brown-darken-3" height="2" />
            <div class="order-details__body" :aria-busy="loading">
                <div v-if="error" class="order-details__message" role="alert">
                    <v-icon icon="mdi-alert-circle-outline" size="24" />
                    <span>{{ error }}</span>
                    <v-btn size="small" variant="tonal" @click="loadOrder">Повторить</v-btn>
                </div>
                <div v-else-if="loading" class="order-details__message" role="status">Загрузка заказа…</div>
                <template v-else-if="order">
                    <div class="order-details__totals">
                        <div><span>Сумма заказа</span><strong>{{ formatMoney(order.total_amount) }}</strong></div>
                        <div><span>Общий вес</span><strong>{{ formatNumber(order.total_weight, 3) }} <small>кг</small></strong></div>
                        <div><span>Позиций</span><strong>{{ order.items_count ?? order.items?.length ?? 0 }}</strong></div>
                    </div>

                    <div class="order-details__info">
                        <section>
                            <h3><v-icon icon="mdi-domain" size="15" /> Покупатель</h3>
                            <strong>{{ order.entity?.name || 'Без Entity' }}</strong>
                            <div v-if="order.entity?.INN" class="order-details__muted">ИНН {{ order.entity.INN }}</div>
                            <div v-if="order.entity?.units?.length" class="order-details__muted">{{ order.entity.units.map(unit => unit.name).join(' · ') }}</div>
                            <a v-if="order.contact_telephone?.number" class="order-details__phone" :href="`tel:${formatPhone(order.contact_telephone.number).replace(/[^+\d]/g, '')}`">
                                <v-icon icon="mdi-phone-outline" size="14" />{{ formatPhone(order.contact_telephone.number) }}
                            </a>
                            <div v-else class="order-details__muted">Контактный телефон не указан</div>
                            <div v-if="order.created_by" class="order-details__muted">Создал: {{ order.created_by.name }}</div>
                        </section>
                        <section>
                            <h3><v-icon icon="mdi-truck-delivery-outline" size="15" /> Доставка</h3>
                            <strong>{{ order.delivery_date ? formatDate(order.delivery_date) : 'Дата не назначена' }}</strong>
                            <div v-if="order.preferred_delivery_time" class="order-details__multiline">{{ order.preferred_delivery_time }}</div>
                            <div v-for="building in (order.buildings || [])" :key="building.id" class="order-details__address">
                                <span v-if="building.building_type" class="order-details__muted">{{ building.building_type }} · </span>{{ buildingAddress(building) }}
                            </div>
                            <div v-if="!order.buildings?.length" class="order-details__muted">Адрес не указан</div>
                            <div v-if="order.shipped_at" class="order-details__muted">Отгружен: {{ formatDate(order.shipped_at, true) }}</div>
                        </section>
                    </div>

                    <div class="order-details__items">
                        <table aria-label="Товары заказа">
                            <thead><tr><th scope="col">Товар</th><th scope="col">Кол-во</th><th scope="col">Вес, кг</th><th scope="col">Цена</th><th scope="col">Сумма</th></tr></thead>
                            <tbody>
                                <tr v-for="item in (order.items || [])" :key="item.id">
                                    <td><strong>{{ item.good_name || item.good?.name || 'Товар' }}</strong><small v-if="item.country_name">{{ item.country_name }}</small></td>
                                    <td>{{ formatNumber(item.quantity, 3) }}</td>
                                    <td>{{ formatNumber(item.line_weight, 3) }}</td>
                                    <td>{{ formatMoney(item.unit_price ?? item.price_gross, item.currency_code) }}</td>
                                    <td>{{ formatMoney(item.total_amount ?? item.line_total, item.currency_code) }}</td>
                                </tr>
                                <tr v-if="!order.items?.length"><td colspan="5" class="order-details__empty">Товаров в заказе нет</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <section v-if="order.internal_comment" class="order-details__comment">
                        <h3><v-icon icon="mdi-comment-text-outline" size="15" /> Внутренний комментарий</h3>
                        <p>{{ order.internal_comment }}</p>
                    </section>
                    <footer class="order-details__footer">
                        <span>Создан {{ formatDate(order.created_at, true) }}</span>
                        <span v-if="order.closed_at">Закрыт {{ formatDate(order.closed_at, true) }}</span>
                    </footer>
                </template>
            </div>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.order-details { max-height: 85vh; border: 1px solid #d7dce2; border-radius: 10px; background: #fff; color: #252b33; }
.order-details__header { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-bottom: 1px solid #d7dce2; background: #f5f6f8; }
.order-details__symbol { color: #7f1d1d; }
.order-details__heading { flex: 1; min-width: 0; }
.order-details__heading h2 { margin: 0; overflow-wrap: anywhere; font-size: 16px; font-weight: 800; line-height: 1.3; }
.order-details__heading > span { color: #737c88; font-size: 10px; }
.order-details__status { display: inline-flex; align-items: center; gap: 6px; max-width: 35%; color: #48525e; font-size: 11px; font-weight: 700; overflow-wrap: anywhere; }
.order-details__status i { flex: 0 0 7px; width: 7px; height: 7px; border-radius: 50%; }
.order-details__page-link { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 28px; height: 30px; border-radius: 4px; color: #697582; }
.order-details__page-link:hover { background: #e7ebef; color: #7f1d1d; }
.order-details__page-link:focus-visible { outline: 2px solid #7f1d1d; outline-offset: 2px; }
.order-details__body { min-height: 130px; overflow: auto; }
.order-details__message { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 10px; min-height: 160px; padding: 20px; color: #697582; font-size: 13px; text-align: center; }
.order-details__totals { display: grid; grid-template-columns: 1.4fr 1fr 0.6fr; border-bottom: 1px solid #dfe4e9; background: #fbfcfd; }
.order-details__totals > div { display: flex; flex-direction: column; gap: 3px; padding: 12px 16px; border-right: 1px solid #e4e8ec; }
.order-details__totals > div:last-child { border-right: 0; }
.order-details__totals span { color: #77818c; font-size: 10px; }
.order-details__totals strong { font-size: 18px; font-variant-numeric: tabular-nums; }
.order-details__totals > div:first-child strong { color: #185c4b; }
.order-details__totals small { font-size: 12px; font-weight: 500; }
.order-details__info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 14px 16px; border-bottom: 1px solid #dfe4e9; font-size: 12px; line-height: 1.6; overflow-wrap: anywhere; }
.order-details h3 { display: flex; align-items: center; gap: 5px; margin: 0 0 6px; color: #727c89; font-size: 10px; font-weight: 700; }
.order-details__muted { color: #7b8490; font-size: 11px; }
.order-details__phone { display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; color: #7f1d1d; font-variant-numeric: tabular-nums; text-decoration: none; }
.order-details__phone:hover { text-decoration: underline; }
.order-details__address { margin-top: 5px; }
.order-details__multiline { white-space: pre-wrap; }
.order-details__items { overflow-x: auto; }
.order-details__items table { width: 100%; border-collapse: collapse; }
.order-details__items th, .order-details__items td { padding: 9px 12px; border-bottom: 1px solid #e5e9ed; text-align: right; font-size: 11px; font-variant-numeric: tabular-nums; white-space: nowrap; }
.order-details__items th { background: #f0f2f4; color: #697582; font-size: 10px; font-weight: 700; }
.order-details__items th:first-child, .order-details__items td:first-child { min-width: 180px; text-align: left; white-space: normal; overflow-wrap: anywhere; }
.order-details__items td strong { font-size: 12px; }
.order-details__items td small { display: block; margin-top: 2px; color: #7b8490; font-size: 10px; }
.order-details__items td:last-child { font-weight: 700; }
.order-details__items .order-details__empty { text-align: center; color: #7b8490; }
.order-details__comment { padding: 14px 16px; border-bottom: 1px solid #e5e9ed; background: #fffcf5; }
.order-details__comment p { margin: 0; font-size: 12px; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.order-details__footer { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; padding: 9px 16px; color: #87909b; font-size: 10px; }
@media (max-width: 600px) {
    .order-details__header { gap: 6px; padding: 8px; flex-wrap: wrap; }
    .order-details__heading h2 { font-size: 14px; }
    .order-details__symbol { display: none; }
    .order-details__status { max-width: 30%; font-size: 10px; }
    .order-details__totals > div { padding: 10px; }
    .order-details__totals strong { font-size: 15px; }
    .order-details__info { grid-template-columns: 1fr; gap: 14px; padding: 12px; }
}
</style>
