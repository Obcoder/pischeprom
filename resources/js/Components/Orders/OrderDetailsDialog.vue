<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, reactive, ref, useId, watch } from 'vue'
import OrderDialogForm from '@/Components/Orders/OrderDialogForm.vue'
import { useOrderDialogEditor } from '@/Composables/useOrderDialogEditor'
import { buildingApartmentLabel } from '@/utils/buildingApartments'

const props = defineProps({
    modelValue: Boolean,
    orderId: { type: [Number, String], default: null },
    editable: { type: Boolean, default: true },
    externalBusy: { type: Boolean, default: false },
    theme: { type: String, default: 'light' },
})
const emit = defineEmits(['update:modelValue', 'saved'])
const titleId = `order-details-${useId()}`
const order = ref(null)
const loading = ref(false)
const error = ref('')
const bodyElement = ref(null)
const saveErrorElement = ref(null)
const editor = reactive(useOrderDialogEditor({
    order,
    orderId: () => props.orderId,
    visible: () => props.modelValue,
    editable: () => props.editable,
    client: axios,
    onSaved: savedOrder => emit('saved', savedOrder),
}))
const busy = computed(() => props.externalBusy || editor.saving || editor.savingDate)
const validationMessages = computed(() => [...new Set(Object.values(editor.errors).flat())])
const orderNumber = computed(() => order.value?.number || (props.orderId ? `#${props.orderId}` : ''))
let controller = null
let requestId = 0
let disposed = false

watch(() => editor.error, message => {
    if (!message) return
    if (bodyElement.value) bodyElement.value.scrollTop = 0
    saveErrorElement.value?.focus({ preventScroll: true })
}, { flush: 'post' })

function cancelRequest() {
    requestId += 1
    controller?.abort()
    controller = null
    loading.value = false
}

function updateDialog(value) {
    if (!value) {
        if (busy.value || !confirmDiscard()) return
        cancelRequest()
        editor.reset()
    }
    emit('update:modelValue', value)
}

function confirmDiscard() {
    return !editor.dirty || window.confirm('Закрыть без сохранения изменений?')
}

function cancelEdit() {
    if (busy.value || !confirmDiscard()) return
    editor.cancelEdit()
    editor.cancelDateEdit()
}

function reloadOrder() {
    if (busy.value || !confirmDiscard()) return
    editor.reset()
    loadOrder()
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
    return [building.postcode, building.city?.region, building.city?.name, building.address, buildingApartmentLabel(building)].filter(Boolean).join(', ') || 'Адрес не указан'
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
    editor.dispose()
})
</script>

