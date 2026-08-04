<script setup>
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import { useHead } from '@unhead/vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { route } from 'ziggy-js'

import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    orderId: {
        type: Number,
        default: null,
    },
    permissions: {
        type: Object,
        default: () => ({
            view: false,
            create: false,
            edit: false,
            delete: false,
        }),
    },
})

const order = ref(null)
const loading = ref(true)
const saving = ref(false)
const deleting = ref(false)
const errorMessage = ref('')
const errors = ref({})
const options = reactive({
    statuses: [],
    entities: [],
    buildings: [],
    goods: [],
    currency_codes: ['RUB'],
})
const form = reactive({
    number: '',
    entity_id: null,
    order_status_id: null,
    building_ids: [],
    currency_code: 'RUB',
    submitted_at: '',
    preferred_delivery_time: '',
    internal_comment: '',
    items: [],
})

let lineKey = 0

const isNew = computed(() => !props.orderId)
const canSave = computed(() => isNew.value
    ? Boolean(props.permissions.create)
    : Boolean(props.permissions.edit))
const pageTitle = computed(() => isNew.value
    ? 'Новый заказ'
    : `Заказ ${order.value?.number || `#${props.orderId}`}`)
const calculatedTotal = computed(() => form.items.reduce((total, item) => {
    const quantity = Number(item.quantity)
    const unitPrice = Number(item.unit_price)

    return total + (
        Number.isFinite(quantity) && Number.isFinite(unitPrice)
            ? quantity * unitPrice
            : 0
    )
}, 0))
const calculatedWeight = computed(() => form.items.reduce((total, item) => {
    const quantity = Number(item.quantity)
    const good = goodById(item.good_id)
    const denominator = Number(good?.denominator)

    return total + (
        Number.isFinite(quantity) && Number.isFinite(denominator)
            ? quantity * denominator
            : 0
    )
}, 0))

useHead(() => ({
    title: `Ameise — ${pageTitle.value}`,
}))

async function loadOptions() {
    const { data } = await axios.get('/api/orders/options')

    Object.assign(options, {
        statuses: data.statuses || [],
        entities: data.entities || [],
        buildings: data.buildings || [],
        goods: data.goods || [],
        currency_codes: data.currency_codes?.length ? data.currency_codes : ['RUB'],
    })
}

async function loadOrder() {
    if (isNew.value) {
        const openStatus = options.statuses.find((status) => status.code === 'open')
        form.order_status_id = openStatus?.id || options.statuses[0]?.id || null
        form.submitted_at = toDateTimeLocal(new Date().toISOString())
        addItem()

        return
    }

    const { data } = await axios.get(`/api/orders/${props.orderId}`)
    order.value = data.data
    fillForm(data.data)
}

function fillForm(source) {
    Object.assign(form, {
        number: source.number || '',
        entity_id: source.entity_id || source.entity?.id || null,
        order_status_id: source.order_status_id || source.status?.id || null,
        building_ids: (source.buildings || []).map((building) => building.id),
        currency_code: source.currency_code || 'RUB',
        submitted_at: toDateTimeLocal(source.submitted_at),
        preferred_delivery_time: source.preferred_delivery_time || '',
        internal_comment: source.internal_comment || '',
        items: (source.items || []).map((item) => makeLine({
            id: item.id,
            good_id: item.good_id,
            quantity: item.quantity,
            unit_price: item.unit_price ?? item.price_gross,
        })),
    })
}

function makeLine(source = {}) {
    lineKey += 1

    return {
        _key: `${Date.now()}-${lineKey}`,
        id: source.id || null,
        good_id: source.good_id || null,
        quantity: source.quantity ?? 1,
        unit_price: source.unit_price ?? null,
    }
}

function addItem() {
    form.items.push(makeLine())
}

function removeItem(index) {
    if (form.items.length <= 1) {
        form.items[0] = makeLine()
        return
    }

    form.items.splice(index, 1)
}

function goodById(goodId) {
    return options.goods.find((good) => Number(good.id) === Number(goodId))
}

function itemTotal(item) {
    const quantity = Number(item.quantity)
    const unitPrice = Number(item.unit_price)

    return Number.isFinite(quantity) && Number.isFinite(unitPrice)
        ? quantity * unitPrice
        : 0
}

function formatNumber(value, digits = 2) {
    const number = Number(value)

    return Number.isFinite(number)
        ? number.toLocaleString('ru-RU', {
            minimumFractionDigits: 0,
            maximumFractionDigits: digits,
        })
        : '—'
}

function formatMoney(value) {
    return `${formatNumber(value)} ${form.currency_code === 'RUB' ? '₽' : form.currency_code}`
}

function toDateTimeLocal(value) {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return String(value).slice(0, 16)
    }

    const offset = date.getTimezoneOffset() * 60000

    return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

function fieldError(field) {
    return errors.value?.[field] || []
}

function lineError(index, field) {
    return fieldError(`items.${index}.${field}`)
}

function payload() {
    return {
        number: form.number || null,
        entity_id: form.entity_id,
        order_status_id: form.order_status_id,
        building_ids: form.building_ids || [],
        currency_code: form.currency_code,
        submitted_at: form.submitted_at || null,
        preferred_delivery_time: form.preferred_delivery_time || null,
        internal_comment: form.internal_comment || null,
        items: form.items.map((item) => ({
            good_id: item.good_id,
            quantity: item.quantity,
            unit_price: item.unit_price === '' ? null : item.unit_price,
        })),
    }
}

async function saveOrder() {
    if (saving.value) {
        return
    }

    saving.value = true
    errors.value = {}
    errorMessage.value = ''

    try {
        const response = isNew.value
            ? await axios.post('/api/orders', payload())
            : await axios.put(`/api/orders/${props.orderId}`, payload())
        const savedOrder = response.data.data

        if (isNew.value) {
            router.visit(orderUrl(savedOrder.id))
            return
        }

        order.value = savedOrder
        fillForm(savedOrder)
    } catch (error) {
        console.error(error)
        errors.value = error.response?.data?.errors || {}
        errorMessage.value = error.response?.data?.message || 'Не удалось сохранить заказ.'
    } finally {
        saving.value = false
    }
}

async function deleteOrder() {
    if (
        isNew.value
        || deleting.value
        || !window.confirm(`Удалить заказ ${order.value?.number || `#${props.orderId}`}?`)
    ) {
        return
    }

    deleting.value = true
    errorMessage.value = ''

    try {
        await axios.delete(`/api/orders/${props.orderId}`)
        router.visit(route('Ameise.orders.index'))
    } catch (error) {
        console.error(error)
        errorMessage.value = 'Не удалось удалить заказ.'
    } finally {
        deleting.value = false
    }
}

function orderUrl(orderId) {
    try {
        return route('Ameise.orders.show', orderId)
    } catch (error) {
        return `/Ameise/orders/${orderId}`
    }
}

function goodUrl(goodId) {
    try {
        return route('Ameise.good.show', goodId)
    } catch (error) {
        return `/Ameise/goods/${goodId}`
    }
}

onMounted(async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        await loadOptions()
        await loadOrder()
    } catch (error) {
        console.error(error)
        errorMessage.value = 'Не удалось загрузить карточку заказа.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <main class="order-card-page">
        <header class="order-card-page__header">
            <div>
                <Link :href="route('Ameise.orders.index')" class="order-card-page__back">
                    <v-icon icon="mdi-arrow-left" size="15" />
                    Все заказы
                </Link>
                <div class="order-card-page__eyebrow">
                    {{ isNew ? 'Создание' : `Order #${orderId}` }}
                </div>
                <h1>{{ pageTitle }}</h1>
            </div>

            <div class="order-card-page__actions">
                <button
                    v-if="!isNew && permissions.delete"
                    type="button"
                    class="is-danger"
                    :disabled="deleting || saving"
                    @click="deleteOrder"
                >
                    <v-icon icon="mdi-delete-outline" size="16" />
                    Удалить
                </button>
                <button
                    v-if="canSave"
                    type="button"
                    class="is-primary"
                    :disabled="loading || deleting"
                    @click="saveOrder"
                >
                    <v-progress-circular v-if="saving" indeterminate size="15" width="2" />
                    <v-icon v-else icon="mdi-content-save-outline" size="16" />
                    {{ isNew ? 'Создать заказ' : 'Сохранить' }}
                </button>
            </div>
        </header>

        <v-progress-linear v-if="loading" indeterminate color="#7f1d1d" height="2" />

        <v-alert
            v-if="errorMessage"
            type="error"
            density="compact"
            variant="tonal"
            class="mb-3"
        >
            {{ errorMessage }}
        </v-alert>

        <template v-if="!loading">
            <section class="order-form-grid">
                <article class="order-panel">
                    <header class="order-panel__header">
                        <div>
                            <span>01</span>
                            <h2>Основное</h2>
                        </div>
                        <small>Entity и состояние заказа</small>
                    </header>

                    <div class="order-panel__body order-panel__body--fields">
                        <v-text-field
                            v-model="form.number"
                            label="Номер заказа (создастся автоматически)"
                            variant="outlined"
                            density="compact"
                            clearable
                            :error-messages="fieldError('number')"
                        />

                        <v-autocomplete
                            v-model="form.entity_id"
                            :items="options.entities"
                            item-title="name"
                            item-value="id"
                            label="Entity"
                            variant="outlined"
                            density="compact"
                            :error-messages="fieldError('entity_id')"
                        />

                        <v-select
                            v-model="form.order_status_id"
                            :items="options.statuses"
                            item-title="name"
                            item-value="id"
                            label="Статус"
                            variant="outlined"
                            density="compact"
                            :error-messages="fieldError('order_status_id')"
                        >
                            <template #item="{ props: itemProps, item }">
                                <v-list-item v-bind="itemProps">
                                    <template #prepend>
                                        <span
                                            class="order-status-dot"
                                            :style="{ background: item.raw.color || '#64748b' }"
                                        />
                                    </template>
                                </v-list-item>
                            </template>
                        </v-select>

                        <v-text-field
                            v-model="form.submitted_at"
                            type="datetime-local"
                            label="Дата создания"
                            variant="outlined"
                            density="compact"
                            :error-messages="fieldError('submitted_at')"
                        />
                    </div>
                </article>

                <article class="order-panel">
                    <header class="order-panel__header">
                        <div>
                            <span>02</span>
                            <h2>Логистика</h2>
                        </div>
                        <small>Buildings выбранного маршрута</small>
                    </header>

                    <div class="order-panel__body order-panel__body--fields">
                        <v-autocomplete
                            v-model="form.building_ids"
                            :items="options.buildings"
                            item-title="address"
                            item-value="id"
                            label="Buildings"
                            variant="outlined"
                            density="compact"
                            multiple
                            chips
                            closable-chips
                            :error-messages="fieldError('building_ids')"
                        >
                            <template #item="{ props: itemProps, item }">
                                <v-list-item
                                    v-bind="itemProps"
                                    :subtitle="[item.raw.city, item.raw.building_type].filter(Boolean).join(' · ')"
                                />
                            </template>
                        </v-autocomplete>

                        <v-text-field
                            v-model="form.preferred_delivery_time"
                            label="Желаемое время поставки"
                            variant="outlined"
                            density="compact"
                            :error-messages="fieldError('preferred_delivery_time')"
                        />

                        <v-textarea
                            v-model="form.internal_comment"
                            label="Внутренний комментарий"
                            variant="outlined"
                            density="compact"
                            rows="4"
                            :error-messages="fieldError('internal_comment')"
                        />
                    </div>
                </article>
            </section>

            <section class="order-panel order-panel--items">
                <header class="order-panel__header">
                    <div>
                        <span>03</span>
                        <h2>Состав заказа</h2>
                    </div>

                    <div class="order-items-toolbar">
                        <v-select
                            v-model="form.currency_code"
                            :items="options.currency_codes"
                            label="Валюта"
                            variant="outlined"
                            density="compact"
                            hide-details
                            class="order-items-toolbar__currency"
                        />
                        <button type="button" @click="addItem">
                            <v-icon icon="mdi-plus" size="14" />
                            Добавить товар
                        </button>
                    </div>
                </header>

                <div class="order-items">
                    <div class="order-items__head" aria-hidden="true">
                        <span>Good</span>
                        <span>Количество</span>
                        <span>Цена</span>
                        <span>Сумма</span>
                        <span />
                    </div>

                    <div
                        v-for="(item, index) in form.items"
                        :key="item._key"
                        class="order-item-row"
                    >
                        <div class="order-item-row__good">
                            <v-autocomplete
                                v-model="item.good_id"
                                :items="options.goods"
                                item-title="name"
                                item-value="id"
                                label="Товар"
                                variant="outlined"
                                density="compact"
                                hide-details="auto"
                                :error-messages="lineError(index, 'good_id')"
                            />
                            <Link
                                v-if="item.good_id"
                                :href="goodUrl(item.good_id)"
                                target="_blank"
                            >
                                Карточка товара
                                <v-icon icon="mdi-open-in-new" size="11" />
                            </Link>
                        </div>

                        <v-text-field
                            v-model="item.quantity"
                            type="number"
                            min="0.001"
                            step="0.001"
                            label="Количество"
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                            :error-messages="lineError(index, 'quantity')"
                        />

                        <v-text-field
                            v-model="item.unit_price"
                            type="number"
                            min="0"
                            step="0.01"
                            label="Цена"
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                            :error-messages="lineError(index, 'unit_price')"
                        />

                        <div class="order-item-row__total">
                            <strong>{{ formatMoney(itemTotal(item)) }}</strong>
                            <small v-if="goodById(item.good_id)?.denominator">
                                {{ formatNumber(Number(item.quantity) * Number(goodById(item.good_id).denominator), 3) }} кг
                            </small>
                        </div>

                        <button
                            type="button"
                            class="order-item-row__remove"
                            aria-label="Удалить позицию"
                            @click="removeItem(index)"
                        >
                            <v-icon icon="mdi-close" size="16" />
                        </button>
                    </div>

                    <div v-if="fieldError('items').length" class="order-items__error">
                        {{ fieldError('items')[0] }}
                    </div>
                </div>

                <footer class="order-total">
                    <div>
                        <span>Позиций</span>
                        <strong>{{ form.items.length }}</strong>
                    </div>
                    <div>
                        <span>Общий вес</span>
                        <strong>{{ formatNumber(calculatedWeight, 3) }} кг</strong>
                    </div>
                    <div class="order-total__money">
                        <span>Итого</span>
                        <strong>{{ formatMoney(calculatedTotal) }}</strong>
                    </div>
                </footer>
            </section>
        </template>
    </main>
</template>

<style scoped>
.order-card-page {
    width: 100%;
    min-height: calc(100vh - 48px);
    padding: 18px;
    background: #f4f5f7;
    color: #252a31;
}

.order-card-page__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 14px;
}

.order-card-page__back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 8px;
    color: #68717b;
    font-size: 10px;
    font-weight: 850;
    text-decoration: none;
}

.order-card-page__eyebrow {
    color: #8f1111;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.order-card-page h1 {
    margin: 1px 0 0;
    font-size: 25px;
    font-weight: 950;
    letter-spacing: -0.04em;
}

.order-card-page__actions {
    display: flex;
    gap: 7px;
}

.order-card-page__actions button,
.order-items-toolbar button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid #cdd2d8;
    border-radius: 6px;
    background: #fff;
    color: #4b535c;
    font-size: 10px;
    font-weight: 900;
}