<template>
    <v-dialog :model-value="modelValue" :aria-labelledby="titleId" :persistent="busy || editor.dirty" max-width="900" scrollable @update:model-value="updateDialog">
        <v-card class="order-details" :class="{ 'order-details--dark': theme === 'dark' }" :theme="theme">
            <header class="order-details__header">
                <v-icon class="order-details__symbol" icon="mdi-receipt-text-outline" size="23" />
                <div class="order-details__heading">
                    <h2 :id="titleId">Заказ {{ orderNumber }}</h2>
                    <span v-if="editor.editing">Редактирование заказа</span>
                    <span v-else-if="order">{{ formatDate(order.submitted_at || order.created_at, true) }}</span>
                    <span v-else>Детали заказа</span>
                </div>
                <span v-if="order?.status" class="order-details__status">
                    <i :style="{ backgroundColor: order.status.color || '#64748b' }" />{{ order.status.name }}
                </span>
                <button v-if="order && editor.canEdit && !editor.editing" type="button" class="order-details__edit" :disabled="busy || editor.editingDate || editor.stale" title="Редактировать заказ" aria-label="Редактировать заказ" @click="editor.beginEdit">
                    <v-icon icon="mdi-pencil-outline" size="15" /><span>Редактировать</span>
                </button>
                <v-btn class="order-details__close" icon="mdi-close" size="small" variant="text" :disabled="busy" aria-label="Закрыть детали заказа" @click="updateDialog(false)" />
            </header>

            <v-progress-linear v-if="loading" indeterminate :color="theme === 'dark' ? 'deep-purple-lighten-2' : 'brown-darken-3'" height="2" />
            <div ref="bodyElement" class="order-details__body" :aria-busy="loading">
                <div v-if="error" class="order-details__message" role="alert">
                    <v-icon icon="mdi-alert-circle-outline" size="24" />
                    <span>{{ error }}</span>
                    <v-btn size="small" variant="tonal" @click="loadOrder">Повторить</v-btn>
                </div>
                <div v-else-if="loading" class="order-details__message" role="status">Загрузка заказа…</div>
                <template v-else-if="order">
                    <div v-if="editor.error" ref="saveErrorElement" class="order-details__notice order-details__notice--error" role="alert" tabindex="-1">
                        <span>{{ editor.error }}</span>
                        <ul v-if="validationMessages.length"><li v-for="message in validationMessages" :key="message">{{ message }}</li></ul>
                        <button v-if="editor.stale" type="button" class="order-details__action" @click="reloadOrder">Обновить заказ</button>
                        <button v-else-if="editor.editing && !editor.optionsReady" type="button" class="order-details__action" :disabled="editor.loadingOptions" @click="editor.beginEdit">Повторить загрузку справочников</button>
                    </div>
                    <div v-if="editor.success" class="order-details__notice" role="status"><v-icon icon="mdi-check-circle-outline" size="16" />{{ editor.success }}</div>
                    <div class="order-details__totals">
                        <div><span>Сумма заказа</span><strong>{{ formatMoney(editor.editing ? editor.total : order.total_amount, editor.editing ? editor.form.currency_code : order.currency_code) }}</strong></div>
                        <div><span>Общий вес</span><strong>{{ formatNumber(editor.editing ? editor.weight : order.total_weight, 6) }} <small>кг</small></strong></div>
                        <div><span>Позиций</span><strong>{{ editor.editing ? editor.form.items.length : (order.items_count ?? order.items?.length ?? 0) }}</strong></div>
                    </div>
                    <slot name="actions" :order="order" :disabled="busy || editor.editing || editor.editingDate || editor.stale" :editing="editor.editing || editor.editingDate" />

                    <OrderDialogForm v-if="editor.editing" :editor="editor" @cancel="cancelEdit" />
                    <template v-else>
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
                            <form v-if="editor.editingDate" class="order-details__date-form" aria-label="Назначение даты доставки" @submit.prevent="editor.saveDate">
                                <label :for="`${titleId}-date`">Дата доставки</label>
                                <input :id="`${titleId}-date`" v-model="editor.dateDraft" type="date" min="1000-01-01" max="9999-12-31" :disabled="busy || editor.stale" :aria-invalid="!!editor.errors.delivery_date" />
                                <div class="order-details__date-actions">
                                    <button type="submit" class="order-details__action order-details__action--primary" :disabled="busy || editor.stale || !editor.dateDirty">{{ editor.savingDate ? 'Сохранение…' : 'Сохранить дату' }}</button>
                                    <button type="button" class="order-details__action" :disabled="busy" @click="cancelEdit">Отмена</button>
                                </div>
                            </form>
                            <div v-else class="order-details__date-row">
                                <strong>{{ order.delivery_date ? formatDate(order.delivery_date) : 'Дата не назначена' }}</strong>
                                <button v-if="editor.canEditDelivery" type="button" class="order-details__date-edit" :disabled="busy || editor.stale" @click="editor.beginDateEdit"><v-icon icon="mdi-calendar-edit" size="14" />{{ order.delivery_date ? 'Изменить дату' : 'Назначить дату' }}</button>
                            </div>
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
                                    <td>{{ formatNumber(item.quantity, 3) }} {{ item.measurement?.unit_label || item.measure_name || 'единица не задана' }}</td>
                                    <td>{{ formatNumber(item.line_weight, 6) }}</td>
                                    <td>{{ formatMoney(item.unit_price ?? item.price_gross, item.currency_code) }} / {{ item.measurement?.unit_label || item.measure_name || '?' }}</td>
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
                </template>
            </div>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.order-details {
    --details-bg: #fff; --details-text: #252b33; --details-border: #d7dce2;
    --details-header: #f5f6f8; --details-muted: #737c88; --details-accent: #7f1d1d;
    --details-hover: #e7ebef; --details-panel: #fbfcfd; --details-total: #185c4b; --details-note: #fffcf5;
 max-height: 85vh; border: 1px solid var(--details-border); border-radius: 10px; background: var(--details-bg); color: var(--details-text); }
.order-details--dark {
    color-scheme: dark;
    --details-bg: #171a2e; --details-text: #edf0ff; --details-border: #343850;
    --details-header: #1e223a; --details-muted: #a1a8c4; --details-accent: #b5a0ff;
    --details-hover: #303550; --details-panel: #1a1e34; --details-total: #9bdfc3; --details-note: #242039;
    box-shadow: 0 24px 90px #06081499;
}
.order-details__header { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-bottom: 1px solid var(--details-border); background: var(--details-header); }
.order-details__symbol { color: var(--details-accent); }
.order-details__heading { flex: 1; min-width: 0; }
.order-details__heading h2 { margin: 0; overflow-wrap: anywhere; font-size: 16px; font-weight: 800; line-height: 1.3; }
.order-details__heading > span { color: var(--details-muted); font-size: 10px; }
.order-details__status { display: inline-flex; align-items: center; gap: 6px; max-width: 35%; color: var(--details-muted); font-size: 11px; font-weight: 700; overflow-wrap: anywhere; }
.order-details__status i { flex: 0 0 7px; width: 7px; height: 7px; border-radius: 50%; }
.order-details__edit { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; gap: 5px; height: 30px; padding: 0 7px; font-size: 10px; border-radius: 4px; color: var(--details-muted); }
.order-details__edit:hover { background: var(--details-hover); color: var(--details-accent); }
.order-details button:focus-visible { outline: 2px solid var(--details-accent); outline-offset: 2px; }
.order-details button:disabled { opacity: .5; cursor: default; }
.order-details__notice { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; padding: 10px 16px; border-bottom: 1px solid var(--details-border); color: var(--details-total); background: var(--details-panel); font-size: 12px; }
.order-details__notice--error { color: #d85e70; }
.order-details__notice ul { flex-basis: 100%; margin: 0; padding-left: 18px; }
.order-details__date-row { display: flex; align-items: center; flex-wrap: wrap; gap: 5px 10px; }
.order-details__date-edit { display: inline-flex; align-items: center; gap: 4px; color: var(--details-accent); font-size: 10px; }
.order-details__date-form { display: grid; gap: 6px; max-width: 320px; }
.order-details__date-form label { color: var(--details-muted); font-size: 10px; }
.order-details__date-form input { min-width: 0; width: 100%; height: 34px; padding: 5px 9px; border: 1px solid var(--details-border); border-radius: 5px; color: var(--details-text); background: var(--details-panel); font-size: 12px; }
.order-details__date-form input:focus { outline: 2px solid var(--details-accent); outline-offset: 1px; }
.order-details__date-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.order-details__action { min-height: 30px; padding: 5px 10px; border: 1px solid var(--details-border); border-radius: 5px; font-size: 11px; color: var(--details-text); background: var(--details-panel); }
.order-details__action--primary { color: var(--details-bg); border-color: var(--details-accent); background: var(--details-accent); }
.order-details__body { min-height: 130px; overflow: auto; }
.order-details__message { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 10px; min-height: 160px; padding: 20px; color: var(--details-muted); font-size: 13px; text-align: center; }
.order-details__totals { display: grid; grid-template-columns: 1.4fr 1fr 0.6fr; border-bottom: 1px solid var(--details-border); background: var(--details-panel); }
.order-details__totals > div { display: flex; flex-direction: column; gap: 3px; padding: 12px 16px; border-right: 1px solid var(--details-border); }
.order-details__totals > div:last-child { border-right: 0; }
.order-details__totals span { color: var(--details-muted); font-size: 10px; }
.order-details__totals strong { font-size: 18px; font-variant-numeric: tabular-nums; }
.order-details__totals > div:first-child strong { color: var(--details-total); }
.order-details__totals small { font-size: 12px; font-weight: 500; }
.order-details__info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 14px 16px; border-bottom: 1px solid var(--details-border); font-size: 12px; line-height: 1.6; overflow-wrap: anywhere; }
.order-details h3 { display: flex; align-items: center; gap: 5px; margin: 0 0 6px; color: var(--details-muted); font-size: 10px; font-weight: 700; }
.order-details__muted { color: var(--details-muted); font-size: 11px; }
.order-details__phone { display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; color: var(--details-accent); font-variant-numeric: tabular-nums; text-decoration: none; }
.order-details__phone:hover { text-decoration: underline; }
.order-details__address { margin-top: 5px; }
.order-details__multiline { white-space: pre-wrap; }
.order-details__items { overflow-x: auto; }
.order-details__items table { width: 100%; border-collapse: collapse; }
.order-details__items th, .order-details__items td { padding: 9px 12px; border-bottom: 1px solid var(--details-border); text-align: right; font-size: 11px; font-variant-numeric: tabular-nums; white-space: nowrap; }
.order-details__items th { background: var(--details-header); color: var(--details-muted); font-size: 10px; font-weight: 700; }
.order-details__items th:first-child, .order-details__items td:first-child { min-width: 180px; text-align: left; white-space: normal; overflow-wrap: anywhere; }
.order-details__items td strong { font-size: 12px; }
.order-details__items td small { display: block; margin-top: 2px; color: var(--details-muted); font-size: 10px; }
.order-details__items td:last-child { font-weight: 700; }
.order-details__items .order-details__empty { text-align: center; color: var(--details-muted); }
.order-details__comment { padding: 14px 16px; border-bottom: 1px solid var(--details-border); background: var(--details-note); }
.order-details__comment p { margin: 0; font-size: 12px; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.order-details__footer { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; padding: 9px 16px; color: var(--details-muted); font-size: 10px; }
@media (max-width: 600px) {
    .order-details__header { display: grid; grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: 'heading close' 'status edit'; gap: 6px 8px; padding: 10px 12px; }
    .order-details__heading { grid-area: heading; }
    .order-details__close { grid-area: close; justify-self: end; }
    .order-details__edit { grid-area: edit; justify-self: end; }
    .order-details__heading h2 { font-size: 14px; }
    .order-details__symbol { display: none; }
    .order-details__status { grid-area: status; max-width: 100%; font-size: 10px; }
    .order-details__totals > div { padding: 10px; }
    .order-details__totals strong { font-size: 15px; }
    .order-details__info { grid-template-columns: 1fr; gap: 14px; padding: 12px; }
}
</style>