.order-card-page__actions .is-primary {
    border-color: #7f1d1d;
    background: #7f1d1d;
    color: #fff;
}

.order-card-page__actions .is-danger {
    border-color: #fecaca;
    color: #b91c1c;
}

.order-card-page__actions button:disabled {
    opacity: 0.55;
}

.order-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}

.order-panel {
    overflow: hidden;
    border: 1px solid #d5d9de;
    border-radius: 8px;
    background: #fff;
}

.order-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 54px;
    padding: 9px 12px;
    border-bottom: 1px solid #dde1e5;
    background: #f2f3f5;
}

.order-panel__header > div:first-child {
    display: flex;
    align-items: center;
    gap: 8px;
}

.order-panel__header > div:first-child > span {
    color: #a2a8af;
    font-family: "JetBrains Mono", monospace;
    font-size: 9px;
    font-weight: 900;
}

.order-panel__header h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 950;
}

.order-panel__header small {
    color: #7d858e;
    font-size: 9px;
}

.order-panel__body {
    padding: 12px;
}

.order-panel__body--fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 5px 9px;
}

.order-panel__body--fields > :last-child:nth-child(odd) {
    grid-column: 1 / -1;
}

.order-status-dot {
    display: block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.order-panel--items {
    margin-bottom: 12px;
}

.order-items-toolbar {
    display: flex;
    align-items: center;
    gap: 7px;
}

.order-items-toolbar__currency {
    width: 122px;
}

.order-items-toolbar button {
    border-color: #7f1d1d;
    background: #7f1d1d;
    color: #fff;
}

.order-items {
    padding: 0 12px;
}

.order-items__head,
.order-item-row {
    display: grid;
    grid-template-columns: minmax(260px, 1.7fr) minmax(115px, 0.55fr) minmax(130px, 0.65fr) minmax(135px, 0.65fr) 34px;
    gap: 8px;
    align-items: center;
}

.order-items__head {
    min-height: 32px;
    color: #737c86;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: 0.07em;
    text-transform: uppercase;
}

.order-item-row {
    min-height: 67px;
    padding: 8px 0;
    border-top: 1px solid #e1e4e8;
}

.order-item-row__good {
    display: grid;
    gap: 2px;
}

.order-item-row__good a {
    width: fit-content;
    color: #7f1d1d;
    font-size: 9px;
    font-weight: 800;
    text-decoration: none;
}

.order-item-row__total {
    display: grid;
    gap: 2px;
    justify-items: end;
    font-family: "JetBrains Mono", monospace;
}

.order-item-row__total strong {
    color: #185c4b;
    font-size: 12px;
}

.order-item-row__total small {
    color: #818991;
    font-size: 9px;
}

.order-item-row__remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: 1px solid #fecaca;
    border-radius: 5px;
    color: #b91c1c;
}

.order-items__error {
    padding: 8px 0;
    color: #b91c1c;
    font-size: 10px;
}

.order-total {
    display: flex;
    justify-content: flex-end;
    gap: 24px;
    padding: 12px;
    border-top: 1px solid #d9dde2;
    background: #f4f5f6;
}

.order-total > div {
    display: grid;
    gap: 2px;
    min-width: 110px;
}

.order-total span {
    color: #767f88;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.order-total strong {
    font-family: "JetBrains Mono", monospace;
    font-size: 12px;
}

.order-total__money strong {
    color: #185c4b;
    font-size: 16px;
}

@media (max-width: 1000px) {
    .order-form-grid {
        grid-template-columns: 1fr;
    }

    .order-items__head {
        display: none;
    }

    .order-item-row {
        grid-template-columns: 1fr 1fr 1fr 34px;
    }

    .order-item-row__good {
        grid-column: 1 / -1;
    }
}

@media (max-width: 700px) {
    .order-card-page {
        padding: 10px;
    }

    .order-card-page__header,
    .order-panel__header {
        align-items: flex-start;
        flex-direction: column;
    }

    .order-panel__body--fields,
    .order-item-row {
        grid-template-columns: 1fr;
    }

    .order-item-row__good {
        grid-column: auto;
    }

    .order-item-row__total {
        justify-items: start;
    }

    .order-total {
        align-items: flex-end;
        flex-direction: column;
    }
}
</style>
